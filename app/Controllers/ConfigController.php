<?php

namespace App\Controllers;

use App\Helpers\Session;
use App\Models\Setting;
use Exception;
use Throwable;

class ConfigController extends BaseController
{
    /**
     * Memastikan akses khusus peran admin.
     * Mengembalikan true jika admin, atau false & render 403 jika ditolak.
     */
    private function authorizeAdmin(): bool
    {
        $user = Session::get('user');
        if (!$user) {
            $this->redirect('/login');
            return false;
        }

        $role = strtolower(trim((string) ($user['role'] ?? '')));
        if ($role !== 'admin') {
            http_response_code(403);
            $this->render('403', [
                'title'   => '403 Akses Ditolak - Marketplace Laundry',
                'message' => 'Halaman Konfigurasi Sistem hanya diperuntukkan bagi Administrator Sistem.',
            ]);
            return false;
        }

        return true;
    }

    /**
     * Tampilan Halaman Konfigurasi Sistem & Aplikasi
     * GET /admin/konfigurasi
     */
    public function index(): void
    {
        if (!$this->authorizeAdmin()) {
            return;
        }

        $sessionUser = Session::get('user');

        // Muat semua pengaturan dari database
        $settings = Setting::getAll();

        // Ambil dan dekripsi nilai asli untuk masking
        $rawAiKey = Setting::get('ai_api_key', true);
        $rawWaToken = Setting::get('wa_token', true);
        $rawGoogleSecret = Setting::get('google_client_secret', true);

        $maskedAiKey = Setting::mask($rawAiKey);
        $maskedWaToken = Setting::mask($rawWaToken);
        $maskedGoogleSecret = Setting::mask($rawGoogleSecret);

        $activeTab = Session::getFlash('active_tab') ?? 'general';

        $this->render('admin/configuration', [
            'title'              => 'Konfigurasi Sistem & Aplikasi - Marketplace Laundry',
            'user'               => $sessionUser,
            'settings'           => $settings,
            'maskedAiKey'        => $maskedAiKey,
            'maskedWaToken'      => $maskedWaToken,
            'maskedGoogleSecret' => $maskedGoogleSecret,
            'activeTab'          => $activeTab,
        ]);
    }

    /**
     * Simpan Pengaturan
     * POST /admin/konfigurasi
     */
    public function save(): void
    {
        if (!$this->authorizeAdmin()) {
            return;
        }

        // 1. Verifikasi CSRF Token
        $token = $_POST['_token'] ?? null;
        if (!Session::verifyCsrfToken($token)) {
            error_log('[ConfigController::save] CSRF token mismatch');
            Session::setFlash('error', 'Sesi formulir kedaluwarsa atau tidak valid (CSRF token invalid). Silakan coba lagi.');
            $this->redirect('/admin/konfigurasi');
            return;
        }

        $section = trim((string) ($_POST['section'] ?? ''));

        try {
            switch ($section) {
                case 'general':
                    $this->saveGeneralSection();
                    break;

                case 'ai':
                    $this->saveAiSection();
                    break;

                case 'whatsapp':
                    $this->saveWhatsappSection();
                    break;

                case 'google_auth':
                    $this->saveGoogleAuthSection();
                    break;

                default:
                    Session::setFlash('error', 'Bagian pengaturan yang dikirim tidak dikenali.');
                    $this->redirect('/admin/konfigurasi');
                    return;
            }

            Session::setFlash('success', 'Pengaturan tersimpan.');
        } catch (Throwable $e) {
            error_log('[ConfigController::save] Error: ' . $e->getMessage());
            Session::setFlash('error', 'Gagal menyimpan pengaturan: ' . htmlspecialchars($e->getMessage()));
        }

        $this->redirect('/admin/konfigurasi');
    }

