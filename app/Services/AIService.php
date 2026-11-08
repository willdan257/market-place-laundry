<?php

namespace App\Services;

use App\Services\AI\AIContextBuilder;
use App\Services\AI\AIException;
use App\Services\AI\AILogger;
use App\Services\AI\AIPromptBuilder;
use App\Services\AI\AIProviderInterface;
use App\Services\AI\AIRateLimiter;
use App\Services\AI\AnthropicProvider;
use App\Services\AI\GeminiProvider;

/**
 * AI Customer Assistant.
 *
 * Alur setiap pertanyaan (mode customer login atau tamu):
 *   validasi -> rate limit -> ambil data dari DB (customer: terotorisasi, tamu: publik saja) -> prompt terbatas
 *   -> panggil provider AI -> guard output -> jawaban.
 *
 * AIService BERSIFAT READ-ONLY: tidak ada kode di sini yang menulis ke tabel bisnis
 * (order, harga, pembayaran). Semua perubahan bisnis tetap dilakukan backend lewat
 * Controller/Service/Model masing-masing.
 */
class AIService
{
    private const SAFE_FALLBACK = 'Informasi tidak dapat ditampilkan saat ini. Silakan periksa menu terkait.';

    private array $config;
    private string $appName;
    private ?AIProviderInterface $provider;
    private AIContextBuilder $contextBuilder;
    private AIRateLimiter $rateLimiter;
    private AIRateLimiter $guestRateLimiter;

    public function __construct(
        ?array $config = null,
        ?AIProviderInterface $provider = null,
        ?AIContextBuilder $contextBuilder = null,
        ?AIRateLimiter $rateLimiter = null,
        ?AIRateLimiter $guestRateLimiter = null
    ) {
        $root = dirname(__DIR__, 2);
        $this->config = $config ?? require $root . '/config/ai.php';
        $appConfig = require $root . '/config/app.php';
        $this->appName = (string) ($appConfig['name'] ?? 'Marketplace Laundry');

        $this->provider = $provider;
        $this->contextBuilder = $contextBuilder ?? new AIContextBuilder($this->config, $appConfig);
        $this->rateLimiter = $rateLimiter ?? new AIRateLimiter(
            (int) $this->config['rate_limit']['per_minute'],
            (int) $this->config['rate_limit']['per_day']
        );
        $guestLimit = $this->config['guest_rate_limit'] ?? ['per_minute' => 5, 'per_day' => 30];
        $this->guestRateLimiter = $guestRateLimiter ?? new AIRateLimiter(
            (int) $guestLimit['per_minute'],
            (int) $guestLimit['per_day']
        );
    }

    public function isConfigured(): bool
    {
        if (isset($this->config['enabled']) && !$this->config['enabled']) {
            return false;
        }
        $key = $this->config['api_key'] ?? '';
        return $key !== '' && !str_starts_with(strtolower($key), 'your_');
    }

    /**
     * Mode CUSTOMER LOGIN: data order & pembayaran milik customer ini.
     *
     * @param int      $customerId      ID customer dari SESSION
     * @param string   $registeredName  Nama terdaftar dari users.name
     * @param string   $message         Pertanyaan pelanggan
     * @param array    $history         Percakapan sebelumnya
     * @param int|null $focusOrderId    Order fokus di UI
     * @return array{reply: string, attachment?: array|null}
     * @throws AIException
     */
    public function chat(int $customerId, string $registeredName, string $message, array $history = [], ?int $focusOrderId = null): array
    {
        return $this->process(
            'customer',
            $message,
            $history,
            $this->rateLimiter,
            'user_' . $customerId,
            $customerId,
            fn(string $msg) => $this->contextBuilder->build($customerId, $registeredName, $msg, $focusOrderId),
            fn(array $ctx) => $this->resolveCustomerIntent($customerId, $registeredName, $message, $focusOrderId, $ctx)
        );
    }

    /**
     * Mode OWNER LOGIN: manajer toko laundry (pesanan toko, kurir, layanan, pembayaran, analitik).
     *
     * @param int    $ownerUserId    ID user owner dari SESSION
     * @param string $registeredName Nama terdaftar dari users.name
     * @param string $message        Pertanyaan owner
     * @param array  $history        Percakapan sebelumnya
     * @return array{reply: string, attachment?: array|null}
     * @throws AIException
     */
    public function chatAsOwner(int $ownerUserId, string $registeredName, string $message, array $history = []): array
    {
        return $this->process(
            'owner',
            $message,
            $history,
            $this->rateLimiter,
            'owner_' . $ownerUserId,
            $ownerUserId,
            fn(string $msg) => $this->contextBuilder->buildOwner($ownerUserId, $registeredName, $msg)
        );
    }

    /**
     * Mode ADMIN LOGIN: verifikasi mitra & kurir, manajemen pengguna, dokumen legalitas.
     *
     * @param int    $adminUserId    ID user admin dari SESSION
     * @param string $registeredName Nama terdaftar dari users.name
     * @param string $message        Pertanyaan admin
     * @param array  $history        Percakapan sebelumnya
     * @return array{reply: string, attachment?: array|null}
     * @throws AIException
     */
    public function chatAsAdmin(int $adminUserId, string $registeredName, string $message, array $history = []): array
    {
        return $this->process(
            'admin',
            $message,
            $history,
            $this->rateLimiter,
            'admin_' . $adminUserId,
            $adminUserId,
            fn(string $msg) => $this->contextBuilder->buildAdmin($adminUserId, $registeredName, $msg)
        );
    }

    /**
     * Mode DRIVER (KURIR) LOGIN: tugas penjemputan & pengantaran yang di-assign ke driver ini.
     *
     * @param int      $driverUserId   ID user kurir dari SESSION
     * @param string   $registeredName Nama terdaftar dari users.name
     * @param string   $message        Pertanyaan kurir
     * @param array    $history        Percakapan sebelumnya
     * @param int|null $focusOrderId   Order fokus di UI
     * @return array{reply: string, attachment?: array|null}
     * @throws AIException
     */
    public function chatAsDriver(int $driverUserId, string $registeredName, string $message, array $history = [], ?int $focusOrderId = null): array
    {
        return $this->process(
            'driver',
            $message,
            $history,
            $this->rateLimiter,
            'driver_' . $driverUserId,
            $driverUserId,
            fn(string $msg) => $this->contextBuilder->buildDriver($driverUserId, $registeredName, $msg, $focusOrderId),
            fn(array $ctx) => $this->resolveMapsAttachment($driverUserId, $registeredName, $message, $focusOrderId, $ctx)
        );
    }

    /**
     * Mode TAMU (belum login): hanya informasi publik (layanan, harga, ongkir, FAQ).
     *
     * @return array{reply: string, attachment?: array|null}
     * @throws AIException
     */
    public function chatAsGuest(string $message, array $history, string $clientIp): array
    {
        return $this->process(
            'tamu',
            $message,
            $history,
            $this->guestRateLimiter,
            'guest_' . sha1($clientIp),
            0,
            fn(string $msg) => $this->contextBuilder->buildGuest()
        );
    }

    /**
     * Pipeline bersama: validasi -> rate limit -> konteks -> cek attachment terstruktur -> AI -> guard output.
     */
    private function process(
        string $role,
        string $message,
        array $history,
        AIRateLimiter $limiter,
        string $rateKey,
        int $logUserId,
        callable $buildContext,
        ?callable $attachmentResolver = null
    ): array {
        // 1. Validasi input
        $message = $this->normalizeMessage($message);
        if ($message === '') {
            throw new AIException('Silakan tulis pertanyaan Anda terlebih dahulu.', 422, 'empty_message');
        }
        $max = (int) $this->config['max_message_length'];
        if (mb_strlen($message) > $max) {
            throw new AIException("Pertanyaan terlalu panjang. Maksimal {$max} karakter.", 422, 'message_too_long');
        }

        // 2. Pastikan AI sudah dikonfigurasi (sebelum menghabiskan kuota rate limit)
        if (!$this->isConfigured()) {
            throw new AIException('Chatbot belum dikonfigurasi. Silakan hubungi Administrator untuk melengkapi pengaturan AI.', 503, 'ai_not_configured');
        }

        // 3. Rate limiting
        $limiter->hit($rateKey);

        // 4. Ambil data terotorisasi SEBELUM memanggil AI
        $built = $buildContext($message);

        // 5. Cek fitur terstruktur backend (QRIS untuk customer, Maps untuk kurir)
        $resolved = $attachmentResolver ? $attachmentResolver($built['context']) : null;
        if ($resolved !== null && !empty($resolved['reply'])) {
            return [
                'reply'      => $this->guardOutput($resolved['reply'], $built['order_numbers'], $message, $logUserId),
                'attachment' => $resolved['attachment'] ?? null,
            ];
        }

        // 6. Susun pesan prompt: riwayat singkat + giliran saat ini (konteks segar + pertanyaan)
        $messages = $this->sanitizeHistory($history);
        $messages[] = [
            'role'    => 'user',
            'content' => AIPromptBuilder::userTurn($built['context'], $message),
        ];

        // 7. Panggil provider AI (dengan fallback cerdas bila upstream bermasalah)
        $rawReply = '';
        if ($this->isConfigured()) {
            try {
                $rawReply = $this->provider()->generate(
                    AIPromptBuilder::systemPrompt($role, $this->appName),
                    $messages,
                    (int) $this->config['max_output_tokens']
                );
            } catch (AIException $e) {
                AILogger::error('provider_failed_using_smart_fallback', ['message' => $e->getMessage()]);
                $rawReply = $this->generateFallbackReply($message, $built['context']);
            }
        } else {
            $rawReply = $this->generateFallbackReply($message, $built['context']);
        }

        // 8. Guard output
        $reply = $this->guardOutput($rawReply, $built['order_numbers'], $message, $logUserId);

        return [
            'reply'      => $reply,
            'attachment' => $resolved['attachment'] ?? null,
        ];
    }

    // ------------------------------------------------------------------

    private function provider(): AIProviderInterface
    {
        if ($this->provider !== null) {
            return $this->provider;
        }

        $args = [
            $this->config['api_key'],
            $this->config['model'],
            (int) $this->config['timeout'],
            (int) $this->config['connect_timeout'],
            $this->config['ca_bundle'],
        ];

        switch ($this->config['provider']) {
            case 'nous':
                return $this->provider = new \App\Services\AI\NousProvider(...$args);
            case 'anthropic':
                return $this->provider = new AnthropicProvider(...$args);
            case 'gemini':
                return $this->provider = new GeminiProvider(...$args);
            default:
                AILogger::error('unknown_provider', ['provider' => $this->config['provider']]);
                throw new AIException('Chatbot belum dikonfigurasi dengan benar. Provider tidak dikenali.', 503, 'ai_provider_unknown');
        }
    }

