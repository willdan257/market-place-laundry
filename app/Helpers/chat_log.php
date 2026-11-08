<?php

use App\Models\BaseModel;

if (!function_exists('simpan_chat')) {
    /**
     * Menyimpan log pesan chat terkait pesanan ke tabel order_chats.
     * Acuan fungsi simpan_chat() standar sistem.
     *
     * @param int $orderId ID pesanan laundry
     * @param int $senderId ID user pengirim
     * @param string $senderRole Role pengirim ('customer', 'owner', 'driver', 'admin')
     * @param string $message Isi pesan chat
     * @return int|false ID baris yang tersimpan atau false jika gagal
     */
    function simpan_chat(int $orderId, int $senderId, string $senderRole, string $message): int|false
    {
        $message = trim($message);
        if ($orderId <= 0 || $senderId <= 0 || empty($message)) {
            return false;
        }

        $allowedRoles = ['customer', 'owner', 'driver', 'admin', 'pelanggan', 'mitra', 'kurir'];
        $roleLower = strtolower(trim($senderRole));
        if (!in_array($roleLower, $allowedRoles, true)) {
            return false;
        }

        $canonicalRole = match ($roleLower) {
            'pelanggan' => 'customer',
            'mitra'     => 'owner',
            'kurir'     => 'driver',
            default     => $roleLower
        };

        try {
            $pdo = BaseModel::getPdo();
            if (!$pdo) {
                return false;
            }

            $stmt = $pdo->prepare("
                INSERT INTO order_chats (order_id, sender_id, sender_role, message, created_at)
                VALUES (:order_id, :sender_id, :sender_role, :message, NOW())
            ");

            $success = $stmt->execute([
                'order_id'    => $orderId,
                'sender_id'   => $senderId,
                'sender_role' => $canonicalRole,
                'message'     => $message,
            ]);

            return $success ? (int) $pdo->lastInsertId() : false;
        } catch (\Throwable $e) {
            error_log("[simpan_chat error] " . $e->getMessage());
            return false;
        }
    }
}

