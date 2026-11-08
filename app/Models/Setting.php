<?php

namespace App\Models;

use PDO;
use Exception;
use App\Helpers\Env;

class Setting
{
    private const CIPHER = 'AES-256-CBC';

    /**
     * Dapatkan kunci enkripsi dari APP_KEY (.env)
     */
    public static function getEncryptionKey(): string
    {
        $raw = (string) Env::get('APP_KEY', 'default_secret_key_laundry_32chars_long!');
        if (str_starts_with($raw, 'base64:')) {
            $decoded = base64_decode(substr($raw, 7), true);
            if ($decoded !== false && strlen($decoded) >= 16) {
                return hash('sha256', $decoded, true);
            }
        }
        return hash('sha256', $raw, true);
    }

    /**
     * Enkripsi teks menggunakan AES-256-CBC
     */
    public static function encrypt(?string $plain): ?string
    {
        if ($plain === null || $plain === '') {
            return null;
        }

        $key = self::getEncryptionKey();
        $ivLength = openssl_cipher_iv_length(self::CIPHER);
        $iv = openssl_random_pseudo_bytes($ivLength);

        $ciphertext = openssl_encrypt($plain, self::CIPHER, $key, OPENSSL_RAW_DATA, $iv);
        if ($ciphertext === false) {
            return null;
        }

        // Simpan dalam format base64 iv + ciphertext
        return base64_encode($iv . $ciphertext);
    }

    /**
     * Dekripsi teks terenkripsi
     */
    public static function decrypt(?string $encrypted): ?string
    {
        if ($encrypted === null || $encrypted === '') {
            return null;
        }

        $key = self::getEncryptionKey();
        $raw = base64_decode($encrypted, true);
        if ($raw === false) {
            return null;
        }

        $ivLength = openssl_cipher_iv_length(self::CIPHER);
        if (strlen($raw) <= $ivLength) {
            return null;
        }

        $iv = substr($raw, 0, $ivLength);
        $ciphertext = substr($raw, $ivLength);

        $decrypted = openssl_decrypt($ciphertext, self::CIPHER, $key, OPENSSL_RAW_DATA, $iv);
        if ($decrypted === false) {
            return null;
        }

        return $decrypted;
    }

    /**
     * Samarkan kunci (misal: ••••abcd)
     */
    public static function mask(?string $plain): string
    {
        if (empty($plain)) {
            return '';
        }
        $len = strlen($plain);
        if ($len <= 4) {
            return str_repeat('•', 4);
        }
        $last4 = substr($plain, -4);
        return '••••' . $last4;
    }

    /**
     * Ambil nilai setting berdasarkan key.
     * Jika $decrypt = true, nilai akan didekripsi.
     */
    public static function get(string $key, bool $decrypt = false): ?string
    {
        $pdo = BaseModel::getPdo();
        if (!$pdo) {
            return null;
        }

        $stmt = $pdo->prepare('SELECT `value` FROM settings WHERE `key` = :key LIMIT 1');
        $stmt->execute([':key' => $key]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row || $row['value'] === null) {
            return null;
        }

        $value = $row['value'];
        if ($decrypt) {
            return self::decrypt($value);
        }

        return $value;
    }

    /**
     * Ambil semua data settings sebagai associative array key => value.
     */
    public static function getAll(): array
    {
        $pdo = BaseModel::getPdo();
        if (!$pdo) {
            return [];
        }

        $stmt = $pdo->query('SELECT `key`, `value` FROM settings');
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $results = [];
        foreach ($rows as $row) {
            $results[$row['key']] = $row['value'];
        }

        return $results;
    }

    /**
     * Simpan nilai setting (upsert).
     * Jika $encrypt = true, nilai akan dienkripsi dengan openssl.
     */
    public static function set(string $key, ?string $value, bool $encrypt = false): bool
    {
        $pdo = BaseModel::getPdo();
        if (!$pdo) {
            throw new Exception('Database connection unavailable.');
        }

        if ($encrypt && !empty($value)) {
            $value = self::encrypt($value);
        }

        $stmt = $pdo->prepare('INSERT INTO settings (`key`, `value`, `created_at`, `updated_at`)
            VALUES (:key, :value, NOW(), NOW())
            ON DUPLICATE KEY UPDATE `value` = VALUES(`value`), `updated_at` = NOW()');

        return $stmt->execute([
            ':key'   => $key,
            ':value' => $value
        ]);
    }
}
