<section class="py-4 bg-light min-vh-100">
    <div class="container">
        <div class="mb-3">
            <a href="/owner/orders" class="text-decoration-none text-muted small fw-semibold">
                <i class="bi bi-arrow-left me-1"></i> Kembali ke Daftar Pesanan
            </a>
        </div>

        <?php
        $statusLabels = \App\Helpers\OrderStatusHelper::all();
        $badge = \App\Helpers\OrderStatusHelper::getBadge($order['order_status']);
        ?>

        <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4">
            <div class="card-header bg-dark text-white p-4">
                <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
                    <div>
                        <span class="badge bg-warning text-dark rounded-pill px-3 py-1 mb-2 fw-bold">
                            ORDER #<?= htmlspecialchars($order['order_number']) ?>
                        </span>
                        <h4 class="fw-bold mb-1"><?= htmlspecialchars($order['store_name']) ?></h4>
                        <small class="text-white-50">
                            <i class="bi bi-calendar3 me-1"></i> Dibuat: <?= date('d F Y, H:i', strtotime($order['created_at'])) ?> WIB
                        </small>
                    </div>
                    <span class="badge <?= $badge[0] ?> fs-6 rounded-pill px-4 py-2 fw-semibold">
                        <?= $badge[1] ?>
                    </span>
                </div>
            </div>

            <div class="card-body p-4">
                <div class="row g-4">
                    <!-- Left: Customer & Service Info -->
                    <div class="col-lg-7">
                        <!-- Customer Info -->
                        <h5 class="fw-bold mb-3"><i class="bi bi-person-circle text-primary me-2"></i>Informasi Pelanggan</h5>
                        <div class="p-3 bg-light rounded-3 border mb-4">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <p class="fw-bold text-dark mb-1"><?= htmlspecialchars($order['recipient_name'] ?? '-') ?></p>
                                    <p class="text-muted small mb-1"><i class="bi bi-telephone me-1"></i><?= htmlspecialchars($order['recipient_phone'] ?? '-') ?></p>
                                    <p class="text-muted small mb-0"><i class="bi bi-geo-alt me-1"></i><?= htmlspecialchars($order['pickup_address'] ?? '-') ?> RT <?= htmlspecialchars($order['rt'] ?? '') ?>/RW <?= htmlspecialchars($order['rw'] ?? '') ?>, <?= htmlspecialchars($order['district'] ?? '') ?>, <?= htmlspecialchars($order['city'] ?? '') ?></p>
                                </div>
                            </div>
                        </div>

                        <!-- Service & Weight -->
                        <h5 class="fw-bold mb-3"><i class="bi bi-bag-check-fill text-warning me-2"></i>Detail Layanan & Tagihan</h5>
                        <div class="p-3 bg-light rounded-3 border mb-4">
                            <?php if (!empty($order['items'])): ?>
                                <?php foreach ($order['items'] as $item): ?>
                                    <div class="d-flex justify-content-between align-items-center mb-2 pb-2 border-bottom">
                                        <div>
                                            <strong class="text-dark d-block"><?= htmlspecialchars($item['service_name']) ?></strong>
                                            <small class="text-muted">Rp <?= number_format($item['price_per_unit'], 0, ',', '.') ?> / <?= htmlspecialchars($item['unit']) ?></small>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>

                            <div class="row g-2 mt-2">
                                <div class="col-6">
                                    <div class="text-muted small">Berat Laundry</div>
                                    <div class="fw-bold <?= $order['weight_kg'] > 0 ? 'text-dark' : 'text-muted' ?>">
                                        <?= $order['weight_kg'] > 0 ? $order['weight_kg'] . ' kg' : 'Belum ditimbang' ?>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="text-muted small">Ongkir (<?= number_format($order['distance_km'] ?? 0, 1) ?> km)</div>
                                    <div class="fw-bold text-primary">Rp <?= number_format($order['shipping_fee'] ?? 0, 0, ',', '.') ?></div>
                                </div>
                                <div class="col-12 mt-2 pt-2 border-top">
                                    <div class="d-flex justify-content-between">
                                        <strong class="fs-5">TOTAL TAGIHAN</strong>
                                        <strong class="fs-5 text-success">Rp <?= number_format($order['total_amount'] ?? 0, 0, ',', '.') ?></strong>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- WEIGHING FORM (Only shown when arrived_at_store) -->
                        <?php if ($order['order_status'] === 'arrived_at_store'): ?>
                            <div class="card border-warning rounded-4 shadow-sm mb-4">
                                <div class="card-header bg-warning text-dark fw-bold rounded-top-4">
                                    <i class="bi bi-rulers me-2"></i>Input Berat Laundry (Penimbangan)
                                </div>
                                <div class="card-body p-4">
                                    <p class="text-muted small mb-3">Masukkan berat total laundry setelah ditimbang. Sistem akan otomatis menghitung invoice dan memberitahu pelanggan.</p>
                                    <form method="POST" action="/owner/orders/<?= (int)$order['id'] ?>/weigh">
                                        <input type="hidden" name="_token" value="<?= htmlspecialchars(\App\Helpers\Session::csrfToken()) ?>">
                                        <div class="input-group mb-3">
                                            <input type="number" class="form-control form-control-lg rounded-start-pill" name="weight_kg"
                                                   id="weightInput" placeholder="e.g. 3.5" step="0.01" min="0.01" max="500" required
                                                   aria-label="Berat dalam kg">
                                            <span class="input-group-text bg-warning text-dark fw-bold">kg</span>
                                            <button type="submit" class="btn btn-warning fw-bold px-4 rounded-end-pill">
                                                <i class="bi bi-check2-circle me-1"></i> Konfirmasi Timbang
                                            </button>
                                        </div>
                                        <small class="text-muted">Setelah submit, status order akan berubah ke <strong>"Selesai Ditimbang"</strong> dan invoice akan dibuat.</small>
                                    </form>
                                </div>
                            </div>
                        <?php endif; ?>

                        <!-- CASH PAYMENT CONFIRM (Owner confirms cash) -->
                        <?php
                        $paymentModel = new \App\Models\Payment();
                        $payment = $paymentModel->findByOrderId((int)$order['id']);
                        if ($payment && $payment['payment_method'] === 'cash' && $payment['payment_status'] === 'pending'):
                        ?>
                            <div class="card border-success rounded-4 shadow-sm mb-4">
                                <div class="card-header bg-success text-white fw-bold rounded-top-4">
                                    <i class="bi bi-cash-coin me-2"></i>Konfirmasi Pembayaran CASH
                                </div>
                                <div class="card-body p-4">
                                    <p class="text-muted small mb-3">
                                        Pelanggan memilih pembayaran tunai. Jumlah tagihan:
                                        <strong>Rp <?= number_format($payment['amount'], 0, ',', '.') ?></strong>.
                                        Konfirmasikan setelah menerima uang tunai dari pelanggan.
                                    </p>
                                    <form method="POST" action="/payment/cash/<?= (int)$order['id'] ?>/confirm">
                                        <input type="hidden" name="_token" value="<?= htmlspecialchars(\App\Helpers\Session::csrfToken()) ?>">
                                        <button type="submit" class="btn btn-success fw-bold px-4 rounded-pill"
                                                onclick="return confirm('Konfirmasi penerimaan pembayaran CASH Rp <?= number_format($payment['amount'], 0) ?> dari pelanggan?')">
                                            <i class="bi bi-check-circle me-1"></i> Konfirmasi Terima Uang Tunai
                                        </button>
                                    </form>
                                </div>
                            </div>
                        <?php endif; ?>
                        <!-- IN PROCESS ACTION (Owner marks ready for delivery) -->
                        <?php if ($order['order_status'] === 'in_process'): ?>
                            <div class="card border-primary rounded-4 shadow-sm mb-4">
                                <div class="card-header bg-primary text-white fw-bold rounded-top-4">
                                    <i class="bi bi-check2-all me-2"></i>Selesai Proses Cuci & Setrika
                                </div>
                                <div class="card-body p-4">
                                    <p class="text-muted small mb-3">Laundry pelanggan telah selesai dicuci dan disetrika? Tandai sebagai Siap Diantar untuk menugaskan kurir.</p>
                                    <form method="POST" action="/owner/orders/<?= (int)$order['id'] ?>/status">
                                        <input type="hidden" name="_token" value="<?= htmlspecialchars(\App\Helpers\Session::csrfToken()) ?>">
                                        <input type="hidden" name="new_status" value="ready_for_delivery">
                                        <button type="submit" class="btn btn-primary fw-bold px-4 rounded-pill">
                                            <i class="bi bi-box-seam me-1"></i> Tandai Siap Diantar
                                        </button>
                                    </form>
                                </div>
                            </div>
                        <?php endif; ?>

                        <!-- READY FOR DELIVERY ACTION (Assign Delivery Driver) -->
                        <?php if ($order['order_status'] === 'ready_for_delivery'): ?>
                            <div class="card border-info rounded-4 shadow-sm mb-4">
                                <div class="card-header bg-info text-white fw-bold rounded-top-4">
                                    <i class="bi bi-bicycle me-2"></i>Pengantaran Laundry
                                </div>
                                <div class="card-body p-4">
                                    <p class="text-muted small mb-3">Laundry sudah siap diantar ke pelanggan. Silakan tugaskan kurir untuk pengiriman.</p>
                                    <a href="/owner/orders/<?= (int)$order['id'] ?>/assign" class="btn btn-info text-white fw-bold px-4 rounded-pill">
                                        <i class="bi bi-person-plus me-1"></i> Tugaskan Kurir Pengantaran
                                    </a>
                                </div>
                            </div>
                        <?php endif; ?>

                        <!-- DELIVERED ACTION (Owner marks completed) -->
                        <?php if ($order['order_status'] === 'delivered'): ?>
                            <div class="card border-success rounded-4 shadow-sm mb-4">
                                <div class="card-header bg-success text-white fw-bold rounded-top-4">
                                    <i class="bi bi-check-circle me-2"></i>Penyelesaian Pesanan
                                </div>
                                <div class="card-body p-4">
                                    <p class="text-muted small mb-3">Laundry telah diantar ke customer. Tandai pesanan sebagai selesai.</p>
                                    <form method="POST" action="/owner/orders/<?= (int)$order['id'] ?>/status">
                                        <input type="hidden" name="_token" value="<?= htmlspecialchars(\App\Helpers\Session::csrfToken()) ?>">
                                        <input type="hidden" name="new_status" value="completed">
                                        <?php if ($payment && $payment['payment_method'] === 'cash' && $payment['payment_status'] !== 'paid'): ?>
                                            <div class="form-check p-3 bg-warning-subtle border border-warning rounded-3 mb-3 text-start">
                                                <input class="form-check-input ms-0 me-2" type="checkbox" name="confirm_cash" id="owner_confirm_cash" value="1" required>
                                                <label class="form-check-label small fw-bold text-dark" for="owner_confirm_cash">
                                                    <i class="bi bi-cash-coin text-success me-1"></i> Uang tunai Rp <?= number_format($payment['amount'], 0, ',', '.') ?> telah diterima oleh toko <span class="text-danger">*</span>
                                                </label>
                                                <div class="small text-muted mt-1" style="font-size:0.75rem;">Wajib dicentang untuk menyelesaikan pesanan COD. Status pembayaran akan otomatis menjadi Lunas.</div>
                                            </div>
                                            <button type="submit" class="btn btn-success fw-bold px-4 rounded-pill">
                                                <i class="bi bi-check-circle-fill me-1"></i> Konfirmasi Uang Tunai & Selesaikan Pesanan
                                            </button>
                                        <?php else: ?>
                                            <button type="submit" class="btn btn-success fw-bold px-4 rounded-pill">
                                                <i class="bi bi-check-circle-fill me-1"></i> Konfirmasi Pesanan Selesai
                                            </button>
                                        <?php endif; ?>
                                    </form>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Right: Status History -->
                    <div class="col-lg-5">
                        <h5 class="fw-bold mb-3"><i class="bi bi-clock-history text-info me-2"></i>Riwayat Status Order</h5>
                        <div class="p-3 bg-light rounded-3 border">
                            <?php if (!empty($order['histories'])): ?>
                                <div class="timeline ps-2 border-start border-2 border-primary ms-2 py-2">
                                    <?php foreach ($order['histories'] as $hist): ?>
                                        <div class="timeline-item position-relative ps-4 mb-3">
                                            <div class="position-absolute start-0 top-0 translate-middle-x bg-primary rounded-circle" style="width: 12px; height: 12px; margin-left: -1px;"></div>
                                            <h6 class="fw-bold text-dark mb-1 small">
                                                <?= htmlspecialchars($statusLabels[$hist['new_status']][1] ?? $hist['new_status']) ?>
                                            </h6>
                                            <p class="text-muted small mb-1"><?= htmlspecialchars($hist['notes'] ?? '') ?></p>
                                            <small class="text-muted d-block" style="font-size: 0.73rem;">
                                                <i class="bi bi-clock me-1"></i><?= date('d M Y, H:i', strtotime($hist['created_at'])) ?>
                                            </small>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <p class="text-muted small mb-0">Belum ada riwayat status.</p>
                            <?php endif; ?>
                        </div>

                        <!-- Action Buttons -->
                        <div class="mt-3 d-flex gap-2 flex-wrap">
                            <?php if (in_array($order['order_status'], ['pending_pickup', 'driver_assigned', 'ready_for_delivery', 'delivery_assigned'])): ?>
                                <a href="/owner/orders/<?= (int)$order['id'] ?>/assign" class="btn btn-warning rounded-pill px-4 fw-bold text-dark shadow-sm">
                                    <i class="bi bi-person-plus me-1"></i> <?= in_array($order['order_status'], ['ready_for_delivery', 'delivery_assigned']) ? 'Tugaskan Kurir Pengantaran' : 'Assign / Ganti Kurir' ?>
                                </a>
                            <?php endif; ?>
                            <a href="/owner/orders" class="btn btn-outline-secondary rounded-pill px-4">
                                <i class="bi bi-list me-1"></i> Semua Pesanan
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
