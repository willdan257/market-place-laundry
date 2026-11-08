<section class="py-4 bg-light min-vh-100">
    <div class="container">
        <!-- Optional Email Verification Notice -->
        <?php if (empty($user['email_verified_at'])): ?>
            <div class="alert rounded-4 p-3 mb-4 d-flex flex-wrap align-items-center justify-content-between gap-3 shadow-sm border-0"
                 style="background:rgba(14,165,233,0.12);border:1px solid rgba(14,165,233,0.25);color:#0369a1;">
                <div class="d-flex align-items-center gap-2">
                    <i class="bi bi-info-circle-fill fs-4 text-info"></i>
                    <div>
                        <strong class="d-block">Email belum diverifikasi (opsional)</strong>
                        <span class="small">Verifikasi membantu keamanan akun dan pemulihan password. Link verifikasi telah dikirim ke <strong><?= htmlspecialchars($user['email'] ?? '') ?></strong>.</span>
                    </div>
                </div>
                <div>
                    <form method="POST" action="/verifikasi-email/kirim-ulang" class="d-inline">
                        <input type="hidden" name="_token" value="<?= \App\Helpers\Session::csrfToken() ?>">
                        <button type="submit" class="btn btn-info text-white btn-sm rounded-pill px-3 fw-semibold shadow-sm">
                            <i class="bi bi-send me-1"></i> Kirim Ulang Link Verifikasi
                        </button>
                    </form>
                </div>
            </div>
        <?php endif; ?>

        <!-- Dashboard Header -->
        <div class="card border-0 shadow-sm rounded-4 p-4 mb-4 bg-primary text-white">
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
                <div>
                    <span class="badge bg-white text-primary rounded-pill px-3 py-1 mb-2 fw-bold">Role: CUSTOMER</span>
                    <h3 class="fw-bold mb-1">Selamat Datang, <?= htmlspecialchars($user['name'] ?? 'Pelanggan') ?>!</h3>
                    <p class="mb-0 text-white-50">Kelola pesanan laundry, buat order baru, dan pantau status penjemputan secara real-time.</p>
                </div>
                <div class="d-flex gap-2">
                    <a href="/customer/orders/create" class="btn btn-light rounded-pill px-4 fw-bold text-primary shadow-sm">
                        <i class="bi bi-plus-lg me-1"></i> Buat Order Laundry
                    </a>
                    <a href="/customer/assistant" class="btn btn-outline-light rounded-pill px-3 fw-medium">
                        <i class="bi bi-robot me-1"></i> Tanya AI
                    </a>
                    <a href="/profile" class="btn btn-outline-light rounded-pill px-3 fw-medium">
                        <i class="bi bi-person-gear"></i>
                    </a>
                </div>
            </div>
        </div>

        <!-- Metric Cards (dari semua pesanan) -->
        <div class="row g-3 mb-4">
            <div class="col-md-4">
                <div class="card border-0 shadow-sm rounded-4 p-3 bg-white hover-lift">
                    <div class="d-flex align-items-center gap-3">
                        <div class="bg-primary-subtle text-primary rounded-circle p-3">
                            <i class="bi bi-basket fs-4"></i>
                        </div>
                        <div>
                            <span class="text-muted small">Total Pesanan</span>
                            <h4 class="fw-bold mb-0"><?= $totalCount ?></h4>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card border-0 shadow-sm rounded-4 p-3 bg-white hover-lift">
                    <div class="d-flex align-items-center gap-3">
                        <div class="bg-warning-subtle text-warning rounded-circle p-3">
                            <i class="bi bi-clock-history fs-4"></i>
                        </div>
                        <div>
                            <span class="text-muted small">Pesanan Aktif</span>
                            <h4 class="fw-bold mb-0"><?= $pendingCount ?></h4>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card border-0 shadow-sm rounded-4 p-3 bg-white hover-lift">
                    <div class="d-flex align-items-center gap-3">
                        <div class="bg-success-subtle text-success rounded-circle p-3">
                            <i class="bi bi-check2-circle fs-4"></i>
                        </div>
                        <div>
                            <span class="text-muted small">Pesanan Selesai</span>
                            <h4 class="fw-bold mb-0"><?= $completedCount ?></h4>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Pesanan Aktif (bukan riwayat) -->
        <div class="card border-0 shadow-sm rounded-4 p-4">
            <div class="d-flex align-items-center justify-content-between mb-3">
                <h5 class="fw-bold mb-0"><i class="bi bi-clock-history text-primary me-2"></i>Pesanan Aktif</h5>
                <a href="/customer/orders" class="text-primary fw-semibold small text-decoration-none">
                    Lihat Semua & Riwayat <i class="bi bi-arrow-right"></i>
                </a>
            </div>

            <div id="customerActiveOrdersContainer">
                <?php if (!empty($activeOrders)): ?>
                    <div class="table-responsive">
                        <table class="table align-middle table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Nomor Order</th>
                                    <th>Layanan</th>
                                    <th>Toko Laundry</th>
                                    <th>Tanggal</th>
                                    <th>Status</th>
                                    <th class="text-end">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach (array_slice($activeOrders, 0, 5) as $ord): ?>
                                    <tr>
                                        <td><strong>#<?= htmlspecialchars($ord['order_number']) ?></strong></td>
                                        <td><?= htmlspecialchars($ord['service_name'] ?? 'Laundry') ?></td>
                                        <td><?= htmlspecialchars($ord['store_name'] ?? '-') ?></td>
                                        <td><?= date('d M Y, H:i', strtotime($ord['created_at'])) ?></td>
                                        <td>
                                            <?php $b = \App\Helpers\OrderStatusHelper::getBadge($ord['order_status']); ?>
                                            <span class="badge <?= $b[0] ?> rounded-pill px-3 py-1">
                                                <?= $b[1] ?>
                                            </span>
                                        </td>
                                        <td class="text-end">
                                            <a href="/customer/orders/<?= $ord['id'] ?>" class="btn btn-sm btn-outline-primary rounded-pill px-3">
                                                Detail
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="text-center py-4">
                        <i class="bi bi-cart-check text-success display-5 mb-3"></i>
                        <h5 class="fw-bold mb-2">Tidak Ada Pesanan Aktif</h5>
                        <p class="text-muted max-w-500 mx-auto mb-4">
                            Semua pesanan Anda sudah selesai atau belum ada pesanan. Buat order baru atau cek riwayat pesanan Anda.
                        </p>
                        <div class="d-flex justify-content-center gap-2 flex-wrap">
                            <a href="/customer/orders/create" class="btn btn-primary rounded-pill px-4 py-2 fw-bold shadow-sm">
                                <i class="bi bi-plus-lg me-1"></i> Buat Order Laundry
                            </a>
                            <a href="/customer/orders?tab=history" class="btn btn-outline-secondary rounded-pill px-4 py-2 fw-medium">
                                <i class="bi bi-clock-history me-1"></i> Lihat Riwayat
                            </a>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>

