<section class="min-vh-100 d-flex align-items-center py-5" style="background: linear-gradient(135deg, #0f172a 0%, #1e293b 60%, #0f172a 100%);">
    <div class="container py-4">

        <div class="text-center mb-5">
            <a href="/" class="d-inline-flex align-items-center gap-2 text-decoration-none mb-4">
                <div class="rounded-circle d-flex align-items-center justify-content-center" style="width:52px;height:52px;background:linear-gradient(135deg,#2563eb,#06b6d4);">
                    <i class="bi bi-water fs-4 text-white"></i>
                </div>
                <span class="fw-bold text-white fs-4">Marketplace<span style="color:#06b6d4">Laundry</span></span>
            </a>
            <h1 class="fw-extrabold text-white mb-2" style="font-size:clamp(1.6rem,4vw,2.2rem);">Pilih Jenis Akun Anda</h1>
            <p class="text-secondary" style="max-width:480px;margin:0 auto;font-size:1.05rem;">Bergabunglah bersama ribuan pengguna MarketplaceLaundry — pilih peran yang sesuai dengan kebutuhan Anda.</p>
        </div>

        <?php if (isset($flashError) && $flashError): ?>
            <div class="alert alert-danger text-center rounded-3 mb-4"><?= htmlspecialchars($flashError) ?></div>
        <?php endif; ?>

        <div class="row justify-content-center g-4">

            <!-- Pelanggan -->
            <div class="col-md-4 col-lg-3">
                <a href="/daftar/pelanggan" class="text-decoration-none d-block h-100">
                    <div class="card border-0 h-100 text-center p-4 rounded-4 position-relative overflow-hidden role-card" style="background:rgba(30,41,59,0.85);backdrop-filter:blur(16px);border:1px solid rgba(255,255,255,0.07) !important;transition:transform .25s,box-shadow .25s;">
                        <div class="mx-auto mb-4 rounded-circle d-flex align-items-center justify-content-center" style="width:72px;height:72px;background:linear-gradient(135deg,#2563eb,#3b82f6);">
                            <i class="bi bi-person-heart fs-2 text-white"></i>
                        </div>
                        <h3 class="fw-bold text-white mb-2" style="font-size:1.25rem;">Pelanggan</h3>
                        <p class="text-secondary small mb-4">Cari dan pesan jasa laundry terbaik di sekitar Anda. Tracking real-time, bayar via QRIS atau tunai.</p>
                        <ul class="list-unstyled text-start small text-secondary mb-4">
                            <li class="mb-2"><i class="bi bi-check-circle-fill text-success me-2"></i>Daftar gratis selamanya</li>
                            <li class="mb-2"><i class="bi bi-check-circle-fill text-success me-2"></i>Banyak pilihan laundry</li>
                            <li class="mb-2"><i class="bi bi-check-circle-fill text-success me-2"></i>Antar-jemput ke pintu rumah</li>
                        </ul>
                        <span class="btn btn-primary w-100 rounded-pill fw-semibold">Daftar Pelanggan</span>
                    </div>
                </a>
            </div>

            <!-- Mitra Laundry -->
            <div class="col-md-4 col-lg-3">
                <a href="/daftar/mitra" class="text-decoration-none d-block h-100">
                    <div class="card border-0 h-100 text-center p-4 rounded-4 position-relative overflow-hidden role-card" style="background:rgba(30,41,59,0.85);backdrop-filter:blur(16px);border:1px solid rgba(255,255,255,0.07) !important;transition:transform .25s,box-shadow .25s;">
                        <div class="mx-auto mb-4 rounded-circle d-flex align-items-center justify-content-center" style="width:72px;height:72px;background:linear-gradient(135deg,#7c3aed,#a855f7);">
                            <i class="bi bi-shop-window fs-2 text-white"></i>
                        </div>
                        <h3 class="fw-bold text-white mb-2" style="font-size:1.25rem;">Mitra Laundry</h3>
                        <p class="text-secondary small mb-4">Buka toko laundry digital Anda. Kelola pesanan, harga, dan kurir dari satu dashboard.</p>
                        <ul class="list-unstyled text-start small text-secondary mb-4">
                            <li class="mb-2"><i class="bi bi-check-circle-fill text-success me-2"></i>Dashboard analitik penjualan</li>
                            <li class="mb-2"><i class="bi bi-check-circle-fill text-success me-2"></i>Terima QRIS & Cash</li>
                            <li class="mb-2"><i class="bi bi-check-circle-fill text-success me-2"></i>Jaringan kurir terintegrasi</li>
                        </ul>
                        <span class="btn w-100 rounded-pill fw-semibold text-white" style="background:linear-gradient(135deg,#7c3aed,#a855f7);">Daftar Mitra</span>
                    </div>
                </a>
            </div>

            <!-- Kurir -->
            <div class="col-md-4 col-lg-3">
                <a href="/daftar/kurir" class="text-decoration-none d-block h-100">
                    <div class="card border-0 h-100 text-center p-4 rounded-4 position-relative overflow-hidden role-card" style="background:rgba(30,41,59,0.85);backdrop-filter:blur(16px);border:1px solid rgba(255,255,255,0.07) !important;transition:transform .25s,box-shadow .25s;">
                        <div class="mx-auto mb-4 rounded-circle d-flex align-items-center justify-content-center" style="width:72px;height:72px;background:linear-gradient(135deg,#0891b2,#06b6d4);">
                            <i class="bi bi-bicycle fs-2 text-white"></i>
                        </div>
                        <h3 class="fw-bold text-white mb-2" style="font-size:1.25rem;">Kurir</h3>
                        <p class="text-secondary small mb-4">Gabung sebagai mitra kurir laundry. Jadwal fleksibel, penghasilan tambahan harian.</p>
                        <ul class="list-unstyled text-start small text-secondary mb-4">
                            <li class="mb-2"><i class="bi bi-check-circle-fill text-success me-2"></i>Jadwal kerja fleksibel</li>
                            <li class="mb-2"><i class="bi bi-check-circle-fill text-success me-2"></i>Penghasilan harian</li>
                            <li class="mb-2"><i class="bi bi-check-circle-fill text-success me-2"></i>Navigasi rute real-time</li>
                        </ul>
                        <span class="btn w-100 rounded-pill fw-semibold text-white" style="background:linear-gradient(135deg,#0891b2,#06b6d4);">Daftar Kurir</span>
                    </div>
                </a>
            </div>

        </div>

        <p class="text-center mt-5 text-secondary small">Sudah punya akun? <a href="/login" class="text-primary fw-semibold text-decoration-none">Masuk di sini</a></p>

    </div>
</section>

<style>
.role-card:hover {
    transform: translateY(-6px);
    box-shadow: 0 20px 60px rgba(0,0,0,0.4) !important;
}
</style>
