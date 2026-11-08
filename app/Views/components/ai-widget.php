<?php
/**
 * Widget AI melayang (pojok kanan bawah), dipasang di layouts/main.php.
 * - Pengunjung / non-customer : hanya info publik (layanan, harga, ongkir, FAQ).
 * - Customer login            : juga bisa tanya status order & pembayaran miliknya sendiri.
 * Mode ditentukan di SERVER dari session, bukan dari browser.
 */

use App\Helpers\Session;
use App\Services\AIService;

$aiUser          = Session::get('user');
$aiRawRole       = is_array($aiUser) ? strtolower(trim((string) ($aiUser['role'] ?? ''))) : '';
$aiCanonicalRole = match ($aiRawRole) {
    'admin'                 => 'admin',
    'owner', 'mitra'        => 'owner',
    'driver', 'kurir'       => 'driver',
    'customer', 'pelanggan' => 'customer',
    default                 => 'tamu'
};

$aiRegisteredName = '';
if (is_array($aiUser) && !empty($aiUser['id'])) {
    try {
        $userDb = (new \App\Models\User())->find((int) $aiUser['id']);
        $aiRegisteredName = $userDb['name'] ?? $aiUser['name'] ?? '';
    } catch (\Throwable $e) {
        $aiRegisteredName = (string) ($aiUser['name'] ?? '');
    }
}
if ($aiRegisteredName === '' && is_array($aiUser)) {
    $aiRegisteredName = (string) ($aiUser['name'] ?? 'Pengguna');
}

try {
    $aiConfigured = (new AIService())->isConfigured();
} catch (\Throwable $e) {
    $aiConfigured = false;
}
$aiHistory    = Session::get('ai_chat_history', []);

// Jika sedang membuka detail order milik customer, fokuskan chat ke order itu
$aiOrderId = ($aiCanonicalRole === 'customer' && isset($order) && is_array($order) && isset($order['id'])) ? (int) $order['id'] : '';

switch ($aiCanonicalRole) {
    case 'admin':
        $aiRoleBadge   = '<span class="badge bg-danger text-white me-1">Admin</span>';
        $aiRoleSub     = htmlspecialchars($aiRegisteredName) . ' &bull; Administrator';
        $aiSuggestions = [
            'Berapa customer?',
            'Berapa mitra?',
            'Berapa kurir?',
            'Siapa saja customer?',
            'Total pengguna',
            'Siapa saya?',
            'Verifikasi menunggu',
        ];
        $aiWelcome = "Halo Admin! Tanya seputar statistik pengguna, verifikasi berkas, atau fitur Admin Console.";
        break;

    case 'owner':
        $aiRoleBadge   = '<span class="badge bg-warning text-dark me-1">Owner</span>';
        $aiRoleSub     = htmlspecialchars($aiRegisteredName) . ' &bull; Manajer Toko';
        $aiSuggestions = [
            'Pendapatan hari ini',
            'Jumlah order hari ini',
            'AOV minggu ini',
            'Pesanan yang perlu ditimbang',
            'Pembayaran tunai menunggu konfirmasi',
        ];
        $aiWelcome = "Tanya pendapatan, jumlah order, atau pesanan toko Anda.";
        break;

    case 'driver':
        $aiRoleBadge   = '<span class="badge bg-info text-white me-1">Kurir</span>';
        $aiRoleSub     = htmlspecialchars($aiRegisteredName) . ' &bull; Antar-Jemput';
        $aiSuggestions = [
            'Penghasilan saya hari ini',
            'Total pengantaran saya',
            'Tugas saya hari ini',
        ];
        $aiWelcome = "Tanya tugas, alamat, atau penghasilan Anda.";
        break;

    case 'customer':
        $aiRoleBadge   = '<span class="badge bg-primary text-white me-1">Customer</span>';
        $aiRoleSub     = htmlspecialchars($aiRegisteredName) . ' &bull; Pelanggan';
        $aiSuggestions = [
            'Pesanan saya sudah sampai mana?',
            'Minta QRIS pembayaran',
            'Apakah pembayaran saya sudah masuk?',
        ];
        $aiWelcome = "Tanya status pesanan atau pembayaran Anda.";
        break;

    default:
        $aiRoleBadge   = '<span class="badge bg-secondary text-white me-1">Tamu</span>';
        $aiRoleSub     = 'Info Layanan &amp; FAQ';
        $aiSuggestions = [
            'Berapa harga laundry kiloan?',
            'Bagaimana cara membuat pesanan?',
            'Metode pembayaran apa saja?',
            'Berapa ongkos kirimnya?',
        ];
        $aiWelcome = "Tanya harga, ongkir, atau cara pesan.";
        break;
}

$scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
$assetBase = ($scriptDir === '/' || $scriptDir === '.' || $scriptDir === '') ? '' : rtrim($scriptDir, '/');
?>
<link rel="stylesheet" href="<?= htmlspecialchars($assetBase . '/assets/css/assistant.css') ?>">

<div class="ai-widget" id="aiWidget">
    <div class="ai-widget__panel" id="aiPanel" role="dialog" aria-label="Asisten Laundry AI">
        <div class="ai-widget__header">
            <div class="d-flex align-items-center gap-2">
                <i class="bi bi-robot fs-5"></i>
                <div>
                    <div class="fw-bold lh-1 d-flex align-items-center gap-1">
                        <?= $aiRoleBadge ?>
                        <span>Asisten AI</span>
                    </div>
                    <small class="text-white-50"><?= $aiRoleSub ?></small>
                </div>
            </div>
            <div class="d-flex align-items-center gap-1">
                <button type="button" id="aiReset" class="btn btn-sm btn-link text-white" title="Percakapan baru" aria-label="Percakapan baru"><i class="bi bi-arrow-counterclockwise"></i></button>
                <button type="button" id="aiClose" class="btn btn-sm btn-link text-white" title="Tutup" aria-label="Tutup"><i class="bi bi-x-lg"></i></button>
            </div>
        </div>

        <?php if (!$aiConfigured): ?>
            <div class="alert alert-warning rounded-0 border-0 mb-0 small py-2">
                <i class="bi bi-info-circle me-1"></i>Chatbot belum dikonfigurasi. Silakan aktifkan dan lengkapi API key pada menu <a href="/admin/konfigurasi" class="alert-link">Konfigurasi</a>.
            </div>
        <?php endif; ?>

        <div id="aiChat" class="ai-chat"
             data-endpoint="<?= htmlspecialchars($assetBase . '/assistant/message') ?>"
             data-reset-endpoint="<?= htmlspecialchars($assetBase . '/assistant/reset') ?>"
             data-csrf="<?= htmlspecialchars(Session::csrfToken()) ?>"
             data-user-id="<?= !empty($aiUser['id']) ? (int) $aiUser['id'] : 0 ?>"
             data-role="<?= htmlspecialchars($aiCanonicalRole) ?>"
             data-order-id="<?= $aiOrderId !== '' ? (int) $aiOrderId : '' ?>"
             data-max-length="500">

            <div id="aiMessages" class="ai-chat__messages" aria-live="polite">
                <div class="ai-msg ai-msg--assistant" data-welcome="1">
                    <div class="ai-msg__bubble"><?= htmlspecialchars($aiWelcome) ?></div>
                </div>
                <?php foreach ((array) $aiHistory as $h): ?>
                    <?php if (!is_array($h) || !isset($h['role'], $h['content'])) continue; ?>
                    <div class="ai-msg ai-msg--<?= $h['role'] === 'user' ? 'user' : 'assistant' ?>">
                        <div class="ai-msg__bubble"><?= htmlspecialchars((string) $h['content']) ?></div>
                    </div>
                <?php endforeach; ?>
            </div>

            <div id="aiSuggestions" class="ai-chat__suggestions">
                <?php foreach ($aiSuggestions as $sg): ?>
                    <button type="button" class="btn btn-sm btn-outline-primary rounded-pill ai-chip"><?= htmlspecialchars($sg) ?></button>
                <?php endforeach; ?>
            </div>

            <form id="aiForm" class="ai-chat__form" autocomplete="off">
                <textarea id="aiInput" class="form-control" rows="1" maxlength="500"
                          placeholder="Tulis pertanyaan Anda..." aria-label="Pertanyaan"
                          <?= $aiConfigured ? '' : 'disabled' ?>></textarea>
                <button type="submit" id="aiSend" class="btn btn-primary rounded-circle ai-send" aria-label="Kirim"
                        <?= $aiConfigured ? '' : 'disabled' ?>><i class="bi bi-send-fill"></i></button>
            </form>
            <div class="ai-chat__meta small text-muted">
                <span><i class="bi bi-shield-check me-1"></i>Tidak dapat mengubah pesanan atau pembayaran.</span>
                <span id="aiCounter">0/500</span>
            </div>
        </div>
    </div>

    <button type="button" id="aiLauncher" class="ai-widget__launcher" aria-label="Buka Asisten Laundry AI" aria-expanded="false">
        <i class="bi bi-robot"></i>
        <span class="ai-widget__label">Tanya AI</span>
    </button>
</div>

<script src="<?= htmlspecialchars($assetBase . '/assets/js/assistant.js') ?>" defer></script>

