<section class="min-vh-100 py-5" style="background:linear-gradient(135deg,#0f172a 0%,#1e293b 100%);">
    <div class="container py-4">
        <div class="row justify-content-center">
            <div class="col-lg-8">

                <!-- Header -->
                <div class="text-center mb-4">
                    <a href="/daftar" class="text-secondary text-decoration-none small d-inline-flex align-items-center gap-1 mb-3">
                        <i class="bi bi-arrow-left"></i> Ganti peran
                    </a>
                    <div class="mx-auto mb-3 rounded-circle d-flex align-items-center justify-content-center"
                         style="width:56px;height:56px;background:linear-gradient(135deg,#7c3aed,#a855f7);">
                        <i class="bi bi-shop-window fs-3 text-white"></i>
                    </div>
                    <h1 class="fw-extrabold text-white mb-1" style="font-size:1.6rem;">Daftar Mitra Laundry</h1>
                    <p class="text-secondary small">Tiga langkah mudah — isi data akun, info usaha, dan layanan awal Anda.</p>
                </div>

                <!-- Step Indicator -->
                <div class="d-flex justify-content-center align-items-center gap-0 mb-4" id="stepIndicator">
                    <?php foreach ([['1','Akun'],['2','Usaha'],['3','Layanan']] as $i => [$num,$label]): ?>
                    <div class="d-flex align-items-center">
                        <div class="step-circle d-flex align-items-center justify-content-center rounded-circle fw-bold text-white me-1"
                             id="stepCircle<?= $num ?>"
                             style="width:36px;height:36px;background:<?= $i === 0 ? 'linear-gradient(135deg,#7c3aed,#a855f7)' : 'rgba(255,255,255,0.12)' ?>;font-size:.875rem;transition:background .4s;">
                            <?= $num ?>
                        </div>
                        <span class="small me-3 <?= $i === 0 ? 'text-white fw-semibold' : 'text-secondary' ?>" id="stepLabel<?= $num ?>"><?= $label ?></span>
                        <?php if ($i < 2): ?><span class="text-secondary me-3" style="opacity:.4;">→</span><?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>

                <!-- Flash Messages -->
                <?php if (isset($flashError) && $flashError): ?>
                    <div class="alert alert-danger rounded-3 py-2 mb-3 small text-center"><i class="bi bi-exclamation-triangle-fill me-2"></i><?= htmlspecialchars($flashError) ?></div>
                <?php endif; ?>

                <!-- Card -->
                <div class="card border-0 rounded-4 shadow-lg"
                     style="background:rgba(30,41,59,0.9);backdrop-filter:blur(16px);border:1px solid rgba(255,255,255,0.07) !important;">
                    <div class="card-body p-4 p-lg-5">
                        <form method="POST" action="/daftar/mitra" enctype="multipart/form-data" id="formMitra" novalidate>
                            <input type="hidden" name="_token" value="<?= \App\Helpers\Session::csrfToken() ?>">

                            <!-- ========== STEP 1: Akun ========== -->
                            <div id="step1">
                                <h5 class="fw-bold text-white mb-4"><i class="bi bi-person-circle me-2" style="color:#a855f7;"></i>Langkah 1 — Data Pemilik</h5>

                                <div class="mb-3">
                                    <label class="form-label small fw-semibold text-secondary" for="name">Nama Pemilik Usaha <span class="text-danger">*</span></label>
                                    <input type="text" id="name" name="name" class="form-control form-control-dark rounded-3" placeholder="Nama lengkap Anda" required minlength="3" autocomplete="name">
                                </div>
                                <div class="row g-3 mb-3">
                                    <div class="col-md-6">
                                        <label class="form-label small fw-semibold text-secondary" for="email">Email <span class="text-danger">*</span></label>
                                        <input type="text" id="email" name="email" class="form-control form-control-dark rounded-3" placeholder="nama@email.com atau email bebas" required autocomplete="email">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label small fw-semibold text-secondary" for="phone">No. WhatsApp <span class="text-danger">*</span></label>
                                        <input type="tel" id="phone" name="phone" class="form-control form-control-dark rounded-3" placeholder="08123456789" required autocomplete="tel">
                                    </div>
                                </div>
                                <div class="row g-3 mb-4">
                                    <div class="col-md-6">
                                        <label class="form-label small fw-semibold text-secondary" for="password">Password <span class="text-danger">*</span></label>
                                        <input type="password" id="password" name="password" class="form-control form-control-dark rounded-3" placeholder="Password bebas" required autocomplete="new-password">
                                        <div class="invalid-feedback">Password wajib diisi.</div>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label small fw-semibold text-secondary" for="password_confirmation">Konfirmasi Password <span class="text-danger">*</span></label>
                                        <input type="password" id="password_confirmation" name="password_confirmation" class="form-control form-control-dark rounded-3" placeholder="Ulangi password" required autocomplete="new-password">
                                        <div class="invalid-feedback">Password tidak cocok.</div>
                                    </div>
                                </div>

                                <button type="button" class="btn w-100 rounded-pill py-2 fw-bold text-white" style="background:linear-gradient(135deg,#7c3aed,#a855f7);" onclick="goStep(2)">
                                    Lanjut ke Info Usaha <i class="bi bi-arrow-right ms-2"></i>
                                </button>
                            </div>

                            <!-- ========== STEP 2: Usaha ========== -->
                            <div id="step2" style="display:none;">
                                <h5 class="fw-bold text-white mb-4"><i class="bi bi-geo-alt-fill me-2" style="color:#a855f7;"></i>Langkah 2 — Info Usaha Laundry</h5>

                                <div class="mb-3">
                                    <label class="form-label small fw-semibold text-secondary" for="nama_usaha">Nama Usaha Laundry <span class="text-danger">*</span></label>
                                    <input type="text" id="nama_usaha" name="nama_usaha" class="form-control form-control-dark rounded-3" placeholder="Contoh: Laundry Bersih & Wangi" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label small fw-semibold text-secondary" for="alamat">Alamat Lengkap Laundry <span class="text-danger">*</span></label>
                                    <textarea id="alamat" name="alamat" rows="2" class="form-control form-control-dark rounded-3" placeholder="Jl. Cempaka No. 5, RT 01/02, Kel. Harapan, Kec. Maju..." required></textarea>
                                </div>

                                <!-- Map Picker -->
                                <div class="mb-3">
                                    <label class="form-label small fw-semibold text-secondary">Lokasi di Peta <span class="text-muted fw-normal">(klik peta untuk pinpoint)</span></label>
                                    <div id="mapPicker" style="height:240px;border-radius:12px;overflow:hidden;border:1px solid rgba(255,255,255,0.15);"></div>
                                    <div class="row g-2 mt-2">
                                        <div class="col-6">
                                            <input type="text" id="lat_display" class="form-control form-control-dark form-control-sm rounded-3" placeholder="Latitude" readonly>
                                            <input type="hidden" name="lat" id="lat" value="-6.200000">
                                        </div>
                                        <div class="col-6">
                                            <input type="text" id="lng_display" class="form-control form-control-dark form-control-sm rounded-3" placeholder="Longitude" readonly>
                                            <input type="hidden" name="lng" id="lng" value="106.816666">
                                        </div>
                                    </div>
                                    <button type="button" class="btn btn-sm btn-outline-secondary mt-2 rounded-pill" onclick="useMyLocation()">
                                        <i class="bi bi-crosshair me-1"></i>Gunakan Lokasi Saya
                                    </button>
                                </div>

                                <div class="mb-4">
                                    <label class="form-label small fw-semibold text-secondary" for="jam_operasional">Jam Operasional</label>
                                    <input type="text" id="jam_operasional" name="jam_operasional" class="form-control form-control-dark rounded-3" placeholder="Contoh: 07:00 - 21:00" value="07:00 - 21:00">
                                </div>

                                <div class="d-flex gap-2">
                                    <button type="button" class="btn btn-outline-secondary rounded-pill px-4" onclick="goStep(1)"><i class="bi bi-arrow-left me-2"></i>Kembali</button>
                                    <button type="button" class="btn rounded-pill py-2 fw-bold text-white flex-fill" style="background:linear-gradient(135deg,#7c3aed,#a855f7);" onclick="goStep(3)">
                                        Lanjut ke Layanan <i class="bi bi-arrow-right ms-2"></i>
                                    </button>
                                </div>
                            </div>

                            <!-- ========== STEP 3: Layanan ========== -->
                            <div id="step3" style="display:none;">
                                <h5 class="fw-bold text-white mb-4"><i class="bi bi-list-stars me-2" style="color:#a855f7;"></i>Langkah 3 — Paket Layanan & Dokumen</h5>

                                <p class="text-secondary small mb-3">Atur harga layanan awal (bisa diubah kapan saja dari dashboard).</p>

                                <div class="card rounded-3 mb-3 p-3" style="background:rgba(255,255,255,0.04);border:1px solid rgba(255,255,255,0.08)!important;">
                                    <div class="row align-items-center g-3">
                                        <div class="col-md-5">
                                            <span class="text-white fw-semibold small"><i class="bi bi-scale me-2 text-purple-400" style="color:#c084fc;"></i>Cuci Kiloan Reguler</span>
                                            <div class="text-muted" style="font-size:.75rem;">Per kg (cuci, kering, lipat)</div>
                                        </div>
                                        <div class="col-md-7">
                                            <div class="input-group input-group-sm">
                                                <span class="input-group-text" style="background:rgba(255,255,255,0.06);border-color:rgba(255,255,255,0.12);color:#94a3b8;">Rp</span>
                                                <input type="number" name="harga_kiloan" class="form-control form-control-dark" value="8000" min="1000" step="500" placeholder="8000">
                                                <span class="input-group-text" style="background:rgba(255,255,255,0.06);border-color:rgba(255,255,255,0.12);color:#94a3b8;">/ kg</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="card rounded-3 mb-3 p-3" style="background:rgba(255,255,255,0.04);border:1px solid rgba(255,255,255,0.08)!important;">
                                    <div class="row align-items-center g-3">
                                        <div class="col-md-5">
                                            <span class="text-white fw-semibold small"><i class="bi bi-shirt me-2" style="color:#c084fc;"></i>Cuci Satuan (Kemeja/Jas)</span>
                                            <div class="text-muted" style="font-size:.75rem;">Per pcs / item</div>
                                        </div>
                                        <div class="col-md-7">
                                            <div class="input-group input-group-sm">
                                                <span class="input-group-text" style="background:rgba(255,255,255,0.06);border-color:rgba(255,255,255,0.12);color:#94a3b8;">Rp</span>
                                                <input type="number" name="harga_satuan" class="form-control form-control-dark" value="15000" min="1000" step="500" placeholder="15000">
                                                <span class="input-group-text" style="background:rgba(255,255,255,0.06);border-color:rgba(255,255,255,0.12);color:#94a3b8;">/ pcs</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="card rounded-3 mb-4 p-3" style="background:rgba(255,255,255,0.04);border:1px solid rgba(255,255,255,0.08)!important;">
                                    <div class="row align-items-center g-3">
                                        <div class="col-md-5">
                                            <span class="text-white fw-semibold small"><i class="bi bi-lightning-charge-fill me-2" style="color:#facc15;"></i>Cuci Express (4-6 jam)</span>
                                            <div class="text-muted" style="font-size:.75rem;">Per kg layanan cepat</div>
                                        </div>
                                        <div class="col-md-7">
                                            <div class="input-group input-group-sm">
                                                <span class="input-group-text" style="background:rgba(255,255,255,0.06);border-color:rgba(255,255,255,0.12);color:#94a3b8;">Rp</span>
                                                <input type="number" name="harga_express" class="form-control form-control-dark" value="15000" min="1000" step="500" placeholder="15000">
                                                <span class="input-group-text" style="background:rgba(255,255,255,0.06);border-color:rgba(255,255,255,0.12);color:#94a3b8;">/ kg</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="mb-4">
                                    <label class="form-label small fw-semibold text-secondary" for="dokumen">Upload Dokumen Usaha <span class="text-muted small fw-normal">(SIUP/NIB/KTP — opsional, maks. 2MB)</span></label>
                                    <input type="file" id="dokumen" name="dokumen" class="form-control form-control-dark rounded-3" accept="image/*,application/pdf">
                                    <div class="form-text text-secondary small">Format: JPG, PNG, PDF. Dokumen membantu mempercepat verifikasi.</div>
                                </div>

                                <div class="alert small py-2 mb-4 rounded-3" style="background:rgba(124,58,237,0.15);border:1px solid rgba(168,85,247,0.25);color:#c4b5fd;">
                                    <i class="bi bi-info-circle-fill me-2"></i>Setelah mendaftar, tim Admin akan memverifikasi data Anda dalam 1×24 jam. Toko akan aktif setelah disetujui.
                                </div>

                                <div class="d-flex gap-2">
                                    <button type="button" class="btn btn-outline-secondary rounded-pill px-4" onclick="goStep(2)"><i class="bi bi-arrow-left me-2"></i>Kembali</button>
                                    <button type="submit" class="btn rounded-pill py-2 fw-bold text-white flex-fill" style="background:linear-gradient(135deg,#7c3aed,#a855f7);">
                                        <i class="bi bi-send-check-fill me-2"></i>Kirim Pendaftaran Mitra
                                    </button>
                                </div>
                            </div>

                        </form>

                        <p class="text-center mt-4 small text-secondary mb-0">Sudah punya akun? <a href="/login" class="text-primary fw-semibold text-decoration-none">Masuk di sini</a></p>
                    </div>
                </div>

            </div>
        </div>
    </div>
