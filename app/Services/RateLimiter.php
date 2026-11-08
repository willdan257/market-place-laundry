<?php

namespace App\Services;

use App\Helpers\Env;

class RateLimiter
{
    private static string $storageDir = __DIR__ . '/../../storage/framework/rate_limits';

    private static function initStorage(): void
    {
        if (!is_dir(self::$storageDir)) {
            @mkdir(self::$storageDir, 0755, true);
        }
    }

    /**
     * Get client IP securely.
     * Never trust X-Forwarded-For unless REMOTE_ADDR is in TRUSTED_PROXIES.
     */
    public static function getClientIp(): string
    {
        $remoteAddr = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        $trustedProxies = array_filter(array_map('trim', explode(',', (string) Env::get('TRUSTED_PROXIES', ''))));

        if (!empty($trustedProxies) && in_array($remoteAddr, $trustedProxies, true)) {
            $forwarded = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '';
            if (!empty($forwarded)) {
                $ips = array_map('trim', explode(',', $forwarded));
                $clientIp = $ips[0] ?? $remoteAddr;
                if (filter_var($clientIp, FILTER_VALIDATE_IP)) {
                    return $clientIp;
                }
            }
        }

        return $remoteAddr;
    }

    /**
     * Build standard storage file path for a key.
     */
    private static function getFilePath(string $key): string
    {
        self::initStorage();
        $safeName = hash('sha256', $key) . '.json';
        return self::$storageDir . '/' . $safeName;
    }

    /**
     * Check if rate limit threshold has been exceeded.
     */
    public static function tooManyAttempts(string $key, int $maxAttempts, int $decaySeconds = 900): bool
    {
        $file = self::getFilePath($key);
        if (!file_exists($file)) {
            return false;
        }

        $content = @file_get_contents($file);
        if (!$content) {
            return false;
        }

        $data = json_decode($content, true);
        if (!is_array($data)) {
            return false;
        }

        $now = time();
        $attempts = array_filter($data['attempts'] ?? [], fn($t) => ($now - $t) < $decaySeconds);

        return count($attempts) >= $maxAttempts;
    }

    /**
     * Record an attempt. Returns current attempt count.
     */
    public static function hit(string $key, int $decaySeconds = 900): int
    {
        $file = self::getFilePath($key);
        $now = time();
        $attempts = [];

        if (file_exists($file)) {
            $content = @file_get_contents($file);
            $data = json_decode((string) $content, true);
            if (is_array($data) && isset($data['attempts'])) {
                $attempts = array_filter($data['attempts'], fn($t) => ($now - $t) < $decaySeconds);
            }
        }

        $attempts[] = $now;
        @file_put_contents($file, json_encode([
            'key' => $key,
            'updated_at' => $now,
            'attempts' => array_values($attempts)
        ]), LOCK_EX);

        return count($attempts);
    }

    /**
     * Get remaining lockout time in seconds.
     */
    public static function availableIn(string $key, int $decaySeconds = 900): int
    {
        $file = self::getFilePath($key);
        if (!file_exists($file)) {
            return 0;
        }

        $content = @file_get_contents($file);
        $data = json_decode((string) $content, true);
        if (!is_array($data) || empty($data['attempts'])) {
            return 0;
        }

        $oldestValidAttempt = min($data['attempts']);
        $remaining = $decaySeconds - (time() - $oldestValidAttempt);
        return max(1, $remaining);
    }

    /**
     * Clear rate limit entries for a key.
     */
    public static function clear(string $key): void
    {
        $file = self::getFilePath($key);
        if (file_exists($file)) {
            @unlink($file);
        }
    }
}
