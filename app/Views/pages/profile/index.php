<section class="py-5 bg-light min-vh-100">
    <div class="container py-3">
        <div class="row g-4">
            <div class="col-lg-4">
                <div class="card border-0 shadow-sm rounded-4 p-4 text-center">
                    <div class="avatar-circle bg-primary text-white rounded-circle d-flex align-items-center justify-content-center mx-auto mb-3" style="width: 80px; height: 80px;">
                        <i class="bi bi-person-fill fs-1"></i>
                    </div>
                    <h5 class="fw-bold mb-1"><?= htmlspecialchars($user['name'] ?? 'User') ?></h5>
                    <p class="text-muted small mb-2"><?= htmlspecialchars($user['email'] ?? '') ?></p>
                    <span class="badge bg-primary-subtle text-primary rounded-pill px-3 py-2 fw-semibold">
                        Role: <?= strtoupper($user['role'] ?? 'CUSTOMER') ?>
                    </span>
                    <hr class="my-3">
                    <div class="d-grid gap-2">
                        <form method="POST" action="/logout" class="w-100">
                            <input type="hidden" name="_token" value="<?= \App\Helpers\Session::csrfToken() ?>">
                            <button type="submit" class="btn btn-outline-danger btn-sm rounded-pill fw-medium w-100">
                                <i class="bi bi-box-arrow-right me-1"></i> Logout Akun
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-lg-8">
                <div class="card border-0 shadow-sm rounded-4 p-4">
                    <h5 class="fw-bold mb-3"><i class="bi bi-person-gear text-primary me-2"></i>Detail Informasi Pengguna</h5>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label text-muted small fw-semibold">Nama Lengkap</label>
                            <p class="fw-bold text-dark mb-0"><?= htmlspecialchars($user['name'] ?? '-') ?></p>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-muted small fw-semibold">Nomor Telepon</label>
                            <p class="fw-bold text-dark mb-0"><?= htmlspecialchars($user['phone'] ?? '-') ?></p>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-muted small fw-semibold">Email</label>
                            <p class="fw-bold text-dark mb-0"><?= htmlspecialchars($user['email'] ?? '-') ?></p>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-muted small fw-semibold">Status Login</label>
                            <p class="fw-bold text-success mb-0"><i class="bi bi-shield-check me-1"></i> Terotentikasi via Google</p>
                        </div>
                    </div>

                    <?php if (($user['role'] ?? 'customer') === 'customer'): ?>
                        <hr class="my-4">
                        <h5 class="fw-bold mb-3"><i class="bi bi-geo-alt-fill text-danger me-2"></i>Alamat Penjemputan</h5>
                        <?php if (isset($address) && $address): ?>
                            <div class="p-3 bg-light rounded-3 border">
                                <p class="fw-semibold text-dark mb-1"><?= htmlspecialchars($address['recipient_name']) ?> (<?= htmlspecialchars($address['recipient_phone']) ?>)</p>
                                <p class="text-secondary small mb-1"><?= htmlspecialchars($address['full_address']) ?></p>
                                <div class="d-flex gap-3 text-muted small">
                                    <span>RT: <strong><?= htmlspecialchars($address['rt'] ?? '-') ?></strong></span>
                                    <span>RW: <strong><?= htmlspecialchars($address['rw'] ?? '-') ?></strong></span>
                                    <span>Kecamatan: <strong><?= htmlspecialchars($address['district'] ?? '-') ?></strong></span>
                                    <span>Kota: <strong><?= htmlspecialchars($address['city'] ?? '-') ?></strong></span>
                                </div>
                                <div class="mt-2 text-muted small">
                                    <i class="bi bi-pin-map-fill me-1 text-danger"></i> Koordinat: <?= htmlspecialchars($address['latitude'] ?? '-') ?>, <?= htmlspecialchars($address['longitude'] ?? '-') ?>
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="alert alert-warning small">Alamat utama belum diisi.</div>
                        <?php endif; ?>

                        <div class="mt-4 d-flex gap-2 flex-wrap">
                            <a href="/profile/edit" class="btn btn-primary btn-sm rounded-pill px-3 fw-semibold">
                                <i class="bi bi-pencil-square me-1"></i> Edit Profil &amp; Akun
                            </a>
                            <a href="/profile/complete" class="btn btn-outline-primary btn-sm rounded-pill px-3 fw-semibold">
                                <i class="bi bi-geo-alt me-1"></i> Edit Alamat Utama
                            </a>
                        </div>
                    <?php else: ?>
                        <div class="mt-4">
                            <a href="/profile/edit" class="btn btn-primary btn-sm rounded-pill px-3 fw-semibold">
                                <i class="bi bi-pencil-square me-1"></i> Edit Profil &amp; Akun
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</section>
