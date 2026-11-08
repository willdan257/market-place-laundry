<?php

namespace App\Services;

use App\Models\BaseModel;
use App\Models\Setting;
use App\Helpers\Env;
use PDO;
use Throwable;

class WhatsAppService
{
    /** @var callable|null */
    private static $mockSender = null;

    /**
     * Memeriksa konfigurasi WhatsApp dan mengembalikan array status & kredensial.
     * Mengutamakan pembacaan dari tabel settings (database), dengan fallback ke .env.
     */
    public static function getConfig(): array
    {
        $dbEnabled = Setting::get('wa_enabled');
        $dbNotify = Setting::get('wa_notify_order_status');
        $dbToken = Setting::get('wa_token', true); // decrypt

        $enabled = ($dbEnabled !== null)
            ? ($dbEnabled === '1')
            : filter_var(Env::get('WA_ENABLED', false), FILTER_VALIDATE_BOOLEAN);

        $notifyStatusChange = ($dbNotify !== null)
            ? ($dbNotify === '1')
            : true;

        $token = !empty($dbToken) ? trim($dbToken) : trim((string) Env::get('WA_TOKEN', ''));
        $apiUrl = (string) Env::get('WA_API_URL', 'https://api.fonnte.com/send');

        return [
            'enabled'              => $enabled && !empty($token),
            'notify_status_change' => $notifyStatusChange,
            'token'                => $token,
            'api_url'              => $apiUrl,
        ];
    }

    /**
     * Normalisasi nomor telepon ke format 628xxx.
     * Mengubah 08xxx atau 8xxx menjadi 628xxx.
     */
    public static function normalizePhone(?string $phone): ?string
    {
        if ($phone === null) {
            return null;
        }

        $clean = preg_replace('/[\s\-\(\)\+]/', '', $phone);
        $clean = preg_replace('/\D/', '', $clean);

        if (empty($clean)) {
            return null;
        }

        if (str_starts_with($clean, '08')) {
            $clean = '62' . substr($clean, 1);
        } elseif (str_starts_with($clean, '8')) {
            $clean = '62' . $clean;
        } elseif (str_starts_with($clean, '62')) {
            // Sudah 62xxx
        } else {
            return null;
        }

        $length = strlen($clean);
        if ($length < 10 || $length > 16 || !ctype_digit($clean)) {
            return null;
        }

        return $clean;
    }

    /**
     * Samarkan nomor telepon untuk keperluan logging (misal: 628****2345).
     */
    public static function maskPhone(?string $phone): string
    {
        if (empty($phone)) {
            return '****';
        }

        $clean = preg_replace('/\D/', '', $phone);
        $len = strlen($clean);
        if ($len < 8) {
            return '****';
        }

        $prefix = substr($clean, 0, 3);
        $suffix = substr($clean, -4);
        return $prefix . '****' . $suffix;
    }

