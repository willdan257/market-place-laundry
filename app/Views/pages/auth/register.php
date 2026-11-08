<section class="py-5 bg-light min-vh-100 d-flex align-items-center">
    <div class="container py-4">
        <div class="row justify-content-center">
            <div class="col-md-8 col-lg-6">
                <div class="card border-0 shadow-lg rounded-4 overflow-hidden">
                    <div class="card-body p-4 p-lg-5">
                        <div class="text-center mb-4">
                            <h4 class="fw-bold mb-1">Registrasi Akun Baru</h4>
                            <p class="text-muted small">Lengkapi data pendaftaran akun Anda</p>
                        </div>

                        <!-- Google Formalitas -->
                        <button type="button" id="btnGoogleRegister"
                                class="btn w-100 rounded-pill py-2.5 mb-2 fw-semibold d-flex align-items-center justify-content-center gap-2 shadow-sm"
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
                        <div id="googleNoticeRegister" class="alert alert-info py-2 px-3 small rounded-3 text-center mb-3 d-none border-0 shadow-sm"
                             style="background:#e0f2fe;color:#0284c7;font-size:0.85rem;line-height:1.4;">
                            <i class="bi bi-info-circle me-1"></i>Login dengan Google belum tersedia. Silakan masuk dengan email atau nomor WhatsApp dan password.
                        </div>

                        <div class="position-relative text-center my-4">
                            <hr class="text-muted opacity-25">
                            <span class="position-absolute top-50 start-50 translate-middle bg-white px-3 text-muted small">atau lengkapi form</span>
                        </div>

                        <form method="POST" action="/register">
                            <input type="hidden" name="_token" value="<?= \App\Helpers\Session::csrfToken() ?>">
                            
                            <div class="mb-3">
                                <label class="form-label small fw-semibold text-secondary">Pilih Role Pengguna</label>
                                <select name="role" class="form-select rounded-3" required>
                                    <option value="customer" selected>Customer (Pelanggan Laundry)</option>
                                    <option value="owner">Owner Laundry (Pemilik Jasa)</option>
                                    <option value="kurir">Kurir (Penjemput & Pengantar)</option>
                                </select>
                            </div>

                            <div class="mb-3">
                                <label class="form-label small fw-semibold text-secondary">Nama Lengkap</label>
                                <input type="text" name="name" class="form-control rounded-3" placeholder="Nama Lengkap Anda" required>
                            </div>

                            <div class="row g-3 mb-3">
                                <div class="col-md-6">
                                    <label class="form-label small fw-semibold text-secondary">Alamat Email</label>
                                    <input type="email" name="email" class="form-control rounded-3" placeholder="nama@email.com" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label small fw-semibold text-secondary">Nomor Telepon/WA</label>
                                    <input type="text" name="phone" class="form-control rounded-3" placeholder="081234567890" required>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label small fw-semibold text-secondary">Alamat Lengkap</label>
                                <textarea name="address" class="form-control rounded-3" rows="2" placeholder="Nama jalan, nomor rumah..."></textarea>
                            </div>

                            <div class="row g-3 mb-3">
                                <div class="col-6">
                                    <label class="form-label small fw-semibold text-secondary">RT</label>
                                    <input type="text" name="rt" class="form-control rounded-3" placeholder="001">
                                </div>
                                <div class="col-6">
                                    <label class="form-label small fw-semibold text-secondary">RW</label>
                                    <input type="text" name="rw" class="form-control rounded-3" placeholder="005">
                                </div>
                            </div>

                            <div class="row g-3 mb-3">
                                <div class="col-6">
                                    <label class="form-label small fw-semibold text-secondary">Latitude</label>
                                    <input type="text" name="latitude" class="form-control rounded-3" placeholder="-6.200000">
                                </div>
                                <div class="col-6">
                                    <label class="form-label small fw-semibold text-secondary">Longitude</label>
                                    <input type="text" name="longitude" class="form-control rounded-3" placeholder="106.816666">
                                </div>
                            </div>

                            <div class="mb-4">
                                <label class="form-label small fw-semibold text-secondary">Password</label>
                                <input type="password" name="password" class="form-control rounded-3" placeholder="••••••••" required>
                            </div>

                            <button type="submit" class="btn btn-primary w-100 rounded-pill py-2.5 fw-bold shadow-sm">
                                Buat Akun Baru
                            </button>
                        </form>

                        <div class="text-center mt-4">
                            <span class="text-muted small">Sudah memiliki akun?</span>
                            <a href="/login" class="text-primary fw-semibold small ms-1 text-decoration-none">Masuk</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<script>
const btnGoogleRegister = document.getElementById('btnGoogleRegister');
const googleNoticeRegister = document.getElementById('googleNoticeRegister');
let googleRegTimer = null;

if (btnGoogleRegister && googleNoticeRegister) {
    btnGoogleRegister.addEventListener('click', function (e) {
        e.preventDefault();
        googleNoticeRegister.classList.remove('d-none');
        if (googleRegTimer) clearTimeout(googleRegTimer);
        googleRegTimer = setTimeout(function () {
            googleNoticeRegister.classList.add('d-none');
        }, 5000);
    });
}
</script>
