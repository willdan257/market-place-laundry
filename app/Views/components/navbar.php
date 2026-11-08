<nav class="navbar navbar-expand-lg navbar-dark bg-dark-gradient sticky-top shadow-sm py-3">
    <div class="container">
        <a class="navbar-brand d-flex align-items-center gap-2 fw-bold text-white fs-4" href="/">
            <div class="brand-icon-wrapper bg-primary text-white rounded-circle d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                <i class="bi bi-water fs-5"></i>
            </div>
            <span>Marketplace<span class="text-primary-accent">Laundry</span></span>
        </a>
        <button class="navbar-toggler border-0" type="button" data-bs-toggle="collapse" data-bs-target="#navbarMain" aria-controls="navbarMain" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarMain">
            <ul class="navbar-nav me-auto mb-2 mb-lg-0 ms-lg-4">
                <li class="nav-item">
                    <a class="nav-link active" href="/">Beranda</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="#layanan">Layanan</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="#fitur">Fitur Unggulan</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="#peran">Role Pengguna</a>
                </li>
            </ul>
            <div class="d-flex align-items-center gap-3">
                <?php if (isset($user) && $user): ?>
                    <div class="dropdown">
                        <button class="btn btn-outline-light dropdown-toggle d-flex align-items-center gap-2 px-3 py-2 rounded-pill" type="button" data-bs-toggle="dropdown">
                            <i class="bi bi-person-circle fs-5"></i>
                            <span><?= htmlspecialchars($user['name'] ?? 'Akun Saya') ?></span>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end shadow">
                            <li><span class="dropdown-item-text text-muted small">Role: <?= strtoupper($user['role'] ?? 'Customer') ?></span></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="/dashboard"><i class="bi bi-speedometer2 me-2"></i>Dashboard</a></li>
                            <li><a class="dropdown-item" href="/profile/edit"><i class="bi bi-pencil-square me-2"></i>Edit Profil</a></li>
                            <?php if (in_array($user['role'] ?? '', ['customer', 'pelanggan'], true)): ?>
                                <li><a class="dropdown-item" href="/customer/orders"><i class="bi bi-bag-check me-2"></i>Pesanan Saya</a></li>
                                <li><a class="dropdown-item" href="/profile"><i class="bi bi-person me-2"></i>Profil &amp; Alamat</a></li>
                            <?php elseif (in_array($user['role'] ?? '', ['owner', 'mitra'], true)): ?>
                                <li><a class="dropdown-item" href="/owner/orders"><i class="bi bi-list-task me-2"></i>Kelola Pesanan</a></li>
                                <li><a class="dropdown-item" href="/owner/drivers"><i class="bi bi-bicycle me-2"></i>Daftar Kurir</a></li>
                                <li><a class="dropdown-item" href="/profile"><i class="bi bi-person me-2"></i>Profil Saya</a></li>
                            <?php elseif (in_array($user['role'] ?? '', ['driver', 'kurir'], true)): ?>
                                <li><a class="dropdown-item" href="/driver/orders"><i class="bi bi-truck me-2"></i>Tugas Kurir</a></li>
                                <li><a class="dropdown-item" href="/profile"><i class="bi bi-person me-2"></i>Profil Saya</a></li>
                            <?php elseif (($user['role'] ?? '') === 'admin'): ?>
                                <li><a class="dropdown-item" href="/admin/verifikasi"><i class="bi bi-shield-check me-2"></i>Verifikasi Mitra &amp; Kurir</a></li>
                                <li><a class="dropdown-item" href="/admin/chats"><i class="bi bi-chat-dots me-2"></i>Riwayat Chat</a></li>
                                <li><a class="dropdown-item" href="/admin/konfigurasi"><i class="bi bi-gear me-2"></i>Konfigurasi</a></li>
                            <?php endif; ?>
                            <?php if (($user['status'] ?? 'aktif') === 'menunggu_verifikasi'): ?>
                                <li><a class="dropdown-item text-warning" href="/verifikasi-menunggu"><i class="bi bi-hourglass-split me-2"></i>Status Verifikasi</a></li>
                            <?php endif; ?>
                            <li><hr class="dropdown-divider"></li>
                            <li>
                                <form method="POST" action="/logout" class="d-inline w-100" onsubmit="try{for(var k in localStorage){if(k.indexOf('laundry_ai_')===0)localStorage.removeItem(k);}}catch(e){}">
                                    <input type="hidden" name="_token" value="<?= \App\Helpers\Session::csrfToken() ?>">
                                    <button type="submit" class="dropdown-item text-danger border-0 bg-transparent w-100 text-start">
                                        <i class="bi bi-box-arrow-right me-2"></i>Logout
                                    </button>
                                </form>
                            </li>
                        </ul>
                    </div>
                <?php else: ?>
                    <a href="/login" class="btn btn-outline-light rounded-pill px-4 fw-medium">Masuk</a>
                    <a href="/daftar" class="btn btn-primary-accent rounded-pill px-4 fw-semibold text-white shadow-sm">Daftar Sekarang</a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</nav>
