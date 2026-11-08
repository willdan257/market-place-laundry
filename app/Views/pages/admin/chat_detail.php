<section class="py-4 bg-light min-vh-100">
    <div class="container">
        <!-- Back Navigation & Breadcrumb -->
        <div class="mb-3">
            <a href="/admin/chats" class="btn btn-sm btn-outline-secondary rounded-pill px-3">
                <i class="bi bi-arrow-left me-1"></i>Kembali ke Riwayat Chat
            </a>
        </div>

        <!-- Order Summary Card -->
        <div class="card border-0 shadow-sm rounded-4 p-4 mb-4 bg-dark text-white">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
                <div>
                    <span class="badge bg-danger rounded-pill px-3 py-1 mb-2 fw-bold">
                        <i class="bi bi-shield-check me-1"></i>ADMIN CONSOLE &bull; LOG PERCAKAPAN
                    </span>
                    <h3 class="fw-bold mb-1">Pesanan #<?= htmlspecialchars($order['order_number']) ?></h3>
                    <p class="mb-0 text-white-50">
                        <i class="bi bi-shop me-1 text-warning"></i><?= htmlspecialchars($order['store_name']) ?> &bull; 
                        <i class="bi bi-person me-1 text-info"></i>Customer: <?= htmlspecialchars($order['customer_name']) ?> (<?= htmlspecialchars($order['customer_phone'] ?? '-') ?>)
                    </p>
                </div>
                <div class="text-end">
                    <span class="badge bg-primary text-white fs-6 px-3 py-2 rounded-pill d-block mb-1">
                        Status: <?= strtoupper(str_replace('_', ' ', $order['order_status'])) ?>
                    </span>
                    <small class="text-white-50">Total: Rp <?= number_format($order['total_amount'], 0, ',', '.') ?></small>
                </div>
            </div>
        </div>

        <!-- Conversation Timeline Card -->
        <div class="card border-0 shadow-sm rounded-4 p-4">
            <div class="d-flex justify-content-between align-items-center mb-4 border-bottom pb-3">
                <h5 class="fw-bold mb-0">
                    <i class="bi bi-chat-dots-fill text-primary me-2"></i>Log Pesan Terkirim (<?= count($messages) ?> Pesan)
                </h5>
                <span class="small text-muted"><i class="bi bi-info-circle me-1"></i>Pesan diurutkan secara kronologis (dari pertama dikirim)</span>
            </div>

            <?php if (empty($messages)): ?>
                <div class="alert alert-secondary text-center py-4 rounded-3">
                    Belum ada pesan yang tercatat untuk pesanan ini.
                </div>
            <?php else: ?>
                <div class="chat-thread d-flex flex-column gap-3">
                    <?php foreach ($messages as $m): ?>
                        <?php
                            $role = $m['sender_role'];
                            $badgeClass = match ($role) {
                                'admin'  => 'bg-danger text-white',
                                'owner'  => 'bg-warning text-dark',
                                'driver' => 'bg-info text-dark',
                                default  => 'bg-primary text-white'
                            };
                            $roleLabel = match ($role) {
                                'admin'  => 'Admin',
                                'owner'  => 'Mitra Toko',
                                'driver' => 'Kurir Armada',
                                default  => 'Customer'
                            };
                            $bubbleBg = match ($role) {
                                'admin'  => 'bg-danger-subtle border-danger',
                                'owner'  => 'bg-warning-subtle border-warning',
                                'driver' => 'bg-info-subtle border-info',
                                default  => 'bg-white border'
                            };
                        ?>
                        <div class="chat-bubble p-3 rounded-4 shadow-sm border <?= $bubbleBg ?>" style="max-width: 85%;">
                            <div class="d-flex align-items-center justify-content-between mb-2 pb-1 border-bottom">
                                <div class="d-flex align-items-center gap-2">
                                    <strong class="text-dark"><?= htmlspecialchars($m['sender_name']) ?></strong>
                                    <span class="badge <?= $badgeClass ?> rounded-pill px-2 py-0.5 small"><?= $roleLabel ?></span>
                                    <?php if (!empty($m['sender_phone'])): ?>
                                        <small class="text-muted">(<?= htmlspecialchars($m['sender_phone']) ?>)</small>
                                    <?php endif; ?>
                                </div>
                                <span class="small text-muted">
                                    <i class="bi bi-clock me-1"></i><?= date('d M Y, H:i:s', strtotime($m['created_at'])) ?>
                                </span>
                            </div>
                            <div class="text-dark lh-base" style="word-break: break-word;">
                                <?= nl2br(htmlspecialchars($m['message'])) ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</section>

