<section class="min-vh-100 py-5 d-flex align-items-center" style="background:linear-gradient(135deg,#0f172a 0%,#1e293b 100%);">
    <div class="container py-4">
        <div class="row justify-content-center">
            <div class="col-md-7 col-lg-5">

                <!-- Header -->
                <div class="text-center mb-4">
                    <div class="rounded-circle d-flex align-items-center justify-content-center mx-auto mb-3"
                         style="width:52px;height:52px;background:linear-gradient(135deg,#2563eb,#06b6d4);">
                        <i class="bi bi-shield-lock-fill fs-4 text-white"></i>
                    </div>
                    <h1 class="fw-extrabold text-white mb-1" style="font-size:1.6rem;">Atur Password Baru</h1>
                    <p class="text-secondary small">Kode OTP berhasil diverifikasi. Masukkan kata sandi baru untuk akun Anda.</p>
                </div>

                <!-- Flash Messages -->
                <?php if (isset($flashError) && $flashError): ?>
                    <div class="alert alert-danger rounded-3 py-2 mb-3 small text-center"><i class="bi bi-exclamation-triangle-fill me-2"></i><?= htmlspecialchars($flashError) ?></div>
                <?php endif; ?>

                <!-- Card -->
                <div class="card border-0 rounded-4 shadow-lg"
                     style="background:rgba(30,41,59,0.9);backdrop-filter:blur(16px);border:1px solid rgba(255,255,255,0.07) !important;">
                    <div class="card-body p-4 p-lg-5">
                        <form method="POST" action="/lupa-password/password-baru" id="formNewPassword" novalidate>
                            <input type="hidden" name="_token" value="<?= \App\Helpers\Session::csrfToken() ?>">

                            <div class="mb-3">
                                <label class="form-label small fw-semibold text-secondary" for="password">Password Baru <span class="text-danger">*</span></label>
                                <input type="password" id="password" name="password" class="form-control" style="background:#0f172a;border-color:rgba(255,255,255,0.12);color:#e2e8f0;"
                                       placeholder="Minimal 8 karakter" required minlength="8" autocomplete="new-password">
                                <div class="form-text text-secondary small">Password minimal 8 karakter.</div>
                            </div>

                            <div class="mb-4">
                                <label class="form-label small fw-semibold text-secondary" for="password_confirmation">Konfirmasi Password Baru <span class="text-danger">*</span></label>
                                <input type="password" id="password_confirmation" name="password_confirmation" class="form-control" style="background:#0f172a;border-color:rgba(255,255,255,0.12);color:#e2e8f0;"
                                       placeholder="Ulangi password baru" required minlength="8" autocomplete="new-password">
                                <div class="invalid-feedback">Konfirmasi password tidak cocok.</div>
                            </div>

                            <button type="submit" class="btn w-100 rounded-pill py-2 fw-bold text-white mb-3"
                                    style="background:linear-gradient(135deg,#2563eb,#3b82f6);border:0;">
                                <i class="bi bi-check-circle me-2"></i>Simpan Password Baru
                            </button>
                        </form>
                    </div>
                </div>

            </div>
        </div>
    </div>
</section>

<script>
const pw = document.getElementById('password');
const pwc = document.getElementById('password_confirmation');
if (pw && pwc) {
    pwc.addEventListener('input', function() {
        if (this.value !== pw.value) {
            this.setCustomValidity('Konfirmasi password tidak cocok.');
            this.classList.add('is-invalid');
        } else {
            this.setCustomValidity('');
            this.classList.remove('is-invalid');
        }
    });
}
document.getElementById('formNewPassword').addEventListener('submit', function (e) {
    if (!this.checkValidity()) {
        e.preventDefault();
        e.stopPropagation();
    }
    this.classList.add('was-validated');
});
</script>