<script>
(function() {
    function escapeHtml(str) {
        if (!str) return '';
        var div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }

    function refreshActiveOrders() {
        fetch('/customer/dashboard/active-orders', {
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(function(res) {
            if (!res.ok) return null;
            return res.json();
        })
        .then(function(data) {
            if (!data || !data.success) return;
            var container = document.getElementById('customerActiveOrdersContainer');
            if (!container) return;

            if (!data.orders || data.orders.length === 0) {
                container.innerHTML = '<div class="text-center py-4">' +
                    '<i class="bi bi-cart-check text-success display-5 mb-3"></i>' +
                    '<h5 class="fw-bold mb-2">Tidak Ada Pesanan Aktif</h5>' +
                    '<p class="text-muted max-w-500 mx-auto mb-4">Semua pesanan Anda sudah selesai atau belum ada pesanan. Buat order baru atau cek riwayat pesanan Anda.</p>' +
                    '<div class="d-flex justify-content-center gap-2 flex-wrap">' +
                        '<a href="/customer/orders/create" class="btn btn-primary rounded-pill px-4 py-2 fw-bold shadow-sm"><i class="bi bi-plus-lg me-1"></i> Buat Order Laundry</a>' +
                        '<a href="/customer/orders?tab=history" class="btn btn-outline-secondary rounded-pill px-4 py-2 fw-medium"><i class="bi bi-clock-history me-1"></i> Lihat Riwayat</a>' +
                    '</div>' +
                '</div>';
                return;
            }

            var rows = '';
            var list = data.orders.slice(0, 5);
            list.forEach(function(ord) {
                rows += '<tr>' +
                    '<td><strong>#' + escapeHtml(ord.order_number) + '</strong></td>' +
                    '<td>' + escapeHtml(ord.service_name) + '</td>' +
                    '<td>' + escapeHtml(ord.store_name) + '</td>' +
                    '<td>' + escapeHtml(ord.created_at) + '</td>' +
                    '<td><span class="badge ' + escapeHtml(ord.status_badge_class) + ' rounded-pill px-3 py-1">' + escapeHtml(ord.status_label) + '</span></td>' +
                    '<td class="text-end"><a href="/customer/orders/' + encodeURIComponent(ord.id) + '" class="btn btn-sm btn-outline-primary rounded-pill px-3">Detail</a></td>' +
                '</tr>';
            });

            container.innerHTML = '<div class="table-responsive">' +
                '<table class="table align-middle table-hover mb-0">' +
                    '<thead class="table-light">' +
                        '<tr>' +
                            '<th>Nomor Order</th>' +
                            '<th>Layanan</th>' +
                            '<th>Toko Laundry</th>' +
                            '<th>Tanggal</th>' +
                            '<th>Status</th>' +
                            '<th class="text-end">Aksi</th>' +
                        '</tr>' +
                    '</thead>' +
                    '<tbody>' + rows + '</tbody>' +
                '</table>' +
            '</div>';
        })
        .catch(function() {
            // Tangani error fetch dengan diam (jangan merusak halaman)
        });
    }

    setInterval(refreshActiveOrders, 30000);
})();
</script>