    private function normalizeMessage(string $message): string
    {
        // Buang karakter kontrol (kecuali newline/tab) lalu rapikan spasi.
        $message = preg_replace('/[^\P{C}\n\t]/u', '', $message) ?? '';
        $message = preg_replace("/[ \t]+/u", ' ', $message) ?? '';
        $message = preg_replace("/\n{3,}/", "\n\n", $message) ?? '';
        return trim($message);
    }

    /**
     * Riwayat hanya berisi teks percakapan (bukan konteks data), maksimal N pesan terakhir,
     * dimulai dari 'user' dan bergantian role. Data pesanan selalu diambil ulang dari DB tiap giliran.
     */
    private function sanitizeHistory(array $history): array
    {
        $clean = [];
        foreach ($history as $item) {
            if (!is_array($item) || !isset($item['role'], $item['content']) || !is_string($item['content'])) {
                continue;
            }
            if (!in_array($item['role'], ['user', 'assistant'], true)) {
                continue;
            }
            $clean[] = [
                'role'    => $item['role'],
                'content' => str_replace(['<', '>'], '', mb_substr($item['content'], 0, 1500)),
            ];
        }

        $clean = array_slice($clean, -max(0, (int) $this->config['history_messages']));

        // Wajib diawali 'user'
        while (!empty($clean) && $clean[0]['role'] !== 'user') {
            array_shift($clean);
        }

        // Wajib bergantian role
        $alternating = [];
        foreach ($clean as $item) {
            $last = end($alternating);
            if ($last !== false && $last['role'] === $item['role']) {
                continue;
            }
            $alternating[] = $item;
        }

        // Giliran saat ini adalah 'user', jadi riwayat harus berakhir di 'assistant'
        if (!empty($alternating) && end($alternating)['role'] === 'user') {
            array_pop($alternating);
        }

        return $alternating;
    }

    /**
     * Lapisan pengaman terakhir di backend (tidak bergantung pada kepatuhan model):
     * nomor order pada jawaban hanya boleh yang ada di konteks customer ini
     * atau yang diketik sendiri oleh customer.
     */
    private function guardOutput(string $reply, array $allowedOrderNumbers, string $question, int $customerId): string
    {
        $reply = trim($reply);
        if (mb_strlen($reply) > 2000) {
            $reply = mb_substr($reply, 0, 2000) . '...';
        }

        $allowed = array_map('strtoupper', $allowedOrderNumbers);
        if (preg_match_all('/ORD-\d{8}-[A-F0-9]{4}/i', $question, $m)) {
            foreach ($m[0] as $n) {
                $allowed[] = strtoupper($n);
            }
        }

        if (preg_match_all('/ORD-\d{8}-[A-F0-9]{4}/i', $reply, $m)) {
            foreach ($m[0] as $n) {
                if (!in_array(strtoupper($n), $allowed, true)) {
                    AILogger::error('guard_blocked_unauthorized_order_number', ['user_id' => $customerId]);
                    return self::SAFE_FALLBACK;
                }
            }
        }

        return $reply;
    }

    /**
     * Resolusi intent terstruktur customer (QRIS, hitung pesanan, kapan bayar).
     */
    private function resolveCustomerIntent(int $customerId, string $registeredName, string $message, ?int $focusOrderId, array $context): ?array
    {
        // 1. Cek permintaan QRIS
        $qrisResult = $this->resolveQrisAttachment($customerId, $registeredName, $message, $focusOrderId, $context);
        if ($qrisResult !== null) {
            return $qrisResult;
        }

        $text = mb_strtolower(trim($message));
        // Normalisasi kata kunci typo
        $nText = str_replace(
            ['pesenan', 'psnan', 'orderan', 'order', 'cucian', 'brp', 'brapa', 'slsai', 'selse', 'beres', 'kelar', 'kpn', 'sy', 'dicuci'],
            ['pesanan', 'pesanan', 'pesanan', 'pesanan', 'pesanan', 'berapa', 'berapa', 'selesai', 'selesai', 'selesai', 'selesai', 'kapan', 'saya', 'diproses'],
            $text
        );
        $nText = preg_replace('/\s+/', ' ', $nText);

        // 2. Intent: Cara Order / Mau Order Baru (Prioritas Utama agar tidak tertukar dengan status)
        if (preg_match('/(cara\s*(order|pesan|laundry)|mau\s*(order|pesan|nyuci|laundry)|gimana\s*(cara\s*)?(saya\s*)?(order|pesan)|bagaimana\s*(cara\s*)?(saya\s*)?(order|pesan)|alur\s*(order|pesan)|langkah\s*(order|pesan)|order\s*baru|pesan\s*baru|buat\s*(order|pesanan)|bikin\s*(order|pesanan))/i', $text)
            || preg_match('/^(order|pesan|mau order|mau pesan|gimana order|order gimana|saya mau order gimana\??)$/i', $text)) {
            return [
                'reply' => "Berikut langkah-langkah mudah membuat pesanan laundry:\n\n"
                         . "1. Buka menu **Layanan** di bilah navigasi atas atau pilih toko laundry mitra di halaman Beranda.\n"
                         . "2. Pilih jenis layanan yang Anda butuhkan (Cuci Kiloan Reguler, Satuan, atau Express).\n"
                         . "3. Tentukan alamat penjemputan pakaian Anda.\n"
                         . "4. Klik tombol **Buat Pesanan**.\n"
                         . "5. Kurir armada akan datang menjemput pakaian kotor ke lokasi Anda.\n"
                         . "6. Setelah pakaian ditimbang di toko, Anda dapat membayar via QRIS otomatis atau Tunai (COD).\n"
                         . "7. Pakaian dicuci bersih, wangi, rapi, dan diantarkan kembali ke alamat Anda!\n\n"
                         . "Silakan klik menu **Layanan** atau buka Beranda untuk mulai memesan sekarang.",
                'attachment' => null,
            ];
        }

        // 3. Intent: Kapan bayar? ("kapan saya harus bayar", "kapan bayar", "kapan bayarnya", dsb.)
        if (preg_match('/(kapan|kpn)\s*(saya|sy|harus|mesti|perlu)?\s*(harus|mesti|perlu)?\s*bayar|(kapan|kpn)\s*bayarnya|bayar\s*kapan|(kapan|kpn)\s*(pembayaran|bayar\s*pesanan)/i', $text)
            || (preg_match('/(kapan|kpn)/i', $text) && preg_match('/(bayar|tagihan|lunas)/i', $text))) {
            return $this->resolvePaymentSchedule($customerId, $registeredName);
        }

        // 3. Intent: Berapa pesanan selesai?
        if (preg_match('/(berapa|jumlah|total)?\s*(pesanan|order)?\s*(yang\s*)?(sudah\s*)?selesai/i', $nText)
            && !preg_match('/(aktif|diproses)/i', $nText)) {
            $counts = $this->resolveCustomerOrderCounts($customerId);
            return [
                'reply'      => "Pesanan selesai Anda ada **{$counts['completed']}**.",
                'attachment' => null,
            ];
        }

        // 4. Intent: Berapa pesanan aktif / diproses?
        if (preg_match('/(berapa|jumlah|total)?\s*(pesanan|order)?\s*(yang\s*)?(sedang\s*)?(aktif|diproses|proses|pending|berjalan)/i', $nText)
            && !preg_match('/selesai/i', $nText)) {
            $counts = $this->resolveCustomerOrderCounts($customerId);
            return [
                'reply'      => "Pesanan aktif/diproses Anda ada **{$counts['active']}**.",
                'attachment' => null,
            ];
        }

        // 5. Intent: Pesanan terakhir
        if (preg_match('/pesanan\s*(terakhir|terbaru|paling\s*baru|last)/i', $nText)) {
            $counts = $this->resolveCustomerOrderCounts($customerId);
            if (empty($counts['all'])) {
                return [
                    'reply'      => "Anda belum memiliki pesanan di akun Anda.",
                    'attachment' => null,
                ];
            }
            $latest = $counts['all'][0];
            $badge = \App\Helpers\OrderStatusHelper::getBadge($latest['order_status']);
            $statusLabel = $badge[1] ?? $latest['order_status'];
            $statusPhrase = in_array($latest['order_status'], ['delivered', 'completed'], true) ? "sudah selesai" : "sedang " . strtolower($statusLabel);
            return [
                'reply'      => "Pesanan terakhir Anda adalah **#{$latest['order_number']}** ({$latest['store_name']}), status {$statusPhrase}.",
                'attachment' => null,
            ];
        }

        // 6. Intent: Berapa total pesanan / pesanan saya
        if (preg_match('/(berapa|jumlah)\s*(total|semua)?\s*pesanan/i', $nText)
            || preg_match('/^(total\s*pesanan|pesanan\s*saya|daftar\s*pesanan|semua\s*pesanan)$/i', $nText)) {
            $counts = $this->resolveCustomerOrderCounts($customerId);
            return [
                'reply'      => "Total pesanan Anda {$counts['total']}: {$counts['active']} aktif, {$counts['completed']} selesai.",
                'attachment' => null,
            ];
        }

        return null;
    }

    /**
     * Hitung pesanan customer (selaras 100% dengan Dashboard Pelanggan).
     */
    private function resolveCustomerOrderCounts(int $customerId): array
    {
        $orderModel = new \App\Models\Order();
        $allOrders = $orderModel->getCustomerOrders($customerId);
        $activeOrders = $orderModel->getActiveCustomerOrders($customerId);

        $totalCount = count($allOrders);
        $pendingCount = count($activeOrders);
        $completedCount = count(array_filter($allOrders, fn($o) => in_array($o['order_status'], ['delivered', 'completed'], true)));

        return [
            'total'         => $totalCount,
            'active'        => $pendingCount,
            'completed'     => $completedCount,
            'all'           => $allOrders,
            'active_orders' => $activeOrders
        ];
    }

