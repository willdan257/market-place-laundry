<section class="py-5 bg-light min-vh-100">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-7 col-lg-5">
                <div class="card border-0 shadow-sm rounded-4 overflow-hidden text-center">
                    <div class="card-header bg-success text-white p-4">
                        <div class="rounded-circle bg-white text-success d-inline-flex align-items-center justify-content-center mb-2" style="width: 56px; height: 56px;">
                            <i class="bi bi-check-lg fs-2"></i>
                        </div>
                        <h5 class="fw-bold mb-1 text-white">Pembayaran Berhasil!</h5>
                        <p class="mb-0 small text-white-50">Terima kasih, pembayaran Anda telah diterima</p>
                    </div>
                    <div class="card-body p-4 bg-white">
                        <span class="badge bg-light text-dark border rounded-pill px-3 py-1.5 mb-3">
                            ORDER #<?= htmlspecialchars($order['order_number']) ?>
                        </span>

                        <div class="p-3 bg-light rounded-3 text-start mb-4">
                            <div class="d-flex justify-content-between mb-2">
                                <span class="text-muted small">Toko Laundry</span>
                                <span class="fw-semibold small text-dark"><?= htmlspecialchars($order['store_name']) ?></span>
                            </div>
                            <div class="d-flex justify-content-between mb-2">
                                <span class="text-muted small">Metode Pembayaran</span>
                                <span class="badge bg-primary rounded-pill"><?= strtoupper(htmlspecialchars($payment['payment_method'])) ?></span>
                            </div>
                            <div class="d-flex justify-content-between mb-2">
                                <span class="text-muted small">Waktu Bayar</span>
                                <span class="small text-dark"><?= date('d M Y, H:i', strtotime($payment['paid_at'] ?? 'now')) ?> WIB</span>
                            </div>
                            <hr class="my-2">
                            <div class="d-flex justify-content-between">
                                <span class="fw-bold">Total Dibayar</span>
                                <span class="fw-bold text-success fs-6">Rp <?= number_format($order['total_amount'], 0, ',', '.') ?></span>
                            </div>
                        </div>

                        <div class="alert alert-info py-2 small mb-4">
                            <i class="bi bi-info-circle me-1"></i> Cucian Anda sedang dalam proses pengerjaan oleh tim laundry.
                        </div>

                        <div class="d-flex flex-column gap-2">
                            <a href="/customer/orders/<?= (int)$order['id'] ?>" class="btn btn-primary rounded-pill py-2.5 fw-bold shadow-sm">
                                <i class="bi bi-eye me-1"></i> Lihat Progress Order
                            </a>
                            <a href="/customer/orders" class="btn btn-outline-secondary rounded-pill py-2 small">
                                Ke Daftar Pesanan
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
