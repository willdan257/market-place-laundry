<section class="py-4 bg-light min-vh-100">
    <div class="container">
        <!-- Header -->
        <div class="card border-0 shadow-sm rounded-4 p-4 mb-4 bg-dark text-white">
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
                <div>
                    <span class="badge bg-danger rounded-pill px-3 py-1 mb-2 fw-bold"><i class="bi bi-shield-check me-1"></i>ADMIN CONSOLE</span>
                    <h3 class="fw-bold mb-1">Verifikasi Pendaftaran Mitra &amp; Kurir</h3>
                    <p class="mb-0 text-white-50">Tinjau dokumen legalitas, setujui atau tolak pendaftaran baru mitra laundry dan kurir armada.</p>
                </div>
                <div class="d-flex flex-wrap gap-2 align-items-center">
                    <span class="badge bg-warning text-dark fs-6 px-3 py-2 rounded-pill d-flex align-items-center">
                        <i class="bi bi-hourglass-split me-1"></i> <?= $pendingPartnersCount ?> Mitra Menunggu
                    </span>
                    <span class="badge bg-info text-dark fs-6 px-3 py-2 rounded-pill d-flex align-items-center">
                        <i class="bi bi-bicycle me-1"></i> <?= $pendingCouriersCount ?> Kurir Menunggu
                    </span>
                    <a href="/admin/chats" class="btn btn-outline-light rounded-pill px-3 py-2">
                        <i class="bi bi-chat-dots me-1"></i>Riwayat Chat
                    </a>
                </div>
            </div>
        </div>

        <!-- Navigation Tabs -->
        <ul class="nav nav-pills mb-4 gap-2" id="verifTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link <?= ($activeTab ?? 'partners') === 'partners' ? 'active' : '' ?> rounded-pill px-4 fw-semibold" id="partners-tab" data-bs-toggle="pill" data-bs-target="#tab-partners" type="button" role="tab">
                    <i class="bi bi-shop me-1"></i> Mitra Laundry (<?= count($partners) ?>)
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link <?= ($activeTab ?? 'partners') === 'couriers' ? 'active' : '' ?> rounded-pill px-4 fw-semibold" id="couriers-tab" data-bs-toggle="pill" data-bs-target="#tab-couriers" type="button" role="tab">
                    <i class="bi bi-bicycle me-1"></i> Kurir Armada (<?= count($couriers) ?>)
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link <?= ($activeTab ?? 'partners') === 'customers' ? 'active' : '' ?> rounded-pill px-4 fw-semibold" id="customers-tab" data-bs-toggle="pill" data-bs-target="#tab-customers" type="button" role="tab">
                    <i class="bi bi-people me-1"></i> Customer (<?= $totalCustomersCount ?? 0 ?>)
                </button>
            </li>
        </ul>

        <div class="tab-content" id="verifTabsContent">
            <!-- TAB 1: MITRA LAUNDRY -->
            <div class="tab-pane fade <?= ($activeTab ?? 'partners') === 'partners' ? 'show active' : '' ?>" id="tab-partners" role="tabpanel">
                <div class="card border-0 shadow-sm rounded-4 p-4">
                    <h5 class="fw-bold mb-3"><i class="bi bi-shop text-primary me-2"></i>Daftar Calon Mitra Laundry</h5>
                    <?php if (empty($partners)): ?>
                        <div class="alert alert-secondary text-center py-4 rounded-3">Belum ada permohonan pendaftaran mitra.</div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Pemilik &amp; Kontak</th>
                                        <th>Nama Usaha &amp; Alamat</th>
                                        <th>Jam Operasional</th>
                                        <th>Dokumen</th>
                                        <th>Status</th>
                                        <th class="text-end">Tindakan Admin</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($partners as $p): ?>
                                    <tr>
                                        <td>
                                            <div class="fw-bold"><?= htmlspecialchars($p['name']) ?></div>
                                            <div class="small text-muted"><?= htmlspecialchars($p['email']) ?></div>
                                            <div class="small text-muted"><i class="bi bi-whatsapp text-success me-1"></i><?= htmlspecialchars($p['phone'] ?? '-') ?></div>
                                        </td>
                                        <td>
                                            <div class="fw-semibold text-primary"><?= htmlspecialchars($p['nama_usaha']) ?></div>
                                            <div class="small text-secondary" style="max-width: 250px;"><?= htmlspecialchars($p['alamat']) ?></div>
                                        </td>
                                        <td><span class="badge bg-light text-dark border"><?= htmlspecialchars($p['jam_operasional'] ?? '08:00 - 20:00') ?></span></td>
                                        <td>
                                            <?php if (!empty($p['dokumen'])): ?>
                                                <?php $pDocUrl = str_starts_with($p['dokumen'], 'http') ? $p['dokumen'] : '/' . ltrim($p['dokumen'], '/'); ?>
                                                <a href="<?= htmlspecialchars($pDocUrl) ?>" target="_blank" class="btn btn-sm btn-outline-primary rounded-pill">
                                                    <i class="bi bi-file-earmark-text me-1"></i>Lihat Dokumen
                                                </a>
                                            <?php else: ?>
                                                <span class="text-muted small">Tidak ada</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($p['status_verifikasi'] === 'menunggu_verifikasi'): ?>
                                                <span class="badge bg-warning text-dark rounded-pill px-3 py-1">Menunggu</span>
                                            <?php elseif ($p['status_verifikasi'] === 'disetujui'): ?>
                                                <span class="badge bg-success rounded-pill px-3 py-1">Disetujui</span>
                                            <?php else: ?>
                                                <span class="badge bg-danger rounded-pill px-3 py-1">Ditolak</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-end">
                                            <?php if ($p['status_verifikasi'] === 'menunggu_verifikasi'): ?>
                                                <div class="d-flex justify-content-end gap-2">
                                                    <form method="POST" action="/admin/verifikasi/approve">
                                                        <input type="hidden" name="_token" value="<?= \App\Helpers\Session::csrfToken() ?>">
                                                        <input type="hidden" name="user_id" value="<?= $p['user_id'] ?>">
                                                        <input type="hidden" name="type" value="partner">
                                                        <button type="submit" class="btn btn-sm btn-success rounded-pill px-3 fw-semibold">
                                                            <i class="bi bi-check-lg me-1"></i>Setujui
                                                        </button>
                                                    </form>
                                                    <button type="button" class="btn btn-sm btn-outline-danger rounded-pill px-3 fw-semibold"
                                                            data-bs-toggle="modal" data-bs-target="#rejectModalPartner<?= $p['user_id'] ?>">
                                                        <i class="bi bi-x-lg me-1"></i>Tolak
                                                    </button>
                                                </div>

                                                <!-- Reject Modal -->
                                                <div class="modal fade text-start" id="rejectModalPartner<?= $p['user_id'] ?>" tabindex="-1">
                                                    <div class="modal-dialog">
                                                        <form method="POST" action="/admin/verifikasi/reject" class="modal-content">
                                                            <input type="hidden" name="_token" value="<?= \App\Helpers\Session::csrfToken() ?>">
                                                            <input type="hidden" name="user_id" value="<?= $p['user_id'] ?>">
                                                            <input type="hidden" name="type" value="partner">
                                                            <div class="modal-header">
                                                                <h5 class="modal-title fw-bold">Tolak Pendaftaran Mitra</h5>
                                                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                            </div>
                                                            <div class="modal-body">
                                                                <p class="small text-secondary">Tuliskan alasan penolakan untuk mitra <strong><?= htmlspecialchars($p['nama_usaha']) ?></strong>:</p>
                                                                <textarea name="reason" rows="3" class="form-control" placeholder="Contoh: Dokumen izin usaha buram atau tidak terbaca..." required></textarea>
                                                            </div>
                                                            <div class="modal-footer">
                                                                <button type="button" class="btn btn-light rounded-pill" data-bs-dismiss="modal">Batal</button>
                                                                <button type="submit" class="btn btn-danger rounded-pill fw-semibold">Konfirmasi Tolak</button>
                                                            </div>
                                                        </form>
                                                    </div>
                                                </div>
                                            <?php else: ?>
                                                <span class="text-muted small">Selesai diproses</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- TAB 3: CUSTOMER -->
            <div class="tab-pane fade <?= ($activeTab ?? 'partners') === 'customers' ? 'show active' : '' ?>" id="tab-customers" role="tabpanel">
                <div class="card border-0 shadow-sm rounded-4 p-4">
                    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
                        <div>
                            <h5 class="fw-bold mb-1"><i class="bi bi-people text-primary me-2"></i>Daftar Customer</h5>
                            <p class="text-muted small mb-0">Total <?= $totalCustomersCount ?? 0 ?> customer terdaftar di sistem.</p>
                        </div>
                        <!-- Search Form Customer -->
                        <form method="GET" action="/admin/verifikasi" class="d-flex gap-2">
                            <input type="hidden" name="tab" value="customers">
                            <div class="input-group" style="min-width: 280px;">
                                <span class="input-group-text bg-white"><i class="bi bi-search text-muted"></i></span>
                                <input type="text" name="customer_search" class="form-control" placeholder="Cari nama, email, nomor WA..." value="<?= htmlspecialchars($customerSearch ?? '') ?>">
                            </div>
                            <button type="submit" class="btn btn-primary rounded-pill px-3 fw-semibold">Cari</button>
                            <?php if (!empty($customerSearch)): ?>
                                <a href="/admin/verifikasi?tab=customers" class="btn btn-outline-secondary rounded-pill px-3" title="Reset pencarian">
                                    <i class="bi bi-arrow-counterclockwise"></i>
                                </a>
                            <?php endif; ?>
                        </form>
                    </div>

                    <?php if (empty($customers)): ?>
                        <div class="alert alert-secondary text-center py-4 rounded-3">
                            <i class="bi bi-person-x fs-2 d-block mb-2 text-muted"></i>
                            <?= !empty($customerSearch) ? 'Tidak ada customer yang cocok dengan kata kunci "' . htmlspecialchars($customerSearch) . '".' : 'Belum ada customer terdaftar.' ?>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Nama &amp; Kontak</th>
                                        <th>Alamat</th>
                                        <th>Tanggal Daftar</th>
                                        <th class="text-center">Jumlah Pesanan</th>
                                        <th class="text-center">Status Akun</th>
                                        <th class="text-end">Tindakan Admin</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($customers as $cust): ?>
                                    <tr>
                                        <td>
                                            <div class="fw-bold text-dark"><?= htmlspecialchars($cust['name']) ?></div>
                                            <div class="small text-muted"><i class="bi bi-envelope me-1"></i><?= htmlspecialchars($cust['email']) ?></div>
                                            <div class="small text-muted"><i class="bi bi-whatsapp me-1 text-success"></i><?= htmlspecialchars($cust['phone'] ?: '-') ?></div>
                                        </td>
                                        <td>
                                            <div class="small text-secondary" style="max-width: 260px;">
                                                <i class="bi bi-geo-alt me-1 text-danger"></i><?= htmlspecialchars($cust['address']) ?>
                                            </div>
                                        </td>
                                        <td class="small text-secondary">
                                            <?= date('d M Y, H:i', strtotime($cust['registered_at'])) ?>
                                        </td>
                                        <td class="text-center">
                                            <span class="badge bg-light text-dark border px-2.5 py-1 rounded-pill">
                                                <i class="bi bi-bag-check me-1 text-primary"></i><?= (int) $cust['order_count'] ?> Pesanan
                                            </span>
                                        </td>
                                        <td class="text-center">
                                            <?php
                                                $cStatus = $cust['user_status'] ?? 'aktif';
                                                $badgeStatus = match ($cStatus) {
                                                    'aktif'               => 'bg-success text-white',
                                                    'menunggu_verifikasi' => 'bg-warning text-dark',
                                                    'ditolak'             => 'bg-danger text-white',
                                                    default               => 'bg-secondary text-white'
                                                };
                                            ?>
                                            <span class="badge <?= $badgeStatus ?> rounded-pill px-2.5 py-1">
                                                <?= ucfirst($cStatus) ?>
                                            </span>
                                        </td>
                                        <td class="text-end">
                                            <a href="/admin/chats?search=<?= urlencode($cust['name']) ?>" class="btn btn-sm btn-outline-primary rounded-pill px-3 fw-medium">
                                                <i class="bi bi-chat-dots me-1"></i>Riwayat Chat
                                            </a>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <!-- Pagination Customer (20 per page) -->
                        <?php if (!empty($customerPagination) && $customerPagination['total_pages'] > 1): ?>
                            <div class="d-flex justify-content-between align-items-center mt-4 pt-3 border-top">
                                <span class="small text-muted">
                                    Halaman <?= $customerPagination['page'] ?> dari <?= $customerPagination['total_pages'] ?> (Total: <?= $customerPagination['total'] ?> customer)
                                </span>
                                <nav aria-label="Customer Page Navigation">
                                    <ul class="pagination pagination-sm mb-0">
                                        <?php
                                            $buildCustUrl = function ($p) use ($customerSearch) {
                                                $params = ['tab' => 'customers', 'customer_page' => $p];
                                                if (!empty($customerSearch)) {
                                                    $params['customer_search'] = $customerSearch;
                                                }
                                                return '/admin/verifikasi?' . http_build_query($params);
                                            };
                                        ?>
                                        <li class="page-item <?= $customerPagination['page'] <= 1 ? 'disabled' : '' ?>">
                                            <a class="page-link" href="<?= $buildCustUrl($customerPagination['page'] - 1) ?>">Sebelumnya</a>
                                        </li>
                                        <?php for ($i = 1; $i <= $customerPagination['total_pages']; $i++): ?>
                                            <?php if ($i === 1 || $i === $customerPagination['total_pages'] || abs($i - $customerPagination['page']) <= 2): ?>
                                                <li class="page-item <?= $customerPagination['page'] === $i ? 'active' : '' ?>">
                                                    <a class="page-link" href="<?= $buildCustUrl($i) ?>"><?= $i ?></a>
                                                </li>
                                            <?php elseif (abs($i - $customerPagination['page']) === 3): ?>
                                                <li class="page-item disabled"><span class="page-link">...</span></li>
                                            <?php endif; ?>
                                        <?php endfor; ?>
                                        <li class="page-item <?= $customerPagination['page'] >= $customerPagination['total_pages'] ? 'disabled' : '' ?>">
                                            <a class="page-link" href="<?= $buildCustUrl($customerPagination['page'] + 1) ?>">Berikutnya</a>
                                        </li>
                                    </ul>
                                </nav>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- TAB 2: KURIR ARMADA -->
            <div class="tab-pane fade <?= ($activeTab ?? 'partners') === 'couriers' ? 'show active' : '' ?>" id="tab-couriers" role="tabpanel">
                <div class="card border-0 shadow-sm rounded-4 p-4">
                    <h5 class="fw-bold mb-3"><i class="bi bi-bicycle text-info me-2"></i>Daftar Calon Kurir Armada</h5>
                    <?php if (empty($couriers)): ?>
                        <div class="alert alert-secondary text-center py-4 rounded-3">Belum ada permohonan pendaftaran kurir.</div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Nama Kurir &amp; Kontak</th>
                                        <th>Kendaraan &amp; Plat Nomor</th>
                                        <th>Dokumen KTP/SIM</th>
                                        <th>Status</th>
                                        <th class="text-end">Tindakan Admin</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($couriers as $c): ?>
                                    <tr>
                                        <td>
                                            <div class="fw-bold"><?= htmlspecialchars($c['name']) ?></div>
                                            <div class="small text-muted"><?= htmlspecialchars($c['email']) ?></div>
                                            <div class="small text-muted"><i class="bi bi-whatsapp text-success me-1"></i><?= htmlspecialchars($c['phone'] ?? '-') ?></div>
                                        </td>
                                        <td>
                                            <div class="fw-semibold text-dark"><?= htmlspecialchars($c['plat_nomor']) ?></div>
                                            <div class="small text-secondary"><?= htmlspecialchars($c['jenis_kendaraan'] ?? 'Motor') ?></div>
                                        </td>
                                        <td>
                                            <?php if (!empty($c['dokumen'])): ?>
                                                <?php $cDocUrl = str_starts_with($c['dokumen'], 'http') ? $c['dokumen'] : '/' . ltrim($c['dokumen'], '/'); ?>
                                                <a href="<?= htmlspecialchars($cDocUrl) ?>" target="_blank" class="btn btn-sm btn-outline-primary rounded-pill">
                                                    <i class="bi bi-file-earmark-person me-1"></i>Lihat Berkas
                                                </a>
                                            <?php else: ?>
                                                <span class="text-muted small">Tidak ada</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($c['status_verifikasi'] === 'menunggu_verifikasi'): ?>
                                                <span class="badge bg-warning text-dark rounded-pill px-3 py-1">Menunggu</span>
                                            <?php elseif ($c['status_verifikasi'] === 'disetujui'): ?>
                                                <span class="badge bg-success rounded-pill px-3 py-1">Disetujui</span>
                                            <?php else: ?>
                                                <span class="badge bg-danger rounded-pill px-3 py-1">Ditolak</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-end">
                                            <?php if ($c['status_verifikasi'] === 'menunggu_verifikasi'): ?>
                                                <div class="d-flex justify-content-end gap-2">
                                                    <form method="POST" action="/admin/verifikasi/approve">
                                                        <input type="hidden" name="_token" value="<?= \App\Helpers\Session::csrfToken() ?>">
                                                        <input type="hidden" name="user_id" value="<?= $c['user_id'] ?>">
                                                        <input type="hidden" name="type" value="courier">
                                                        <button type="submit" class="btn btn-sm btn-success rounded-pill px-3 fw-semibold">
                                                            <i class="bi bi-check-lg me-1"></i>Setujui
                                                        </button>
                                                    </form>
                                                    <button type="button" class="btn btn-sm btn-outline-danger rounded-pill px-3 fw-semibold"
                                                            data-bs-toggle="modal" data-bs-target="#rejectModalCourier<?= $c['user_id'] ?>">
                                                        <i class="bi bi-x-lg me-1"></i>Tolak
                                                    </button>
                                                </div>

                                                <!-- Reject Modal -->
                                                <div class="modal fade text-start" id="rejectModalCourier<?= $c['user_id'] ?>" tabindex="-1">
                                                    <div class="modal-dialog">
                                                        <form method="POST" action="/admin/verifikasi/reject" class="modal-content">
                                                            <input type="hidden" name="_token" value="<?= \App\Helpers\Session::csrfToken() ?>">
                                                            <input type="hidden" name="user_id" value="<?= $c['user_id'] ?>">
                                                            <input type="hidden" name="type" value="courier">
                                                            <div class="modal-header">
                                                                <h5 class="modal-title fw-bold">Tolak Pendaftaran Kurir</h5>
                                                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                            </div>
                                                            <div class="modal-body">
                                                                <p class="small text-secondary">Tuliskan alasan penolakan untuk kurir <strong><?= htmlspecialchars($c['name']) ?></strong>:</p>
                                                                <textarea name="reason" rows="3" class="form-control" placeholder="Contoh: Dokumen SIM/KTP sudah kedaluwarsa..." required></textarea>
                                                            </div>
                                                            <div class="modal-footer">
                                                                <button type="button" class="btn btn-light rounded-pill" data-bs-dismiss="modal">Batal</button>
                                                                <button type="submit" class="btn btn-danger rounded-pill fw-semibold">Konfirmasi Tolak</button>
                                                            </div>
                                                        </form>
                                                    </div>
                                                </div>
                                            <?php else: ?>
                                                <span class="text-muted small">Selesai diproses</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- TAB 3: CUSTOMER -->
            <div class="tab-pane fade <?= ($activeTab ?? 'partners') === 'customers' ? 'show active' : '' ?>" id="tab-customers" role="tabpanel">
                <div class="card border-0 shadow-sm rounded-4 p-4">
                    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
                        <div>
                            <h5 class="fw-bold mb-1"><i class="bi bi-people text-primary me-2"></i>Daftar Customer</h5>
                            <p class="text-muted small mb-0">Total <?= $totalCustomersCount ?? 0 ?> customer terdaftar di sistem.</p>
                        </div>
                        <!-- Search Form Customer -->
                        <form method="GET" action="/admin/verifikasi" class="d-flex gap-2">
                            <input type="hidden" name="tab" value="customers">
                            <div class="input-group" style="min-width: 280px;">
                                <span class="input-group-text bg-white"><i class="bi bi-search text-muted"></i></span>
                                <input type="text" name="customer_search" class="form-control" placeholder="Cari nama, email, nomor WA..." value="<?= htmlspecialchars($customerSearch ?? '') ?>">
                            </div>
                            <button type="submit" class="btn btn-primary rounded-pill px-3 fw-semibold">Cari</button>
                            <?php if (!empty($customerSearch)): ?>
                                <a href="/admin/verifikasi?tab=customers" class="btn btn-outline-secondary rounded-pill px-3" title="Reset pencarian">
                                    <i class="bi bi-arrow-counterclockwise"></i>
                                </a>
                            <?php endif; ?>
                        </form>
                    </div>

                    <?php if (empty($customers)): ?>
                        <div class="alert alert-secondary text-center py-4 rounded-3">
                            <i class="bi bi-person-x fs-2 d-block mb-2 text-muted"></i>
                            <?= !empty($customerSearch) ? 'Tidak ada customer yang cocok dengan kata kunci "' . htmlspecialchars($customerSearch) . '".' : 'Belum ada customer terdaftar.' ?>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Nama &amp; Kontak</th>
                                        <th>Alamat</th>
                                        <th>Tanggal Daftar</th>
                                        <th class="text-center">Jumlah Pesanan</th>
                                        <th class="text-center">Status Akun</th>
                                        <th class="text-end">Tindakan Admin</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($customers as $cust): ?>
                                    <tr>
                                        <td>
                                            <div class="fw-bold text-dark"><?= htmlspecialchars($cust['name']) ?></div>
                                            <div class="small text-muted"><i class="bi bi-envelope me-1"></i><?= htmlspecialchars($cust['email']) ?></div>
                                            <div class="small text-muted"><i class="bi bi-whatsapp me-1 text-success"></i><?= htmlspecialchars($cust['phone'] ?: '-') ?></div>
                                        </td>
                                        <td>
                                            <div class="small text-secondary" style="max-width: 260px;">
                                                <i class="bi bi-geo-alt me-1 text-danger"></i><?= htmlspecialchars($cust['address']) ?>
                                            </div>
                                        </td>
                                        <td class="small text-secondary">
                                            <?= date('d M Y, H:i', strtotime($cust['registered_at'])) ?>
                                        </td>
                                        <td class="text-center">
                                            <span class="badge bg-light text-dark border px-2.5 py-1 rounded-pill">
                                                <i class="bi bi-bag-check me-1 text-primary"></i><?= (int) $cust['order_count'] ?> Pesanan
                                            </span>
                                        </td>
                                        <td class="text-center">
                                            <?php
                                                $cStatus = $cust['user_status'] ?? 'aktif';
                                                $badgeStatus = match ($cStatus) {
                                                    'aktif'               => 'bg-success text-white',
                                                    'menunggu_verifikasi' => 'bg-warning text-dark',
                                                    'ditolak'             => 'bg-danger text-white',
                                                    default               => 'bg-secondary text-white'
                                                };
                                            ?>
                                            <span class="badge <?= $badgeStatus ?> rounded-pill px-2.5 py-1">
                                                <?= ucfirst($cStatus) ?>
                                            </span>
                                        </td>
                                        <td class="text-end">
                                            <a href="/admin/chats?search=<?= urlencode($cust['name']) ?>" class="btn btn-sm btn-outline-primary rounded-pill px-3 fw-medium">
                                                <i class="bi bi-chat-dots me-1"></i>Riwayat Chat
                                            </a>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <!-- Pagination Customer (20 per page) -->
                        <?php if (!empty($customerPagination) && $customerPagination['total_pages'] > 1): ?>
                            <div class="d-flex justify-content-between align-items-center mt-4 pt-3 border-top">
                                <span class="small text-muted">
                                    Halaman <?= $customerPagination['page'] ?> dari <?= $customerPagination['total_pages'] ?> (Total: <?= $customerPagination['total'] ?> customer)
                                </span>
                                <nav aria-label="Customer Page Navigation">
                                    <ul class="pagination pagination-sm mb-0">
                                        <?php
                                            $buildCustUrl = function ($p) use ($customerSearch) {
                                                $params = ['tab' => 'customers', 'customer_page' => $p];
                                                if (!empty($customerSearch)) {
                                                    $params['customer_search'] = $customerSearch;
                                                }
                                                return '/admin/verifikasi?' . http_build_query($params);
                                            };
                                        ?>
                                        <li class="page-item <?= $customerPagination['page'] <= 1 ? 'disabled' : '' ?>">
                                            <a class="page-link" href="<?= $buildCustUrl($customerPagination['page'] - 1) ?>">Sebelumnya</a>
                                        </li>
                                        <?php for ($i = 1; $i <= $customerPagination['total_pages']; $i++): ?>
                                            <?php if ($i === 1 || $i === $customerPagination['total_pages'] || abs($i - $customerPagination['page']) <= 2): ?>
                                                <li class="page-item <?= $customerPagination['page'] === $i ? 'active' : '' ?>">
                                                    <a class="page-link" href="<?= $buildCustUrl($i) ?>"><?= $i ?></a>
                                                </li>
                                            <?php elseif (abs($i - $customerPagination['page']) === 3): ?>
                                                <li class="page-item disabled"><span class="page-link">...</span></li>
                                            <?php endif; ?>
                                        <?php endfor; ?>
                                        <li class="page-item <?= $customerPagination['page'] >= $customerPagination['total_pages'] ? 'disabled' : '' ?>">
                                            <a class="page-link" href="<?= $buildCustUrl($customerPagination['page'] + 1) ?>">Berikutnya</a>
                                        </li>
                                    </ul>
                                </nav>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</section>
