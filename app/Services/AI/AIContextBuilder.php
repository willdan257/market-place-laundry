<?php

namespace App\Services\AI;

use App\Models\BaseModel;
use PDO;
use PDOException;

/**
 * Membangun konteks data yang BOLEH dilihat AI.
 *
 * Aturan keamanan:
 *  - Semua query order WAJIB memakai filter customer_id = id user yang sedang login
 *    (id berasal dari session di sisi server, bukan dari input klien).
 *  - order_id / nomor order dari klien hanya dipakai sebagai "fokus" dan tetap difilter customer_id.
 *  - Data pribadi (alamat, telepon, email, data kurir) TIDAK dikirim ke AI.
 *  - Angka uang diformat di backend agar AI tidak perlu menghitung sendiri.
 */
class AIContextBuilder
{
    private const ORDER_NUMBER_PATTERN = '/ORD-\d{8}-[A-F0-9]{4}/i';

    /** [label, keterangan] */
    public const ORDER_STATUS = [
        'pending_pickup'      => ['Menunggu Kurir Ditugaskan', 'Pesanan sudah dibuat dan menunggu pemilik laundry menugaskan kurir penjemput.'],
        'driver_assigned'     => ['Kurir Ditugaskan', 'Kurir penjemput sudah ditugaskan dan akan menuju lokasi penjemputan.'],
        'driver_on_the_way'   => ['Kurir Dalam Perjalanan', 'Kurir sedang dalam perjalanan menuju lokasi pelanggan.'],
        'arrived_at_customer' => ['Kurir Tiba di Lokasi Pelanggan', 'Kurir sudah tiba di lokasi penjemputan.'],
        'picked_up'           => ['Pakaian Sudah Dijemput', 'Pakaian sudah diambil kurir dan sedang dibawa ke toko laundry.'],
        'arrived_at_store'    => ['Sampai di Toko Laundry', 'Pakaian sudah tiba di toko dan menunggu penimbangan.'],
        'weighed'             => ['Sudah Ditimbang', 'Pakaian sudah ditimbang. Total final tersedia dan pelanggan dapat melanjutkan ke pembayaran.'],
        'waiting_payment'     => ['Menunggu Pembayaran', 'Metode pembayaran sudah dipilih. Laundry diproses setelah pembayaran terkonfirmasi.'],
        'in_process'          => ['Sedang Dicuci / Diproses', 'Pembayaran sudah terkonfirmasi dan pakaian sedang diproses di toko.'],
        'ready_for_delivery'  => ['Siap Diantar', 'Laundry sudah selesai diproses dan menunggu pengantaran.'],
        'delivery_assigned'   => ['Kurir Pengantar Ditugaskan', 'Kurir pengantar sudah ditugaskan untuk mengantar laundry.'],
        'delivered'           => ['Sudah Diantar', 'Laundry sudah diantar ke pelanggan.'],
        'completed'           => ['Selesai', 'Pesanan sudah selesai.'],
        'cancelled'           => ['Dibatalkan', 'Pesanan dibatalkan.'],
    ];

    /** Status di mana berat sudah ditimbang sehingga total sudah final. */
    private const FINAL_TOTAL_STATUSES = [
        'weighed', 'waiting_payment', 'in_process', 'ready_for_delivery',
        'delivery_assigned', 'delivered', 'completed',
    ];

    /** Status di mana proses cuci belum dimulai. */
    private const BEFORE_PROCESS_STATUSES = [
        'pending_pickup', 'driver_assigned', 'driver_on_the_way', 'arrived_at_customer',
        'picked_up', 'arrived_at_store', 'weighed', 'waiting_payment',
    ];

    private const AFTER_PROCESS_STATUSES = ['ready_for_delivery', 'delivery_assigned', 'delivered', 'completed'];

    private const PAYMENT_METHOD = [
        'qris'          => 'QRIS',
        'cash'          => 'Tunai (Cash)',
        'bank_transfer' => 'Transfer Bank',
    ];

    private const MONTHS = [
        1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April', 5 => 'Mei', 6 => 'Juni',
        7 => 'Juli', 8 => 'Agustus', 9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember',
    ];

    private array $config;
    private array $appConfig;

    public function __construct(?array $aiConfig = null, ?array $appConfig = null)
    {
        $root = dirname(__DIR__, 3);
        $this->config = $aiConfig ?? require $root . '/config/ai.php';
        $this->appConfig = $appConfig ?? require $root . '/config/app.php';
        date_default_timezone_set($this->appConfig['timezone'] ?? 'Asia/Jakarta');
    }

    /**
     * @return array{context: array, order_numbers: string[]}
     * @throws AIException
     */
    public function build(int $customerId, string $firstName, string $question, ?int $focusOrderId = null): array
    {
        $pdo = BaseModel::getPdo();
        if (!$pdo) {
            AILogger::error('context_db_unavailable', ['user_id' => $customerId]);
            throw new AIException('Data pesanan sementara tidak dapat diakses. Silakan coba lagi nanti.', 503, 'db_unavailable');
        }

        try {
            // Nomor order yang disebut di pertanyaan menggantikan order_id dari halaman.
            $focusNumber = null;
            if (preg_match(self::ORDER_NUMBER_PATTERN, $question, $m)) {
                $focusNumber = strtoupper($m[0]);
                $focusOrderId = null;
            }

            $orders = $this->fetchRecentOrders($pdo, $customerId, (int) $this->config['recent_orders']);
            $recentIds = array_column($orders, 'id');

            $focusRow = null;
            $notFound = [];
            if ($focusOrderId !== null || $focusNumber !== null) {
                $focusRow = $this->fetchFocusOrder($pdo, $customerId, $focusOrderId, $focusNumber);
                if ($focusRow === null) {
                    if ($focusNumber !== null) {
                        $notFound[] = $focusNumber;
                    }
                } elseif (!in_array($focusRow['id'], $recentIds, false)) {
                    $orders[] = $focusRow;
                }
            }

            $orderIds = array_map('intval', array_column($orders, 'id'));
            $items = $this->fetchItems($pdo, $orderIds);
            $histories = $this->fetchHistories($pdo, $orderIds);

            $orderContexts = [];
            $orderNumbers = [];
            foreach ($orders as $index => $row) {
                $id = (int) $row['id'];
                $isFocus = $focusRow !== null && (int) $focusRow['id'] === $id;
                $orderContexts[] = $this->formatOrder(
                    $row,
                    $items[$id] ?? [],
                    $histories[$id] ?? [],
                    $isFocus,
                    $index === 0
                );
                $orderNumbers[] = $row['order_number'];
            }

            $stmtUser = $pdo->prepare("SELECT email FROM users WHERE id = :id LIMIT 1");
            $stmtUser->execute(['id' => $customerId]);
            $uRow = $stmtUser->fetch(PDO::FETCH_ASSOC);
            $username = $uRow['email'] ?? $firstName;

            $context = [
                'peran'           => 'customer',
                'nama_terdaftar'  => $firstName,
                'username'        => $username,
                'user_id'         => $customerId,
                'mode_akses'      => 'pelanggan_login',
                'waktu_sekarang'  => $this->formatDate(date('Y-m-d H:i:s')),
                'nama_pelanggan'  => $firstName,
                'pesanan'         => $orderContexts,
                'catatan_pesanan' => empty($orderContexts)
                    ? 'Pelanggan ini belum memiliki pesanan.'
                    : 'Hanya pesanan terbaru milik pelanggan ini yang dimuat (maksimal ' . (int) $this->config['recent_orders'] . ' ditambah pesanan yang ditanyakan). Urutan dari yang terbaru.',
                'layanan_tersedia' => $this->fetchServices($pdo),
                'info_ongkir'      => $this->describeDeliveryFees(),
                'faq'              => $this->config['faq'] ?? [],
                'kontak_bantuan'   => $this->config['support'] ?? [],
            ];

            if (!empty($notFound)) {
                $context['nomor_pesanan_tidak_ditemukan_di_akun_ini'] = $notFound;
            }

            return ['context' => $context, 'order_numbers' => $orderNumbers];
        } catch (PDOException $e) {
            AILogger::error('context_query_failed', ['user_id' => $customerId, 'error' => $e->getMessage()]);
            throw new AIException('Data pesanan sementara tidak dapat diakses. Silakan coba lagi nanti.', 500, 'db_error', null, $e);
        }
    }

