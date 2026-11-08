<?php

namespace App\Controllers;

use App\Helpers\Session;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Store;
use App\Services\PaymentService;
use Exception;

class PaymentController extends BaseController
{
    /**
     * GET /payment/{orderId}
     * Show payment method selection page.
     * Only accessible if order is in 'weighed' or 'waiting_payment' status.
     */
    public function method(string $orderId): void
    {
        $user    = Session::get('user');
        $orderId = (int) $orderId;

        $orderModel = new Order();
        $order = $orderModel->getOrderDetail($orderId, $user['id']);

        if (!$order) {
            Session::setFlash('error', 'Pesanan tidak ditemukan.');
            $this->redirect('/customer/orders');
            return;
        }

        if (!in_array($order['order_status'], ['weighed', 'waiting_payment'])) {
            Session::setFlash('error', 'Pembayaran belum tersedia. Laundry Anda sedang dalam proses penjemputan / penimbangan.');
            $this->redirect("/customer/orders/{$orderId}");
            return;
        }

        // Check for existing payment record
        $paymentModel = new Payment();
        $payment = $paymentModel->findByOrderId($orderId);

        // If already paid — redirect to success
        if ($payment && $payment['payment_status'] === 'paid') {
            $this->redirect("/payment/{$orderId}/success");
            return;
        }

        $this->render('payment/method', [
            'title'   => 'Pilih Metode Pembayaran - Marketplace Laundry',
            'user'    => $user,
            'order'   => $order,
            'payment' => $payment
        ]);
    }

    /**
     * POST /payment/{orderId}/qris
     * Initiate QRIS payment via Midtrans.
     */
    public function createQris(string $orderId): void
    {
        $token = $_POST['_token'] ?? '';
        if (!Session::verifyCsrfToken($token)) {
            Session::setFlash('error', 'Token CSRF tidak valid.');
            $this->redirect("/payment/{$orderId}");
            return;
        }

        $user    = Session::get('user');
        $orderId = (int) $orderId;

        $orderModel = new Order();
        $order = $orderModel->getOrderDetail($orderId, $user['id']);

        if (!$order) {
            Session::setFlash('error', 'Akses ditolak atau pesanan tidak ditemukan.');
            $this->redirect('/customer/orders');
            return;
        }

        try {
            $paymentService = new PaymentService();
            $payment = $paymentService->createQrisPayment($orderId, [
                'name'  => $user['name'],
                'email' => $user['email'],
                'phone' => $user['phone'] ?? ''
            ]);

            $this->redirect("/payment/{$orderId}/qris");
        } catch (Exception $e) {
            Session::setFlash('error', 'Gagal membuat transaksi QRIS: ' . $e->getMessage());
            $this->redirect("/payment/{$orderId}");
        }
    }

    /**
     * GET /payment/{orderId}/qris
     * Show QRIS code page with 60 minute countdown.
     */
    public function showQris(string $orderId): void
    {
        $user    = Session::get('user');
        $orderId = (int) $orderId;

        $orderModel = new Order();
        $order = $orderModel->getOrderDetail($orderId, $user['id']);

        if (!$order) {
            $this->redirect('/customer/orders');
            return;
        }

        $paymentModel = new Payment();
        $payment = $paymentModel->findByOrderId($orderId);

        if (!$payment || $payment['payment_method'] !== 'qris') {
            Session::setFlash('error', 'Data pembayaran QRIS tidak ditemukan.');
            $this->redirect("/payment/{$orderId}");
            return;
        }

        // Auto-expire check
        if ($payment['payment_status'] === 'pending' && strtotime($payment['expired_at']) < time()) {
            $paymentService = new PaymentService();
            $paymentService->expirePaymentAndCancelOrder((int)$payment['id'], $orderId);
            $this->redirect("/payment/{$orderId}/expired");
            return;
        }

        if ($payment['payment_status'] === 'paid') {
            $this->redirect("/payment/{$orderId}/success");
            return;
        }

        if ($payment['payment_status'] === 'expired') {
            $this->redirect("/payment/{$orderId}/expired");
            return;
        }

        $this->render('payment/qris', [
            'title'   => 'Scan QRIS - Marketplace Laundry',
            'user'    => $user,
            'order'   => $order,
            'payment' => $payment,
            'expiredAt' => strtotime($payment['expired_at'])
        ]);
    }

