<section class="min-vh-100 py-5 d-flex align-items-center" style="background:linear-gradient(135deg,#0f172a 0%,#1e293b 100%);">
    <div class="container py-4">
        <div class="row justify-content-center">
            <div class="col-md-7 col-lg-5">

                <!-- Brand -->
                <div class="text-center mb-4">
                    <a href="/" class="d-inline-flex align-items-center gap-2 text-decoration-none mb-3">
                        <div class="rounded-circle d-flex align-items-center justify-content-center"
                             style="width:48px;height:48px;background:linear-gradient(135deg,#2563eb,#06b6d4);">
                            <i class="bi bi-water fs-4 text-white"></i>
                        </div>
                        <span class="fw-bold text-white fs-5">Marketplace<span style="color:#06b6d4">Laundry</span></span>
                    </a>
                    <h1 class="fw-extrabold text-white mb-1" style="font-size:1.6rem;">Masuk ke Akun Anda</h1>
                    <p class="text-secondary small">Selamat datang kembali! Masuk untuk melanjutkan.</p>
                </div>

                <!-- Flash Messages -->
                <?php if (isset($flashSuccess) && $flashSuccess): ?>
                    <div class="alert alert-success rounded-3 py-2 mb-3 small text-center"><i class="bi bi-check-circle-fill me-2"></i><?= htmlspecialchars($flashSuccess) ?></div>
                <?php endif; ?>
                <?php if (isset($flashError) && $flashError): ?>
                    <div class="alert alert-danger rounded-3 py-2 mb-3 small text-center"><i class="bi bi-exclamation-triangle-fill me-2"></i><?= htmlspecialchars($flashError) ?></div>
                <?php endif; ?>
                <?php if (isset($flashWarning) && $flashWarning): ?>
                    <div class="alert rounded-3 py-2 mb-3 small text-center" style="background:rgba(234,179,8,.15);border:1px solid rgba(234,179,8,.3);color:#fbbf24;"><i class="bi bi-exclamation-circle me-2"></i><?= htmlspecialchars($flashWarning) ?></div>
                <?php endif; ?>

                <!-- Card -->
                <div class="card border-0 rounded-4 shadow-lg"
                     style="background:rgba(30,41,59,0.9);backdrop-filter:blur(16px);border:1px solid rgba(255,255,255,0.07) !important;">
                    <div class="card-body p-4 p-lg-5">

                        <!-- Credential Login Form -->
                        <form method="POST" action="/login" id="formLogin" novalidate>
                            <input type="hidden" name="_token" value="<?= \App\Helpers\Session::csrfToken() ?>">

                            <!-- Pilihan Masuk Sebagai -->
                            <?php $currentRole = $loginAs ?? 'customer'; ?>
                            <div class="mb-4">
                                <label class="form-label small fw-semibold text-secondary mb-2 d-block">
                                    Masuk Sebagai <span class="text-danger">*</span>
                                </label>
                                <div class="row g-2">
                                    <div class="col-4">
                                        <input type="radio" class="btn-check" name="login_as" id="role_customer" value="customer" <?= $currentRole === 'customer' ? 'checked' : '' ?> required>
                                        <label class="btn role-card w-100 p-2 d-flex flex-column align-items-center justify-content-center text-center" for="role_customer">
                                            <i class="bi bi-person-fill fs-5 mb-1 text-primary"></i>
                                            <span class="small fw-semibold">Pelanggan</span>
                                        </label>
                                    </div>
                                    <div class="col-4">
                                        <input type="radio" class="btn-check" name="login_as" id="role_owner" value="owner" <?= $currentRole === 'owner' ? 'checked' : '' ?> required>
                                        <label class="btn role-card w-100 p-2 d-flex flex-column align-items-center justify-content-center text-center" for="role_owner">
                                            <i class="bi bi-shop fs-5 mb-1" style="color:#a855f7;"></i>
                                            <span class="small fw-semibold" style="font-size:0.75rem;">Mitra Laundry</span>
                                        </label>
                                    </div>
                                    <div class="col-4">
                                        <input type="radio" class="btn-check" name="login_as" id="role_driver" value="driver" <?= $currentRole === 'driver' ? 'checked' : '' ?> required>
                                        <label class="btn role-card w-100 p-2 d-flex flex-column align-items-center justify-content-center text-center" for="role_driver">
                                            <i class="bi bi-bicycle fs-5 mb-1" style="color:#06b6d4;"></i>
                                            <span class="small fw-semibold">Kurir</span>
                                        </label>
                                    </div>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label small fw-semibold text-secondary" for="identity">Email atau No. WhatsApp <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text" style="background:#0f172a;border-color:rgba(255,255,255,0.12);color:#64748b;">
                                        <i class="bi bi-envelope"></i>
                                    </span>
                                    <input type="text" id="identity" name="identity" class="form-control form-control-dark" style="border-left:0;"
                                           placeholder="email@contoh.com atau 0812..." value="<?= htmlspecialchars($oldIdentity ?? '') ?>" required autocomplete="username">
                                    <div class="invalid-feedback">Masukkan email atau nomor WhatsApp Anda.</div>
                                </div>
                            </div>

                            <div class="mb-4">
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <label class="form-label small fw-semibold text-secondary mb-0" for="password">Password <span class="text-danger">*</span></label>
                                    <a href="/lupa-password" class="text-primary small text-decoration-none">Lupa password?</a>
                                </div>
                                <div class="input-group">
                                    <span class="input-group-text" style="background:#0f172a;border-color:rgba(255,255,255,0.12);color:#64748b;">
                                        <i class="bi bi-lock"></i>
                                    </span>
                                    <input type="password" id="password" name="password" class="form-control form-control-dark" style="border-left:0;"
                                           placeholder="••••••••" required autocomplete="current-password">
                                    <button type="button" class="btn btn-outline-secondary" id="btnTogglePw">
                                        <i class="bi bi-eye"></i>
                                    </button>
                                    <div class="invalid-feedback">Masukkan password Anda.</div>
                                </div>
                            </div>

                            <button type="submit" class="btn w-100 rounded-pill py-2 fw-bold text-white mb-3"
                                    style="background:linear-gradient(135deg,#2563eb,#3b82f6);border:0;">
                                <i class="bi bi-box-arrow-in-right me-2"></i>Masuk
                            </button>
                        </form>

                        <!-- Google Formalitas -->
                        <div id="googleAuthContainer">
                            <!-- Divider -->
                            <div class="position-relative text-center my-3">
                                <hr style="border-color:rgba(255,255,255,0.1);">
                                <span class="position-absolute top-50 start-50 translate-middle px-3 small text-secondary" style="background:#1e293b;">atau</span>
                            </div>

                            <button type="button" id="btnGoogleFormalitas"
                                    class="btn btn-google-formalitas w-100 rounded-pill py-2 fw-semibold d-flex align-items-center justify-content-center gap-2 mb-2 shadow-sm"
                                    style="background:#ffffff;border:1px solid #dadce0;color:#3c4043;font-size:0.95rem;transition:all 0.2s ease;">
                                <svg width="20" height="20" viewBox="0 0 48 48" class="flex-shrink-0">
                                    <path fill="#EA4335" d="M24 9.5c3.54 0 6.71 1.22 9.21 3.6l6.85-6.85C35.9 2.38 30.47 0 24 0 14.62 0 6.51 5.38 2.56 13.22l7.98 6.19C12.43 13.72 17.74 9.5 24 9.5z"/>
                                    <path fill="#4285F4" d="M46.98 24.55c0-1.57-.15-3.09-.38-4.55H24v9.02h12.94c-.58 2.96-2.26 5.48-4.78 7.18l7.73 6c4.51-4.18 7.09-10.36 7.09-17.65z"/>
                                    <path fill="#FBBC05" d="M10.53 28.59c-.48-1.45-.76-2.99-.76-4.59s.27-3.14.76-4.59l-7.98-6.19C.92 16.46 0 20.12 0 24c0 3.88.92 7.54 2.56 10.78l7.97-6.19z"/>
                                    <path fill="#34A853" d="M24 48c6.48 0 11.93-2.13 15.89-5.81l-7.73-6c-2.15 1.45-4.92 2.3-8.16 2.3-6.26 0-11.57-4.22-13.47-9.91l-7.98 6.19C6.51 42.62 14.62 48 24 48z"/>
                                    <path fill="none" d="M0 0h48v48H0z"/>
                                </svg>
                                <span>Masuk dengan Google</span>
                            </button>
                            <div id="googleNotice" class="alert alert-info py-2 px-3 small rounded-3 text-center mb-3 d-none border-0 shadow-sm"
                                 style="background:rgba(14,165,233,0.15);color:#38bdf8;font-size:0.85rem;line-height:1.4;">
                                <i class="bi bi-info-circle me-1"></i>Login dengan Google belum tersedia. Silakan masuk dengan email atau nomor WhatsApp dan password.
                            </div>
                        </div>

                        <div class="text-center mt-2">
                            <span class="text-secondary small">Belum punya akun?</span>
                            <a href="/daftar" class="text-primary fw-semibold small ms-1 text-decoration-none">Daftar Sekarang</a>
                        </div>

                    </div>
                </div>

                <!-- Admin Login Link -->
                <div class="text-center mt-3">
                    <a href="/admin/login" class="text-secondary small text-decoration-none admin-link">
                        <i class="bi bi-shield me-1"></i>Masuk sebagai Administrator
                    </a>
                </div>

            </div>
        </div>
    </div>
