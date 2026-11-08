<?php

namespace App\Services\AI;

/**
 * Logger sederhana ke storage/logs/ai.log.
 * Sengaja TIDAK mencatat isi pertanyaan/jawaban pelanggan (menghindari data pribadi di log).
 */
class AILogger
{
    public static function error(string $event, array $context = []): void
    {
        $dir = dirname(__DIR__, 3) . '/storage/logs';
        if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
            return;
        }

        $line = sprintf(
            "[%s] %s %s\n",
            date('Y-m-d H:i:s'),
            $event,
            $context ? json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : ''
        );

        @file_put_contents($dir . '/ai.log', $line, FILE_APPEND | LOCK_EX);
    }
}
