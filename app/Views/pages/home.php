<!-- Hero Section -->
<section class="hero-section text-white py-5 position-relative overflow-hidden">
    <div class="container py-lg-5 position-relative z-1">
        <div class="row align-items-center g-5">
            <div class="col-lg-6">
                <div class="badge bg-primary-subtle text-primary border border-primary-subtle rounded-pill px-3 py-2 mb-3 fw-medium">
                    <i class="bi bi-stars text-warning me-1"></i> Ecosystem Marketplace Laundry Modern
                </div>
                <h1 class="display-4 fw-extrabold mb-4 text-gradient">
                    Solusi Laundry Digital Serba Praktis & Terpercaya
                </h1>
                <p class="lead text-light-subtle mb-4">
                    Hubungkan permintaan cucian Anda dengan penyedia jasa laundry terbaik di sekitar. Dilengkapi perhitungan ongkir otomatis berbasis lokasi, penimbangan transparan, dan sistem notifikasi real-time.
                </p>
                <div class="d-flex flex-wrap gap-3">
                    <a href="/register" class="btn btn-primary-accent btn-lg rounded-pill px-4 fw-semibold shadow-lg text-white">
                        <i class="bi bi-person-plus-fill me-2"></i>Daftar Sekarang
                    </a>
                    <a href="/login" class="btn btn-outline-light btn-lg rounded-pill px-4 fw-medium">
                        <i class="bi bi-box-arrow-in-right me-2"></i>Masuk Akun
                    </a>
                </div>
                <div class="row mt-5 pt-3 border-top border-secondary-subtle g-3">
                    <div class="col-4">
                        <h4 class="fw-bold text-white mb-0">3 Roles</h4>
                        <small class="text-muted">Customer, Owner, Kurir</small>
                    </div>
                    <div class="col-4">
                        <h4 class="fw-bold text-white mb-0">Google Maps</h4>
                        <small class="text-muted">Jarak & Ongkir Akurat</small>
                    </div>
                    <div class="col-4">
                        <h4 class="fw-bold text-white mb-0">QRIS & Cash</h4>
                        <small class="text-muted">Batas Waktu 1 Jam</small>
                    </div>
                </div>
            </div>
            <div class="col-lg-6">
                <!-- Ilustrasi Mesin Cuci -->
                <div class="text-center mb-3">
                    <img src="/assets/img/mesin-cuci.png" 
                         alt="Ilustrasi mesin cuci" 
                         class="img-fluid hero-washing-machine">
                </div>

                <div class="hero-card-preview p-4 rounded-4 shadow-2xl bg-glass border border-white-10">
                    <div class="d-flex align-items-center justify-content-between mb-4">
                        <div class="d-flex align-items-center gap-3">
                            <div class="bg-success text-white rounded-circle p-3 d-flex align-items-center justify-content-center" style="width:50px; height:50px;">
                                <i class="bi bi-cart-check fs-4"></i>
                            </div>
                            <div>
                                <h6 class="mb-0 fw-bold text-white">Simulasi Pesanan Laundry</h6>
                                <small class="text-muted">Order ID: #LAUNDRY-8829</small>
                            </div>
                        </div>
                        <span class="badge bg-warning text-dark px-3 py-2 rounded-pill">Status: Ditimbang</span>
                    </div>
                    
                    <div class="p-3 bg-dark-subtle rounded-3 mb-3 text-white-50 small d-grid gap-2">
                        <div class="d-flex justify-content-between">
                            <span>Pilihan Layanan:</span>
                            <strong class="text-white">Cuci Komplit Express</strong>
                        </div>
                        <div class="d-flex justify-content-between">
                            <span>Estimasi Jarak (Google Maps):</span>
                            <strong class="text-white">2.5 km</strong>
                        </div>
                        <div class="d-flex justify-content-between">
                            <span>Berat Laundry (Owner):</span>
                            <strong class="text-white">4.0 kg</strong>
                        </div>
                        <div class="d-flex justify-content-between">
                            <span>Rincian Biaya:</span>
                            <strong class="text-white">(4 kg x Rp 7.000) + Ongkir Rp 8.000</strong>
                        </div>
                    </div>

                    <div class="d-flex align-items-center justify-content-between p-3 bg-primary-gradient rounded-3 text-white">
                        <div>
                            <span class="small opacity-75 d-block">Total Pembayaran</span>
                            <h4 class="fw-bold mb-0">Rp 36.000</h4>
                        </div>
                        <button class="btn btn-light btn-sm rounded-pill px-3 fw-bold text-primary">
                            <i class="bi bi-qr-code-scan me-1"></i> Bayar QRIS
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Layanan Section -->
<section id="layanan" class="py-5 bg-light">
    <div class="container py-lg-4">
        <div class="text-center max-w-700 mx-auto mb-5">
            <h2 class="fw-bold mb-2">Layanan Terpadu Platform</h2>
            <p class="text-muted">Memberikan kenyamanan penuh bagi pengguna dari proses pemesanan hingga pakaian kembali bersih dan harum.</p>
        </div>

        <div class="row g-4">
            <div class="col-md-4">
                <div class="card h-100 border-0 shadow-sm rounded-4 p-4 text-center hover-lift">
                    <div class="icon-circle bg-primary-subtle text-primary mx-auto mb-3">
                        <i class="bi bi-geo-alt fs-3"></i>
                    </div>
                    <h5 class="fw-bold mb-2">Hitung Jarak & Ongkir</h5>
                    <p class="text-muted small">
                        Integrasi Google Maps API menghitung jarak dari lokasi Anda (Latitude & Longitude) ke lokasi laundry secara presisi.
                    </p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card h-100 border-0 shadow-sm rounded-4 p-4 text-center hover-lift">
                    <div class="icon-circle bg-success-subtle text-success mx-auto mb-3">
                        <i class="bi bi-speedometer2 fs-3"></i>
                    </div>
                    <h5 class="fw-bold mb-2">Penimbangan Transparan</h5>
                    <p class="text-muted small">
                        Pemilik laundry memasukkan berat laundry yang ditimbang, sistem menghitung total biaya secara otomatis dan akurat.
                    </p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card h-100 border-0 shadow-sm rounded-4 p-4 text-center hover-lift">
                    <div class="icon-circle bg-info-subtle text-info mx-auto mb-3">
                        <i class="bi bi-truck fs-3"></i>
                    </div>
                    <h5 class="fw-bold mb-2">Penugasan Kurir</h5>
                    <p class="text-muted small">
                        Kurir menerima tugas pickup dari lokasi pelanggan dan pengantaran kembali setelah laundry selesai diproses.
                    </p>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Peran / Roles Section -->
