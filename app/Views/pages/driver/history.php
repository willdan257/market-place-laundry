<section class="py-4 bg-light min-vh-100">
    <div class="container">
        <!-- Header -->
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
            <div>
                <h3 class="fw-bold mb-1"><i class="bi bi-clock-history text-info me-2"></i>Riwayat Tugas Kurir</h3>
                <p class="text-muted small mb-0">Riwayat tugas penjemputan &amp; pengantaran yang telah selesai (<?= htmlspecialchars($driver['driver_name']) ?>)</p>
            </div>
            <div class="d-flex gap-2">
                <a href="/driver/dashboard" class="btn btn-outline-secondary rounded-pill px-3 fw-medium">
                    <i class="bi bi-speedometer2 me-1"></i> Dashboard
                </a>
                <a href="/driver/orders" class="btn btn-outline-info rounded-pill px-3 fw-medium">
                    <i class="bi bi-bicycle me-1"></i> Tugas Aktif
                </a>
            </div>
        </div>

        <!-- Table Card -->
        <div class="card border-0 shadow-sm rounded-4 p-4">
            <div class="d-flex align-items-center justify-content-between mb-3">
                <h5 class="fw-bold mb-0">Daftar Riwayat Selesai</h5>
                <span class="text-muted small">Total: <strong><?= $totalRows ?></strong> tugas</span>
            </div>

            <?php if (!empty($history)): ?>
                <div class="table-responsive">
                    <table class="table align-middle table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Tanggal Selesai</th>
                                <th>No. Order</th>
                                <th>Pelanggan</th>
                                <th>Toko Laundry</th>
                                <th>Jenis Tahap</th>
                                <th>Status Order</th>
                                <th class="text-end">Pendapatan</th>
                                <th class="text-end">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($history as $row): ?>
                                <tr>
                                    <td>
                                        <small class="text-muted d-block">
                                            <?= !empty($row['completed_at']) ? date('d M Y, H:i', strtotime($row['completed_at'])) : '-' ?>
                                        </small>
                                    </td>
                                    <td>
                                        <strong>#<?= htmlspecialchars($row['order_number']) ?></strong>
                                    </td>
                                    <td><?= htmlspecialchars($row['recipient_name'] ?? '-') ?></td>
                                    <td><?= htmlspecialchars($row['store_name'] ?? '-') ?></td>
                                    <td>
                                        <span class="badge <?= ($row['task_type'] === 'Penjemputan') ? 'bg-primary' : 'bg-success' ?> rounded-pill">
                                            <?= htmlspecialchars($row['task_type']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge bg-secondary-subtle text-secondary rounded-pill px-2.5 py-1 small">
                                            <?= htmlspecialchars($row['order_status']) ?>
                                        </span>
                                    </td>
                                    <td class="text-end">
                                        <?php if ($row['order_status'] === 'cancelled'): ?>
                                            <span class="text-muted small fst-italic">Rp 0 (Dibatalkan)</span>
                                        <?php else: ?>
                                            <strong class="text-success">+ Rp <?= number_format($row['earning'], 0, ',', '.') ?></strong>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end">
                                        <a href="/driver/orders/<?= $row['id'] ?>" class="btn btn-sm btn-outline-secondary rounded-pill px-3">
                                            Detail
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <?php if ($totalPages > 1): ?>
                    <nav class="d-flex justify-content-center mt-4">
                        <ul class="pagination pagination-sm mb-0">
                            <?php if ($page > 1): ?>
                                <li class="page-item">
                                    <a class="page-link rounded-pill px-3 me-1" href="/driver/history?page=<?= $page - 1 ?>">
                                        &laquo; Sebelumnya
                                    </a>
                                </li>
                            <?php endif; ?>

                            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                <li class="page-item <?= ($i === $page) ? 'active' : '' ?>">
                                    <a class="page-link rounded-circle mx-1" style="width: 32px; height: 32px; display: inline-flex; align-items: center; justify-content: center;" href="/driver/history?page=<?= $i ?>">
                                        <?= $i ?>
                                    </a>
                                </li>
                            <?php endfor; ?>

                            <?php if ($page < $totalPages): ?>
                                <li class="page-item">
                                    <a class="page-link rounded-pill px-3 ms-1" href="/driver/history?page=<?= $page + 1 ?>">
                                        Berikutnya &raquo;
                                    </a>
                                </li>
                            <?php endif; ?>
                        </ul>
                    </nav>
                <?php endif; ?>

            <?php else: ?>
                <div class="text-center py-5 text-muted">
                    <i class="bi bi-inbox display-4 d-block mb-3 text-secondary opacity-50"></i>
                    <h6 class="fw-bold mb-1">Belum Ada Riwayat Tugas</h6>
                    <p class="small mb-0">Tugas yang telah selesai diantar atau dijemput akan tercatat di sini beserta pendapatan Anda.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</section>

