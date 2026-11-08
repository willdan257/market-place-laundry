<?php

namespace App\Models;

class Payment extends BaseModel
{
    protected string $table = 'payments';

    public function findById(int $id): ?array
    {
        $results = $this->query("SELECT * FROM {$this->table} WHERE id = :id LIMIT 1", ['id' => $id]);
        return $results[0] ?? null;
    }

    public function findByOrderId(int $orderId): ?array
    {
        $results = $this->query(
            "SELECT * FROM {$this->table} WHERE order_id = :order_id ORDER BY id DESC LIMIT 1",
            ['order_id' => $orderId]
        );
        return $results[0] ?? null;
    }

    public function findByIdempotencyKey(string $key): ?array
    {
        $results = $this->query(
            "SELECT * FROM {$this->table} WHERE idempotency_key = :key LIMIT 1",
            ['key' => $key]
        );
        return $results[0] ?? null;
    }

    public function findByTransactionReference(string $reference): ?array
    {
        $results = $this->query(
            "SELECT * FROM {$this->table} WHERE transaction_reference = :ref LIMIT 1",
            ['ref' => $reference]
        );
        return $results[0] ?? null;
    }

    public function findByGatewayTransactionId(string $txId): ?array
    {
        $results = $this->query(
            "SELECT * FROM {$this->table} WHERE gateway_transaction_id = :tx LIMIT 1",
            ['tx' => $txId]
        );
        return $results[0] ?? null;
    }

    /**
     * Create a QRIS payment record (status = pending).
     * Returns the new payment ID.
     */
    public function createQrisPayment(array $data): int
    {
        $pdo = self::getPdo();
        $sql = "INSERT INTO {$this->table} (
                    order_id, payment_method, payment_status, payment_type,
                    amount, expired_at, transaction_reference, gateway_transaction_id,
                    idempotency_key, qris_url, gateway_response
                ) VALUES (
                    :order_id, 'qris', 'pending', :payment_type,
                    :amount, :expired_at, :tx_ref, :gateway_tx_id,
                    :idempotency_key, :qris_url, :gateway_response
                )";

        $this->execute($sql, [
            'order_id'         => $data['order_id'],
            'payment_type'     => $data['payment_type'] ?? 'qris',
            'amount'           => $data['amount'],
            'expired_at'       => $data['expired_at'],
            'tx_ref'           => $data['transaction_reference'],
            'gateway_tx_id'    => $data['gateway_transaction_id'],
            'idempotency_key'  => $data['idempotency_key'],
            'qris_url'         => $data['qris_url'] ?? null,
            'gateway_response' => $data['gateway_response'] ?? null
        ]);

