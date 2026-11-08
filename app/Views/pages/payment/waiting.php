<section class="py-5 bg-light min-vh-100">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-7 col-lg-5">
                <div class="card border-0 shadow-sm rounded-4 overflow-hidden text-center">
                    <div class="card-header bg-dark text-white p-4">
                        <span class="badge bg-warning text-dark rounded-pill px-3 mb-2 fw-bold">ORDER #<?= htmlspecialchars($order['order_number']) ?></span>
                        <h5 class="fw-bold mb-1 text-white">Menunggu Pembayaran</h5>
                        <p class="mb-0 small text-white-50"><?= $payment['payment_method'] === 'cash' ? 'Metode Pembayaran Tunai (CASH)' : 'Pembayaran Online (QRIS)' ?></p>
                    </div>
                    <div class="card-body p-4 bg-white">
                        <div class="spinner-border text-primary my-4" role="status" style="width: 3.5rem; height: 3.5rem;">
                            <span class="visually-hidden">Loading...</span>
                        </div>

                        <h6 class="fw-bold text-dark mb-2">Sedang Menunggu Konfirmasi</h6>
                        <p class="text-muted small mb-4">
                            <?php if ($payment['payment_method'] === 'cash'): ?>
                                Mohon siapkan uang pas sebesar <strong>Rp <?= number_format($order['total_amount'], 0, ',', '.') ?></strong> saat kurir tiba atau di toko laundry.
                            <?php else: ?>
                                Sistem sedang menunggu notifikasi pembayaran QRIS dari gateway pembayaran.
                            <?php endif; ?>
                        </p>

                        <div class="d-flex flex-column gap-2">
                            <button id="btnCheckStatus" class="btn btn-primary rounded-pill py-2.5 fw-bold shadow-sm" onclick="checkStatus()">
                                <i class="bi bi-arrow-repeat me-1"></i> Refresh Status Sekarang
                            </button>
                            <a href="/customer/orders/<?= (int)$order['id'] ?>" class="btn btn-outline-secondary rounded-pill py-2 small">
                                Kembali ke Detail Pesanan
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<script>
let pollInterval = setInterval(checkStatus, 4000);

function checkStatus() {
    fetch('/payment/<?= (int)$order['id'] ?>/poll', {
        headers: { 'Accept': 'application/json' }
    })
    .then(res => res.json())
    .then(data => {
        if (data.payment_status === 'paid') {
            clearInterval(pollInterval);
            window.location.href = '/payment/<?= (int)$order['id'] ?>/success';
        } else if (data.payment_status === 'expired') {
            clearInterval(pollInterval);
            window.location.href = '/payment/<?= (int)$order['id'] ?>/expired';
        }
    })
    .catch(() => {});
}
</script>
