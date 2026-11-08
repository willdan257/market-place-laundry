<?php

namespace App\Controllers;

use App\Helpers\Session;
use App\Models\Driver;
use App\Models\Order;

class DriverController extends BaseController
{
    private function getDriverProfile(int $userId): ?array
    {
        $driverModel = new Driver();
        return $driverModel->findByUserId($userId);
    }

    /**
     * GET /driver/orders — hanya tugas AKTIF
     */
    public function orders(): void
    {
        $user   = Session::get('user');
        $driver = $this->getDriverProfile($user['id']);

        if (!$driver) {
            Session::setFlash('error', 'Profil kurir tidak ditemukan.');
            $this->redirect('/dashboard');
            return;
        }

        $orderModel = new Order();
        $orders     = $orderModel->getActiveDriverTasks((int)$driver['id']);

        $this->render('driver/orders', [
            'title'  => 'Tugas Aktif - Dashboard Kurir',
            'user'   => $user,
            'driver' => $driver,
            'orders' => $orders,
        ]);
    }

    /**
     * GET /driver/history — riwayat tugas selesai (paginasi 50)
     */
    public function history(): void
    {
        $user   = Session::get('user');
        $driver = $this->getDriverProfile($user['id']);

        if (!$driver) {
            Session::setFlash('error', 'Profil kurir tidak ditemukan.');
            $this->redirect('/dashboard');
            return;
        }

        $page   = max(1, (int)($_GET['page'] ?? 1));
        $limit  = 50;
        $offset = ($page - 1) * $limit;

        $orderModel = new Order();
        $history    = $orderModel->getDriverHistory((int)$driver['id'], $limit, $offset);
        $totalRows  = $orderModel->getDriverHistoryCount((int)$driver['id']);
        $totalPages = (int) ceil($totalRows / $limit);

        $this->render('driver/history', [
            'title'      => 'Riwayat Tugas - Dashboard Kurir',
            'user'       => $user,
            'driver'     => $driver,
            'history'    => $history,
            'page'       => $page,
            'totalPages' => $totalPages,
            'totalRows'  => $totalRows,
        ]);
    }

    /**
     * GET /driver/orders/{id} — detail order (hanya baca jika sudah selesai)
     */
    public function show(string $id): void
    {
        $user   = Session::get('user');
        $driver = $this->getDriverProfile($user['id']);

        if (!$driver) {
            Session::setFlash('error', 'Profil kurir tidak ditemukan.');
            $this->redirect('/dashboard');
            return;
        }

        $orderId    = (int) $id;
        $orderModel = new Order();
        $order      = $orderModel->getOrderDetail($orderId);

        // Strict Authorization: Driver can ONLY view orders assigned to them!
        if (!$order || ($order['pickup_driver_id'] != $driver['id'] && $order['delivery_driver_id'] != $driver['id'])) {
            Session::setFlash('error', 'Akses ditolak! Anda hanya dapat membuka order yang ditugaskan kepada Anda.');
            $this->redirect('/driver/orders');
            return;
        }

        $this->render('driver/show', [
            'title'  => "Detail Tugas Order #{$order['order_number']}",
            'user'   => $user,
            'driver' => $driver,
            'order'  => $order,
        ]);
    }

