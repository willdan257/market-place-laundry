<section class="min-vh-100 py-5 d-flex align-items-center" style="background:linear-gradient(135deg,#0b0f19 0%,#1e293b 100%);">
    <div class="container py-4">
        <div class="row justify-content-center">
            <div class="col-md-6 col-lg-5">

                <!-- Header / Brand -->
                <div class="text-center mb-4">
                    <div class="mb-3">
                        <span class="badge bg-danger rounded-pill px-3 py-1 fw-bold shadow-sm" style="font-size:0.85rem;letter-spacing:0.5px;">
                            <i class="bi bi-shield-lock-fill me-1"></i>Area Administrator
                        </span>
                    </div>
                    <div class="d-inline-flex align-items-center gap-2 mb-2">
                        <div class="rounded-circle d-flex align-items-center justify-content-center shadow"
                             style="width:48px;height:48px;background:linear-gradient(135deg,#dc2626,#ef4444);">
                            <i class="bi bi-shield-fill-check fs-4 text-white"></i>
                        </div>
                        <span class="fw-bold text-white fs-5">Marketplace<span style="color:#ef4444">Laundry</span></span>
                    </div>
                    <h1 class="fw-extrabold text-white mb-1" style="font-size:1.6rem;">Login Administrator</h1>
                    <p class="text-secondary small">Portal otorisasi khusus manajemen sistem</p>
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
                     style="background:rgba(30,41,59,0.92);backdrop-filter:blur(16px);border:1px solid rgba(239,68,68,0.2) !important;">
                    <div class="card-body p-4 p-lg-5">

                        <form method="POST" action="/admin/login" id="formAdminLogin" novalidate>
                            <input type="hidden" name="_token" value="<?= \App\Helpers\Session::csrfToken() ?>">

                            <div class="mb-3">
                                <label class="form-label small fw-semibold text-secondary" for="identity">
                                    Email atau No. WhatsApp Administrator <span class="text-danger">*</span>
                                </label>
                                <div class="input-group">
                                    <span class="input-group-text" style="background:#0f172a;border-color:rgba(255,255,255,0.12);color:#94a3b8;">
                                        <i class="bi bi-person-badge"></i>
                                    </span>
                                    <input type="text" id="identity" name="identity" class="form-control form-control-dark" style="border-left:0;"
                                           placeholder="admin@marketplacelaundry.com" value="<?= htmlspecialchars($oldIdentity ?? '') ?>" required autocomplete="username">
                                    <div class="invalid-feedback">Masukkan email atau nomor WhatsApp admin.</div>
                                </div>
                            </div>

                            <div class="mb-4">
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <label class="form-label small fw-semibold text-secondary mb-0" for="password">Password <span class="text-danger">*</span></label>
                                </div>
                                <div class="input-group">
                                    <span class="input-group-text" style="background:#0f172a;border-color:rgba(255,255,255,0.12);color:#94a3b8;">
                                        <i class="bi bi-key-fill"></i>
                                    </span>
                                    <input type="password" id="password" name="password" class="form-control form-control-dark" style="border-left:0;"
                                           placeholder="••••••••" required autocomplete="current-password">
                                    <button type="button" class="btn btn-outline-secondary" id="btnTogglePw">
                                        <i class="bi bi-eye"></i>
                                    </button>
                                    <div class="invalid-feedback">Masukkan password admin.</div>
                                </div>
                            </div>

                            <button type="submit" class="btn w-100 rounded-pill py-2 fw-bold text-white mb-2 shadow"
                                    style="background:linear-gradient(135deg,#dc2626,#b91c1c);border:0;">
                                <i class="bi bi-shield-lock me-2"></i>Masuk sebagai Administrator
                            </button>
                        </form>

                    </div>
                </div>

                <!-- Back to user login -->
                <div class="text-center mt-4">
                    <a href="/login" class="text-secondary small text-decoration-none d-inline-flex align-items-center gap-1 back-link">
                        <i class="bi bi-arrow-left"></i> Kembali ke login pengguna
                    </a>
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
.form-control-dark:focus { border-color: #ef4444 !important; box-shadow: 0 0 0 0.2rem rgba(239,68,68,.25); }
.input-group-text { border-color: rgba(255,255,255,0.12); }
.back-link {
    transition: color 0.2s ease;
}
.back-link:hover {
    color: #cbd5e1 !important;
}
</style>

<script>
// Toggle password visibility
document.getElementById('btnTogglePw').addEventListener('click', function () {
    const inp = document.getElementById('password');
    inp.type = inp.type === 'password' ? 'text' : 'password';
    this.querySelector('i').className = inp.type === 'password' ? 'bi bi-eye' : 'bi bi-eye-slash';
});

// Client-side validation
document.getElementById('formAdminLogin').addEventListener('submit', function (e) {
    if (!this.checkValidity()) { e.preventDefault(); e.stopPropagation(); }
    this.classList.add('was-validated');
});
</script>

