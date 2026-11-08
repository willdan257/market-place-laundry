<?php require __DIR__ . '/header.php'; ?>
<?php require __DIR__ . '/../components/navbar.php'; ?>

<main class="flex-shrink-0">
    <?php if (isset($flashSuccess) && $flashSuccess): ?>
        <div class="container mt-3">
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="bi bi-check-circle-fill me-2"></i><?= htmlspecialchars($flashSuccess) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        </div>
    <?php endif; ?>

    <?php if (isset($flashError) && $flashError): ?>
        <div class="container mt-3">
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="bi bi-exclamation-triangle-fill me-2"></i><?= htmlspecialchars($flashError) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        </div>
    <?php endif; ?>

    <?php if (isset($flashWarning) && $flashWarning): ?>
        <div class="container mt-3">
            <div class="alert alert-warning alert-dismissible fade show" role="alert">
                <i class="bi bi-exclamation-circle-fill me-2"></i><?= htmlspecialchars($flashWarning) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        </div>
    <?php endif; ?>

    <?= $content ?? '' ?>
</main>

<?php if (empty($hideAiWidget)): ?>
    <?php require __DIR__ . '/../components/ai-widget.php'; ?>
<?php endif; ?>

<?php require __DIR__ . '/footer.php'; ?>
