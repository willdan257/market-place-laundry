<?php

namespace App\Controllers;

use App\Helpers\Session;
use App\Models\Order;
use App\Models\Driver;
use App\Models\Store;

class OwnerController extends BaseController
{
    private function getOwnerStore(int $ownerUserId): ?array
    {
        $storeModel = new Store();
        return $storeModel->findByOwnerId($ownerUserId);
    }

    public function orders(): void
    {
        $user = Session::get('user');
        $store = $this->getOwnerStore($user['id']);

        if (!$store) {
            Session::setFlash('error', 'Akun Anda belum terhubung dengan toko laundry manapun.');
            $this->render('owner/orders', [
                'title' => 'Kelola Pesanan Laundry - Owner Dashboard',
                'user' => $user,
                'store' => null,
                'orders' => []
            ]);
            return;
        }

        $orderModel = new Order();
        $orders = $orderModel->getStoreOrders($store['id']);

        $this->render('owner/orders', [
            'title' => 'Kelola Pesanan Laundry - Owner Dashboard',
            'user' => $user,
            'store' => $store,
            'orders' => $orders
        ]);
    }

    public function drivers(): void
    {
        $user = Session::get('user');
        $driverModel = new Driver();
        $drivers = $driverModel->getAssignableDrivers();

        $this->render('owner/drivers', [
            'title' => 'Daftar & Status Kurir - Owner Dashboard',
            'user' => $user,
            'drivers' => $drivers
        ]);
    }

    public function assignForm(string $id): void
    {
        $user = Session::get('user');
        $orderId = (int) $id;

        $store = $this->getOwnerStore($user['id']);
        if (!$store) {
            Session::setFlash('error', 'Akun Anda belum terhubung dengan toko laundry.');
            $this->redirect('/owner/orders');
            return;
        }

        $orderModel = new Order();
        $order = $orderModel->getOrderDetail($orderId);

        // Strict Authorization: Owner can ONLY access orders belonging to their store!
        if (!$order || (int)$order['store_id'] !== (int)$store['id']) {
            Session::setFlash('error', 'Akses ditolak! Anda hanya dapat mengelola order dari toko laundry Anda sendiri.');
            $this->redirect('/owner/orders');
            return;
        }

        $status = $order['order_status'];
        $allowedStatuses = ['pending_pickup', 'driver_assigned', 'ready_for_delivery', 'delivery_assigned'];
        if (!in_array($status, $allowedStatuses, true)) {
            Session::setFlash('error', "Penugasan kurir tidak diizinkan pada status order [{$status}].");
            $this->redirect("/owner/orders/{$orderId}");
            return;
        }

        $driverModel = new Driver();
        $drivers = $driverModel->getAssignableDrivers();

        $this->render('owner/assign', [
            'title' => "Penugasan Kurir Order #{$order['order_number']}",
            'user' => $user,
            'order' => $order,
            'drivers' => $drivers
        ]);
    }