    /**
     * POST /payment/{orderId}/cash
     * Customer selects Cash — create cash payment record.
     */
    public function createCash(string $orderId): void
    {
        $token = $_POST['_token'] ?? '';
        if (!Session::verifyCsrfToken($token)) {
            Session::setFlash('error', 'Token CSRF tidak valid.');
            $this->redirect("/payment/{$orderId}");
            return;
        }

        $user    = Session::get('user');
        $orderId = (int) $orderId;

        $orderModel = new Order();
        $order = $orderModel->getOrderDetail($orderId, $user['id']);

        if (!$order) {
            Session::setFlash('error', 'Akses ditolak atau pesanan tidak ditemukan.');
            $this->redirect('/customer/orders');
            return;
        }

        try {
            $paymentService = new PaymentService();
            $paymentService->createCashPayment($orderId, $user['id']);

            $formattedTotal = number_format((float) ($order['total_amount'] ?? 0), 0, ',', '.');
            Session::setFlash('success', "Pesanan berhasil dibuat. Siapkan uang pas Rp {$formattedTotal} saat kurir tiba.");
            $this->redirect("/customer/orders/{$orderId}");
        } catch (Exception $e) {
            Session::setFlash('error', 'Gagal memilih metode CASH: ' . $e->getMessage());
            $this->redirect("/payment/{$orderId}");
        }
    }

    /**
     * GET /payment/{orderId}/status
     * Payment waiting / status polling page.
     */
    public function status(string $orderId): void
    {
        $user    = Session::get('user');
        $orderId = (int) $orderId;

        $orderModel   = new Order();
        $order        = $orderModel->getOrderDetail($orderId, $user['id']);
        $paymentModel = new Payment();
        $payment      = $paymentModel->findByOrderId($orderId);

        if (!$order || !$payment) {
            Session::setFlash('error', 'Pesanan atau pembayaran tidak ditemukan.');
            $this->redirect('/customer/orders');
            return;
        }

        // Expire stale pending payments automatically
        if ($payment['payment_status'] === 'pending' && strtotime($payment['expired_at']) < time()) {
            $paymentService = new PaymentService();
            $paymentService->expirePaymentAndCancelOrder((int)$payment['id'], $orderId);
            $this->redirect("/payment/{$orderId}/expired");
            return;
        }

        if ($payment['payment_status'] === 'paid') {
            $this->redirect("/payment/{$orderId}/success");
            return;
        }

        if ($payment['payment_status'] === 'expired') {
            $this->redirect("/payment/{$orderId}/expired");
            return;
        }

        $this->render('payment/waiting', [
            'title'   => 'Menunggu Pembayaran - Marketplace Laundry',
            'user'    => $user,
            'order'   => $order,
            'payment' => $payment
        ]);
    }

    /**
     * GET /payment/{orderId}/success
     * Payment success confirmation page.
     */
    public function success(string $orderId): void
    {
        $user    = Session::get('user');
        $orderId = (int) $orderId;

        $orderModel   = new Order();
        $order        = $orderModel->getOrderDetail($orderId, $user['id']);
        $paymentModel = new Payment();
        $payment      = $paymentModel->findByOrderId($orderId);

        if (!$order || !$payment) {
            $this->redirect('/customer/orders');
            return;
        }

        $this->render('payment/success', [
            'title'   => 'Pembayaran Berhasil - Marketplace Laundry',
            'user'    => $user,
            'order'   => $order,
            'payment' => $payment
        ]);
    }

    /**
     * GET /payment/{orderId}/expired
     * Payment expired page.
     */
    public function expired(string $orderId): void
    {
        $user    = Session::get('user');
        $orderId = (int) $orderId;

        $orderModel   = new Order();
        $order        = $orderModel->getOrderDetail($orderId, $user['id']);
        $paymentModel = new Payment();
        $payment      = $paymentModel->findByOrderId($orderId);

        $this->render('payment/expired', [
            'title'   => 'Pembayaran Kadaluarsa - Marketplace Laundry',
            'user'    => $user,
            'order'   => $order,
            'payment' => $payment
        ]);
    }

