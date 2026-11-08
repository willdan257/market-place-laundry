<?php

use App\Models\BaseModel;
use App\Models\Setting;
use App\Services\WhatsAppService;

if (!function_exists('notif_pesanan_baru_ke_admin_toko')) {
    /**
     * Kirim notifikasi WhatsApp ke Admin/Pemilik Toko Laundry saat ada pesanan baru masuk.
     * Berisi: nomor pesanan, nama customer, layanan, alamat jemput, dan jadwal/waktu.
     */
    function notif_pesanan_baru_ke_admin_toko(int $orderId): bool
    {
        try {
            $pdo = BaseModel::getPdo();
            if (!$pdo) {
                return false;
            }

            // Ambil data pesanan lengkap dengan pemilik toko dan alamat jemput
            $stmt = $pdo->prepare("
                SELECT 
                    o.id, o.order_number, o.created_at, o.notes,
                    st.store_name,
                    ou.name as owner_name, ou.phone as owner_phone,
                    cu.name as customer_name, cu.phone as customer_phone,
                    ca.full_address, ca.rt, ca.rw, ca.district, ca.city,
                    s.service_name
                FROM laundry_orders o
                JOIN stores st ON st.id = o.store_id
                JOIN users ou ON ou.id = st.owner_id
                JOIN users cu ON cu.id = o.customer_id
                JOIN customer_addresses ca ON ca.id = o.address_id
                LEFT JOIN order_items oi ON oi.order_id = o.id
                LEFT JOIN services s ON s.id = oi.service_id
                WHERE o.id = :id
                LIMIT 1
            ");
            $stmt->execute(['id' => $orderId]);
            $order = $stmt->fetch(\PDO::FETCH_ASSOC);

            if (!$order) {
                return false;
            }

            $ownerPhone = $order['owner_phone'] ?? '';
            if (empty($ownerPhone)) {
                WhatsAppService::recordLog(
                    $orderId,
                    'none',
                    'store_admin',
                    'new_order',
                    'skipped',
                    'Nomor WhatsApp pemilik toko kosong',
                    "Pesanan baru #{$order['order_number']}"
                );
                return false;
            }

            $orderNumber  = $order['order_number'];
            $storeName    = trim((string) ($order['store_name'] ?? 'Toko Laundry'));
            $customerName = trim((string) ($order['customer_name'] ?? 'Pelanggan'));
            if (str_contains($customerName, '@')) {
                $customerName = ucwords(str_replace(['.', '_', '-'], ' ', explode('@', $customerName)[0]));
            }
            $serviceName  = $order['service_name'] ?? 'Layanan Laundry';

            $fullAddress = trim($order['full_address']);
            if (!empty($order['rt'])) $fullAddress .= ' RT ' . $order['rt'];
            if (!empty($order['rw'])) $fullAddress .= ' RW ' . $order['rw'];
            if (!empty($order['district'])) $fullAddress .= ', ' . $order['district'];
            if (!empty($order['city'])) $fullAddress .= ', ' . $order['city'];

            $scheduleTime = date('d M Y, H:i', strtotime($order['created_at'])) . ' WIB';

            $message = "Halo {$storeName},\n"
                     . "Ada pesanan baru masuk!\n\n"
                     . "No. Pesanan: #{$orderNumber}\n"
                     . "Pelanggan: {$customerName}\n"
                     . "Layanan: {$serviceName}\n"
                     . "Alamat Jemput: {$fullAddress}\n"
                     . "Waktu Pemesanan: {$scheduleTime}\n\n"
                     . "Silakan segera buka dashboard untuk menugaskan kurir penjemputan.";

            return WhatsAppService::sendSingle(
                $ownerPhone,
                $message,
                $orderId,
                'store_admin',
                'new_order'
            );
        } catch (\Throwable $e) {
            error_log('[WA_NEW_ORDER_ERROR] ' . $e->getMessage());
            return false;
        }
    }
}

if (!function_exists('notif_status_pesanan')) {
    /**
     * Mengirim notifikasi WhatsApp saat status pesanan berubah.
     * Menggunakan Fonnte.
     *
     * 1. Kirim WA ke customer dari jemput sampai selesai:
     *    Pesan singkat, ramah, berisi nomor pesanan, status baru, dan nama toko.
     * 2. Kirim WA ringkasan ke Admin Toko jika status diubah oleh kurir.
     *
     * @param int $orderId ID pesanan
     * @param string $newStatus Status pesanan terbaru (enum database)
     * @param string $statusTitle Judul pembaruan (opsional)
     * @param string $statusNotes Catatan tambahan status (opsional)
     * @param int|null $updatedByUserId ID user yang mengubah status
     */
    function notif_status_pesanan(
        int $orderId,
        string $newStatus,
        string $statusTitle = '',
        string $statusNotes = '',
        ?int $updatedByUserId = null
    ): bool {
        // Cek toggle konfigurasi status pesanan berubah
        $config = WhatsAppService::getConfig();
        if (!$config['enabled'] || !$config['notify_status_change']) {
            return false;
        }

        try {
            $pdo = BaseModel::getPdo();
            if (!$pdo) {
                return false;
            }

            // Ambil data lengkap pesanan, toko, pemilik toko, customer, dan kurir (jemput & antar)
            $stmt = $pdo->prepare("
                SELECT 
                    o.id, o.order_number, o.order_status, o.total_amount,
                    o.pickup_driver_id, o.delivery_driver_id,
                    st.store_name, st.owner_id,
                    ou.name as owner_name, ou.phone as owner_phone,
                    cu.name as customer_name, cu.phone as customer_phone,
                    -- Data Kurir Jemput
                    COALESCE(pu_u.name, pu_d_u.name, '') as pickup_driver_name,
                    COALESCE(pu_u.phone, pu_d_u.phone, '') as pickup_driver_phone,
                    COALESCE(pu_d.vehicle_type, pu_c.jenis_kendaraan, 'Motor') as pickup_vehicle,
                    COALESCE(pu_d.license_plate, pu_c.plat_nomor, '') as pickup_plate,
                    -- Data Kurir Antar
                    COALESCE(del_u.name, del_d_u.name, '') as delivery_driver_name,
                    COALESCE(del_u.phone, del_d_u.phone, '') as delivery_driver_phone,
                    COALESCE(del_d.vehicle_type, del_c.jenis_kendaraan, 'Motor') as delivery_vehicle,
                    COALESCE(del_d.license_plate, del_c.plat_nomor, '') as delivery_plate
                FROM laundry_orders o
                JOIN stores st ON st.id = o.store_id
                JOIN users ou ON ou.id = st.owner_id
                JOIN users cu ON cu.id = o.customer_id
                -- Kurir Jemput (dukung relasi via drivers.id atau users.id langsung)
                LEFT JOIN drivers pu_d ON (pu_d.id = o.pickup_driver_id OR pu_d.user_id = o.pickup_driver_id)
                LEFT JOIN users pu_u ON (pu_u.id = pu_d.user_id)
                LEFT JOIN users pu_d_u ON (pu_d_u.id = o.pickup_driver_id)
                LEFT JOIN couriers pu_c ON (pu_c.user_id = pu_u.id OR pu_c.user_id = pu_d_u.id)
                -- Kurir Antar (dukung relasi via drivers.id atau users.id langsung)
                LEFT JOIN drivers del_d ON (del_d.id = o.delivery_driver_id OR del_d.user_id = o.delivery_driver_id)
                LEFT JOIN users del_u ON (del_u.id = del_d.user_id)
                LEFT JOIN users del_d_u ON (del_d_u.id = o.delivery_driver_id)
                LEFT JOIN couriers del_c ON (del_c.user_id = del_u.id OR del_c.user_id = del_d_u.id)
                WHERE o.id = :id
                LIMIT 1
            ");
            $stmt->execute(['id' => $orderId]);
            $order = $stmt->fetch(\PDO::FETCH_ASSOC);

            if (!$order) {
                return false;
            }

            $orderNumber   = $order['order_number'];
            $storeName     = trim((string) ($order['store_name'] ?? 'MarketplaceLaundry'));

            // Pastikan nama pelanggan bersih sesuai profil (tanpa embel-embel email atau kata tambahan)
            $customerName  = trim((string) ($order['customer_name'] ?? 'Pelanggan'));
            if (str_contains($customerName, '@')) {
                $customerName = ucwords(str_replace(['.', '_', '-'], ' ', explode('@', $customerName)[0]));
            }

            $customerPhone = $order['customer_phone'] ?? '';
            $ownerPhone    = $order['owner_phone'] ?? '';
            $ownerId       = (int) $order['owner_id'];

            // Tentukan info kurir aktif berdasarkan status pesanan
            $pickupStatuses = [
                'driver_assigned',
                'driver_on_the_way',
                'arrived_at_customer',
                'picked_up',
                'arrived_at_store'
            ];
            $deliveryStatuses = [
                'ready_for_delivery',
                'delivery_assigned',
                'delivered',
                'completed'
            ];

            $isPickupFlow        = in_array($newStatus, $pickupStatuses, true);
            $activeDriverName    = null;
            $activeDriverPlate   = null;
            $activeDriverVehicle = null;
            $activeDriverPhone   = null;

            if ($isPickupFlow) {
                $activeDriverName    = !empty($order['pickup_driver_name']) ? trim($order['pickup_driver_name']) : null;
                $activeDriverPlate   = !empty($order['pickup_plate']) ? trim($order['pickup_plate']) : null;
                $activeDriverVehicle = !empty($order['pickup_vehicle']) ? trim($order['pickup_vehicle']) : null;
                $activeDriverPhone   = !empty($order['pickup_driver_phone']) ? trim($order['pickup_driver_phone']) : null;
            } elseif (in_array($newStatus, $deliveryStatuses, true)) {
                $activeDriverName    = !empty($order['delivery_driver_name']) ? trim($order['delivery_driver_name']) : (!empty($order['pickup_driver_name']) ? trim($order['pickup_driver_name']) : null);
                $activeDriverPlate   = !empty($order['delivery_plate']) ? trim($order['delivery_plate']) : (!empty($order['pickup_plate']) ? trim($order['pickup_plate']) : null);
                $activeDriverVehicle = !empty($order['delivery_vehicle']) ? trim($order['delivery_vehicle']) : (!empty($order['pickup_vehicle']) ? trim($order['pickup_vehicle']) : null);
                $activeDriverPhone   = !empty($order['delivery_driver_phone']) ? trim($order['delivery_driver_phone']) : (!empty($order['pickup_driver_phone']) ? trim($order['pickup_driver_phone']) : null);
            }

            // Bersihkan nama kurir jika mengandung format email
            if (!empty($activeDriverName) && str_contains($activeDriverName, '@')) {
                $activeDriverName = ucwords(str_replace(['.', '_', '-'], ' ', explode('@', $activeDriverName)[0]));
            }

            // Peta status ramah dan spesifik untuk penjemputan, proses, dan pengantaran
            $friendlyStatusMap = [
                'driver_assigned'     => 'Kurir kami telah ditugaskan untuk menjemput pakaian ke lokasi Anda. Mohon siapkan pakaian Anda.',
                'driver_on_the_way'   => 'Kurir sedang dalam perjalanan menuju lokasi Anda untuk menjemput pakaian.',
                'arrived_at_customer' => 'Kurir telah tiba di alamat penjemputan Anda. Silakan serahkan pakaian Anda kepada kurir.',
                'picked_up'           => 'Pakaian Anda telah berhasil dijemput oleh kurir dan sedang dibawa menuju toko laundry.',
                'arrived_at_store'    => "Pakaian Anda telah sampai di {$storeName} dan segera ditimbang serta diproses.",
                'weighed'             => 'Pakaian telah selesai ditimbang dan siap diproses.',
                'waiting_payment'     => 'Menunggu pembayaran dari Anda agar pesanan dapat segera diproses.',
                'in_process'          => 'Pakaian Anda sedang dalam proses pencucian dan perawatan.',
                'ready_for_delivery'  => 'Pakaian bersih Anda sudah selesai dicuci dan siap untuk diantarkan.',
                'delivery_assigned'   => 'Kurir telah ditugaskan untuk mengantarkan pakaian bersih ke alamat Anda.',
                'delivered'           => 'Pakaian bersih Anda telah sampai dan berhasil diantarkan oleh kurir.',
                'completed'           => 'Pesanan laundry Anda telah selesai. Terima kasih banyak!',
                'cancelled'           => 'Pesanan laundry Anda telah dibatalkan.',
            ];

            $statusText = $friendlyStatusMap[$newStatus] ?? "Status pesanan Anda: {$newStatus}";
            $shortStatusLabel = match ($newStatus) {
                'driver_assigned'     => 'Kurir Ditugaskan Menjemput',
                'driver_on_the_way'   => 'Kurir Menuju Lokasi Anda',
                'arrived_at_customer' => 'Kurir Tiba di Lokasi Jemput',
                'picked_up'           => 'Pakaian Berhasil Dijemput',
                'arrived_at_store'    => 'Pakaian Tiba di Toko',
                'weighed'             => 'Selesai Ditimbang',
                'waiting_payment'     => 'Menunggu Pembayaran',
                'in_process'          => 'Sedang Dicuci',
                'ready_for_delivery'  => 'Selesai Dicuci (Siap Diantar)',
                'delivery_assigned'   => 'Kurir Ditugaskan Mengantar',
                'delivered'           => 'Pakaian Telah Diantar',
                'completed'           => 'Pesanan Selesai',
                'cancelled'           => 'Pesanan Dibatalkan',
                default               => ucwords(str_replace('_', ' ', $newStatus)),
            };

            // Blok informasi kurir yang spesifik (Kurir Penjemput vs Kurir Pengantar)
            $driverInfoBlock = '';
            if (!empty($activeDriverName)) {
                $courierRoleLabel = $isPickupFlow ? 'Kurir Penjemput' : 'Kurir Pengantar';
                $driverInfoBlock = "\n\n{$courierRoleLabel}:\n"
                                 . "• Nama: {$activeDriverName}\n"
                                 . (!empty($activeDriverPlate) ? "• Plat Nomor: {$activeDriverPlate}\n" : "")
                                 . (!empty($activeDriverVehicle) ? "• Kendaraan: {$activeDriverVehicle}\n" : "")
                                 . (!empty($activeDriverPhone) ? "• No. WA Kurir: {$activeDriverPhone}\n" : "");
                $driverInfoBlock = rtrim($driverInfoBlock);
            }

            // 1. KIRIM WA KE CUSTOMER
            if (!empty($customerPhone)) {
                $customerMsg = "Halo {$customerName},\n"
                             . "Pembaruan untuk pesanan #{$orderNumber} di {$storeName}:\n\n"
                             . "Status: {$shortStatusLabel}\n"
                             . "{$statusText}"
                             . $driverInfoBlock . "\n\n"
                             . "Terima kasih telah mempercayakan pakaian Anda kepada {$storeName}.";

                WhatsAppService::sendSingle(
                    $customerPhone,
                    $customerMsg,
                    $orderId,
                    'customer',
                    "status_{$newStatus}"
                );
            }

            // 2. KIRIM WA RINGKASAN KE ADMIN TOKO JIKA DIUBAH OLEH KURIR
            // Kurir mengubah status di DriverController (driver_on_the_way, arrived_at_customer, picked_up, arrived_at_store, delivered, completed)
            $courierDrivenStatuses = [
                'driver_on_the_way',
                'arrived_at_customer',
                'picked_up',
                'arrived_at_store',
                'delivered',
                'completed',
            ];

            $isChangedByCourier = ($updatedByUserId !== null && $updatedByUserId !== $ownerId && in_array($newStatus, $courierDrivenStatuses, true));

            // Jika status termasuk alur kurir dan diubah oleh kurir
            if ($isChangedByCourier && !empty($ownerPhone)) {
                $courierLine = !empty($activeDriverName) ? "\nKurir: {$activeDriverName}" . (!empty($activeDriverPlate) ? " (Plat: {$activeDriverPlate})" : "") : "";
                $driverSummaryMsg = "Halo {$storeName},\n"
                                  . "Info tugas kurir untuk pesanan #{$orderNumber}:\n\n"
                                  . "Status terkini: {$shortStatusLabel}\n"
                                  . "Pelanggan: {$customerName}"
                                  . $courierLine . "\n"
                                  . "Waktu: " . date('d M Y, H:i') . " WIB\n\n"
                                  . "Kurir telah memperbarui status tugas pesanan ini.";

                WhatsAppService::sendSingle(
                    $ownerPhone,
                    $driverSummaryMsg,
                    $orderId,
                    'store_admin',
                    "courier_update_{$newStatus}"
                );
            }

            return true;
        } catch (\Throwable $e) {
            error_log('[WA_STATUS_NOTIF_ERROR] ' . $e->getMessage());
            return false;
        }
    }
}