    /**
     * POST /driver/orders/{id}/status — update status; tolak jika tahap sudah selesai
     */
    public function updateStatus(string $id): void
    {
        $token = $_POST['_token'] ?? '';
        if (!Session::verifyCsrfToken($token)) {
            Session::setFlash('error', 'Token CSRF tidak valid.');
            $this->redirect("/driver/orders/{$id}");
            return;
        }

        $user   = Session::get('user');
        $driver = $this->getDriverProfile($user['id']);

        if (!$driver) {
            Session::setFlash('error', 'Profil kurir tidak ditemukan.');
            $this->redirect('/dashboard');
            return;
        }

        $orderId    = (int) $id;
        $orderModel = new Order();
        $order      = $orderModel->getOrderDetail($orderId);

        // Strict Authorization: Driver can ONLY update orders assigned to them!
        if (!$order || ($order['pickup_driver_id'] != $driver['id'] && $order['delivery_driver_id'] != $driver['id'])) {
            Session::setFlash('error', 'Akses ditolak! Anda hanya dapat mengubah order yang ditugaskan kepada Anda.');
            $this->redirect('/driver/orders');
            return;
        }

        // Guard: tolak jika tahap yang dikerjakan kurir ini sudah selesai
        $currentStatus = $order['order_status'];
        $isPickupDriver   = (int)$order['pickup_driver_id']   === (int)$driver['id'];
        $isDeliveryDriver = (int)$order['delivery_driver_id'] === (int)$driver['id'];

        $pickupDone   = in_array($currentStatus, Order::PICKUP_DONE_STATUSES, true);
        // Pengantaran baru selesai jika sudah completed / cancelled
        $deliveryDone = in_array($currentStatus, ['completed', 'cancelled'], true);

        // Jika kurir hanya penjemputan DAN tahap penjemputan sudah selesai
        if ($isPickupDriver && !$isDeliveryDriver && $pickupDone) {
            Session::setFlash('error', 'Tahap penjemputan Anda untuk order ini sudah selesai dan tidak dapat diubah lagi.');
            $this->redirect("/driver/orders/{$orderId}");
            return;
        }
        // Jika kurir hanya pengantaran DAN tahap pengantaran sudah selesai
        if ($isDeliveryDriver && !$isPickupDriver && $deliveryDone) {
            Session::setFlash('error', 'Tahap pengantaran Anda untuk order ini sudah selesai dan tidak dapat diubah lagi.');
            $this->redirect("/driver/orders/{$orderId}");
            return;
        }
        // Jika kurir menangani kedua tahap DAN keduanya sudah selesai
        if ($isPickupDriver && $isDeliveryDriver && $deliveryDone) {
            Session::setFlash('error', 'Semua tahap untuk order ini sudah selesai dan tidak dapat diubah lagi.');
            $this->redirect("/driver/orders/{$orderId}");
            return;
        }

        $newStatus  = trim($_POST['new_status'] ?? '');
        $statusNotes = trim($_POST['status_notes'] ?? '');

        // Driver Status Mapping & Titles
        $statusConfig = [
            'driver_on_the_way' => [
                'title'         => 'Kurir Dalam Perjalanan',
                'default_notes' => 'Kurir sedang dalam perjalanan menuju lokasi penjemputan Anda.',
            ],
            'arrived_at_customer' => [
                'title'         => 'Kurir Tiba di Lokasi',
                'default_notes' => 'Kurir telah sampai di depan rumah/lokasi penjemputan Anda.',
            ],
            'picked_up' => [
                'title'         => 'Pakaian Berhasil Dijemput',
                'default_notes' => 'Pakaian kotor telah diterima oleh kurir dan sedang dibawa menuju toko laundry.',
            ],
            'arrived_at_store' => [
                'title'         => 'Pakaian Tiba di Toko Laundry',
                'default_notes' => 'Pakaian telah sampai di toko laundry dan siap untuk proses penimbagan.',
            ],
            'delivered' => [
                'title'         => 'Laundry Telah Diantar',
                'default_notes' => 'Pakaian bersih telah berhasil diantarkan ke alamat pelanggan.',
            ],
            'completed' => [
                'title'         => 'Pesanan Selesai',
                'default_notes' => 'Pesanan laundry telah selesai secara keseluruhan. Terima kasih!',
            ],
        ];

        if (!array_key_exists($newStatus, $statusConfig)) {
            Session::setFlash('error', 'Pilihan status perjalanan tidak valid.');
            $this->redirect("/driver/orders/{$orderId}");
            return;
        }

        $config     = $statusConfig[$newStatus];
        $finalNotes = !empty($statusNotes) ? $statusNotes : $config['default_notes'];

        // Validasi COD: Pesanan COD tidak boleh jadi Selesai tanpa konfirmasi uang tunai diterima
        if ($newStatus === 'completed') {
            $paymentModel = new \App\Models\Payment();
            $payment = $paymentModel->findByOrderId($orderId);

            if ($payment && $payment['payment_method'] === 'cash' && $payment['payment_status'] !== 'paid') {
                $confirmCash = !empty($_POST['confirm_cash']) || !empty($_POST['cash_received']);
                if ($confirmCash) {
                    // Konfirmasi otomatis pembayaran menjadi Lunas (catat siapa dan kapan)
                    $paymentModel->markAsPaid(
                        $payment['id'],
                        'CASH-' . $orderId . '-' . date('YmdHis'),
                        date('Y-m-d H:i:s'),
                        json_encode([
                            'confirmed_by' => $user['id'],
                            'role'         => 'driver',
                            'method'       => 'cash',
                            'confirmed_at' => date('Y-m-d H:i:s')
                        ]),
                        (int) $user['id']
                    );
                } else {
                    Session::setFlash('error', 'Pesanan COD tidak dapat diselesaikan tanpa konfirmasi bahwa uang tunai telah diterima dari pelanggan.');
                    $this->redirect("/driver/orders/{$orderId}");
                    return;
                }
            }
        }

        // Execute status update with audit history logging & customer notification
        $success = $orderModel->updateOrderStatusWithNotification(
            $orderId,
            $newStatus,
            $config['title'],
            $finalNotes,
            $user['id']
        );

        if ($success) {
            Session::setFlash('success', "Status berhasil diperbarui menjadi [{$config['title']}]. Notifikasi telah dikirim ke pelanggan.");
        } else {
            Session::setFlash('error', 'Gagal memperbarui status order.');
        }

        $this->redirect("/driver/orders/{$orderId}");
    }
}
