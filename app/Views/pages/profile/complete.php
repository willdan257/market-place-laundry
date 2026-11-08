<section class="py-5 bg-light">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-9 col-lg-7">
                <div class="card border-0 shadow-lg rounded-4 overflow-hidden">
                    <div class="card-header bg-primary text-white p-4 text-center">
                        <i class="bi bi-person-lines-fill fs-2 mb-2 d-block"></i>
                        <h4 class="fw-bold mb-1">Lengkapi Profil & Alamat Pelanggan</h4>
                        <p class="mb-0 small opacity-75">Harap melengkapi data lokasi dan kontak untuk kemudahan proses pickup & delivery laundry Anda.</p>
                    </div>
                    <div class="card-body p-4 p-lg-5">
                        <form method="POST" action="/profile/complete" class="needs-validation" novalidate>
                            <input type="hidden" name="_token" value="<?= \App\Helpers\Session::csrfToken() ?>">

                            <div class="mb-3">
                                <label class="form-label fw-semibold text-secondary">Alamat Email</label>
                                <input type="email" class="form-control rounded-3 bg-light" value="<?= htmlspecialchars($user['email'] ?? '') ?>" readonly>
                                <?php if (!empty($user['google_id'])): ?>
                                    <div class="form-text text-muted">Email ini berasal dari akun Google Anda dan tidak dapat diubah.</div>
                                <?php else: ?>
                                    <div class="form-text text-muted">Untuk mengubah alamat email, silakan kunjungi menu <a href="/profile/edit" class="text-primary text-decoration-none">Edit Profil</a>.</div>
                                <?php endif; ?>
                            </div>

                            <div class="mb-3">
                                <label class="form-label fw-semibold text-secondary">Nama Lengkap <span class="text-danger">*</span></label>
                                <input type="text" name="name" class="form-control rounded-3 <?= isset($errors['name']) ? 'is-invalid' : '' ?>" value="<?= htmlspecialchars($_POST['name'] ?? $user['name'] ?? '') ?>" required>
                                <?php if (isset($errors['name'])): ?>
                                    <div class="invalid-feedback"><?= htmlspecialchars($errors['name']) ?></div>
                                <?php endif; ?>
                            </div>

                            <div class="mb-3">
                                <label class="form-label fw-semibold text-secondary">Nomor Telepon / WhatsApp <span class="text-danger">*</span></label>
                                <input type="tel" name="phone" class="form-control rounded-3 <?= isset($errors['phone']) ? 'is-invalid' : '' ?>" placeholder="Contoh: 081234567890" value="<?= htmlspecialchars($_POST['phone'] ?? $user['phone'] ?? '') ?>" required pattern="[0-9+ -]{9,16}">
                                <?php if (isset($errors['phone'])): ?>
                                    <div class="invalid-feedback"><?= htmlspecialchars($errors['phone']) ?></div>
                                <?php else: ?>
                                    <div class="form-text">Digunakan oleh kurir untuk konfirmasi penjemputan.</div>
                                <?php endif; ?>
                            </div>

                            <hr class="my-4">

                            <h5 class="fw-bold text-dark mb-3"><i class="bi bi-geo-alt-fill text-danger me-2"></i>Alamat Penjemputan Utama</h5>

                            <div class="mb-3">
                                <label class="form-label fw-semibold text-secondary">Alamat Lengkap <span class="text-danger">*</span></label>
                                <textarea name="full_address" class="form-control rounded-3 <?= isset($errors['full_address']) ? 'is-invalid' : '' ?>" rows="3" placeholder="Nama Jalan, Nomor Rumah, Patokan..." required><?= htmlspecialchars($_POST['full_address'] ?? $address['full_address'] ?? '') ?></textarea>
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
                                    <input type="text" name="city" class="form-control rounded-3" placeholder="Nama Kota/Kabupaten" value="<?= htmlspecialchars($_POST['city'] ?? $address['city'] ?? '') ?>">
                                </div>
                            </div>

                            <div class="row g-3 mb-4">
                                <div class="col-6">
                                    <label class="form-label fw-semibold text-secondary">Latitude <span class="text-danger">*</span></label>
                                    <input type="text" id="latitude" name="latitude" class="form-control rounded-3 <?= isset($errors['latitude']) ? 'is-invalid' : '' ?>" placeholder="-6.200000" value="<?= htmlspecialchars($_POST['latitude'] ?? $address['latitude'] ?? '-6.200000') ?>" required>
                                    <?php if (isset($errors['latitude'])): ?>
                                        <div class="invalid-feedback"><?= htmlspecialchars($errors['latitude']) ?></div>
                                    <?php endif; ?>
                                </div>
                                <div class="col-6">
                                    <label class="form-label fw-semibold text-secondary">Longitude <span class="text-danger">*</span></label>
                                    <input type="text" id="longitude" name="longitude" class="form-control rounded-3 <?= isset($errors['longitude']) ? 'is-invalid' : '' ?>" placeholder="106.816666" value="<?= htmlspecialchars($_POST['longitude'] ?? $address['longitude'] ?? '106.816666') ?>" required>
                                    <?php if (isset($errors['longitude'])): ?>
                                        <div class="invalid-feedback"><?= htmlspecialchars($errors['longitude']) ?></div>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <button type="submit" class="btn btn-primary w-100 rounded-pill py-3 fw-bold shadow-lg">
                                <i class="bi bi-check-circle me-2"></i>Simpan Profil & Lanjutkan
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
