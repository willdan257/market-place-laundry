<section class="py-5 bg-light">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-9 col-lg-8">
                <div class="card border-0 shadow-lg rounded-4 overflow-hidden">
                    <div class="card-header bg-primary text-white p-4">
                        <div class="d-flex align-items-center gap-3">
                            <div class="bg-white text-primary rounded-circle p-3 d-flex align-items-center justify-content-center" style="width: 50px; height: 50px;">
                                <i class="bi bi-cart-plus-fill fs-3"></i>
                            </div>
                            <div>
                                <h4 class="fw-bold mb-0">Buat Pesanan Laundry Baru</h4>
                                <p class="mb-0 small opacity-75">
                                    <?= $selectedStore ? 'Layanan dan alamat penjemputan pakaian' : 'Langkah 1: Pilih Toko Laundry Terpercaya' ?>
                                </p>
                            </div>
                        </div>
                    </div>

                    <div class="card-body p-4 p-lg-5">
                        <?php if (!$selectedStore): ?>
                            <!-- ========================================== -->
                            <!-- LANGKAH 1: PELANGGAN MEMILIH TOKO LAUNDRY   -->
                            <!-- ========================================== -->
                            <div class="d-flex align-items-center justify-content-between mb-4">
                                <div>
                                    <h5 class="fw-bold text-dark mb-1">
                                        <i class="bi bi-shop text-primary me-2"></i>1. Pilih Toko Laundry Mitra
                                    </h5>
                                    <p class="text-muted small mb-0">Pilih salah satu toko laundry aktif dan terverifikasi untuk melanjutkan pemesanan.</p>
                                </div>
                            </div>

                            <?php if (!empty($stores)): ?>
                                <div class="row g-3 mb-4">
                                    <?php foreach ($stores as $st): ?>
                                        <div class="col-12">
                                            <div class="card border rounded-4 p-3 hover-lift shadow-sm transition">
                                                <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center gap-3">
                                                    <div class="flex-grow-1">
                                                        <div class="d-flex flex-wrap align-items-center gap-2 mb-1">
                                                            <h5 class="fw-bold text-dark mb-0"><?= htmlspecialchars($st['store_name']) ?></h5>
                                                            <span class="badge bg-success-subtle text-success rounded-pill px-2.5 py-1 small">
                                                                <i class="bi bi-check-circle-fill me-1"></i>Buka & Terverifikasi
                                                            </span>
                                                            <?php if (!empty($st['distance_km'])): ?>
                                                                <span class="badge bg-info-subtle text-info rounded-pill px-2.5 py-1 small">
                                                                    <i class="bi bi-geo-fill me-1"></i>~<?= number_format($st['distance_km'], 1) ?> km dari lokasi Anda
                                                                </span>
                                                            <?php endif; ?>
                                                        </div>

                                                        <p class="text-secondary small mb-2">
                                                            <i class="bi bi-geo-alt text-danger me-1"></i><?= htmlspecialchars($st['full_address']) ?>
                                                        </p>

                                                        <div class="d-flex flex-wrap align-items-center gap-3 text-muted small">
                                                            <span><i class="bi bi-telephone text-primary me-1"></i><?= htmlspecialchars($st['phone']) ?></span>
                                                            <span><i class="bi bi-basket text-primary me-1"></i><strong><?= $st['service_count'] ?></strong> Layanan Tersedia</span>
                                                            <?php if ($st['min_price'] !== null && $st['min_price'] > 0): ?>
                                                                <span class="text-success fw-semibold">
                                                                    <i class="bi bi-tag-fill me-1"></i>Mulai Rp <?= number_format($st['min_price'], 0, ',', '.') ?>
                                                                </span>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>

                                                    <div class="text-md-end w-100 w-md-auto mt-2 mt-md-0">
                                                        <a href="/customer/orders/create?store_id=<?= $st['id'] ?>" class="btn btn-primary rounded-pill px-4 py-2 fw-semibold shadow-sm w-100 w-md-auto">
                                                            Pilih Toko Ini <i class="bi bi-arrow-right ms-1"></i>
                                                        </a>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <div class="alert alert-warning rounded-4 p-4 text-center my-4">
                                    <i class="bi bi-exclamation-triangle-fill display-5 text-warning mb-3 d-block"></i>
                                    <h5 class="fw-bold mb-1">Belum ada toko yang tersedia saat ini</h5>
                                    <p class="text-muted small mb-0">Toko laundry mitra sedang dalam proses verifikasi atau sedang tutup. Silakan cek kembali beberapa saat lagi.</p>
                                </div>
                            <?php endif; ?>

                            <div class="d-flex justify-content-between align-items-center pt-3 border-top">
                                <a href="/customer/dashboard" class="btn btn-outline-secondary rounded-pill px-4 fw-medium">
                                    <i class="bi bi-arrow-left me-1"></i>Kembali ke Dashboard
                                </a>
                            </div>

                        <?php else: ?>
                            <!-- ========================================== -->
                            <!-- LANGKAH 2: TOKO TERPILIH, PILIH LAYANAN & FORM -->
                            <!-- ========================================== -->
                            
                            <!-- Kartu Info Toko yang Dipilih -->
                            <div class="card bg-primary-subtle border-0 rounded-4 p-3 mb-4">
                                <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
                                    <div>
                                        <span class="badge bg-primary text-white rounded-pill px-2.5 py-1 mb-1 small fw-bold">Toko Laundry Terpilih</span>
                                        <h5 class="fw-bold text-dark mb-0"><?= htmlspecialchars($selectedStore['store_name']) ?></h5>
                                        <small class="text-secondary d-block">
                                            <i class="bi bi-geo-alt me-1"></i><?= htmlspecialchars($selectedStore['full_address']) ?> (Telp: <?= htmlspecialchars($selectedStore['phone']) ?>)
                                        </small>
                                    </div>
                                    <div>
                                        <a href="/customer/orders/create" class="btn btn-outline-primary btn-sm rounded-pill px-3 fw-semibold">
                                            <i class="bi bi-arrow-repeat me-1"></i>Ganti Toko
                                        </a>
                                    </div>
                                </div>
                            </div>

                            <form method="POST" action="/customer/orders/confirm" class="needs-validation" novalidate>
                                <input type="hidden" name="_token" value="<?= \App\Helpers\Session::csrfToken() ?>">
                                <input type="hidden" name="store_id" value="<?= $selectedStore['id'] ?>">

                                <!-- 2. Pilihan Layanan dari Toko Terpilih -->
                                <h5 class="fw-bold text-dark mb-3">
                                    <i class="bi bi-basket-fill text-primary me-2"></i>2. Pilih Layanan Laundry
                                </h5>

                                <div class="mb-4">
                                    <div class="row g-3">
                                        <?php if (!empty($services)): ?>
                                            <?php foreach ($services as $svc): ?>
                                                <div class="col-md-6">
                                                    <div class="card border rounded-3 p-3 hover-lift h-100 position-relative">
                                                        <div class="form-check">
                                                            <input class="form-check-input me-2" type="radio" name="service_id" id="svc_<?= $svc['id'] ?>" value="<?= $svc['id'] ?>" required <?= (isset($_POST['service_id']) && $_POST['service_id'] == $svc['id']) ? 'checked' : '' ?>>
                                                            <label class="form-check-label w-100 cursor-pointer" for="svc_<?= $svc['id'] ?>">
                                                                <div class="d-flex justify-content-between align-items-start mb-1">
                                                                    <strong class="text-dark fs-6"><?= htmlspecialchars($svc['service_name']) ?></strong>
                                                                </div>
                                                                <span class="badge bg-primary-subtle text-primary rounded-pill mb-2">
                                                                    Rp <?= number_format($svc['price_per_unit'], 0, ',', '.') ?> / <?= htmlspecialchars($svc['unit']) ?>
                                                                </span>
                                                                <p class="text-muted small mb-0"><?= htmlspecialchars($svc['description'] ?? '') ?></p>
                                                                <small class="text-secondary d-block mt-2">
                                                                    <i class="bi bi-clock me-1"></i> Estimasi: <?= $svc['estimated_duration_hours'] ?> Jam
                                                                </small>
                                                            </label>
                                                        </div>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <div class="col-12">
                                                <div class="alert alert-warning rounded-3 mb-0">
                                                    Toko ini belum menambahkan layanan aktif. Silakan <a href="/customer/orders/create" class="fw-bold">pilih toko lain</a>.
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <?php if (isset($errors['service_id'])): ?>
                                        <div class="text-danger small mt-2"><?= htmlspecialchars($errors['service_id']) ?></div>
                                    <?php endif; ?>
                                </div>

                                <hr class="my-4">

                                <!-- 3. Alamat & Kontak Penjemputan -->
                                <h5 class="fw-bold text-dark mb-3">
                                    <i class="bi bi-geo-alt-fill text-danger me-2"></i>3. Alamat & Kontak Penjemputan
                                </h5>

                                <div class="mb-3">
                                    <label class="form-label fw-semibold text-secondary">Nomor Telepon Penjemputan <span class="text-danger">*</span></label>
                                    <input type="tel" name="recipient_phone" class="form-control rounded-3 <?= isset($errors['recipient_phone']) ? 'is-invalid' : '' ?>" placeholder="Contoh: 081234567890" value="<?= htmlspecialchars($_POST['recipient_phone'] ?? $address['recipient_phone'] ?? $user['phone'] ?? '') ?>" required pattern="[0-9+ -]{9,16}">
                                    <?php if (isset($errors['recipient_phone'])): ?>
                                        <div class="invalid-feedback"><?= htmlspecialchars($errors['recipient_phone']) ?></div>
                                    <?php endif; ?>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label fw-semibold text-secondary">Alamat Lengkap Pickup <span class="text-danger">*</span></label>
                                    <textarea name="full_address" class="form-control rounded-3 <?= isset($errors['full_address']) ? 'is-invalid' : '' ?>" rows="3" placeholder="Nama Jalan, Nomor Rumah, Komplek..." required><?= htmlspecialchars($_POST['full_address'] ?? $address['full_address'] ?? '') ?></textarea>
                                    <?php if (isset($errors['full_address'])): ?>
                                        <div class="invalid-feedback"><?= htmlspecialchars($errors['full_address']) ?></div>
                                    <?php endif; ?>
                                </div>

                                <div class="row g-3 mb-3">
                                    <div class="col-6">
                                        <label class="form-label fw-semibold text-secondary">RT <span class="text-danger">*</span></label>
                                        <input type="text" name="rt" class="form-control rounded-3 <?= isset($errors['rt']) ? 'is-invalid' : '' ?>" placeholder="001" value="<?= htmlspecialchars($_POST['rt'] ?? $address['rt'] ?? '') ?>" required>
                                        <?php if (isset($errors['rt'])): ?>
                                            <div class="invalid-feedback"><?= htmlspecialchars($errors['rt']) ?></div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="col-6">
                                        <label class="form-label fw-semibold text-secondary">RW <span class="text-danger">*</span></label>
                                        <input type="text" name="rw" class="form-control rounded-3 <?= isset($errors['rw']) ? 'is-invalid' : '' ?>" placeholder="005" value="<?= htmlspecialchars($_POST['rw'] ?? $address['rw'] ?? '') ?>" required>
                                        <?php if (isset($errors['rw'])): ?>
                                            <div class="invalid-feedback"><?= htmlspecialchars($errors['rw']) ?></div>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <div class="row g-3 mb-3">
                                    <div class="col-md-6">
                                        <label class="form-label fw-semibold text-secondary">Kecamatan</label>
                                        <input type="text" name="district" class="form-control rounded-3" placeholder="Nama Kecamatan" value="<?= htmlspecialchars($_POST['district'] ?? $address['district'] ?? '') ?>">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label fw-semibold text-secondary">Kota / Kabupaten</label>
                                        <input type="text" name="city" class="form-control rounded-3" placeholder="Nama Kota" value="<?= htmlspecialchars($_POST['city'] ?? $address['city'] ?? '') ?>">
                                    </div>
                                </div>

                                <div class="row g-3 mb-4">
                                    <div class="col-6">
                                        <label class="form-label fw-semibold text-secondary">Latitude</label>
                                        <input type="text" name="latitude" class="form-control rounded-3" value="<?= htmlspecialchars($_POST['latitude'] ?? $address['latitude'] ?? '-6.200000') ?>">
                                    </div>
                                    <div class="col-6">
                                        <label class="form-label fw-semibold text-secondary">Longitude</label>
                                        <input type="text" name="longitude" class="form-control rounded-3" value="<?= htmlspecialchars($_POST['longitude'] ?? $address['longitude'] ?? '106.816666') ?>">
                                    </div>
                                </div>

                                <div class="mb-4">
                                    <label class="form-label fw-semibold text-secondary">Catatan Khusus Pesanan (Opsional)</label>
                                    <textarea name="notes" class="form-control rounded-3" rows="2" placeholder="Contoh: Tolong pisahkan baju putih, jangan pakai pewangi menyengat..."><?= htmlspecialchars($_POST['notes'] ?? '') ?></textarea>
                                </div>

                                <div class="d-flex justify-content-between align-items-center pt-3 border-top">
                                    <a href="/customer/orders/create" class="btn btn-outline-secondary rounded-pill px-4 fw-medium">
                                        <i class="bi bi-arrow-left me-1"></i>Ganti Toko
                                    </a>
                                    <button type="submit" class="btn btn-primary rounded-pill px-5 py-2.5 fw-bold shadow-sm" <?= empty($services) ? 'disabled' : '' ?>>
                                        Lanjut ke Konfirmasi <i class="bi bi-arrow-right ms-1"></i>
                                    </button>
                                </div>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
