<section class="py-5 bg-light min-vh-100">
    <div class="container py-3">
        <div class="row justify-content-center">
            <div class="col-lg-8">

                <!-- Breadcrumb & Back -->
                <div class="d-flex align-items-center justify-content-between mb-4">
                    <a href="/profile" class="btn btn-outline-secondary rounded-pill btn-sm px-3">
                        <i class="bi bi-arrow-left me-1"></i> Kembali ke Profil
                    </a>
                    <span class="badge bg-primary-subtle text-primary rounded-pill px-3 py-2 fw-semibold">
                        Role: <?= strtoupper(htmlspecialchars($user['role'] ?? 'CUSTOMER')) ?>
                    </span>
                </div>

                <!-- 1. Form Edit Profil Dasar (Nama, HP, Email) -->
                <div class="card border-0 shadow-sm rounded-4 p-4 mb-4">
                    <div class="d-flex align-items-center gap-3 mb-4">
                        <div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center flex-shrink-0" style="width: 48px; height: 48px;">
                            <i class="bi bi-person-gear fs-4"></i>
                        </div>
                        <div>
                            <h5 class="fw-bold mb-1">Informasi Akun &amp; Profil</h5>
                            <p class="text-muted small mb-0">Perbarui nama lengkap, nomor handphone, dan alamat email Anda.</p>
                        </div>
                    </div>

                    <form method="POST" action="/profile/edit" id="formEditProfile" novalidate>
                        <input type="hidden" name="_token" value="<?= \App\Helpers\Session::csrfToken() ?>">

                        <!-- Nama Lengkap -->
                        <div class="mb-3">
                            <label class="form-label fw-semibold text-secondary" for="name">Nama Lengkap <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text bg-white"><i class="bi bi-person"></i></span>
                                <input type="text" id="name" name="name" class="form-control rounded-end-3"
                                       value="<?= htmlspecialchars($_POST['name'] ?? $user['name'] ?? '') ?>" required minlength="3">
                            </div>
                            <div class="form-text">Minimal 3 karakter.</div>
                        </div>

                        <!-- Nomor HP -->
                        <div class="mb-3">
                            <label class="form-label fw-semibold text-secondary" for="phone">Nomor Telepon / WhatsApp <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text bg-white"><i class="bi bi-telephone"></i></span>
                                <input type="tel" id="phone" name="phone" class="form-control rounded-end-3"
                                       placeholder="Contoh: 081234567890 atau +6281234567890"
                                       value="<?= htmlspecialchars($_POST['phone'] ?? $user['phone'] ?? '') ?>" required>
                            </div>
                            <div class="form-text">Nomor HP akan dinormalisasi ke format standar (628xxxxxxxx).</div>
                        </div>

                        <!-- Email -->
                        <div class="mb-3">
                            <label class="form-label fw-semibold text-secondary" for="email">Alamat Email</label>
                            <div class="input-group">
                                <span class="input-group-text bg-white"><i class="bi bi-envelope"></i></span>
                                <?php if ($isGoogleUser): ?>
                                    <input type="email" id="email" class="form-control rounded-end-3 bg-light"
                                           value="<?= htmlspecialchars($user['email'] ?? '') ?>" readonly>
                                <?php else: ?>
                                    <input type="email" id="email" name="email" class="form-control rounded-end-3"
                                           value="<?= htmlspecialchars($_POST['email'] ?? $user['email'] ?? '') ?>" required>
                                <?php endif; ?>
                            </div>

                            <?php if ($isGoogleUser): ?>
                                <div class="form-text text-muted mt-2 d-flex align-items-center gap-1">
                                    <i class="bi bi-info-circle text-primary"></i>
                                    <span>Email ini berasal dari akun Google Anda dan tidak dapat diubah.</span>
                                </div>
                            <?php else: ?>
                                <div class="form-text mt-1 text-muted">
                                    Mengubah email memerlukan konfirmasi melalui tautan yang dikirimkan ke email baru Anda (berlaku 60 menit). Email lama tetap aktif sampai tautan dikonfirmasi.
                                </div>
                                <?php if (!empty($user['pending_email'])): ?>
                                    <div class="alert alert-warning py-2 px-3 small mt-2 rounded-3">
                                        <i class="bi bi-clock-history me-1"></i> Sedang menunggu konfirmasi untuk email baru: <strong><?= htmlspecialchars($user['pending_email']) ?></strong>.
                                    </div>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>

                        <!-- Password Sekarang (Hanya jika user manual ingin mengganti email) -->
                        <?php if (!$isGoogleUser): ?>
                        <div class="mb-4 p-3 rounded-3 bg-light border" id="boxPasswordCurrent">
                            <label class="form-label fw-semibold text-secondary small" for="current_password_email">
                                <i class="bi bi-shield-lock me-1"></i> Password Saat Ini <span class="text-muted fw-normal">(Wajib diisi hanya jika ingin mengganti email)</span>
                            </label>
                            <input type="password" id="current_password_email" name="current_password" class="form-control rounded-3"
                                   placeholder="Masukkan password saat ini untuk konfirmasi ganti email" autocomplete="current-password">
                        </div>
                        <?php endif; ?>

                        <div class="d-flex justify-content-end">
                            <button type="submit" class="btn btn-primary rounded-pill px-4 fw-bold">
                                <i class="bi bi-check2-circle me-1"></i> Simpan Perubahan Profil
                            </button>
                        </div>
                    </form>
                </div>

                <!-- 2. Form Ganti Password / Buat Password -->
                <div class="card border-0 shadow-sm rounded-4 p-4">
                    <div class="d-flex align-items-center gap-3 mb-4">
                        <div class="rounded-circle bg-warning text-dark d-flex align-items-center justify-content-center flex-shrink-0" style="width: 48px; height: 48px;">
                            <i class="bi bi-key-fill fs-4"></i>
                        </div>
                        <div>
                            <h5 class="fw-bold mb-1"><?= $hasPassword ? 'Ganti Password Akun' : 'Buat Password Akun' ?></h5>
                            <p class="text-muted small mb-0">
                                <?= $hasPassword
                                    ? 'Amankan akun Anda dengan mengganti kata sandi secara berkala.'
                                    : 'Akun Anda masuk menggunakan Google dan belum memiliki password login manual. Buat password jika Anda ingin bisa masuk menggunakan email/HP langsung.' ?>
                            </p>
                        </div>
                    </div>

                    <form method="POST" action="/profile/change-password" id="formPassword" novalidate>
                        <input type="hidden" name="_token" value="<?= \App\Helpers\Session::csrfToken() ?>">

                        <?php if ($hasPassword): ?>
                            <div class="mb-3">
                                <label class="form-label fw-semibold text-secondary" for="current_password">Password Lama (Saat Ini) <span class="text-danger">*</span></label>
                                <input type="password" id="current_password" name="current_password" class="form-control rounded-3"
                                       placeholder="••••••••" required autocomplete="current-password">
                            </div>
                        <?php endif; ?>

                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <label class="form-label fw-semibold text-secondary" for="new_password">Password Baru <span class="text-danger">*</span></label>
                                <input type="password" id="new_password" name="new_password" class="form-control rounded-3"
                                       placeholder="Min. 6 karakter" required minlength="6" autocomplete="new-password">
                                <div class="form-text">Password minimal 6 karakter.</div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold text-secondary" for="new_password_confirmation">Konfirmasi Password Baru <span class="text-danger">*</span></label>
                                <input type="password" id="new_password_confirmation" name="new_password_confirmation" class="form-control rounded-3"
                                       placeholder="Ulangi password baru" required minlength="6" autocomplete="new-password">
                                <div class="invalid-feedback">Konfirmasi password tidak cocok.</div>
                            </div>
                        </div>

                        <div class="d-flex justify-content-end">
                            <button type="submit" class="btn btn-warning rounded-pill px-4 fw-bold text-dark">
                                <i class="bi bi-shield-check me-1"></i> <?= $hasPassword ? 'Perbarui Password' : 'Buat Password' ?>
                            </button>
                        </div>
                    </form>
                </div>

            </div>
        </div>
    </div>
</section>

<script>
// Validasi kecocokan konfirmasi password baru
const np = document.getElementById('new_password');
const npc = document.getElementById('new_password_confirmation');
if (np && npc) {
    npc.addEventListener('input', function() {
        if (this.value !== np.value) {
            this.setCustomValidity('Konfirmasi password tidak cocok.');
            this.classList.add('is-invalid');
        } else {
            this.setCustomValidity('');
            this.classList.remove('is-invalid');
        }
    });
}
</script>
