<?php

namespace App\Services;

use App\Models\BaseModel;
use App\Helpers\Env;

class NotificationService
{
    /**
     * Simpan notifikasi ke tabel notifications (database).
     * Pengiriman WhatsApp dilakukan secara terpisah (di luar transaksi DB)
     * oleh wa_notif.php helper, bukan di sini.
     *
     * @param int         $userId      ID user penerima notifikasi
     * @param string      $title       Judul notifikasi
     * @param string      $message     Isi pesan notifikasi
     * @param string      $type        Tipe notifikasi (order_update, dll)
     * @param int|null    $referenceId ID referensi (misalnya order_id)
     * @return bool
     */
    public function send(int $userId, string $title, string $message, string $type = 'order_update', ?int $referenceId = null): bool
    {
        $pdo = BaseModel::getPdo();
        if (!$pdo) {
            return false;
        }

        try {
            $stmt = $pdo->prepare("INSERT INTO notifications (user_id, title, message, type, reference_id, is_read) VALUES (:user_id, :title, :message, :type, :ref_id, 0)");
            $saved = $stmt->execute([
                'user_id' => $userId,
                'title'   => $title,
                'message' => $message,
                'type'    => $type,
                'ref_id'  => $referenceId
            ]);

            return $saved;
        } catch (\Throwable $e) {
            error_log('[NOTIFICATION_SAVE_ERROR] ' . $e->getMessage());
            return false;
        }
    }
}