    /**
     * Jawaban jadwal/instruksi pembayaran "Kapan saya harus bayar?".
     */
    private function resolvePaymentSchedule(int $customerId, string $registeredName): array
    {
        $pdo = \App\Models\BaseModel::getPdo();
        if (!$pdo) {
            return [
                'reply'      => 'Koneksi database tidak tersedia. Silakan coba kembali nanti.',
                'attachment' => null,
            ];
        }

        $stmt = $pdo->prepare("
            SELECT o.id, o.order_number, o.order_status, o.total_amount,
                   p.payment_method, p.payment_status, p.amount as payment_amount
            FROM laundry_orders o
            LEFT JOIN payments p ON p.order_id = o.id
            WHERE o.customer_id = :cid AND o.order_status != 'cancelled'
            ORDER BY o.id DESC
        ");
        $stmt->execute(['cid' => $customerId]);
        $orders = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        if (empty($orders)) {
            return [
                'reply'      => "Halo {$registeredName}, Anda belum memiliki pesanan aktif di akun Anda.",
                'attachment' => null,
            ];
        }

        // Filter pesanan yang belum lunas
        $unpaid = array_values(array_filter($orders, fn($o) => ($o['payment_status'] ?? '') !== 'paid'));

        // Jika semua pesanan sudah lunas:
        if (empty($unpaid)) {
            $latest = $orders[0];
            return [
                'reply'      => "Pesanan {$latest['order_number']} sudah lunas.",
                'attachment' => null,
            ];
        }

        // Jika hanya ada 1 pesanan yang belum lunas:
        if (count($unpaid) === 1) {
            $o = $unpaid[0];
            $amountVal = (float) ($o['payment_amount'] ?? $o['total_amount']);
            $totalFmt = 'Rp ' . number_format($amountVal, 0, ',', '.');
            $method = strtolower($o['payment_method'] ?? 'cash');

            if ($method === 'cash') {
                return [
                    'reply'      => "Bayar tunai ke kurir saat pesanan tiba, siapkan {$totalFmt}.",
                    'attachment' => null,
                ];
            } else {
                return [
                    'reply'      => "Pesanan {$o['order_number']} ({$totalFmt}) belum dibayar. Silakan lakukan pembayaran di [Halaman Pembayaran](/payment/{$o['id']}).",
                    'attachment' => null,
                ];
            }
        }

        // Jika ada beberapa pesanan, rangkum yang belum lunas saja:
        $lines = ["Berikut rangkuman pembayaran untuk pesanan Anda yang belum lunas:"];
        foreach ($unpaid as $o) {
            $amountVal = (float) ($o['payment_amount'] ?? $o['total_amount']);
            $totalFmt = 'Rp ' . number_format($amountVal, 0, ',', '.');
            $method = strtolower($o['payment_method'] ?? 'cash');

            if ($method === 'cash') {
                $lines[] = "• Pesanan {$o['order_number']} (COD): Bayar tunai ke kurir saat pesanan tiba, siapkan {$totalFmt}.";
            } else {
                $lines[] = "• Pesanan {$o['order_number']} ({$totalFmt}): Silakan lakukan pembayaran di [Halaman Pembayaran](/payment/{$o['id']}).";
            }
        }

        return [
            'reply'      => implode("\n", $lines),
            'attachment' => null,
        ];
    }

    /**
     * Resolusi fitur QRIS di backend (bukan diserahkan ke AI).
     * Syarat:
     *  1. Pesanan milik customer yang login.
     *  2. Pembayaran berstatus 'pending' dan belum expired.
     *  3. Jika belum ada payment QRIS & order berstatus 'weighed'/'waiting_payment', panggil PaymentService::createQrisPayment().
     *  4. Jika sudah 'paid', tolak QRIS & beri penjelasan. Jika 'expired', arahkan bayar baru. Jika 'cash', jelaskan tunggu konfirmasi.
     */
    private function resolveQrisAttachment(int $customerId, string $registeredName, string $message, ?int $focusOrderId, array $context): ?array
    {
        $isQrisRequest = (bool) preg_match('/(\bqris\b|barcode|qr\s*code|bayar\s*pakai\s*qris|minta\s*qris|link\s*qris|kode\s*qris|minta\s*barcode)/i', $message);
        if (!$isQrisRequest) {
            return null;
        }

        $pdo = \App\Models\BaseModel::getPdo();
        if (!$pdo) {
            return null;
        }

        // 1. Tentukan target order
        $targetOrderNumber = null;
        if (preg_match('/ORD-\d{8}-[A-F0-9]{4}/i', $message, $m)) {
            $targetOrderNumber = strtoupper($m[0]);
        }

        $order = null;
        if ($targetOrderNumber !== null) {
            $stmt = $pdo->prepare("SELECT * FROM laundry_orders WHERE customer_id = :cid AND order_number = :num LIMIT 1");
            $stmt->execute(['cid' => $customerId, 'num' => $targetOrderNumber]);
            $order = $stmt->fetch(\PDO::FETCH_ASSOC);
        } elseif ($focusOrderId !== null) {
            $stmt = $pdo->prepare("SELECT * FROM laundry_orders WHERE customer_id = :cid AND id = :id LIMIT 1");
            $stmt->execute(['cid' => $customerId, 'id' => $focusOrderId]);
            $order = $stmt->fetch(\PDO::FETCH_ASSOC);
        } else {
            // Cari order terbaru milik customer yang belum selesai / dibayar
            $stmt = $pdo->prepare("
                SELECT o.* FROM laundry_orders o
                LEFT JOIN payments p ON p.order_id = o.id
                WHERE o.customer_id = :cid AND o.order_status NOT IN ('cancelled', 'completed')
                ORDER BY (p.payment_status = 'pending') DESC, o.id DESC
                LIMIT 1
            ");
            $stmt->execute(['cid' => $customerId]);
            $order = $stmt->fetch(\PDO::FETCH_ASSOC);
        }

        if (!$order) {
            return [
                'attachment' => null,
                'reply'      => "Halo {$registeredName}, saya tidak menemukan pesanan yang dimaksud pada akun Anda. Pastikan Anda telah memesan laundry dan memilih nomor pesanan yang sesuai."
            ];
        }

        $orderId = (int) $order['id'];
        $orderNumber = $order['order_number'];

        // 2. Cek payment record
        $paymentModel = new \App\Models\Payment();
        $payment = $paymentModel->findByOrderId($orderId);

        // Kasus: Sudah lunas
        if ($payment && $payment['payment_status'] === 'paid') {
            return [
                'attachment' => null,
                'reply'      => "Halo {$registeredName}, pesanan **#{$orderNumber}** sudah berstatus **LUNAS**. Anda tidak perlu melakukan pembayaran lagi! Cucian Anda sedang atau telah selesai diproses."
            ];
        }

        // Kasus: Metode tunai (cash)
        if ($payment && $payment['payment_method'] === 'cash') {
            $amt = $this->money($payment['amount'] ?? $order['total_amount']);
            return [
                'attachment' => null,
                'reply'      => "Halo {$registeredName}, pesanan **#{$orderNumber}** menggunakan metode pembayaran **Tunai (Cash)** sebesar **{$amt}**. Pembayaran ini sedang menunggu konfirmasi penerimaan tunai dari pemilik toko laundry saat pakaian diserahkan."
            ];
        }

        // Kasus: Kedaluwarsa (expired)
        if ($payment && $payment['payment_status'] === 'expired') {
            return [
                'attachment' => null,
                'reply'      => "Halo {$registeredName}, pembayaran QRIS untuk pesanan **#{$orderNumber}** telah **kedaluwarsa** karena melewati batas waktu 60 menit. Silakan buka halaman pembayaran untuk membuat transaksi pembayaran baru."
            ];
        }

        // Kasus: Belum ada record payment
        if (!$payment) {
            // Cek apakah order sudah boleh dibayar (sudah ditimbang)
            if (!in_array($order['order_status'], ['weighed', 'waiting_payment'], true) || (float) $order['total_amount'] <= 0) {
                $statusLabel = \App\Services\AI\AIContextBuilder::ORDER_STATUS[$order['order_status']][0] ?? $order['order_status'];
                return [
                    'attachment' => null,
                    'reply'      => "Halo {$registeredName}, kode QRIS untuk pesanan **#{$orderNumber}** belum dapat diterbitkan karena pakaian Anda belum selesai ditimbang oleh pihak toko laundry (status saat ini: **{$statusLabel}**). Total tagihan final akan terbit setelah penimbangan selesai."
                ];
            }

            // Buat payment QRIS via PaymentService (idempotent)
            try {
                $userModel = new \App\Models\User();
                $userRow = $userModel->find($customerId);
                $paymentService = new \App\Services\PaymentService();
                $payment = $paymentService->createQrisPayment($orderId, [
                    'name'  => $userRow['name'] ?? $registeredName,
                    'email' => $userRow['email'] ?? 'customer@marketplacelaundry.id',
                    'phone' => $userRow['phone'] ?? ''
                ]);
            } catch (\Throwable $e) {
                AILogger::error('ai_qris_creation_failed', ['order_id' => $orderId, 'error' => $e->getMessage()]);
                return [
                    'attachment' => null,
                    'reply'      => "Halo {$registeredName}, mohon maaf terjadi kendala saat membuat pembayaran QRIS untuk pesanan **#{$orderNumber}**. Silakan buka halaman pembayaran untuk mencoba lagi."
                ];
            }
        }

        // Kasus: Payment pending & belum expired
        if ($payment && $payment['payment_status'] === 'pending') {
            if (!empty($payment['expired_at']) && strtotime($payment['expired_at']) <= time()) {
                return [
                    'attachment' => null,
                    'reply'      => "Halo {$registeredName}, pembayaran QRIS untuk pesanan **#{$orderNumber}** telah kedaluwarsa. Silakan buka halaman pembayaran untuk membuat transaksi baru."
                ];
            }

            $amountFormatted = $this->money($payment['amount'] ?? $order['total_amount']);
            $attachment = [
                'type'         => 'qris',
                'order_number' => $orderNumber,
                'amount'       => $amountFormatted,
                'qris_url'     => $payment['qris_url'] ?? '',
                'expired_at'   => $payment['expired_at'] ?? date('Y-m-d H:i:s', time() + 3600),
                'pay_page'     => '/payment/' . $orderId . '/qris'
            ];

            return [
                'attachment' => $attachment,
                'reply'      => "Halo {$registeredName}, berikut kode QRIS untuk pesanan **#{$orderNumber}** dengan total tagihan **{$amountFormatted}**. Silakan scan QRIS di bawah menggunakan GoPay, OVO, DANA, BCA, atau aplikasi perbankan Anda sebelum batas waktu kedaluwarsa."
            ];
        }

        return null;
    }

    /**
     * Resolusi fitur Google Maps untuk Kurir (Driver).
     * Validasi: tugas HARUS ditugaskan ke driver yang login dan berstatus AKTIF.
     */
    private function resolveMapsAttachment(int $driverUserId, string $registeredName, string $message, ?int $focusOrderId, array $context): ?array
    {
        $isMapsRequest = (bool) preg_match('/(\bmaps\b|alamat|lokasi|rute|navigasi|titik\s*jemput|titik\s*antar|tujuan|koordinat)/i', $message);
        if (!$isMapsRequest) {
            return null;
        }

        $pdo = \App\Models\BaseModel::getPdo();
        if (!$pdo) {
            return null;
        }

        $stmtDriver = $pdo->prepare("SELECT id FROM drivers WHERE user_id = :uid LIMIT 1");
        $stmtDriver->execute(['uid' => $driverUserId]);
        $driverRow = $stmtDriver->fetch(\PDO::FETCH_ASSOC);
        if (!$driverRow) {
            return [
                'attachment' => null,
                'reply'      => "Akun Anda belum terdaftar sebagai kurir aktif di sistem."
            ];
        }
        $driverId = (int) $driverRow['id'];

        $targetOrderNumber = null;
        if (preg_match('/ORD-\d{8}-[A-F0-9]{4}/i', $message, $m)) {
            $targetOrderNumber = strtoupper($m[0]);
        }

        $order = null;
        if ($targetOrderNumber !== null) {
            $stmt = $pdo->prepare("
                SELECT o.*, ca.recipient_name, ca.recipient_phone, ca.full_address, ca.rt, ca.rw, ca.district, ca.city, ca.latitude, ca.longitude, st.store_name
                FROM laundry_orders o
                JOIN customer_addresses ca ON ca.id = o.address_id
                JOIN stores st ON st.id = o.store_id
                WHERE o.order_number = :num LIMIT 1
            ");
            $stmt->execute(['num' => $targetOrderNumber]);
            $foundOrder = $stmt->fetch(\PDO::FETCH_ASSOC);

            // Validasi: tugas HARUS ditugaskan ke driver yang login!
            if (!$foundOrder || ((int)$foundOrder['pickup_driver_id'] !== $driverId && (int)$foundOrder['delivery_driver_id'] !== $driverId)) {
                return [
                    'attachment' => null,
                    'reply'      => "Tugas untuk pesanan tersebut tidak ditemukan pada akun Anda."
                ];
            }
            $order = $foundOrder;
        } elseif ($focusOrderId !== null) {
            $stmt = $pdo->prepare("
                SELECT o.*, ca.recipient_name, ca.recipient_phone, ca.full_address, ca.rt, ca.rw, ca.district, ca.city, ca.latitude, ca.longitude, st.store_name
                FROM laundry_orders o
                JOIN customer_addresses ca ON ca.id = o.address_id
                JOIN stores st ON st.id = o.store_id
                WHERE o.id = :id LIMIT 1
            ");
            $stmt->execute(['id' => $focusOrderId]);
            $foundOrder = $stmt->fetch(\PDO::FETCH_ASSOC);

            if (!$foundOrder || ((int)$foundOrder['pickup_driver_id'] !== $driverId && (int)$foundOrder['delivery_driver_id'] !== $driverId)) {
                return [
                    'attachment' => null,
                    'reply'      => "Tugas tidak ditemukan pada akun Anda."
                ];
            }
            $order = $foundOrder;
        } else {
            // Ambil tugas aktif terbaru milik kurir ini
            $stmt = $pdo->prepare("
                SELECT o.*, ca.recipient_name, ca.recipient_phone, ca.full_address, ca.rt, ca.rw, ca.district, ca.city, ca.latitude, ca.longitude, st.store_name
                FROM laundry_orders o
                JOIN customer_addresses ca ON ca.id = o.address_id
                JOIN stores st ON st.id = o.store_id
                WHERE (o.pickup_driver_id = :d1 OR o.delivery_driver_id = :d2)
                  AND o.order_status IN ('driver_assigned', 'driver_on_the_way', 'arrived_at_customer', 'picked_up', 'ready_for_delivery', 'delivery_assigned')
                ORDER BY o.id DESC
                LIMIT 1
            ");
            $stmt->execute(['d1' => $driverId, 'd2' => $driverId]);
            $order = $stmt->fetch(\PDO::FETCH_ASSOC);
        }

        if (!$order) {
            return [
                'attachment' => null,
                'reply'      => "Saat ini tidak ada tugas antar-jemput aktif yang ditugaskan kepada Anda."
            ];
        }

        // Cek status aktif
        $isPickup = ((int)$order['pickup_driver_id'] === $driverId);
        $isActive = $isPickup
            ? in_array($order['order_status'], ['driver_assigned', 'driver_on_the_way', 'arrived_at_customer', 'picked_up'], true)
            : in_array($order['order_status'], ['ready_for_delivery', 'delivery_assigned'], true);

        if (!$isActive) {
            return [
                'attachment' => null,
                'reply'      => "Pesanan **#{$order['order_number']}** sudah selesai atau tidak aktif."
            ];
        }

        // Bangun alamat lengkap & URL Google Maps di backend
        $fullAddr = $order['full_address'];
        if (!empty($order['rt'])) $fullAddr .= ' RT ' . $order['rt'];
        if (!empty($order['rw'])) $fullAddr .= ' RW ' . $order['rw'];
        if (!empty($order['district'])) $fullAddr .= ', Kec. ' . $order['district'];
        if (!empty($order['city'])) $fullAddr .= ', ' . $order['city'];

        $lat = $order['latitude'];
        $lng = $order['longitude'];

        if ($lat !== null && $lng !== null && is_numeric($lat) && is_numeric($lng)) {
            $latVal = (float) $lat;
            $lngVal = (float) $lng;
            $viewUrl = "https://www.google.com/maps/search/?api=1&query={$latVal},{$lngVal}";
            $navUrl  = "https://www.google.com/maps/dir/?api=1&destination={$latVal},{$lngVal}&travelmode=driving";
        } else {
            $viewUrl = "https://www.google.com/maps/search/?api=1&query=" . urlencode($fullAddr);
            $navUrl  = "https://www.google.com/maps/dir/?api=1&destination=" . urlencode($fullAddr) . "&travelmode=driving";
        }

        $taskLabel = $isPickup ? 'penjemputan' : 'pengantaran';
        $attachment = [
            'type'           => 'maps',
            'order_number'   => $order['order_number'],
            'address'        => $fullAddr,
            'view_url'       => $viewUrl,
            'navigate_url'   => $navUrl,
            'customer_phone' => $order['recipient_phone'] ?? '',
        ];

        return [
            'attachment' => $attachment,
            'reply'      => "Lokasi tugas **{$taskLabel}** pesanan **#{$order['order_number']}**: {$fullAddr}."
        ];
    }

    private function money($value): string
    {
        $v = (float) $value;
        $decimals = (abs($v - round($v)) < 0.005) ? 0 : 2;
        return 'Rp ' . number_format($v, $decimals, ',', '.');
    }

    /**
     * Normalisasi teks untuk toleransi typo pada intent statistik admin.
     * Mengubah salah ketik umum ke bentuk baku sebelum pencocokan regex.
     */
    private function normalizeStatsText(string $text): string
    {
        // Hapus tanda baca kecuali spasi
        $text = preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $text);
        // Normalisasi spasi berlebih
        $text = preg_replace('/\s+/', ' ', trim($text));

        // Peta typo umum untuk kata kunci statistik
        $typoMap = [
            // Variasi "berapa"
            '/\b(beraapa|berrapa|berapaa|brrapa|berpa|berapah|b[e3]r[a4]p[a4])\b/i' => 'berapa',
            '/\b(brp|brapa)\b/i' => 'berapa',
            // Variasi "jumlah"
            '/\b(jumla|jumlh|jmlah|jmlh|jumalh|jumblah)\b/i' => 'jumlah',
            '/\b(jml)\b/i' => 'jumlah',
            // Variasi "total"
            '/\b(totall|ttal|totl|ttl)\b/i' => 'total',
            // Variasi "customer"
            '/\b(costumer|custumer|costomer|custommer|castomer|customr|custmer|kustomer|kastomer|costumers?)\b/i' => 'customer',
            // Variasi "pelanggan"
            '/\b(pelangan|pelangagn|pelangaan|planggan|plangan)\b/i' => 'pelanggan',
            // Variasi "kurir"
            '/\b(kurrir|kuirr|kurr|kuir|kurirr)\b/i' => 'kurir',
            // Variasi "mitra"
            '/\b(mitraa|mirta|mtra|miitra|mtera)\b/i' => 'mitra',
            // Variasi "pengguna"
            '/\b(penguna|pengguna|penggunaa|pngguna|penggna)\b/i' => 'pengguna',
            // Variasi "statistik"
            '/\b(statistk|satatistik|statisik|stattistik|statisttik)\b/i' => 'statistik',
            // Variasi "verifikasi"
            '/\b(verif|verifiksi|verivikasi|ferivikasi|ferifikasi)\b/i' => 'verifikasi',
            // Variasi "laundry"
            '/\b(londry|laundri|laundrey|loundry|laundary)\b/i' => 'laundry',
            // Variasi "driver"
            '/\b(driverr|drver|drivr|drifer)\b/i' => 'driver',
        ];

        foreach ($typoMap as $pattern => $replacement) {
            $text = preg_replace($pattern, $replacement, $text);
        }

        return $text;
    }

    /**
     * Fallback cerdas berbasis konteks lokal dan aturan pengetahuan bisnis.
     * Aktif otomatis jika upstream AI sedang error, kuota habis, atau tidak merespons.
     */
    private function generateFallbackReply(string $message, array $context): string
    {
        $text = mb_strtolower($message);
        $role = $context['peran'] ?? 'tamu';
        $registeredName = !empty($context['nama_terdaftar']) ? htmlspecialchars((string) $context['nama_terdaftar']) : '';
        $name = $registeredName !== '' ? $registeredName : (!empty($context['nama_pelanggan']) ? htmlspecialchars((string) $context['nama_pelanggan']) : 'Pelanggan');

        // Larangan manipulasi role lewat chat
        if (preg_match('/(saya\s*(adalah\s*)?(owner|pemilik|admin|kurir|driver)|ganti\s*(peran|role)|jadikan\s*saya\s*(owner|kurir)|anggap\s*saya\s*(owner|kurir))/i', $text)) {
            if ($role === 'customer') {
                return "Peran akun Anda adalah Pelanggan dan tidak dapat diubah melalui chat.";
            } elseif ($role === 'driver') {
                return "Peran akun Anda adalah Kurir dan tidak dapat diubah melalui chat.";
            } elseif ($role === 'owner') {
                return "Peran akun Anda adalah Pemilik Toko dan tidak dapat diubah melalui chat.";
            } elseif ($role === 'admin') {
                return "Peran akun Anda adalah Administrator dan tidak dapat diubah melalui chat.";
            }
            return "Peran akun Anda ditentukan oleh sistem dan tidak dapat diubah melalui chat.";
        }

        // Pertanyaan Identitas Pengguna ("siapa saya", "akun saya", dll)
        if (preg_match('/(saya\s*siapa|siapa\s*saya|nama\s*saya|akun\s*saya|profil\s*saya)/i', $text)) {
            $isLoggedIn = ($role !== 'tamu' && !empty($name) && ($context['mode_akses'] ?? '') !== 'tamu' && ($context['mode_akses'] ?? '') !== 'tamu_publik');

            if (!$isLoggedIn) {
                return 'Anda sedang mengakses sebagai tamu. Silakan login untuk melihat akun Anda.';
            }

            $labelRole = match ($role) {
                'admin'                 => 'Admin',
                'owner', 'mitra'        => 'Mitra Laundry',
                'driver', 'kurir'       => 'Kurir',
                'customer', 'pelanggan' => 'Customer',
                default                 => ucfirst($role)
            };

            $username = $context['username'] ?? $context['email'] ?? $name;

            if ($role === 'admin') {
                return "Anda login sebagai **{$name}** (username: {$username}), dengan role **Admin**. Anda dapat memverifikasi pendaftaran mitra dan kurir serta mengelola role pengguna.";
            }

            return "Anda login sebagai **{$name}** ({$username}), role: **{$labelRole}**.";
        }

        // Larangan koding / topik di luar laundry
        if (preg_match('/(\bcode\b|\bcoding\b|\bscript\b|\bpython\b|\bjavascript\b|\bphp\b|\bjava\b|\bc\+\+|\bhtml\b|\bcss\b|bikin aplikasi|buat program|tulis kode)/i', $text)) {
            return "Saya asisten Marketplace Laundry dan hanya melayani seputar layanan laundry.";
        }

        // Dispatch Fallback Berdasarkan Role
        if ($role === 'admin') {
            return $this->generateAdminFallback($text, $name, $context);
        }

        // Normalisasi typo untuk pengecekan akses non-admin
        $nText = $this->normalizeStatsText($text);

        // Pembatasan akses statistik & data pengguna/customer untuk peran selain admin
        if ($role !== 'admin') {
            $isCustomerDataQuery = (bool) (
                preg_match('/(siapa\s*(saja)?\s*(customer|pelanggan)|daftar\s*(customer|pelanggan)|(customer|pelanggan)\s*terbaru|(customer|pelanggan)\s*siapa\s*saja)/i', $nText)
                || preg_match('/(?:cari|info|detail|tentang|data)\s+(?:customer|pelanggan)/i', $nText)
                || preg_match('/(?:customer|pelanggan)\s+(?:bernama|nama)/i', $nText)
            );
            if ($isCustomerDataQuery) {
                return "Informasi data customer hanya dapat diakses oleh Admin.";
            }

            $isStatsQuery = (bool) (
                preg_match('/(jumlah|total|berapa|ringkasan|statistik)\s*(semua\s*)?(pengguna|user|mitra|kurir|driver|customer|pelanggan)/i', $nText)
                || preg_match('/(berapa\s*(jumlah\s*)?(kurir|mitra|driver|customer|pelanggan|pengguna|user))/i', $nText)
            );
            if ($isStatsQuery) {
                return "Informasi statistik/data pengguna hanya dapat diakses oleh Admin.";
            }
        }

        if ($role === 'owner') {
            return $this->generateOwnerFallback($text, $name, $context);
        }

        if ($role === 'driver') {
            return $this->generateDriverFallback($text, $name, $context);
        }

        if (preg_match('/(hilang|tertinggal|ketinggalan|rusak|sobek|robek|kurang|tertukar|cacat)/i', $text)) {
            $phone = $context['kontak_bantuan']['phone'] ?? '+62 812-3456-7890';
            return "Untuk kendala pakaian hilang atau rusak, silakan hubungi CS resmi di **{$phone}**.";
        }

        if (preg_match('/(error|bug|gagal|tidak bisa|tombol|masalah)/i', $text)) {
            $phone = $context['kontak_bantuan']['phone'] ?? '+62 812-3456-7890';
            return "Jika mengalami kendala teknis, silakan muat ulang halaman atau hubungi CS di **{$phone}**.";
        }

        // Tanya Kapan Bayar (Customer Login)
        if (($context['mode_akses'] ?? '') === 'pelanggan_login'
            && (preg_match('/(kapan|kpn)\s*(saya|sy|harus|mesti|perlu)?\s*(harus|mesti|perlu)?\s*bayar|(kapan|kpn)\s*bayarnya|bayar\s*kapan|(kapan|kpn)\s*(pembayaran|bayar\s*pesanan)/i', $text)
                || (preg_match('/(kapan|kpn)/i', $text) && preg_match('/(bayar|tagihan|lunas)/i', $text)))) {
            $customerId = (int) ($context['user_id'] ?? 0);
            if ($customerId > 0) {
                $paySched = $this->resolvePaymentSchedule($customerId, $name);
                if (!empty($paySched['reply'])) {
                    return $paySched['reply'];
                }
            }
        }

        // Tanya Jumlah / Hitung Pesanan Customer
        if (($context['mode_akses'] ?? '') === 'pelanggan_login') {
            $customerId = (int) ($context['user_id'] ?? 0);
            if ($customerId > 0) {
                $nText = str_replace(
                    ['pesenan', 'psnan', 'orderan', 'order', 'cucian', 'brp', 'brapa', 'slsai', 'selse', 'beres', 'kelar', 'kpn', 'sy', 'dicuci'],
                    ['pesanan', 'pesanan', 'pesanan', 'pesanan', 'pesanan', 'berapa', 'berapa', 'selesai', 'selesai', 'selesai', 'selesai', 'kapan', 'saya', 'diproses'],
                    $text
                );
                $nText = preg_replace('/\s+/', ' ', $nText);

                if (preg_match('/(berapa|jumlah|total)?\s*(pesanan|order)?\s*(yang\s*)?(sudah\s*)?selesai/i', $nText) && !preg_match('/(aktif|diproses)/i', $nText)) {
                    $counts = $this->resolveCustomerOrderCounts($customerId);
                    return "Pesanan selesai Anda ada **{$counts['completed']}**.";
                }

                if (preg_match('/(berapa|jumlah|total)?\s*(pesanan|order)?\s*(yang\s*)?(sedang\s*)?(aktif|diproses|proses|pending|berjalan)/i', $nText) && !preg_match('/selesai/i', $nText)) {
                    $counts = $this->resolveCustomerOrderCounts($customerId);
                    return "Pesanan aktif/diproses Anda ada **{$counts['active']}**.";
                }

                if (preg_match('/pesanan\s*(terakhir|terbaru|paling\s*baru|last)/i', $nText)) {
                    $counts = $this->resolveCustomerOrderCounts($customerId);
                    if (empty($counts['all'])) {
                        return "Anda belum memiliki pesanan di akun Anda.";
                    }
                    $latest = $counts['all'][0];
                    $badge = \App\Helpers\OrderStatusHelper::getBadge($latest['order_status']);
                    $statusLabel = $badge[1] ?? $latest['order_status'];
                    $statusPhrase = in_array($latest['order_status'], ['delivered', 'completed'], true) ? "sudah selesai" : "sedang " . strtolower($statusLabel);
                    return "Pesanan terakhir Anda adalah **#{$latest['order_number']}** ({$latest['store_name']}), status {$statusPhrase}.";
                }

                if (preg_match('/(berapa|jumlah)\s*(total|semua)?\s*pesanan/i', $nText)
                    || preg_match('/^(total\s*pesanan|pesanan\s*saya|daftar\s*pesanan|semua\s*pesanan)$/i', $nText)) {
                    $counts = $this->resolveCustomerOrderCounts($customerId);
                    return "Total pesanan Anda {$counts['total']}: {$counts['active']} aktif, {$counts['completed']} selesai.";
                }
            }
        }

        // Alur / Cara Order / Mau Order Baru (Prioritas sebelum cek status)
        if (preg_match('/(cara\s*(order|pesan|laundry)|mau\s*(order|pesan|nyuci|laundry)|gimana\s*(cara\s*)?(saya\s*)?(order|pesan)|bagaimana\s*(cara\s*)?(saya\s*)?(order|pesan)|alur\s*(order|pesan)|langkah\s*(order|pesan)|order\s*baru|pesan\s*baru|buat\s*(order|pesanan)|bikin\s*(order|pesanan))/i', $text)
            || preg_match('/^(order|pesan|mau order|mau pesan|gimana order|order gimana|saya mau order gimana\??)$/i', $text)) {
            return "Berikut langkah-langkah mudah membuat pesanan laundry:\n\n"
                 . "1. Buka menu **Layanan** di navigasi atas atau pilih toko laundry mitra di halaman Beranda.\n"
                 . "2. Pilih jenis layanan yang Anda inginkan (Cuci Kiloan Reguler, Satuan, atau Express).\n"
                 . "3. Tentukan alamat penjemputan pakaian Anda.\n"
                 . "4. Klik tombol **Buat Pesanan**.\n"
                 . "5. Kurir armada akan datang menjemput pakaian kotor ke lokasi Anda.\n"
                 . "6. Setelah pakaian ditimbang di toko, Anda dapat membayar via QRIS otomatis atau Tunai (COD).\n"
                 . "7. Pakaian dicuci bersih, wangi, rapi, dan diantarkan kembali ke alamat Anda!\n\n"
                 . "Silakan klik menu **Layanan** atau buka Beranda untuk langsung memesan.";
        }

        // Tanya Status Pesanan atau Total Biaya
        if (preg_match('/(status|lacak|tracking|posisi\s*(cucian|kurir|pesanan)|sampai\s*mana|cucian saya|baju saya|biaya|total|tagihan)/i', $text)
            || (preg_match('/(pesanan|order)/i', $text) && preg_match('/(status|lacak|cek|lihat|posisi|dimana|mana)/i', $text))) {
            if (($context['mode_akses'] ?? '') !== 'pelanggan_login') {
                return "Silakan login terlebih dahulu untuk mengecek pesanan Anda.";
            }

            $orders = $context['pesanan'] ?? [];
            if (empty($orders)) {
                return "Anda belum memiliki pesanan aktif di akun Anda.";
            }

            $targetOrder = null;
            foreach ($orders as $o) {
                if (!empty($o['dibahas_pelanggan'])) {
                    $targetOrder = $o;
                    break;
                }
            }
            if (!$targetOrder) {
                $targetOrder = $orders[0];
            }

            $orderNum = $targetOrder['nomor_pesanan'];
            $statusLabel = $targetOrder['status_pesanan']['label'] ?? 'Diproses';
            $total = $targetOrder['rincian_biaya']['total'] ?? 'Rp 0';

            if (preg_match('/(biaya|total|tagihan)/i', $text) && !preg_match('/(status|lacak|sampai mana|posisi)/i', $text)) {
                return "Total biaya pesanan **{$orderNum}** adalah **{$total}**.";
            }

            $isDone = in_array(strtolower($statusLabel), ['selesai', 'completed'], true);
            $statusVerb = $isDone ? 'sudah' : 'sedang';
            $formattedStatus = $isDone ? 'selesai' : $statusLabel;

            return "Pesanan **{$orderNum}** {$statusVerb} **{$formattedStatus}**.";
        }

        // Metode Pembayaran
        if (preg_match('/(bayar|pembayaran|qris|tunai|cash|cod|transfer|kapan bayar)/i', $text)) {
            if (($context['mode_akses'] ?? '') === 'pelanggan_login' && !empty($context['pesanan'][0]['pembayaran'])) {
                $p = $context['pesanan'][0]['pembayaran'];
                $status = $p['status'] ?? 'belum bayar';
                $metode = $p['metode'] ?? 'belum dipilih';
                return "Status pembayaran pesanan **{$context['pesanan'][0]['nomor_pesanan']}**: **{$status}** (metode: {$metode}).";
            }
            return "Pembayaran tersedia via QRIS otomatis dan Tunai (COD) setelah cucian selesai ditimbang di toko.";
        }

        // Harga Kiloan
        if (preg_match('/(kiloan|per kilo|per kg|kilo|cuci kering|setrika)/i', $text)) {
            $kiloPrice = 'Rp 7.000 / kg';
            if (!empty($context['layanan_toko'])) {
                foreach ($context['layanan_toko'] as $ls) {
                    if (stripos($ls['nama_layanan'], 'kilo') !== false) {
                        $kiloPrice = $ls['harga'];
                        break;
                    }
                }
            }
            return "Cuci Kiloan Reguler **{$kiloPrice}**.";
        }

        // Harga Satuan
        if (preg_match('/(satuan|bedcover|bed cover|jas|sepatu|helm|boneka|sprei|selimut|gorden|dry clean)/i', $text)) {
            if (preg_match('/(bedcover|bed cover)/i', $text)) {
                return "Tarif cuci bedcover mulai dari **Rp 25.000 / pcs**.";
            }
            if (preg_match('/(jas)/i', $text)) {
                return "Tarif cuci jas lengkap **Rp 35.000 / stel**.";
            }
            if (preg_match('/(sepatu)/i', $text)) {
                return "Tarif cuci sepatu mulai dari **Rp 25.000 / pasang**.";
            }
            return "Tarif cuci satuan mulai dari **Rp 15.000 - Rp 35.000** tergantung jenis pakaian.";
        }

        // Alur / Cara Pesan
        if (preg_match('/(cara pesan|alur|langkah|order|pesan|jemput|antar|penjemputan|pengantaran)/i', $text)) {
            return "Cara pesan: 1. Pilih toko & layanan di Buat Order. 2. Kurir menjemput & pakaian ditimbang. 3. Bayar via QRIS/Tunai, pakaian dicuci lalu diantar kembali.";
        }

        // Ongkos Kirim
        if (preg_match('/(ongkir|ongkos kirim|jarak|radius|biaya kirim|tarif antar)/i', $text)) {
            return "Ongkos kirim (termasuk jemput & antar): 0-3 km **Rp 5.000**, 3-5 km **Rp 8.000**, di atas 5 km ditambah **Rp 2.000/km**.";
        }

        // Garansi
        if (preg_match('/(garansi|luntur|rusak|hilang|bau|apek|noda|komplain|aman)/i', $text)) {
            return "Garansi cuci ulang 100% gratis jika cucian kurang bersih atau berbau apek dalam 2x24 jam.";
        }

        // Sapaan / Terima kasih / Default
        if (preg_match('/^(halo|hai|hi|hey|pagi|siang|sore|malam)/i', $text)) {
            return "Ada yang bisa dibantu terkait layanan laundry?";
        }

        if (preg_match('/(terima\s*kasih|makasih|thanks)/i', $text)) {
            return "Sama-sama.";
        }

        if (preg_match('/(fungsi|kegunaan|tentang|aplikasi apa|website apa)/i', $text)) {
            return "Marketplace Laundry melayani laundry kiloan dan satuan dengan fasilitas antar-jemput kurir.";
        }

        return "Pertanyaan di luar topik. Ada yang bisa dibantu terkait pesanan, harga, atau kurir?";
    }

    /**
     * Fallback cerdas untuk peran ADMIN.
     */
    private function generateAdminFallback(string $text, string $name, array $context): string
    {
        // Normalisasi typo sebelum pencocokan intent
        $text = $this->normalizeStatsText($text);

        $pendingPartners = (int) ($context['mitra_menunggu_verifikasi'] ?? 0);
        $pendingCouriers = (int) ($context['kurir_menunggu_verifikasi'] ?? 0);

        $courierData = $context['kurir_armada'] ?? [];
        $cTotal      = (int) ($courierData['total'] ?? 0);
        $cApproved   = (int) ($courierData['disetujui'] ?? 0);
        $cPending    = (int) ($courierData['menunggu'] ?? 0);
        $cRejected   = (int) ($courierData['ditolak'] ?? 0);

        $partnerData = $context['mitra_laundry'] ?? [];
        $mTotal      = (int) ($partnerData['total'] ?? 0);
        $mApproved   = (int) ($partnerData['disetujui'] ?? 0);
        $mPending    = (int) ($partnerData['menunggu'] ?? 0);
        $mRejected   = (int) ($partnerData['ditolak'] ?? 0);

        $custData    = $context['customer'] ?? [];
        $custTotal   = (int) ($custData['total'] ?? 0);
        $custActive  = (int) ($custData['aktif'] ?? 0);
        $custInactive= (int) ($custData['nonaktif'] ?? 0);

        $userData    = $context['total_pengguna'] ?? [];
        $uTotal      = (int) ($userData['total'] ?? 0);
        $uCust       = (int) ($userData['customer'] ?? 0);
        $uMitra      = (int) ($userData['mitra'] ?? 0);
        $uKurir      = (int) ($userData['kurir'] ?? 0);
        $uAdmin      = (int) ($userData['admin'] ?? 0);

        // 1. Pertanyaan spesifik tentang menunggu verifikasi / antrean berkas (tetap dijawab seperti sekarang)
        $isWaitingIntent = (bool) preg_match('/(menunggu\s*verifikasi|antrean\s*verifikasi|verifikasi\s*menunggu|belum\s*diverifikasi)/i', $text)
            || (preg_match('/(menunggu|antrean)/i', $text) && preg_match('/(verifikasi|berkas|dokumen)/i', $text));

        if ($isWaitingIntent) {
            return "Saat ini ada **{$pendingPartners} mitra** dan **{$pendingCouriers} kurir** menunggu verifikasi. Anda dapat meninjaunya di menu Verifikasi Berkas.";
        }

        // 2. Intent: Cari atau Info Customer Tertentu ("cari customer santos", "info customer budi")
        $isSearchCustomer = (bool) (
            preg_match('/(?:cari|info|detail|tentang|data)\s+(?:customer|pelanggan)\s+([^\?\.\,\!]+)/i', $text, $mSearch)
            || preg_match('/(?:customer|pelanggan)\s+(?:bernama|nama)\s+([^\?\.\,\!]+)/i', $text, $mSearch)
        );

        if ($isSearchCustomer) {
            $rawKw = trim($mSearch[1]);
            $cleanKw = preg_replace('/^(bernama|nama|yang)\s+/i', '', $rawKw);
            $searchKw = trim($cleanKw);

            // Cek apakah hasil sudah ada di konteks
            $searchData = $context['pencarian_customer'] ?? [];
            if (!empty($searchData['kata_kunci']) && strcasecmp($searchData['kata_kunci'], $searchKw) === 0) {
                $results = $searchData['hasil'] ?? [];
            } else {
                // Fallback query database via prepared statement
                $pdo = \App\Models\BaseModel::getPdo();
                $stmt = $pdo->prepare("
                    SELECT u.id, u.name, u.email, u.phone, u.status, u.created_at as registered_at,
                           (SELECT COUNT(*) FROM laundry_orders o WHERE o.customer_id = u.id) as order_count
                    FROM users u
                    WHERE u.role IN ('customer', 'pelanggan')
                      AND (u.name LIKE :s1 OR u.email LIKE :s2 OR u.phone LIKE :s3)
                    ORDER BY u.created_at DESC, u.id DESC
                    LIMIT 6
                ");
                $wcard = '%' . $searchKw . '%';
                $stmt->execute(['s1' => $wcard, 's2' => $wcard, 's3' => $wcard]);
                $resultsRaw = $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
                $results = [];
                foreach ($resultsRaw as $row) {
                    $results[] = [
                        'nama'           => $row['name'],
                        'email'          => $row['email'],
                        'phone'          => $row['phone'] ?: '-',
                        'tanggal_daftar' => date('d M Y, H:i', strtotime($row['registered_at'])),
                        'jumlah_pesanan' => (int) $row['order_count'],
                        'status'         => $row['status'] ?? 'aktif',
                    ];
                }
            }

            if (empty($results)) {
                return "Customer dengan nama atau kata kunci \"{$searchKw}\" tidak ditemukan di sistem.";
            }

            if (count($results) === 1) {
                $c = $results[0];
                return "Informasi customer **{$c['nama']}**:\n" .
                       "- Email: {$c['email']}\n" .
                       "- Nomor WA: {$c['phone']}\n" .
                       "- Tanggal Daftar: {$c['tanggal_daftar']}\n" .
                       "- Jumlah Pesanan: {$c['jumlah_pesanan']} pesanan\n" .
                       "- Status Akun: " . ucfirst($c['status'] ?? 'aktif');
            }

            // Jika ada beberapa hasil (2-5 atau lebih)
            $lines = [];
            $idx = 1;
            foreach (array_slice($results, 0, 5) as $c) {
                $lines[] = "{$idx}. **{$c['nama']}** ({$c['email']} | WA: {$c['phone']}) - {$c['jumlah_pesanan']} pesanan";
                $idx++;
            }
            return "Ditemukan beberapa customer dengan kata kunci \"{$searchKw}\":\n" .
                   implode("\n", $lines) . "\n\n" .
                   "Silakan sebutkan nama yang lebih spesifik untuk melihat detail lengkap.";
        }

        // 3. Intent: Siapa saja customer / Daftar customer / Customer terbaru
        $isListCustomer = (bool) (
            preg_match('/(siapa\s*(saja)?\s*(customer|pelanggan)|daftar\s*(customer|pelanggan)|(customer|pelanggan)\s*terbaru|(customer|pelanggan)\s*siapa\s*saja)/i', $text)
        );

        if ($isListCustomer) {
            $recent = $context['customer_terbaru'] ?? [];
            if (empty($recent)) {
                // Fallback query database jika belum termuat
                $pdo = \App\Models\BaseModel::getPdo();
                $stmt = $pdo->query("
                    SELECT u.name, u.created_at as registered_at
                    FROM users u
                    WHERE u.role IN ('customer', 'pelanggan')
                    ORDER BY u.created_at DESC, u.id DESC
                    LIMIT 10
                ");
                foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [] as $rc) {
                    $recent[] = [
                        'nama'           => $rc['name'],
                        'tanggal_daftar' => date('d M Y, H:i', strtotime($rc['registered_at'])),
                    ];
                }
            }

            $lines = [];
            $idx = 1;
            foreach ($recent as $rc) {
                $lines[] = "{$idx}. **{$rc['nama']}** (terdaftar: {$rc['tanggal_daftar']})";
                $idx++;
            }
            $listStr = !empty($lines) ? implode("\n", $lines) : "Belum ada customer terdaftar.";

            return "Saat ini ada total **{$custTotal} customer** terdaftar di sistem. Berikut 10 customer terbaru:\n\n" .
                   $listStr . "\n\n" .
                   "Untuk melihat daftar lengkap, pencarian, dan riwayat chat seluruh customer, silakan buka tab **Customer** di menu [Verifikasi Pendaftaran](/admin/verifikasi?tab=customers).";
        }

        // 4. Intent: Customer / Pelanggan (Berapa / Jumlah / Statistik)
        // Variasi: "total customer", "jumlah customer", "berapa customer", "jumlah pelanggan", "total pelanggan"
        if (preg_match('/(customer|pelanggan)/i', $text) && preg_match('/(berapa|jumlah|total|statistik|ringkasan|semua|ada)/i', $text)) {
            return "Saat ini terdapat **{$custTotal} customer**: {$custActive} aktif, {$custInactive} nonaktif.";
        }

        // 5. Intent: Kurir Armada
        // Variasi: "berapa jumlah kurir", "berapa kurir", "jumlah kurir", "total kurir", "kurir armada", "statistik kurir"
        if (preg_match('/(kurir|driver)/i', $text) && preg_match('/(berapa|jumlah|total|statistik|ringkasan|armada|semua|ada)/i', $text)) {
            return "Saat ini terdapat **{$cTotal} kurir**: {$cApproved} disetujui, {$cPending} menunggu, {$cRejected} ditolak.";
        }

        // 6. Intent: Mitra Laundry
        // Variasi: "berapa mitra laundry", "berapa jumlah mitra", "jumlah mitra", "total mitra", "mitra laundry", "toko laundry"
        if (preg_match('/(mitra|toko)/i', $text) && preg_match('/(berapa|jumlah|total|statistik|ringkasan|laundry|semua|ada)/i', $text)) {
            return "Saat ini terdapat **{$mTotal} mitra**: {$mApproved} disetujui, {$mPending} menunggu, {$mRejected} ditolak.";
        }

        // 7. Intent: Total Pengguna / Ringkasan Pengguna
        // Variasi: "jumlah pengguna", "total pengguna", "ringkasan pengguna", "statistik pengguna", "berapa pengguna", "total user", "jumlah user"
        if (preg_match('/(pengguna|user)/i', $text) && preg_match('/(berapa|jumlah|total|statistik|ringkasan|semua|ada)/i', $text)) {
            return "Total pengguna terdaftar saat ini **{$uTotal} pengguna**: {$uCust} customer, {$uMitra} mitra, {$uKurir} kurir, dan {$uAdmin} admin.";
        }

        // 8. Intent ambigu: ada kata "berapa"/"jumlah"/"total" tapi tidak jelas subjeknya
        if (preg_match('/\b(berapa|jumlah|total|statistik|ringkasan)\b/i', $text)
            && !preg_match('/(pesanan|order|pendapatan|omset|pembayaran|harga|biaya|ongkir|berat)/i', $text)) {
            return "Maksud Anda jumlah **customer**, **mitra**, atau **kurir**? Silakan ulangi pertanyaan dengan menyebutkan subjeknya.";
        }

        // Jika hanya bertanya kata kunci "verifikasi" atau "berkas" secara umum
        if (preg_match('/(verifikasi|berkas)/i', $text)) {
            return "Saat ini ada **{$pendingPartners} mitra** dan **{$pendingCouriers} kurir** menunggu verifikasi. Anda dapat meninjaunya di menu Verifikasi Berkas.";
        }

        if (preg_match('/(role|fitur|menu|akses|console)/i', $text)) {
            return "Sebagai Admin, Anda dapat memverifikasi pendaftaran mitra dan kurir serta mengelola role pengguna di Admin Console.";
        }

        return "Sebagai Admin, Anda dapat memverifikasi pendaftaran mitra dan kurir serta mengelola pengguna di Admin Console.";
    }

    /**
     * Fallback cerdas untuk peran OWNER.
     */
    private function generateOwnerFallback(string $text, string $name, array $context): string
    {
        $store = $context['toko'] ?? null;
        if (!$store) {
            return "Akun Anda belum terhubung dengan toko laundry aktif di sistem.";
        }

        $activeData = $context['pesanan_aktif_sekarang'] ?? [];
        $activeCount = (int) ($activeData['jumlah'] ?? 0);
        $activeBreakdown = $activeData['rincian_per_status'] ?? [];

        // Pertanyaan spesifik status pesanan aktif (misal: "sedang dicuci", "siap diantar", dll.)
        if (preg_match('/(dicuci|sedang\s*dicuci|diproses|siap\s*diantar|siap\s*antar|menunggu\s*pembayaran)/i', $text) && preg_match('/(berapa|ada|pesanan|order)/i', $text)) {
            if (preg_match('/(cuci|proses)/i', $text)) {
                $count = 0;
                foreach ($activeBreakdown as $lbl => $c) {
                    if (stripos($lbl, 'cuci') !== false || stripos($lbl, 'proses') !== false) {
                        $count += $c;
                    }
                }
                if ($count > 0) {
                    return "Ada **{$count} pesanan** sedang dicuci / diproses.";
                }
                return "Saat ini tidak ada pesanan yang sedang dicuci.";
            }
            if (preg_match('/(siap\s*diantar|siap\s*antar)/i', $text)) {
                $count = 0;
                foreach ($activeBreakdown as $lbl => $c) {
                    if (stripos($lbl, 'siap diantar') !== false) {
                        $count += $c;
                    }
                }
                if ($count > 0) {
                    return "Ada **{$count} pesanan** siap diantar.";
                }
                return "Saat ini tidak ada pesanan yang siap diantar.";
            }
            if (preg_match('/(menunggu\s*pembayaran|bayar)/i', $text)) {
                $count = 0;
                foreach ($activeBreakdown as $lbl => $c) {
                    if (stripos($lbl, 'menunggu pembayaran') !== false) {
                        $count += $c;
                    }
                }
                if ($count > 0) {
                    return "Ada **{$count} pesanan** menunggu pembayaran.";
                }
                return "Saat ini tidak ada pesanan yang menunggu pembayaran.";
            }
        }

        // Pertanyaan pesanan aktif sekarang / saat ini / berjalan / ada orderan
        $isActiveOrderQuery = (bool) preg_match('/(sekarang|saat\s*ini|aktif|berjalan|belum\s*selesai|ada\s*orderan|ada\s*pesanan|^orderan\??$|^pesanan\??$)/i', $text)
            && !preg_match('/(pendapatan|omset|omzet|penjualan|keuangan|uang\s*masuk|penghasilan)/i', $text)
            && !preg_match('/(kemarin|minggu|pekan|bulan|hari\s*ini)/i', $text);

        if ($isActiveOrderQuery) {
            if ($activeCount === 0) {
                return "Saat ini tidak ada pesanan aktif.";
            }
            $parts = [];
            foreach ($activeBreakdown as $statusLabel => $cnt) {
                if ($cnt > 0) {
                    $parts[] = "{$cnt} " . strtolower($statusLabel);
                }
            }
            $detail = !empty($parts) ? ": " . implode(', ', $parts) : "";
            return "Saat ini ada **{$activeCount} pesanan aktif**{$detail}.";
        }

        // Intent AOV (Average Order Value / Rata-rata Nilai Order)
        // Kenali: "aov", "rata-rata nilai order", "rata rata order", "rata-rata transaksi"
        if (preg_match('/\baov\b|rata[\s\-]*rata[\s\-]*(nilai[\s\-]*)?(order|transaksi|pesanan)|avg[\s\-]*order/i', $text)) {
            if (preg_match('/\bhari\s*ini\b/i', $text)) {
                $aovPeriodData = $context['pendapatan_hari_ini'] ?? [];
                $aovPeriodName = 'hari ini';
            } elseif (preg_match('/\bbulan\s*lalu\b/i', $text)) {
                return "Data bulan lalu belum tersedia di asisten ini. Gunakan filter **Bulan Lalu** pada halaman Analitik Performa Toko.";
            } elseif (preg_match('/\bbulan\b/i', $text)) {
                $aovPeriodData = $context['pendapatan_bulan_ini'] ?? [];
                $aovPeriodName = 'bulan ini';
            } else {
                $aovPeriodData = $context['pendapatan_minggu_ini'] ?? [];
                $aovPeriodName = 'minggu ini';
            }

            $aovFormatted  = $aovPeriodData['rata_rata_order'] ?? 'Rp 0';
            $aovTotal      = (int) ($aovPeriodData['total_pesanan'] ?? 0);
            $aovDibatalkan = (int) ($aovPeriodData['pesanan_dibatalkan'] ?? 0);
            $aovValidCount = $aovTotal - $aovDibatalkan;
            $aovPeriodLbl  = !empty($aovPeriodData['periode']) ? " ({$aovPeriodData['periode']})" : "";

            if ($aovValidCount <= 0 || $aovFormatted === 'Rp 0') {
                return "Belum ada transaksi pada periode **{$aovPeriodName}**{$aovPeriodLbl}.";
            }
            return "AOV {$aovPeriodName}{$aovPeriodLbl} adalah **{$aovFormatted}** dari **{$aovValidCount} transaksi valid**.";
        }

        // Cabang pendapatan / order ditaruh SEBELUM cabang lain
        if (preg_match('/(pendapatan|omset|omzet|penjualan|keuangan|uang\s*masuk|order|pesanan|orderan)/i', $text)) {
            // Tentukan periode
            if (preg_match('/(minggu|pekan)/i', $text)) {
                $periodData = $context['pendapatan_minggu_ini'] ?? [];
                $periodName = 'minggu ini';
                $periodLabel = !empty($periodData['periode']) ? " ({$periodData['periode']})" : "";
            } elseif (preg_match('/(bulan)/i', $text)) {
                $periodData = $context['pendapatan_bulan_ini'] ?? [];
                $periodName = 'bulan ini';
                $periodLabel = !empty($periodData['periode']) ? " ({$periodData['periode']})" : "";
            } else {
                $periodData = $context['pendapatan_hari_ini'] ?? [];
                $periodName = 'hari ini';
                $periodLabel = "";
            }

            $totalRevenue = $periodData['total_pendapatan'] ?? 'Rp 0';
            $totalOrders  = (int) ($periodData['total_pesanan'] ?? 0);
            $completed    = (int) ($periodData['pesanan_selesai'] ?? 0);
            $pending      = (int) ($periodData['pesanan_diproses'] ?? 0);

            $hasRevenueKeyword = (bool) preg_match('/(pendapatan|omset|omzet|penjualan|keuangan|uang\s*masuk|penghasilan)/i', $text);

            if ($hasRevenueKeyword) {
                if ($periodName === 'hari ini') {
                    return "Pendapatan hari ini **{$totalRevenue}** dari **{$totalOrders} pesanan**.";
                }
                return "Pendapatan {$periodName}{$periodLabel}: **{$totalRevenue}** dari **{$totalOrders} pesanan**.";
            }

            // Hanya menanyakan order / pesanan
            if ($periodName === 'hari ini') {
                if ($totalOrders === 0 && $activeCount > 0) {
                    return "Hari ini belum ada pesanan masuk baru, tetapi ada **{$activeCount} pesanan aktif** yang sedang berjalan.";
                }
                return "Hari ini ada **{$totalOrders} pesanan** masuk ({$completed} selesai, {$pending} diproses).";
            }
            return "Pesanan {$periodName}{$periodLabel} berjumlah **{$totalOrders} pesanan** ({$completed} selesai, {$pending} diproses).";
        }

        // Pesanan yang perlu ditimbang
        if (preg_match('/(timbang|penimbangan|belum\s*ditimbang|perlu\s*ditimbang)/i', $text)) {
            $toWeigh = $context['pesanan_menunggu_ditimbang'] ?? [];
            if (empty($toWeigh)) {
                return "Saat ini tidak ada pesanan yang menunggu penimbangan.";
            }

            $count = count($toWeigh);
            $first = $toWeigh[0];
            return "Ada **{$count} pesanan** perlu ditimbang, termasuk pesanan **#{$first['nomor_pesanan']}** ({$first['nama_pelanggan']}).";
        }

        // Pembayaran tunai yang menunggu konfirmasi
        if (preg_match('/(tunai|cash|konfirmasi\s*bayar|menunggu\s*konfirmasi)/i', $text)) {
            $cashOrders = $context['pembayaran_tunai_menunggu_konfirmasi'] ?? [];
            if (empty($cashOrders)) {
                return "Saat ini tidak ada pembayaran tunai yang menunggu konfirmasi.";
            }

            $count = count($cashOrders);
            $first = $cashOrders[0];
            return "Ada **{$count} pembayaran tunai** menunggu konfirmasi, termasuk pesanan **#{$first['nomor_pesanan']}** ({$first['nominal']}).";
        }

        // Daftar kurir dan ketersediaan
        if (preg_match('/(kurir|driver|pengantar|penjemput)/i', $text)) {
            $drivers = $context['daftar_kurir'] ?? [];
            if (empty($drivers)) {
                return "Belum ada kurir yang terdaftar pada sistem.";
            }

            $summary = [];
            foreach (array_slice($drivers, 0, 3) as $d) {
                $summary[] = "{$d['nama']} ({$d['status']})";
            }
            return "Kurir terdaftar: " . implode(', ', $summary) . ".";
        }

        // Layanan dan harga toko
        if (preg_match('/(layanan|tarif|harga|katalog)/i', $text)) {
            $services = $context['layanan_toko'] ?? [];
            if (empty($services)) {
                return "Toko belum memiliki katalog layanan aktif.";
            }

            $summary = [];
            foreach (array_slice($services, 0, 3) as $s) {
                $summary[] = "{$s['nama_layanan']} ({$s['harga']})";
            }
            return "Layanan toko: " . implode(', ', $summary) . ".";
        }

        // Default Owner
        return "Anda dapat menanyakan pendapatan, jumlah order, penimbangan, pembayaran tunai, atau kurir toko.";
    }

    /**
     * Fallback cerdas untuk peran DRIVER (Kurir).
     */
    private function generateDriverFallback(string $text, string $name, array $context): string
    {
        // Larangan kurir melihat pendapatan/omzet toko
        if (preg_match('/(toko|omset|omzet)/i', $text) && preg_match('/(pendapatan|omset|omzet|penjualan|keuangan|uang)/i', $text)) {
            return "Kurir hanya dapat mengakses data penghasilan tugas sendiri.";
        }

        $earnings   = $context['penghasilan_kurir'] ?? [];
        $taskCounts = $earnings['jumlah_tugas_selesai'] ?? [];
        $todayCount = (int) ($taskCounts['hari_ini'] ?? 0);
        $weekCount  = (int) ($taskCounts['minggu_ini'] ?? 0);
        $monthCount = (int) ($taskCounts['bulan_ini'] ?? 0);
        $totalCount = (int) ($taskCounts['total'] ?? $earnings['total_pengantaran_selesai'] ?? 0);

        $hasEarningKw = (bool) preg_match('/(penghasilan|pendapatan|gaji|upah|komisi|bayaran|earning|income)/i', $text);
        $hasCountKw   = (bool) preg_match('/(pengantaran|\bantar\b|tugas\s*selesai|berapa\s*kali|jumlah\s*pengantaran|total\s*pengantaran|sudah\s*antar)/i', $text)
                        && !preg_match('/(alamat|lokasi|rute|maps|navigasi|titik|tujuan)/i', $text);

        // A. Penghasilan + Jumlah tugas sekaligus
        if ($hasEarningKw && $hasCountKw) {
            if (preg_match('/(total|semua)/i', $text)) {
                $amt = $earnings['total_semua_waktu'] ?? 'Rp 0';
                return "Total Anda menyelesaikan **{$totalCount} tugas** dengan penghasilan **{$amt}**.";
            }
            if (preg_match('/(minggu|pekan)/i', $text)) {
                $amt = $earnings['minggu_ini'] ?? 'Rp 0';
                return "Minggu ini Anda menyelesaikan **{$weekCount} tugas** dengan penghasilan **{$amt}**.";
            }
            if (preg_match('/(bulan)/i', $text)) {
                $amt = $earnings['bulan_ini'] ?? 'Rp 0';
                return "Bulan ini Anda menyelesaikan **{$monthCount} tugas** dengan penghasilan **{$amt}**.";
            }
            $amt = $earnings['hari_ini'] ?? 'Rp 0';
            return "Hari ini Anda menyelesaikan **{$todayCount} tugas** dengan penghasilan **{$amt}**.";
        }

        // B. Hanya menanyakan penghasilan
        if ($hasEarningKw) {
            if (preg_match('/(total|semua)/i', $text)) {
                $totalAmt = $earnings['total_semua_waktu'] ?? 'Rp 0';
                return "Total penghasilan Anda **{$totalAmt}** dari **{$totalCount} pengantaran**.";
            }
            if (preg_match('/(minggu|pekan)/i', $text)) {
                $amt = $earnings['minggu_ini'] ?? 'Rp 0';
                return "Penghasilan Anda minggu ini **{$amt}**.";
            }
            if (preg_match('/(bulan)/i', $text)) {
                $amt = $earnings['bulan_ini'] ?? 'Rp 0';
                return "Penghasilan Anda bulan ini **{$amt}**.";
            }
            $amt = $earnings['hari_ini'] ?? 'Rp 0';
            return "Penghasilan Anda hari ini **{$amt}**.";
        }

        // C. Hanya menanyakan jumlah pengantaran / tugas selesai
        if ($hasCountKw) {
            if (preg_match('/(total|semua)/i', $text)) {
                return "Total tugas yang sudah Anda selesaikan: **{$totalCount} tugas**.";
            }
            if (preg_match('/(minggu|pekan)/i', $text)) {
                return "Minggu ini Anda telah menyelesaikan **{$weekCount} tugas pengantaran/penjemputan**.";
            }
            if (preg_match('/(bulan)/i', $text)) {
                return "Bulan ini Anda telah menyelesaikan **{$monthCount} tugas pengantaran/penjemputan**.";
            }
            return "Hari ini Anda telah menyelesaikan **{$todayCount} tugas pengantaran/penjemputan**.";
        }

        // Larangan data kurir lain
        if (preg_match('/(kurir lain|driver lain)/i', $text)) {
            return "Data kurir lain tidak dapat diakses.";
        }

        $tasks = $context['daftar_tugas'] ?? [];
        $activeTask = $context['tugas_aktif_terbaru'] ?? null;

        // 2. Alamat / Rute / Maps
        if (preg_match('/(alamat|lokasi|rute|maps|navigasi|titik|tujuan)/i', $text)) {
            if ($activeTask) {
                $mapsView = $activeTask['link_google_maps'] ?? '#';
                $addr = $activeTask['alamat_lengkap'] ?? $activeTask['alamat_toko'] ?? '-';
                return "Tugas **#{$activeTask['nomor_pesanan']}** ({$activeTask['jenis_tugas']}): {$addr}. [Buka Google Maps]({$mapsView}).";
            }

            return "Saat ini tidak ada tugas antar-jemput aktif.";
        }

        // 3. Alur status & tahapan
        if (preg_match('/(status|tahapan|alur|cara\s*update)/i', $text)) {
            $alur = $context['alur_status_tugas'] ?? [];
            return "Alur penjemputan: " . ($alur['penjemputan'] ?? '-') . ". Alur pengantaran: " . ($alur['pengantaran'] ?? '-') . ".";
        }

        // 4. Default Driver: Ringkasan Tugas Aktif
        $activeTasks = array_filter($tasks, fn($t) => !empty($t['sedang_aktif']));
        $activeCount = count($activeTasks);
        if ($activeCount > 0) {
            $first = reset($activeTasks);
            return "Anda punya **{$activeCount} tugas aktif**: **#{$first['nomor_pesanan']}**, status **{$first['status_tugas']}**.";
        }

        return "Saat ini tidak ada tugas aktif yang ditugaskan kepada Anda.";
    }
}

