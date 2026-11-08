<section class="py-5 bg-light min-vh-100">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-8 col-lg-6">

                <!-- Invoice Summary Card -->
                <div class="card border-0 shadow-sm rounded-4 mb-4 overflow-hidden">
                    <div class="card-header bg-dark text-white p-4">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <span class="badge bg-warning text-dark rounded-pill px-3 mb-2 fw-bold">ORDER #<?= htmlspecialchars($order['order_number']) ?></span>
                                <h5 class="fw-bold mb-1 text-white"><?= htmlspecialchars($order['store_name']) ?></h5>
                                <p class="mb-0 small text-white-50">Laundry selesai ditimbang — pilih cara pembayaran</p>
                            </div>
                            <div class="text-end">
                                <div class="text-white-50 small">Total Tagihan</div>
                                <div class="fw-bold fs-3 text-warning">Rp <?= number_format($order['total_amount'], 0, ',', '.') ?></div>
                            </div>
                        </div>
                    </div>
                    <div class="card-body p-3 bg-white">
                        <div class="row g-2 text-center small text-muted">
                            <div class="col-4">
                                <div class="fw-semibold text-dark"><?= number_format($order['weight_kg'], 2) ?> kg</div>
                                <div>Berat Laundry</div>
                            </div>
                            <div class="col-4">
                                <div class="fw-semibold text-dark">Rp <?= number_format($order['subtotal_amount'], 0, ',', '.') ?></div>
                                <div>Subtotal Cuci</div>
                            </div>
                            <div class="col-4">
                                <div class="fw-semibold text-success">Rp <?= number_format($order['shipping_fee'], 0, ',', '.') ?></div>
                                <div>Ongkir (<?= number_format($order['distance_km'], 1) ?> km)</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Payment Method Selection -->
                <h5 class="fw-bold text-dark mb-3 text-center">Pilih Metode Pembayaran</h5>

                <div class="row g-3 mb-4">
                    <!-- QRIS Option -->
                    <div class="col-6">
                        <form method="POST" action="/payment/<?= $order['id'] ?>/qris">
                            <input type="hidden" name="_token" value="<?= \App\Helpers\Session::csrfToken() ?>">
                            <button type="submit" id="btn-qris"
                                class="card border-2 border-primary shadow-sm rounded-4 w-100 text-start p-4 hover-lift position-relative"
                                style="cursor:pointer; background: linear-gradient(135deg, #f0f4ff, #e8edff);">
                                <div class="d-flex align-items-center gap-3 mb-3">
                                    <div class="rounded-3 bg-primary text-white d-flex align-items-center justify-content-center" style="width:48px;height:48px;">
                                        <i class="bi bi-qr-code fs-4"></i>
                                    </div>
                                    <div>
                                        <div class="fw-bold text-dark fs-6">QRIS</div>
                                        <div class="text-muted" style="font-size:0.75rem;">Bayar Sekarang Online</div>
                                    </div>
                                </div>
                                <div class="text-muted small">
                                    <i class="bi bi-check-circle-fill text-success me-1"></i> GoPay, OVO, Dana, dll.<br>
                                    <i class="bi bi-clock-fill text-warning me-1"></i> Batas waktu <strong>60 menit</strong>
                                </div>
                                <span class="badge bg-primary text-white position-absolute top-0 end-0 m-2 rounded-pill px-2">Recommended</span>
                            </button>
                        </form>
                    </div>

                    <!-- CASH Option -->
                    <div class="col-6">
                        <form method="POST" action="/payment/<?= $order['id'] ?>/cash" onsubmit="document.getElementById('btn-cash').disabled = true; this.submit();">
                            <input type="hidden" name="_token" value="<?= \App\Helpers\Session::csrfToken() ?>">
                            <button type="submit" id="btn-cash"
                                class="card border-2 border-success shadow-sm rounded-4 w-100 text-start p-4 hover-lift"
                                style="cursor:pointer; background: linear-gradient(135deg, #f0fdf4, #dcfce7);">
                                <div class="d-flex align-items-center gap-3 mb-3">
                                    <div class="rounded-3 bg-success text-white d-flex align-items-center justify-content-center" style="width:48px;height:48px;">
                                        <i class="bi bi-cash-coin fs-4"></i>
                                    </div>
                                    <div>
                                        <div class="fw-bold text-dark fs-6">CASH (COD)</div>
                                        <div class="text-muted" style="font-size:0.75rem;">Bayar Tunai di Tempat</div>
                                    </div>
                                </div>
                                <div class="text-muted small">
                                    <i class="bi bi-check2-circle text-success me-1"></i> Langsung ke Detail Pesanan<br>
                                    <i class="bi bi-person-check-fill text-success me-1"></i> Konfirmasi Kurir / Owner
                                </div>
                            </button>
                        </form>
                    </div>
                </div>

                <!-- Security Notice -->
                <div class="text-center small text-muted">
                    <i class="bi bi-shield-lock-fill text-success me-1"></i>
                    Pembayaran QRIS diproses secara aman oleh <strong>Midtrans</strong>. Data pembayaran Anda terenkripsi.
                </div>
            </div>
        </div>
    </div>
</section>
