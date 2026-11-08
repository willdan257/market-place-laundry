<?php
// Ambil konfigurasi dari Setting database dengan fallback dinamis yang aman
use App\Models\Setting;
use App\Helpers\Env;

$footerAppName = Setting::get('app_name') ?: (string) Env::get('APP_NAME', 'Marketplace Laundry');
$footerContactEmail = Setting::get('contact_email') ?: (string) Env::get('MAIL_FROM_ADDRESS', 'support@marketplacelaundry.id');
$footerContactPhone = Setting::get('wa_admin_phone') ?: (string) Env::get('WA_ADMIN_PHONE', '');
?>
<footer class="text-white pt-5 pb-4 mt-auto" style="background:#0b1120;border-top:1px solid rgba(255,255,255,0.08);">
    <div class="container">
        <div class="row g-4">
            <!-- Kolom 1: Brand & Tagline -->
            <div class="col-lg-4 col-md-6">
                <a href="/" class="d-inline-flex align-items-center gap-2 fw-bold text-white fs-4 text-decoration-none mb-3">
                    <div class="rounded-circle d-flex align-items-center justify-content-center"
                         style="width:40px;height:40px;background:linear-gradient(135deg,#2563eb,#06b6d4);">
                        <i class="bi bi-water fs-5 text-white"></i>
                    </div>
                    <span>Marketplace<span style="color:#38bdf8;">Laundry</span></span>
                </a>
                <p class="small mb-3" style="color:#94a3b8;line-height:1.65;">
                    Platform marketplace laundry yang menghubungkan pelanggan, mitra laundry, dan kurir dalam satu aplikasi.
                </p>
                <div class="d-flex align-items-center gap-2">
                    <span class="badge rounded-pill px-3 py-1 fw-semibold small" style="background:rgba(37,99,235,0.18);color:#60a5fa;border:1px solid rgba(59,130,246,0.3);">
                        <i class="bi bi-shield-check me-1"></i>Ekosistem Terpercaya
                    </span>
                </div>
            </div>

            <!-- Kolom 2: Role Platform -->
            <div class="col-lg-2 col-md-6 col-6">
                <h6 class="text-white fw-bold mb-3" style="letter-spacing:0.02rem;">Role Platform</h6>
                <ul class="list-unstyled small d-grid gap-2 mb-0">
                    <li>
                        <a href="/#peran" class="text-decoration-none footer-link d-inline-flex align-items-center">
                            <i class="bi bi-arrow-right text-primary me-2"></i> Pelanggan
                        </a>
                    </li>
                    <li>
                        <a href="/#peran" class="text-decoration-none footer-link d-inline-flex align-items-center">
                            <i class="bi bi-arrow-right text-primary me-2"></i> Mitra Laundry
                        </a>
                    </li>
                    <li>
                        <a href="/#peran" class="text-decoration-none footer-link d-inline-flex align-items-center">
                            <i class="bi bi-arrow-right text-primary me-2"></i> Kurir
                        </a>
                    </li>
                </ul>
            </div>

            <!-- Kolom 3: Teknologi Terintegrasi -->
            <div class="col-lg-3 col-md-6 col-6">
                <h6 class="text-white fw-bold mb-3" style="letter-spacing:0.02rem;">Teknologi Terintegrasi</h6>
                <ul class="list-unstyled small d-grid gap-2 mb-0" style="color:#94a3b8;">
                    <li class="d-flex align-items-center">
                        <i class="bi bi-geo-alt-fill text-danger me-2 fs-6"></i>
                        <span>Pelacakan &amp; Lokasi</span>
                    </li>
                    <li class="d-flex align-items-center">
                        <i class="bi bi-qr-code-scan text-success me-2 fs-6"></i>
                        <span>Pembayaran QRIS</span>
                    </li>
                    <li class="d-flex align-items-center">
                        <i class="bi bi-robot text-info me-2 fs-6"></i>
                        <span>Asisten AI</span>
                    </li>
                </ul>
            </div>

            <!-- Kolom 4: Bantuan & Layanan -->
            <div class="col-lg-3 col-md-6">
                <h6 class="text-white fw-bold mb-3" style="letter-spacing:0.02rem;">Bantuan &amp; Layanan</h6>
                <ul class="list-unstyled small d-grid gap-2 mb-3">
                    <li>
                        <a href="/assistant" class="text-decoration-none footer-link d-inline-flex align-items-center">
                            <i class="bi bi-question-circle text-primary me-2"></i> Pusat Bantuan (FAQ)
                        </a>
                    </li>
                    <li>
                        <a href="javascript:void(0)" class="text-decoration-none footer-link d-inline-flex align-items-center" onclick="alert('Syarat & Ketentuan berlaku sesuai standar operasional layanan <?= htmlspecialchars($footerAppName) ?>.')">
                            <i class="bi bi-file-text text-primary me-2"></i> Syarat &amp; Ketentuan
                        </a>
                    </li>
                    <li>
                        <a href="javascript:void(0)" class="text-decoration-none footer-link d-inline-flex align-items-center" onclick="alert('Kebijakan Privasi: <?= htmlspecialchars($footerAppName) ?> berkomitmen melindungi data pribadi seluruh pengguna kami.')">
                            <i class="bi bi-shield-lock text-primary me-2"></i> Kebijakan Privasi
                        </a>
                    </li>
                </ul>
                <div class="small pt-2 border-top border-secondary border-opacity-25" style="color:#94a3b8;">
                    <div class="mb-1 d-flex align-items-center gap-2">
                        <i class="bi bi-envelope text-primary"></i>
                        <a href="mailto:<?= htmlspecialchars($footerContactEmail) ?>" class="text-decoration-none footer-link text-truncate">
                            <?= htmlspecialchars($footerContactEmail) ?>
                        </a>
                    </div>
                    <?php if (!empty($footerContactPhone)): ?>
                    <div class="d-flex align-items-center gap-2">
                        <i class="bi bi-whatsapp text-success"></i>
                        <a href="https://wa.me/<?= preg_replace('/\D/', '', $footerContactPhone) ?>" target="_blank" rel="noopener noreferrer" class="text-decoration-none footer-link">
                            <?= htmlspecialchars($footerContactPhone) ?>
                        </a>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <hr class="my-4" style="border-color:rgba(255,255,255,0.08);">

        <div class="row align-items-center gy-2">
            <div class="col-md-6 text-center text-md-start small" style="color:#64748b;">
                &copy; <?= date('Y') ?> <?= htmlspecialchars($footerAppName) ?>. Seluruh hak cipta dilindungi.
            </div>
            <div class="col-md-6 text-center text-md-end small" style="color:#64748b;">
                Ekosistem Laundry Modern &amp; Terintegrasi
            </div>
        </div>
    </div>
</footer>

<style>
.footer-link {
    color: #94a3b8;
    transition: color 0.2s ease, transform 0.2s ease;
}
.footer-link:hover {
    color: #38bdf8 !important;
    transform: translateX(2px);
}
</style>

<!-- Bootstrap 5 JS Bundle -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<!-- Custom JavaScript -->
<script src="/assets/js/app.js"></script>
</body>
</html>
