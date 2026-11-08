<section class="py-4 bg-light min-vh-100">
    <div class="container">
        <!-- Header -->
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
            <div>
                <h3 class="fw-bold mb-1"><i class="bi bi-basket text-primary me-2"></i>Pesanan Laundry Saya</h3>
                <p class="text-muted small mb-0">Kelola dan pantau seluruh pesanan laundry Anda</p>
            </div>
            <div>
                <a href="/customer/orders/create" class="btn btn-primary rounded-pill px-4 fw-bold shadow-sm">
                    <i class="bi bi-plus-lg me-1"></i> Buat Order Baru
                </a>
            </div>
        </div>

        <?php
        $currentTab = $_GET['tab'] ?? ($tab ?? 'active');
        if (!in_array($currentTab, ['active', 'history'])) {
            $currentTab = 'active';
        }
        $activeCount = count($activeOrders ?? []);
        $historyCount = count($historyOrders ?? []);

        $statusBadges = [
            'pending_pickup'      => ['bg-warning text-dark',   'Menunggu Pickup Kurir'],
            'driver_assigned'     => ['bg-info text-white',     'Kurir Ditugaskan'],
            'driver_on_the_way'   => ['bg-info text-white',     'Kurir Dalam Perjalanan'],
            'arrived_at_customer' => ['bg-primary text-white',  'Kurir Tiba di Lokasi'],
            'picked_up'           => ['bg-primary text-white',  'Pakaian Dijemput'],
            'arrived_at_store'    => ['bg-success text-white',  'Sampai di Toko'],
            'weighed'             => ['bg-secondary text-white','Selesai Ditimbang'],
            'waiting_payment'     => ['bg-warning text-dark',   'Menunggu Pembayaran'],
            'in_process'          => ['bg-primary text-white',  'Sedang Dicuci'],
            'ready_for_delivery'  => ['bg-success text-white',  'Siap Diantar'],
            'delivery_assigned'   => ['bg-info text-white',     'Kurir Antar Ditugaskan'],
            'delivered'           => ['bg-info text-white',     'Pakaian Telah Diantar'],
            'completed'           => ['bg-success text-white',  'Pesanan Selesai'],
            'cancelled'           => ['bg-danger text-white',   'Dibatalkan'],
        ];
        ?>

        <!-- Tabs Navigation -->
        <ul class="nav nav-pills nav-fill bg-white p-2 rounded-4 shadow-sm mb-4 border">
            <li class="nav-item">
                <a class="nav-link rounded-pill py-2.5 fw-bold <?= ($currentTab === 'active') ? 'active bg-primary' : 'text-secondary' ?>" 
                   href="/customer/orders?tab=active">
                    <i class="bi bi-hourglass-split me-1"></i> Pesanan Aktif
                    <span class="badge <?= ($currentTab === 'active') ? 'bg-white text-primary' : 'bg-primary text-white' ?> rounded-pill ms-2">
                        <?= $activeCount ?>
                    </span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link rounded-pill py-2.5 fw-bold <?= ($currentTab === 'history') ? 'active bg-primary' : 'text-secondary' ?>" 
                   href="/customer/orders?tab=history">
                    <i class="bi bi-clock-history me-1"></i> Riwayat Selesai
                    <span class="badge <?= ($currentTab === 'history') ? 'bg-white text-primary' : 'bg-secondary text-white' ?> rounded-pill ms-2">
                        <?= $historyCount ?>
                    </span>
                </a>
            </li>
        </ul>

        <?php if ($currentTab === 'active'): ?>
            <!-- TAB: PESANAN AKTIF -->
            <?php if (!empty($activeOrders)): ?>
                <div class="row g-3">
                    <?php foreach ($activeOrders as $ord): ?>
                        <div class="col-12">
                            <div class="card border-0 shadow-sm rounded-4 p-3 bg-white hover-lift">
                                <div class="row align-items-center g-3">
                                    <div class="col-md-3">
                                        <span class="badge bg-light text-secondary border px-2.5 py-1 mb-1">
                                            <i class="bi bi-hash me-1"></i><?= htmlspecialchars($ord['order_number']) ?>
                                        </span>
                                        <h6 class="fw-bold text-dark mb-0"><?= htmlspecialchars($ord['store_name'] ?? 'Toko Laundry') ?></h6>
                                        <small class="text-muted"><?= date('d M Y, H:i', strtotime($ord['created_at'])) ?></small>
                                    </div>

                                    <div class="col-md-3">
                                        <span class="text-muted small d-block">Layanan:</span>
                                        <strong class="text-dark"><?= htmlspecialchars($ord['service_name'] ?? 'Laundry') ?></strong>
                                    </div>

                                    <div class="col-md-3">
                                        <span class="text-muted small d-block">Status:</span>
                                        <?php $badge = $statusBadges[$ord['order_status']] ?? ['bg-secondary text-white', $ord['order_status']]; ?>
                                        <span class="badge <?= $badge[0] ?> rounded-pill px-3 py-1.5 fw-semibold">
                                            <?= $badge[1] ?>
                                        </span>
                                    </div>

                                    <div class="col-md-3 text-md-end">
                                        <a href="/customer/orders/<?= $ord['id'] ?>" class="btn btn-outline-primary btn-sm rounded-pill px-3 fw-medium">
                                            Lihat Detail <i class="bi bi-chevron-right ms-1"></i>
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="card border-0 shadow-sm rounded-4 p-5 text-center my-4 bg-white">
                    <i class="bi bi-check2-circle text-success display-4 mb-3"></i>
                    <h5 class="fw-bold mb-2">Tidak Ada Pesanan Aktif</h5>
                    <p class="text-muted max-w-500 mx-auto mb-4">
                        Saat ini Anda tidak memiliki pesanan yang sedang berjalan. Ingin mencuci pakaian? Buat pesanan baru atau lihat riwayat pesanan sebelumnya.
                    </p>
                    <div class="d-flex justify-content-center gap-2 flex-wrap">
                        <a href="/customer/orders/create" class="btn btn-primary rounded-pill px-4 py-2.5 fw-bold shadow-sm">
                            <i class="bi bi-plus-lg me-1"></i> Buat Order Laundry
                        </a>
                        <a href="/customer/orders?tab=history" class="btn btn-outline-secondary rounded-pill px-4 py-2.5 fw-medium">
                            <i class="bi bi-clock-history me-1"></i> Lihat Riwayat
                        </a>
                    </div>
                </div>
            <?php endif; ?>

        <?php else: ?>
            <!-- TAB: RIWAYAT -->
            <?php if (!empty($historyOrders)): ?>
                <div class="row g-3">
                    <?php foreach ($historyOrders as $ord): ?>
                        <div class="col-12">
                            <div class="card border-0 shadow-sm rounded-4 p-3 bg-white hover-lift">
                                <div class="row align-items-center g-3">
                                    <div class="col-md-3">
                                        <span class="badge bg-light text-secondary border px-2.5 py-1 mb-1">
                                            <i class="bi bi-hash me-1"></i><?= htmlspecialchars($ord['order_number']) ?>
                                        </span>
                                        <h6 class="fw-bold text-dark mb-0"><?= htmlspecialchars($ord['store_name'] ?? 'Toko Laundry') ?></h6>
                                        <small class="text-muted"><?= date('d M Y, H:i', strtotime($ord['created_at'])) ?></small>
                                    </div>

                                    <div class="col-md-3">
                                        <span class="text-muted small d-block">Layanan:</span>
                                        <strong class="text-dark"><?= htmlspecialchars($ord['service_name'] ?? 'Laundry') ?></strong>
                                    </div>

                                    <div class="col-md-3">
                                        <span class="text-muted small d-block">Status:</span>
                                        <?php $badge = $statusBadges[$ord['order_status']] ?? ['bg-secondary text-white', $ord['order_status']]; ?>
                                        <span class="badge <?= $badge[0] ?> rounded-pill px-3 py-1.5 fw-semibold">
                                            <?= $badge[1] ?>
                                        </span>
                                    </div>

                                    <div class="col-md-3 text-md-end d-flex justify-content-md-end align-items-center gap-2 flex-wrap">
                                        <?php if ($ord['order_status'] === 'delivered'): ?>
                                            <form method="POST" action="/customer/orders/<?= (int)$ord['id'] ?>/complete" class="d-inline m-0">
                                                <input type="hidden" name="_token" value="<?= htmlspecialchars(\App\Helpers\Session::csrfToken()) ?>">
                                                <button type="submit" class="btn btn-success btn-sm rounded-pill px-3 fw-bold shadow-sm" onclick="return confirm('Konfirmasi bahwa laundry telah Anda terima dengan baik?')">
                                                    <i class="bi bi-check2-circle me-1"></i> Pesanan Diterima
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                        <a href="/customer/orders/<?= $ord['id'] ?>" class="btn btn-outline-primary btn-sm rounded-pill px-3 fw-medium">
                                            Detail <i class="bi bi-chevron-right ms-1"></i>
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="card border-0 shadow-sm rounded-4 p-5 text-center my-4 bg-white">
                    <i class="bi bi-inbox text-muted display-4 mb-3"></i>
                    <h5 class="fw-bold mb-2">Belum Ada Riwayat Pesanan</h5>
                    <p class="text-muted max-w-500 mx-auto mb-4">Pesanan yang telah diantar, selesai, atau dibatalkan akan muncul di tab ini.</p>
                    <div>
                        <a href="/customer/orders/create" class="btn btn-primary rounded-pill px-4 py-2.5 fw-bold shadow-sm">
                            <i class="bi bi-plus-lg me-1"></i> Buat Order Sekarang
                        </a>
                    </div>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</section>
