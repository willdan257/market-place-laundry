<section class="py-4 bg-light min-vh-100">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-8 col-lg-6">
                <div class="card border-0 shadow-lg rounded-4 overflow-hidden">
                    <div class="card-header bg-dark text-white p-4">
                        <h4 class="fw-bold mb-1"><i class="bi bi-person-plus-fill text-warning me-2"></i>Penugasan Kurir</h4>
                        <p class="mb-0 small text-white-50">Order #<?= htmlspecialchars($order['order_number']) ?></p>
                    </div>

                    <div class="card-body p-4 p-lg-5">
                        <!-- Order Summary -->
                        <div class="p-3 bg-light rounded-3 border mb-4">
                            <span class="text-muted small fw-semibold text-uppercase d-block mb-1">Informasi Pemesan</span>
                            <h6 class="fw-bold text-dark mb-1"><?= htmlspecialchars($order['recipient_name']) ?> (<?= htmlspecialchars($order['recipient_phone']) ?>)</h6>
                            <p class="text-secondary small mb-2"><?= htmlspecialchars($order['pickup_address']) ?></p>
                            <small class="text-muted">RT <?= htmlspecialchars($order['rt']) ?> / RW <?= htmlspecialchars($order['rw']) ?>, <?= htmlspecialchars($order['district'] ?? '') ?></small>
                        </div>

                        <form method="POST" action="/owner/orders/<?= $order['id'] ?>/assign">
                            <input type="hidden" name="_token" value="<?= \App\Helpers\Session::csrfToken() ?>">

                            <div class="mb-4">
                                <label class="form-label fw-bold text-dark">Pilih Kurir Penjemput <span class="text-danger">*</span></label>
                                <select name="driver_id" class="form-select rounded-3 py-2.5" required>
                                    <option value="" disabled selected>-- Pilih Kurir dari Daftar --</option>
                                    <?php foreach ($drivers as $dr): ?>
                                        <?php $availLabel = ((int) ($dr['is_available'] ?? 0) === 1) ? 'Tersedia' : 'Sedang bertugas'; ?>
                                        <option value="<?= $dr['id'] ?>" <?= ($order['pickup_driver_id'] == $dr['id']) ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($dr['driver_name']) ?> (<?= htmlspecialchars($dr['jenis_kendaraan'] ?? $dr['vehicle_type'] ?? '-') ?> - <?= htmlspecialchars($dr['plat_nomor'] ?? $dr['license_plate'] ?? '-') ?>) — [<?= $availLabel ?>]
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="d-flex justify-content-between align-items-center pt-3 border-top">
                                <a href="/owner/orders" class="btn btn-outline-secondary rounded-pill px-4 fw-medium">
                                    <i class="bi bi-arrow-left me-1"></i> Batal
                                </a>
                                <button type="submit" class="btn btn-warning rounded-pill px-5 py-2.5 fw-bold text-dark shadow-sm">
                                    <i class="bi bi-check-circle me-1"></i> Penugasan Kurir
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