<section id="peran" class="py-5">
    <div class="container py-lg-4">
        <div class="text-center max-w-700 mx-auto mb-5">
            <h2 class="fw-bold mb-2">Tiga Role Utama Sistem</h2>
            <p class="text-muted">Hak akses dan antarmuka khusus dirancang sesuai dengan peran pengguna dalam sistem.</p>
        </div>

        <div class="row g-4">
            <div class="col-lg-4">
                <div class="card border-0 shadow-sm rounded-4 overflow-hidden h-100">
                    <div class="card-header bg-primary text-white p-4">
                        <i class="bi bi-person-fill fs-2 mb-2 d-block"></i>
                        <h5 class="fw-bold mb-0">Role Customer</h5>
                    </div>
                    <div class="card-body p-4">
                        <ul class="list-unstyled d-grid gap-2 text-secondary small mb-0">
                            <li><i class="bi bi-check-circle-fill text-success me-2"></i> Login/Register Google</li>
                            <li><i class="bi bi-check-circle-fill text-success me-2"></i> Menyimpan alamat, RT, RW & Koordinat</li>
                            <li><i class="bi bi-check-circle-fill text-success me-2"></i> Membuat order laundry baru</li>
                            <li><i class="bi bi-check-circle-fill text-success me-2"></i> Bayar via QRIS (Limit 1 Jam) atau Cash</li>
                            <li><i class="bi bi-check-circle-fill text-success me-2"></i> Melakukan tracking status order</li>
                        </ul>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="card border-0 shadow-sm rounded-4 overflow-hidden h-100">
                    <div class="card-header bg-dark text-white p-4">
                        <i class="bi bi-shop fs-2 mb-2 d-block text-warning"></i>
                        <h5 class="fw-bold mb-0">Role Owner Laundry</h5>
                    </div>
                    <div class="card-body p-4">
                        <ul class="list-unstyled d-grid gap-2 text-secondary small mb-0">
                            <li><i class="bi bi-check-circle-fill text-success me-2"></i> Kelola daftar pesanan masuk</li>
                            <li><i class="bi bi-check-circle-fill text-success me-2"></i> Input berat timbangan laundry</li>
                            <li><i class="bi bi-check-circle-fill text-success me-2"></i> Menentukan biaya tambahan & diskon</li>
                            <li><i class="bi bi-check-circle-fill text-success me-2"></i> Perubahan status proses cuci</li>
                            <li><i class="bi bi-check-circle-fill text-success me-2"></i> Konfirmasi pembayaran cash</li>
                        </ul>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="card border-0 shadow-sm rounded-4 overflow-hidden h-100">
                    <div class="card-header bg-info text-white p-4">
                        <i class="bi bi-bicycle fs-2 mb-2 d-block"></i>
                        <h5 class="fw-bold mb-0">Role Kurir</h5>
                    </div>
                    <div class="card-body p-4">
                        <ul class="list-unstyled d-grid gap-2 text-secondary small mb-0">
                            <li><i class="bi bi-check-circle-fill text-success me-2"></i> Terima tugas jemput (Pickup)</li>
                            <li><i class="bi bi-check-circle-fill text-success me-2"></i> Terima tugas antar (Delivery)</li>
                            <li><i class="bi bi-check-circle-fill text-success me-2"></i> Update status penjemputan/pengantaran</li>
                            <li><i class="bi bi-check-circle-fill text-success me-2"></i> Navigasi alamat & titik koordinat</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<style>
.hero-washing-machine {
    width: 280px;
    max-width: 100%;
    height: auto;
    display: inline-block;
    animation: floatingMachine 3.5s ease-in-out infinite;
    pointer-events: none;
    filter: drop-shadow(0 15px 25px rgba(0, 0, 0, 0.35));
}

@keyframes floatingMachine {
    0%, 100% {
        transform: translateY(0);
    }
    50% {
        transform: translateY(-12px);
    }
}

@media (max-width: 768px) {
    .hero-washing-machine {
        width: 180px;
    }
}
</style>


