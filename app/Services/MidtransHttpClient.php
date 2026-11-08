<?php

namespace App\Services;

use App\Helpers\Env;
use Exception;

/**
 * Low-level HTTP client for Midtrans REST API.
 * Handles authentication (Base64 Server Key), cURL requests, and error parsing.
 */
class MidtransHttpClient
{
    private string $serverKey;
    private bool $isProduction;
    private string $baseUrl;

    // Midtrans API endpoints
    const SANDBOX_BASE_URL    = 'https://api.sandbox.midtrans.com';
    const PRODUCTION_BASE_URL = 'https://api.midtrans.com';
    const SNAP_SANDBOX_URL    = 'https://app.sandbox.midtrans.com/snap/v1';
    const SNAP_PRODUCTION_URL = 'https://app.midtrans.com/snap/v1';

    public function __construct()
    {
        $this->serverKey    = Env::get('PAYMENT_SERVER_KEY', '');
        $this->isProduction = Env::get('PAYMENT_ENV', 'sandbox') === 'production';
        $this->baseUrl      = $this->isProduction ? self::PRODUCTION_BASE_URL : self::SANDBOX_BASE_URL;
    }

    /**
     * Create a QRIS charge via Midtrans Core API v2.
     * Returns the full API response array.
     *
     * @throws Exception if API key missing or HTTP call fails
     */
    public function createQrisCharge(string $orderId, float $amount, array $customerDetails): array
    {
        if (empty($this->serverKey) || $this->serverKey === 'your_payment_server_key_here') {
            $isLocal = (Env::get('APP_ENV', 'local') === 'local') || (Env::get('APP_DEBUG', 'true') === 'true');
            if ($isLocal) {
                $mockTxId = 'MOCK-TX-' . uniqid();
                return [
                    'status_code'        => '201',
                    'status_message'     => 'Success, QRIS transaction is created (SIMULATED)',
                    'transaction_id'     => $mockTxId,
                    'order_id'           => $orderId,
                    'gross_amount'       => (string) ((int) round($amount)),
                    'payment_type'       => 'qris',
                    'transaction_time'   => date('Y-m-d H:i:s'),
                    'transaction_status' => 'pending',
                    'actions'            => [
                        [
                            'name'   => 'generate-qr-code',
                            'method' => 'GET',
                            'url'    => "https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=MARKETPLACE_LAUNDRY_QRIS_" . urlencode($orderId)
                        ]
                    ]
                ];
            }
            $this->assertKeyConfigured();
        }

        $payload = [
            'payment_type'    => 'qris',
            'transaction_details' => [
                'order_id'     => $orderId,
                'gross_amount' => (int) round($amount) // Midtrans requires integer
            ],
            'qris' => [
                'acquirer' => 'gopay'
            ],
            'customer_details' => [
                'first_name' => $customerDetails['name'] ?? 'Customer',
                'email'      => $customerDetails['email'] ?? '',
                'phone'      => $customerDetails['phone'] ?? ''
            ]
        ];

        $endpoint = $this->baseUrl . '/v2/charge';
        return $this->post($endpoint, $payload);
    }

    /**
     * Get transaction status from Midtrans (source of truth for payment verification).
     */
    public function getTransactionStatus(string $orderId): array
    {
        $this->assertKeyConfigured();
        $endpoint = $this->baseUrl . "/v2/{$orderId}/status";
        return $this->get($endpoint);
    }

    /**
     * Validate Midtrans webhook signature.
     * Formula: SHA512(order_id + status_code + gross_amount + server_key)
     */
    public function validateWebhookSignature(
        string $orderId,
        string $statusCode,
        string $grossAmount,
        string $incomingSignature
    ): bool {
        $serverKey = $this->serverKey;
        if (empty($serverKey) || $serverKey === 'your_payment_server_key_here') {
            $isLocal = (Env::get('APP_ENV', 'local') === 'local') || (Env::get('APP_DEBUG', 'true') === 'true');
            if ($isLocal && ($incomingSignature === 'mock_test_signature' || $incomingSignature === 'test_signature')) {
                return true;
            }
            $serverKey = 'sandbox_test_key';
        }
        $raw       = (string)$orderId . (string)$statusCode . (string)$grossAmount . $serverKey;
        $computed  = hash('sha512', $raw);
        return hash_equals($computed, (string)$incomingSignature);
    }

    private function post(string $url, array $payload): array
    {
        return $this->request('POST', $url, $payload);
    }

    private function get(string $url): array
    {
        return $this->request('GET', $url, []);
    }

    private function request(string $method, string $url, array $payload): array
    {
        $authHeader = 'Authorization: Basic ' . base64_encode($this->serverKey . ':');

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'Accept: application/json',
                $authHeader
            ]
        ]);

        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        }

        $responseBody = curl_exec($ch);
        $httpCode     = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError    = curl_error($ch);
        curl_close($ch);

        if ($responseBody === false || !empty($curlError)) {
            throw new Exception("Koneksi ke Midtrans API gagal: {$curlError}");
        }

        $data = json_decode($responseBody, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception("Midtrans mengembalikan respons tidak valid (bukan JSON). HTTP {$httpCode}.");
        }

        // HTTP 2xx is success; others are errors
        if ($httpCode < 200 || $httpCode >= 300) {
            $errorMsg = $data['error_messages'][0] ?? $data['message'] ?? "HTTP {$httpCode}";
            throw new Exception("Midtrans API Error: {$errorMsg}");
        }

        return $data;
    }

    private function assertKeyConfigured(): void
    {
        if (empty($this->serverKey) || $this->serverKey === 'your_payment_server_key_here') {
            throw new Exception(
                'Midtrans Server Key belum dikonfigurasi. Tambahkan PAYMENT_SERVER_KEY pada file .env. ' .
                'Dapatkan key dari https://dashboard.sandbox.midtrans.com/settings/config_info'
            );
        }
    }

    public function isProduction(): bool
    {
        return $this->isProduction;
    }
}
