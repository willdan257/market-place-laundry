<?php

namespace App\Helpers;

class Response
{
    public static function json(mixed $data, int $statusCode = 200): void
    {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;
    }

    public static function redirect(string $url, int $statusCode = 302): void
    {
        if (defined('TEST_ENV') && TEST_ENV) {
            $_SERVER['TEST_REDIRECT_URL'] = $url;
            return;
        }
        if (!headers_sent()) {
            http_response_code($statusCode);
            header("Location: {$url}");
        }
        exit;
    }
}
