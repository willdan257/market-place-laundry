<?php

namespace App\Services;

use App\Helpers\Env;
use App\Helpers\Session;
use App\Models\Setting;

class GoogleAuthService
{
    private string $clientId;
    private string $clientSecret;
    private string $redirectUri;
    private bool $enabled;
    private string $logPath;

    public function __construct()
    {
        // 1. Baca dari database Setting terlebih dahulu, fallback ke .env
        $dbEnabled = Setting::get('google_auth_enabled');
        $dbClientId = Setting::get('google_client_id');
        $dbClientSecret = Setting::get('google_client_secret', true); // decrypt

        $this->enabled = ($dbEnabled !== null)
            ? ($dbEnabled === '1')
            : filter_var(Env::get('GOOGLE_AUTH_ENABLED', false), FILTER_VALIDATE_BOOLEAN);

        $this->clientId = !empty($dbClientId)
            ? trim($dbClientId)
            : trim((string) Env::get('GOOGLE_CLIENT_ID', ''));

        $this->clientSecret = !empty($dbClientSecret)
            ? trim($dbClientSecret)
            : trim((string) Env::get('GOOGLE_CLIENT_SECRET', ''));

        $appUrl = rtrim((string) Env::get('APP_URL', 'http://localhost:8000'), '/');
        $this->redirectUri = (string) Env::get('GOOGLE_REDIRECT_URI', $appUrl . '/auth/google/callback');
        $this->logPath = __DIR__ . '/../../storage/logs/auth.log';

        $logDir = dirname($this->logPath);
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0755, true);
        }
    }

    public function isConfigured(): bool
    {
        return $this->enabled &&
               !empty($this->clientId) &&
               !str_contains(strtolower($this->clientId), 'your_google_client_id') &&
               !empty($this->clientSecret) &&
               !str_contains(strtolower($this->clientSecret), 'your_google_client_secret');
    }

    /**
     * Dapatkan status diagnostik konfigurasi Google OAuth untuk pesan detail antarmuka.
     * Mengembalikan null jika konfigurasi sudah lengkap & aktif.
     */
    public function getDisabledReason(): ?string
    {
        if (!$this->enabled) {
            return 'Fitur dinonaktifkan di pengaturan';
        }
        if (empty($this->clientId) || str_contains(strtolower($this->clientId), 'your_google_client_id')) {
            return 'Client ID belum diisi';
        }
        if (empty($this->clientSecret) || str_contains(strtolower($this->clientSecret), 'your_google_client_secret')) {
            return 'Client Secret belum diisi';
        }
        return null;
    }

    private function logAuthError(string $message, array $context = []): void
    {
        $date = date('Y-m-d H:i:s');
        unset($context['client_secret'], $context['access_token'], $context['code']);
        $ctxStr = !empty($context) ? ' ' . json_encode($context) : '';
        $entry = "[{$date}] [GOOGLE_OAUTH_ERROR] {$message}{$ctxStr}\n";
        @file_put_contents($this->logPath, $entry, FILE_APPEND | LOCK_EX);
    }

    public function getAuthUrl(): ?string
    {
        if (!$this->isConfigured()) {
            return null;
        }

        $state = bin2hex(random_bytes(32));
        Session::set('_oauth_state', $state);

        $params = [
            'client_id'     => $this->clientId,
            'redirect_uri'  => $this->redirectUri,
            'response_type' => 'code',
            'scope'         => 'openid email profile',
            'access_type'   => 'online',
            'prompt'        => 'select_account',
            'state'         => $state
        ];

        return 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query($params);
    }

    public function verifyState(?string $state): bool
    {
        $saved = Session::get('_oauth_state');
        Session::remove('_oauth_state');
        return !empty($saved) && !empty($state) && hash_equals($saved, $state);
    }

    public function getUserInfoFromCode(string $code): ?array
    {
        if (!$this->isConfigured()) {
            $this->logAuthError('Attempted OAuth exchange without valid Google credentials.');
            return null;
        }

        // 1. Exchange Authorization Code for Token
        $tokenUrl = 'https://oauth2.googleapis.com/token';
        $postData = [
            'code'          => $code,
            'client_id'     => $this->clientId,
            'client_secret' => $this->clientSecret,
            'redirect_uri'  => $this->redirectUri,
            'grant_type'    => 'authorization_code'
        ];

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $tokenUrl,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => http_build_query($postData),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);

        $tokenResponse = curl_exec($ch);
        $curlError = curl_error($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($curlError || $httpCode !== 200 || !$tokenResponse) {
            $this->logAuthError('Token exchange failed', [
                'http_code'  => $httpCode,
                'curl_error' => $curlError
            ]);
            return null;
        }

        $tokenData = json_decode($tokenResponse, true);
        $accessToken = $tokenData['access_token'] ?? null;

        if (!$accessToken) {
            $this->logAuthError('Access token missing from Google response', ['http_code' => $httpCode]);
            return null;
        }

        // 2. Fetch User Profile from Google UserInfo endpoint
        $userInfoUrl = 'https://www.googleapis.com/oauth2/v3/userinfo';
        $chUser = curl_init();
        curl_setopt_array($chUser, [
            CURLOPT_URL            => $userInfoUrl,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_HTTPHEADER     => [
                'Authorization: Bearer ' . $accessToken,
                'Accept: application/json'
            ]
        ]);

        $userResponse = curl_exec($chUser);
        $curlErrorUser = curl_error($chUser);
        $httpCodeUser = curl_getinfo($chUser, CURLINFO_HTTP_CODE);
        curl_close($chUser);

        if ($curlErrorUser || $httpCodeUser !== 200 || !$userResponse) {
            $this->logAuthError('UserInfo request failed', [
                'http_code'  => $httpCodeUser,
                'curl_error' => $curlErrorUser
            ]);
            return null;
        }

        $userInfo = json_decode($userResponse, true);

        if (!isset($userInfo['sub'])) {
            $this->logAuthError('Invalid userinfo response: sub missing');
            return null;
        }

        return [
            'google_id'      => $userInfo['sub'],
            'email'          => $userInfo['email'] ?? '',
            'name'           => $userInfo['name'] ?? 'Google User',
            'picture'        => $userInfo['picture'] ?? null,
            'email_verified' => (bool) ($userInfo['email_verified'] ?? false)
        ];
    }
}
