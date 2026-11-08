<section class="min-vh-100 py-5 d-flex align-items-center" style="background:linear-gradient(135deg,#0f172a 0%,#1e293b 100%);">
    <div class="container py-4">
        <div class="row justify-content-center">
            <div class="col-md-8 col-lg-6">

                <!-- Header -->
                <div class="text-center mb-4">
                    <a href="/daftar" class="text-secondary text-decoration-none small d-inline-flex align-items-center gap-1 mb-3">
                        <i class="bi bi-arrow-left"></i> Ganti peran
                    </a>
                    <div class="mx-auto mb-3 rounded-circle d-flex align-items-center justify-content-center"
                         style="width:56px;height:56px;background:linear-gradient(135deg,#0891b2,#06b6d4);">
                        <i class="bi bi-bicycle fs-3 text-white"></i>
                    </div>
                    <h1 class="fw-extrabold text-white mb-1" style="font-size:1.6rem;">Daftar Kurir Laundry</h1>
                    <p class="text-secondary small">Isi data diri dan kendaraan Anda untuk mulai mengantarkan pesanan laundry.</p>
                </div>

                <!-- Flash Messages -->
                <?php if (isset($flashError) && $flashError): ?>
                    <div class="alert alert-danger rounded-3 py-2 mb-3 small text-center"><i class="bi bi-exclamation-triangle-fill me-2"></i><?= htmlspecialchars($flashError) ?></div>
                <?php endif; ?>

                <!-- Card -->
                <div class="card border-0 rounded-4 shadow-lg"
                     style="background:rgba(30,41,59,0.9);backdrop-filter:blur(16px);border:1px solid rgba(255,255,255,0.07) !important;">
                    <div class="card-body p-4 p-lg-5">
                        <form method="POST" action="/daftar/kurir" enctype="multipart/form-data" id="formKurir" novalidate>
                            <input type="hidden" name="_token" value="<?= \App\Helpers\Session::csrfToken() ?>">

                            <!-- Data Diri -->
                            <h6 class="text-white fw-bold mb-3"><i class="bi bi-person-vcard me-2" style="color:#06b6d4;"></i>Data Diri</h6>

                            <div class="mb-3">
                                <label class="form-label small fw-semibold text-secondary" for="name">Nama Lengkap <span class="text-danger">*</span></label>
                                <input type="text" id="name" name="name" class="form-control form-control-dark rounded-3"
                                       placeholder="Sesuai KTP" required minlength="3" autocomplete="name">
                                <div class="invalid-feedback">Nama minimal 3 karakter.</div>
                            </div>

                            <div class="row g-3 mb-3">
                                <div class="col-md-6">
                                    <label class="form-label small fw-semibold text-secondary" for="email">Email <span class="text-danger">*</span></label>
                                    <input type="text" id="email" name="email" class="form-control form-control-dark rounded-3"
                                           placeholder="nama@email.com atau email bebas" required autocomplete="email">
                                    <div class="invalid-feedback">Masukkan email valid.</div>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label small fw-semibold text-secondary" for="phone">No. WhatsApp <span class="text-danger">*</span></label>
                                    <input type="tel" id="phone" name="phone" class="form-control form-control-dark rounded-3"
                                           placeholder="081234567890" required autocomplete="tel">
                                    <div class="invalid-feedback">Nomor minimal 10 digit, contoh 081234567890</div>
                                </div>
                            </div>

                            <div class="row g-3 mb-4">
                                <div class="col-md-6">
                                    <label class="form-label small fw-semibold text-secondary" for="password">Password <span class="text-danger">*</span></label>
                                    <input type="password" id="password" name="password" class="form-control form-control-dark rounded-3"
                                           placeholder="Password bebas" required autocomplete="new-password">
                                    <div class="invalid-feedback">Password wajib diisi.</div>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label small fw-semibold text-secondary" for="password_confirmation">Konfirmasi <span class="text-danger">*</span></label>
                                    <input type="password" id="password_confirmation" name="password_confirmation" class="form-control form-control-dark rounded-3"
                                           placeholder="Ulangi password" required autocomplete="new-password">
                                    <div class="invalid-feedback" id="pwConFb">Password tidak cocok.</div>
                                </div>
                            </div>

                            <!-- Data Kendaraan -->
                            <hr style="border-color:rgba(255,255,255,0.1);" class="mb-4">
                            <h6 class="text-white fw-bold mb-3"><i class="bi bi-truck me-2" style="color:#06b6d4;"></i>Data Kendaraan</h6>

                            <div class="row g-3 mb-3">
                                <div class="col-md-6">
                                    <label class="form-label small fw-semibold text-secondary" for="jenis_kendaraan">Jenis Kendaraan <span class="text-danger">*</span></label>
                                    <select id="jenis_kendaraan" name="jenis_kendaraan" class="form-select form-control-dark rounded-3" required>
                                        <option value="Motor">Motor / Sepeda Motor</option>
                                        <option value="Mobil">Mobil</option>
                                        <option value="Sepeda">Sepeda (Non-Motor)</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label small fw-semibold text-secondary" for="plat_nomor">Nomor Plat Kendaraan <span class="text-danger">*</span></label>
                                    <input type="text" id="plat_nomor" name="plat_nomor" class="form-control form-control-dark rounded-3"
                                           placeholder="Contoh: B 1234 ABC" required>
                                    <div class="invalid-feedback">Nomor plat kendaraan wajib diisi.</div>
                                </div>
                            </div>

                            <!-- Dokumen Upload -->
                            <div class="mb-4">
                                <label class="form-label small fw-semibold text-secondary" for="dokumen">Upload KTP / SIM <span class="text-muted small fw-normal">(opsional, maks. 2MB)</span></label>
                                <input type="file" id="dokumen" name="dokumen" class="form-control form-control-dark rounded-3"
                                       accept="image/jpeg,image/png,image/webp,application/pdf">
                                <div class="form-text text-secondary small mt-1">Format: JPG, PNG, atau PDF. Dokumen membantu mempercepat verifikasi.</div>
                            </div>

                            <!-- Info Verifikasi -->
                            <div class="alert small py-2 mb-4 rounded-3"
                                 style="background:rgba(6,182,212,0.12);border:1px solid rgba(6,182,212,0.3);color:#67e8f9;">
                                <i class="bi bi-shield-check me-2"></i>Data Anda aman &amp; terenkripsi. Tim Admin akan memverifikasi dalam 1×24 jam. Akun aktif setelah disetujui.
                            </div>

                            <!-- Terms -->
                            <div class="form-check mb-4">
                                <input class="form-check-input" type="checkbox" id="terms" name="terms" required>
                                <label class="form-check-label small text-secondary" for="terms">
                                    Saya menyetujui <a href="#" class="text-primary text-decoration-none">Syarat & Ketentuan</a> dan <a href="#" class="text-primary text-decoration-none">Kebijakan Privasi</a> MarketplaceLaundry.
                                </label>
                                <div class="invalid-feedback">Anda harus menyetujui Syarat & Ketentuan.</div>
                            </div>

                            <button type="submit" class="btn w-100 rounded-pill py-2 fw-bold text-white shadow-sm"
                                    style="background:linear-gradient(135deg,#0891b2,#06b6d4);border:0;">
                                <i class="bi bi-send-check-fill me-2"></i>Kirim Pendaftaran Kurir
                            </button>
                        </form>

                        <p class="text-center mt-4 small text-secondary mb-0">Sudah punya akun? <a href="/login" class="text-primary fw-semibold text-decoration-none">Masuk di sini</a></p>
                    </div>
                </div>

            </div>
        </div>
    </div>
</section>

<style>
.form-control-dark {
    background: #0f172a !important;
    border-color: rgba(255,255,255,0.12) !important;
    color: #e2e8f0 !important;
}
.form-control-dark::placeholder { color: rgba(148,163,184,0.6); }
.form-control-dark:focus { border-color: #06b6d4 !important; box-shadow: 0 0 0 0.2rem rgba(6,182,212,.2); }
select.form-control-dark option { background: #0f172a; color: #e2e8f0; }
</style>

<script>
// Confirm password match
const pw = document.getElementById('password');
const pc = document.getElementById('password_confirmation');
pc.addEventListener('input', function () {
    if (this.value !== pw.value) {
        this.classList.add('is-invalid');
        document.getElementById('pwConFb').textContent = 'Password tidak cocok.';
    } else {
        this.classList.remove('is-invalid');
        this.classList.add('is-valid');
    }
});

// Form submit
document.getElementById('formKurir').addEventListener('submit', function (e) {
    if (!this.checkValidity()) { e.preventDefault(); e.stopPropagation(); }
    this.classList.add('was-validated');
    if (pw.value !== pc.value) {
        e.preventDefault();
        pc.classList.add('is-invalid');
    }
});
</script>
