<section class="py-5 bg-light min-vh-100">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-7 col-lg-5">
                <div class="card border-0 shadow-sm rounded-4 overflow-hidden text-center">
                    <div class="card-header bg-danger text-white p-4">
                        <div class="rounded-circle bg-white text-danger d-inline-flex align-items-center justify-content-center mb-2" style="width: 56px; height: 56px;">
                            <i class="bi bi-x-lg fs-2"></i>
                        </div>
                        <h5 class="fw-bold mb-1 text-white">Pembayaran Kadaluarsa</h5>
                        <p class="mb-0 small text-white-50">Batas waktu pembayaran untuk transaksi ini telah habis</p>
                    </div>
                    <div class="card-body p-4 bg-white">
                        <p class="text-muted small mb-4">
                            Kode QRIS atau sesi pembayaran Anda telah kadaluarsa. Jangan khawatir, Anda dapat membuat sesi pembayaran baru untuk pesanan ini.
                        </p>

                        <div class="d-flex flex-column gap-2">
                            <a href="/payment/<?= (int)$order['id'] ?>" class="btn btn-primary rounded-pill py-2.5 fw-bold shadow-sm">
                                <i class="bi bi-arrow-repeat me-1"></i> Pilih Metode Bayar Ulang
                            </a>
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