        return (int) $pdo->lastInsertId();
    }

    /**
     * Update existing payment record to QRIS method.
     */
    public function updateToQris(int $paymentId, array $data): bool
    {
        return $this->execute(
            "UPDATE {$this->table} SET
                payment_method = 'qris',
                payment_type = :payment_type,
                payment_status = 'pending',
                amount = :amount,
                expired_at = :expired_at,
                transaction_reference = :tx_ref,
                gateway_transaction_id = :gateway_tx_id,
                idempotency_key = :idempotency_key,
                qris_url = :qris_url,
                gateway_response = :gateway_response,
                paid_at = NULL
             WHERE id = :id",
            [
                'payment_type'     => $data['payment_type'] ?? 'qris',
                'amount'           => $data['amount'],
                'expired_at'       => $data['expired_at'],
                'tx_ref'           => $data['transaction_reference'],
                'gateway_tx_id'    => $data['gateway_transaction_id'],
                'idempotency_key'  => $data['idempotency_key'],
                'qris_url'         => $data['qris_url'] ?? null,
                'gateway_response' => $data['gateway_response'] ?? null,
                'id'               => $paymentId
            ]
        );
    }

    /**
     * Create a CASH payment record (status = pending, confirmed by Owner later).
     * Returns the new payment ID.
     */
    public function createCashPayment(array $data): int
    {
        $pdo = self::getPdo();
        $sql = "INSERT INTO {$this->table} (
                    order_id, payment_method, payment_status, payment_type,
                    amount, expired_at, idempotency_key
                ) VALUES (
                    :order_id, 'cash', 'pending', 'cash',
                    :amount, :expired_at, :idempotency_key
                )";

        $this->execute($sql, [
            'order_id'        => $data['order_id'],
            'amount'          => $data['amount'],
            'expired_at'      => $data['expired_at'],
            'idempotency_key' => $data['idempotency_key']
        ]);

        return (int) $pdo->lastInsertId();
    }

    /**
     * Update existing payment record to CASH method.
     */
    public function updateToCash(int $paymentId, string $idempotencyKey, string $expiredAt): bool
    {
        return $this->execute(
            "UPDATE {$this->table} SET
                payment_method = 'cash', payment_type = 'cash', payment_status = 'pending',
                idempotency_key = :key, expired_at = :exp,
                gateway_transaction_id = NULL, qris_url = NULL, gateway_response = NULL
             WHERE id = :id",
            ['key' => $idempotencyKey, 'exp' => $expiredAt, 'id' => $paymentId]
        );
    }

    /**
     * Mark payment as PAID.
     */
    public function markAsPaid(int $paymentId, string $gatewayTxId, string $paidAt, ?string $gatewayResponse = null, ?int $confirmedBy = null): bool
    {
        return $this->execute(
            "UPDATE {$this->table} SET
                payment_status = 'paid',
                gateway_transaction_id = :tx_id,
                paid_at = :paid_at,
                confirmed_by = COALESCE(:confirmed_by, confirmed_by),
                gateway_response = COALESCE(:resp, gateway_response)
             WHERE id = :id",
            [
                'tx_id'        => $gatewayTxId,
                'paid_at'      => $paidAt,
                'confirmed_by' => $confirmedBy,
                'resp'         => $gatewayResponse,
                'id'           => $paymentId
            ]
        );
    }

    /**
     * Mark payment as EXPIRED.
     */
    public function markAsExpired(int $paymentId): bool
    {
        return $this->execute(
            "UPDATE {$this->table} SET payment_status = 'expired' WHERE id = :id",
            ['id' => $paymentId]
        );
    }

    /**
     * Mark payment as FAILED.
     */
    public function markAsFailed(int $paymentId, ?string $gatewayResponse = null): bool
    {
        return $this->execute(
            "UPDATE {$this->table} SET payment_status = 'failed', gateway_response = COALESCE(:resp, gateway_response) WHERE id = :id",
            ['resp' => $gatewayResponse, 'id' => $paymentId]
        );
    }

    /**
     * Mark payment as CANCELLED.
     */
    public function markAsCancelled(int $paymentId): bool
    {
        return $this->execute(
            "UPDATE {$this->table} SET payment_status = 'cancelled' WHERE id = :id",
            ['id' => $paymentId]
        );
    }

    /**
     * Get all overdue PENDING payments (expired_at has passed).
     */
    public function getOverduePayments(): array
    {
        return $this->query(
            "SELECT * FROM {$this->table}
             WHERE payment_status = 'pending' AND expired_at < NOW()",
            []
        );
    }

    /**
     * Legacy compatibility — old createPayment method.
     */
    public function createPayment(int $orderId, string $method, float $amount, int $expireMinutes = 60): bool
    {
        $expiredAt = date('Y-m-d H:i:s', strtotime("+{$expireMinutes} minutes"));
        $sql = "INSERT INTO {$this->table} (order_id, payment_method, payment_status, payment_type, amount, expired_at, idempotency_key)
                VALUES (:order_id, :method, 'pending', :method2, :amount, :expired_at, :key)
                ON DUPLICATE KEY UPDATE payment_method = :method3, amount = :amount2, expired_at = :expired_at2, payment_status = 'pending'";

        return $this->execute($sql, [
            'order_id'   => $orderId,
            'method'     => $method,
            'method2'    => $method,
            'method3'    => $method,
            'amount'     => $amount,
            'expired_at' => $expiredAt,
            'amount2'    => $amount,
            'expired_at2' => $expiredAt,
            'key'        => 'LEGACY-' . $orderId . '-' . time()
        ]);
    }
}
