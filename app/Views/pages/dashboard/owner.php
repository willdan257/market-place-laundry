<section class="py-4 bg-light min-vh-100">
    <div class="container">
        <!-- Owner Dashboard Header -->
        <div class="card border-0 shadow-sm rounded-4 p-4 mb-4 bg-dark text-white position-relative overflow-hidden">
            <div class="position-absolute end-0 top-0 translate-middle-y me-n5 mt-n3 opacity-10 d-none d-md-block" style="pointer-events: none;">
                <i class="bi bi-robot" style="font-size: 15rem; color: #fff;"></i>
            </div>
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 position-relative">
                <div>
                    <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
                        <span class="badge bg-warning text-dark rounded-pill px-3 py-1 fw-bold">Role: OWNER LAUNDRY</span>
                        <span class="badge bg-primary-subtle text-primary border border-primary-subtle rounded-pill px-3 py-1 fw-semibold">
                            <i class="bi bi-shop me-1"></i><?= htmlspecialchars($store['store_name'] ?? 'Laundry Clean & Fresh') ?>
                        </span>
                        <?php if ($store): ?>
                            <?php $isOpen = (int) ($store['is_open'] ?? 1); ?>
                            <?php if ($isOpen === 1): ?>
                                <span class="badge bg-success rounded-pill px-3 py-1 fw-bold shadow-sm">
                                    <i class="bi bi-door-open-fill me-1"></i>STATUS: TOKO BUKA
                                </span>
                            <?php else: ?>
                                <span class="badge bg-danger rounded-pill px-3 py-1 fw-bold shadow-sm">
                                    <i class="bi bi-door-closed-fill me-1"></i>STATUS: TOKO TUTUP
                                </span>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                    <h3 class="fw-bold mb-1">Dashboard Pengelola Laundry</h3>
                    <p class="mb-0 text-white-50">Pengelola: <?= htmlspecialchars($user['name'] ?? 'Owner') ?> (<?= htmlspecialchars($user['email'] ?? '') ?>)</p>
                </div>
                <div class="d-flex flex-wrap align-items-center gap-2">
                    <?php if ($store): ?>
                        <form method="POST" action="/owner/store/toggle-status" class="d-inline m-0">
                            <input type="hidden" name="_token" value="<?= \App\Helpers\Session::csrfToken() ?>">
                            <?php if ($isOpen === 1): ?>
                                <button type="submit" class="btn btn-outline-danger rounded-pill px-3 py-2 fw-semibold shadow-sm"
                                        onclick="return confirm('Apakah Anda yakin ingin MENUTUP toko sementara? Pelanggan tidak dapat membuat pesanan baru hingga toko dibuka kembali.')">
                                    <i class="bi bi-door-closed-fill me-1"></i> Tutup Toko Sementara
                                </button>
                            <?php else: ?>
                                <button type="submit" class="btn btn-success rounded-pill px-3 py-2 fw-bold text-white shadow-sm"
                                        onclick="return confirm('Buka toko sekarang? Pelanggan akan dapat membuat pesanan laundry baru.')">
                                    <i class="bi bi-door-open-fill me-1"></i> Buka Toko Sekarang
                                </button>
                            <?php endif; ?>
                        </form>
                    <?php endif; ?>

                    <a href="/owner/orders" class="btn btn-warning rounded-pill px-4 fw-bold text-dark shadow-sm">
                        <i class="bi bi-shop me-1"></i> Kelola Pesanan Toko
                    </a>
                    <a href="/owner/drivers" class="btn btn-outline-light rounded-pill px-3 fw-medium">
                        <i class="bi bi-bicycle me-1"></i> Status Kurir
                    </a>
                </div>
            </div>
        </div>

        <?php if ($store && (int)($store['is_open'] ?? 1) === 0): ?>
            <!-- Alert Banner Ketika Toko Sedang Tutup -->
            <div class="alert alert-danger border-0 shadow-sm rounded-4 p-3 mb-4 d-flex flex-wrap align-items-center justify-content-between gap-3">
                <div class="d-flex align-items-center gap-3">
                    <div class="p-3 bg-danger text-white rounded-circle d-flex align-items-center justify-content-center" style="width: 48px; height: 48px;">
                        <i class="bi bi-door-closed-fill fs-4"></i>
                    </div>
                    <div>
                        <h6 class="fw-bold mb-1 text-danger">Toko Laundry Saat Ini Sedang TUTUP</h6>
                        <p class="mb-0 small text-secondary">
                            Toko Anda disembunyikan sementara dari daftar pemesanan pelanggan sehingga tidak ada pesanan baru yang masuk.
                            Jika sudah siap melayani pesanan, klik tombol di samping untuk membuka toko kembali.
                        </p>
                    </div>
                </div>
                <form method="POST" action="/owner/store/toggle-status" class="m-0">
                    <input type="hidden" name="_token" value="<?= \App\Helpers\Session::csrfToken() ?>">
                    <input type="hidden" name="is_open" value="1">
                    <button type="submit" class="btn btn-success rounded-pill px-4 py-2 fw-bold text-white shadow-sm">
                        <i class="bi bi-door-open-fill me-1"></i> Buka Toko Sekarang
                    </button>
                </form>
            </div>
        <?php endif; ?>

        <!-- ==========================================
             AI-ASSISTED ANALYTICS SECTION
             ========================================== -->
        <div class="card border-0 shadow-sm rounded-4 p-4 mb-4 bg-white">
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4 pb-3 border-bottom">
                <div>
                    <div class="d-flex align-items-center gap-2">
                        <span class="p-2 rounded-3 bg-primary-subtle text-primary d-inline-flex align-items-center justify-content-center" style="width: 38px; height: 38px;">
                            <i class="bi bi-bar-chart-fill fs-5"></i>
                        </span>
                        <div>
                            <h5 class="fw-bold mb-0 text-dark">Analitik Performa Toko</h5>
                            <small class="text-muted">Statistik pesanan & pendapatan toko Anda</small>
                        </div>
                    </div>
                </div>

                <!-- Filter Form Bar -->
                <div class="d-flex flex-wrap align-items-center gap-2">
                    <div class="btn-group p-1 bg-light rounded-pill border" role="group" id="filterButtonGroup">
                        <a href="?filter=today" class="btn btn-sm rounded-pill px-3 <?= ($analyticsData['filter'] === 'today') ? 'btn-primary shadow-sm fw-bold' : 'btn-light text-muted' ?>">
                            <i class="bi bi-calendar-check me-1"></i> Hari Ini
                        </a>
                        <a href="?filter=week" class="btn btn-sm rounded-pill px-3 <?= ($analyticsData['filter'] === 'week') ? 'btn-primary shadow-sm fw-bold' : 'btn-light text-muted' ?>">
                            <i class="bi bi-calendar-range me-1"></i> Minggu Ini
                        </a>
                        <a href="?filter=month" class="btn btn-sm rounded-pill px-3 <?= ($analyticsData['filter'] === 'month') ? 'btn-primary shadow-sm fw-bold' : 'btn-light text-muted' ?>">
                            <i class="bi bi-calendar-month me-1"></i> Bulan Ini
                        </a>
                        <button type="button" class="btn btn-sm rounded-pill px-3 <?= ($analyticsData['filter'] === 'custom') ? 'btn-primary shadow-sm fw-bold' : 'btn-light text-muted' ?>" data-bs-toggle="collapse" data-bs-target="#customDateCollapse" aria-expanded="<?= ($analyticsData['filter'] === 'custom') ? 'true' : 'false' ?>">
                            <i class="bi bi-sliders me-1"></i> Custom Range
                        </button>
                    </div>
                </div>
            </div>

            <!-- Custom Date Range Collapse Picker -->
            <div class="collapse <?= ($analyticsData['filter'] === 'custom') ? 'show' : '' ?> mb-4" id="customDateCollapse">
                <div class="p-3 bg-light rounded-4 border">
                    <form method="GET" action="/owner/dashboard" class="row g-2 align-items-center">
                        <input type="hidden" name="filter" value="custom">
                        <div class="col-auto">
                            <span class="small fw-bold text-muted"><i class="bi bi-calendar-event me-1"></i>Rentang Tanggal:</span>
                        </div>
                        <div class="col-sm-3 col-6">
                            <input type="date" class="form-control form-control-sm rounded-pill" name="start_date" id="inputStartDate" value="<?= htmlspecialchars($analyticsData['start_date']) ?>" required>
                        </div>
                        <div class="col-auto text-muted small">s/d</div>
                        <div class="col-sm-3 col-6">
                            <input type="date" class="form-control form-control-sm rounded-pill" name="end_date" id="inputEndDate" value="<?= htmlspecialchars($analyticsData['end_date']) ?>" required>
                        </div>
                        <div class="col-auto">
                            <button type="submit" class="btn btn-primary btn-sm rounded-pill px-3 fw-semibold">
                                Terapkan Filter
                            </button>
                        </div>
                    </form>
                </div>
            </div>


            <!-- 7 Analytics Metrics Grid (Required by Owner) -->
            <div class="row g-3">
                <!-- 1. Total Order -->
                <div class="col-xl-3 col-md-6">
                    <div class="card border-0 rounded-4 p-3 bg-light hover-lift h-100">
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <span class="text-muted small fw-semibold">Total Order</span>
                            <span class="p-2 rounded-circle bg-primary-subtle text-primary">
                                <i class="bi bi-receipt fs-6"></i>
                            </span>
                        </div>
                        <h3 class="fw-bold text-dark mb-1" id="valTotalOrders"><?= $analyticsData['total_orders'] ?></h3>
                        <div class="text-muted small">Semua pesanan masuk pada periode ini</div>
                    </div>
                </div>

                <!-- 2. Order Selesai -->
                <div class="col-xl-3 col-md-6">
                    <div class="card border-0 rounded-4 p-3 bg-light hover-lift h-100">
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <span class="text-muted small fw-semibold">Order Selesai</span>
                            <span class="p-2 rounded-circle bg-success-subtle text-success">
                                <i class="bi bi-check2-circle fs-6"></i>
                            </span>
                        </div>
                        <h3 class="fw-bold text-success mb-1" id="valCompletedOrders"><?= $analyticsData['completed_orders'] ?></h3>
                        <div class="text-muted small">Cucian selesai & telah diterima customer</div>
                    </div>
                </div>

                <!-- 3. Order Pending -->
                <div class="col-xl-3 col-md-6">
                    <div class="card border-0 rounded-4 p-3 bg-light hover-lift h-100">
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <span class="text-muted small fw-semibold">Order Pending</span>
                            <span class="p-2 rounded-circle bg-warning-subtle text-warning">
                                <i class="bi bi-hourglass-split fs-6"></i>
                            </span>
                        </div>
                        <h3 class="fw-bold text-warning mb-1" id="valPendingOrders"><?= $analyticsData['pending_orders'] ?></h3>
                        <div class="text-muted small">Sedang dijemput, dicuci, atau diantar</div>
                    </div>
                </div>

                <!-- 4. Pendapatan -->
                <div class="col-xl-3 col-md-6">
                    <div class="card border-0 rounded-4 p-3 bg-light hover-lift h-100">
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <span class="text-muted small fw-semibold">Pendapatan</span>
                            <span class="p-2 rounded-circle bg-info-subtle text-info">
                                <i class="bi bi-cash-stack fs-6"></i>
                            </span>
                        </div>
                        <h3 class="fw-bold text-primary mb-1" id="valTotalRevenue"><?= $analyticsData['formatted_revenue'] ?></h3>
                        <div class="text-muted small">Akumulasi transaksi pada periode ini</div>
                    </div>
                </div>

                <!-- 5. Rata-rata Nilai Order -->
                <div class="col-xl-4 col-md-6">
                    <div class="card border-0 rounded-4 p-3 bg-light hover-lift h-100">
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <span class="text-muted small fw-semibold">Rata-rata Nilai Order (AOV)</span>
                            <span class="p-2 rounded-circle bg-primary-subtle text-primary">
                                <i class="bi bi-calculator fs-6"></i>
                            </span>
                        </div>
                        <h4 class="fw-bold text-dark mb-1" id="valAvgOrder"><?= $analyticsData['formatted_avg_order'] ?></h4>
                        <div class="text-muted small">Rata-rata pengeluaran per transaksi valid</div>
                    </div>
                </div>

                <!-- 6. Layanan Paling Populer -->
                <div class="col-xl-4 col-md-6">
                    <div class="card border-0 rounded-4 p-3 bg-light hover-lift h-100">
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <span class="text-muted small fw-semibold">Layanan Paling Populer</span>
                            <span class="p-2 rounded-circle bg-danger-subtle text-danger">
                                <i class="bi bi-fire fs-6"></i>
                            </span>
                        </div>
                        <h5 class="fw-bold text-dark mb-1 text-truncate" id="valPopularService" title="<?= htmlspecialchars($analyticsData['most_popular_service']) ?>">
                            <?= htmlspecialchars($analyticsData['most_popular_service']) ?>
                        </h5>
                        <div class="text-muted small">Layanan favorit pilihan pelanggan</div>
                    </div>
                </div>

                <!-- 7. Periode Transaksi -->
                <div class="col-xl-4 col-md-12">
                    <div class="card border-0 rounded-4 p-3 bg-light hover-lift h-100">
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <span class="text-muted small fw-semibold">Periode Transaksi</span>
                            <span class="p-2 rounded-circle bg-secondary-subtle text-secondary">
                                <i class="bi bi-calendar2-range fs-6"></i>
                            </span>
                        </div>
                        <h5 class="fw-bold text-dark mb-1" id="valPeriodLabel"><?= htmlspecialchars($analyticsData['period_label']) ?></h5>
                        <div class="text-muted small">Rentang filter data yang aktif ditampilkan</div>
                    </div>
                </div>
            </div>
            <?php if ((int)$analyticsData['total_orders'] === 0): ?>
                <div class="alert alert-info text-center mt-3" role="alert">
                    Belum ada pesanan pada periode ini.
                </div>
            <?php endif; ?>

            <!-- Breakdown Layanan Terpopuler Table -->
            <?php if (!empty($analyticsData['popular_services'])): ?>
                <div class="mt-4 pt-3 border-top">
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <h6 class="fw-bold mb-0 text-dark">
                            <i class="bi bi-bar-chart-fill text-primary me-2"></i>Peringkat Layanan Terlaris (Periode Terpilih)
                        </h6>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-sm align-middle table-borderless mb-0">
                            <thead class="table-light">
                                <tr class="text-muted small">
                                    <th style="width: 50px;">#</th>
                                    <th>Nama Layanan</th>
                                    <th>Frekuensi Order</th>
                                    <th>Total Volume</th>
                                    <th class="text-end">Total Penjualan</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($analyticsData['popular_services'] as $idx => $ps): ?>
                                    <tr>
                                        <td><span class="badge bg-secondary-subtle text-dark rounded-circle"><?= $idx + 1 ?></span></td>
                                        <td class="fw-semibold text-dark"><?= htmlspecialchars($ps['service_name']) ?></td>
                                        <td><span class="badge bg-light text-dark border"><?= $ps['order_frequency'] ?> transaksi</span></td>
                                        <td><?= number_format((float)$ps['total_qty'], 1) ?> <?= htmlspecialchars($ps['unit'] ?? 'kg') ?></td>
                                        <td class="text-end fw-bold text-primary">Rp <?= number_format((float)$ps['total_sales'], 0, ',', '.') ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <!-- ==========================================
             STORE ORDERS MANAGEMENT TABLE
             ========================================== -->
        <div class="card border-0 shadow-sm rounded-4 p-4">
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
                <h5 class="fw-bold mb-0"><i class="bi bi-basket3-fill text-warning me-2"></i>Pesanan Aktif</h5>
                <a href="/owner/orders" class="btn btn-sm btn-outline-dark rounded-pill fw-semibold">
                    Kelola Semua Pesanan Toko <i class="bi bi-arrow-right ms-1"></i>
                </a>
            </div>

            <?php if (!empty($activeStoreOrders)): ?>
                <div class="table-responsive">
                    <table class="table align-middle table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>No. Order</th>
                                <th>Pelanggan</th>
                                <th>Layanan</th>
                                <th>Total Biaya</th>
                                <th>Status Pesanan</th>
                                <th>Kurir Penjemput</th>
                                <th class="text-end">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach (array_slice($activeStoreOrders, 0, 5) as $ord): ?>
                                <tr>
                                    <td><strong>#<?= htmlspecialchars($ord['order_number']) ?></strong></td>
                                    <td>
                                        <div class="fw-semibold"><?= htmlspecialchars($ord['customer_name']) ?></div>
                                        <small class="text-muted"><?= htmlspecialchars($ord['customer_phone'] ?? '-') ?></small>
                                    </td>
                                    <td><?= htmlspecialchars($ord['service_name'] ?? 'Laundry Kiloan') ?></td>
                                    <td class="fw-semibold text-primary">
                                        Rp <?= number_format((float)($ord['total_amount'] ?? 0), 0, ',', '.') ?>
                                    </td>
                                    <td>
                                        <?php $b = \App\Helpers\OrderStatusHelper::getBadge($ord['order_status']); ?>
                                        <span class="badge <?= $b[0] ?> rounded-pill px-3 py-1">
                                            <?= $b[1] ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?= !empty($ord['driver_name']) ? htmlspecialchars($ord['driver_name']) : '<span class="text-danger small"><i class="bi bi-exclamation-circle me-1"></i>Belum Ditugaskan</span>' ?>
                                    </td>
                                    <td class="text-end">
                                        <?php $st = $ord['order_status']; ?>
                                        <div class="d-flex justify-content-end align-items-center gap-1.5 flex-wrap">
                                            <?php if ($st === 'pending_pickup'): ?>
                                                <a href="/owner/orders/<?= $ord['id'] ?>/assign" class="btn btn-sm btn-warning rounded-pill px-3 fw-bold text-dark shadow-sm">
                                                    <i class="bi bi-bicycle me-1"></i> Assign Kurir Jemput
                                                </a>
                                                <a href="/owner/orders/<?= $ord['id'] ?>" class="btn btn-sm btn-outline-secondary rounded-pill px-2.5">
                                                    Detail
                                                </a>
                                            <?php elseif (in_array($st, ['driver_assigned', 'driver_on_the_way', 'arrived_at_customer', 'picked_up'], true)): ?>
                                                <span class="badge bg-info-subtle text-info border border-info-subtle rounded-pill px-2.5 py-1.5 small fw-semibold">
                                                    <i class="bi bi-bicycle me-1"></i> Kurir sedang menjemput
                                                </span>
                                                <a href="/owner/orders/<?= $ord['id'] ?>" class="btn btn-sm btn-outline-primary rounded-pill px-2.5">
                                                    Detail
                                                </a>
                                            <?php elseif ($st === 'arrived_at_store'): ?>
                                                <button type="button" class="btn btn-sm btn-success rounded-pill px-3 fw-bold shadow-sm btn-open-weigh-modal"
                                                        data-bs-toggle="modal"
                                                        data-bs-target="#weighModal"
                                                        data-order-id="<?= (int)$ord['id'] ?>"
                                                        data-order-number="<?= htmlspecialchars($ord['order_number']) ?>"
                                                        data-customer-name="<?= htmlspecialchars($ord['customer_name'] ?? 'Pelanggan') ?>"
                                                        data-service-name="<?= htmlspecialchars($ord['service_name'] ?? 'Laundry') ?>"
                                                        data-price-per-unit="<?= (float)($ord['price_per_unit'] ?? 0) ?>"
                                                        data-unit="<?= htmlspecialchars($ord['unit'] ?? 'kg') ?>"
                                                        data-shipping-fee="<?= (float)($ord['shipping_fee'] ?? 0) ?>">
                                                    <i class="bi bi-rulers me-1"></i> Timbang
                                                </button>
                                                <a href="/owner/orders/<?= $ord['id'] ?>" class="btn btn-sm btn-outline-secondary rounded-pill px-2.5">
                                                    Detail
                                                </a>
                                            <?php elseif (in_array($st, ['weighed', 'waiting_payment'], true)): ?>
                                                <span class="badge bg-warning-subtle text-dark border border-warning-subtle rounded-pill px-2.5 py-1.5 small fw-semibold">
                                                    <i class="bi bi-hourglass-split me-1"></i> Menunggu pembayaran pelanggan
                                                </span>
                                                <a href="/owner/orders/<?= $ord['id'] ?>" class="btn btn-sm btn-outline-primary rounded-pill px-2.5">
                                                    Detail
                                                </a>
                                            <?php elseif ($st === 'in_process'): ?>
                                                <form method="POST" action="/owner/orders/<?= (int)$ord['id'] ?>/status" class="d-inline m-0">
                                                    <input type="hidden" name="_token" value="<?= htmlspecialchars(\App\Helpers\Session::csrfToken()) ?>">
                                                    <input type="hidden" name="new_status" value="ready_for_delivery">
                                                    <input type="hidden" name="redirect_to" value="dashboard">
                                                    <button type="submit" class="btn btn-sm btn-primary rounded-pill px-3 fw-bold shadow-sm" onclick="return confirm('Tandai cucian order #<?= htmlspecialchars($ord['order_number']) ?> telah selesai dicuci dan siap diantar?')">
                                                        <i class="bi bi-check2-all me-1"></i> Tandai Selesai Dicuci
                                                    </button>
                                                </form>
                                                <a href="/owner/orders/<?= $ord['id'] ?>" class="btn btn-sm btn-outline-secondary rounded-pill px-2.5">
                                                    Detail
                                                </a>
                                            <?php elseif ($st === 'ready_for_delivery'): ?>
                                                <a href="/owner/orders/<?= $ord['id'] ?>/assign" class="btn btn-sm btn-info text-white rounded-pill px-3 fw-bold shadow-sm">
                                                    <i class="bi bi-box-seam me-1"></i> Assign Kurir Antar
                                                </a>
                                                <a href="/owner/orders/<?= $ord['id'] ?>" class="btn btn-sm btn-outline-secondary rounded-pill px-2.5">
                                                    Detail
                                                </a>
                                            <?php elseif ($st === 'delivery_assigned'): ?>
                                                <span class="badge bg-info-subtle text-info border border-info-subtle rounded-pill px-2.5 py-1.5 small fw-semibold">
                                                    <i class="bi bi-bicycle me-1"></i> Kurir sedang mengantar
                                                </span>
                                                <a href="/owner/orders/<?= $ord['id'] ?>" class="btn btn-sm btn-outline-primary rounded-pill px-2.5">
                                                    Detail
                                                </a>
                                            <?php else: ?>
                                                <a href="/owner/orders/<?= $ord['id'] ?>" class="btn btn-sm btn-outline-secondary rounded-pill px-3">
                                                    Detail
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="text-center py-5 text-muted">
                    <i class="bi bi-inbox fs-1 d-block mb-2 text-muted opacity-50"></i>
                    <p class="mb-3">Tidak ada pesanan aktif</p>
                    <a href="/owner/orders" class="btn btn-sm btn-outline-dark rounded-pill fw-semibold px-3">
                        Lihat Semua Pesanan <i class="bi bi-arrow-right ms-1"></i>
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</section>

