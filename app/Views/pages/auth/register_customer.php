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
                         style="width:56px;height:56px;background:linear-gradient(135deg,#2563eb,#3b82f6);">
                        <i class="bi bi-person-heart fs-3 text-white"></i>
                    </div>
                    <h1 class="fw-extrabold text-white mb-1" style="font-size:1.6rem;">Daftar Pelanggan</h1>
                    <p class="text-secondary small">Buat akun gratis Anda dan mulai pesan laundry dalam hitungan menit.</p>
                </div>

                <!-- Flash Messages -->
                <?php if (isset($flashError) && $flashError): ?>
                    <div class="alert alert-danger rounded-3 py-2 px-3 mb-3 small"><i class="bi bi-exclamation-triangle-fill me-2"></i><?= htmlspecialchars($flashError) ?></div>
                <?php endif; ?>

                <!-- Card -->
                <div class="card border-0 rounded-4 shadow-lg overflow-hidden"
                     style="background:rgba(30,41,59,0.9);backdrop-filter:blur(16px);border:1px solid rgba(255,255,255,0.07) !important;">
                    <div class="card-body p-4 p-lg-5">

                        <!-- Google Formalitas -->
                        <button type="button" id="btnGoogleCustomer"
                                class="btn btn-google-formalitas w-100 rounded-pill py-2 fw-semibold d-flex align-items-center justify-content-center gap-2 mb-2 shadow-sm"
                                style="background:#ffffff;border:1px solid #dadce0;color:#3c4043;font-size:0.95rem;transition:all 0.2s ease;">
                            <svg width="20" height="20" viewBox="0 0 48 48" class="flex-shrink-0">
                                <path fill="#EA4335" d="M24 9.5c3.54 0 6.71 1.22 9.21 3.6l6.85-6.85C35.9 2.38 30.47 0 24 0 14.62 0 6.51 5.38 2.56 13.22l7.98 6.19C12.43 13.72 17.74 9.5 24 9.5z"/>
                                <path fill="#4285F4" d="M46.98 24.55c0-1.57-.15-3.09-.38-4.55H24v9.02h12.94c-.58 2.96-2.26 5.48-4.78 7.18l7.73 6c4.51-4.18 7.09-10.36 7.09-17.65z"/>
                                <path fill="#FBBC05" d="M10.53 28.59c-.48-1.45-.76-2.99-.76-4.59s.27-3.14.76-4.59l-7.98-6.19C.92 16.46 0 20.12 0 24c0 3.88.92 7.54 2.56 10.78l7.97-6.19z"/>
                                <path fill="#34A853" d="M24 48c6.48 0 11.93-2.13 15.89-5.81l-7.73-6c-2.15 1.45-4.92 2.3-8.16 2.3-6.26 0-11.57-4.22-13.47-9.91l-7.98 6.19C6.51 42.62 14.62 48 24 48z"/>
                                <path fill="none" d="M0 0h48v48H0z"/>
                            </svg>
                            <span class="fw-semibold" style="color:#3c4043;">Masuk dengan Google</span>
                        </button>
                        <div id="googleNoticeCustomer" class="alert alert-info py-2 px-3 small rounded-3 text-center mb-3 d-none border-0 shadow-sm"
                             style="background:rgba(14,165,233,0.15);color:#38bdf8;font-size:0.85rem;line-height:1.4;">
                            <i class="bi bi-info-circle me-1"></i>Login dengan Google belum tersedia. Silakan masuk dengan email atau nomor WhatsApp dan password.
                        </div>

                        <div class="position-relative text-center my-4">
                            <hr style="border-color:rgba(255,255,255,0.12);">
                            <span class="position-absolute top-50 start-50 translate-middle px-3 small text-secondary" style="background:#1e293b;">atau isi formulir</span>
                        </div>

                        <form method="POST" action="/daftar/pelanggan" id="formCustomer" novalidate>
                            <input type="hidden" name="_token" value="<?= \App\Helpers\Session::csrfToken() ?>">

                            <!-- Nama -->
                            <div class="mb-3">
                                <label class="form-label small fw-semibold text-secondary" for="name">Nama Lengkap <span class="text-danger">*</span></label>
                                <input type="text" id="name" name="name" class="form-control rounded-3" style="background:#0f172a;border-color:rgba(255,255,255,0.12);color:#e2e8f0;"
                                       placeholder="Contoh: Budi Santoso" required minlength="3" autocomplete="name">
                                <div class="invalid-feedback">Nama minimal 3 karakter.</div>
                            </div>

                            <!-- Email & Phone -->
                            <div class="row g-3 mb-3">
                                <div class="col-md-6">
                                    <label class="form-label small fw-semibold text-secondary" for="email">Alamat Email <span class="text-danger">*</span></label>
                                    <input type="text" id="email" name="email" class="form-control rounded-3" style="background:#0f172a;border-color:rgba(255,255,255,0.12);color:#e2e8f0;"
                                           placeholder="nama@email.com atau email bebas" required autocomplete="email">
                                    <div class="invalid-feedback">Masukkan email yang valid.</div>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label small fw-semibold text-secondary" for="phone">No. WhatsApp <span class="text-danger">*</span></label>
                                    <input type="tel" id="phone" name="phone" class="form-control rounded-3" style="background:#0f172a;border-color:rgba(255,255,255,0.12);color:#e2e8f0;"
                                           placeholder="081234567890" required autocomplete="tel">
                                    <div class="invalid-feedback">Nomor minimal 10 digit, contoh 081234567890</div>
                                </div>
                            </div>

                            <!-- Alamat (opsional) -->
                            <div class="mb-3">
                                <label class="form-label small fw-semibold text-secondary" for="alamat_utama">Alamat Rumah <span class="text-muted small fw-normal">(opsional — bisa dilengkapi nanti)</span></label>
                                <textarea id="alamat_utama" name="alamat_utama" rows="2" class="form-control rounded-3" style="background:#0f172a;border-color:rgba(255,255,255,0.12);color:#e2e8f0;"
                                          placeholder="Jl. Kenanga No. 10, RT 02/03, Kel. Melati..."></textarea>
                            </div>

                            <!-- Password -->
                            <div class="row g-3 mb-4">
                                <div class="col-md-6">
                                    <label class="form-label small fw-semibold text-secondary" for="password">Password <span class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <input type="password" id="password" name="password" class="form-control rounded-start-3" style="background:#0f172a;border-color:rgba(255,255,255,0.12);color:#e2e8f0;"
                                               placeholder="Password bebas" required autocomplete="new-password">
                                        <button type="button" class="btn btn-outline-secondary rounded-end-3 btn-toggle-pw" data-target="password">
                                            <i class="bi bi-eye"></i>
                                        </button>
                                    </div>
                                    <div class="invalid-feedback">Password wajib diisi.</div>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label small fw-semibold text-secondary" for="password_confirmation">Konfirmasi Password <span class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <input type="password" id="password_confirmation" name="password_confirmation" class="form-control rounded-start-3" style="background:#0f172a;border-color:rgba(255,255,255,0.12);color:#e2e8f0;"
                                               placeholder="Ulangi password" required autocomplete="new-password">
                                        <button type="button" class="btn btn-outline-secondary rounded-end-3 btn-toggle-pw" data-target="password_confirmation">
                                            <i class="bi bi-eye"></i>
                                        </button>
                                    </div>
                                    <div class="invalid-feedback" id="pwConfirmFeedback">Password tidak cocok.</div>
                                </div>
                            </div>

                            <!-- Strength bar -->
                            <div class="mb-3" id="strengthWrap" style="display:none;">
                                <div class="progress" style="height:5px;border-radius:99px;">
                                    <div id="strengthBar" class="progress-bar" role="progressbar" style="width:0%;transition:width .3s,background .3s;"></div>
                                </div>
                                <span id="strengthLabel" class="small text-muted"></span>
                            </div>

                            <!-- Terms -->
                            <div class="form-check mb-4">
                                <input class="form-check-input" type="checkbox" id="terms" name="terms" required>
                                <label class="form-check-label small text-secondary" for="terms">
                                    Saya menyetujui <a href="#" class="text-primary text-decoration-none">Syarat & Ketentuan</a> dan <a href="#" class="text-primary text-decoration-none">Kebijakan Privasi</a> MarketplaceLaundry.
                                </label>
                                <div class="invalid-feedback">Anda harus menyetujui Syarat & Ketentuan.</div>
                            </div>

                            <button type="submit" id="btnSubmit" class="btn btn-primary w-100 rounded-pill py-2 fw-bold shadow-sm" style="background:linear-gradient(135deg,#2563eb,#3b82f6);border:0;">
                                <i class="bi bi-person-check-fill me-2"></i>Buat Akun Pelanggan
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
.form-control::placeholder { color: rgba(148,163,184,0.6); }
.form-control:focus { background: #0f172a !important; border-color: #2563eb !important; color: #e2e8f0 !important; box-shadow: 0 0 0 0.2rem rgba(37,99,235,.2); }
</style>

<script>
// Toggle password visibility
document.querySelectorAll('.btn-toggle-pw').forEach(btn => {
    btn.addEventListener('click', function() {
        const inp = document.getElementById(this.dataset.target);
        if (!inp) return;
        inp.type = inp.type === 'password' ? 'text' : 'password';
        this.querySelector('i').className = inp.type === 'password' ? 'bi bi-eye' : 'bi bi-eye-slash';
    });
});

// Password strength meter
const pwInput = document.getElementById('password');
const bar = document.getElementById('strengthBar');
const lbl = document.getElementById('strengthLabel');
const wrap = document.getElementById('strengthWrap');

pwInput && pwInput.addEventListener('input', function () {
    const val = this.value;
    wrap.style.display = val.length > 0 ? 'block' : 'none';
    let score = 0;
    if (val.length >= 6) score++;
    if (val.length >= 10) score++;
    if (/[A-Z]/.test(val)) score++;
    if (/[0-9]/.test(val)) score++;
    if (/[^A-Za-z0-9]/.test(val)) score++;
    const map = [
        { pct: '20%', color: '#ef4444', text: 'Sangat Lemah' },
        { pct: '40%', color: '#f97316', text: 'Lemah' },
        { pct: '60%', color: '#eab308', text: 'Sedang' },
        { pct: '80%', color: '#22c55e', text: 'Kuat' },
        { pct: '100%', color: '#16a34a', text: 'Sangat Kuat' }
    ];
    const lvl = map[Math.min(score - 1, 4)] || map[0];
    bar.style.width = lvl.pct;
    bar.style.background = lvl.color;
    lbl.textContent = lvl.text;
    lbl.style.color = lvl.color;
});

// Real-time confirm match
const pwConf = document.getElementById('password_confirmation');
const confFb = document.getElementById('pwConfirmFeedback');
pwConf && pwConf.addEventListener('input', function () {
    if (this.value !== pwInput.value) {
        this.classList.add('is-invalid');
        confFb.textContent = 'Password tidak cocok.';
    } else {
        this.classList.remove('is-invalid');
        this.classList.add('is-valid');
    }
});

// Client-side HTML5 validation
document.getElementById('formCustomer').addEventListener('submit', function (e) {
    if (!this.checkValidity()) {
        e.preventDefault();
        e.stopPropagation();
    }
    this.classList.add('was-validated');
    const pw = document.getElementById('password').value;
    const pc = document.getElementById('password_confirmation').value;
    if (pw && pc && pw !== pc) {
        e.preventDefault();
        document.getElementById('password_confirmation').classList.add('is-invalid');
        confFb.textContent = 'Password tidak cocok.';
    }
});

// Formalitas Google Notice
const btnGoogleCustomer = document.getElementById('btnGoogleCustomer');
const googleNoticeCustomer = document.getElementById('googleNoticeCustomer');
let googleCustTimer = null;

if (btnGoogleCustomer && googleNoticeCustomer) {
    btnGoogleCustomer.addEventListener('click', function (e) {
        e.preventDefault();
        googleNoticeCustomer.classList.remove('d-none');
        if (googleCustTimer) clearTimeout(googleCustTimer);
        googleCustTimer = setTimeout(function () {
            googleNoticeCustomer.classList.add('d-none');
        }, 5000);
    });
}
</script>
