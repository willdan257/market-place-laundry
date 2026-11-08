<section class="py-5 bg-light">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-9 col-lg-7">

                <?php if (!empty($mapsError)): ?>
                    <!-- HARD STOP: Google Maps API Error - Cannot proceed -->
                    <div class="card border-0 shadow-lg rounded-4 overflow-hidden">
                        <div class="card-header bg-danger text-white p-4">
                            <div class="d-flex align-items-center gap-3">
                                <div class="bg-white text-danger rounded-circle p-2 d-flex align-items-center justify-content-center" style="width:50px;height:50px;">
                                    <i class="bi bi-exclamation-triangle-fill fs-3"></i>
                                </div>
                                <div>
                                    <h4 class="fw-bold mb-0">Gagal Menghitung Ongkir</h4>
                                    <p class="mb-0 small text-white-50">Pesanan tidak dapat dilanjutkan</p>
                                </div>
                            </div>
                        </div>
                        <div class="card-body p-4">
                            <div class="alert alert-danger rounded-3 mb-4">
                                <h6 class="fw-bold mb-2"><i class="bi bi-geo-alt-fill me-2"></i>Google Maps API Error</h6>
                                <p class="mb-0 small"><?= htmlspecialchars($mapsError) ?></p>
                            </div>
                            <div class="alert alert-warning rounded-3 mb-4 small">
                                <i class="bi bi-info-circle me-1"></i>
                                <strong>Kenapa ini terjadi?</strong> Sistem kami menghitung ongkir berdasarkan jarak aktual via Google Maps. Tanpa kalkulasi ini, ongkir tidak dapat ditentukan dan pesanan tidak bisa diproses — untuk mencegah biaya yang tidak akurat.
                            </div>
                            <div class="bg-light rounded-3 border p-3 mb-4">
                                <p class="text-muted small mb-1 fw-semibold">Kemungkinan penyebab:</p>
                                <ul class="text-muted small mb-0 ps-3">
                                    <li>API Key Google Maps belum dikonfigurasi di file <code>.env</code></li>
                                    <li>Koordinat latitude/longitude belum valid (nilai 0)</li>
                                    <li>Koneksi internet server tidak tersedia</li>
                                    <li>Batas kuota API Google Maps tercapai</li>
                                </ul>
                            </div>
                            <div class="d-flex gap-3">
                                <a href="/customer/orders/create" class="btn btn-outline-secondary rounded-pill px-4 fw-medium">
                                    <i class="bi bi-arrow-left me-1"></i> Kembali & Coba Lagi
                                </a>
                                <?php if (defined('APP_ENV') || (function_exists('getenv') && getenv('APP_ENV') === 'local')): ?>
                                <a href="/customer/dashboard" class="btn btn-secondary rounded-pill px-4 fw-medium">
                                    Dashboard
                                </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                <?php else: ?>
                    <!-- SUCCESS: Google Maps API returned distance and fee -->
                    <div class="card border-0 shadow-lg rounded-4 overflow-hidden">
                        <div class="card-header bg-dark text-white p-4">
                            <div class="d-flex align-items-center gap-3">
                                <div class="bg-warning text-dark rounded-circle p-3 d-flex align-items-center justify-content-center" style="width: 50px; height: 50px;">
                                    <i class="bi bi-file-earmark-check-fill fs-3"></i>
                                </div>
                                <div>
                                    <h4 class="fw-bold mb-0">Konfirmasi Pesanan Laundry</h4>
                                    <p class="mb-0 small text-white-50">Periksa kembali rincian pesanan Anda sebelum memproses</p>
                                </div>
                            </div>
                        </div>

                        <div class="card-body p-4 p-lg-5">
                            <div class="alert alert-info py-2 small mb-4">
                                <i class="bi bi-info-circle-fill me-2"></i>
                                Berat laundry akan ditimbang oleh pihak toko setelah penjemputan. Total biaya akhir akan diperbarui berdasarkan hasil penimbangan.
                            </div>

                            <!-- Layanan -->
                            <div class="p-3 bg-light rounded-3 border mb-4">
                                <span class="text-muted small fw-semibold text-uppercase d-block mb-1">Pilihan Layanan Laundry</span>
                                <h5 class="fw-bold text-primary mb-1"><?= htmlspecialchars($service['service_name']) ?></h5>
                                <p class="text-muted small mb-2"><?= htmlspecialchars($service['description'] ?? '') ?></p>
                                <div class="d-flex justify-content-between align-items-center pt-2 border-top">
                                    <span class="small text-secondary">Toko:</span>
                                    <strong class="text-dark"><?= htmlspecialchars($service['store_name']) ?></strong>
                                </div>
                                <div class="d-flex justify-content-between align-items-center mt-1">
                                    <span class="small text-secondary">Tarif Satuan:</span>
                                    <strong class="text-success">Rp <?= number_format($service['price_per_unit'], 0, ',', '.') ?> / <?= htmlspecialchars($service['unit']) ?></strong>
                                </div>
                                <div class="d-flex justify-content-between align-items-center mt-1">
                                    <span class="small text-secondary">Estimasi Pengerjaan:</span>
                                    <strong><?= $service['estimated_duration_hours'] ?> Jam</strong>
                                </div>
                            </div>

                            <!-- Alamat Pickup -->
                            <div class="p-3 bg-light rounded-3 border mb-4">
                                <span class="text-muted small fw-semibold text-uppercase d-block mb-1">Lokasi Penjemputan (Pickup)</span>
                                <p class="fw-bold text-dark mb-1"><?= htmlspecialchars($user['name']) ?> (<?= htmlspecialchars($postData['recipient_phone']) ?>)</p>
                                <p class="text-secondary small mb-2"><?= htmlspecialchars($postData['full_address']) ?></p>
                                <div class="d-flex flex-wrap gap-3 text-muted small">
                                    <span>RT: <strong><?= htmlspecialchars($postData['rt']) ?></strong></span>
                                    <span>RW: <strong><?= htmlspecialchars($postData['rw']) ?></strong></span>
                                    <?php if (!empty($postData['district'])): ?>
                                        <span>Kec: <strong><?= htmlspecialchars($postData['district']) ?></strong></span>
                                    <?php endif; ?>
                                    <?php if (!empty($postData['city'])): ?>
                                        <span>Kota: <strong><?= htmlspecialchars($postData['city']) ?></strong></span>
                                    <?php endif; ?>
                                </div>
                                <div class="mt-2 pt-2 border-top small text-muted">
                                    <i class="bi bi-pin-map-fill text-danger me-1"></i>
                                    Koordinat: <code><?= number_format($postData['latitude'], 6) ?>, <?= number_format($postData['longitude'], 6) ?></code>
                                </div>
                            </div>

                            <!-- ✅ Google Maps Distance & Ongkir -->
                            <div class="p-3 rounded-3 border mb-4" style="background: linear-gradient(135deg, #f0fdf4, #dcfce7); border-color: #86efac !important;">
                                <span class="text-success small fw-bold text-uppercase d-block mb-2">
                                    <i class="bi bi-google me-1"></i>Kalkulasi Jarak & Ongkir (Google Maps)
                                </span>
                                <div class="row g-2">
                                    <div class="col-6">
                                        <div class="bg-white rounded-3 p-3 text-center border">
                                            <div class="text-muted small mb-1">Jarak Tempuh</div>
                                            <div class="fw-bold fs-4 text-primary"><?= number_format($distanceKm, 1) ?> km</div>
                                            <div class="text-muted" style="font-size:0.7rem;">via Google Maps API (Driving)</div>
                                        </div>
                                    </div>
                                    <div class="col-6">
                                        <div class="bg-white rounded-3 p-3 text-center border">
                                            <div class="text-muted small mb-1">Ongkir</div>
                                            <div class="fw-bold fs-4 text-success">Rp <?= number_format($shippingFee, 0, ',', '.') ?></div>
                                            <div class="text-muted" style="font-size:0.7rem;">Tarif berdasarkan jarak aktual</div>
                                        </div>
                                    </div>
                                </div>
                                <div class="mt-2 small text-muted d-flex align-items-center gap-2">
                                    <i class="bi bi-check-circle-fill text-success"></i>
                                    Ongkir dihitung otomatis dari koordinat Anda ke toko laundry — tidak ada biaya tersembunyi.
                                </div>
                            </div>

                            <!-- Catatan -->
                            <?php if (!empty($postData['notes'])): ?>
                                <div class="p-3 bg-warning-subtle rounded-3 border mb-4">
                                    <span class="text-muted small fw-semibold d-block mb-1">Catatan Pesanan:</span>
                                    <p class="text-dark small mb-0 fst-italic">"<?= htmlspecialchars($postData['notes']) ?>"</p>
                                </div>
                            <?php endif; ?>

                            <!-- Ringkasan Biaya -->
                            <div class="p-3 bg-dark text-white rounded-3 mb-4">
                                <div class="d-flex justify-content-between mb-1 small">
                                    <span class="text-white-50">Subtotal Layanan:</span>
                                    <span>Rp <?= number_format($service['price_per_unit'], 0, ',', '.') ?> (estimasi, belum timbang)</span>
                                </div>
                                <div class="d-flex justify-content-between mb-2 small">
                                    <span class="text-white-50">Ongkir (<?= number_format($distanceKm, 1) ?> km):</span>
                                    <span class="text-success fw-bold">Rp <?= number_format($shippingFee, 0, ',', '.') ?></span>
                                </div>
                                <div class="border-top border-secondary pt-2 d-flex justify-content-between">
                                    <span class="fw-bold">Total Sementara:</span>
                                    <span class="fw-bold text-warning fs-5">Rp <?= number_format($service['price_per_unit'] + $shippingFee, 0, ',', '.') ?>*</span>
                                </div>
                                <p class="text-white-50 mb-0 mt-1" style="font-size:0.7rem;">*Total akhir akan diperbarui setelah penimbangan oleh pihak laundry.</p>
                            </div>

                            <!-- Form Submit -->
                            <form method="POST" action="/customer/orders/store">
                                <input type="hidden" name="_token" value="<?= \App\Helpers\Session::csrfToken() ?>">
                                <input type="hidden" name="store_id" value="<?= htmlspecialchars($postData['store_id'] ?? $service['store_id']) ?>">
                                <input type="hidden" name="service_id" value="<?= $postData['service_id'] ?>">
                                <input type="hidden" name="recipient_phone" value="<?= htmlspecialchars($postData['recipient_phone']) ?>">
                                <input type="hidden" name="full_address" value="<?= htmlspecialchars($postData['full_address']) ?>">
                                <input type="hidden" name="rt" value="<?= htmlspecialchars($postData['rt']) ?>">
                                <input type="hidden" name="rw" value="<?= htmlspecialchars($postData['rw']) ?>">
                                <input type="hidden" name="district" value="<?= htmlspecialchars($postData['district']) ?>">
                                <input type="hidden" name="city" value="<?= htmlspecialchars($postData['city']) ?>">
                                <input type="hidden" name="latitude" value="<?= htmlspecialchars($postData['latitude']) ?>">
                                <input type="hidden" name="longitude" value="<?= htmlspecialchars($postData['longitude']) ?>">
                                <input type="hidden" name="notes" value="<?= htmlspecialchars($postData['notes']) ?>">
                                <input type="hidden" name="distance_km" value="<?= htmlspecialchars($postData['distance_km']) ?>">
                                <input type="hidden" name="shipping_fee" value="<?= htmlspecialchars($postData['shipping_fee']) ?>">

                                <div class="d-flex justify-content-between align-items-center pt-3 border-top">
                                    <a href="/customer/orders/create?store_id=<?= htmlspecialchars($postData['store_id'] ?? $service['store_id']) ?>" class="btn btn-outline-secondary rounded-pill px-4 fw-medium">
                                        <i class="bi bi-pencil me-1"></i> Ubah Data
                                    </a>
                                    <button type="submit" class="btn btn-success rounded-pill px-5 py-2 fw-bold shadow-lg">
                                        <i class="bi bi-check2-circle me-1"></i> Konfirmasi & Buat Order
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                <?php endif; ?>

            </div>
        </div>
    </div>
</section>
