<?php

namespace App\Controllers;

use App\Helpers\Session;
use App\Models\Service;
use App\Models\Store;
use App\Models\CustomerAddress;
use App\Models\Order;
use App\Services\GoogleMapsService;
use App\Services\DeliveryFeeService;
use Exception;

class OrderController extends BaseController
{
    public function index(): void
    {
        $user       = Session::get('user');
        $orderModel = new Order();

        $activeOrders  = $orderModel->getActiveCustomerOrders($user['id']);
        $historyOrders = $orderModel->getCustomerOrderHistory($user['id']);

        $this->render('orders/index', [
            'title'         => 'Pesanan Laundry - Marketplace Laundry',
            'user'          => $user,
            'activeOrders'  => $activeOrders,
            'historyOrders' => $historyOrders,
            'tab'           => $_GET['tab'] ?? 'active',
        ]);
    }

    public function create(): void
    {
        $user = Session::get('user');

        $addressModel = new CustomerAddress();
        $primaryAddress = $addressModel->getPrimaryAddress($user['id']);

        $storeModel = new Store();
        $stores = $storeModel->getOpenApprovedStores();

        // Hitung perkiraan jarak garis lurus (Haversine) jika koordinat pelanggan tersedia
        $custLat = (float) ($primaryAddress['latitude'] ?? 0);
        $custLng = (float) ($primaryAddress['longitude'] ?? 0);

        foreach ($stores as &$store) {
            $stLat = (float) ($store['latitude'] ?? 0);
            $stLng = (float) ($store['longitude'] ?? 0);
            if ($custLat != 0 && $custLng != 0 && $stLat != 0 && $stLng != 0) {
                $store['distance_km'] = $this->calculateHaversineDistance($custLat, $custLng, $stLat, $stLng);
            } else {
                $store['distance_km'] = null;
            }
        }
        unset($store);

        $storeId = isset($_GET['store_id']) ? (int) $_GET['store_id'] : 0;
        $selectedStore = null;
        $services = [];

        if ($storeId > 0) {
            $selectedStore = $storeModel->findOpenApprovedStore($storeId);
            if (!$selectedStore) {
                $rawStore = $storeModel->find($storeId);
                if ($rawStore && (int)($rawStore['is_open'] ?? 1) === 0) {
                    Session::setFlash('error', 'Toko laundry yang Anda pilih saat ini sedang TUTUP sementara. Silakan pilih toko lain yang sedang buka.');
                } else {
                    Session::setFlash('error', 'Toko laundry yang dipilih tidak tersedia atau belum disetujui.');
                }
                $this->redirect('/customer/orders/create');
                return;
            }
            $serviceModel = new Service();
            $services = $serviceModel->getActiveByStore($storeId);
        }

        $this->render('orders/create', [
            'title'         => 'Buat Order Laundry Baru - Marketplace Laundry',
            'user'          => $user,
            'stores'        => $stores,
            'selectedStore' => $selectedStore,
            'storeId'       => $storeId,
            'services'      => $services,
            'address'       => $primaryAddress,
            'errors'        => Session::getFlash('errors_data')
                ? json_decode(Session::getFlash('errors_data'), true)
                : []
        ]);
    }

