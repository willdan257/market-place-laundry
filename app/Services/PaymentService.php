<?php

namespace App\Services;

use App\Models\Payment;
use App\Models\Order;
use App\Services\MidtransHttpClient;
use App\Services\NotificationService;
use Exception;

/**
 * PaymentService — Business logic for QRIS and Cash payments.
 * All gateway calls are delegated to MidtransHttpClient.
 * This class is responsible for:
 * - Creating QRIS transactions via Midtrans
 * - Handling webhook callbacks with signature validation and idempotency
 * - Verifying Cash payments (by Owner)
 * - Expiring overdue pending payments
 */
class PaymentService
{
    private MidtransHttpClient $midtrans;
    private Payment $paymentModel;
    private Order $orderModel;
    private NotificationService $notifService;

    public function __construct()
    {
        $this->midtrans      = new MidtransHttpClient();
        $this->paymentModel  = new Payment();
        $this->orderModel    = new Order();
        $this->notifService  = new NotificationService();
    }

    /**
     * Initiate a QRIS payment for an order.
     * - Validates order is in correct state (weighed)
     * - Checks for existing pending payment (idempotency)
     * - Calls Midtrans QRIS charge API
     * - Persists payment record with QRIS URL and expiry
     *
     * Returns the payment record array.
     * @throws Exception if order invalid, already paid, or Midtrans API fails
     */
    public function createQrisPayment(int $orderId, array $customerDetails): array
    {
        $order = $this->orderModel->getOrderDetail($orderId);
        if (!$order) {
            throw new Exception('Pesanan tidak ditemukan.');
        }

        if (!in_array($order['order_status'], ['weighed', 'waiting_payment'])) {
            throw new Exception(
                'Pembayaran hanya dapat dibuat setelah laundry selesai ditimbang. ' .
                'Status saat ini: ' . $order['order_status']
            );
        }

        if ($order['total_amount'] <= 0) {
            throw new Exception('Total tagihan belum dapat ditentukan. Owner belum menginput berat laundry.');
        }

        // Check for existing ACTIVE payment (idempotency guard)
        $existing = $this->paymentModel->findByOrderId($orderId);
        if ($existing) {
            if ($existing['payment_status'] === 'paid') {
                throw new Exception('Pesanan ini sudah dibayar.');
            }
            if ($existing['payment_status'] === 'pending' && $existing['payment_method'] === 'qris') {
                // Check if still within expiry — return existing if valid
                if (strtotime($existing['expired_at']) > time()) {
                    return $existing;
                }
                // Expired — will create new one below
                $this->paymentModel->markAsExpired($existing['id']);
            }
        }

        // Build idempotency key: orderId + timestamp (minute precision)
        $idempotencyKey = 'PAY-' . $orderId . '-' . date('YmdHi');

        // Check if same idempotency key already exists (double-click guard)
        $byKey = $this->paymentModel->findByIdempotencyKey($idempotencyKey);
        if ($byKey && $byKey['payment_status'] === 'pending') {
            return $byKey;
        }

        // Midtrans order_id must be unique — append timestamp to allow retries
        $midtransOrderId = $order['order_number'] . '-' . time();

        // Call Midtrans API
        $midtransResponse = $this->midtrans->createQrisCharge(
            $midtransOrderId,
            (float) $order['total_amount'],
            $customerDetails
        );

        // Parse QRIS data from response
        $qrisUrl    = $midtransResponse['actions'][0]['url'] ?? null;
        $qrisString = null;
        foreach (($midtransResponse['actions'] ?? []) as $action) {
            if (($action['name'] ?? '') === 'generate-qr-code') {
                $qrisUrl = $action['url'];
            }
        }

        $gatewayTxId = $midtransResponse['transaction_id'] ?? null;
        $expiredAt   = date('Y-m-d H:i:s', time() + 3600); // 1 hour

        // Persist or update payment record
        $paymentData = [
            'order_id'               => $orderId,
            'amount'                 => $order['total_amount'],
            'gateway_transaction_id' => $gatewayTxId,
            'transaction_reference'  => $midtransOrderId,
            'idempotency_key'        => $idempotencyKey,
            'payment_type'           => 'qris',
            'qris_url'               => $qrisUrl,
            'expired_at'             => $expiredAt,
            'gateway_response'       => json_encode($midtransResponse)
        ];

        if ($existing) {
            $this->paymentModel->updateToQris((int) $existing['id'], $paymentData);
            $paymentId = (int) $existing['id'];
        } else {
            $paymentId = $this->paymentModel->createQrisPayment($paymentData);
        }

        // Update order status to waiting_payment
        $this->orderModel->updateOrderStatusWithNotification(
            $orderId,
            'waiting_payment',
            'Menunggu Pembayaran',
            'Transaksi QRIS berhasil dibuat. Silakan scan QR dalam 60 menit.',
            $order['customer_id']
        );

        return $this->paymentModel->findById($paymentId);
    }

