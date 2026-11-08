<?php

namespace App\Controllers;

use App\Helpers\Response;
use App\Helpers\Session;
use App\Models\Order;
use App\Services\AIService;
use App\Services\AI\AIException;
use App\Services\AI\AILogger;
use Throwable;

/**
 * AI Assistant. Customer login: data order sendiri. Tamu/non-customer: hanya info publik.
 * Controller ini hanya meneruskan pertanyaan ke AIService; tidak ada perubahan data bisnis di sini.
 */
class AIController extends BaseController
{
    private const HISTORY_KEY = 'ai_chat_history';
    private const MAX_BODY_BYTES = 8192;

    /**
     * GET /customer/assistant[?order=ID]
     */
    public function chatView(): void
    {
        $user = Session::get('user');
        $registeredName = $this->getRegisteredName($user);

        $focusOrder = null;
        $orderId = (int) ($_GET['order'] ?? 0);
        if ($orderId > 0 && !empty($user['id'])) {
            try {
                $order = (new Order())->getOrderDetail($orderId, (int) $user['id']);
                if ($order) {
                    $focusOrder = ['id' => (int) $order['id'], 'order_number' => $order['order_number']];
                }
            } catch (Throwable $e) {
                $focusOrder = null;
            }
        }

        $service = new AIService();

        $this->render('assistant/chat', [
            'title'          => 'Asisten Customer AI - Marketplace Laundry',
            'hideAiWidget'   => true,
            'user'           => $user,
            'role'           => 'customer',
            'roleBadge'      => 'Customer',
            'registeredName' => $registeredName,
            'history'        => Session::get(self::HISTORY_KEY, []),
            'focusOrder'     => $focusOrder,
            'csrfToken'      => Session::csrfToken(),
            'isConfigured'   => $service->isConfigured(),
            'maxLength'      => 500,
            'welcomeMessage' => "Tanya status pesanan atau pembayaran Anda.",
            'suggestions'    => [
                'Pesanan saya sudah sampai mana?',
                'Minta QRIS pembayaran',
                'Apakah pembayaran saya sudah masuk?',
                'Berapa harga laundry kiloan?',
            ],
        ]);
    }

    /**
     * GET /owner/assistant
     */
    public function ownerChatView(): void
    {
        $user = Session::get('user');
        $registeredName = $this->getRegisteredName($user);
        $service = new AIService();

        $this->render('assistant/chat', [
            'title'          => 'Asisten Owner (Manajer Toko) AI - Marketplace Laundry',
            'hideAiWidget'   => true,
            'user'           => $user,
            'role'           => 'owner',
            'roleBadge'      => 'Owner / Mitra Toko',
            'registeredName' => $registeredName,
            'history'        => Session::get(self::HISTORY_KEY, []),
            'focusOrder'     => null,
            'csrfToken'      => Session::csrfToken(),
            'isConfigured'   => $service->isConfigured(),
            'maxLength'      => 500,
            'welcomeMessage' => "Tanya pendapatan, jumlah order, atau pesanan toko Anda.",
            'suggestions'    => [
                'Pendapatan hari ini',
                'Jumlah order hari ini',
                'Pesanan yang perlu ditimbang',
                'Pembayaran tunai menunggu konfirmasi',
            ],
        ]);
    }

    /**
     * GET /admin/assistant
     */
    public function adminChatView(): void
    {
        $user = Session::get('user');
        $registeredName = $this->getRegisteredName($user);
        $service = new AIService();

        $this->render('assistant/chat', [
            'title'          => 'Asisten Admin AI - Marketplace Laundry',
            'hideAiWidget'   => true,
            'user'           => $user,
            'role'           => 'admin',
            'roleBadge'      => 'Admin / Administrator',
            'registeredName' => $registeredName,
            'history'        => Session::get(self::HISTORY_KEY, []),
            'focusOrder'     => null,
            'csrfToken'      => Session::csrfToken(),
            'isConfigured'   => $service->isConfigured(),
            'maxLength'      => 500,
            'welcomeMessage' => "Halo Admin {$registeredName}! Tanya seputar verifikasi berkas, pengguna, atau fitur Admin Console.",
            'suggestions'    => [
                'Berapa customer?',
                'Berapa mitra?',
                'Berapa kurir?',
                'Total pengguna',
                'Siapa saja customer?',
                'Siapa saya?',
                'Verifikasi menunggu',
            ],
        ]);
    }

    /**
     * GET /driver/assistant
     */
    public function driverChatView(): void
    {
        $user = Session::get('user');
        $registeredName = $this->getRegisteredName($user);
        $service = new AIService();

        $this->render('assistant/chat', [
            'title'          => 'Asisten Kurir (Driver) AI - Marketplace Laundry',
            'hideAiWidget'   => true,
            'user'           => $user,
            'role'           => 'driver',
            'roleBadge'      => 'Kurir / Driver',
            'registeredName' => $registeredName,
            'history'        => Session::get(self::HISTORY_KEY, []),
            'focusOrder'     => null,
            'csrfToken'      => Session::csrfToken(),
            'isConfigured'   => $service->isConfigured(),
            'maxLength'      => 500,
            'welcomeMessage' => "Tanya tugas, alamat, atau penghasilan Anda.",
            'suggestions'    => [
                'Penghasilan saya hari ini',
                'Total pengantaran saya',
                'Tugas saya hari ini',
            ],
        ]);
    }

