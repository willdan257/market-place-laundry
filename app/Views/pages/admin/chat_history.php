<section class="py-4 bg-light min-vh-100">
    <div class="container">
        <!-- Header Admin Console -->
        <div class="card border-0 shadow-sm rounded-4 p-4 mb-4 bg-dark text-white">
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
                <div>
                    <span class="badge bg-danger rounded-pill px-3 py-1 mb-2 fw-bold"><i class="bi bi-shield-check me-1"></i>ADMIN CONSOLE</span>
                    <h3 class="fw-bold mb-1">Riwayat Percakapan Chat</h3>
                    <p class="mb-0 text-white-50">Pantau seluruh interaksi pesan chat AI (Tanya AI) dan percakapan pesanan dari seluruh peran (Customer, Mitra, Kurir, Admin).</p>
                </div>
                <div class="d-flex gap-2">
                    <a href="/admin/verifikasi" class="btn btn-outline-light rounded-pill px-3 py-2">
                        <i class="bi bi-check2-circle me-1"></i>Verifikasi Mitra &amp; Kurir
                    </a>
                </div>
            </div>
        </div>

        <!-- Tab Navigasi: Chat AI vs Chat Pesanan -->
        <ul class="nav nav-pills mb-4 gap-2">
            <li class="nav-item">
                <a class="nav-link rounded-pill px-4 py-2.5 fw-semibold <?= $activeTab === 'ai' ? 'active shadow-sm' : 'bg-white text-secondary' ?>" 
                   href="/admin/chats?tab=ai<?= !empty($filters['role']) ? '&role=' . urlencode($filters['role']) : '' ?><?= !empty($filters['search']) ? '&search=' . urlencode($filters['search']) : '' ?>">
                    <i class="bi bi-robot me-2"></i>Riwayat Tanya AI (Chatbot)
                    <span class="badge rounded-pill ms-2 <?= $activeTab === 'ai' ? 'bg-light text-dark' : 'bg-secondary text-white' ?>"><?= number_format($aiTotalCount) ?></span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link rounded-pill px-4 py-2.5 fw-semibold <?= $activeTab === 'order' ? 'active shadow-sm' : 'bg-white text-secondary' ?>" 
                   href="/admin/chats?tab=order<?= !empty($filters['role']) ? '&role=' . urlencode($filters['role']) : '' ?><?= !empty($filters['search']) ? '&search=' . urlencode($filters['search']) : '' ?>">
                    <i class="bi bi-chat-left-dots me-2"></i>Chat Pesanan Antar-Pengguna
                    <span class="badge rounded-pill ms-2 <?= $activeTab === 'order' ? 'bg-light text-dark' : 'bg-secondary text-white' ?>"><?= number_format($orderTotalCount) ?></span>
                </a>
            </li>
        </ul>

        <!-- Filter & Search Card -->
        <div class="card border-0 shadow-sm rounded-4 p-4 mb-4">
            <form method="GET" action="/admin/chats" class="row g-3 align-items-end">
                <input type="hidden" name="tab" value="<?= htmlspecialchars($activeTab) ?>">

                <div class="col-md-3">
                    <label class="form-label small fw-semibold text-secondary">Filter Peran Pengguna</label>
                    <select name="role" class="form-select rounded-3">
                        <option value="">Semua Peran</option>
                        <option value="customer" <?= ($filters['role'] ?? '') === 'customer' ? 'selected' : '' ?>>Customer (Pelanggan)</option>
                        <option value="owner" <?= ($filters['role'] ?? '') === 'owner' ? 'selected' : '' ?>>Mitra Laundry (Owner)</option>
                        <option value="driver" <?= ($filters['role'] ?? '') === 'driver' ? 'selected' : '' ?>>Kurir Armada</option>
                        <option value="admin" <?= ($filters['role'] ?? '') === 'admin' ? 'selected' : '' ?>>Admin</option>
                        <?php if ($activeTab === 'ai'): ?>
                            <option value="tamu" <?= ($filters['role'] ?? '') === 'tamu' ? 'selected' : '' ?>>Tamu / Pengunjung</option>
                        <?php endif; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label small fw-semibold text-secondary">Cari (Nama, Pesan, No. Order)</label>
                    <div class="input-group">
                        <span class="input-group-text bg-white"><i class="bi bi-search text-muted"></i></span>
                        <input type="text" name="search" class="form-control" placeholder="Ketik nama atau kata kunci..." value="<?= htmlspecialchars($filters['search'] ?? '') ?>">
                    </div>
                </div>
                <div class="col-md-2">
                    <label class="form-label small fw-semibold text-secondary">Dari Tanggal</label>
                    <input type="date" name="start_date" class="form-control rounded-3" value="<?= htmlspecialchars($filters['start_date'] ?? '') ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label small fw-semibold text-secondary">Sampai Tanggal</label>
                    <input type="date" name="end_date" class="form-control rounded-3" value="<?= htmlspecialchars($filters['end_date'] ?? '') ?>">
                </div>
                <div class="col-md-2 d-flex gap-2">
                    <button type="submit" class="btn btn-primary rounded-3 flex-fill fw-semibold">
                        <i class="bi bi-filter me-1"></i>Filter
                    </button>
                    <a href="/admin/chats?tab=<?= $activeTab ?>" class="btn btn-outline-secondary rounded-3" title="Reset filter">
                        <i class="bi bi-arrow-counterclockwise"></i>
                    </a>
                </div>
            </form>
        </div>

        <?php if ($activeTab === 'ai'): ?>
            <!-- ================= TAB 1: RIWAYAT CHAT AI (TANYA AI) ================= -->
            <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
                <div class="card-header bg-white py-3 px-4 border-bottom d-flex align-items-center justify-content-between">
                    <h6 class="mb-0 fw-bold text-dark"><i class="bi bi-robot text-primary me-2"></i>Daftar Percakapan Asisten AI (Semua Role)</h6>
                    <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-3 py-1.5 rounded-pill small">
                        Total Ditemukan: <?= number_format($pagination['total'] ?? 0) ?> pesan
                    </span>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th class="ps-4" style="width: 220px;">Pengguna &amp; Peran</th>
                                <th>Pertanyaan Pengguna</th>
                                <th>Jawaban Asisten AI</th>
                                <th style="width: 130px;">Terkait Order</th>
                                <th style="width: 150px;">Waktu</th>
                                <th class="text-end pe-4" style="width: 100px;">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($aiMessages)): ?>
                                <tr>
                                    <td colspan="6" class="text-center py-5 text-muted">
                                        <i class="bi bi-chat-square-dots fs-1 d-block mb-2 text-secondary"></i>
                                        Belum ada riwayat chat AI yang tersimpan atau sesuai dengan filter.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($aiMessages as $idx => $m): ?>
                                    <?php
                                        $roleBadge = match ($m['user_role']) {
                                            'admin'                 => 'bg-danger text-white',
                                            'owner', 'mitra'        => 'bg-warning text-dark',
                                            'driver', 'kurir'       => 'bg-info text-dark',
                                            'customer', 'pelanggan' => 'bg-primary text-white',
                                            default                 => 'bg-secondary text-white'
                                        };
                                        $roleLabel = match ($m['user_role']) {
                                            'admin'                 => 'Admin',
                                            'owner', 'mitra'        => 'Mitra Laundry',
                                            'driver', 'kurir'       => 'Kurir Armada',
                                            'customer', 'pelanggan' => 'Customer',
                                            default                 => 'Tamu'
                                        };
                                        $previewPrompt = mb_strlen($m['message']) > 80 ? mb_substr($m['message'], 0, 80) . '...' : $m['message'];
                                        $previewReply  = mb_strlen($m['response']) > 110 ? mb_substr($m['response'], 0, 110) . '...' : $m['response'];
                                    ?>
                                    <tr>
                                        <td class="ps-4">
                                            <div class="fw-bold text-dark"><?= htmlspecialchars($m['user_name']) ?></div>
                                            <div class="d-flex align-items-center gap-1 mt-1">
                                                <span class="badge <?= $roleBadge ?> rounded-pill px-2 py-0.5" style="font-size:0.75rem;">
                                                    <?= $roleLabel ?>
                                                </span>
                                            </div>
                                            <?php if (!empty($m['user_email'])): ?>
                                                <div class="small text-muted mt-1" style="font-size:0.78rem;">
                                                    <i class="bi bi-envelope me-1"></i><?= htmlspecialchars($m['user_email']) ?>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="p-2 rounded-3 bg-light border text-dark small" style="max-width:320px;">
                                                <i class="bi bi-person-fill text-secondary me-1"></i>
                                                <?= htmlspecialchars($previewPrompt) ?>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="text-secondary small" style="max-width:380px;">
                                                <span class="badge bg-primary-subtle text-primary border border-primary-subtle me-1" style="font-size:0.7rem;">AI</span>
                                                <?= htmlspecialchars($previewReply) ?>
                                            </div>
                                        </td>
                                        <td>
                                            <?php if (!empty($m['order_number'])): ?>
                                                <span class="badge bg-light text-dark border rounded-pill px-2 py-1 small">
                                                    #<?= htmlspecialchars($m['order_number']) ?>
                                                </span>
                                            <?php else: ?>
                                                <span class="text-muted small">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="small text-secondary">
                                            <i class="bi bi-clock me-1"></i><?= date('d M Y, H:i', strtotime($m['created_at'])) ?>
                                        </td>
                                        <td class="text-end pe-4">
                                            <button type="button" 
                                                    class="btn btn-sm btn-outline-primary rounded-pill px-3 fw-medium btn-view-ai-chat"
                                                    data-bs-toggle="modal" 
                                                    data-bs-target="#aiChatDetailModal"
                                                    data-user-name="<?= htmlspecialchars($m['user_name']) ?>"
                                                    data-user-role="<?= htmlspecialchars($roleLabel) ?>"
                                                    data-user-email="<?= htmlspecialchars($m['user_email']) ?>"
                                                    data-created-at="<?= date('d M Y, H:i', strtotime($m['created_at'])) ?> WIB"
                                                    data-order-number="<?= htmlspecialchars($m['order_number'] ?? '-') ?>"
                                                    data-prompt="<?= htmlspecialchars($m['message']) ?>"
                                                    data-reply="<?= htmlspecialchars($m['response']) ?>">
                                                <i class="bi bi-eye me-1"></i>Detail
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Modal Detail AI Chat -->
                <div class="modal fade" id="aiChatDetailModal" tabindex="-1" aria-labelledby="aiChatDetailModalLabel" aria-hidden="true">
                    <div class="modal-dialog modal-dialog-centered modal-lg">
                        <div class="modal-content rounded-4 border-0 shadow">
                            <div class="modal-header border-bottom py-3 px-4 bg-light">
                                <h5 class="modal-title fw-bold text-dark" id="aiChatDetailModalLabel">
                                    <i class="bi bi-robot text-primary me-2"></i>Detail Percakapan Asisten AI
                                </h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body p-4">
                                <div class="row g-3 mb-4 p-3 bg-light rounded-3 border">
                                    <div class="col-sm-6">
                                        <small class="text-muted d-block">Pengguna</small>
                                        <span class="fw-bold text-dark" id="modalUserName">-</span>
                                        <span class="badge bg-primary text-white ms-1 rounded-pill" id="modalUserRole">-</span>
                                        <div class="small text-muted" id="modalUserEmail"></div>
                                    </div>
                                    <div class="col-sm-3">
                                        <small class="text-muted d-block">Waktu Interaksi</small>
                                        <span class="fw-semibold text-dark small" id="modalCreatedAt">-</span>
                                    </div>
                                    <div class="col-sm-3">
                                        <small class="text-muted d-block">Pesanan Terkait</small>
                                        <span class="badge bg-white text-dark border" id="modalOrderNumber">-</span>
                                    </div>
                                </div>

                                <div class="mb-4">
                                    <label class="fw-bold small text-secondary mb-2"><i class="bi bi-person-circle me-1"></i>Pertanyaan dari Pengguna:</label>
                                    <div class="p-3 rounded-3 bg-primary text-white small" style="white-space: pre-wrap;" id="modalPrompt"></div>
                                </div>

                                <div>
                                    <label class="fw-bold small text-secondary mb-2"><i class="bi bi-robot me-1 text-primary"></i>Jawaban Asisten AI:</label>
                                    <div class="p-3 rounded-3 bg-light border text-dark small" style="white-space: pre-wrap; line-height: 1.6;" id="modalReply"></div>
                                </div>
                            </div>
                            <div class="modal-footer border-top py-2 px-4">
                                <button type="button" class="btn btn-secondary rounded-pill px-4" data-bs-dismiss="modal">Tutup</button>
                            </div>
                        </div>
                    </div>
                </div>

        <?php else: ?>
            <!-- ================= TAB 2: CHAT PESANAN ANTAR-PENGGUNA ================= -->
            <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
                <div class="card-header bg-white py-3 px-4 border-bottom d-flex align-items-center justify-content-between">
                    <h6 class="mb-0 fw-bold text-dark"><i class="bi bi-chat-left-text text-primary me-2"></i>Daftar Percakapan Antar-Pengguna Per Pesanan</h6>
                    <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-3 py-1.5 rounded-pill small">
                        Total Ditemukan: <?= number_format($pagination['total'] ?? 0) ?> percakapan
                    </span>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th class="ps-4">No. Pesanan</th>
                                <th>Toko Laundry</th>
                                <th>Peserta Chat</th>
                                <th>Pesan Terakhir</th>
                                <th class="text-center">Jumlah</th>
                                <th>Waktu Terakhir</th>
                                <th class="text-end pe-4">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($conversations)): ?>
                                <tr>
                                    <td colspan="7" class="text-center py-5 text-muted">
                                        <i class="bi bi-chat-left-dots fs-1 d-block mb-2 text-secondary"></i>
                                        Tidak ada riwayat percakapan yang sesuai dengan filter.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($conversations as $c): ?>
                                    <tr>
                                        <td class="ps-4">
                                            <a href="/admin/chats/<?= $c['order_id'] ?>" class="fw-bold text-decoration-none text-primary">
                                                #<?= htmlspecialchars($c['order_number']) ?>
                                            </a>
                                        </td>
                                        <td>
                                            <div class="fw-medium text-dark"><?= htmlspecialchars($c['store_name']) ?></div>
                                        </td>
                                        <td>
                                            <div class="d-flex flex-wrap gap-1">
                                                <?php foreach ($c['participants'] as $p): ?>
                                                    <?php
                                                        $badgeClass = match ($p['role']) {
                                                            'admin'  => 'bg-danger text-white',
                                                            'owner'  => 'bg-warning text-dark',
                                                            'driver' => 'bg-info text-dark',
                                                            default  => 'bg-primary text-white'
                                                        };
                                                        $roleLabel = match ($p['role']) {
                                                            'admin'  => 'Admin',
                                                            'owner'  => 'Mitra',
                                                            'driver' => 'Kurir',
                                                            default  => 'Customer'
                                                        };
                                                    ?>
                                                    <span class="badge <?= $badgeClass ?> rounded-pill px-2 py-1 small" title="<?= htmlspecialchars($p['name']) ?>">
                                                        <?= htmlspecialchars($p['name']) ?> (<?= $roleLabel ?>)
                                                    </span>
                                                <?php endforeach; ?>
                                            </div>
                                        </td>
                                        <td style="max-width: 280px;">
                                            <div class="text-truncate text-secondary small">
                                                <strong class="text-dark"><?= htmlspecialchars($c['last_sender_name']) ?>:</strong>
                                                <?= htmlspecialchars($c['last_message']) ?>
                                            </div>
                                        </td>
                                        <td class="text-center">
                                            <span class="badge bg-light text-dark border px-2 py-1 rounded-pill">
                                                <i class="bi bi-chat-text me-1"></i><?= $c['message_count'] ?>
                                            </span>
                                        </td>
                                        <td class="small text-secondary">
                                            <?= date('d M Y, H:i', strtotime($c['last_message_at'])) ?>
                                        </td>
                                        <td class="text-end pe-4">
                                            <a href="/admin/chats/<?= $c['order_id'] ?>" class="btn btn-sm btn-outline-primary rounded-pill px-3 fw-medium">
                                                <i class="bi bi-eye me-1"></i>Buka Chat
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
        <?php endif; ?>

            <!-- Pagination (20 per page) -->
            <?php if (!empty($pagination) && $pagination['total_pages'] > 1): ?>
                <div class="card-footer bg-white border-top py-3 d-flex flex-wrap justify-content-between align-items-center gap-2">
                    <span class="small text-muted">
                        Menampilkan halaman <?= $pagination['page'] ?> dari <?= $pagination['total_pages'] ?> (Total: <?= $pagination['total'] ?> baris)
                    </span>
                    <nav aria-label="Page navigation">
                        <ul class="pagination pagination-sm mb-0">
                            <?php
                                $queryParams = $filters;
                                $queryParams['tab'] = $activeTab;
                                $buildUrl = function($p) use ($queryParams) {
                                    $queryParams['page'] = $p;
                                    return '/admin/chats?' . http_build_query($queryParams);
                                };
                            ?>
                            <li class="page-item <?= $pagination['page'] <= 1 ? 'disabled' : '' ?>">
                                <a class="page-link" href="<?= $buildUrl($pagination['page'] - 1) ?>">Sebelumnya</a>
                            </li>
                            <?php for ($p = 1; $p <= $pagination['total_pages']; $p++): ?>
                                <li class="page-item <?= $p === $pagination['page'] ? 'active' : '' ?>">
                                    <a class="page-link" href="<?= $buildUrl($p) ?>"><?= $p ?></a>
                                </li>
                            <?php endfor; ?>
                            <li class="page-item <?= $pagination['page'] >= $pagination['total_pages'] ? 'disabled' : '' ?>">
                                <a class="page-link" href="<?= $buildUrl($pagination['page'] + 1) ?>">Berikutnya</a>
                            </li>
                        </ul>
                    </nav>
                </div>
            <?php endif; ?>
        </div>

    </div>
</section>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const modal = document.getElementById('aiChatDetailModal');
    if (!modal) return;

    modal.addEventListener('show.bs.modal', function (event) {
        const button = event.relatedTarget;
        if (!button) return;

        document.getElementById('modalUserName').textContent = button.getAttribute('data-user-name') || '-';
        document.getElementById('modalUserRole').textContent = button.getAttribute('data-user-role') || '-';
        document.getElementById('modalUserEmail').textContent = button.getAttribute('data-user-email') || '';
        document.getElementById('modalCreatedAt').textContent = button.getAttribute('data-created-at') || '-';
        document.getElementById('modalOrderNumber').textContent = button.getAttribute('data-order-number') || '-';
        document.getElementById('modalPrompt').textContent = button.getAttribute('data-prompt') || '-';
        document.getElementById('modalReply').textContent = button.getAttribute('data-reply') || '-';
    });
});
</script>