    /**
     * Create a CASH payment record (no gateway call needed).
     * Status starts as PENDING — must be confirmed by Owner.
     * @throws Exception if order invalid or already paid
     */
    public function createCashPayment(int $orderId, int $customerId): array
    {
        $order = $this->orderModel->getOrderDetail($orderId);
        if (!$order) {
            throw new Exception('Pesanan tidak ditemukan.');
        }

        if (!in_array($order['order_status'], ['weighed', 'waiting_payment'])) {
            throw new Exception('Pembayaran hanya dapat dibuat setelah laundry selesai ditimbang.');
        }

        $existing = $this->paymentModel->findByOrderId($orderId);
        if ($existing && $existing['payment_status'] === 'paid') {
            throw new Exception('Pesanan ini sudah dibayar.');
        }

        $idempotencyKey = 'CASH-' . $orderId . '-' . date('Ymd');
        $expiredAt = date('Y-m-d H:i:s', strtotime('+7 days')); // Cash: 7 day window

        if ($existing) {
            // Update existing record to cash
            $this->paymentModel->updateToCash($existing['id'], $idempotencyKey, $expiredAt);
            $payment = $this->paymentModel->findByOrderId($orderId);
        } else {
            $paymentId = $this->paymentModel->createCashPayment([
                'order_id'       => $orderId,
                'amount'         => $order['total_amount'],
                'idempotency_key' => $idempotencyKey,
                'expired_at'     => $expiredAt
            ]);
            $payment = $this->paymentModel->findById($paymentId);
        }

        // Update order status
        $this->orderModel->updateOrderStatusWithNotification(
            $orderId,
            'waiting_payment',
            'Menunggu Pembayaran Tunai',
            'Pembayaran CASH dipilih. Silakan bayar saat kurir tiba atau di toko.',
            $customerId
        );

        return $payment;
    }

