<section class="py-4 bg-light min-vh-100">
    <div class="container">
        <div class="d-flex align-items-center justify-content-between mb-4">
            <div>
                <h3 class="fw-bold mb-1"><i class="bi bi-bicycle text-info me-2"></i>Daftar & Status Kurir</h3>
                <p class="text-muted small mb-0">Pantau ketersediaan dan informasi armada kurir</p>
            </div>
            <a href="/owner/orders" class="btn btn-outline-secondary rounded-pill px-4 fw-medium">
                <i class="bi bi-arrow-left me-1"></i> Kembali ke Pesanan Toko
            </a>
        </div>

        <?php if (!empty($drivers)): ?>
            <div class="row g-3">
                <?php foreach ($drivers as $dr): ?>
                    <div class="col-md-6 col-lg-4">
                        <div class="card border-0 shadow-sm rounded-4 p-4 hover-lift">
                            <div class="d-flex align-items-center gap-3 mb-3">
                                <div class="avatar-circle bg-info text-white rounded-circle p-3 d-flex align-items-center justify-content-center" style="width: 50px; height: 50px;">
                                    <i class="bi bi-person-fill fs-3"></i>
                                </div>
                                <div>
                                    <h6 class="fw-bold text-dark mb-0"><?= htmlspecialchars($dr['driver_name']) ?></h6>
                                    <small class="text-muted"><i class="bi bi-telephone me-1"></i><?= htmlspecialchars($dr['driver_phone'] ?? '-') ?></small>
                                </div>
                            </div>

                            <div class="p-3 bg-light rounded-3 border mb-3">
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <span class="small text-secondary">Kendaraan:</span>
                                    <strong class="text-dark small"><?= htmlspecialchars($dr['vehicle_type']) ?></strong>
                                </div>
                                <div class="d-flex justify-content-between align-items-center">
                                    <span class="small text-secondary">Plat Nomor:</span>
                                    <strong class="badge bg-dark text-white font-monospace"><?= htmlspecialchars($dr['license_plate']) ?></strong>
                                </div>
                            </div>

                            <div class="d-flex align-items-center justify-content-between">
                                <span class="small text-muted">Status Ketersediaan:</span>
                                <?php if ((int)($dr['is_available'] ?? 0) === 1): ?>
                                    <span class="badge bg-success-subtle text-success rounded-pill px-3 py-1.5 fw-semibold">
                                        <i class="bi bi-check-circle-fill me-1"></i> Tersedia
                                    </span>
                                <?php else: ?>
                                    <span class="badge bg-secondary-subtle text-secondary rounded-pill px-3 py-1.5 fw-semibold">
                                        <i class="bi bi-clock-fill me-1"></i> Sedang bertugas
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="card border-0 shadow-sm rounded-4 p-5 text-center my-4">
                <i class="bi bi-person-x text-muted display-4 mb-3"></i>
                <h5 class="fw-bold mb-2">Belum Ada Kurir Terdaftar</h5>
                <p class="text-muted max-w-500 mx-auto">Kurir yang mendaftar dan aktif akan tampil di halaman ini.</p>
            </div>
        <?php endif; ?>
    </div>
</section>