    /**
     * POST /assistant/message  (JSON, publik; mode dan role ditentukan dari session di server)
     */
    public function message(): void
    {
        $this->prepareJsonEndpoint();

        if (!$this->csrfIsValid()) {
            $this->respond(['success' => false, 'code' => 'csrf', 'message' => 'Sesi keamanan tidak valid. Muat ulang halaman lalu coba lagi.'], 419);
        }

        // WAJIB: Ambil role dari session server (abaikan apapun dari request body)
        $user = Session::get('user');
        $rawRole = is_array($user) ? strtolower(trim((string) ($user['role'] ?? ''))) : '';
        $canonicalRole = match ($rawRole) {
            'admin'               => 'admin',
            'owner', 'mitra'      => 'owner',
            'driver', 'kurir'     => 'driver',
            'customer', 'pelanggan' => 'customer',
            default               => 'tamu'
        };

        $raw = file_get_contents('php://input', false, null, 0, self::MAX_BODY_BYTES + 1);
        if ($raw === false || strlen($raw) > self::MAX_BODY_BYTES) {
            $this->respond(['success' => false, 'code' => 'payload_too_large', 'message' => 'Permintaan terlalu besar.'], 413);
        }

        $body = json_decode($raw, true);
        if (!is_array($body) || !isset($body['message']) || !is_string($body['message'])) {
            $this->respond(['success' => false, 'code' => 'bad_request', 'message' => 'Permintaan tidak valid.'], 400);
        }

        $focusOrderId = isset($body['order_id']) && is_numeric($body['order_id']) && (int) $body['order_id'] > 0
            ? (int) $body['order_id']
            : null;

        $userId = is_array($user) && isset($user['id']) ? (int) $user['id'] : 0;
        $registeredName = $this->getRegisteredName($user);

        $history = Session::get(self::HISTORY_KEY, []);
        $history = is_array($history) ? $history : [];
        $clientIp = (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown');

        // Lepas session lock selama menunggu API AI agar request lain tidak tertahan.
        session_write_close();

        try {
            $service = new AIService();
            $result = match ($canonicalRole) {
                'admin'    => $service->chatAsAdmin($userId, $registeredName, $body['message'], $history),
                'owner'    => $service->chatAsOwner($userId, $registeredName, $body['message'], $history),
                'driver'   => $service->chatAsDriver($userId, $registeredName, $body['message'], $history, $focusOrderId),
                'customer' => $service->chat($userId, $registeredName, $body['message'], $history, $focusOrderId),
                default    => $service->chatAsGuest($body['message'], $history, $clientIp)
            };
        } catch (AIException $e) {
            $payload = ['success' => false, 'code' => $e->errorCode(), 'message' => $e->getMessage()];
            if ($e->retryAfter() !== null) {
                $payload['retry_after'] = $e->retryAfter();
                header('Retry-After: ' . $e->retryAfter());
            }
            $this->respond($payload, $e->httpStatus());
        } catch (Throwable $e) {
            AILogger::error('unexpected_error', [
                'user_id' => $userId,
                'role'    => $canonicalRole,
                'type'    => get_class($e),
                'error'   => $e->getMessage()
            ]);
            $this->respond(['success' => false, 'code' => 'internal_error', 'message' => 'Terjadi kesalahan pada asisten. Silakan coba lagi nanti.'], 500);
        }

        // Buka lagi session untuk menyimpan riwayat (hanya teks percakapan).
        Session::start();
        $stored = $history;
        $stored[] = ['role' => 'user', 'content' => mb_substr($body['message'], 0, 500)];
        $stored[] = ['role' => 'assistant', 'content' => $result['reply']];
        Session::set(self::HISTORY_KEY, array_slice($stored, -12));

        // Catat interaksi tanya-jawab AI ke database agar Admin dapat memantau riwayat chat AI dari semua peran
        \App\Models\AiChatMessage::recordMessage(
            $userId > 0 ? $userId : null,
            !empty($registeredName) ? $registeredName : (is_array($user) ? ($user['name'] ?? 'Tamu') : 'Tamu'),
            is_array($user) ? ($user['email'] ?? null) : null,
            $canonicalRole,
            $focusOrderId,
            $body['message'],
            $result['reply']
        );

        $resPayload = [
            'success' => true,
            'reply'   => $result['reply'],
        ];
        if (!empty($result['attachment'])) {
            $resPayload['attachment'] = $result['attachment'];
        }

        $this->respond($resPayload);
    }

    /**
     * Ambil nama terdaftar user dari database tabel users (bukan dari input chat).
     */
    private function getRegisteredName(?array $userSession): string
    {
        if (!is_array($userSession) || empty($userSession['id'])) {
            return '';
        }

        try {
            $userDb = (new \App\Models\User())->find((int) $userSession['id']);
            if ($userDb && !empty($userDb['name'])) {
                return (string) $userDb['name'];
            }
        } catch (Throwable $e) {
            // fallback ke nama di session jika DB query error
        }

        return (string) ($userSession['name'] ?? 'Pengguna');
    }

    /**
     * POST /assistant/reset  (JSON, publik)
     */
    public function reset(): void
    {
        $this->prepareJsonEndpoint();

        if (!$this->csrfIsValid()) {
            $this->respond(['success' => false, 'code' => 'csrf', 'message' => 'Sesi keamanan tidak valid. Muat ulang halaman lalu coba lagi.'], 419);
        }

        Session::remove(self::HISTORY_KEY);
        $this->respond(['success' => true]);
    }

    // ------------------------------------------------------------------

    /**
     * Pastikan warning/notice PHP (APP_DEBUG=true) tidak merusak output JSON.
     */
    private function prepareJsonEndpoint(): void
    {
        ini_set('display_errors', '0');
        ob_start();
    }

    private function csrfIsValid(): bool
    {
        return Session::verifyCsrfToken($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null);
    }

    private function respond(array $data, int $status = 200): void
    {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        Response::json($data, $status);
    }
}
