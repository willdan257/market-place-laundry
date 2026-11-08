<section class="py-4 bg-light min-vh-100">
    <div class="container">
        <!-- Driver Dashboard Header -->
        <div class="card border-0 shadow-sm rounded-4 p-4 mb-4 bg-info text-white">
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
                <div>
                    <span class="badge bg-white text-info rounded-pill px-3 py-1 mb-2 fw-bold">KURIR / DRIVER</span>
                    <h3 class="fw-bold mb-1">Dashboard Pendapatan Kurir</h3>
                    <p class="mb-0 text-white-50">Kurir: <?= htmlspecialchars($user['name'] ?? 'Driver') ?> (<?= htmlspecialchars($user['email'] ?? '') ?>)</p>
                </div>
                <div class="d-flex gap-2 flex-wrap">
                    <a href="/driver/orders" class="btn btn-light rounded-pill px-4 fw-bold text-info shadow-sm">
                        <i class="bi bi-bicycle me-1"></i> Tugas Aktif
                    </a>
                    <a href="/driver/history" class="btn btn-outline-light rounded-pill px-3 fw-medium">
                        <i class="bi bi-clock-history me-1"></i> Riwayat
                    </a>
                    <a href="/profile" class="btn btn-outline-light rounded-pill px-3 fw-medium">
                        <i class="bi bi-person-gear"></i>
                    </a>
                </div>
            </div>
        </div>

        <!-- Kartu Pendapatan -->
        <div class="row g-3 mb-4">
            <div class="col-6 col-md-3">
                <div class="card border-0 shadow-sm rounded-4 p-3 bg-white text-center hover-lift">
                    <span class="text-muted small">Hari Ini</span>
                    <h4 class="fw-bold text-success mb-0">Rp <?= number_format($earnings['today'], 0, ',', '.') ?></h4>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="card border-0 shadow-sm rounded-4 p-3 bg-white text-center hover-lift">
                    <span class="text-muted small">Minggu Ini</span>
                    <h4 class="fw-bold text-primary mb-0">Rp <?= number_format($earnings['week'], 0, ',', '.') ?></h4>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="card border-0 shadow-sm rounded-4 p-3 bg-white text-center hover-lift">
                    <span class="text-muted small">Bulan Ini</span>
                    <h4 class="fw-bold text-warning mb-0">Rp <?= number_format($earnings['month'], 0, ',', '.') ?></h4>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="card border-0 shadow-sm rounded-4 p-3 bg-white text-center hover-lift">
                    <span class="text-muted small">Total Pengantaran</span>
                    <h4 class="fw-bold text-info mb-0"><?= $earnings['completed_count'] ?> kali</h4>
                </div>
            </div>
        </div>
        <div class="card border-0 shadow-sm rounded-4 p-3 mb-4 bg-white text-center">
            <span class="text-muted small">Total Pendapatan Semua Waktu</span>
            <h3 class="fw-bold text-dark mb-0">Rp <?= number_format($earnings['total'], 0, ',', '.') ?></h3>
            <small class="text-muted">50% dari ongkir per tahap yang selesai</small>
        </div>

        <!-- Tugas Aktif (hanya tampil jika ada) -->
        <?php if (!empty($activeTasks)): ?>
            <div class="card border-0 shadow-sm rounded-4 p-4">
                <div class="d-flex align-items-center justify-content-between mb-3">
                    <h5 class="fw-bold mb-0"><i class="bi bi-bicycle text-info me-2"></i>Tugas Aktif
                        <span class="badge bg-danger rounded-pill ms-2"><?= count($activeTasks) ?></span>
                    </h5>
                    <a href="/driver/orders" class="btn btn-sm btn-outline-info rounded-pill text-dark fw-semibold">
                        Lihat Semua Tugas <i class="bi bi-arrow-right"></i>
                    </a>
                </div>
                <div class="table-responsive">
                    <table class="table align-middle table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>No. Order</th>
                                <th>Jenis</th>
                                <th>Customer</th>
                                <th>Alamat Pickup</th>
                                <th>Status</th>
                                <th class="text-end">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach (array_slice($activeTasks, 0, 5) as $ord): ?>
                                <tr>
                                    <td><strong>#<?= htmlspecialchars($ord['order_number']) ?></strong></td>
                                    <td>
                                        <span class="badge <?= $ord['task_type'] === 'Penjemputan' ? 'bg-primary' : 'bg-success' ?> rounded-pill">
                                            <?= $ord['task_type'] ?>
                                        </span>
                                    </td>
                                    <td><?= htmlspecialchars($ord['recipient_name']) ?></td>
                                    <td class="text-truncate" style="max-width:200px;"><?= htmlspecialchars($ord['full_address']) ?></td>
                                    <td>
                                        <span class="badge bg-info-subtle text-info rounded-pill px-3 py-1">
                                            <?= htmlspecialchars($ord['order_status']) ?>
                                        </span>
                                    </td>
                                    <td class="text-end">
                                        <a href="/driver/orders/<?= $ord['id'] ?>" class="btn btn-sm btn-info rounded-pill px-3 text-white fw-bold">
                                            Detail & Update
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php else: ?>
            <div class="card border-0 shadow-sm rounded-4 p-4 text-center">
                <i class="bi bi-check2-all text-success display-5 mb-3"></i>
                <h5 class="fw-bold mb-2">Tidak Ada Tugas Aktif</h5>
                <p class="text-muted mb-4">Semua tugas Anda sudah selesai. Tunggu penugasan berikutnya dari toko laundry.</p>
                <a href="/driver/history" class="btn btn-outline-info rounded-pill px-4 fw-semibold">
                    <i class="bi bi-clock-history me-1"></i> Lihat Riwayat Tugas
                </a>
            </div>
        <?php endif; ?>
    </div>
</section>
