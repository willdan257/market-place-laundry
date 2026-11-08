<?php

namespace App\Models;

use Exception;
use App\Services\NotificationService;

class Order extends BaseModel
{
    protected string $table = 'laundry_orders';

    // ──────────────────────────────────────────────────────────────────
    // STATUS CONSTANTS  (satu tempat — ubah di sini jika ada status baru)
    // ──────────────────────────────────────────────────────────────────

    /** Status saat kurir penjemputan masih AKTIF bekerja */
    public const PICKUP_ACTIVE_STATUSES = [
        'driver_assigned',
        'driver_on_the_way',
        'arrived_at_customer',
        'picked_up',
    ];

    /** Status penjemputan dianggap SELESAI (arrived_at_store ke atas) */
    public const PICKUP_DONE_STATUSES = [
        'arrived_at_store',
        'weighed',
        'waiting_payment',
        'in_process',
        'ready_for_delivery',
        'delivery_assigned',
        'delivered',
        'completed',
        'cancelled',
    ];

    /** Status saat kurir pengantaran masih AKTIF bekerja */
    public const DELIVERY_ACTIVE_STATUSES = [
        'delivery_assigned',
    ];

    /** Status pengantaran dianggap SELESAI */
    public const DELIVERY_DONE_STATUSES = [
        'delivered',
        'completed',
        'cancelled',
    ];

    /** Status order yang masuk Riwayat Pelanggan (bukan aktif) */
    public const CUSTOMER_HISTORY_STATUSES = [
        'delivered',
        'completed',
        'cancelled',
    ];

    /** Persentase ongkir yang menjadi pendapatan kurir per tahap */
    public const COURIER_FEE_SHARE = 0.5;

    public function generateUniqueOrderNumber(): string
    {
        $pdo = self::getPdo();
        do {
            $dateStr = date('Ymd');
            $randomCode = strtoupper(bin2hex(random_bytes(2)));
            $orderNumber = "ORD-{$dateStr}-{$randomCode}";

            $stmt = $pdo->prepare("SELECT id FROM {$this->table} WHERE order_number = :num LIMIT 1");
            $stmt->execute(['num' => $orderNumber]);
            $exists = $stmt->fetch();
        } while ($exists);

        return $orderNumber;
    }

