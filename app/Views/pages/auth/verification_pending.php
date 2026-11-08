<section class="min-vh-100 py-5 d-flex align-items-center" style="background:linear-gradient(135deg,#0f172a 0%,#1e293b 100%);">
    <div class="container py-4">
        <div class="row justify-content-center">
            <div class="col-md-8 col-lg-6">

                <!-- Header -->
                <div class="text-center mb-4">
                    <a href="/" class="d-inline-flex align-items-center gap-2 text-decoration-none mb-4">
                        <div class="rounded-circle d-flex align-items-center justify-content-center" style="width:44px;height:44px;background:linear-gradient(135deg,#2563eb,#06b6d4);">
                            <i class="bi bi-water fs-5 text-white"></i>
                        </div>
                        <span class="fw-bold text-white">Marketplace<span style="color:#06b6d4">Laundry</span></span>
                    </a>
                </div>

                <!-- Card -->
                <div class="card border-0 rounded-4 shadow-lg text-center"
                     style="background:rgba(30,41,59,0.9);backdrop-filter:blur(16px);border:1px solid rgba(255,255,255,0.07) !important;">
                    <div class="card-body p-4 p-lg-5">

                        <!-- Status Icon -->
                        <?php
                        $status = $user['status'] ?? 'menunggu_verifikasi';
                        $role = strtolower($user['role'] ?? '');
                        ?>

                        <?php if ($status === 'menunggu_verifikasi'): ?>
                        <div class="mx-auto mb-4 rounded-circle d-flex align-items-center justify-content-center pending-pulse"
                             style="width:80px;height:80px;background:linear-gradient(135deg,#d97706,#f59e0b);">
                            <i class="bi bi-hourglass-split fs-2 text-white"></i>
                        </div>
                        <h2 class="fw-extrabold text-white mb-2">Menunggu Verifikasi</h2>
                        <p class="text-secondary mb-4" style="max-width:420px;margin:0 auto;">
                            Terima kasih sudah mendaftar! Tim Admin MarketplaceLaundry sedang meninjau data Anda.
                            Proses verifikasi biasanya selesai dalam <strong class="text-warning">1×24 jam</strong>.
                        </p>

                        <?php elseif ($status === 'ditolak'): ?>
                        <div class="mx-auto mb-4 rounded-circle d-flex align-items-center justify-content-center"
                             style="width:80px;height:80px;background:linear-gradient(135deg,#dc2626,#ef4444);">
                            <i class="bi bi-x-circle fs-2 text-white"></i>
                        </div>
                        <h2 class="fw-extrabold text-white mb-2">Pendaftaran Ditolak</h2>
                        <p class="text-secondary mb-3" style="max-width:420px;margin:0 auto;">
                            Maaf, pendaftaran Anda belum dapat disetujui saat ini.
                        </p>
                        <?php if (!empty($details['alasan_penolakan'])): ?>
                        <div class="alert text-start rounded-3 mb-4 small" style="background:rgba(239,68,68,0.15);border:1px solid rgba(239,68,68,0.3);color:#fca5a5;">
                            <strong><i class="bi bi-chat-square-text me-1"></i>Alasan:</strong><br>
                            <?= nl2br(htmlspecialchars($details['alasan_penolakan'])) ?>
                        </div>
                        <?php endif; ?>
                        <?php endif; ?>

                        <!-- Role-specific Info -->
                        <?php if ($role === 'owner' || $role === 'mitra'): ?>
                        <div class="row g-3 text-start mb-4">
                            <div class="col-12">
                                <div class="p-3 rounded-3" style="background:rgba(255,255,255,0.04);border:1px solid rgba(255,255,255,0.08)!important;">
                                    <div class="small text-secondary mb-2 fw-semibold text-uppercase" style="letter-spacing:.05em;">Rincian Pendaftaran Mitra</div>
                                    <div class="text-white fw-bold"><?= htmlspecialchars($details['nama_usaha'] ?? '-') ?></div>
                                    <div class="text-secondary small"><?= htmlspecialchars($details['alamat'] ?? '-') ?></div>
                                    <?php if (!empty($details['jam_operasional'])): ?>
                                    <div class="text-secondary small"><i class="bi bi-clock me-1"></i><?= htmlspecialchars($details['jam_operasional']) ?></div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <?php elseif ($role === 'driver' || $role === 'kurir'): ?>
                        <div class="row g-3 text-start mb-4">
                            <div class="col-12">
                                <div class="p-3 rounded-3" style="background:rgba(255,255,255,0.04);border:1px solid rgba(255,255,255,0.08)!important;">
                                    <div class="small text-secondary mb-2 fw-semibold text-uppercase" style="letter-spacing:.05em;">Rincian Pendaftaran Kurir</div>
                                    <div class="text-white fw-bold"><i class="bi bi-person me-1"></i><?= htmlspecialchars($user['name'] ?? '-') ?></div>
                                    <div class="text-secondary small"><i class="bi bi-bicycle me-1"></i><?= htmlspecialchars($details['jenis_kendaraan'] ?? '-') ?> — <?= htmlspecialchars($details['plat_nomor'] ?? '-') ?></div>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>

                        <!-- Timeline Steps -->
                        <?php if ($status === 'menunggu_verifikasi'): ?>
                        <div class="text-start mb-4">
                            <div class="d-flex align-items-start gap-3 mb-3">
                                <div class="rounded-circle d-flex align-items-center justify-content-center flex-shrink-0" style="width:32px;height:32px;background:#16a34a;margin-top:2px;">
                                    <i class="bi bi-check text-white small fw-bold"></i>
                                </div>
                                <div>
                                    <div class="text-white fw-semibold small">Data Pendaftaran Diterima</div>
                                    <div class="text-secondary" style="font-size:.8rem;">Formulir Anda telah berhasil tersimpan.</div>
                                </div>
                            </div>
                            <div class="d-flex align-items-start gap-3 mb-3">
                                <div class="rounded-circle d-flex align-items-center justify-content-center flex-shrink-0 pending-pulse" style="width:32px;height:32px;background:linear-gradient(135deg,#d97706,#f59e0b);margin-top:2px;">
                                    <i class="bi bi-search text-white small"></i>
                                </div>
                                <div>
                                    <div class="text-white fw-semibold small">Verifikasi Admin <span class="badge rounded-pill ms-1" style="background:rgba(245,158,11,.2);color:#fbbf24;font-size:.7rem;">Sedang Berlangsung</span></div>
                                    <div class="text-secondary" style="font-size:.8rem;">Tim kami meninjau dokumen dan data Anda.</div>
                                </div>
                            </div>
                            <div class="d-flex align-items-start gap-3">
                                <div class="rounded-circle d-flex align-items-center justify-content-center flex-shrink-0" style="width:32px;height:32px;background:rgba(255,255,255,0.1);margin-top:2px;">
                                    <i class="bi bi-rocket-takeoff text-secondary small"></i>
                                </div>
                                <div>
                                    <div class="text-secondary fw-semibold small">Akun Aktif — Mulai Beroperasi</div>
                                    <div class="text-secondary" style="font-size:.8rem;">Setelah disetujui, Anda langsung bisa mulai.</div>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>



                        <div class="d-grid gap-2">
                            <?php if ($status === 'ditolak'): ?>
                            <a href="/daftar" class="btn btn-primary rounded-pill fw-semibold">
                                <i class="bi bi-arrow-clockwise me-2"></i>Daftar Ulang dengan Data Baru
                            </a>
                            <?php endif; ?>
                            <form method="POST" action="/logout" class="w-100">
                                <input type="hidden" name="_token" value="<?= \App\Helpers\Session::csrfToken() ?>">
                                <button type="submit" class="btn btn-outline-secondary rounded-pill fw-semibold w-100">
                                    <i class="bi bi-box-arrow-right me-2"></i>Keluar dari Akun
                                </button>
                            </form>
                        </div>

                        <p class="text-muted small mt-4 mb-0">Ada pertanyaan? Hubungi tim kami di <a href="mailto:support@marketplacelaundry.id" class="text-primary text-decoration-none">support@marketplacelaundry.id</a></p>

                    </div>
                </div>

            </div>
        </div>
    </div>
</section>

<style>
@keyframes pulse-ring {
    0%   { box-shadow: 0 0 0 0 rgba(245,158,11,.5); }
    70%  { box-shadow: 0 0 0 14px rgba(245,158,11,0); }
    100% { box-shadow: 0 0 0 0 rgba(245,158,11,0); }
}
.pending-pulse { animation: pulse-ring 2s infinite; }
</style>
