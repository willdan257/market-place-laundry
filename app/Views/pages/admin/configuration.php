<section class="py-4 bg-light min-vh-100">
    <div class="container">
        <!-- Header -->
        <div class="card border-0 shadow-sm rounded-4 p-4 mb-4 bg-dark text-white">
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
                <div>
                    <span class="badge bg-danger rounded-pill px-3 py-1 mb-2 fw-bold"><i class="bi bi-shield-check me-1"></i>Area Administrator</span>
                    <h3 class="fw-bold mb-1">Konfigurasi Sistem &amp; Aplikasi</h3>
                    <p class="mb-0 text-white-50">Kelola preferensi umum aplikasi, integrasi model Chatbot AI, gerbang WhatsApp (Fonnte), dan Login Google.</p>
                </div>
                <div>
                    <a href="/dashboard" class="btn btn-outline-light rounded-pill px-3 py-2">
                        <i class="bi bi-speedometer2 me-1"></i>Kembali ke Dashboard
                    </a>
                </div>
            </div>
        </div>

        <!-- Flash Messages -->
        <?php if (\App\Helpers\Session::hasFlash('success')): ?>
            <div class="alert alert-success alert-dismissible fade show rounded-4 shadow-sm mb-4" role="alert">
                <i class="bi bi-check-circle-fill me-2 fs-5 align-middle"></i>
                <strong>Berhasil:</strong> <?= htmlspecialchars(\App\Helpers\Session::getFlash('success')) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>
        <?php if (\App\Helpers\Session::hasFlash('error')): ?>
            <div class="alert alert-danger alert-dismissible fade show rounded-4 shadow-sm mb-4" role="alert">
                <i class="bi bi-exclamation-triangle-fill me-2 fs-5 align-middle"></i>
                <strong>Gagal:</strong> <?= htmlspecialchars(\App\Helpers\Session::getFlash('error')) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <?php $currentTab = $activeTab ?? 'general'; ?>
        <!-- Navigation Tabs (Pill Style) -->
        <ul class="nav nav-pills mb-4 gap-2" id="configTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link <?= $currentTab === 'general' ? 'active' : '' ?> rounded-pill px-4 py-2 fw-semibold" id="tab-general-btn" data-bs-toggle="pill" data-bs-target="#tab-general" type="button" role="tab">
                    <i class="bi bi-sliders me-1"></i> Pengaturan Umum
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link <?= $currentTab === 'ai' ? 'active' : '' ?> rounded-pill px-4 py-2 fw-semibold" id="tab-ai-btn" data-bs-toggle="pill" data-bs-target="#tab-ai" type="button" role="tab">
                    <i class="bi bi-robot me-1"></i> Chatbot AI
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link <?= $currentTab === 'whatsapp' ? 'active' : '' ?> rounded-pill px-4 py-2 fw-semibold" id="tab-wa-btn" data-bs-toggle="pill" data-bs-target="#tab-wa" type="button" role="tab">
                    <i class="bi bi-whatsapp me-1"></i> Notifikasi WhatsApp
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link <?= $currentTab === 'google_auth' ? 'active' : '' ?> rounded-pill px-4 py-2 fw-semibold" id="tab-google-btn" data-bs-toggle="pill" data-bs-target="#tab-google" type="button" role="tab">
                    <i class="bi bi-google me-1"></i> Login Google
                </button>
            </li>
        </ul>

        <div class="tab-content" id="configTabsContent">
            <!-- TAB 1: PENGATURAN UMUM -->
            <div class="tab-pane fade <?= $currentTab === 'general' ? 'show active' : '' ?>" id="tab-general" role="tabpanel">
                <div class="card border-0 shadow-sm rounded-4 p-4">
                    <div class="d-flex align-items-center gap-2 mb-3 border-bottom pb-3">
                        <div class="bg-primary-subtle text-primary p-2 rounded-circle">
                            <i class="bi bi-gear-fill fs-5"></i>
                        </div>
                        <div>
                            <h5 class="fw-bold mb-0">Pengaturan Umum Aplikasi</h5>
                            <small class="text-muted">Informasi identitas marketplace dan konfigurasi sistem dasar</small>
                        </div>
                    </div>

                    <form method="POST" action="/admin/konfigurasi" enctype="multipart/form-data">
                        <input type="hidden" name="_token" value="<?= \App\Helpers\Session::csrfToken() ?>">
                        <input type="hidden" name="section" value="general">

                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Nama Aplikasi <span class="text-danger">*</span></label>
                                <input type="text" name="app_name" class="form-control rounded-3" value="<?= htmlspecialchars($settings['app_name'] ?? 'Marketplace Laundry') ?>" required>
                                <small class="text-muted">Nama platform yang tampil pada judul halaman dan navbar.</small>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Nama Usaha / Perusahaan <span class="text-danger">*</span></label>
                                <input type="text" name="business_name" class="form-control rounded-3" value="<?= htmlspecialchars($settings['business_name'] ?? 'PT Marketplace Laundry Indonesia') ?>" required>
                                <small class="text-muted">Nama entitas pengelola resmi untuk faktur &amp; footer.</small>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Email Kontak Resmi <span class="text-danger">*</span></label>
                                <input type="email" name="contact_email" class="form-control rounded-3" value="<?= htmlspecialchars($settings['contact_email'] ?? 'admin@marketplacelaundry.com') ?>" required>
                                <small class="text-muted">Email untuk bantuan pelanggan dan korespondensi.</small>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Zona Waktu Sistem <span class="text-danger">*</span></label>
                                <select name="timezone" class="form-select rounded-3">
                                    <?php
                                        $tz = $settings['timezone'] ?? 'Asia/Jakarta';
                                        $timezones = [
                                            'Asia/Jakarta'  => 'Asia/Jakarta (WIB, UTC+7)',
                                            'Asia/Makassar' => 'Asia/Makassar (WITA, UTC+8)',
                                            'Asia/Jayapura' => 'Asia/Jayapura (WIT, UTC+9)',
                                        ];
                                        foreach ($timezones as $tzVal => $tzLabel):
                                    ?>
                                        <option value="<?= $tzVal ?>" <?= $tz === $tzVal ? 'selected' : '' ?>><?= $tzLabel ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <small class="text-muted">Zona waktu operasional pesanan dan laporan transaksi.</small>
                            </div>

                            <div class="col-12">
                                <label class="form-label fw-semibold">Logo Aplikasi (JPG, PNG, WEBP, maks 2 MB)</label>
                                <div class="d-flex align-items-center gap-3">
                                    <?php if (!empty($settings['logo_path'])): ?>
                                        <div class="p-2 border rounded-3 bg-light">
                                            <img src="<?= htmlspecialchars($settings['logo_path']) ?>" alt="Logo Aplikasi" style="max-height: 50px; max-width: 120px; object-fit: contain;">
                                        </div>
                                    <?php endif; ?>
                                    <input type="file" name="logo" class="form-control rounded-3" accept="image/png,image/jpeg,image/webp">
                                </div>
                                <small class="text-muted">Biarkan kosong jika tidak ingin mengubah logo yang ada.</small>
                            </div>
                        </div>

                        <div class="mt-4 pt-3 border-top d-flex justify-content-end">
                            <button type="submit" class="btn btn-primary rounded-pill px-4 fw-semibold shadow-sm">
                                <i class="bi bi-save me-1"></i> Simpan Pengaturan Umum
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- TAB 2: CHATBOT AI -->
            <div class="tab-pane fade <?= $currentTab === 'ai' ? 'show active' : '' ?>" id="tab-ai" role="tabpanel">
                <div class="card border-0 shadow-sm rounded-4 p-4">
                    <div class="d-flex align-items-center justify-content-between mb-3 border-bottom pb-3">
                        <div class="d-flex align-items-center gap-2">
                            <div class="bg-info-subtle text-info p-2 rounded-circle">
                                <i class="bi bi-robot fs-5"></i>
                            </div>
                            <div>
                                <h5 class="fw-bold mb-0">Integrasi Asisten Chatbot AI</h5>
                                <small class="text-muted">Konfigurasi penyedia LLM untuk asisten cerdas pelanggan, mitra, dan admin</small>
                            </div>
                        </div>
                        <div>
                            <button type="button" id="btnTestAi" class="btn btn-outline-info rounded-pill px-3 fw-semibold">
                                <i class="bi bi-plug me-1"></i> Uji Koneksi
                            </button>
                        </div>
                    </div>

                    <div id="aiTestAlert" class="alert d-none mb-3" role="alert"></div>

                    <form method="POST" action="/admin/konfigurasi">
                        <input type="hidden" name="_token" value="<?= \App\Helpers\Session::csrfToken() ?>">
                        <input type="hidden" name="section" value="ai">

                        <div class="row g-3">
                            <div class="col-12">
                                <div class="form-check form-switch fs-5">
                                    <input class="form-check-input" type="checkbox" role="switch" id="aiEnabledSwitch" name="ai_enabled" value="1" <?= (!empty($settings['ai_enabled']) && $settings['ai_enabled'] === '1') ? 'checked' : '' ?>>
                                    <label class="form-check-label fs-6 fw-semibold" for="aiEnabledSwitch">Aktifkan Layanan Chatbot AI</label>
                                </div>
                                <small class="text-muted">Jika dinonaktifkan, widget chatbot akan menampilkan status offline yang rapi tanpa menimbulkan galat aplikasi.</small>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label fw-semibold">AI Provider</label>
                                <select name="ai_provider" class="form-select rounded-3">
                                    <?php $provider = $settings['ai_provider'] ?? 'gemini'; ?>
                                    <option value="gemini" <?= $provider === 'gemini' ? 'selected' : '' ?>>Google Gemini (Direkomendasikan)</option>
                                    <option value="nous" <?= $provider === 'nous' ? 'selected' : '' ?>>Nous Research</option>
                                    <option value="anthropic" <?= $provider === 'anthropic' ? 'selected' : '' ?>>Anthropic (Claude)</option>
                                </select>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Model AI</label>
                                <input type="text" name="ai_model" class="form-control rounded-3" value="<?= htmlspecialchars($settings['ai_model'] ?? 'gemini-1.5-flash') ?>" placeholder="Contoh: gemini-1.5-flash">
                                <small class="text-muted">Contoh: <code>gemini-1.5-flash</code>, <code>anthropic/claude-haiku-5.5</code>, <code>claude-haiku-5-5</code>.</small>
                            </div>

                            <div class="col-12">
                                <label class="form-label fw-semibold">API Key</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-white"><i class="bi bi-key text-muted"></i></span>
                                    <input type="password" name="ai_api_key" class="form-control" placeholder="<?= !empty($maskedAiKey) ? $maskedAiKey : 'Masukkan Google AI API Key...' ?>" autocomplete="new-password">
                                </div>
                                <small class="text-muted">
                                    <?php if (!empty($maskedAiKey)): ?>
                                        <span class="text-success fw-semibold"><i class="bi bi-shield-lock-fill me-1"></i>Tersimpan terenkripsi: <?= htmlspecialchars($maskedAiKey) ?></span>. Biarkan kosong jika tidak ingin mengubah kunci.
                                    <?php else: ?>
                                        Disimpan terenkripsi di database dengan AES-256-CBC.
                                    <?php endif; ?>
                                </small>
                            </div>
                        </div>

                        <div class="mt-4 pt-3 border-top d-flex justify-content-end">
                            <button type="submit" class="btn btn-primary rounded-pill px-4 fw-semibold shadow-sm">
                                <i class="bi bi-save me-1"></i> Simpan Pengaturan AI
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- TAB 3: NOTIFIKASI WHATSAPP -->
            <div class="tab-pane fade <?= $currentTab === 'whatsapp' ? 'show active' : '' ?>" id="tab-wa" role="tabpanel">
                <div class="card border-0 shadow-sm rounded-4 p-4">
                    <div class="d-flex align-items-center justify-content-between mb-3 border-bottom pb-3">
                        <div class="d-flex align-items-center gap-2">
                            <div class="bg-success-subtle text-success p-2 rounded-circle">
                                <i class="bi bi-whatsapp fs-5"></i>
                            </div>
                            <div>
                                <h5 class="fw-bold mb-0">Gerbang Notifikasi WhatsApp (Fonnte API)</h5>
                                <small class="text-muted">Pengiriman pesan otomatis notifikasi pesanan dan verifikasi ke nomor pelanggan</small>
                            </div>
                        </div>
                        <div>
                            <button type="button" id="btnTestWa" class="btn btn-outline-success rounded-pill px-3 fw-semibold">
                                <i class="bi bi-send me-1"></i> Kirim Pesan Tes
                            </button>
                        </div>
                    </div>

                    <div id="waTestAlert" class="alert d-none mb-3" role="alert"></div>

                    <form method="POST" action="/admin/konfigurasi">
                        <input type="hidden" name="_token" value="<?= \App\Helpers\Session::csrfToken() ?>">
                        <input type="hidden" name="section" value="whatsapp">

                        <div class="row g-3">
                            <div class="col-12">
                                <div class="form-check form-switch fs-5">
                                    <input class="form-check-input" type="checkbox" role="switch" id="waEnabledSwitch" name="wa_enabled" value="1" <?= (!empty($settings['wa_enabled']) && $settings['wa_enabled'] === '1') ? 'checked' : '' ?>>
                                    <label class="form-check-label fs-6 fw-semibold" for="waEnabledSwitch">Aktifkan Notifikasi WhatsApp</label>
                                </div>
                                <small class="text-muted">Jika dinonaktifkan, aplikasi tidak akan mencoba mengirim pesan WhatsApp ke Fonnte.</small>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Token Fonnte</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-white"><i class="bi bi-shield-lock text-muted"></i></span>
                                    <input type="password" name="wa_token" class="form-control" placeholder="<?= !empty($maskedWaToken) ? $maskedWaToken : 'Masukkan Token Fonnte...' ?>" autocomplete="new-password">
                                </div>
                                <small class="text-muted">
                                    <?php if (!empty($maskedWaToken)): ?>
                                        <span class="text-success fw-semibold"><i class="bi bi-shield-lock-fill me-1"></i>Tersimpan terenkripsi: <?= htmlspecialchars($maskedWaToken) ?></span>. Biarkan kosong jika tidak ingin mengubah token.
                                    <?php else: ?>
                                        Token akun WhatsApp gateway dari dashboard Fonnte (disimpan terenkripsi).
                                    <?php endif; ?>
                                </small>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Nomor WhatsApp Admin</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-white"><i class="bi bi-telephone text-muted"></i></span>
                                    <input type="text" name="wa_admin_phone" class="form-control rounded-end-3" value="<?= htmlspecialchars($settings['wa_admin_phone'] ?? '') ?>" placeholder="Contoh: 08123456789 atau 628123456789">
                                </div>
                                <small class="text-muted">Nomor yang akan menerima pesan tes dan peringatan sistem.</small>
                            </div>

                            <div class="col-12">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" id="waNotifyOrder" name="wa_notify_order_status" value="1" <?= (!empty($settings['wa_notify_order_status']) && $settings['wa_notify_order_status'] === '1') ? 'checked' : '' ?>>
                                    <label class="form-check-label fw-semibold" for="waNotifyOrder">Notifikasi status pesanan berubah</label>
                                </div>
                                <small class="text-muted">Kirim pesan WhatsApp otomatis ke pelanggan saat status pesanan diperbarui (mis. Sedang Dicuci, Selesai, Diantar).</small>
                            </div>
                        </div>

                        <div class="mt-4 pt-3 border-top d-flex justify-content-end">
                            <button type="submit" class="btn btn-primary rounded-pill px-4 fw-semibold shadow-sm">
                                <i class="bi bi-save me-1"></i> Simpan Pengaturan WhatsApp
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            <!-- TAB 4: LOGIN GOOGLE (OAUTH 2.0) -->
            <div class="tab-pane fade <?= $currentTab === 'google_auth' ? 'show active' : '' ?>" id="tab-google" role="tabpanel">
                <div class="card border-0 shadow-sm rounded-4 p-4">
                    <div class="d-flex align-items-center gap-2 mb-3 border-bottom pb-3">
                        <div class="p-2 rounded-circle text-danger" style="background:#fee2e2;">
                            <i class="bi bi-google fs-5"></i>
                        </div>
                        <div>
                            <h5 class="fw-bold mb-0">Konfigurasi Login Google (OAuth 2.0)</h5>
                            <small class="text-muted">Integrasi kredensial Google Console untuk tombol masuk akun pelanggan</small>
                        </div>
                    </div>

                    <form method="POST" action="/admin/konfigurasi">
                        <input type="hidden" name="_token" value="<?= \App\Helpers\Session::csrfToken() ?>">
                        <input type="hidden" name="section" value="google_auth">

                        <!-- Toggle Aktifkan Google Auth -->
                        <div class="card bg-light border-0 rounded-3 p-3 mb-4">
                            <div class="form-check form-switch mb-0">
                                <input type="hidden" name="google_auth_enabled" value="0">
                                <input class="form-check-input fs-5" type="checkbox" role="switch" id="google_auth_enabled" name="google_auth_enabled" value="1" <?= (($settings['google_auth_enabled'] ?? '0') === '1') ? 'checked' : '' ?>>
                                <label class="form-check-label fw-bold ms-2" for="google_auth_enabled">
                                    Aktifkan Tombol "Masuk dengan Google"
                                </label>
                                <div class="text-muted small ms-2">Bila dimatikan atau kredensial kosong, tombol Google di halaman login akan disembunyikan. Khusus akun pelanggan.</div>
                            </div>
                        </div>

                        <div class="row g-3">
                            <div class="col-12">
                                <label class="form-label fw-semibold">Google Client ID</label>
                                <input type="text" name="google_client_id" class="form-control rounded-3 font-monospace"
                                       value="<?= htmlspecialchars($settings['google_client_id'] ?? '') ?>"
                                       placeholder="xxxxxxxxxxxx-xxxxxxxxxxxxxxxx.apps.googleusercontent.com">
                                <small class="text-muted">Client ID OAuth 2.0 dari Google Cloud Console.</small>
                            </div>

                            <div class="col-12">
                                <label class="form-label fw-semibold">Google Client Secret</label>
                                <?php if (!empty($maskedGoogleSecret)): ?>
                                    <div class="input-group mb-2">
                                        <span class="input-group-text bg-white text-muted small"><i class="bi bi-lock-fill me-1"></i>Tersimpan:</span>
                                        <input type="text" class="form-control bg-light text-muted font-monospace" value="<?= htmlspecialchars($maskedGoogleSecret) ?>" disabled readonly>
                                    </div>
                                <?php endif; ?>
                                <input type="password" name="google_client_secret" class="form-control rounded-3"
                                       placeholder="<?= !empty($maskedGoogleSecret) ? 'Kosongkan jika tidak ingin mengubah Secret yang tersimpan' : 'GOCSPX-xxxxxxxxxxxxxxxxxxxxxxxx' ?>"
                                       autocomplete="new-password">
                                <small class="text-muted">Client Secret tersimpan terenkripsi (AES-256) di database dan tidak pernah dikirim balik ke browser.</small>
                            </div>

                            <div class="col-12">
                                <label class="form-label fw-semibold text-secondary">Authorized Redirect URI (Wajib didaftarkan di Google Console)</label>
                                <?php
                                $appUrl = rtrim((string) \App\Helpers\Env::get('APP_URL', 'http://localhost:8000'), '/');
                                $redirectUri = $appUrl . '/auth/google/callback';
                                ?>
                                <div class="input-group">
                                    <input type="text" id="googleRedirectUriInput" class="form-control bg-light text-muted font-monospace"
                                           value="<?= htmlspecialchars($redirectUri) ?>" readonly>
                                    <button class="btn btn-outline-secondary" type="button" id="btnCopyRedirectUri" onclick="copyRedirectUri()">
                                        <i class="bi bi-clipboard me-1"></i> Salin URI
                                    </button>
                                </div>
                                <div id="copyAlert" class="text-success small mt-1 d-none"><i class="bi bi-check-circle-fill me-1"></i>URI berhasil disalin ke clipboard!</div>
                                <small class="text-muted d-block mt-1">Salin URL di atas ke daftar <em>Authorized redirect URIs</em> pada kredensial Google Cloud Console Anda.</small>
                            </div>
                        </div>

                        <div class="d-flex justify-content-end mt-4">
                            <button type="submit" class="btn btn-primary rounded-pill px-4 py-2 fw-semibold">
                                <i class="bi bi-save me-1"></i> Simpan Pengaturan Google
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Client JavaScript for AJAX test buttons -->
<script>
document.addEventListener('DOMContentLoaded', function () {
    const csrfToken = "<?= \App\Helpers\Session::csrfToken() ?>";

    // AI Connection Test
    const btnTestAi = document.getElementById('btnTestAi');
    const aiTestAlert = document.getElementById('aiTestAlert');
    if (btnTestAi) {
        btnTestAi.addEventListener('click', function () {
            btnTestAi.disabled = true;
            btnTestAi.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status"></span> Menguji...';
            aiTestAlert.className = 'alert d-none';

            fetch('/admin/konfigurasi/test-ai', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: '_token=' + encodeURIComponent(csrfToken)
            })
            .then(res => res.json())
            .then(data => {
                aiTestAlert.className = data.success ? 'alert alert-success' : 'alert alert-danger';
                aiTestAlert.innerHTML = '<i class="bi ' + (data.success ? 'bi-check-circle-fill' : 'bi-exclamation-triangle-fill') + ' me-2"></i>' + (data.message || 'Hasil uji selesai.');
            })
            .catch(err => {
                aiTestAlert.className = 'alert alert-danger';
                aiTestAlert.innerHTML = '<i class="bi bi-exclamation-triangle-fill me-2"></i>Gagal menghubungi server: ' + err.message;
            })
            .finally(() => {
                btnTestAi.disabled = false;
                btnTestAi.innerHTML = '<i class="bi bi-plug me-1"></i> Uji Koneksi';
            });
        });
    }

    // WA Test Message
    const btnTestWa = document.getElementById('btnTestWa');
    const waTestAlert = document.getElementById('waTestAlert');
    if (btnTestWa) {
        btnTestWa.addEventListener('click', function () {
            btnTestWa.disabled = true;
            btnTestWa.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status"></span> Mengirim...';
            waTestAlert.className = 'alert d-none';

            fetch('/admin/konfigurasi/test-wa', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: '_token=' + encodeURIComponent(csrfToken)
            })
            .then(res => res.json())
            .then(data => {
                waTestAlert.className = data.success ? 'alert alert-success' : 'alert alert-danger';
                waTestAlert.innerHTML = '<i class="bi ' + (data.success ? 'bi-check-circle-fill' : 'bi-exclamation-triangle-fill') + ' me-2"></i>' + (data.message || 'Hasil uji selesai.');
            })
            .catch(err => {
                waTestAlert.className = 'alert alert-danger';
                waTestAlert.innerHTML = '<i class="bi bi-exclamation-triangle-fill me-2"></i>Gagal menghubungi server: ' + err.message;
            })
            .finally(() => {
                btnTestWa.disabled = false;
                btnTestWa.innerHTML = '<i class="bi bi-send me-1"></i> Kirim Pesan Tes';
            });
        });
    }
});

function copyRedirectUri() {
    const input = document.getElementById('googleRedirectUriInput');
    const alertBox = document.getElementById('copyAlert');
    if (input) {
        navigator.clipboard.writeText(input.value).then(() => {
            alertBox.classList.remove('d-none');
            setTimeout(() => {
                alertBox.classList.add('d-none');
            }, 3000);
        }).catch(err => {
            input.select();
            document.execCommand('copy');
            alertBox.classList.remove('d-none');
            setTimeout(() => {
                alertBox.classList.add('d-none');
            }, 3000);
        });
    }
}
</script>

