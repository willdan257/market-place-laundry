<section class="py-4 bg-light min-vh-100">
    <div class="container">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
            <div>
                <div class="d-flex flex-wrap align-items-center gap-2 mb-1">
                    <h3 class="fw-bold mb-0"><i class="bi bi-shop text-warning me-2"></i>Daftar Pesanan Laundry Toko</h3>
                    <?php if (!empty($store)): ?>
                        <?php $isOpen = (int) ($store['is_open'] ?? 1); ?>
                        <?php if ($isOpen === 1): ?>
                            <span class="badge bg-success rounded-pill px-3 py-1 fw-bold shadow-sm">
                                <i class="bi bi-door-open-fill me-1"></i>TOKO BUKA
                            </span>
                        <?php else: ?>
                            <span class="badge bg-danger rounded-pill px-3 py-1 fw-bold shadow-sm">
                                <i class="bi bi-door-closed-fill me-1"></i>TOKO TUTUP
                            </span>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
                <p class="text-muted small mb-0">Kelola pesanan masuk dan penugasan kurir untuk <?= htmlspecialchars($store['store_name'] ?? 'Toko Laundry') ?></p>
            </div>
            <div class="d-flex flex-wrap align-items-center gap-2">
                <?php if (!empty($store)): ?>
                    <form method="POST" action="/owner/store/toggle-status" class="d-inline m-0">
                        <input type="hidden" name="_token" value="<?= \App\Helpers\Session::csrfToken() ?>">
                        <?php if ($isOpen === 1): ?>
                            <button type="submit" class="btn btn-outline-danger rounded-pill px-3 py-2 fw-semibold shadow-sm"
                                    onclick="return confirm('Apakah Anda yakin ingin MENUTUP toko sementara? Pelanggan tidak dapat membuat pesanan baru hingga toko dibuka kembali.')">
                                <i class="bi bi-door-closed-fill me-1"></i> Tutup Toko
                            </button>
                        <?php else: ?>
                            <button type="submit" class="btn btn-success rounded-pill px-3 py-2 fw-bold text-white shadow-sm"
                                    onclick="return confirm('Buka toko sekarang? Pelanggan akan dapat membuat pesanan laundry baru.')">
                                <i class="bi bi-door-open-fill me-1"></i> Buka Toko Sekarang
                            </button>
                        <?php endif; ?>
                    </form>
                <?php endif; ?>

                <a href="/owner/drivers" class="btn btn-outline-dark rounded-pill px-4 fw-medium">
                    <i class="bi bi-bicycle me-1"></i> Lihat Status Kurir
                </a>
            </div>
        </div>

        <?php if (!empty($store) && (int)($store['is_open'] ?? 1) === 0): ?>
            <!-- Alert Banner Ketika Toko Sedang Tutup di Halaman Pesanan -->
            <div class="alert alert-danger border-0 shadow-sm rounded-4 p-3 mb-4 d-flex flex-wrap align-items-center justify-content-between gap-3">
                <div class="d-flex align-items-center gap-2">
                    <i class="bi bi-door-closed-fill fs-4 text-danger"></i>
                    <div>
                        <strong class="text-danger">Toko Anda Saat Ini Sedang TUTUP.</strong>
                        <span class="small text-secondary d-block">Pelanggan tidak dapat membuat pesanan baru hingga Anda membukanya kembali.</span>
                    </div>
                </div>
                <form method="POST" action="/owner/store/toggle-status" class="m-0">
                    <input type="hidden" name="_token" value="<?= \App\Helpers\Session::csrfToken() ?>">
                    <input type="hidden" name="is_open" value="1">
                    <button type="submit" class="btn btn-success btn-sm rounded-pill px-3 fw-bold text-white shadow-sm">
                        <i class="bi bi-door-open-fill me-1"></i> Buka Toko
                    </button>
                </form>
            </div>
        <?php endif; ?>

        <?php
        $historyStatuses = \App\Models\Order::CUSTOMER_HISTORY_STATUSES;
        $activeOrders    = array_values(array_filter($orders ?? [], fn($o) => !in_array($o['order_status'], $historyStatuses, true)));
        $historyOrders   = array_values(array_filter($orders ?? [], fn($o) => in_array($o['order_status'], $historyStatuses, true)));

        $renderOrderTable = function(array $list, string $emptyMsg) {
            if (empty($list)) {
                return '<div class="card border-0 shadow-sm rounded-4 p-5 text-center my-2">
                    <i class="bi bi-inbox text-muted display-4 mb-3"></i>
                    <h5 class="fw-bold mb-2">' . htmlspecialchars($emptyMsg) . '</h5>
                </div>';
            }
            ob_start();
            ?>
            <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
                <div class="table-responsive">
                    <table class="table align-middle table-hover mb-0">
                        <thead class="table-dark">
                            <tr>
                                <th>No. Order</th>
                                <th>Pelanggan</th>
                                <th>Layanan</th>
                                <th>Status Order</th>
                                <th>Kurir Penjemput</th>
                                <th class="text-end">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($list as $ord): ?>
                                <tr>
                                    <td>
                                        <strong class="text-primary d-block">#<?= htmlspecialchars($ord['order_number']) ?></strong>
                                        <small class="text-muted"><?= date('d M H:i', strtotime($ord['created_at'])) ?></small>
                                    </td>
                                    <td>
                                        <strong class="text-dark d-block"><?= htmlspecialchars($ord['customer_name']) ?></strong>
                                        <small class="text-muted"><i class="bi bi-telephone me-1"></i><?= htmlspecialchars($ord['customer_phone'] ?? '-') ?></small>
                                    </td>
                                    <td><?= htmlspecialchars($ord['service_name'] ?? 'Custom') ?></td>
                                    <td>
                                        <?php $b = \App\Helpers\OrderStatusHelper::getBadge($ord['order_status']); ?>
                                        <span class="badge <?= $b[0] ?> rounded-pill px-3 py-1.5 fw-semibold">
                                            <?= $b[1] ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if (!empty($ord['driver_name'])): ?>
                                            <span class="badge bg-success-subtle text-success rounded-pill px-3 py-1.5">
                                                <i class="bi bi-person-check me-1"></i><?= htmlspecialchars($ord['driver_name']) ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="badge bg-warning-subtle text-dark rounded-pill px-3 py-1.5">
                                                <i class="bi bi-exclamation-circle me-1"></i> Belum Ada Kurir
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end">
                                        <a href="/owner/orders/<?= $ord['id'] ?>" class="btn btn-sm btn-outline-primary rounded-pill px-3 fw-medium me-1">
                                            <i class="bi bi-eye me-1"></i> Detail
                                        </a>
                                        <?php if (in_array($ord['order_status'], ['pending_pickup', 'driver_assigned', 'ready_for_delivery', 'delivery_assigned'], true)): ?>
                                            <a href="/owner/orders/<?= $ord['id'] ?>/assign" class="btn btn-sm btn-warning rounded-pill px-3 fw-bold text-dark shadow-sm">
                                                <i class="bi bi-person-plus me-1"></i> <?= in_array($ord['order_status'], ['ready_for_delivery', 'delivery_assigned']) ? 'Assign Kurir Antar' : 'Assign Kurir' ?>
                                            </a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php
            return ob_get_clean();
        };
        ?>

        <ul class="nav nav-pills gap-2 mb-4" id="orderTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active rounded-pill px-4 fw-semibold" id="active-tab" data-bs-toggle="pill" data-bs-target="#active-orders" type="button" role="tab" aria-controls="active-orders" aria-selected="true">
                    <i class="bi bi-clock-history me-1"></i> Pesanan Aktif (<?= count($activeOrders) ?>)
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link rounded-pill px-4 fw-semibold" id="history-tab" data-bs-toggle="pill" data-bs-target="#history-orders" type="button" role="tab" aria-controls="history-orders" aria-selected="false">
                    <i class="bi bi-check2-circle me-1"></i> Riwayat Selesai (<?= count($historyOrders) ?>)
                </button>
            </li>
        </ul>

        <div class="tab-content" id="orderTabsContent">
            <div class="tab-pane fade show active" id="active-orders" role="tabpanel" aria-labelledby="active-tab">
                <?= $renderOrderTable($activeOrders, 'Tidak ada pesanan aktif saat ini') ?>
            </div>
            <div class="tab-pane fade" id="history-orders" role="tabpanel" aria-labelledby="history-tab">
                <?= $renderOrderTable($historyOrders, 'Belum ada riwayat pesanan selesai') ?>
            </div>
        </div>
    </div>
</section>