    /**
     * Handle Midtrans webhook callback.
     * SECURITY: validates SHA512 signature before processing.
     * IDEMPOTENCY: skip if payment already in final state.
     *
     * @throws Exception if signature invalid
     */
    public function handleWebhookCallback(array $payload): array
    {
        // 1. Validate signature
        $orderId      = $payload['order_id'] ?? '';
        $statusCode   = $payload['status_code'] ?? '';
        $grossAmount  = $payload['gross_amount'] ?? '';
        $signature    = $payload['signature_key'] ?? '';

        if (!$this->midtrans->validateWebhookSignature($orderId, $statusCode, $grossAmount, $signature)) {
            throw new Exception('Webhook signature tidak valid. Request ditolak.');
        }

        // 2. Find payment by gateway transaction reference (order_id from Midtrans)
        $payment = $this->paymentModel->findByTransactionReference($orderId);
        if (!$payment) {
            // Also try finding by order number prefix (ORD-XXXXXX)
            $parts   = explode('-', $orderId);
            $orderNumber = implode('-', array_slice($parts, 0, 3)); // ORD-YYYYMMDD-XXXX
            $order   = $this->orderModel->findByOrderNumber($orderNumber);
            if ($order) {
                $payment = $this->paymentModel->findByOrderId($order['id']);
            }
        }

        if (!$payment) {
            return ['status' => 'not_found', 'message' => 'Payment record not found for order: ' . $orderId];
        }

        // 3. Atomically check and lock payment record to prevent race conditions and duplicate webhook processing
        $pdo = \App\Models\BaseModel::getPdo();
        $pdo->beginTransaction();

        try {
            $stmtLock = $pdo->prepare("SELECT * FROM payments WHERE id = :id FOR UPDATE");
            $stmtLock->execute(['id' => $payment['id']]);
            $lockedPayment = $stmtLock->fetch();

            if (!$lockedPayment) {
                $pdo->rollBack();
                return ['status' => 'not_found', 'message' => 'Payment record not found during lock.'];
            }

            // Idempotency: skip if already in final state
            if (in_array($lockedPayment['payment_status'], ['paid', 'expired', 'cancelled', 'failed'])) {
                $pdo->rollBack();
                return ['status' => 'skipped', 'message' => 'Payment already in final state: ' . $lockedPayment['payment_status']];
            }

            // 3b. Verify gross_amount matches payment.amount (Protection against Price Tampering / Underpayment)
            $expectedAmount = (float) $lockedPayment['amount'];
            $receivedAmount = (float) $grossAmount;
            if (abs($expectedAmount - $receivedAmount) > 0.01) {
                $this->paymentModel->markAsFailed($lockedPayment['id'], json_encode([
                    'error' => 'amount_mismatch',
                    'expected' => $expectedAmount,
                    'received' => $receivedAmount
                ]));
                $pdo->commit();
                throw new Exception("Nominal pembayaran ({$receivedAmount}) tidak sesuai dengan tagihan pesanan ({$expectedAmount}).");
            }

            $transactionStatus = $payload['transaction_status'] ?? '';
            $fraudStatus       = $payload['fraud_status'] ?? 'accept';
            $paidAt            = $payload['settlement_time'] ?? $payload['transaction_time'] ?? date('Y-m-d H:i:s');
            $gatewayTxId       = $payload['transaction_id'] ?? '';
            $linkedOrderId     = (int) $lockedPayment['order_id'];

            // 4. Map Midtrans status to internal status
            if ($transactionStatus === 'settlement' || ($transactionStatus === 'capture' && $fraudStatus === 'accept')) {
                // PAYMENT SUCCESSFUL
                $this->paymentModel->markAsPaid($lockedPayment['id'], $gatewayTxId, $paidAt, json_encode($payload));

                $order = $this->orderModel->getOrderDetail($linkedOrderId);
                if ($order) {
                    $this->orderModel->updateOrderStatusWithNotification(
                        $linkedOrderId,
                        'in_process',
                        'Pembayaran Berhasil — Laundry Diproses',
                        'Pembayaran QRIS berhasil dikonfirmasi. Laundry Anda sedang diproses.',
                        $order['customer_id']
                    );
                }
                $pdo->commit();
                return ['status' => 'success', 'payment_status' => 'paid'];

            } elseif (in_array($transactionStatus, ['expire', 'expired'])) {
                $pdo->commit();
                $this->expirePaymentAndCancelOrder((int)$lockedPayment['id'], $linkedOrderId);
                return ['status' => 'success', 'payment_status' => 'expired'];

            } elseif (in_array($transactionStatus, ['cancel', 'deny', 'failure'])) {
                $this->paymentModel->markAsFailed($lockedPayment['id'], json_encode($payload));
                $pdo->commit();
                return ['status' => 'success', 'payment_status' => 'failed'];

            } elseif ($transactionStatus === 'pending') {
                $pdo->commit();
                return ['status' => 'pending', 'message' => 'Payment still pending.'];
            }

            $pdo->commit();
            return ['status' => 'unknown', 'transaction_status' => $transactionStatus];
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Owner confirms Cash payment for an order.
     * @throws Exception if order/payment not found or already paid
     */
    public function confirmCashPayment(int $orderId, int $confirmedByUserId): array
    {
        $pdo = \App\Models\BaseModel::getPdo();
        $pdo->beginTransaction();

        try {
            $stmt = $pdo->prepare("SELECT * FROM payments WHERE order_id = :order_id FOR UPDATE");
            $stmt->execute(['order_id' => $orderId]);
            $payment = $stmt->fetch();

            if (!$payment) {
                $pdo->rollBack();
                throw new Exception('Data pembayaran tidak ditemukan untuk pesanan ini.');
            }
            if ($payment['payment_method'] !== 'cash') {
                $pdo->rollBack();
                throw new Exception('Metode pembayaran pesanan ini bukan CASH.');
            }
            if ($payment['payment_status'] === 'paid') {
                $pdo->rollBack();
                throw new Exception('Pembayaran sudah dikonfirmasi sebelumnya.');
            }

            $this->paymentModel->markAsPaid(
                $payment['id'],
                'CASH-' . $orderId . '-' . date('YmdHis'),
                date('Y-m-d H:i:s'),
                json_encode(['confirmed_by' => $confirmedByUserId, 'method' => 'cash', 'confirmed_at' => date('Y-m-d H:i:s')]),
                $confirmedByUserId
            );

            $order = $this->orderModel->getOrderDetail($orderId);
            // Hanya update ke 'in_process' jika order masih di tahap awal menunggu konfirmasi pembayaran
            if ($order && in_array($order['order_status'], ['waiting_payment', 'pending_pickup'], true)) {
                $this->orderModel->updateOrderStatusWithNotification(
                    $orderId,
                    'in_process',
                    'Pembayaran Tunai Dikonfirmasi',
                    'Pembayaran tunai Anda telah dikonfirmasi oleh toko. Laundry sedang diproses.',
                    $order['customer_id']
                );
            }

            $pdo->commit();
            return $this->paymentModel->findById($payment['id']);
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Mark payment as expired, cancel the order following cancellation rules, and notify customer.
     */
    public function expirePaymentAndCancelOrder(int $paymentId, int $orderId): bool
    {
        $this->paymentModel->markAsExpired($paymentId);

        $order = $this->orderModel->getOrderDetail($orderId);
        if (!$order) {
            return false;
        }

        // Send payment expired notification
        $this->notifService->send(
            (int) $order['customer_id'],
            'Pembayaran Kadaluarsa',
            "Batas waktu pembayaran pesanan #{$order['order_number']} telah habis. Pesanan dibatalkan otomatis.",
            'payment_expired',
            $orderId
        );

        // Cancel order if not already in process/delivered/completed
        if (!in_array($order['order_status'], ['completed', 'cancelled'])) {
            $this->orderModel->updateOrderStatusWithNotification(
                $orderId,
                'cancelled',
                'Pesanan Dibatalkan',
                'Dibatalkan otomatis karena batas waktu pembayaran terlewati.',
                (int) $order['customer_id']
            );
        }

        return true;
    }

    /**
     * Expire overdue payments (called by cron or on-demand).
     * Returns count of expired payments.
     */
    public function expireOverduePayments(): int
    {
        $overdue = $this->paymentModel->getOverduePayments();
        $count = 0;

        foreach ($overdue as $payment) {
            $this->expirePaymentAndCancelOrder((int) $payment['id'], (int) $payment['order_id']);
            $count++;
        }

        return $count;
    }

    public function handleExpiredOrderNotification(int $orderId): void
    {
        $payment = $this->paymentModel->findByOrderId($orderId);
        $paymentId = $payment ? (int) $payment['id'] : 0;
        $this->expirePaymentAndCancelOrder($paymentId, $orderId);
    }
}