</section>

<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

<style>
.form-control-dark {
    background: #0f172a !important;
    border-color: rgba(255,255,255,0.12) !important;
    color: #e2e8f0 !important;
}
.form-control-dark::placeholder { color: rgba(148,163,184,0.6); }
.form-control-dark:focus { border-color: #7c3aed !important; box-shadow: 0 0 0 0.2rem rgba(124,58,237,.2); }
</style>

<script>
// ─── Step Navigation ───────────────────────────────────────
let currentStep = 1;

function goStep(n) {
    if (n > currentStep && !validateStep(currentStep)) return;
    document.getElementById('step' + currentStep).style.display = 'none';
    document.getElementById('step' + n).style.display = 'block';
    currentStep = n;
    updateStepUI();
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

function validateStep(s) {
    if (s === 1) {
        const fields = ['name','email','phone','password','password_confirmation'];
        let valid = true;
        fields.forEach(id => {
            const el = document.getElementById(id);
            if (el && !el.value.trim()) { el.classList.add('is-invalid'); valid = false; }
            else if (el) el.classList.remove('is-invalid');
        });
        const pw = document.getElementById('password').value;
        const pc = document.getElementById('password_confirmation').value;
        if (pw && pc && pw !== pc) {
            document.getElementById('password_confirmation').classList.add('is-invalid');
            valid = false;
        }
        if (!pw) { document.getElementById('password').classList.add('is-invalid'); valid = false; }
        return valid;
    }
    if (s === 2) {
        const nu = document.getElementById('nama_usaha').value.trim();
        const al = document.getElementById('alamat').value.trim();
        let valid = true;
        if (!nu) { document.getElementById('nama_usaha').classList.add('is-invalid'); valid = false; }
        if (!al) { document.getElementById('alamat').classList.add('is-invalid'); valid = false; }
        return valid;
    }
    return true;
}

function updateStepUI() {
    [1,2,3].forEach(n => {
        const c = document.getElementById('stepCircle' + n);
        const l = document.getElementById('stepLabel' + n);
        if (n === currentStep) {
            c.style.background = 'linear-gradient(135deg,#7c3aed,#a855f7)';
            l.classList.add('text-white','fw-semibold');
            l.classList.remove('text-secondary');
        } else if (n < currentStep) {
            c.style.background = '#16a34a';
            c.innerHTML = '<i class="bi bi-check"></i>';
            l.classList.remove('text-white','fw-semibold');
            l.classList.add('text-secondary');
        } else {
            c.style.background = 'rgba(255,255,255,0.12)';
            l.classList.remove('text-white','fw-semibold');
            l.classList.add('text-secondary');
        }
    });
}

// ─── Leaflet Map Picker ─────────────────────────────────────
let map, marker;
let defLat = -6.200000, defLng = 106.816666;

function initMap() {
    map = L.map('mapPicker').setView([defLat, defLng], 13);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '© OpenStreetMap'
    }).addTo(map);
    marker = L.marker([defLat, defLng], { draggable: true }).addTo(map);
    setLatLng(defLat, defLng);
    marker.on('dragend', function () { setLatLng(marker.getLatLng().lat, marker.getLatLng().lng); });
    map.on('click', function (e) {
        marker.setLatLng(e.latlng);
        setLatLng(e.latlng.lat, e.latlng.lng);
    });
}

function setLatLng(lat, lng) {
    document.getElementById('lat').value = lat.toFixed(6);
    document.getElementById('lng').value = lng.toFixed(6);
    document.getElementById('lat_display').value = lat.toFixed(6);
    document.getElementById('lng_display').value = lng.toFixed(6);
}

function useMyLocation() {
    if (!navigator.geolocation) return alert('Browser tidak mendukung geolokasi.');
    navigator.geolocation.getCurrentPosition(pos => {
        const lt = pos.coords.latitude, ln = pos.coords.longitude;
        map.setView([lt, ln], 15);
        marker.setLatLng([lt, ln]);
        setLatLng(lt, ln);
    }, () => alert('Gagal mendapatkan lokasi. Pastikan izin lokasi diaktifkan.'));
}

// Init map when tab 2 becomes visible (lazy)
const origGoStep = goStep;
window.goStep = function(n) {
    origGoStep(n);
    if (n === 2 && !map) setTimeout(initMap, 100);
};
</script>