    public function createOrderTransaction(array $orderData, array $serviceItemData, array $addressData): array
    {
        $pdo = self::getPdo();
        if (!$pdo) {
            throw new Exception("Koneksi database tidak tersedia.");
        }

        try {
            $pdo->beginTransaction();

            $addressModel = new CustomerAddress();
            $addressModel->savePrimaryAddress($orderData['customer_id'], $addressData);
            $primaryAddress = $addressModel->getPrimaryAddress($orderData['customer_id']);

            if (!$primaryAddress) {
                throw new Exception("Gagal menyimpan alamat pengiriman.");
            }

            $orderNumber = $this->generateUniqueOrderNumber();

            $distanceKm  = (float) ($orderData['distance_km'] ?? 0);
            $shippingFee = (float) ($orderData['shipping_fee'] ?? 0);

            $sqlOrder = "INSERT INTO {$this->table} (
                            order_number, customer_id, store_id, address_id,
                            weight_kg, distance_km, subtotal_amount, shipping_fee, additional_fee,
                            discount_amount, total_amount, order_status, notes
                        ) VALUES (
                            :order_number, :customer_id, :store_id, :address_id,
                            0.00, :distance_km, :subtotal, :shipping_fee, 0.00,
                            0.00, :total, 'pending_pickup', :notes
                        )";

            $stmtOrder = $pdo->prepare($sqlOrder);
            $stmtOrder->execute([
                'order_number' => $orderNumber,
                'customer_id'  => $orderData['customer_id'],
                'store_id'     => $orderData['store_id'],
                'address_id'   => $primaryAddress['id'],
                'distance_km'  => $distanceKm,
                'subtotal'     => $serviceItemData['price_per_unit'] * $serviceItemData['qty'],
                'shipping_fee' => $shippingFee,
                'total'        => ($serviceItemData['price_per_unit'] * $serviceItemData['qty']) + $shippingFee,
                'notes'        => $orderData['notes'] ?? null
            ]);

            $orderId = (int) $pdo->lastInsertId();

            $sqlItem = "INSERT INTO order_items (order_id, service_id, qty, price_per_unit, subtotal, notes)
                        VALUES (:order_id, :service_id, :qty, :price, :subtotal, :notes)";

            $stmtItem = $pdo->prepare($sqlItem);
            $stmtItem->execute([
                'order_id' => $orderId,
                'service_id' => $serviceItemData['service_id'],
                'qty' => $serviceItemData['qty'],
                'price' => $serviceItemData['price_per_unit'],
                'subtotal' => $serviceItemData['price_per_unit'] * $serviceItemData['qty'],
                'notes' => $orderData['notes'] ?? null
            ]);

            $sqlHistory = "INSERT INTO order_status_histories (order_id, previous_status, new_status, notes, updated_by)
                           VALUES (:order_id, NULL, 'pending_pickup', 'Pesanan laundry berhasil dibuat oleh pelanggan.', :updated_by)";

            $stmtHistory = $pdo->prepare($sqlHistory);
            $stmtHistory->execute([
                'order_id' => $orderId,
                'updated_by' => $orderData['customer_id']
            ]);

            $pdo->commit();

            // Notifikasi untuk pelanggan saat pesanan dibuat (di luar transaksi)
            try {
                $notifService = new NotificationService();
                $notifService->send(
                    $orderData['customer_id'],
                    'Pesanan Berhasil Dibuat',
                    "Pesanan laundry #{$orderNumber} berhasil dibuat dan sedang menunggu penugasan kurir.",
                    'order_update',
                    $orderId
                );
            } catch (\Throwable $e) {
                error_log("[NOTIF_CREATE_ORDER_ERROR] " . $e->getMessage());
            }

            // Notifikasi WhatsApp ke Admin/Pemilik Toko saat ada pesanan baru
            try {
                require_once __DIR__ . '/../Helpers/wa_notif.php';
                if (function_exists('notif_pesanan_baru_ke_admin_toko')) {
                    notif_pesanan_baru_ke_admin_toko($orderId);
                }
            } catch (\Throwable $e) {
                error_log("[WA_NEW_ORDER_CALL_ERROR] " . $e->getMessage());
            }

            return [
                'id' => $orderId,
                'order_number' => $orderNumber
            ];
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Atomically update status order, insert history audit, and create notification for customer.
     */
    public function updateOrderStatusWithNotification(
        int $orderId,
        string $newStatus,
        string $statusTitle,
        string $statusNotes,
        int $updatedByUserId
    ): bool {
        $pdo = self::getPdo();
        if (!$pdo) {
            return false;
        }

        $wasInTransaction = $pdo->inTransaction();

        try {
            if (!$wasInTransaction) {
                $pdo->beginTransaction();
            }

            // Fetch current order info
            $stmtCurrent = $pdo->prepare("SELECT customer_id, order_number, order_status FROM {$this->table} WHERE id = :id LIMIT 1 FOR UPDATE");
            $stmtCurrent->execute(['id' => $orderId]);
            $order = $stmtCurrent->fetch();

            if (!$order) {
                if (!$wasInTransaction && $pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                return false;
            }

            $previousStatus = $order['order_status'];

            // 1. Update Order Status
            $stmtUpdate = $pdo->prepare("UPDATE {$this->table} SET order_status = :status WHERE id = :id");
            $stmtUpdate->execute([
                'status' => $newStatus,
                'id' => $orderId
            ]);

            // 2. Insert into order_status_histories
            $stmtHistory = $pdo->prepare("INSERT INTO order_status_histories (order_id, previous_status, new_status, notes, updated_by) VALUES (:order_id, :prev, :new, :notes, :by)");
            $stmtHistory->execute([
                'order_id' => $orderId,
                'prev' => $previousStatus,
                'new' => $newStatus,
                'notes' => $statusNotes,
                'by' => $updatedByUserId
            ]);

            if (!$wasInTransaction) {
                $pdo->commit();
            }

            // 3. Create Notification for Customer (di luar transaksi)
            try {
                $notifMessage = "Status pesanan #{$order['order_number']} diperbarui: {$statusTitle}. {$statusNotes}";
                $notifService = new NotificationService();
                $notifService->send($order['customer_id'], $statusTitle, $notifMessage, 'order_update', $orderId);
            } catch (\Throwable $e) {
                error_log("[NOTIF_STATUS_UPDATE_ERROR] " . $e->getMessage());
            }

            // Panggil notif_status_pesanan() tepat setelah UPDATE status berhasil
            try {
                require_once __DIR__ . '/../Helpers/wa_notif.php';
                notif_status_pesanan($orderId, $newStatus, $statusTitle, $statusNotes, $updatedByUserId);
            } catch (\Throwable $e) {
                error_log("[WA_NOTIF_CALL_ERROR] " . $e->getMessage());
            }

            return true;
        } catch (Exception $e) {
            if (!$wasInTransaction && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            return false;
        }
    }

    /**
     * Assign Driver to Order (Executed by Owner)
     */
    public function assignDriverToOrder(int $orderId, int $driverId, int $ownerUserId): bool
    {
        $pdo = self::getPdo();
        if (!$pdo) {
            return false;
        }

        $wasInTransaction = $pdo->inTransaction();

        try {
            if (!$wasInTransaction) {
                $pdo->beginTransaction();
            }

            $stmtCurrent = $pdo->prepare("SELECT customer_id, order_number, order_status FROM {$this->table} WHERE id = :id LIMIT 1 FOR UPDATE");
            $stmtCurrent->execute(['id' => $orderId]);
            $order = $stmtCurrent->fetch();

            if (!$order) {
                if (!$wasInTransaction && $pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                return false;
            }

            $driverModel = new Driver();
            $driverInfo = $driverModel->findWithUser($driverId);
            $driverName = $driverInfo['driver_name'] ?? 'Kurir';

            $isDeliveryAssignment = in_array($order['order_status'], ['in_process', 'ready_for_delivery', 'delivery_assigned']);

            if ($isDeliveryAssignment) {
                $newStatus = 'delivery_assigned';
                $stmtUpdate = $pdo->prepare("UPDATE {$this->table} SET delivery_driver_id = :driver_id, order_status = 'delivery_assigned' WHERE id = :id");
                $historyNotes = "Kurir {$driverName} telah ditugaskan untuk pengantaran laundry.";
                $notifTitle = "Kurir Pengantaran Ditugaskan";
                $notifMsg = "Kurir {$driverName} telah ditugaskan mengantarkan pesanan laundry #{$order['order_number']} ke alamat Anda.";
            } else {
                $newStatus = 'driver_assigned';
                $stmtUpdate = $pdo->prepare("UPDATE {$this->table} SET pickup_driver_id = :driver_id, order_status = 'driver_assigned' WHERE id = :id");
                $historyNotes = "Kurir {$driverName} telah ditugaskan untuk penjemputan laundry.";
                $notifTitle = "Kurir Ditugaskan";
                $notifMsg = "Kurir {$driverName} telah ditugaskan menjemput pesanan laundry #{$order['order_number']}.";
            }

            $stmtUpdate->execute([
                'driver_id' => $driverId,
                'id' => $orderId
            ]);

            // Audit history
            $stmtHistory = $pdo->prepare("INSERT INTO order_status_histories (order_id, previous_status, new_status, notes, updated_by) VALUES (:order_id, :prev, :new_status, :notes, :by)");
            $stmtHistory->execute([
                'order_id' => $orderId,
                'prev' => $order['order_status'],
                'new_status' => $newStatus,
                'notes' => $historyNotes,
                'by' => $ownerUserId
            ]);

            if (!$wasInTransaction) {
                $pdo->commit();
            }

            // Customer notification (di luar transaksi)
            try {
                $notifService = new NotificationService();
                $notifService->send(
                    $order['customer_id'],
                    $notifTitle,
                    $notifMsg,
                    'order_update',
                    $orderId
                );

                // Courier notification
                $courierUserId = (int) ($driverInfo['user_id'] ?? 0);
                if ($courierUserId > 0) {
                    $taskType = $isDeliveryAssignment ? 'antar' : 'jemput';
                    $courierNotifTitle = "Penugasan Order #" . $order['order_number'];
                    $courierNotifMsg = "Anda ditugaskan untuk order #{$order['order_number']} ({$taskType}).";
                    $notifService->send(
                        $courierUserId,
                        $courierNotifTitle,
                        $courierNotifMsg,
                        'order_update',
                        $orderId
                    );
                }
            } catch (\Throwable $e) {
                error_log("[NOTIF_ASSIGN_DRIVER_ERROR] " . $e->getMessage());
            }

            // Panggil notif_status_pesanan() tepat setelah penugasan kurir berhasil
            try {
                require_once __DIR__ . '/../Helpers/wa_notif.php';
                notif_status_pesanan($orderId, $newStatus, $notifTitle ?? '', $historyNotes ?? '', $ownerUserId);
            } catch (\Throwable $e) {
                error_log("[WA_NOTIF_CALL_ERROR] " . $e->getMessage());
            }

            return true;
        } catch (Exception $e) {
            if (!$wasInTransaction && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            return false;
        }
    }

    public function getCustomerOrders(int $customerId): array
    {
        return $this->query("
            SELECT o.*, st.store_name, s.service_name
            FROM {$this->table} o
            JOIN stores st ON o.store_id = st.id
            LEFT JOIN order_items oi ON o.id = oi.order_id
            LEFT JOIN services s ON oi.service_id = s.id
            WHERE o.customer_id = :customer_id
            ORDER BY o.id DESC
        ", ['customer_id' => $customerId]);
    }

    /**
     * Pesanan AKTIF pelanggan (belum selesai / belum dibatalkan).
     */
    public function getActiveCustomerOrders(int $customerId): array
    {
        $historyPlaceholders = implode(',', array_fill(0, count(self::CUSTOMER_HISTORY_STATUSES), '?'));
        $pdo = self::getPdo();
        if (!$pdo) return [];

        $sql = "
            SELECT o.*, st.store_name, s.service_name
            FROM {$this->table} o
            JOIN stores st ON o.store_id = st.id
            LEFT JOIN order_items oi ON o.id = oi.order_id
            LEFT JOIN services s ON oi.service_id = s.id
            WHERE o.customer_id = ?
              AND o.order_status NOT IN ({$historyPlaceholders})
            ORDER BY o.id DESC
        ";
        $params = array_merge([$customerId], self::CUSTOMER_HISTORY_STATUSES);
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Pesanan RIWAYAT pelanggan (selesai / dibatalkan / diantarkan).
     */
    public function getCustomerOrderHistory(int $customerId): array
    {
        $historyPlaceholders = implode(',', array_fill(0, count(self::CUSTOMER_HISTORY_STATUSES), '?'));
        $pdo = self::getPdo();
        if (!$pdo) return [];

        $sql = "
            SELECT o.*, st.store_name, s.service_name
            FROM {$this->table} o
            JOIN stores st ON o.store_id = st.id
            LEFT JOIN order_items oi ON o.id = oi.order_id
            LEFT JOIN services s ON oi.service_id = s.id
            WHERE o.customer_id = ?
              AND o.order_status IN ({$historyPlaceholders})
            ORDER BY o.id DESC
        ";
        $params = array_merge([$customerId], self::CUSTOMER_HISTORY_STATUSES);
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function getAssignedDriverOrders(int $driverId): array
    {
        return $this->query("
            SELECT o.*, st.store_name, ca.recipient_name, ca.recipient_phone, ca.full_address, ca.rt, ca.rw, ca.district, ca.city, s.service_name
            FROM {$this->table} o
            JOIN stores st ON o.store_id = st.id
            JOIN customer_addresses ca ON o.address_id = ca.id
            LEFT JOIN order_items oi ON o.id = oi.order_id
            LEFT JOIN services s ON oi.service_id = s.id
            WHERE o.pickup_driver_id = :d1 OR o.delivery_driver_id = :d2
            ORDER BY o.id DESC
        ", ['d1' => $driverId, 'd2' => $driverId]);
    }

    /**
     * Tugas AKTIF kurir: penjemputan aktif ATAU pengantaran aktif.
     * Tiap baris mengandung 'task_type' ("Penjemputan" / "Pengantaran").
     */
    public function getActiveDriverTasks(int $driverId): array
    {
        $pdo = self::getPdo();
        if (!$pdo) return [];

        $pickupIn  = implode(',', array_fill(0, count(self::PICKUP_ACTIVE_STATUSES), '?'));
        $deliveryIn = implode(',', array_fill(0, count(self::DELIVERY_ACTIVE_STATUSES), '?'));

        // Penjemputan aktif: kurir adalah pickup_driver_id dan status dalam PICKUP_ACTIVE
        $sqlPickup = "
            SELECT o.*, st.store_name,
                   ca.recipient_name, ca.recipient_phone, ca.full_address, ca.rt, ca.rw, ca.district, ca.city, ca.latitude, ca.longitude,
                   s.service_name,
                   'Penjemputan' AS task_type
            FROM {$this->table} o
            JOIN stores st ON o.store_id = st.id
            JOIN customer_addresses ca ON o.address_id = ca.id
            LEFT JOIN order_items oi ON o.id = oi.order_id
            LEFT JOIN services s ON oi.service_id = s.id
            WHERE o.pickup_driver_id = ?
              AND o.order_status IN ({$pickupIn})
        ";

        // Pengantaran aktif: kurir adalah delivery_driver_id dan status dalam DELIVERY_ACTIVE
        $sqlDelivery = "
            SELECT o.*, st.store_name,
                   ca.recipient_name, ca.recipient_phone, ca.full_address, ca.rt, ca.rw, ca.district, ca.city, ca.latitude, ca.longitude,
                   s.service_name,
                   'Pengantaran' AS task_type
            FROM {$this->table} o
            JOIN stores st ON o.store_id = st.id
            JOIN customer_addresses ca ON o.address_id = ca.id
            LEFT JOIN order_items oi ON o.id = oi.order_id
            LEFT JOIN services s ON oi.service_id = s.id
            WHERE o.delivery_driver_id = ?
              AND o.order_status IN ({$deliveryIn})
        ";

        $paramsPickup   = array_merge([$driverId], self::PICKUP_ACTIVE_STATUSES);
        $paramsDelivery = array_merge([$driverId], self::DELIVERY_ACTIVE_STATUSES);

        $stmtP = $pdo->prepare($sqlPickup);
        $stmtP->execute($paramsPickup);
        $pickup = $stmtP->fetchAll(\PDO::FETCH_ASSOC);

        $stmtD = $pdo->prepare($sqlDelivery);
        $stmtD->execute($paramsDelivery);
        $delivery = $stmtD->fetchAll(\PDO::FETCH_ASSOC);

        // Gabungkan, sorted by id DESC
        $all = array_merge($pickup, $delivery);
        usort($all, fn($a, $b) => (int)$b['id'] - (int)$a['id']);
        return $all;
    }

    /**
     * Riwayat tugas SELESAI kurir (max 50, untuk paginasi).
     * task_type, completed_at, dan earning per tahap sudah dihitung.
     */
    public function getDriverHistory(int $driverId, int $limit = 50, int $offset = 0): array
    {
        $pdo = self::getPdo();
        if (!$pdo) return [];

        $pickupDoneIn   = implode(',', array_fill(0, count(self::PICKUP_DONE_STATUSES), '?'));
        $deliveryDoneIn = implode(',', array_fill(0, count(self::DELIVERY_DONE_STATUSES), '?'));

        // Penjemputan SELESAI
        $sqlPickup = "
            SELECT o.id, o.order_number, o.shipping_fee, o.order_status, o.updated_at,
                   st.store_name,
                   ca.recipient_name,
                   s.service_name,
                   'Penjemputan' AS task_type,
                   COALESCE(
                       (SELECT osh.created_at FROM order_status_histories osh
                        WHERE osh.order_id = o.id AND osh.new_status = 'arrived_at_store'
                        ORDER BY osh.id DESC LIMIT 1),
                       o.updated_at
                   ) AS completed_at
            FROM {$this->table} o
            JOIN stores st ON o.store_id = st.id
            JOIN customer_addresses ca ON o.address_id = ca.id
            LEFT JOIN order_items oi ON o.id = oi.order_id
            LEFT JOIN services s ON oi.service_id = s.id
            WHERE o.pickup_driver_id = ?
              AND o.order_status IN ({$pickupDoneIn})
        ";

        // Pengantaran SELESAI
        $sqlDelivery = "
            SELECT o.id, o.order_number, o.shipping_fee, o.order_status, o.updated_at,
                   st.store_name,
                   ca.recipient_name,
                   s.service_name,
                   'Pengantaran' AS task_type,
                   COALESCE(
                       (SELECT osh.created_at FROM order_status_histories osh
                        WHERE osh.order_id = o.id AND osh.new_status = 'delivered'
                        ORDER BY osh.id DESC LIMIT 1),
                       o.updated_at
                   ) AS completed_at
            FROM {$this->table} o
            JOIN stores st ON o.store_id = st.id
            JOIN customer_addresses ca ON o.address_id = ca.id
            LEFT JOIN order_items oi ON o.id = oi.order_id
            LEFT JOIN services s ON oi.service_id = s.id
            WHERE o.delivery_driver_id = ?
              AND o.order_status IN ({$deliveryDoneIn})
        ";

        $paramsPickup   = array_merge([$driverId], self::PICKUP_DONE_STATUSES);
        $paramsDelivery = array_merge([$driverId], self::DELIVERY_DONE_STATUSES);

        $stmtP = $pdo->prepare($sqlPickup);
        $stmtP->execute($paramsPickup);
        $pickup = $stmtP->fetchAll(\PDO::FETCH_ASSOC);

        $stmtD = $pdo->prepare($sqlDelivery);
        $stmtD->execute($paramsDelivery);
        $delivery = $stmtD->fetchAll(\PDO::FETCH_ASSOC);

        $all = array_merge($pickup, $delivery);

        // Hitung earning per baris (tidak termasuk cancelled)
        foreach ($all as &$row) {
            if ($row['order_status'] === 'cancelled') {
                $row['earning'] = 0.0;
            } else {
                $row['earning'] = round((float)$row['shipping_fee'] * self::COURIER_FEE_SHARE, 2);
            }
        }
        unset($row);

        // Sort by completed_at DESC
        usort($all, fn($a, $b) => strcmp($b['completed_at'], $a['completed_at']));

        // Paginasi
        return array_slice($all, $offset, $limit);
    }

    /**
     * Total riwayat untuk paginasi.
     */
    public function getDriverHistoryCount(int $driverId): int
    {
        return count($this->getDriverHistory($driverId, 9999, 0));
    }

    /**
     * Pendapatan kurir berdasarkan periode.
     * Mengembalikan: today, week, month, total, completed_count.
     */
    public function getDriverEarnings(int $driverId): array
    {
        $history = $this->getDriverHistory($driverId, 9999, 0);

        $now   = new \DateTime('now', new \DateTimeZone('Asia/Jakarta'));
        $today = $now->format('Y-m-d');
        $weekStart  = (clone $now)->modify('monday this week')->format('Y-m-d');
        $monthStart = $now->format('Y-m-01');

        $earnings = [
            'today'           => 0.0,
            'week'            => 0.0,
            'month'           => 0.0,
            'total'           => 0.0,
            'completed_count' => 0,
            'today_count'     => 0,
            'week_count'      => 0,
            'month_count'     => 0,
        ];

        foreach ($history as $row) {
            if ($row['order_status'] === 'cancelled') continue;
            $e = (float) $row['earning'];
            $date = substr($row['completed_at'], 0, 10);

            $earnings['total'] += $e;
            $earnings['completed_count']++;
            if ($date === $today) {
                $earnings['today'] += $e;
                $earnings['today_count']++;
            }
            if ($date >= $weekStart) {
                $earnings['week']  += $e;
                $earnings['week_count']++;
            }
            if ($date >= $monthStart) {
                $earnings['month'] += $e;
                $earnings['month_count']++;
            }
        }

        return $earnings;
    }

    public function getStoreOrders(int $storeId): array
    {
        return $this->query("
            SELECT o.*, u.name as customer_name, u.phone as customer_phone,
                   du.name as driver_name, s.service_name, oi.price_per_unit, s.unit
            FROM {$this->table} o
            JOIN users u ON o.customer_id = u.id
            LEFT JOIN drivers d ON o.pickup_driver_id = d.id
            LEFT JOIN users du ON d.user_id = du.id
            LEFT JOIN order_items oi ON o.id = oi.order_id
            LEFT JOIN services s ON oi.service_id = s.id
            WHERE o.store_id = :store_id
            ORDER BY o.id DESC
        ", ['store_id' => $storeId]);
    }

    /**
     * Mengambil pesanan aktif toko (bukan delivered, completed, cancelled)
     */
    public function getActiveStoreOrders(int $storeId): array
    {
        $historyPlaceholders = implode(',', array_fill(0, count(self::CUSTOMER_HISTORY_STATUSES), '?'));
        $pdo = self::getPdo();
        if (!$pdo) return [];

        $sql = "
            SELECT o.*, u.name as customer_name, u.phone as customer_phone,
                   du.name as driver_name,
                   (SELECT s.service_name FROM order_items oi JOIN services s ON oi.service_id = s.id WHERE oi.order_id = o.id LIMIT 1) as service_name,
                   (SELECT oi.price_per_unit FROM order_items oi WHERE oi.order_id = o.id LIMIT 1) as price_per_unit,
                   (SELECT s.unit FROM order_items oi JOIN services s ON oi.service_id = s.id WHERE oi.order_id = o.id LIMIT 1) as unit
            FROM {$this->table} o
            JOIN users u ON o.customer_id = u.id
            LEFT JOIN drivers d ON o.pickup_driver_id = d.id
            LEFT JOIN users du ON d.user_id = du.id
            WHERE o.store_id = ?
              AND o.order_status NOT IN ({$historyPlaceholders})
            ORDER BY o.id DESC
        ";
        $params = array_merge([$storeId], self::CUSTOMER_HISTORY_STATUSES);
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function getOrderDetail(int $orderId, ?int $customerId = null): ?array
    {
        $params = ['id' => $orderId];
        $whereCust = "";
        if ($customerId !== null) {
            $whereCust = " AND o.customer_id = :customer_id";
            $params['customer_id'] = $customerId;
        }

        $orders = $this->query("
            SELECT o.*, st.store_name, st.full_address as store_address,
                   ca.recipient_name, ca.recipient_phone, ca.full_address as pickup_address,
                   ca.rt, ca.rw, ca.district, ca.city, ca.latitude, ca.longitude,
                   du.name as driver_name, du.phone as driver_phone, dr.vehicle_type, dr.license_plate
            FROM {$this->table} o
            JOIN stores st ON o.store_id = st.id
            JOIN customer_addresses ca ON o.address_id = ca.id
            LEFT JOIN drivers dr ON o.pickup_driver_id = dr.id
            LEFT JOIN users du ON dr.user_id = du.id
            WHERE o.id = :id {$whereCust}
            LIMIT 1
        ", $params);

        if (empty($orders)) {
            return null;
        }

        $order = $orders[0];

        $itemModel = new OrderItem();
        $order['items'] = $itemModel->getItemsByOrderId($orderId);

        $historyModel = new OrderStatusHistory();
        $order['histories'] = $historyModel->getHistoryByOrderId($orderId);

        return $order;
    }

    public function findByOrderNumber(string $orderNumber): ?array
    {
        $results = $this->query(
            "SELECT * FROM {$this->table} WHERE order_number = :num LIMIT 1",
            ['num' => $orderNumber]
        );
        return $results[0] ?? null;
    }

    /**
     * Update weight and recalculate total (called by Owner after weighing).
     */
    public function updateWeightAndTotal(int $orderId, float $weightKg, float $pricePerUnit): bool
    {
        $subtotal    = round($weightKg * $pricePerUnit, 2);
        $pdo         = self::getPdo();

        // Get existing shipping_fee and additional_fee, discount
        $stmt = $pdo->prepare("SELECT shipping_fee, additional_fee, discount_amount FROM {$this->table} WHERE id = :id LIMIT 1");
        $stmt->execute(['id' => $orderId]);
        $row = $stmt->fetch();
        if (!$row) return false;

        $total = $subtotal + (float)$row['shipping_fee'] + (float)$row['additional_fee'] - (float)$row['discount_amount'];

        return $this->execute(
            "UPDATE {$this->table} SET weight_kg = :wt, subtotal_amount = :sub, total_amount = :total WHERE id = :id",
            ['wt' => $weightKg, 'sub' => $subtotal, 'total' => max(0, $total), 'id' => $orderId]
        );
    }
}

