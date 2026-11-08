<section class="py-4 bg-light min-vh-100">
    <div class="container">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
            <div>
                <h3 class="fw-bold mb-1"><i class="bi bi-bicycle text-info me-2"></i>Tugas Aktif</h3>
                <p class="text-muted small mb-0">Tugas penjemputan &amp; pengantaran yang sedang berjalan (<?= htmlspecialchars($driver['driver_name']) ?>)</p>
            </div>
            <div class="d-flex gap-2">
                <a href="/driver/dashboard" class="btn btn-outline-secondary rounded-pill px-3 fw-medium">
                    <i class="bi bi-speedometer2 me-1"></i> Dashboard
                </a>
                <a href="/driver/history" class="btn btn-outline-info rounded-pill px-3 fw-medium">
                    <i class="bi bi-clock-history me-1"></i> Riwayat
                </a>
            </div>
        </div>

        <?php if (!empty($orders)): ?>
            <div class="row g-3">
                <?php foreach ($orders as $ord): ?>
                    <div class="col-12">
                        <div class="card border-0 shadow-sm rounded-4 p-4 bg-white hover-lift">
                            <div class="row align-items-center g-3">
                                <div class="col-md-3">
                                    <span class="badge bg-primary text-white rounded-pill px-3 py-1 mb-1 fw-bold">
                                        #<?= htmlspecialchars($ord['order_number']) ?>
                                    </span>
                                    <?php if (!empty($ord['task_type'])): ?>
                                        <span class="badge <?= $ord['task_type'] === 'Penjemputan' ? 'bg-info' : 'bg-success' ?> rounded-pill mb-1">
                                            <?= $ord['task_type'] ?>
                                        </span>
                                    <?php endif; ?>
                                    <h6 class="fw-bold text-dark mb-0"><?= htmlspecialchars($ord['recipient_name']) ?></h6>
                                    <small class="text-muted"><i class="bi bi-telephone me-1"></i><?= htmlspecialchars($ord['recipient_phone']) ?></small>
                                </div>

                                <div class="col-md-4">
                                    <span class="text-muted small d-block mb-1">Alamat Penjemputan (Pickup):</span>
                                    <p class="text-dark small mb-0 fw-semibold text-truncate"><?= htmlspecialchars($ord['full_address']) ?></p>
                                    <small class="text-muted">RT <?= htmlspecialchars($ord['rt']) ?> / RW <?= htmlspecialchars($ord['rw']) ?>, <?= htmlspecialchars($ord['district'] ?? '-') ?></small>
                                </div>

                                <div class="col-md-3">
                                    <span class="text-muted small d-block mb-1">Status Perjalanan:</span>
                                    <span class="badge bg-info-subtle text-info rounded-pill px-3 py-1 fw-bold">
                                        <?= htmlspecialchars($ord['order_status']) ?>
                                    </span>
                                </div>

                                <div class="col-md-2 text-md-end">
                                    <a href="/driver/orders/<?= $ord['id'] ?>" class="btn btn-info rounded-pill px-4 fw-bold text-white shadow-sm">
                                        Detail &amp; Update <i class="bi bi-chevron-right ms-1"></i>
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="card border-0 shadow-sm rounded-4 p-5 text-center my-4">
                <i class="bi bi-check2-all text-success display-4 mb-3"></i>
                <h5 class="fw-bold mb-2">Tidak Ada Tugas Aktif</h5>
                <p class="text-muted max-w-500 mx-auto mb-4">Semua tugas Anda sudah selesai. Tunggu penugasan berikutnya dari toko laundry.</p>
                <a href="/driver/history" class="btn btn-outline-info rounded-pill px-4 fw-semibold">
                    <i class="bi bi-clock-history me-1"></i> Lihat Riwayat Tugas
                </a>
            </div>
        <?php endif; ?>
    </div>
</section>
