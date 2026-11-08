<section class="py-5 bg-light min-vh-100">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-7 col-lg-5">
                <div class="card border-0 shadow-sm rounded-4 overflow-hidden text-center">
                    <div class="card-header bg-dark text-white p-4">
                        <span class="badge bg-warning text-dark rounded-pill px-3 mb-2 fw-bold">ORDER #<?= htmlspecialchars($order['order_number']) ?></span>
                        <h5 class="fw-bold mb-1 text-white">Pembayaran QRIS</h5>
                        <p class="mb-0 small text-white-50">Scan QR Code di bawah dengan e-Wallet atau m-Banking Anda</p>
                    </div>
                    <div class="card-body p-4 bg-white">
                        <div class="mb-3">
                            <span class="text-muted small">Total yang harus dibayar:</span>
                            <div class="fs-2 fw-bold text-primary">Rp <?= number_format($order['total_amount'], 0, ',', '.') ?></div>
                        </div>

                        <!-- QR Code Container -->
                        <div class="p-3 bg-light rounded-4 d-inline-block border mb-3 shadow-inner">
                            <?php if (!empty($payment['qris_url'])): ?>
                                <img src="<?= htmlspecialchars($payment['qris_url']) ?>" alt="QRIS Code" class="img-fluid rounded-3" style="max-width: 240px; height: auto;">
                            <?php else: ?>
                                <div class="p-4 bg-white rounded-3 border d-flex flex-column align-items-center justify-content-center" style="width: 240px; height: 240px;">
                                    <i class="bi bi-qr-code text-secondary" style="font-size: 5rem;"></i>
                                    <span class="badge bg-primary-subtle text-primary mt-2">QRIS Simulasi / Sandbox</span>
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- Countdown Timer -->
                        <div class="alert alert-warning py-2 rounded-pill small mb-4 d-flex align-items-center justify-content-center gap-2">
                            <i class="bi bi-clock-history"></i>
                            <span>Berlaku hingga: <strong><?= date('H:i', $expiredAt) ?> WIB</strong></span>
                        </div>

                        <div class="d-flex flex-column gap-2">
                            <a href="/payment/<?= (int)$order['id'] ?>/status" class="btn btn-primary rounded-pill py-2.5 fw-bold shadow-sm">
                                <i class="bi bi-arrow-repeat me-1"></i> Cek Status Pembayaran
                            </a>
                            <a href="/customer/orders/<?= (int)$order['id'] ?>" class="btn btn-outline-secondary rounded-pill py-2 small">
                                Kembali ke Detail Pesanan
                            </a>
                        </div>
                    </div>
                    <div class="card-footer bg-light p-3 small text-muted">
                        <i class="bi bi-shield-check text-success me-1"></i> Otomatis terverifikasi setelah transfer berhasil
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