    public function assignStore(string $id): void
    {
        $token = $_POST['_token'] ?? '';
        if (!Session::verifyCsrfToken($token)) {
            Session::setFlash('error', 'Token CSRF tidak valid.');
            $this->redirect("/owner/orders/{$id}/assign");
            return;
        }

        $user = Session::get('user');
        $orderId = (int) $id;
        $driverId = (int) ($_POST['driver_id'] ?? 0);

        $store = $this->getOwnerStore($user['id']);
        if (!$store) {
            Session::setFlash('error', 'Akun Anda belum terhubung dengan toko laundry.');
            $this->redirect('/owner/orders');
            return;
        }

        $orderModel = new Order();
        $order = $orderModel->getOrderDetail($orderId);

        // Strict Authorization: Owner can ONLY update orders belonging to their store!
        if (!$order || (int)$order['store_id'] !== (int)$store['id']) {
            Session::setFlash('error', 'Akses ditolak! Anda hanya dapat mengelola order dari toko laundry Anda sendiri.');
            $this->redirect('/owner/orders');
            return;
        }

        // GUARD: check order status
        $status = $order['order_status'];
        $allowedPickupStatuses   = ['pending_pickup', 'driver_assigned'];
        $allowedDeliveryStatuses = ['ready_for_delivery', 'delivery_assigned'];
        $allowedStatuses = array_merge($allowedPickupStatuses, $allowedDeliveryStatuses);

        if (!in_array($status, $allowedStatuses, true)) {
            Session::setFlash('error', "Penugasan kurir tidak diizinkan pada status [{$status}]. Penugasan penjemput hanya saat status pending_pickup atau driver_assigned, dan penugasan pengantar hanya saat ready_for_delivery atau delivery_assigned.");
            $this->redirect("/owner/orders/{$orderId}");
            return;
        }

        if (!$driverId) {
            Session::setFlash('error', 'Silakan pilih kurir dari daftar.');
            $this->redirect("/owner/orders/{$orderId}/assign");
            return;
        }

        $driverModel = new Driver();
        $driver = $driverModel->findAssignableDriver($driverId);
        if (!$driver) {
            Session::setFlash('error', 'Kurir yang dipilih tidak valid, belum disetujui admin, atau akun tidak aktif.');
            $this->redirect("/owner/orders/{$orderId}/assign");
            return;
        }

        $success = $orderModel->assignDriverToOrder($orderId, $driverId, $user['id']);

        if ($success) {
            Session::setFlash('success', 'Kurir berhasil ditugaskan! Notifikasi telah dikirim ke pelanggan dan kurir.');
        } else {
            Session::setFlash('error', 'Gagal menugaskan kurir.');
        }

        $this->redirect('/owner/orders');
    }

    /**
     * GET /owner/orders/{id}
     * View full order detail for the owner's store.
     */
    public function orderDetail(string $id): void
    {
        $user = Session::get('user');
        $orderId = (int) $id;

        $store = $this->getOwnerStore($user['id']);
        if (!$store) {
            Session::setFlash('error', 'Akun Anda belum terhubung dengan toko laundry.');
            $this->redirect('/owner/orders');
            return;
        }

        $orderModel = new Order();
        $order = $orderModel->getOrderDetail($orderId);

        if (!$order || (int)$order['store_id'] !== (int)$store['id']) {
            Session::setFlash('error', 'Akses ditolak! Order tidak ditemukan atau bukan milik toko Anda.');
            $this->redirect('/owner/orders');
            return;
        }

        $this->render('owner/order_detail', [
            'title' => "Detail Order #{$order['order_number']} - Owner",
            'user'  => $user,
            'store' => $store,
            'order' => $order,
        ]);
    }

