<?php

namespace App\Services\AI;

abstract class AbstractAIProvider implements AIProviderInterface
{
    protected string $apiKey;
    protected string $model;
    protected int $timeout;
    protected int $connectTimeout;
    protected string $caBundle;

    public function __construct(
        string $apiKey,
        string $model,
        int $timeout = 25,
        int $connectTimeout = 8,
        string $caBundle = ''
    ) {
        $this->apiKey = $apiKey;
        $this->model = $model;
        $this->timeout = $timeout;
        $this->connectTimeout = $connectTimeout;
        $this->caBundle = $caBundle;
    }

    /**
     * POST JSON ke provider dan petakan semua kegagalan ke AIException yang aman untuk pelanggan.
     */
    protected function postJson(string $url, array $headers, array $payload): array
    {
        if (!function_exists('curl_init')) {
            AILogger::error('curl_extension_missing');
            throw new AIException('Asisten AI belum dapat digunakan karena ekstensi cURL PHP belum aktif.', 503, 'curl_missing');
        }

        $ch = curl_init($url);
        $options = [
            CURLOPT_POST           => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => array_merge(['Content-Type: application/json'], $headers),
            CURLOPT_POSTFIELDS     => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            CURLOPT_TIMEOUT        => $this->timeout,
            CURLOPT_CONNECTTIMEOUT => $this->connectTimeout,
            CURLOPT_SSL_VERIFYPEER => true,
        ];
        if ($this->caBundle !== '' && is_file($this->caBundle)) {
            $options[CURLOPT_CAINFO] = $this->caBundle;
        }
        curl_setopt_array($ch, $options);

        $body = curl_exec($ch);
        $errno = curl_errno($ch);
        $error = curl_error($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);

        if ($errno === 28) {
            AILogger::error('provider_timeout', ['provider' => static::class]);
            throw new AIException('Asisten AI terlalu lama merespons. Silakan coba lagi.', 504, 'ai_timeout');
        }

        if ($errno !== 0 || $body === false) {
            AILogger::error('provider_unreachable', ['provider' => static::class, 'curl_errno' => $errno, 'curl_error' => $error]);
            throw new AIException('Tidak dapat terhubung ke layanan AI saat ini. Silakan coba lagi nanti.', 503, 'ai_unreachable');
        }

        if ($status === 401 || $status === 403) {
            AILogger::error('provider_auth_failed', ['provider' => static::class, 'status' => $status]);
            throw new AIException('Asisten AI belum dikonfigurasi dengan benar. Silakan hubungi admin.', 503, 'ai_auth');
        }

        if ($status === 429) {
            AILogger::error('provider_rate_limited', ['provider' => static::class]);
            throw new AIException('Asisten AI sedang sibuk. Silakan coba lagi sebentar lagi.', 503, 'ai_busy', 15);
        }

        if ($status < 200 || $status >= 300) {
            AILogger::error('provider_http_error', [
                'provider' => static::class,
                'status'   => $status,
                'body'     => mb_substr((string) $body, 0, 300),
            ]);
            throw new AIException('Layanan AI sedang bermasalah. Silakan coba lagi nanti.', 502, 'ai_upstream');
        }

        $decoded = json_decode((string) $body, true);
        if (!is_array($decoded)) {
            AILogger::error('provider_invalid_json', ['provider' => static::class]);
            throw new AIException('Layanan AI mengembalikan respons yang tidak valid. Silakan coba lagi.', 502, 'ai_invalid_response');
        }

        return $decoded;
    }

    protected function emptyResponse(): AIException
    {
        return new AIException('Asisten tidak dapat memberikan jawaban untuk pertanyaan tersebut. Coba ajukan dengan kalimat lain.', 502, 'ai_empty');
    }
}
