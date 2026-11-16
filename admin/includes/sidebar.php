<?php
// =============================================================================
// VALENOR HOROLOGY — ADMIN PANEL SIDEBAR (Section 1)
// =============================================================================

$currentScript = basename($_SERVER['PHP_SELF']);
?>
<aside class="admin-sidebar" id="adminSidebar">
    <!-- Brand Title -->
    <div class="sidebar-brand d-flex align-items-center justify-content-between">
        <a href="<?= ADMIN_URL ?>/index.php" class="text-decoration-none d-flex align-items-center gap-2">
            <span class="fs-4 font-serif fw-bold text-white tracking-wide" style="letter-spacing:0.15em;">VALENOR</span>
            <span class="badge" style="background:var(--caramel-dark); font-size:0.65rem; padding:3px 6px;">ADMIN</span>
        </a>
        <button class="btn btn-sm btn-outline-light d-lg-none" type="button" id="sidebarCloseBtn" aria-label="Close sidebar">
            <i class="bi bi-x-lg"></i>
        </button>
    </div>

    <!-- Navigation Menu -->
    <nav class="nav flex-column py-3 flex-grow-1">
        <a class="nav-link <?= $currentScript === 'index.php' ? 'active' : '' ?>" href="<?= ADMIN_URL ?>/index.php">
            <i class="bi bi-speedometer2"></i>
            <span>Dashboard</span>
        </a>
        
        <div class="px-3 pt-3 pb-1 small text-uppercase fw-bold" style="font-size:0.68rem; color:#888; letter-spacing:0.1em;">
            Catalog Management
        </div>

        <a class="nav-link <?= in_array($currentScript, ['products.php', 'product-add.php', 'product-edit.php']) ? 'active' : '' ?>" href="<?= ADMIN_URL ?>/products.php">
            <i class="bi bi-watch"></i>
            <span>Products</span>
        </a>

        <a class="nav-link <?= in_array($currentScript, ['categories.php', 'category-add.php', 'category-edit.php']) ? 'active' : '' ?>" href="<?= ADMIN_URL ?>/categories.php">
            <i class="bi bi-grid"></i>
            <span>Categories</span>
        </a>

        <div class="px-3 pt-3 pb-1 small text-uppercase fw-bold" style="font-size:0.68rem; color:#888; letter-spacing:0.1em;">
            Sales &amp; Logistics
        </div>

        <a class="nav-link <?= $currentScript === 'orders.php' ? 'active' : '' ?>" href="<?= ADMIN_URL ?>/orders.php">
            <i class="bi bi-receipt"></i>
            <span>Orders</span>
        </a>

        <a class="nav-link <?= $currentScript === 'customers.php' ? 'active' : '' ?>" href="<?= ADMIN_URL ?>/customers.php">
            <i class="bi bi-people"></i>
            <span>Customers</span>
        </a>

        <a class="nav-link <?= $currentScript === 'reviews.php' ? 'active' : '' ?>" href="<?= ADMIN_URL ?>/reviews.php">
            <i class="bi bi-star"></i>
            <span>Reviews</span>
        </a>

        <div class="px-3 pt-3 pb-1 small text-uppercase fw-bold" style="font-size:0.68rem; color:#888; letter-spacing:0.1em;">
            Quick Links
        </div>

        <a class="nav-link" href="<?= BASE_URL ?>/index.php" target="_blank">
            <i class="bi bi-box-arrow-up-right"></i>
            <span>View Storefront</span>
        </a>
    </nav>

    <!-- Admin User Info / Sign Out -->
    <div class="p-3 border-top" style="border-color:rgba(232,222,210,0.1) !important; background:#151311;">
        <div class="d-flex align-items-center justify-content-between">
            <div class="d-flex align-items-center gap-2 overflow-hidden">
                <div class="rounded-circle d-flex align-items-center justify-content-center text-white fw-bold" style="width:34px; height:34px; background:var(--caramel-dark); flex-shrink:0;">
                    <?= strtoupper(substr($adminUser['name'] ?? 'A', 0, 1)) ?>
                </div>
                <div class="text-truncate">
                    <div class="text-white small fw-bold text-truncate"><?= e($adminUser['name'] ?? 'Admin') ?></div>
                    <div class="text-muted" style="font-size:0.72rem;"><?= e($adminUser['role'] ?? 'super_admin') ?></div>
                </div>
            </div>
            <a href="<?= ADMIN_URL ?>/logout.php" class="btn btn-sm btn-outline-danger border-0 p-1" title="Sign Out" aria-label="Sign Out">
                <i class="bi bi-box-arrow-right fs-5"></i>
            </a>
        </div>
    </div>
</aside>
