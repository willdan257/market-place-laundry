<section class="py-4 bg-light min-vh-100">
    <div class="container">
        <!-- Breadcrumb & Back Link -->
        <div class="mb-3 d-flex flex-wrap justify-content-between align-items-center gap-2">
            <a href="/customer/orders" class="text-decoration-none text-muted small fw-semibold">
                <i class="bi bi-arrow-left me-1"></i> Kembali ke Riwayat Order
            </a>
            <a href="/customer/assistant?order=<?= (int) $order['id'] ?>" class="btn btn-sm btn-outline-primary rounded-pill px-3">
                <i class="bi bi-robot me-1"></i> Tanya AI tentang pesanan ini
            </a>
        </div>

        <!-- In-App Notifications Banner if any -->
        <?php
        $notifModel = new \App\Models\Notification();
        $userNotifs = $notifModel->getUserNotifications($user['id']);
        $unreadNotifs = array_filter($userNotifs, fn($n) => $n['reference_id'] == $order['id'] && !$n['is_read']);
        ?>
        <?php if (!empty($unreadNotifs)): ?>
            <?php foreach ($unreadNotifs as $un): ?>
                <div class="alert alert-info alert-dismissible fade show rounded-4 border-0 shadow-sm mb-3" role="alert">
                    <i class="bi bi-bell-fill me-2 text-primary"></i>
                    <strong><?= htmlspecialchars($un['title']) ?>:</strong> <?= htmlspecialchars($un['message']) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>

        <?php
        $showPaymentModel = new \App\Models\Payment();
        $showPayment = $showPaymentModel->findByOrderId((int)$order['id']);
        ?>

        <?php if ($showPayment && $showPayment['payment_method'] === 'cash' && $showPayment['payment_status'] === 'pending'): ?>
            <div class="alert alert-warning d-flex flex-wrap justify-content-between align-items-center rounded-4 shadow-sm mb-3 p-3">
                <div class="mb-2 mb-md-0">
                    <h6 class="fw-bold mb-1 text-dark"><i class="bi bi-cash-coin me-2 text-success fs-5"></i>Metode Pembayaran: COD (Bayar Tunai)</h6>
                    <span class="small text-muted">Status Pembayaran: <strong class="badge bg-warning text-dark px-2 py-1">COD - belum dibayar</strong>. Siapkan uang pas <strong>Rp <?= number_format($order['total_amount'], 0, ',', '.') ?></strong> saat kurir tiba atau di toko.</span>
                </div>
            </div>
        <?php elseif ($showPayment && $showPayment['payment_status'] === 'paid'): ?>
            <div class="alert alert-success d-flex flex-wrap justify-content-between align-items-center rounded-4 shadow-sm mb-3 p-3">
                <div class="mb-2 mb-md-0">
                    <h6 class="fw-bold mb-1 text-success"><i class="bi bi-check-circle-fill me-2 fs-5"></i>Pembayaran Lunas</h6>
                    <span class="small text-muted">Metode: <strong><?= strtoupper($showPayment['payment_method']) ?></strong>. Tagihan sebesar Rp <?= number_format($showPayment['amount'], 0, ',', '.') ?> telah terbayar.</span>
                </div>
            </div>
        <?php elseif (in_array($order['order_status'], ['weighed', 'waiting_payment'])): ?>
            <div class="alert alert-success d-flex flex-wrap justify-content-between align-items-center rounded-4 shadow-sm mb-3 p-3">
                <div class="mb-2 mb-md-0">
                    <h6 class="fw-bold mb-1 text-success"><i class="bi bi-wallet2 me-2"></i>Pembayaran Siap Dilakukan</h6>
                    <span class="small text-muted">Pakaian telah selesai ditimbang oleh pemilik toko. Total: <strong>Rp <?= number_format($order['total_amount'], 0, ',', '.') ?></strong></span>
                </div>
                <a href="/payment/<?= (int)$order['id'] ?>" class="btn btn-success rounded-pill px-4 fw-bold shadow-sm">
                    Bayar Sekarang <i class="bi bi-arrow-right ms-1"></i>
                </a>
            </div>
        <?php endif; ?>

        <?php if ($order['order_status'] === 'delivered'): ?>
            <div class="alert alert-primary d-flex flex-wrap justify-content-between align-items-center rounded-4 shadow-sm mb-3 p-3">
                <div class="mb-2 mb-md-0">
                    <h6 class="fw-bold mb-1 text-primary"><i class="bi bi-box2-heart me-2"></i>Laundry Telah Diantar</h6>
                    <span class="small text-muted">Pakaian bersih Anda telah diantar oleh kurir. Silakan periksa dan konfirmasi jika sudah diterima.</span>
                </div>
                <form method="POST" action="/customer/orders/<?= (int)$order['id'] ?>/complete" class="m-0">
                    <input type="hidden" name="_token" value="<?= htmlspecialchars(\App\Helpers\Session::csrfToken()) ?>">
                    <button type="submit" class="btn btn-primary rounded-pill px-4 fw-bold shadow-sm" onclick="return confirm('Konfirmasi bahwa laundry telah Anda terima dengan baik?')">
                        <i class="bi bi-check-circle me-1"></i> Konfirmasi Terima & Selesai
                    </button>
                </form>
            </div>
        <?php endif; ?>

        <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4">
            <div class="card-header bg-dark text-white p-4">
                <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
                    <div>
                        <span class="badge bg-warning text-dark rounded-pill px-3 py-1 mb-2 fw-bold">
                            ORDER #<?= htmlspecialchars($order['order_number']) ?>
                        </span>
                        <h4 class="fw-bold mb-1"><?= htmlspecialchars($order['store_name']) ?></h4>
                        <small class="text-white-50"><i class="bi bi-calendar3 me-1"></i> Dibuat pada: <?= date('d F Y, H:i', strtotime($order['created_at'])) ?> WIB</small>
                    </div>
                    <div>
                        <?php
                        $statusBadges = [
                            'pending_pickup'       => ['bg-warning text-dark',   'Menunggu Kurir Ditugaskan'],
                            'driver_assigned'      => ['bg-info text-white',     'Kurir Ditugaskan'],
                            'driver_on_the_way'    => ['bg-info text-white',     'Kurir Dalam Perjalanan'],
                            'arrived_at_customer'  => ['bg-primary text-white',  'Kurir Tiba di Rumah Anda'],
                            'picked_up'            => ['bg-warning text-dark',   'Pakaian Telah Dijemput'],
                            'arrived_at_store'     => ['bg-success text-white',  'Sampai di Toko Laundry'],
                            'weighed'              => ['bg-secondary text-white','Sudah Ditimbang — Siap Bayar'],
                            'waiting_payment'      => ['bg-warning text-dark',   'Menunggu Pembayaran'],
                            'in_process'           => ['bg-primary text-white',  'Sedang Dicuci'],
                            'ready_for_delivery'   => ['bg-success text-white',  'Siap Diantar'],
                            'delivery_assigned'    => ['bg-info text-white',     'Kurir Pengiriman Ditugaskan'],
                            'delivered'            => ['bg-success text-white',  'Pakaian Sudah Diantar'],
                            'completed'            => ['bg-success text-white',  'Selesai'],
                            'cancelled'            => ['bg-danger text-white',   'Dibatalkan'],
                        ];
                        $badge = $statusBadges[$order['order_status']] ?? ['bg-secondary text-white', $order['order_status']];
                        ?>
                        <span class="badge <?= $badge[0] ?> fs-6 rounded-pill px-4 py-2 fw-semibold">
                            Status: <?= $badge[1] ?>
                        </span>
                    </div>
                </div>
            </div>

            <div class="card-body p-4">
                <div class="row g-4">
                    <!-- Left Column: Details & Driver Info -->
                    <div class="col-lg-7">
                        <!-- Driver Info Card -->
                        <h5 class="fw-bold mb-3"><i class="bi bi-bicycle text-info me-2"></i>Informasi Kurir Penjemput</h5>
                        <?php if (!empty($order['driver_name'])): ?>
                            <div class="p-3 bg-info-subtle border-info rounded-3 border mb-4">
                                <div class="d-flex align-items-center justify-content-between">
                                    <div>
                                        <h6 class="fw-bold text-dark mb-1"><?= htmlspecialchars($order['driver_name']) ?></h6>
                                        <p class="text-muted small mb-0"><i class="bi bi-telephone me-1"></i><?= htmlspecialchars($order['driver_phone'] ?? '-') ?></p>
                                        <small class="text-secondary"><?= htmlspecialchars($order['vehicle_type'] ?? 'Motor') ?> - <strong><?= htmlspecialchars($order['license_plate'] ?? '-') ?></strong></small>
                                    </div>
                                    <div>
                                        <a href="tel:<?= htmlspecialchars($order['driver_phone'] ?? '') ?>" class="btn btn-sm btn-info text-white rounded-pill px-3 fw-bold">
                                            <i class="bi bi-telephone-fill me-1"></i> Hubungi Kurir
                                        </a>
                                    </div>
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="alert alert-warning py-2.5 small mb-4">
                                <i class="bi bi-clock me-1"></i> Menunggu owner toko menugaskan kurir untuk penjemputan pakaian Anda.
                            </div>
                        <?php endif; ?>

                        <h5 class="fw-bold mb-3"><i class="bi bi-bag-check-fill text-primary me-2"></i>Rincian Layanan</h5>
                        <div class="p-3 bg-light rounded-3 border mb-4">
                            <?php if (!empty($order['items'])): ?>
                                <?php foreach ($order['items'] as $item): ?>
                                    <div class="d-flex justify-content-between align-items-center mb-2 pb-2 border-bottom">
                                        <div>
                                            <strong class="text-dark d-block"><?= htmlspecialchars($item['service_name']) ?></strong>
                                            <small class="text-muted">Tarif Satuan: Rp <?= number_format($item['price_per_unit'], 0, ',', '.') ?> / <?= htmlspecialchars($item['unit']) ?></small>
                                        </div>
                                        <div class="text-end">
                                            <span class="badge bg-secondary-subtle text-secondary rounded-pill">Penimbangan oleh Owner</span>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>

                            <div class="d-flex justify-content-between align-items-center mt-3">
                                <span class="text-muted small">Berat Laundry:</span>
                                <strong class="text-dark"><?= $order['weight_kg'] > 0 ? $order['weight_kg'] . ' kg' : 'Belum ditimbang' ?></strong>
                            </div>
                        </div>

                        <h5 class="fw-bold mb-3"><i class="bi bi-geo-alt-fill text-danger me-2"></i>Alamat Penjemputan</h5>
                        <div class="p-3 bg-light rounded-3 border mb-4">
                            <p class="fw-bold text-dark mb-1"><?= htmlspecialchars($order['recipient_name']) ?> (<?= htmlspecialchars($order['recipient_phone']) ?>)</p>
                            <p class="text-secondary small mb-2"><?= htmlspecialchars($order['pickup_address']) ?></p>
                            <div class="d-flex gap-3 text-muted small">
                                <span>RT: <strong><?= htmlspecialchars($order['rt']) ?></strong></span>
                                <span>RW: <strong><?= htmlspecialchars($order['rw']) ?></strong></span>
                                <span>Kecamatan: <strong><?= htmlspecialchars($order['district'] ?? '-') ?></strong></span>
                                <span>Kota: <strong><?= htmlspecialchars($order['city'] ?? '-') ?></strong></span>
                            </div>
                        </div>

                        <!-- Distance & Shipping Fee from Google Maps -->
                        <h5 class="fw-bold mb-3"><i class="bi bi-geo-fill text-success me-2"></i>Jarak & Ongkir (Google Maps)</h5>
                        <div class="row g-3 mb-4">
                            <div class="col-6">
                                <div class="p-3 rounded-3 border text-center bg-light">
                                    <div class="text-muted small mb-1">Jarak Tempuh</div>
                                    <div class="fw-bold fs-5 text-primary">
                                        <?= isset($order['distance_km']) && $order['distance_km'] > 0
                                            ? number_format($order['distance_km'], 1) . ' km'
                                            : '<span class="text-muted small">Belum dihitung</span>' ?>
                                    </div>
                                    <div class="text-muted" style="font-size:0.7rem;">via Google Maps Driving</div>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="p-3 rounded-3 border text-center bg-light">
                                    <div class="text-muted small mb-1">Ongkir</div>
                                    <div class="fw-bold fs-5 text-success">
                                        <?= isset($order['shipping_fee']) && $order['shipping_fee'] > 0
                                            ? 'Rp ' . number_format($order['shipping_fee'], 0, ',', '.')
                                            : '<span class="text-muted small">-</span>' ?>
                                    </div>
                                    <div class="text-muted" style="font-size:0.7rem;">Tarif dinamis berdasarkan jarak</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Right Column: Status Tracking Timeline -->
                    <div class="col-lg-5">
                        <h5 class="fw-bold mb-3"><i class="bi bi-clock-history text-info me-2"></i>Tracking Progress Status</h5>
                        <div class="p-3 bg-light rounded-3 border">
                            <?php if (!empty($order['histories'])): ?>
                                <div class="timeline ps-2 border-start border-2 border-primary ms-2 py-2">
                                    <?php foreach ($order['histories'] as $hist): ?>
                                        <div class="timeline-item position-relative ps-4 mb-3">
                                            <div class="position-absolute start-0 top-0 translate-middle-x bg-primary rounded-circle" style="width: 12px; height: 12px; margin-left: -1px;"></div>
                                            <h6 class="fw-bold text-dark mb-1">
                                                <?= htmlspecialchars($statusBadges[$hist['new_status']][1] ?? $hist['new_status']) ?>
                                            </h6>
                                            <p class="text-muted small mb-1"><?= htmlspecialchars($hist['notes'] ?? '') ?></p>
                                            <small class="text-muted d-block" style="font-size: 0.75rem;">
                                                <i class="bi bi-clock me-1"></i> <?= date('d M Y, H:i', strtotime($hist['created_at'])) ?>
                                            </small>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <p class="text-muted small mb-0">Belum ada riwayat status.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
