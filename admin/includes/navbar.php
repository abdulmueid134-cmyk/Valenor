<?php
// =============================================================================
// VALENOR HOROLOGY — ADMIN PANEL TOPBAR
// =============================================================================
?>
<header class="admin-topbar">
    <div class="d-flex align-items-center gap-3">
        <button class="btn btn-outline-dark d-lg-none p-1 border-0" id="sidebarToggleBtn" type="button" aria-label="Toggle sidebar">
            <i class="bi bi-list fs-3" style="color:var(--caramel-dark);"></i>
        </button>
        <div>
            <h5 class="mb-0 font-serif fw-bold" style="color:var(--text-charcoal);"><?= isset($adminPageHeader) ? e($adminPageHeader) : 'Admin Concierge Portal' ?></h5>
            <?php if (isset($adminPageSub)): ?>
                <span class="text-muted small"><?= e($adminPageSub) ?></span>
            <?php endif; ?>
        </div>
    </div>

    <div class="d-flex align-items-center gap-3">
        <a href="<?= BASE_URL ?>/index.php" target="_blank" class="btn btn-sm btn-outline-dark rounded-pill px-3 d-none d-sm-inline-flex align-items-center gap-1" style="font-size:0.82rem; border-color:var(--border-beige);">
            <i class="bi bi-globe"></i> Live Storefront
        </a>

        <div class="dropdown">
            <button class="btn btn-sm btn-light border rounded-pill px-3 dropdown-toggle d-flex align-items-center gap-2" type="button" data-bs-toggle="dropdown" aria-expanded="false" style="border-color:var(--border-beige) !important;">
                <i class="bi bi-shield-check text-success"></i>
                <span class="small fw-semibold"><?= e($adminUser['name'] ?? 'Admin') ?></span>
            </button>
            <ul class="dropdown-menu dropdown-menu-end shadow-sm border" style="border-color:var(--border-beige);">
                <li class="px-3 py-2 small text-muted border-bottom">
                    Signed in as<br><strong><?= e($adminUser['email'] ?? 'admin@valenor.com') ?></strong>
                </li>
                <li><a class="dropdown-item py-2 small" href="<?= ADMIN_URL ?>/index.php"><i class="bi bi-speedometer2 me-2"></i>Dashboard</a></li>
                <li><a class="dropdown-item py-2 small" href="<?= BASE_URL ?>/index.php" target="_blank"><i class="bi bi-shop me-2"></i>View Shop</a></li>
                <li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item py-2 small text-danger" href="<?= ADMIN_URL ?>/logout.php"><i class="bi bi-box-arrow-right me-2"></i>Sign Out</a></li>
            </ul>
        </div>
    </div>
</header>

<main class="p-4 flex-grow-1">
    <?= display_flash() ?>