<!-- Modal Timbang Laundry Dashboard -->
<div class="modal fade" id="weighModal" tabindex="-1" aria-labelledby="weighModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
            <div class="modal-header bg-warning text-dark p-3 px-4">
                <h5 class="modal-title fw-bold" id="weighModalLabel">
                    <i class="bi bi-rulers me-2"></i>Timbang Laundry <span id="weighModalOrderNum"></span>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="weighForm" method="POST" action="">
                <div class="modal-body p-4">
                    <input type="hidden" name="_token" value="<?= htmlspecialchars(\App\Helpers\Session::csrfToken()) ?>">
                    <input type="hidden" name="redirect_to" value="dashboard">
                    
                    <div class="bg-light p-3 rounded-3 border mb-3">
                        <div class="row g-2 small">
                            <div class="col-6">
                                <span class="text-muted d-block">Pelanggan:</span>
                                <strong id="weighModalCustName" class="text-dark">-</strong>
                            </div>
                            <div class="col-6">
                                <span class="text-muted d-block">Layanan:</span>
                                <strong id="weighModalServiceName" class="text-dark">-</strong>
                            </div>
                            <div class="col-6">
                                <span class="text-muted d-block">Tarif Satuan:</span>
                                <strong id="weighModalPricePerUnit" class="text-primary">-</strong>
                            </div>
                            <div class="col-6">
                                <span class="text-muted d-block">Ongkir:</span>
                                <strong id="weighModalShippingFee" class="text-dark">-</strong>
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="weighInputWeight" class="form-label fw-bold text-dark">
                            Berat Cucian (kg) <span class="text-danger">*</span>
                        </label>
                        <div class="input-group">
                            <input type="number" class="form-control form-control-lg rounded-start-3"
                                   id="weighInputWeight" name="weight_kg"
                                   step="0.01" min="0.01" max="500" placeholder="0.00" required>
                            <span class="input-group-text bg-warning text-dark fw-bold">kg</span>
                        </div>
                        <div class="form-text">Batas berat: 0.01 kg s/d 500 kg (contoh: 3.5 atau 4.25)</div>
                    </div>

                    <div class="p-3 bg-warning-subtle border border-warning rounded-3">
                        <div class="d-flex justify-content-between mb-1 small">
                            <span class="text-muted">Estimasi Subtotal:</span>
                            <span id="weighEstimateSubtotal" class="fw-semibold">Rp 0</span>
                        </div>
                        <div class="d-flex justify-content-between mb-1 small">
                            <span class="text-muted">Ongkir:</span>
                            <span id="weighEstimateShipping" class="fw-semibold">Rp 0</span>
                        </div>
                        <div class="border-top border-warning pt-2 d-flex justify-content-between">
                            <strong class="text-dark">Estimasi Total Tagihan:</strong>
                            <strong id="weighEstimateTotal" class="text-success fs-5">Rp 0</strong>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-top-0 p-3 px-4 bg-light">
                    <button type="button" class="btn btn-outline-secondary rounded-pill px-4" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-warning rounded-pill px-4 fw-bold text-dark shadow-sm">
                        <i class="bi bi-check2-circle me-1"></i> Simpan Hasil Timbang
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const weighModalEl = document.getElementById('weighModal');
    if (!weighModalEl) return;

    let currentPricePerUnit = 0;
    let currentShippingFee = 0;

    const weighForm = document.getElementById('weighForm');
    const orderNumEl = document.getElementById('weighModalOrderNum');
    const custNameEl = document.getElementById('weighModalCustName');
    const serviceNameEl = document.getElementById('weighModalServiceName');
    const pricePerUnitEl = document.getElementById('weighModalPricePerUnit');
    const shippingFeeEl = document.getElementById('weighModalShippingFee');
    const weightInput = document.getElementById('weighInputWeight');
    const estSubtotalEl = document.getElementById('weighEstimateSubtotal');
    const estShippingEl = document.getElementById('weighEstimateShipping');
    const estTotalEl = document.getElementById('weighEstimateTotal');

    function formatRupiah(num) {
        return 'Rp ' + Math.round(num).toLocaleString('id-ID');
    }

    function calculateEstimate() {
        const weight = parseFloat(weightInput.value) || 0;
        const subtotal = Math.round(weight * currentPricePerUnit * 100) / 100;
        const total = subtotal + currentShippingFee;

        estSubtotalEl.textContent = formatRupiah(subtotal);
        estShippingEl.textContent = formatRupiah(currentShippingFee);
        estTotalEl.textContent = formatRupiah(total);
    }

    weighModalEl.addEventListener('show.bs.modal', function (event) {
        const button = event.relatedTarget;
        if (!button) return;

        const orderId = button.getAttribute('data-order-id');
        const orderNumber = button.getAttribute('data-order-number');
        const custName = button.getAttribute('data-customer-name');
        const serviceName = button.getAttribute('data-service-name');
        currentPricePerUnit = parseFloat(button.getAttribute('data-price-per-unit')) || 0;
        currentShippingFee = parseFloat(button.getAttribute('data-shipping-fee')) || 0;
        const unit = button.getAttribute('data-unit') || 'kg';

        weighForm.action = '/owner/orders/' + orderId + '/weigh';
        orderNumEl.textContent = '#' + orderNumber;
        custNameEl.textContent = custName;
        serviceNameEl.textContent = serviceName;
        pricePerUnitEl.textContent = formatRupiah(currentPricePerUnit) + ' / ' + unit;
        shippingFeeEl.textContent = formatRupiah(currentShippingFee);

        weightInput.value = '';
        calculateEstimate();
        setTimeout(function () { weightInput.focus(); }, 350);
    });

    weightInput.addEventListener('input', calculateEstimate);
});
</script>

<style>
.hover-lift {
    transition: transform 0.2s ease, box-shadow 0.2s ease;
}
.hover-lift:hover {
    transform: translateY(-2px);
    box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.08) !important;
}
</style>