    /**
     * POST /owner/orders/{id}/weigh
     * Owner sets laundry weight and triggers invoice recalculation.
     * Status transitions: arrived_at_store → weighed
     */
    public function processWeigh(string $id): void
    {
        $token = $_POST['_token'] ?? '';
        if (!Session::verifyCsrfToken($token)) {
            Session::setFlash('error', 'Token CSRF tidak valid.');
            $this->redirect("/owner/orders/{$id}");
            return;
        }

        $user    = Session::get('user');
        $orderId = (int) $id;

        $store = $this->getOwnerStore($user['id']);
        if (!$store) {
            Session::setFlash('error', 'Akun Anda belum terhubung dengan toko laundry.');
            $this->redirect('/owner/orders');
            return;
        }

        $orderModel = new Order();
        $order = $orderModel->getOrderDetail($orderId);

        if (!$order || (int)$order['store_id'] !== (int)$store['id']) {
            Session::setFlash('error', 'Akses ditolak! Order tidak ditemukan atau bukan milik toko Anda.');
            $this->redirect('/owner/orders');
            return;
        }

        $redirectTarget = (isset($_POST['redirect_to']) && $_POST['redirect_to'] === 'dashboard')
            || (isset($_SERVER['HTTP_REFERER']) && str_contains($_SERVER['HTTP_REFERER'], '/owner/dashboard'))
            ? '/owner/dashboard'
            : "/owner/orders/{$orderId}";

        // GUARD: Hanya boleh saat status arrived_at_store
        if ($order['order_status'] !== 'arrived_at_store') {
            Session::setFlash('error', 'Penimbangan hanya dapat dilakukan saat pakaian telah tiba di toko (status arrived_at_store).');
            $this->redirect($redirectTarget);
            return;
        }

        $weightKg = (float) ($_POST['weight_kg'] ?? 0);

        if ($weightKg <= 0 || $weightKg > 500) {
            Session::setFlash('error', 'Berat laundry harus diisi antara 0.01 dan 500 kg.');
            $this->redirect($redirectTarget);
            return;
        }

        // Get price per unit from order items
        $pricePerUnit = 0.0;
        if (!empty($order['items'])) {
            $pricePerUnit = (float) ($order['items'][0]['price_per_unit'] ?? 0);
        }

        if ($pricePerUnit <= 0) {
            Session::setFlash('error', 'Harga per unit layanan tidak valid. Periksa data layanan di toko.');
            $this->redirect($redirectTarget);
            return;
        }

        $orderModel->updateWeightAndTotal($orderId, $weightKg, $pricePerUnit);

        $orderModel->updateOrderStatusWithNotification(
            $orderId,
            'weighed',
            'Laundry Selesai Ditimbang',
            "Berat laundry Anda adalah {$weightKg} kg. Invoice siap — silakan lakukan pembayaran.",
            $user['id']
        );

        Session::setFlash('success', "Penimbangan berhasil! Berat: {$weightKg} kg. Invoice telah dibuat dan customer telah diberitahu.");
        $this->redirect($redirectTarget);
    }

    /**
     * POST /owner/orders/{id}/status
     * Owner updates order status (e.g. in_process -> ready_for_delivery, delivered -> completed).
     */
    public function updateStatus(string $id): void
    {
        $token = $_POST['_token'] ?? '';
        if (!Session::verifyCsrfToken($token)) {
            Session::setFlash('error', 'Token CSRF tidak valid.');
            $this->redirect("/owner/orders/{$id}");
            return;
        }

        $user    = Session::get('user');
        $orderId = (int) $id;

        $store = $this->getOwnerStore($user['id']);
        if (!$store) {
            Session::setFlash('error', 'Akun Anda belum terhubung dengan toko laundry.');
            $this->redirect('/owner/orders');
            return;
        }

        $orderModel = new Order();
        $order = $orderModel->getOrderDetail($orderId);

        if (!$order || (int)$order['store_id'] !== (int)$store['id']) {
            Session::setFlash('error', 'Akses ditolak! Order tidak ditemukan atau bukan milik toko Anda.');
            $this->redirect('/owner/orders');
            return;
        }

        $redirectTarget = (isset($_POST['redirect_to']) && $_POST['redirect_to'] === 'dashboard')
            || (isset($_SERVER['HTTP_REFERER']) && str_contains($_SERVER['HTTP_REFERER'], '/owner/dashboard'))
            ? '/owner/dashboard'
            : "/owner/orders/{$orderId}";

        $newStatus = trim($_POST['new_status'] ?? '');
        $statusConfig = [
            'ready_for_delivery' => [
                'title' => 'Laundry Selesai Dicuci',
                'notes' => 'Pakaian Anda telah selesai dicuci dan siap untuk diantarkan.'
            ],
            'completed' => [
                'title' => 'Pesanan Selesai',
                'notes' => 'Pesanan laundry telah selesai. Terima kasih telah mempercayakan laundry Anda kepada kami!'
            ]
        ];

        if (!array_key_exists($newStatus, $statusConfig)) {
            Session::setFlash('error', 'Status pembaruan tidak valid untuk pemilik toko.');
            $this->redirect($redirectTarget);
            return;
        }

        $cfg = $statusConfig[$newStatus];
        $notes = trim($_POST['status_notes'] ?? '') ?: $cfg['notes'];

        // Validasi COD: Pesanan COD tidak boleh jadi Selesai tanpa konfirmasi uang tunai diterima
        if ($newStatus === 'completed') {
            $paymentModel = new \App\Models\Payment();
            $payment = $paymentModel->findByOrderId($orderId);

            if ($payment && $payment['payment_method'] === 'cash' && $payment['payment_status'] !== 'paid') {
                $confirmCash = !empty($_POST['confirm_cash']) || !empty($_POST['cash_received']);
                if ($confirmCash) {
                    $paymentModel->markAsPaid(
                        $payment['id'],
                        'CASH-' . $orderId . '-' . date('YmdHis'),
                        date('Y-m-d H:i:s'),
                        json_encode([
                            'confirmed_by' => $user['id'],
                            'role'         => 'owner',
                            'method'       => 'cash',
                            'confirmed_at' => date('Y-m-d H:i:s')
                        ]),
                        (int) $user['id']
                    );
                } else {
                    Session::setFlash('error', 'Pesanan COD tidak dapat diselesaikan tanpa konfirmasi bahwa uang tunai telah diterima oleh toko.');
                    $this->redirect($redirectTarget);
                    return;
                }
            }
        }

        $orderModel->updateOrderStatusWithNotification(
            $orderId,
            $newStatus,
            $cfg['title'],
            $notes,
            $user['id']
        );

        Session::setFlash('success', "Status berhasil diperbarui menjadi [{$cfg['title']}].");
        $this->redirect($redirectTarget);
    }

