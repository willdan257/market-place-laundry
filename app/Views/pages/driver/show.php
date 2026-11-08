<section class="py-4 bg-light min-vh-100">
    <div class="container">
        <div class="mb-3">
            <a href="/driver/orders" class="text-decoration-none text-muted small fw-semibold">
                <i class="bi bi-arrow-left me-1"></i> Kembali ke Daftar Tugas Kurir
            </a>
        </div>

        <div class="row g-4">
            <!-- Customer & Pickup Info -->
            <div class="col-lg-7">
                <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4">
                    <div class="card-header bg-info text-white p-4">
                        <span class="badge bg-white text-info rounded-pill px-3 py-1 mb-2 fw-bold">
                            TUGAS KURIR #<?= htmlspecialchars($order['order_number']) ?>
                        </span>
                        <h4 class="fw-bold mb-1">Penjemputan Laundry Pelanggan</h4>
                        <p class="mb-0 small text-white-50">Toko Tujuan: <?= htmlspecialchars($order['store_name']) ?></p>
                    </div>

                    <div class="card-body p-4">
                        <h5 class="fw-bold mb-3"><i class="bi bi-person-circle text-primary me-2"></i>Informasi Pelanggan</h5>
                        <div class="p-3 bg-light rounded-3 border mb-4">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <span class="text-muted small d-block">Nama Pelanggan:</span>
                                    <strong class="text-dark fs-6"><?= htmlspecialchars($order['recipient_name']) ?></strong>
                                </div>
                                <div class="col-md-6">
                                    <span class="text-muted small d-block">Nomor Telepon / WA:</span>
                                    <a href="tel:<?= htmlspecialchars($order['recipient_phone']) ?>" class="fw-bold text-success text-decoration-none fs-6">
                                        <i class="bi bi-telephone-fill me-1"></i><?= htmlspecialchars($order['recipient_phone']) ?>
                                    </a>
                                </div>
                            </div>
                        </div>

                        <h5 class="fw-bold mb-3"><i class="bi bi-geo-alt-fill text-danger me-2"></i>Alamat Lengkap Penjemputan</h5>
                        <div class="p-3 bg-light rounded-3 border mb-4">
                            <p class="fw-bold text-dark fs-6 mb-2"><?= htmlspecialchars($order['pickup_address']) ?></p>
                            <div class="row g-2 text-muted small mb-3">
                                <div class="col-6">RT: <strong><?= htmlspecialchars($order['rt']) ?></strong></div>
                                <div class="col-6">RW: <strong><?= htmlspecialchars($order['rw']) ?></strong></div>
                                <div class="col-6">Kecamatan: <strong><?= htmlspecialchars($order['district'] ?? '-') ?></strong></div>
                                <div class="col-6">Kota: <strong><?= htmlspecialchars($order['city'] ?? '-') ?></strong></div>
                            </div>

                            <div class="p-2.5 bg-white border rounded-3 d-flex align-items-center justify-content-between">
                                <span class="small text-muted">
                                    <i class="bi bi-pin-map-fill text-danger me-1"></i> Titik Koordinat: <?= htmlspecialchars($order['latitude'] ?? '-') ?>, <?= htmlspecialchars($order['longitude'] ?? '-') ?>
                                </span>
                                <a href="https://www.google.com/maps/search/?api=1&query=<?= $order['latitude'] ?>,<?= $order['longitude'] ?>" target="_blank" class="btn btn-sm btn-outline-primary rounded-pill px-3 fw-medium">
                                    Buka Google Maps <i class="bi bi-box-arrow-up-right ms-1"></i>
                                </a>
                            </div>
                        </div>

                        <?php if (!empty($order['notes'])): ?>
                            <div class="p-3 bg-warning-subtle text-dark border-warning rounded-3">
                                <strong class="d-block small text-uppercase mb-1"><i class="bi bi-sticky me-1"></i> Catatan Khusus Customer:</strong>
                                <p class="mb-0 small fs-italic">"<?= htmlspecialchars($order['notes']) ?>"</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Trip Status Update Control Panel -->
            <div class="col-lg-5">
                <?php
                $isPickupDriver   = (int)($order['pickup_driver_id'] ?? 0) === (int)$driver['id'];
                $isDeliveryDriver = (int)($order['delivery_driver_id'] ?? 0) === (int)$driver['id'];
                $pickupDone       = in_array($order['order_status'], \App\Models\Order::PICKUP_DONE_STATUSES, true);
                // Pengantaran baru benar-benar selesai bila status sudah completed / cancelled
                $deliveryDone     = in_array($order['order_status'], ['completed', 'cancelled'], true);
                $isStageDone      = false;

                if ($isPickupDriver && !$isDeliveryDriver && $pickupDone) {
                    $isStageDone = true;
                    $stageDoneMsg = "Tahap penjemputan untuk pesanan ini telah selesai. Halaman ini hanya untuk membaca informasi (Hanya Baca).";
                } elseif ($isDeliveryDriver && !$isPickupDriver && $deliveryDone) {
                    $isStageDone = true;
                    $stageDoneMsg = "Tahap pengantaran untuk pesanan ini telah selesai. Halaman ini hanya untuk membaca informasi (Hanya Baca).";
                } elseif ($isPickupDriver && $isDeliveryDriver && $deliveryDone) {
                    $isStageDone = true;
                    $stageDoneMsg = "Semua tahap tugas Anda untuk pesanan ini telah selesai (Hanya Baca).";
                }
                ?>

                <!-- CASH PAYMENT CONFIRM (Kurir konfirmasi terima uang tunai) -->
                <?php
                $driverPaymentModel = new \App\Models\Payment();
                $driverPayment = $driverPaymentModel->findByOrderId((int)$order['id']);
                if ($driverPayment && $driverPayment['payment_method'] === 'cash' && $driverPayment['payment_status'] === 'pending'):
                ?>
                    <div class="card border-success rounded-4 shadow-sm mb-4">
                        <div class="card-header bg-success text-white fw-bold rounded-top-4">
                            <i class="bi bi-cash-coin me-2"></i>Konfirmasi Terima Uang Tunai (CASH)
                        </div>
                        <div class="card-body p-4">
                            <p class="text-muted small mb-3">
                                Pelanggan memilih metode tunai (COD). Tagihan yang harus ditagih:
                                <strong class="text-dark fs-5 d-block mt-1">Rp <?= number_format($driverPayment['amount'], 0, ',', '.') ?></strong>
                                Klik tombol di bawah ini jika pelanggan telah menyerahkan uang tunai pas.
                            </p>
                            <form method="POST" action="/payment/cash/<?= (int)$order['id'] ?>/confirm">
                                <input type="hidden" name="_token" value="<?= htmlspecialchars(\App\Helpers\Session::csrfToken()) ?>">
                                <button type="submit" class="btn btn-success fw-bold px-4 rounded-pill shadow-sm"
                                        onclick="return confirm('Konfirmasi bahwa uang tunai sebesar Rp <?= number_format($driverPayment['amount'], 0) ?> telah Anda terima dari pelanggan?')">
                                    <i class="bi bi-check-circle me-1"></i> Konfirmasi Uang Diterima
                                </button>
                            </form>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="card border-0 shadow-sm rounded-4 p-4 mb-4">
                    <h5 class="fw-bold mb-3"><i class="bi bi-speedometer2 text-info me-2"></i>Status Perjalanan</h5>
                    
                    <div class="alert alert-secondary py-2.5 small mb-3">
                        Status Terkini: <strong><?= htmlspecialchars($order['order_status']) ?></strong>
                    </div>

                    <?php if ($isStageDone): ?>
                        <div class="alert alert-success py-3 px-3 rounded-3 small mb-0">
                            <i class="bi bi-check-circle-fill text-success me-2 fs-5 align-middle"></i>
                            <strong>Tugas Selesai (Hanya Baca)</strong>
                            <p class="mb-0 mt-1 text-muted"><?= htmlspecialchars($stageDoneMsg) ?></p>
                        </div>
                    <?php else: ?>
                        <form method="POST" action="/driver/orders/<?= $order['id'] ?>/status">
                            <input type="hidden" name="_token" value="<?= \App\Helpers\Session::csrfToken() ?>">

                            <div class="d-grid gap-2.5 mb-3">
                                <?php if ($isPickupDriver && !$pickupDone): ?>
                                    <button type="submit" name="new_status" value="driver_on_the_way" class="btn btn-outline-info text-dark py-2.5 rounded-pill text-start px-3 font-semibold <?= ($order['order_status'] === 'driver_on_the_way') ? 'active bg-info text-white' : '' ?>">
                                        <i class="bi bi-record-circle me-2"></i>1. DRIVER ON THE WAY (Dalam Perjalanan)
                                    </button>
                                    <button type="submit" name="new_status" value="arrived_at_customer" class="btn btn-outline-primary py-2.5 rounded-pill text-start px-3 font-semibold <?= ($order['order_status'] === 'arrived_at_customer') ? 'active bg-primary text-white' : '' ?>">
                                        <i class="bi bi-geo-fill me-2"></i>2. ARRIVED AT CUSTOMER (Tiba di Lokasi)
                                    </button>
                                    <button type="submit" name="new_status" value="picked_up" class="btn btn-outline-warning text-dark py-2.5 rounded-pill text-start px-3 font-semibold <?= ($order['order_status'] === 'picked_up') ? 'active bg-warning text-dark' : '' ?>">
                                        <i class="bi bi-box-seam me-2"></i>3. PICKED UP (Pakaian Dijemput)
                                    </button>
                                    <button type="submit" name="new_status" value="arrived_at_store" class="btn btn-outline-success py-2.5 rounded-pill text-start px-3 font-semibold <?= ($order['order_status'] === 'arrived_at_store') ? 'active bg-success text-white' : '' ?>">
                                        <i class="bi bi-building-check me-2"></i>4. ARRIVED AT STORE (Sampai di Toko)
                                    </button>
                                <?php endif; ?>

                                <?php if ($isDeliveryDriver && in_array($order['order_status'], ['delivery_assigned', 'ready_for_delivery', 'delivered'])): ?>
                                    <button type="submit" name="new_status" value="delivered" class="btn btn-outline-primary py-2.5 rounded-pill text-start px-3 font-semibold <?= ($order['order_status'] === 'delivered') ? 'active bg-primary text-white' : '' ?>">
                                        <i class="bi bi-check2-circle me-2"></i>5. DELIVERED (Laundry Telah Diantar)
                                    </button>

                                    <?php if ($driverPayment && $driverPayment['payment_method'] === 'cash' && $driverPayment['payment_status'] !== 'paid'): ?>
                                        <div class="form-check p-3 bg-warning-subtle border border-warning rounded-3 my-2 text-start">
                                            <input class="form-check-input ms-0 me-2" type="checkbox" name="confirm_cash" id="driver_confirm_cash" value="1" required>
                                            <label class="form-check-label small fw-bold text-dark" for="driver_confirm_cash">
                                                <i class="bi bi-cash-coin text-success me-1"></i> Uang tunai Rp <?= number_format($driverPayment['amount'], 0, ',', '.') ?> telah saya terima dari pelanggan <span class="text-danger">*</span>
                                            </label>
                                            <div class="small text-muted mt-1" style="font-size:0.75rem;">Wajib dicentang untuk menyelesaikan pesanan COD. Status pembayaran akan otomatis menjadi Lunas.</div>
                                        </div>
                                        <button type="submit" name="new_status" value="completed" class="btn btn-outline-success py-2.5 rounded-pill text-start px-3 font-semibold">
                                            <i class="bi bi-flag-fill me-2"></i>6. COMPLETED (Konfirmasi Uang Tunai & Selesai)
                                        </button>
                                    <?php else: ?>
                                        <button type="submit" name="new_status" value="completed" class="btn btn-outline-success py-2.5 rounded-pill text-start px-3 font-semibold <?= ($order['order_status'] === 'completed') ? 'active bg-success text-white' : '' ?>">
                                            <i class="bi bi-flag-fill me-2"></i>6. COMPLETED (Selesai Keseluruhan)
                                        </button>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>

                            <div class="mb-3">
                                <label class="form-label small fw-semibold text-secondary">Catatan Perjalanan Tambahan (Opsional)</label>
                                <input type="text" name="status_notes" class="form-control rounded-3" placeholder="Contoh: Sudah konfirmasi dengan pemesan via WA...">
                            </div>
                        </form>
                    <?php endif; ?>
                </div>

                <!-- History Audit Trail -->
                <div class="card border-0 shadow-sm rounded-4 p-4">
                    <h6 class="fw-bold mb-3"><i class="bi bi-clock-history me-2"></i>Riwayat Perubahan Status</h6>
                    <?php if (!empty($order['histories'])): ?>
                        <ul class="list-group list-group-flush small">
                            <?php foreach ($order['histories'] as $hist): ?>
                                <li class="list-group-item bg-transparent px-0 py-2">
                                    <strong class="text-dark d-block"><?= htmlspecialchars($hist['new_status']) ?></strong>
                                    <span class="text-muted"><?= htmlspecialchars($hist['notes'] ?? '') ?></span>
                                    <small class="text-muted d-block" style="font-size:0.7rem;"><?= date('d M Y H:i', strtotime($hist['created_at'])) ?></small>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</section>
