<?php

namespace App\Services\AI;

/**
 * Rate limiter sederhana berbasis file, per kunci (user_ID atau guest_hashIP), sliding window.
 * Dibatasi per menit dan per hari agar biaya API tidak membengkak.
 *
 * Disimpan per user (bukan per session) supaya tidak bisa dilewati dengan membuka tab baru.
 */
class AIRateLimiter
{
    private int $perMinute;
    private int $perDay;
    private string $dir;

    public function __construct(int $perMinute, int $perDay, ?string $dir = null)
    {
        $this->perMinute = max(1, $perMinute);
        $this->perDay = max(1, $perDay);
        $this->dir = $dir ?? dirname(__DIR__, 3) . '/storage/cache/ai_rate';
    }

    /**
     * Catat satu permintaan. Melempar AIException (429) jika limit terlampaui.
     */
    public function hit(string|int $key): void
    {
        if (!is_dir($this->dir) && !@mkdir($this->dir, 0775, true) && !is_dir($this->dir)) {
            AILogger::error('rate_limit_storage_unavailable', ['dir' => $this->dir]);
            throw new AIException('Asisten sedang tidak tersedia. Silakan coba lagi nanti.', 503, 'rate_store_unavailable');
        }

        $handle = @fopen($this->dir . '/' . preg_replace('/[^a-zA-Z0-9_-]/', '', (string) $key) . '.json', 'c+');
        if (!$handle) {
            AILogger::error('rate_limit_file_unwritable', ['key' => (string) $key]);
            throw new AIException('Asisten sedang tidak tersedia. Silakan coba lagi nanti.', 503, 'rate_store_unavailable');
        }

        try {
            flock($handle, LOCK_EX);

            $raw = stream_get_contents($handle);
            $hits = json_decode($raw !== false && $raw !== '' ? $raw : '[]', true);
            if (!is_array($hits)) {
                $hits = [];
            }

            $now = time();
            $hits = array_values(array_filter($hits, fn($t) => is_int($t) && $t > $now - 86400));
            $lastMinute = array_values(array_filter($hits, fn($t) => $t > $now - 60));

            if (count($lastMinute) >= $this->perMinute) {
                $retry = max(1, min($lastMinute) + 60 - $now);
                throw new AIException(
                    "Terlalu banyak pertanyaan dalam waktu singkat. Coba lagi dalam {$retry} detik.",
                    429,
                    'rate_limited',
                    $retry
                );
            }

            if (count($hits) >= $this->perDay) {
                $retry = max(1, min($hits) + 86400 - $now);
                throw new AIException(
                    'Batas penggunaan asisten hari ini sudah tercapai. Silakan coba lagi besok atau hubungi bantuan pelanggan.',
                    429,
                    'daily_limit',
                    $retry
                );
            }

            $hits[] = $now;
            ftruncate($handle, 0);
            rewind($handle);
            fwrite($handle, json_encode($hits));
            fflush($handle);
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }
}