    /**
     * Buka / Tutup Toko Laundry oleh Mitra (Owner).
     * POST /owner/store/toggle-status
     */
    public function toggleStoreStatus(): void
    {
        $token = $_POST['_token'] ?? '';
        if (!Session::verifyCsrfToken($token)) {
            Session::setFlash('error', 'Token keamanan tidak valid.');
            $this->redirectBack('/owner/dashboard');
            return;
        }

        $user = Session::get('user');
        if (!$user) {
            $this->redirect('/login');
            return;
        }

        $store = $this->getOwnerStore((int) $user['id']);
        if (!$store) {
            Session::setFlash('error', 'Akun Anda belum terhubung dengan toko laundry.');
            $this->redirect('/owner/dashboard');
            return;
        }

        $storeModel = new Store();
        $currentIsOpen = (int) ($store['is_open'] ?? 1);

        // Jika dikirim status baru eksplisit (0 atau 1), gunakan itu; jika tidak, toggle
        if (isset($_POST['is_open']) && ($_POST['is_open'] === '0' || $_POST['is_open'] === '1')) {
            $newIsOpen = (int) $_POST['is_open'];
        } else {
            $newIsOpen = ($currentIsOpen === 1) ? 0 : 1;
        }

        $success = $storeModel->updateOpenStatus((int) $store['id'], $newIsOpen);

        if ($success) {
            if ($newIsOpen === 1) {
                Session::setFlash('success', 'Toko laundry berhasil DIBUKA! Pelanggan sekarang dapat membuat pesanan baru.');
            } else {
                Session::setFlash('warning', 'Toko laundry berhasil DITUTUP sementara. Pelanggan tidak dapat membuat pesanan baru hingga Anda membukanya kembali.');
            }
        } else {
            Session::setFlash('error', 'Gagal memperbarui status buka/tutup toko.');
        }

        $this->redirectBack('/owner/dashboard');
    }

    private function redirectBack(string $defaultUrl): void
    {
        $referer = $_SERVER['HTTP_REFERER'] ?? '';
        if (!empty($referer) && filter_var($referer, FILTER_VALIDATE_URL)) {
            $path = parse_url($referer, PHP_URL_PATH);
            if (str_starts_with($path, '/owner')) {
                $this->redirect($referer);
                return;
            }
        }
        $this->redirect($defaultUrl);
    }
}