    /**
     * Pastikan tabel wa_logs sudah ada di database secara otomatis jika belum dibuat.
     */
    public static function ensureTableExists(PDO $pdo): void
    {
        static $checked = false;
        if ($checked) {
            return;
        }

        try {
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS `wa_logs` (
                  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                  `order_id` INT UNSIGNED DEFAULT NULL,
                  `target_phone` VARCHAR(30) NOT NULL,
                  `recipient_type` ENUM('customer', 'store_admin', 'system_admin') NOT NULL DEFAULT 'customer',
                  `notification_type` VARCHAR(50) NOT NULL,
                  `status` ENUM('sent', 'failed', 'skipped') NOT NULL DEFAULT 'sent',
                  `error_message` VARCHAR(255) DEFAULT NULL,
                  `message_preview` VARCHAR(255) DEFAULT NULL,
                  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                  PRIMARY KEY (`id`),
                  KEY `idx_wa_order_status` (`order_id`, `notification_type`, `recipient_type`),
                  KEY `idx_wa_target` (`target_phone`),
                  KEY `idx_wa_created_at` (`created_at`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
            ");
            $checked = true;
        } catch (Throwable $e) {
            // Jangan gagalkan alur
        }
    }

    /**
     * Cek apakah notifikasi untuk kombinasi order_id + notification_type + recipient_type sudah pernah berhasil terkirim.
     * Mencegah pesan ganda untuk status yang sama.
     */
    public static function hasBeenSent(int $orderId, string $notificationType, string $recipientType): bool
    {
        $pdo = BaseModel::getPdo();
        if (!$pdo) {
            return false;
        }

        self::ensureTableExists($pdo);

        try {
            $stmt = $pdo->prepare("
                SELECT id FROM wa_logs 
                WHERE order_id = :order_id 
                  AND notification_type = :notif_type 
                  AND recipient_type = :recipient_type 
                  AND status = 'sent' 
                LIMIT 1
            ");
            $stmt->execute([
                'order_id'       => $orderId,
                'notif_type'     => $notificationType,
                'recipient_type' => $recipientType,
            ]);
            return (bool) $stmt->fetch();
        } catch (Throwable $e) {
            return false;
        }
    }

    /**
     * Catat riwayat pengiriman ke tabel wa_logs.
     */
    public static function recordLog(
        ?int $orderId,
        string $targetPhone,
        string $recipientType,
        string $notificationType,
        string $status,
        ?string $errorMessage,
        string $message
    ): void {
        $pdo = BaseModel::getPdo();
        if (!$pdo) {
            return;
        }

        self::ensureTableExists($pdo);

        try {
            $preview = mb_substr(trim(preg_replace('/\s+/', ' ', $message)), 0, 250);
            $errPreview = $errorMessage !== null ? mb_substr($errorMessage, 0, 250) : null;

            $stmt = $pdo->prepare("
                INSERT INTO wa_logs (
                    order_id, target_phone, recipient_type, notification_type,
                    status, error_message, message_preview, created_at
                ) VALUES (
                    :order_id, :target_phone, :recipient_type, :notification_type,
                    :status, :error_message, :message_preview, NOW()
                )
            ");
            $stmt->execute([
                'order_id'          => $orderId,
                'target_phone'      => self::maskPhone($targetPhone),
                'recipient_type'    => $recipientType,
                'notification_type' => $notificationType,
                'status'            => $status,
                'error_message'     => $errPreview,
                'message_preview'   => $preview,
            ]);
        } catch (Throwable $e) {
            error_log('[WA_LOG_RECORD_ERROR] ' . $e->getMessage());
        }
    }

    /**
     * Kirim pesan WhatsApp tunggal ke satu nomor via cURL Fonnte.
     */
    public static function sendSingle(
        string $rawPhone,
        string $message,
        ?int $orderId = null,
        string $recipientType = 'customer',
        string $notificationType = 'general'
    ): bool {
        $config = self::getConfig();

        // 1. Cek fitur aktif dan token tersedia
        if (!$config['enabled']) {
            self::recordLog($orderId, $rawPhone ?: 'none', $recipientType, $notificationType, 'skipped', 'WhatsApp disabled or token empty', $message);
            return false;
        }

        // 2. Normalisasi nomor penerima
        $normalizedPhone = self::normalizePhone($rawPhone);
        if ($normalizedPhone === null) {
            self::recordLog($orderId, $rawPhone ?: 'invalid', $recipientType, $notificationType, 'skipped', 'Nomor telepon tidak valid/kosong', $message);
            return false;
        }

        // 3. Cek duplikasi jika ada order_id
        if ($orderId !== null && self::hasBeenSent($orderId, $notificationType, $recipientType)) {
            return false;
        }

        // 4. Mock sender untuk unit test
        if (self::$mockSender !== null) {
            $mockResult = (bool) call_user_func(self::$mockSender, $normalizedPhone, $message, $orderId, $recipientType, $notificationType);
            self::recordLog($orderId, $normalizedPhone, $recipientType, $notificationType, $mockResult ? 'sent' : 'failed', $mockResult ? null : 'Mock sender returned false', $message);
            return $mockResult;
        }

        // 5. Eksekusi cURL Fonnte
        $token = $config['token'];
        $apiUrl = $config['api_url'];

        try {
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL            => $apiUrl,
                CURLOPT_POST           => true,
                CURLOPT_POSTFIELDS     => [
                    'target'      => $normalizedPhone,
                    'message'     => $message,
                    'countryCode' => '62',
                ],
                CURLOPT_HTTPHEADER     => [
                    'Authorization: ' . $token,
                ],
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT        => 8,
                CURLOPT_CONNECTTIMEOUT => 5,
                CURLOPT_SSL_VERIFYPEER => false,
            ]);

            $response = curl_exec($ch);
            $curlError = curl_error($ch);
            $httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($curlError) {
                self::recordLog($orderId, $normalizedPhone, $recipientType, $notificationType, 'failed', "cURL: {$curlError} (HTTP {$httpCode})", $message);
                return false;
            }

            $json = json_decode((string) $response, true);
            if (is_array($json) && !empty($json['status']) && $json['status'] == true) {
                self::recordLog($orderId, $normalizedPhone, $recipientType, $notificationType, 'sent', null, $message);
                return true;
            }

            $reason = $json['reason'] ?? $json['message'] ?? "HTTP {$httpCode}";
            self::recordLog($orderId, $normalizedPhone, $recipientType, $notificationType, 'failed', (string) $reason, $message);
            return false;
        } catch (Throwable $e) {
            self::recordLog($orderId, $normalizedPhone, $recipientType, $notificationType, 'failed', $e->getMessage(), $message);
            return false;
        }
    }

    /**
     * Helper untuk keperluan unit testing.
     */
    public static function setMockSender(?callable $mockSender): void
    {
        self::$mockSender = $mockSender;
    }
}
