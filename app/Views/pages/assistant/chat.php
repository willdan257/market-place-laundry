<?php
/**
 * @var array       $user
 * @var string      $role
 * @var string      $roleBadge
 * @var string      $registeredName
 * @var string      $welcomeMessage
 * @var array       $history
 * @var array|null  $focusOrder
 * @var string      $csrfToken
 * @var bool        $isConfigured
 * @var int         $maxLength
 * @var string[]    $suggestions
 */
$role = $role ?? 'customer';
$roleBadge = $roleBadge ?? 'Customer';
$registeredName = $registeredName ?? ($user['name'] ?? 'Pengguna');
$welcomeMessage = $welcomeMessage ?? "Halo {$registeredName}! Ada yang bisa saya bantu?";

$backLink = match ($role) {
    'owner'  => '/owner/orders',
    'driver' => '/driver/orders',
    default  => '/customer/dashboard',
};
$backLabel = match ($role) {
    'owner'  => 'Dashboard Owner',
    'driver' => 'Dashboard Kurir',
    default  => 'Dashboard Customer',
};
?>
<link rel="stylesheet" href="/assets/css/assistant.css">

<section class="py-4 bg-light min-vh-100">
    <div class="container" style="max-width: 860px;">
        <div class="mb-3">
            <a href="<?= htmlspecialchars($backLink) ?>" class="text-decoration-none text-muted small fw-semibold">
                <i class="bi bi-arrow-left me-1"></i> Kembali ke <?= htmlspecialchars($backLabel) ?>
            </a>
        </div>

        <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
            <div class="card-header bg-dark text-white p-4">
                <div class="d-flex align-items-center justify-content-between gap-3">
                    <div class="d-flex align-items-center gap-3">
                        <div class="bg-primary rounded-circle d-flex align-items-center justify-content-center" style="width:48px;height:48px;">
                            <i class="bi bi-robot fs-4"></i>
                        </div>
                        <div>
                            <div class="d-flex align-items-center gap-2 mb-1">
                                <h5 class="fw-bold mb-0">Asisten AI</h5>
                                <span class="badge bg-primary text-white rounded-pill px-2 py-1"><?= htmlspecialchars($roleBadge) ?></span>
                            </div>
                            <small class="text-white-50"><?= htmlspecialchars($registeredName) ?> &bull; Marketplace Laundry</small>
                        </div>
                    </div>
                    <button type="button" id="aiReset" class="btn btn-sm btn-outline-light rounded-pill px-3">
                        <i class="bi bi-arrow-counterclockwise me-1"></i> Percakapan baru
                    </button>
                </div>
            </div>

            <?php if (!$isConfigured): ?>
                <div class="alert alert-warning rounded-0 border-0 mb-0 small">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i>
                    Chatbot belum dikonfigurasi. Hubungi Administrator untuk mengisi API key di menu Konfigurasi.
                </div>
            <?php endif; ?>

            <div id="aiChat"
                 class="ai-chat"
                 data-endpoint="/assistant/message"
                 data-reset-endpoint="/assistant/reset"
                 data-csrf="<?= htmlspecialchars($csrfToken) ?>"
                 data-user-id="<?= !empty($user['id']) ? (int) $user['id'] : 0 ?>"
                 data-role="<?= htmlspecialchars($role ?? 'customer') ?>"
                 data-order-id="<?= $focusOrder ? (int) $focusOrder['id'] : '' ?>"
                 data-max-length="<?= (int) $maxLength ?>">

                <?php if ($focusOrder): ?>
                    <div class="ai-chat__context small">
                        <i class="bi bi-receipt me-1"></i>
                        Membahas pesanan <strong>#<?= htmlspecialchars($focusOrder['order_number']) ?></strong>
                        &middot; <a href="/customer/assistant">lepas fokus</a>
                    </div>
                <?php endif; ?>

                <div id="aiMessages" class="ai-chat__messages" aria-live="polite">
                    <div class="ai-msg ai-msg--assistant" data-welcome="1">
                        <div class="ai-msg__bubble"><?= htmlspecialchars($welcomeMessage) ?></div>
                    </div>

                    <?php foreach ((array) $history as $h): ?>
                        <?php if (!is_array($h) || !isset($h['role'], $h['content'])) continue; ?>
                        <div class="ai-msg ai-msg--<?= $h['role'] === 'user' ? 'user' : 'assistant' ?>">
                            <div class="ai-msg__bubble"><?= htmlspecialchars((string) $h['content']) ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div id="aiSuggestions" class="ai-chat__suggestions">
                    <?php foreach ($suggestions as $s): ?>
                        <button type="button" class="btn btn-sm btn-outline-primary rounded-pill ai-chip"><?= htmlspecialchars($s) ?></button>
                    <?php endforeach; ?>
                </div>

                <form id="aiForm" class="ai-chat__form" autocomplete="off">
                    <textarea id="aiInput" class="form-control" rows="1" maxlength="<?= (int) $maxLength ?>"
                              placeholder="Tulis pertanyaan Anda..." aria-label="Pertanyaan"
                              <?= $isConfigured ? '' : 'disabled' ?>></textarea>
                    <button type="submit" id="aiSend" class="btn btn-primary rounded-circle ai-send" aria-label="Kirim"
                            <?= $isConfigured ? '' : 'disabled' ?>>
                        <i class="bi bi-send-fill"></i>
                    </button>
                </form>
                <div class="ai-chat__meta small text-muted">
                    <span><i class="bi bi-shield-check me-1"></i>Hanya membaca data pesanan Anda. Tidak dapat mengubah status, harga, atau pembayaran.</span>
                    <span id="aiCounter">0/<?= (int) $maxLength ?></span>
                </div>
            </div>
        </div>
    </div>
</section>

<script src="/assets/js/assistant.js" defer></script>