    /**
     * POST /payment/cash/{orderId}/confirm
     * Owner or Driver confirms Cash payment.
     */
    public function confirmCash(string $orderId): void
    {
        $token = $_POST['_token'] ?? '';
        $user = Session::get('user');
        $userRole = $user['role'] ?? '';
        $redirectUrl = ($userRole === 'driver') ? "/driver/orders/{$orderId}" : "/owner/orders/{$orderId}";

        if (!Session::verifyCsrfToken($token)) {
            http_response_code(403);
            Session::setFlash('error', 'Token CSRF tidak valid.');
            $this->redirect($redirectUrl);
            return;
        }

        if (!$user || !in_array($userRole, ['owner', 'driver'], true)) {
            http_response_code(403);
            Session::setFlash('error', 'Akses ditolak: Hanya pemilik toko atau kurir yang dapat mengonfirmasi uang tunai.');
            $this->redirect("/login");
            return;
        }

        $orderId = (int) $orderId;
        $orderModel = new Order();
        $order = $orderModel->getOrderDetail($orderId);

        if (!$order) {
            Session::setFlash('error', 'Pesanan tidak ditemukan.');
            $this->redirect($redirectUrl);
            return;
        }

        // Authorization check per role:
        if ($userRole === 'owner') {
            $storeModel = new Store();
            $store = $storeModel->findByOwnerId((int)$user['id']);
            if (!$store || (int)$order['store_id'] !== (int)$store['id']) {
                http_response_code(403);
                Session::setFlash('error', 'Akses ditolak: Anda hanya dapat mengonfirmasi pembayaran order milik toko Anda sendiri.');
                $this->redirect($redirectUrl);
                return;
            }
        } elseif ($userRole === 'driver') {
            $driverModel = new \App\Models\Driver();
            $driver = $driverModel->findByUserId((int)$user['id']);
            $driverId = (int)($driver['id'] ?? 0);
            $isAssigned = ((int)($order['pickup_driver_id'] ?? 0) === $driverId) || ((int)($order['delivery_driver_id'] ?? 0) === $driverId);
            if (!$driver || !$isAssigned) {
                http_response_code(403);
                Session::setFlash('error', 'Akses ditolak: Anda hanya dapat mengonfirmasi pembayaran untuk pesanan yang ditugaskan kepada Anda.');
                $this->redirect($redirectUrl);
                return;
            }
        }

        try {
            $paymentService = new PaymentService();
            $paymentService->confirmCashPayment($orderId, $user['id']);

            Session::setFlash('success', 'Pembayaran CASH berhasil dikonfirmasi sebagai Lunas.');
            $this->redirect($redirectUrl);
        } catch (Exception $e) {
            Session::setFlash('error', 'Gagal konfirmasi pembayaran: ' . $e->getMessage());
            $this->redirect($redirectUrl);
        }
    }

    /**
     * POST /payment/webhook/midtrans
     * Public endpoint — receives webhook from Midtrans.
     * NO session auth. Validates via SHA512 signature.
     */
    public function webhook(): void
    {
        // Only accept POST requests
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['status' => 'error', 'message' => 'Method Not Allowed']);
            return;
        }

        $rawBody = file_get_contents('php://input');
        $payload = json_decode($rawBody, true);

        if (empty($payload) || json_last_error() !== JSON_ERROR_NONE) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Invalid JSON payload']);
            return;
        }

        try {
            $paymentService = new PaymentService();
            $result = $paymentService->handleWebhookCallback($payload);

            http_response_code(200);
            echo json_encode(['status' => 'ok', 'result' => $result]);
        } catch (Exception $e) {
            // Log error but return 200 to prevent Midtrans from resending
            error_log('[Midtrans Webhook Error] ' . $e->getMessage());
            http_response_code(200);
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
    }

    /**
     * GET /payment/{orderId}/poll  (AJAX endpoint for status polling)
     * Returns JSON with current payment status.
     */
    public function poll(string $orderId): void
    {
        $user = Session::get('user');
        if (!$user) {
            http_response_code(401);
            echo json_encode(['status' => 'unauthorized']);
            return;
        }
        $orderId = (int) $orderId;

        $orderModel   = new Order();
        $order        = $orderModel->getOrderDetail($orderId, $user['id']);
        $paymentModel = new Payment();
        $payment      = $paymentModel->findByOrderId($orderId);

        if (!$order || !$payment) {
            http_response_code(404);
            echo json_encode(['status' => 'not_found']);
            return;
        }

        // Auto-expire check
        if ($payment['payment_status'] === 'pending' && strtotime($payment['expired_at']) < time()) {
            $paymentService = new PaymentService();
            $paymentService->expirePaymentAndCancelOrder((int)$payment['id'], $orderId);
            $payment['payment_status'] = 'expired';
            $order['order_status'] = 'cancelled';
        }

        header('Content-Type: application/json');
        echo json_encode([
            'payment_status' => $payment['payment_status'],
            'order_status'   => $order['order_status'],
            'expired_at'     => $payment['expired_at'],
            'paid_at'        => $payment['paid_at'] ?? null
        ]);
    }
}
