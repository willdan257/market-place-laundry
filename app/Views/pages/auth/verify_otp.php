<section class="min-vh-100 py-5 d-flex align-items-center" style="background:linear-gradient(135deg,#0f172a 0%,#1e293b 100%);">
    <div class="container py-4">
        <div class="row justify-content-center">
            <div class="col-md-7 col-lg-5">

                <!-- Header -->
                <div class="text-center mb-4">
                    <a href="/lupa-password" class="text-secondary text-decoration-none small d-inline-flex align-items-center gap-1 mb-3">
                        <i class="bi bi-arrow-left"></i> Ganti Nomor WhatsApp
                    </a>
                    <div class="rounded-circle d-flex align-items-center justify-content-center mx-auto mb-3"
                         style="width:52px;height:52px;background:linear-gradient(135deg,#2563eb,#06b6d4);">
                        <i class="bi bi-chat-dots-fill fs-4 text-white"></i>
                    </div>
                    <h1 class="fw-extrabold text-white mb-1" style="font-size:1.6rem;">Verifikasi Kode OTP</h1>
                    <p class="text-secondary small">
                        Masukkan 6 digit kode yang kami kirimkan ke WhatsApp
                        <strong class="text-white"><?= htmlspecialchars($maskedPhone ?? '') ?></strong>.
                    </p>
                </div>

                <!-- Flash Messages -->
                <?php if (isset($flashSuccess) && $flashSuccess): ?>
                    <div class="alert alert-success rounded-3 py-2 mb-3 small text-center"><i class="bi bi-check-circle-fill me-2"></i><?= htmlspecialchars($flashSuccess) ?></div>
                <?php endif; ?>
                <?php if (isset($flashError) && $flashError): ?>
                    <div class="alert alert-danger rounded-3 py-2 mb-3 small text-center"><i class="bi bi-exclamation-triangle-fill me-2"></i><?= htmlspecialchars($flashError) ?></div>
                <?php endif; ?>

                <!-- Card -->
                <div class="card border-0 rounded-4 shadow-lg"
                     style="background:rgba(30,41,59,0.9);backdrop-filter:blur(16px);border:1px solid rgba(255,255,255,0.07) !important;">
                    <div class="card-body p-4 p-lg-5">
                        <form method="POST" action="/lupa-password/verifikasi" id="formVerifyOtp" novalidate>
                            <input type="hidden" name="_token" value="<?= \App\Helpers\Session::csrfToken() ?>">

                            <div class="mb-4 text-center">
                                <label class="form-label small fw-semibold text-secondary mb-3 d-block" for="otp_code">
                                    Kode OTP 6 Digit <span class="text-danger">*</span>
                                </label>
                                <input type="text" id="otp_code" name="otp_code" class="form-control form-control-lg text-center font-monospace fw-bold"
                                       style="background:#0f172a;border-color:rgba(255,255,255,0.12);color:#38bdf8;font-size:1.8rem;letter-spacing:0.5rem;"
                                       placeholder="••••••" maxlength="6" pattern="[0-9]{6}" required autocomplete="one-time-code" autofocus>
                                <div class="invalid-feedback">Masukkan 6 digit angka kode verifikasi.</div>
                                <div class="text-secondary small mt-2">
                                    <i class="bi bi-clock-history me-1"></i>Maksimal 5 kali percobaan. Berlaku 10 menit.
                                </div>
                            </div>

                            <button type="submit" class="btn w-100 rounded-pill py-2 fw-bold text-white mb-3"
                                    style="background:linear-gradient(135deg,#2563eb,#3b82f6);border:0;">
                                <i class="bi bi-shield-check me-2"></i>Verifikasi Kode
                            </button>
                        </form>

                        <div class="text-center mt-3">
                            <span class="text-secondary small">Tidak menerima kode?</span>
                            <a href="/lupa-password" class="text-primary fw-semibold small ms-1 text-decoration-none">Kirim Ulang</a>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>
</section>

<script>
document.getElementById('formVerifyOtp').addEventListener('submit', function (e) {
    if (!this.checkValidity()) {
        e.preventDefault();
        e.stopPropagation();
    }
    this.classList.add('was-validated');
});
</script>