</section>

<style>
.role-card {
    background: #0f172a;
    border: 1px solid rgba(255,255,255,0.12);
    color: #94a3b8;
    border-radius: 12px;
    transition: all 0.2s ease;
    cursor: pointer;
    min-height: 72px;
}
.role-card:hover {
    border-color: rgba(255,255,255,0.25);
    color: #e2e8f0;
}
.btn-google-formalitas:hover {
    background: #f8fafc !important;
    box-shadow: 0 2px 6px rgba(0,0,0,0.12) !important;
}
.admin-link {
    color: #64748b !important;
    font-size: 0.82rem;
    opacity: 0.75;
    transition: all 0.2s ease;
}
.admin-link:hover {
    color: #cbd5e1 !important;
    opacity: 1;
}
.btn-check:checked + .role-card {
    background: linear-gradient(135deg, rgba(37,99,235,0.18), rgba(6,182,212,0.18));
    border-color: #38bdf8;
    color: #ffffff;
    box-shadow: 0 0 12px rgba(56,189,248,0.25);
}
.btn-check:focus + .role-card {
    box-shadow: 0 0 0 0.2rem rgba(37,99,235,.25);
}
.form-control-dark {
    background: #0f172a !important;
    border-color: rgba(255,255,255,0.12) !important;
    color: #e2e8f0 !important;
}
.form-control-dark::placeholder { color: rgba(148,163,184,0.6); }
.form-control-dark:focus { border-color: #2563eb !important; box-shadow: 0 0 0 0.2rem rgba(37,99,235,.2); }
.input-group-text { border-color: rgba(255,255,255,0.12); }
</style>

<script>
// Toggle password visibility
document.getElementById('btnTogglePw').addEventListener('click', function () {
    const inp = document.getElementById('password');
    inp.type = inp.type === 'password' ? 'text' : 'password';
    this.querySelector('i').className = inp.type === 'password' ? 'bi bi-eye' : 'bi bi-eye-slash';
});

// Client-side validation
document.getElementById('formLogin').addEventListener('submit', function (e) {
    if (!this.checkValidity()) { e.preventDefault(); e.stopPropagation(); }
    this.classList.add('was-validated');
});

// Dynamic toggle: Tombol Google tampil di semua peran (Pelanggan, Mitra Laundry, Kurir, Admin)
const btnGoogleFormalitas = document.getElementById('btnGoogleFormalitas');
const googleNotice = document.getElementById('googleNotice');
let googleNoticeTimer = null;

if (btnGoogleFormalitas && googleNotice) {
    btnGoogleFormalitas.addEventListener('click', function (e) {
        e.preventDefault();
        googleNotice.classList.remove('d-none');
        if (googleNoticeTimer) clearTimeout(googleNoticeTimer);
        googleNoticeTimer = setTimeout(function () {
            googleNotice.classList.add('d-none');
        }, 5000);
    });
}
</script>