    /**
     * Hitung jarak perkiraan garis lurus (Haversine) antar koordinat (km).
     */
    private function calculateHaversineDistance(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $earthRadius = 6371; // km
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);
        $a = sin($dLat / 2) * sin($dLat / 2) +
             cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
             sin($dLon / 2) * sin($dLon / 2);
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
        return round($earthRadius * $c, 1);
    }

    public function confirm(): void
    {
        $token = $_POST['_token'] ?? '';
        if (!Session::verifyCsrfToken($token)) {
            Session::setFlash('error', 'Token CSRF tidak valid.');
            $this->redirect('/customer/orders/create');
            return;
        }

        $storeId   = (int) ($_POST['store_id'] ?? 0);
        $serviceId = (int) ($_POST['service_id'] ?? 0);
        $address   = trim($_POST['full_address'] ?? '');
        $rt        = trim($_POST['rt'] ?? '');
        $rw        = trim($_POST['rw'] ?? '');
        $phone     = trim($_POST['recipient_phone'] ?? '');
        $notes     = trim($_POST['notes'] ?? '');
        $latitude  = (float) ($_POST['latitude'] ?? 0);
        $longitude = (float) ($_POST['longitude'] ?? 0);

        if (!$storeId) {
            Session::setFlash('error', 'Silakan pilih toko laundry terlebih dahulu.');
            $this->redirect('/customer/orders/create');
            return;
        }

        $storeModel = new Store();
        $store = $storeModel->findOpenApprovedStore($storeId);
        if (!$store) {
            Session::setFlash('error', 'Toko laundry yang dipilih tidak tersedia atau belum disetujui.');
            $this->redirect('/customer/orders/create');
            return;
        }

        // Validation
        $errors = [];
        if (!$serviceId) {
            $errors['service_id'] = 'Pilihan layanan laundry wajib dipilih.';
        }
        if (empty($address) || strlen($address) < 10) {
            $errors['full_address'] = 'Alamat lengkap penjemputan wajib diisi minimal 10 karakter.';
        }
        if (empty($rt) || !preg_match('/^[0-9]{1,4}$/', $rt)) {
            $errors['rt'] = 'RT wajib diisi angka.';
        }
        if (empty($rw) || !preg_match('/^[0-9]{1,4}$/', $rw)) {
            $errors['rw'] = 'RW wajib diisi angka.';
        }
        if (empty($phone) || !preg_match('/^[0-9+ -]{9,16}$/', $phone)) {
            $errors['recipient_phone'] = 'Nomor telepon wajib diisi dengan format yang benar.';
        }
        if ($latitude == 0 || $longitude == 0) {
            $errors['coordinates'] = 'Koordinat lokasi (latitude/longitude) belum terdeteksi. Aktifkan GPS atau izinkan akses lokasi.';
        }

        if (!empty($errors)) {
            Session::setFlash('error', 'Terdapat kesalahan pada isian form order Anda.');
            Session::setFlash('errors_data', json_encode($errors));
            $this->redirect('/customer/orders/create?store_id=' . $storeId);
            return;
        }

        // Fetch selected service + store coordinates & pastikan layanan milik toko yang dipilih
        $serviceModel = new Service();
        $selectedService = $serviceModel->findWithStore($serviceId);

        if (!$selectedService || (int)$selectedService['store_id'] !== $storeId) {
            Session::setFlash('error', 'Layanan laundry yang dipilih tidak valid atau bukan milik toko yang dipilih.');
            $this->redirect('/customer/orders/create?store_id=' . $storeId);
            return;
        }

        // Validate store has coordinates
        $storeLat = (float) ($selectedService['store_latitude'] ?? 0);
        $storeLng = (float) ($selectedService['store_longitude'] ?? 0);
        if ($storeLat == 0 || $storeLng == 0) {
            Session::setFlash('error', 'Koordinat toko laundry belum dikonfigurasi. Hubungi pemilik toko.');
            $this->redirect('/customer/orders/create?store_id=' . $storeId);
            return;
        }

        // Calculate distance via Google Maps API
        $distanceKm = null;
        $shippingFee = null;
        $mapsError = null;

        try {
            $mapsService = new GoogleMapsService();
            $distanceKm = $mapsService->getDrivingDistanceKm($latitude, $longitude, $storeLat, $storeLng);

            $feeService = new DeliveryFeeService();
            $shippingFee = $feeService->calculateFee($distanceKm);
        } catch (Exception $e) {
            $mapsError = $e->getMessage();
        }

        $user = Session::get('user');

        $this->render('orders/confirm', [
            'title'       => 'Konfirmasi Pesanan Laundry - Marketplace Laundry',
            'user'        => $user,
            'service'     => $selectedService,
            'distanceKm'  => $distanceKm,
            'shippingFee' => $shippingFee,
            'mapsError'   => $mapsError,
            'postData'    => [
                'store_id'        => $storeId,
                'service_id'      => $serviceId,
                'full_address'    => $address,
                'rt'              => $rt,
                'rw'              => $rw,
                'recipient_phone' => $phone,
                'district'        => trim($_POST['district'] ?? ''),
                'city'            => trim($_POST['city'] ?? ''),
                'latitude'        => $latitude,
                'longitude'       => $longitude,
                'notes'           => $notes,
                'distance_km'     => $distanceKm,
                'shipping_fee'    => $shippingFee,
            ]
        ]);
    }

    public function store(): void
    {
        $token = $_POST['_token'] ?? '';
        if (!Session::verifyCsrfToken($token)) {
            Session::setFlash('error', 'Token CSRF tidak valid.');
            $this->redirect('/customer/orders/create');
            return;
        }

        $user = Session::get('user');
        $storeId   = (int) ($_POST['store_id'] ?? 0);
        $serviceId = (int) ($_POST['service_id'] ?? 0);

        if (!$storeId) {
            Session::setFlash('error', 'Toko laundry wajib dipilih.');
            $this->redirect('/customer/orders/create');
            return;
        }

        $storeModel = new Store();
        $store = $storeModel->findOpenApprovedStore($storeId);
        if (!$store) {
            $rawStore = $storeModel->find($storeId);
            if ($rawStore && (int)($rawStore['is_open'] ?? 1) === 0) {
                Session::setFlash('error', 'Toko laundry yang Anda pilih saat ini sedang TUTUP sementara. Silakan pilih toko lain.');
            } else {
                Session::setFlash('error', 'Toko laundry tidak valid atau belum disetujui.');
            }
            $this->redirect('/customer/orders/create');
            return;
        }

        $serviceModel = new Service();
        $selectedService = $serviceModel->findWithStore($serviceId);

        if (!$selectedService || (int)$selectedService['store_id'] !== $storeId) {
            Session::setFlash('error', 'Layanan laundry tidak valid atau tidak cocok dengan toko yang dipilih.');
            $this->redirect('/customer/orders/create?store_id=' . $storeId);
            return;
        }

        $latitude    = (float) ($_POST['latitude'] ?? 0);
        $longitude   = (float) ($_POST['longitude'] ?? 0);

        // Strict Coordinate Validation
        if ($latitude < -90 || $latitude > 90 || $longitude < -180 || $longitude > 180 || ($latitude == 0 && $longitude == 0)) {
            Session::setFlash('error', 'Koordinat lokasi tidak valid.');
            $this->redirect('/customer/orders/create');
            return;
        }

        $storeLat = (float) ($selectedService['store_latitude'] ?? 0);
        $storeLng = (float) ($selectedService['store_longitude'] ?? 0);
        if ($storeLat == 0 || $storeLng == 0) {
            Session::setFlash('error', 'Koordinat toko laundry belum dikonfigurasi. Hubungi pemilik toko.');
            $this->redirect('/customer/orders/create');
            return;
        }

        // Authoritative Server-side Distance & Fee Calculation (Anti-Tampering)
        try {
            $mapsService = new GoogleMapsService();
            $distanceKm = $mapsService->getDrivingDistanceKm($latitude, $longitude, $storeLat, $storeLng);

            $feeService = new DeliveryFeeService();
            $shippingFee = $feeService->calculateFee($distanceKm);
        } catch (Exception $e) {
            Session::setFlash('error', 'Gagal menghitung ongkir: ' . $e->getMessage() . ' Pesanan tidak dapat dibuat tanpa kalkulasi ongkir yang valid.');
            $this->redirect('/customer/orders/create');
            return;
        }

        if ($distanceKm <= 0 || $shippingFee < 0) {
            Session::setFlash('error', 'Ongkir tidak dapat dihitung. Pastikan koordinat lokasi Anda valid.');
            $this->redirect('/customer/orders/create');
            return;
        }

        $orderData = [
            'customer_id'  => $user['id'],
            'store_id'     => $selectedService['store_id'],
            'notes'        => trim($_POST['notes'] ?? ''),
            'distance_km'  => $distanceKm,
            'shipping_fee' => $shippingFee,
        ];

        $serviceItemData = [
            'service_id'     => $selectedService['id'],
            'price_per_unit' => $selectedService['price_per_unit'],
            'qty'            => 1.00
        ];

        $addressData = [
            'recipient_name'  => $user['name'],
            'recipient_phone' => trim($_POST['recipient_phone'] ?? ''),
            'full_address'    => trim($_POST['full_address'] ?? ''),
            'rt'              => trim($_POST['rt'] ?? ''),
            'rw'              => trim($_POST['rw'] ?? ''),
            'district'        => trim($_POST['district'] ?? ''),
            'city'            => trim($_POST['city'] ?? ''),
            'latitude'        => $latitude,
            'longitude'       => $longitude,
        ];

        try {
            $orderModel = new Order();
            $result = $orderModel->createOrderTransaction($orderData, $serviceItemData, $addressData);

            Session::setFlash('success', "Order laundry berhasil dibuat! Nomor Order: {$result['order_number']}. Ongkir: Rp " . number_format($shippingFee, 0, ',', '.') . " (Jarak: {$distanceKm} km)");
            $this->redirect("/customer/orders/{$result['id']}");
        } catch (Exception $e) {
            Session::setFlash('error', 'Gagal membuat order laundry: ' . $e->getMessage());
            $this->redirect('/customer/orders/create');
        }
    }

    public function show(string $id): void
    {
        $user = Session::get('user');
        $orderId = (int) $id;

        $orderModel = new Order();
        $order = $orderModel->getOrderDetail($orderId, $user['id']);

        if (!$order) {
            Session::setFlash('error', 'Pesanan tidak ditemukan.');
            $this->redirect('/customer/orders');
            return;
        }

        $this->render('orders/show', [
            'title' => "Detail Order #{$order['order_number']} - Marketplace Laundry",
            'user'  => $user,
            'order' => $order
        ]);
    }

    public function complete(string $id): void
    {
        $token = $_POST['_token'] ?? '';
        if (!Session::verifyCsrfToken($token)) {
            Session::setFlash('error', 'Token CSRF tidak valid.');
            $this->redirect("/customer/orders/{$id}");
            return;
        }

        $user = Session::get('user');
        $orderId = (int) $id;

        $orderModel = new Order();
        $order = $orderModel->getOrderDetail($orderId, $user['id']);

        if (!$order) {
            Session::setFlash('error', 'Pesanan tidak ditemukan.');
            $this->redirect('/customer/orders');
            return;
        }

        if (!in_array($order['order_status'], ['delivered', 'ready_for_delivery', 'in_process'])) {
            Session::setFlash('error', 'Pesanan belum dapat diselesaikan pada status saat ini.');
            $this->redirect("/customer/orders/{$orderId}");
            return;
        }

        $orderModel->updateOrderStatusWithNotification(
            $orderId,
            'completed',
            'Pesanan Selesai',
            'Pelanggan telah mengonfirmasi penerimaan pakaian. Transaksi selesai. Terima kasih!',
            $user['id']
        );

        Session::setFlash('success', 'Terima kasih! Pesanan telah selesai.');
        $this->redirect("/customer/orders/{$orderId}");
    }
}