    /**
     * Konteks untuk OWNER (pemilik toko laundry):
     * Hanya mencakup toko milik owner ini, pesanan toko, kurir, dan layanan toko.
     *
     * @return array{context: array, order_numbers: string[]}
     * @throws AIException
     */
    public function buildOwner(int $ownerUserId, string $registeredName, string $question): array
    {
        $pdo = BaseModel::getPdo();
        if (!$pdo) {
            AILogger::error('owner_context_db_unavailable', ['owner_id' => $ownerUserId]);
            throw new AIException('Data toko sementara tidak dapat diakses. Silakan coba lagi nanti.', 503, 'db_unavailable');
        }

        try {
            $stmtStore = $pdo->prepare("SELECT * FROM stores WHERE owner_id = :oid LIMIT 1");
            $stmtStore->execute(['oid' => $ownerUserId]);
            $store = $stmtStore->fetch(PDO::FETCH_ASSOC);

            if (!$store) {
                return [
                    'context' => [
                        'peran'          => 'owner',
                        'nama_terdaftar' => $registeredName,
                        'mode_akses'     => 'owner_login',
                        'waktu_sekarang' => $this->formatDate(date('Y-m-d H:i:s')),
                        'toko'           => null,
                        'catatan'        => 'Akun Anda belum terhubung dengan toko laundry manapun.',
                        'faq'            => $this->config['faq'] ?? [],
                        'kontak_bantuan' => $this->config['support'] ?? [],
                    ],
                    'order_numbers' => [],
                ];
            }

            $storeId = (int) $store['id'];

            // 1. Ringkasan jumlah pesanan per status
            $stmtStatus = $pdo->prepare("
                SELECT order_status, COUNT(*) as jumlah
                FROM laundry_orders
                WHERE store_id = :sid
                GROUP BY order_status
            ");
            $stmtStatus->execute(['sid' => $storeId]);
            $rawStatusCounts = $stmtStatus->fetchAll(PDO::FETCH_KEY_PAIR) ?: [];
            $statusSummary = [];
            foreach (self::ORDER_STATUS as $code => $info) {
                $statusSummary[$code] = [
                    'label'  => $info[0],
                    'jumlah' => (int) ($rawStatusCounts[$code] ?? 0),
                ];
            }

            // 2. Pesanan yang perlu ditimbang (status arrived_at_store)
            $stmtWeigh = $pdo->prepare("
                SELECT o.id, o.order_number, o.created_at, o.notes, u.name as customer_name
                FROM laundry_orders o
                JOIN users u ON u.id = o.customer_id
                WHERE o.store_id = :sid AND o.order_status = 'arrived_at_store'
                ORDER BY o.id ASC
            ");
            $stmtWeigh->execute(['sid' => $storeId]);
            $ordersToWeigh = [];
            foreach ($stmtWeigh->fetchAll(PDO::FETCH_ASSOC) as $w) {
                $ordersToWeigh[] = [
                    'nomor_pesanan' => $w['order_number'],
                    'nama_pelanggan'=> $w['customer_name'],
                    'waktu_masuk'   => $this->formatDate($w['created_at']),
                    'catatan'       => $w['notes'] ?: 'Tidak ada',
                ];
            }

            // 3. Pembayaran tunai yang menunggu konfirmasi (cash + pending)
            $stmtCash = $pdo->prepare("
                SELECT o.id, o.order_number, o.total_amount, p.amount, p.created_at, u.name as customer_name
                FROM laundry_orders o
                JOIN payments p ON p.order_id = o.id
                JOIN users u ON u.id = o.customer_id
                WHERE o.store_id = :sid AND p.payment_method = 'cash' AND p.payment_status = 'pending'
                ORDER BY p.id ASC
            ");
            $stmtCash->execute(['sid' => $storeId]);
            $cashPending = [];
            foreach ($stmtCash->fetchAll(PDO::FETCH_ASSOC) as $c) {
                $cashPending[] = [
                    'nomor_pesanan' => $c['order_number'],
                    'nama_pelanggan'=> $c['customer_name'],
                    'nominal'       => $this->money($c['amount']),
                    'waktu_order'   => $this->formatDate($c['created_at']),
                ];
            }

            // 4. Pendapatan hari ini, minggu ini & bulan ini via OwnerAnalyticsService
            $formatAnalyticsBlock = function (?array $data): array {
                if (!$data) {
                    return [
                        'periode'            => '',
                        'total_pendapatan'   => $this->money(0),
                        'total_pesanan'      => 0,
                        'pesanan_selesai'    => 0,
                        'pesanan_diproses'   => 0,
                        'pesanan_dibatalkan' => 0,
                        'rata_rata_order'    => $this->money(0),
                    ];
                }
                return [
                    'periode'            => (string) ($data['period_label'] ?? ''),
                    'total_pendapatan'   => $this->money($data['total_revenue'] ?? 0),
                    'total_pesanan'      => (int) ($data['total_orders'] ?? 0),
                    'pesanan_selesai'    => (int) ($data['completed_orders'] ?? 0),
                    'pesanan_diproses'   => (int) ($data['pending_orders'] ?? 0),
                    'pesanan_dibatalkan' => (int) ($data['cancelled_orders'] ?? 0),
                    'rata_rata_order'    => $this->money($data['average_order_value'] ?? 0),
                ];
            };

            $revenueToday = $formatAnalyticsBlock(null);
            $revenueWeek  = $formatAnalyticsBlock(null);
            $revenueMonth = $formatAnalyticsBlock(null);

            try {
                $analyticsService = new \App\Services\OwnerAnalyticsService();
                $todayData = $analyticsService->getAnalyticsData($storeId, 'today');
                $weekData  = $analyticsService->getAnalyticsData($storeId, 'week');
                $monthData = $analyticsService->getAnalyticsData($storeId, 'month');

                $revenueToday = $formatAnalyticsBlock($todayData);
                $revenueWeek  = $formatAnalyticsBlock($weekData);
                $revenueMonth = $formatAnalyticsBlock($monthData);
            } catch (\Throwable $e) {
                // Kegagalan analytics tidak menjatuhkan seluruh konteks owner
            }

            // 5. Daftar kurir terdaftar + status
            $driverModel = new \App\Models\Driver();
            $allDrivers = $driverModel->getAllDriversWithUser();
            $couriers = [];
            foreach ($allDrivers as $d) {
                $couriers[] = [
                    'nama'      => $d['driver_name'],
                    'telepon'   => $d['driver_phone'],
                    'kendaraan' => $d['vehicle_type'] . ' (' . $d['license_plate'] . ')',
                    'status'    => ((int) ($d['is_available'] ?? 0) === 1) ? 'Tersedia' : 'Sedang Bertugas',
                ];
            }

            // 6. Layanan dan harga toko ini
            $stmtServices = $pdo->prepare("
                SELECT service_name, unit, price_per_unit, estimated_duration_hours, is_active
                FROM services
                WHERE store_id = :sid
                ORDER BY is_active DESC, id ASC
            ");
            $stmtServices->execute(['sid' => $storeId]);
            $services = [];
            foreach ($stmtServices->fetchAll(PDO::FETCH_ASSOC) as $s) {
                $services[] = [
                    'nama_layanan'    => $s['service_name'],
                    'satuan'          => $s['unit'],
                    'harga'           => $this->money($s['price_per_unit']) . ' / ' . $s['unit'],
                    'estimasi_durasi' => (int) $s['estimated_duration_hours'] . ' jam',
                    'status_aktif'    => (bool) $s['is_active'],
                ];
            }

            // 7. Pesanan terbaru toko (untuk referensi nomor order)
            $stmtOrders = $pdo->prepare("
                SELECT o.id, o.order_number, o.order_status, o.total_amount, o.weight_kg, o.created_at,
                       u.name as customer_name,
                       p.payment_method, p.payment_status
                FROM laundry_orders o
                JOIN users u ON u.id = o.customer_id
                LEFT JOIN payments p ON p.order_id = o.id
                WHERE o.store_id = :sid
                ORDER BY o.id DESC
                LIMIT 15
            ");
            $stmtOrders->execute(['sid' => $storeId]);
            $recentOrders = [];
            $orderNumbers = [];
            foreach ($stmtOrders->fetchAll(PDO::FETCH_ASSOC) as $ro) {
                $orderNumbers[] = $ro['order_number'];
                $recentOrders[] = [
                    'nomor_pesanan'    => $ro['order_number'],
                    'pelanggan'        => $ro['customer_name'],
                    'status'           => self::ORDER_STATUS[$ro['order_status']][0] ?? $ro['order_status'],
                    'kode_status'      => $ro['order_status'],
                    'berat'            => (float) $ro['weight_kg'] > 0 ? $this->qty($ro['weight_kg']) . ' kg' : 'belum ditimbang',
                    'total'            => $this->money($ro['total_amount']),
                    'metode_bayar'     => $ro['payment_method'] ?? 'belum dipilih',
                    'status_bayar'     => $ro['payment_status'] ?? 'belum bayar',
                    'dibuat_pada'      => $this->formatDate($ro['created_at']),
                ];
            }

            // 8. Pesanan aktif sekarang (semua order non-selesai/non-cancelled)
            $activeStoreOrders = (new \App\Models\Order())->getActiveStoreOrders($storeId);
            $activeStatusCounts = [];
            foreach ($activeStoreOrders as $ao) {
                $label = self::ORDER_STATUS[$ao['order_status']][0] ?? $ao['order_status'];
                $activeStatusCounts[$label] = ($activeStatusCounts[$label] ?? 0) + 1;
                if (!in_array($ao['order_number'], $orderNumbers)) {
                    $orderNumbers[] = $ao['order_number'];
                }
            }

            $activeOrdersSummary = [];
            foreach (array_slice($activeStoreOrders, 0, 10) as $ao) {
                $activeOrdersSummary[] = [
                    'nomor_pesanan' => $ao['order_number'],
                    'pelanggan'     => $ao['customer_name'] ?? 'Pelanggan',
                    'status'        => self::ORDER_STATUS[$ao['order_status']][0] ?? $ao['order_status'],
                    'total'         => $this->money($ao['total_amount'] ?? 0),
                ];
            }

            $stmtOwnerUser = $pdo->prepare("SELECT email FROM users WHERE id = :id LIMIT 1");
            $stmtOwnerUser->execute(['id' => $ownerUserId]);
            $ownerUserRow = $stmtOwnerUser->fetch(PDO::FETCH_ASSOC);
            $ownerUsername = $ownerUserRow['email'] ?? $registeredName;

            $context = [
                'peran'                                 => 'owner',
                'nama_terdaftar'                        => $registeredName,
                'username'                              => $ownerUsername,
                'user_id'                               => $ownerUserId,
                'mode_akses'                            => 'owner_login',
                'waktu_sekarang'                        => $this->formatDate(date('Y-m-d H:i:s')),
                'toko'                                  => [
                    'id'          => $storeId,
                    'nama_toko'   => $store['store_name'],
                    'alamat'      => $store['full_address'],
                    'status_buka' => ((int) $store['is_open'] === 1) ? 'Buka' : 'Tutup',
                ],
                'pendapatan_hari_ini'                   => $revenueToday,
                'pendapatan_minggu_ini'                 => $revenueWeek,
                'pendapatan_bulan_ini'                  => $revenueMonth,
                'pesanan_aktif_sekarang'                => [
                    'jumlah'             => count($activeStoreOrders),
                    'rincian_per_status' => $activeStatusCounts,
                ],
                'daftar_pesanan_aktif'                  => $activeOrdersSummary,
                'ringkasan_status_pesanan'              => $statusSummary,
                'pesanan_menunggu_ditimbang'            => $ordersToWeigh,
                'pembayaran_tunai_menunggu_konfirmasi'  => $cashPending,
                'daftar_kurir'                          => $couriers,
                'layanan_toko'                          => $services,
                'pesanan_terbaru_toko'                  => $recentOrders,
                'kontak_bantuan'                        => $this->config['support'] ?? [],
            ];

            return ['context' => $context, 'order_numbers' => $orderNumbers];
        } catch (PDOException $e) {
            AILogger::error('owner_context_query_failed', ['owner_id' => $ownerUserId, 'error' => $e->getMessage()]);
            throw new AIException('Data toko sementara tidak dapat diakses. Silakan coba lagi nanti.', 500, 'db_error', null, $e);
        }
    }

    /**
     * Konteks untuk DRIVER (kurir antar-jemput):
     * Hanya mencakup tugas penjemputan/pengantaran yang di-assign ke driver ini.
     *
     * @return array{context: array, order_numbers: string[]}
     * @throws AIException
     */
    public function buildDriver(int $driverUserId, string $registeredName, string $question, ?int $focusOrderId = null): array
    {
        $pdo = BaseModel::getPdo();
        if (!$pdo) {
            AILogger::error('driver_context_db_unavailable', ['driver_user_id' => $driverUserId]);
            throw new AIException('Data tugas kurir sementara tidak dapat diakses. Silakan coba lagi nanti.', 503, 'db_unavailable');
        }

        try {
            $stmtDriver = $pdo->prepare("SELECT * FROM drivers WHERE user_id = :uid LIMIT 1");
            $stmtDriver->execute(['uid' => $driverUserId]);
            $driver = $stmtDriver->fetch(PDO::FETCH_ASSOC);

            if (!$driver) {
                return [
                    'context' => [
                        'peran'          => 'driver',
                        'nama_terdaftar' => $registeredName,
                        'mode_akses'     => 'driver_login',
                        'waktu_sekarang' => $this->formatDate(date('Y-m-d H:i:s')),
                        'driver'         => null,
                        'catatan'        => 'Profil kurir Anda belum terdaftar di sistem.',
                        'kontak_bantuan' => $this->config['support'] ?? [],
                    ],
                    'order_numbers' => [],
                ];
            }

            $driverId = (int) $driver['id'];

            // Ambil data penghasilan kurir
            $earningsBlock = [
                'hari_ini'                  => $this->money(0),
                'minggu_ini'                => $this->money(0),
                'bulan_ini'                 => $this->money(0),
                'total_semua_waktu'         => $this->money(0),
                'total_pengantaran_selesai' => 0,
                'jumlah_tugas_selesai'      => [
                    'hari_ini'   => 0,
                    'minggu_ini' => 0,
                    'bulan_ini'  => 0,
                    'total'      => 0,
                ],
                'catatan'                   => '50% dari ongkir per tahap yang selesai',
            ];

            try {
                $orderModel = new \App\Models\Order();
                $earningsData = $orderModel->getDriverEarnings($driverId);
                $earningsBlock = [
                    'hari_ini'                  => $this->money($earningsData['today'] ?? 0),
                    'minggu_ini'                => $this->money($earningsData['week'] ?? 0),
                    'bulan_ini'                 => $this->money($earningsData['month'] ?? 0),
                    'total_semua_waktu'         => $this->money($earningsData['total'] ?? 0),
                    'total_pengantaran_selesai' => (int) ($earningsData['completed_count'] ?? 0),
                    'jumlah_tugas_selesai'      => [
                        'hari_ini'   => (int) ($earningsData['today_count'] ?? 0),
                        'minggu_ini' => (int) ($earningsData['week_count'] ?? 0),
                        'bulan_ini'  => (int) ($earningsData['month_count'] ?? 0),
                        'total'      => (int) ($earningsData['completed_count'] ?? 0),
                    ],
                    'catatan'                   => '50% dari ongkir per tahap yang selesai; satu tahap = satu penjemputan atau satu pengantaran',
                ];
            } catch (\Throwable $e) {
                // Jangan gagalkan seluruh konteks jika pengambilan earning error
            }

            // Cek nomor order di pertanyaan
            $focusNumber = null;
            if (preg_match(self::ORDER_NUMBER_PATTERN, $question, $m)) {
                $focusNumber = strtoupper($m[0]);
            }

            $sql = "
                SELECT o.id, o.order_number, o.order_status, o.pickup_driver_id, o.delivery_driver_id,
                       o.weight_kg, o.distance_km, o.total_amount, o.notes, o.created_at, o.updated_at,
                       st.store_name, st.full_address as store_address,
                       ca.recipient_name, ca.recipient_phone, ca.full_address as pickup_address,
                       ca.rt, ca.rw, ca.district, ca.city, ca.latitude, ca.longitude
                FROM laundry_orders o
                JOIN stores st ON st.id = o.store_id
                JOIN customer_addresses ca ON ca.id = o.address_id
                WHERE (o.pickup_driver_id = :d1 OR o.delivery_driver_id = :d2)
                ORDER BY o.id DESC
                LIMIT 20
            ";
            $stmt = $pdo->prepare($sql);
            $stmt->execute(['d1' => $driverId, 'd2' => $driverId]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

            $tasks = [];
            $orderNumbers = [];

            foreach ($rows as $row) {
                $orderNumbers[] = $row['order_number'];
                $isPickup = ((int) ($row['pickup_driver_id'] ?? 0) === $driverId);
                $isDelivery = ((int) ($row['delivery_driver_id'] ?? 0) === $driverId);

                $taskType = $isPickup ? 'penjemputan' : 'pengantaran';
                $taskTypeLabel = $isPickup ? 'Penjemputan Pakaian Kotor' : 'Pengantaran Pakaian Bersih';

                // Status aktif kurir
                $isActive = $isPickup
                    ? in_array($row['order_status'], ['driver_assigned', 'driver_on_the_way', 'arrived_at_customer', 'picked_up'], true)
                    : in_array($row['order_status'], ['ready_for_delivery', 'delivery_assigned'], true);

                $fullAddr = $row['pickup_address'];
                if (!empty($row['rt'])) $fullAddr .= ' RT ' . $row['rt'];
                if (!empty($row['rw'])) $fullAddr .= ' RW ' . $row['rw'];
                if (!empty($row['district'])) $fullAddr .= ', Kec. ' . $row['district'];
                if (!empty($row['city'])) $fullAddr .= ', ' . $row['city'];

                $lat = $row['latitude'];
                $lng = $row['longitude'];
                if (!empty($lat) && !empty($lng)) {
                    $mapsView = "https://www.google.com/maps/search/?api=1&query={$lat},{$lng}";
                    $mapsNav  = "https://www.google.com/maps/dir/?api=1&destination={$lat},{$lng}&travelmode=driving";
                } else {
                    $mapsView = "https://www.google.com/maps/search/?api=1&query=" . urlencode($fullAddr);
                    $mapsNav  = "https://www.google.com/maps/dir/?api=1&destination=" . urlencode($fullAddr) . "&travelmode=driving";
                }

                $taskItem = [
                    'nomor_pesanan'   => $row['order_number'],
                    'jenis_tugas'     => $taskTypeLabel,
                    'status_tugas'    => self::ORDER_STATUS[$row['order_status']][0] ?? $row['order_status'],
                    'kode_status'     => $row['order_status'],
                    'sedang_aktif'    => $isActive,
                    'nama_customer'   => $row['recipient_name'],
                    'toko_laundry'    => $row['store_name'],
                    'alamat_toko'     => $row['store_address'],
                    'catatan_khusus'  => $row['notes'] ?: 'Tidak ada',
                    'terakhir_update' => $this->formatDate($row['updated_at'] ?? $row['created_at']),
                ];

                // Kurir HANYA boleh melihat nomor telepon dan koordinat detail untuk tugas yang aktif
                if ($isActive) {
                    $taskItem['telepon_customer'] = $row['recipient_phone'];
                    $taskItem['alamat_lengkap']   = $fullAddr;
                    $taskItem['titik_koordinat']  = (!empty($lat) && !empty($lng)) ? "{$lat}, {$lng}" : null;
                    $taskItem['link_google_maps'] = $mapsView;
                    $taskItem['link_navigasi']    = $mapsNav;
                } else {
                    $taskItem['catatan_privasi'] = 'Tugas telah selesai. Alamat lengkap dan nomor kontak disamarkan untuk perlindungan privasi pelanggan.';
                }

                $tasks[] = $taskItem;
            }

            $stmtDriverUser = $pdo->prepare("SELECT email FROM users WHERE id = :id LIMIT 1");
            $stmtDriverUser->execute(['id' => $driverUserId]);
            $driverUserRow = $stmtDriverUser->fetch(PDO::FETCH_ASSOC);
            $driverUsername = $driverUserRow['email'] ?? $registeredName;

            $context = [
                'peran'                  => 'driver',
                'nama_terdaftar'         => $registeredName,
                'username'               => $driverUsername,
                'user_id'                => $driverUserId,
                'mode_akses'             => 'driver_login',
                'waktu_sekarang'         => $this->formatDate(date('Y-m-d H:i:s')),
                'driver'                 => [
                    'kendaraan' => $driver['vehicle_type'] . ' (' . $driver['license_plate'] . ')',
                    'status'    => ((int) ($driver['is_available'] ?? 0) === 1) ? 'Tersedia' : 'Sedang Bertugas',
                ],
                'penghasilan_kurir'      => $earningsBlock,
                'daftar_tugas'           => $tasks,
                'tugas_aktif_terbaru'    => null,
                'alur_status_tugas'      => [
                    'penjemputan' => 'Kurir Ditugaskan -> Kurir Dalam Perjalanan -> Kurir Tiba di Lokasi -> Pakaian Sudah Dijemput -> Sampai di Toko Laundry',
                    'pengantaran' => 'Siap Diantar -> Kurir Pengantar Ditugaskan -> Sudah Diantar (Selesai)',
                ],
                'faq_driver'             => [
                    'Bagaimana cara update status?' => 'Buka detail pesanan di menu Tugas Kurir lalu klik tombol aksi status yang sesuai.',
                    'Bagaimana jika pelanggan tidak ada di rumah?' => 'Hubungi nomor telepon pelanggan atau laporkan ke toko laundry pengirim.',
                ],
                'kontak_bantuan'         => $this->config['support'] ?? [],
            ];

            // Tentukan tugas aktif terbaru
            foreach ($tasks as $t) {
                if ($t['sedang_aktif']) {
                    $context['tugas_aktif_terbaru'] = $t;
                    break;
                }
            }

            return ['context' => $context, 'order_numbers' => $orderNumbers];
        } catch (PDOException $e) {
            AILogger::error('driver_context_query_failed', ['driver_user_id' => $driverUserId, 'error' => $e->getMessage()]);
            throw new AIException('Data tugas kurir sementara tidak dapat diakses. Silakan coba lagi nanti.', 500, 'db_error', null, $e);
        }
    }

    /**
     * Konteks untuk ADMIN (Administrator Sistem):
     * Informasi verifikasi mitra, kurir, dan ringkasan platform.
     *
     * @return array{context: array, order_numbers: string[]}
     * @throws AIException
     */
    public function buildAdmin(int $adminUserId, string $registeredName, string $question): array
    {
        $pdo = BaseModel::getPdo();
        if (!$pdo) {
            AILogger::error('admin_context_db_unavailable', ['admin_id' => $adminUserId]);
            throw new AIException('Data admin sementara tidak dapat diakses. Silakan coba lagi nanti.', 503, 'db_unavailable');
        }

        try {
            $stmtUser = $pdo->prepare("SELECT email FROM users WHERE id = :id LIMIT 1");
            $stmtUser->execute(['id' => $adminUserId]);
            $uRow = $stmtUser->fetch(PDO::FETCH_ASSOC);
            $username = $uRow['email'] ?? $registeredName;

            // Hitung antrean verifikasi & statistik mitra (partners)
            $stmtPartners = $pdo->query("
                SELECT 
                    COUNT(*) as total,
                    SUM(CASE WHEN status_verifikasi = 'disetujui' THEN 1 ELSE 0 END) as disetujui,
                    SUM(CASE WHEN status_verifikasi = 'menunggu_verifikasi' THEN 1 ELSE 0 END) as menunggu,
                    SUM(CASE WHEN status_verifikasi = 'ditolak' THEN 1 ELSE 0 END) as ditolak
                FROM partners
            ");
            $partnerStats = $stmtPartners->fetch(PDO::FETCH_ASSOC) ?: ['total' => 0, 'disetujui' => 0, 'menunggu' => 0, 'ditolak' => 0];

            // Hitung antrean verifikasi & statistik kurir (couriers)
            $stmtCouriers = $pdo->query("
                SELECT 
                    COUNT(*) as total,
                    SUM(CASE WHEN status_verifikasi = 'disetujui' THEN 1 ELSE 0 END) as disetujui,
                    SUM(CASE WHEN status_verifikasi = 'menunggu_verifikasi' THEN 1 ELSE 0 END) as menunggu,
                    SUM(CASE WHEN status_verifikasi = 'ditolak' THEN 1 ELSE 0 END) as ditolak
                FROM couriers
            ");
            $courierStats = $stmtCouriers->fetch(PDO::FETCH_ASSOC) ?: ['total' => 0, 'disetujui' => 0, 'menunggu' => 0, 'ditolak' => 0];

            // Hitung statistik customer (users role customer/pelanggan)
            $stmtCustomers = $pdo->query("
                SELECT 
                    COUNT(*) as total,
                    SUM(CASE WHEN status = 'aktif' THEN 1 ELSE 0 END) as aktif,
                    SUM(CASE WHEN status = 'menunggu_verifikasi' THEN 1 ELSE 0 END) as menunggu,
                    SUM(CASE WHEN status = 'ditolak' THEN 1 ELSE 0 END) as ditolak,
                    SUM(CASE WHEN status = 'nonaktif' THEN 1 ELSE 0 END) as nonaktif
                FROM users 
                WHERE role IN ('customer', 'pelanggan')
            ");
            $customerStats = $stmtCustomers->fetch(PDO::FETCH_ASSOC) ?: ['total' => 0, 'aktif' => 0, 'menunggu' => 0, 'ditolak' => 0, 'nonaktif' => 0];

            // Hitung statistik seluruh pengguna (users)
            $stmtUsers = $pdo->query("
                SELECT 
                    COUNT(*) as total,
                    SUM(CASE WHEN role IN ('customer', 'pelanggan') THEN 1 ELSE 0 END) as customer,
                    SUM(CASE WHEN role IN ('owner', 'mitra') THEN 1 ELSE 0 END) as mitra,
                    SUM(CASE WHEN role IN ('driver', 'kurir') THEN 1 ELSE 0 END) as kurir,
                    SUM(CASE WHEN role = 'admin' THEN 1 ELSE 0 END) as admin,
                    SUM(CASE WHEN status = 'aktif' THEN 1 ELSE 0 END) as aktif,
                    SUM(CASE WHEN status = 'menunggu_verifikasi' THEN 1 ELSE 0 END) as menunggu,
                    SUM(CASE WHEN status = 'ditolak' THEN 1 ELSE 0 END) as ditolak,
                    SUM(CASE WHEN status = 'nonaktif' THEN 1 ELSE 0 END) as nonaktif
                FROM users
            ");
            $userStats = $stmtUsers->fetch(PDO::FETCH_ASSOC) ?: ['total' => 0, 'customer' => 0, 'mitra' => 0, 'kurir' => 0, 'admin' => 0, 'aktif' => 0, 'menunggu' => 0, 'ditolak' => 0, 'nonaktif' => 0];

            $pendingPartners = (int) ($partnerStats['menunggu'] ?? 0);
            $pendingCouriers = (int) ($courierStats['menunggu'] ?? 0);

            // Ambil 10 customer terbaru
            $stmtRecentCust = $pdo->query("
                SELECT u.id, u.name, u.email, u.phone, u.status, u.created_at as registered_at,
                       (SELECT COUNT(*) FROM laundry_orders o WHERE o.customer_id = u.id) as order_count
                FROM users u
                WHERE u.role IN ('customer', 'pelanggan')
                ORDER BY u.created_at DESC, u.id DESC
                LIMIT 10
            ");
            $recentCustRows = $stmtRecentCust->fetchAll(PDO::FETCH_ASSOC) ?: [];
            $recentCustomers = [];
            foreach ($recentCustRows as $rc) {
                $recentCustomers[] = [
                    'nama'           => $rc['name'],
                    'email'          => $rc['email'],
                    'phone'          => $rc['phone'] ?: '-',
                    'tanggal_daftar' => $this->formatDate($rc['registered_at']),
                    'jumlah_pesanan' => (int) $rc['order_count'],
                ];
            }

            // Ekstrak keyword pencarian customer jika admin bertanya mencari / info customer
            $searchCustomerKeyword = null;
            $searchCustomerResults = [];
            if (preg_match('/(?:cari|info|detail|tentang|data)\s+(?:customer|pelanggan)\s+([^\?\.\,\!]+)/i', $question, $mCust)
                || preg_match('/(?:customer|pelanggan)\s+(?:bernama|nama)\s+([^\?\.\,\!]+)/i', $question, $mCust)) {
                $rawKw = trim($mCust[1]);
                $cleanKw = preg_replace('/^(bernama|nama|yang)\s+/i', '', $rawKw);
                $searchCustomerKeyword = trim($cleanKw);
            }

            if (!empty($searchCustomerKeyword)) {
                $stmtSearch = $pdo->prepare("
                    SELECT u.id, u.name, u.email, u.phone, u.status, u.created_at as registered_at,
                           (SELECT COUNT(*) FROM laundry_orders o WHERE o.customer_id = u.id) as order_count
                    FROM users u
                    WHERE u.role IN ('customer', 'pelanggan')
                      AND (u.name LIKE :s1 OR u.email LIKE :s2 OR u.phone LIKE :s3)
                    ORDER BY u.created_at DESC, u.id DESC
                    LIMIT 6
                ");
                $kwWildcard = '%' . $searchCustomerKeyword . '%';
                $stmtSearch->execute(['s1' => $kwWildcard, 's2' => $kwWildcard, 's3' => $kwWildcard]);
                $resultsRaw = $stmtSearch->fetchAll(PDO::FETCH_ASSOC) ?: [];
                foreach ($resultsRaw as $res) {
                    $searchCustomerResults[] = [
                        'nama'           => $res['name'],
                        'email'          => $res['email'],
                        'phone'          => $res['phone'] ?: '-',
                        'tanggal_daftar' => $this->formatDate($res['registered_at']),
                        'jumlah_pesanan' => (int) $res['order_count'],
                        'status'         => $res['status'] ?? 'aktif',
                    ];
                }
            }

            $context = [
                'peran'                     => 'admin',
                'nama_terdaftar'            => $registeredName,
                'username'                  => $username,
                'user_id'                   => $adminUserId,
                'mode_akses'                => 'admin_login',
                'waktu_sekarang'            => $this->formatDate(date('Y-m-d H:i:s')),
                'mitra_menunggu_verifikasi' => $pendingPartners,
                'kurir_menunggu_verifikasi' => $pendingCouriers,
                'mitra_laundry'             => [
                    'total'     => (int) ($partnerStats['total'] ?? 0),
                    'disetujui' => (int) ($partnerStats['disetujui'] ?? 0),
                    'menunggu'  => (int) ($partnerStats['menunggu'] ?? 0),
                    'ditolak'   => (int) ($partnerStats['ditolak'] ?? 0),
                ],
                'kurir_armada'              => [
                    'total'     => (int) ($courierStats['total'] ?? 0),
                    'disetujui' => (int) ($courierStats['disetujui'] ?? 0),
                    'menunggu'  => (int) ($courierStats['menunggu'] ?? 0),
                    'ditolak'   => (int) ($courierStats['ditolak'] ?? 0),
                ],
                'customer'                  => [
                    'total'    => (int) ($customerStats['total'] ?? 0),
                    'aktif'    => (int) ($customerStats['aktif'] ?? 0),
                    'menunggu' => (int) ($customerStats['menunggu'] ?? 0),
                    'ditolak'  => (int) ($customerStats['ditolak'] ?? 0),
                    'nonaktif' => (int) ($customerStats['nonaktif'] ?? 0),
                ],
                'customer_terbaru'          => $recentCustomers,
                'pencarian_customer'        => [
                    'kata_kunci'      => $searchCustomerKeyword,
                    'total_ditemukan' => count($searchCustomerResults),
                    'hasil'           => $searchCustomerResults,
                ],
                'total_pengguna'            => [
                    'total'    => (int) ($userStats['total'] ?? 0),
                    'customer' => (int) ($userStats['customer'] ?? 0),
                    'mitra'    => (int) ($userStats['mitra'] ?? 0),
                    'kurir'    => (int) ($userStats['kurir'] ?? 0),
                    'admin'    => (int) ($userStats['admin'] ?? 0),
                    'aktif'    => (int) ($userStats['aktif'] ?? 0),
                    'menunggu' => (int) ($userStats['menunggu'] ?? 0),
                    'ditolak'  => (int) ($userStats['ditolak'] ?? 0),
                    'nonaktif' => (int) ($userStats['nonaktif'] ?? 0),
                ],
                'fitur_admin'               => [
                    'Verifikasi Mitra Laundry' => '/admin/verifikasi',
                    'Verifikasi Kurir'         => '/admin/verifikasi',
                    'Tab Customer'             => '/admin/verifikasi?tab=customers',
                    'Riwayat Chat'             => '/admin/chats',
                ],
                'kontak_bantuan'            => $this->config['support'] ?? [],
            ];

            return ['context' => $context, 'order_numbers' => []];
        } catch (PDOException $e) {
            AILogger::error('admin_context_query_failed', ['admin_id' => $adminUserId, 'error' => $e->getMessage()]);
            throw new AIException('Data admin sementara tidak dapat diakses. Silakan coba lagi nanti.', 500, 'db_error', null, $e);
        }
    }

    /**
     * Konteks untuk pengunjung yang belum login (atau bukan customer):
     * HANYA informasi publik. Tidak ada query order/pembayaran sama sekali.
     * Tetap berfungsi walau database bermasalah (daftar layanan dikosongkan).
     *
     * @return array{context: array, order_numbers: string[]}
     */
    public function buildGuest(): array
    {
        $services = [];
        $note = 'Pengguna belum login. Tidak ada data pesanan atau pembayaran yang tersedia. Untuk melihat status pesanan, pengguna harus login lewat tombol Masuk.';

        $pdo = BaseModel::getPdo();
        if ($pdo) {
            try {
                $services = $this->fetchServices($pdo);
            } catch (PDOException $e) {
                AILogger::error('guest_services_query_failed', ['error' => $e->getMessage()]);
            }
        }
        if (empty($services)) {
            $note .= ' Daftar layanan dan harga sementara tidak tersedia.';
        }

        return [
            'context' => [
                'peran'            => 'tamu',
                'nama_terdaftar'   => null,
                'mode_akses'       => 'tamu',
                'waktu_sekarang'   => $this->formatDate(date('Y-m-d H:i:s')),
                'catatan_akses'    => $note,
                'layanan_tersedia' => $services,
                'info_ongkir'      => $this->describeDeliveryFees(),
                'faq'              => $this->config['faq'] ?? [],
                'kontak_bantuan'   => $this->config['support'] ?? [],
            ],
            'order_numbers' => [],
        ];
    }

    // ------------------------------------------------------------------
    // Query (semua order query memakai customer_id)
    // ------------------------------------------------------------------

    private const ORDER_SELECT = "
        SELECT o.id, o.order_number, o.order_status, o.weight_kg,
               o.subtotal_amount, o.shipping_fee, o.additional_fee, o.discount_amount, o.total_amount,
               o.created_at, o.updated_at,
               st.store_name,
               p.payment_method, p.payment_status, p.amount AS payment_amount,
               p.expired_at AS payment_expired_at, p.paid_at AS payment_paid_at, p.qris_url
        FROM laundry_orders o
        JOIN stores st ON st.id = o.store_id
        LEFT JOIN payments p ON p.order_id = o.id
    ";

    private function fetchRecentOrders(PDO $pdo, int $customerId, int $limit): array
    {
        $limit = max(1, min(10, $limit));
        $stmt = $pdo->prepare(self::ORDER_SELECT . " WHERE o.customer_id = :cid ORDER BY o.id DESC LIMIT {$limit}");
        $stmt->execute(['cid' => $customerId]);
        return $stmt->fetchAll();
    }

    private function fetchFocusOrder(PDO $pdo, int $customerId, ?int $orderId, ?string $orderNumber): ?array
    {
        $stmt = $pdo->prepare(
            self::ORDER_SELECT . " WHERE o.customer_id = :cid AND (o.id = :oid OR o.order_number = :onum) LIMIT 1"
        );
        $stmt->execute([
            'cid'  => $customerId,
            'oid'  => $orderId ?? 0,
            'onum' => $orderNumber ?? '',
        ]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    private function fetchItems(PDO $pdo, array $orderIds): array
    {
        if (empty($orderIds)) {
            return [];
        }
        $in = implode(',', array_fill(0, count($orderIds), '?'));
        $stmt = $pdo->prepare("
            SELECT oi.order_id, s.service_name, s.unit, s.estimated_duration_hours,
                   oi.qty, oi.price_per_unit, oi.subtotal
            FROM order_items oi
            JOIN services s ON s.id = oi.service_id
            WHERE oi.order_id IN ({$in})
            ORDER BY oi.id ASC
        ");
        $stmt->execute($orderIds);

        $grouped = [];
        foreach ($stmt->fetchAll() as $row) {
            $grouped[(int) $row['order_id']][] = $row;
        }
        return $grouped;
    }

    private function fetchHistories(PDO $pdo, array $orderIds): array
    {
        if (empty($orderIds)) {
            return [];
        }
        $in = implode(',', array_fill(0, count($orderIds), '?'));
        // Catatan (notes) sengaja tidak diambil: hanya status & waktu.
        $stmt = $pdo->prepare("
            SELECT order_id, new_status, created_at
            FROM order_status_histories
            WHERE order_id IN ({$in})
            ORDER BY id ASC
        ");
        $stmt->execute($orderIds);

        $grouped = [];
        foreach ($stmt->fetchAll() as $row) {
            $grouped[(int) $row['order_id']][] = $row;
        }
        return $grouped;
    }

    private function fetchServices(PDO $pdo): array
    {
        $stmt = $pdo->query("
            SELECT s.service_name, s.unit, s.price_per_unit, s.estimated_duration_hours, s.description, st.store_name
            FROM services s
            JOIN stores st ON st.id = s.store_id
            WHERE s.is_active = 1 AND st.is_open = 1
            ORDER BY st.id ASC, s.id ASC
            LIMIT 30
        ");

        $services = [];
        foreach ($stmt->fetchAll() as $row) {
            $services[] = [
                'nama'            => $row['service_name'],
                'toko'            => $row['store_name'],
                'harga'           => $this->money($row['price_per_unit']) . ' per ' . $row['unit'],
                'estimasi_durasi' => (int) $row['estimated_duration_hours'] . ' jam',
                'deskripsi'       => $row['description'],
            ];
        }
        return $services;
    }

    // ------------------------------------------------------------------
    // Formatting
    // ------------------------------------------------------------------

    private function formatOrder(array $row, array $items, array $history, bool $isFocus, bool $isLatest): array
    {
        $status = $row['order_status'];
        [$statusLabel, $statusDesc] = self::ORDER_STATUS[$status] ?? [$status, ''];

        $durations = array_map(fn($i) => (int) $i['estimated_duration_hours'], $items);
        $maxHours = $durations ? max($durations) : null;

        $itemList = array_map(fn($i) => [
            'layanan'          => $i['service_name'],
            'jumlah'           => $this->qty($i['qty']) . ' ' . $i['unit'],
            'harga_per_satuan' => $this->money($i['price_per_unit']),
            'subtotal'         => $this->money($i['subtotal']),
        ], $items);

        $totalFinal = in_array($status, self::FINAL_TOTAL_STATUSES, true);

        $order = [
            'nomor_pesanan'    => $row['order_number'],
            'pesanan_terbaru'  => $isLatest,
            'dibahas_pelanggan' => $isFocus,
            'toko'             => $row['store_name'],
            'status_pesanan'   => [
                'kode'       => $status,
                'label'      => $statusLabel,
                'keterangan' => $statusDesc,
            ],
            'dibuat_pada'      => $this->formatDate($row['created_at']),
            'diperbarui_pada'  => $this->formatDate($row['updated_at']),
            'layanan_dipesan'  => $itemList,
            'berat_kg'         => ((float) $row['weight_kg']) > 0 ? $this->qty($row['weight_kg']) : 'belum ditimbang',
            'rincian_biaya'    => [
                'subtotal_layanan' => $this->money($row['subtotal_amount']),
                'ongkos_kirim'     => $this->money($row['shipping_fee']),
                'biaya_tambahan'   => $this->money($row['additional_fee']),
                'diskon'           => $this->money($row['discount_amount']),
                'total'            => $this->money($row['total_amount']),
            ],
            'total_sudah_final' => $totalFinal,
            'pembayaran'        => $this->formatPayment($row),
            'estimasi'          => $this->formatEstimate($status, $history, $maxHours),
            'riwayat_status'    => $this->formatHistory($history),
        ];

        if (!$totalFinal && $status !== 'cancelled') {
            $order['catatan_total'] = 'Total ini hanya perkiraan awal karena pakaian belum ditimbang.';
        }

        return $order;
    }

    private function formatPayment(array $row): array
    {
        if ($row['payment_status'] === null) {
            return [
                'ada_data_pembayaran' => false,
                'keterangan'          => 'Belum ada pembayaran. Pembayaran baru tersedia setelah pakaian ditimbang (status "Sudah Ditimbang").',
            ];
        }

        $status = $row['payment_status'];
        $method = $row['payment_method'];

        $descriptions = [
            'paid'      => 'Lunas. Pembayaran sudah diterima.',
            'expired'   => 'Kedaluwarsa. Batas waktu pembayaran terlewati.',
            'failed'    => 'Pembayaran gagal.',
            'cancelled' => 'Pembayaran dibatalkan.',
            'refunded'  => 'Pembayaran sudah dikembalikan (refund).',
            'pending'   => $method === 'cash'
                ? 'Menunggu konfirmasi pembayaran tunai oleh pemilik laundry. Belum dinyatakan lunas.'
                : 'Menunggu pembayaran. Belum dinyatakan lunas.',
        ];

        $payment = [
            'ada_data_pembayaran' => true,
            'metode'              => self::PAYMENT_METHOD[$method] ?? $method,
            'status_pembayaran'   => $status,
            'keterangan'          => $descriptions[$status] ?? $status,
            'jumlah'              => $this->money($row['payment_amount']),
            'qris_url'            => !empty($row['qris_url']) ? $row['qris_url'] : null,
            'pay_page'            => '/payment/' . $row['id'] . '/qris',
        ];

        if ($status === 'paid' && !empty($row['payment_paid_at'])) {
            $payment['dibayar_pada'] = $this->formatDate($row['payment_paid_at']);
        }

        if ($status === 'pending' && !empty($row['payment_expired_at'])) {
            $payment['batas_waktu_pembayaran'] = $this->formatDate($row['payment_expired_at']);
            if (strtotime($row['payment_expired_at']) < time()) {
                $payment['catatan'] = 'Batas waktu sudah lewat; status akan diperbarui otomatis oleh sistem.';
            }
        }

        return $payment;
    }

    private function formatEstimate(string $status, array $history, ?int $maxHours): array
    {
        $estimate = [
            'durasi_pengerjaan' => $maxHours !== null ? "{$maxHours} jam" : 'tidak diketahui',
        ];

        if ($status === 'cancelled') {
            $estimate['catatan'] = 'Pesanan dibatalkan.';
        } elseif ($status === 'in_process') {
            $startedAt = null;
            foreach ($history as $h) {
                if ($h['new_status'] === 'in_process') {
                    $startedAt = $h['created_at'];
                }
            }
            if ($startedAt !== null && $maxHours !== null) {
                $estimate['mulai_diproses']    = $this->formatDate($startedAt);
                $estimate['perkiraan_selesai'] = $this->formatDate(date('Y-m-d H:i:s', strtotime($startedAt) + $maxHours * 3600));
                $estimate['catatan']           = 'Perkiraan = waktu mulai diproses + durasi layanan. Bisa berubah.';
            } else {
                $estimate['catatan'] = 'Waktu mulai diproses tidak tercatat, perkiraan selesai belum dapat dihitung.';
            }
        } elseif (in_array($status, self::BEFORE_PROCESS_STATUSES, true)) {
            $estimate['catatan'] = 'Perkiraan selesai belum dapat dihitung. Durasi pengerjaan dihitung sejak laundry mulai diproses (setelah pembayaran terkonfirmasi).';
        } elseif (in_array($status, self::AFTER_PROCESS_STATUSES, true)) {
            $estimate['catatan'] = 'Proses pencucian sudah selesai.';
        }

        return $estimate;
    }

    private function formatHistory(array $history): array
    {
        $out = [];
        foreach (array_slice($history, -8) as $h) {
            $out[] = [
                'status' => self::ORDER_STATUS[$h['new_status']][0] ?? $h['new_status'],
                'waktu'  => $this->formatDate($h['created_at']),
            ];
        }
        return $out;
    }

    private function describeDeliveryFees(): string
    {
        $fees = $this->appConfig['delivery_fees'] ?? null;
        if (!$fees) {
            return 'Ongkos kirim dihitung berdasarkan jarak.';
        }

        $t1 = $fees['tier_1'];
        $t2 = $fees['tier_2'];

        return sprintf(
            'Ongkos kirim berdasarkan jarak: sampai %s km %s; sampai %s km %s; di atas %s km ditambah %s per km tambahan.',
            $this->qty($t1['max_km']),
            $this->money($t1['fee']),
            $this->qty($t2['max_km']),
            $this->money($t2['fee']),
            $this->qty($t2['max_km']),
            $this->money($fees['extra_km_rate'])
        );
    }

    private function money($value): string
    {
        $v = (float) $value;
        $decimals = (abs($v - round($v)) < 0.005) ? 0 : 2;
        return 'Rp ' . number_format($v, $decimals, ',', '.');
    }

    private function qty($value): string
    {
        return rtrim(rtrim(sprintf('%.2f', (float) $value), '0'), '.');
    }

    private function formatDate(?string $timestamp): ?string
    {
        if (empty($timestamp)) {
            return null;
        }
        $t = strtotime($timestamp);
        if ($t === false) {
            return null;
        }
        return sprintf('%d %s %s, %s WIB', (int) date('j', $t), self::MONTHS[(int) date('n', $t)], date('Y', $t), date('H:i', $t));
    }
}
