<section class="py-5 text-center my-5">
    <div class="container">
        <div class="display-1 fw-extrabold text-danger mb-3">403</div>
        <h2 class="fw-bold mb-3">Akses Ditolak (Forbidden)</h2>
        <p class="text-muted max-w-500 mx-auto mb-4">
            <?= htmlspecialchars($message ?? 'Maaf, Anda tidak memiliki izin atau hak akses untuk membuka halaman ini.') ?>
        </p>
        <div class="d-flex justify-content-center gap-2">
            <a href="/" class="btn btn-outline-secondary rounded-pill px-4 fw-semibold">
                <i class="bi bi-house-door me-2"></i>Beranda
            </a>
            <a href="/dashboard" class="btn btn-primary rounded-pill px-4 fw-semibold">
                <i class="bi bi-speedometer2 me-2"></i>Ke Dashboard Saya
            </a>
        </div>
    </div>
</section>