    /**
     * Simpan Tab Pengaturan Umum
     */
    private function saveGeneralSection(): void
    {
        Session::setFlash('active_tab', 'general');

        $appName = trim((string) ($_POST['app_name'] ?? ''));
        $businessName = trim((string) ($_POST['business_name'] ?? ''));
        $contactEmail = trim((string) ($_POST['contact_email'] ?? ''));
        $timezone = trim((string) ($_POST['timezone'] ?? 'Asia/Jakarta'));

        if ($appName === '') {
            throw new Exception('Nama aplikasi wajib diisi.');
        }
        if ($businessName === '') {
            throw new Exception('Nama usaha wajib diisi.');
        }
        if ($contactEmail === '' || !filter_var($contactEmail, FILTER_VALIDATE_EMAIL)) {
            throw new Exception('Format email kontak tidak valid.');
        }

        $allowedTimezones = ['Asia/Jakarta', 'Asia/Makassar', 'Asia/Jayapura'];
        if (!in_array($timezone, $allowedTimezones, true)) {
            $timezone = 'Asia/Jakarta';
        }

        Setting::set('app_name', $appName);
        Setting::set('business_name', $businessName);
        Setting::set('contact_email', $contactEmail);
        Setting::set('timezone', $timezone);

        // Upload Logo jika ada
        if (isset($_FILES['logo']) && $_FILES['logo']['error'] !== UPLOAD_ERR_NO_FILE) {
            $file = $_FILES['logo'];

            if ($file['error'] !== UPLOAD_ERR_OK) {
                throw new Exception('Terjadi kesalahan saat mengunggah berkas logo.');
            }

            // Maksimal 2MB (2 * 1024 * 1024)
            if ($file['size'] > 2097152) {
                throw new Exception('Ukuran berkas logo terlalu besar. Maksimal 2 MB.');
            }

            // Validasi MIME type di server menggunakan finfo
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime = finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);

            $allowedMimes = [
                'image/jpeg' => 'jpg',
                'image/png'  => 'png',
                'image/webp' => 'webp',
            ];

            if (!isset($allowedMimes[$mime])) {
                throw new Exception('Format berkas tidak valid. Hanya JPG, PNG, dan WEBP yang diperbolehkan.');
            }

            $ext = $allowedMimes[$mime];
            $filename = 'app_logo_' . time() . '.' . $ext;
            $uploadDir = __DIR__ . '/../../public/uploads/branding';

            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }

            $targetPath = $uploadDir . '/' . $filename;
            if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
                throw new Exception('Gagal memindahkan berkas logo ke folder tujuan.');
            }

            $webPath = '/uploads/branding/' . $filename;
            Setting::set('logo_path', $webPath);
        }
    }

    /**
     * Simpan Tab Chatbot AI
     */
    private function saveAiSection(): void
    {
        Session::setFlash('active_tab', 'ai');

        $aiEnabled = !empty($_POST['ai_enabled']) ? '1' : '0';
        $aiProvider = strtolower(trim((string) ($_POST['ai_provider'] ?? 'gemini')));
        $aiModel = trim((string) ($_POST['ai_model'] ?? 'gemini-1.5-flash'));
        $aiApiKey = trim((string) ($_POST['ai_api_key'] ?? ''));

        Setting::set('ai_enabled', $aiEnabled);
        Setting::set('ai_provider', $aiProvider);
        Setting::set('ai_model', $aiModel);

        // Jika kolom API key diisi, simpan yang baru terenkripsi.
        // Jika dikosongkan, pertahankan nilai lama!
        if ($aiApiKey !== '') {
            Setting::set('ai_api_key', $aiApiKey, true);
        }
    }

    /**
     * Simpan Tab Notifikasi WhatsApp
     */
    private function saveWhatsappSection(): void
    {
        Session::setFlash('active_tab', 'whatsapp');

        $waEnabled = !empty($_POST['wa_enabled']) ? '1' : '0';
        $waAdminPhone = trim((string) ($_POST['wa_admin_phone'] ?? ''));
        $waNotifyOrderStatus = !empty($_POST['wa_notify_order_status']) ? '1' : '0';
        $waToken = trim((string) ($_POST['wa_token'] ?? ''));

        // Normalisasi nomor telepon
        $waAdminPhone = preg_replace('/[^0-9]/', '', $waAdminPhone);
        if (str_starts_with($waAdminPhone, '0')) {
            $waAdminPhone = '62' . substr($waAdminPhone, 1);
        }

        Setting::set('wa_enabled', $waEnabled);
        Setting::set('wa_admin_phone', $waAdminPhone);
        Setting::set('wa_notify_order_status', $waNotifyOrderStatus);

        // Jika kolom token diisi, simpan yang baru terenkripsi.
        // Jika dikosongkan, pertahankan token lama!
        if ($waToken !== '') {
            Setting::set('wa_token', $waToken, true);
        }
    }

    /**
     * Simpan Tab Login Google (OAuth 2.0)
     */
    private function saveGoogleAuthSection(): void
    {
        Session::setFlash('active_tab', 'google_auth');

        $enabled = isset($_POST['google_auth_enabled']) && $_POST['google_auth_enabled'] === '1' ? '1' : '0';
        $clientId = trim((string) ($_POST['google_client_id'] ?? ''));
        $clientSecret = trim((string) ($_POST['google_client_secret'] ?? ''));

        Setting::set('google_auth_enabled', $enabled);
        Setting::set('google_client_id', $clientId);

        // Jika kolom client secret tidak dikosongkan pengguna, simpan & enkripsi
        if ($clientSecret !== '') {
            Setting::set('google_client_secret', $clientSecret, true);
        }
    }

    /**
     * Uji Koneksi Chatbot AI
     * POST /admin/konfigurasi/test-ai
     */
    public function testAi(): void
    {
        if (!$this->authorizeAdmin()) {
            return;
        }

        $token = $_POST['_token'] ?? null;
        if (!Session::verifyCsrfToken($token)) {
            $this->json(['success' => false, 'message' => 'CSRF token tidak valid.'], 403);
            return;
        }

        $provider = Setting::get('ai_provider') ?? 'gemini';
        $apiKey = Setting::get('ai_api_key', true);
        $model = Setting::get('ai_model') ?? 'gemini-1.5-flash';

        if (empty($apiKey)) {
            $this->json(['success' => false, 'message' => 'API Key belum diisi pada pengaturan AI.'], 400);
            return;
        }

        try {
            if ($provider === 'gemini') {
                $useModel = !empty($model) ? $model : 'gemini-1.5-flash';
                $url = "https://generativelanguage.googleapis.com/v1beta/models/{$useModel}:generateContent?key=" . urlencode($apiKey);
                $payload = json_encode([
                    'contents' => [
                        ['parts' => [['text' => 'Halo, ini uji koneksi singkat. Balas dengan kata OK.']]]
                    ]
                ]);

                $ch = curl_init($url);
                curl_setopt_array($ch, [
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_POST           => true,
                    CURLOPT_POSTFIELDS     => $payload,
                    CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
                    CURLOPT_TIMEOUT        => 10,
                    CURLOPT_CONNECTTIMEOUT => 5,
                    CURLOPT_SSL_VERIFYPEER => false,
                ]);

                $response = curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                $curlErr = curl_error($ch);
                curl_close($ch);

                if ($curlErr) {
                    $this->json(['success' => false, 'message' => 'Koneksi gagal (cURL error): ' . $curlErr], 500);
                    return;
                }

                if ($httpCode >= 200 && $httpCode < 300) {
                    $this->json(['success' => true, 'message' => "Koneksi ke Google Gemini ({$useModel}) berhasil terhubung!"]);
                    return;
                }

                $json = json_decode((string) $response, true);
                $errMsg = $json['error']['message'] ?? "Kode status HTTP {$httpCode}";
                $this->json(['success' => false, 'message' => "Gagal terhubung ke Gemini: {$errMsg}"], 400);
                return;
            } elseif ($provider === 'nous') {
                $useModel = !empty($model) ? $model : \App\Services\AI\NousProvider::DEFAULT_MODEL;
                $url = 'https://inference-api.nousresearch.com/v1/chat/completions';
                $payload = json_encode([
                    'model' => $useModel,
                    'messages' => [
                        ['role' => 'user', 'content' => 'Halo, uji koneksi. Balas OK.']
                    ],
                    'max_tokens' => 10,
                ]);

                $ch = curl_init($url);
                curl_setopt_array($ch, [
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_POST           => true,
                    CURLOPT_POSTFIELDS     => $payload,
                    CURLOPT_HTTPHEADER     => [
                        'Content-Type: application/json',
                        'Authorization: Bearer ' . $apiKey
                    ],
                    CURLOPT_TIMEOUT        => 10,
                    CURLOPT_CONNECTTIMEOUT => 5,
                    CURLOPT_SSL_VERIFYPEER => false,
                ]);

                $response = curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                $curlErr = curl_error($ch);
                curl_close($ch);

                if ($curlErr) {
                    $this->json(['success' => false, 'message' => 'Koneksi gagal (cURL error): ' . $curlErr], 500);
                    return;
                }

                if ($httpCode >= 200 && $httpCode < 300) {
                    $this->json(['success' => true, 'message' => "Koneksi ke Nous Research ({$useModel}) berhasil terhubung!"]);
                    return;
                }

                $json = json_decode((string) $response, true);
                $errMsg = $json['error']['message'] ?? "Kode status HTTP {$httpCode}";
                $this->json(['success' => false, 'message' => "Gagal terhubung ke Nous Research: {$errMsg}"], 400);
                return;
            } elseif ($provider === 'anthropic') {
                $useModel = !empty($model) ? $model : \App\Services\AI\AnthropicProvider::DEFAULT_MODEL;
                $url = 'https://api.anthropic.com/v1/messages';
                $payload = json_encode([
                    'model' => $useModel,
                    'max_tokens' => 10,
                    'messages' => [
                        ['role' => 'user', 'content' => 'Halo, uji koneksi. Balas OK.']
                    ]
                ]);

                $ch = curl_init($url);
                curl_setopt_array($ch, [
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_POST           => true,
                    CURLOPT_POSTFIELDS     => $payload,
                    CURLOPT_HTTPHEADER     => [
                        'Content-Type: application/json',
                        'x-api-key: ' . $apiKey,
                        'anthropic-version: 2023-06-01'
                    ],
                    CURLOPT_TIMEOUT        => 10,
                    CURLOPT_CONNECTTIMEOUT => 5,
                    CURLOPT_SSL_VERIFYPEER => false,
                ]);

                $response = curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                $curlErr = curl_error($ch);
                curl_close($ch);

                if ($curlErr) {
                    $this->json(['success' => false, 'message' => 'Koneksi gagal (cURL error): ' . $curlErr], 500);
                    return;
                }

                if ($httpCode >= 200 && $httpCode < 300) {
                    $this->json(['success' => true, 'message' => "Koneksi ke Anthropic ({$useModel}) berhasil terhubung!"]);
                    return;
                }

                $json = json_decode((string) $response, true);
                $errMsg = $json['error']['message'] ?? "Kode status HTTP {$httpCode}";
                $this->json(['success' => false, 'message' => "Gagal terhubung ke Anthropic: {$errMsg}"], 400);
                return;
            }

            $this->json(['success' => true, 'message' => "Konfigurasi provider '{$provider}' tersimpan."]);
        } catch (Throwable $e) {
            $this->json(['success' => false, 'message' => 'Kesalahan sistem: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Kirim Pesan Tes WhatsApp (Fonnte)
     * POST /admin/konfigurasi/test-wa
     */
    public function testWa(): void
    {
        if (!$this->authorizeAdmin()) {
            return;
        }

        $token = $_POST['_token'] ?? null;
        if (!Session::verifyCsrfToken($token)) {
            $this->json(['success' => false, 'message' => 'CSRF token tidak valid.'], 403);
            return;
        }

        $waToken = Setting::get('wa_token', true);
        $adminPhone = Setting::get('wa_admin_phone');

        if (empty($waToken)) {
            $this->json(['success' => false, 'message' => 'Token Fonnte belum diisi pada pengaturan.'], 400);
            return;
        }

        if (empty($adminPhone)) {
            $this->json(['success' => false, 'message' => 'Nomor WhatsApp Admin belum diisi.'], 400);
            return;
        }

        try {
            $message = "Ini adalah pesan uji coba dari Marketplace Laundry.\nKonfigurasi WhatsApp Fonnte berhasil terhubung!";
            $ch = curl_init('https://api.fonnte.com/send');
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST           => true,
                CURLOPT_POSTFIELDS     => [
                    'target'  => $adminPhone,
                    'message' => $message,
                ],
                CURLOPT_HTTPHEADER     => [
                    "Authorization: {$waToken}"
                ],
                CURLOPT_TIMEOUT        => 12,
                CURLOPT_CONNECTTIMEOUT => 6,
                CURLOPT_SSL_VERIFYPEER => false,
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlErr = curl_error($ch);
            curl_close($ch);

            if ($curlErr) {
                $this->json(['success' => false, 'message' => 'Koneksi ke Fonnte gagal: ' . $curlErr], 500);
                return;
            }

            $json = json_decode((string) $response, true);
            if (!empty($json['status']) && $json['status'] == true) {
                $this->json(['success' => true, 'message' => "Pesan tes WhatsApp berhasil dikirim ke {$adminPhone}!"]);
                return;
            }

            $reason = $json['reason'] ?? $json['message'] ?? "Kode respon Fonnte {$httpCode}";
            $this->json(['success' => false, 'message' => "Fonnte merespon: {$reason}"], 400);
        } catch (Throwable $e) {
            $this->json(['success' => false, 'message' => 'Kesalahan sistem: ' . $e->getMessage()], 500);
        }
    }
}
