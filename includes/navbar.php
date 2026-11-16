<?php
// =============================================================================
// VALENOR HOROLOGY — REUSABLE NAVIGATION BAR & HEADER COMPONENT
// =============================================================================

require_once __DIR__ . '/../config/config.php';

$cartCount     = get_cart_count();
$wishlistCount = get_wishlist_count();
$compareCount  = get_compare_count();
$currentUser   = current_user();

// Fetch categories for navbar dropdown from MySQL
$navCatStmt = getDB()->query("SELECT name, slug FROM categories WHERE status = 'active' ORDER BY sort_order ASC LIMIT 8");
$navCategories = $navCatStmt->fetchAll();

// Determine Cart Target Link (Mandatory login workflow)
$cartUrl = is_logged_in() ? BASE_URL . '/cart.php' : BASE_URL . '/login.php?redirect=cart.php';
?>

<!-- 1. Top Luxury Announcement Strip -->
<div class="announcement-bar">
    <div class="container d-flex justify-content-between align-items-center">
        <div class="d-none d-md-block">
            <span class="badge bg-light text-dark me-2" style="font-size:0.7rem; font-weight:700;">VALENOR PRIVILEGE</span>
            <span>Complimentary Insured Delivery on orders over <?= CURRENCY_SYMBOL ?>15,000</span>
        </div>
        <div class="mx-auto mx-md-0 text-center">
            <span>Special 20% Privilege Discount with code: <strong>VALENOR20</strong></span>
            <a href="<?= BASE_URL ?>/products.php?filter=sale">Shop 20% Sale</a>
        </div>
        <div class="d-none d-lg-flex align-items-center gap-3">
            <a href="<?= BASE_URL ?>/track-order.php" style="text-decoration:none;"><i class="bi bi-geo-alt me-1"></i>Track Order</a>
            <span class="text-white-50">|</span>
            <a href="<?= BASE_URL ?>/admin/login.php" style="text-decoration:none;" target="_blank"><i class="bi bi-shield-lock me-1"></i>Staff Portal</a>
        </div>
    </div>
</div>

<!-- 2. Main Sticky Header -->
<header class="site-header">
    <div class="container py-2">
        <div class="d-flex align-items-center justify-content-between gap-3">

            <!-- Mobile Menu Toggle Button -->
            <button class="btn btn-outline-dark d-lg-none p-1 border-0" type="button" data-bs-toggle="offcanvas" data-bs-target="#mobileMenuOffcanvas" aria-controls="mobileMenuOffcanvas" aria-label="Toggle navigation">
                <i class="bi bi-list fs-3" style="color:var(--caramel-dark);"></i>
            </button>

            <!-- Brand Logo -->
            <a href="<?= BASE_URL ?>/index.php" class="navbar-brand-logo">
                <img src="<?= BASE_URL ?>/assets/images/logo.svg" alt="<?= APP_NAME ?> Horology" height="46">
            </a>

            <!-- Functional Search Bar -->
            <form action="<?= BASE_URL ?>/products.php" method="GET" class="header-search-form d-none d-md-block">
                <input type="text" name="q" class="header-search-input" placeholder="Search chronographs, automatic, SKU, brand..." value="<?= e($_GET['q'] ?? '') ?>" autocomplete="off" required>
                <button type="submit" class="header-search-btn" aria-label="Search">
                    <i class="bi bi-search"></i>
                </button>
            </form>

            <!-- Header Action Icons (Compare, Wishlist, Cart, Account) -->
            <div class="d-flex align-items-center gap-2 gap-sm-3">

                <!-- Comparison Icon with Badge -->
                <a href="<?= BASE_URL ?>/compare.php" class="header-action-btn d-none d-sm-inline-flex" title="Product Comparison" aria-label="Product Comparison">
                    <i class="bi bi-arrow-left-right"></i>
                    <span class="header-action-badge compare-badge"><?= (int)$compareCount ?></span>
                </a>

                <!-- Wishlist Icon with Badge -->
                <a href="<?= BASE_URL ?>/wishlist.php" class="header-action-btn d-none d-sm-inline-flex" title="Wishlist" aria-label="Wishlist">
                    <i class="bi bi-heart"></i>
                    <span class="header-action-badge wishlist-badge"><?= (int)$wishlistCount ?></span>
                </a>

                <!-- Shopping Bag Icon (Requires Login if Unauthenticated) -->
                <a href="<?= e($cartUrl) ?>" class="header-action-btn" title="<?= is_logged_in() ? 'View Shopping Bag' : 'Sign in to view Shopping Bag' ?>" aria-label="Shopping Bag">
                    <i class="bi bi-bag"></i>
                    <span class="header-action-badge cart-badge <?= $cartCount > 0 ? '' : 'd-none' ?>"><?= (int)$cartCount ?></span>
                </a>

                <!-- Customer Account Dropdown or Login/Register Links -->
                <?php if (is_logged_in() && $currentUser): ?>
                    <div class="dropdown">
                        <button class="btn btn-outline-dark dropdown-toggle d-flex align-items-center gap-2 py-1 px-3 rounded-pill" type="button" data-bs-toggle="dropdown" aria-expanded="false" style="border-color:var(--border-beige); font-size:0.85rem;">
                            <i class="bi bi-person-circle" style="color:var(--caramel-dark);"></i>
                            <span class="d-none d-lg-inline fw-semibold"><?= e($currentUser['first_name']) ?></span>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end shadow border-0" style="font-size:0.85rem;">
                            <li class="px-3 py-2 border-bottom bg-light">
                                <div class="fw-bold"><?= e($currentUser['first_name'] . ' ' . $currentUser['last_name']) ?></div>
                                <div class="text-muted small"><?= e($currentUser['email']) ?></div>
                            </li>
                            <li><a class="dropdown-item py-2" href="<?= BASE_URL ?>/my-account.php"><i class="bi bi-person me-2 text-muted"></i>My Account</a></li>
                            <li><a class="dropdown-item py-2" href="<?= BASE_URL ?>/my-orders.php"><i class="bi bi-box-seam me-2 text-muted"></i>My Orders</a></li>
                            <li><a class="dropdown-item py-2" href="<?= BASE_URL ?>/wishlist.php"><i class="bi bi-heart me-2 text-muted"></i>Wishlist</a></li>
                            <li><hr class="dropdown-divider my-1"></li>
                            <li><a class="dropdown-item py-2 text-danger" href="<?= BASE_URL ?>/logout.php"><i class="bi bi-box-arrow-right me-2"></i>Sign Out</a></li>
                        </ul>
                    </div>
                <?php else: ?>
                    <div class="d-flex align-items-center gap-1">
                        <a href="<?= BASE_URL ?>/login.php" class="btn btn-sm btn-outline-dark px-3 py-2 rounded-pill fw-semibold" style="font-size:0.82rem; border-color:var(--border-beige);">
                            Sign In
                        </a>
                        <a href="<?= BASE_URL ?>/register.php" class="btn btn-sm text-white px-3 py-2 rounded-pill fw-semibold d-none d-md-inline-block" style="background-color:var(--caramel-primary); font-size:0.82rem;">
                            Register
                        </a>
                    </div>
                <?php endif; ?>

            </div>

        </div>

        <!-- Mobile Search Row -->
        <div class="d-md-none mt-2 pt-1 border-top" style="border-color:var(--border-beige) !important;">
            <form action="<?= BASE_URL ?>/products.php" method="GET" class="header-search-form">
                <input type="text" name="q" class="header-search-input" placeholder="Search luxury timepieces..." value="<?= e($_GET['q'] ?? '') ?>" required>
                <button type="submit" class="header-search-btn" aria-label="Search">
                    <i class="bi bi-search"></i>
                </button>
            </form>
        </div>
    </div>

    <!-- 3. Sub-Navbar Links (Section 3.A & Navigation requirement) -->
    <nav class="sub-navbar d-none d-lg-block" aria-label="Main Category Navigation">
        <div class="container d-flex align-items-center justify-content-center gap-1">
            <a href="<?= BASE_URL ?>/index.php" class="nav-link-valenor <?= basename($_SERVER['PHP_SELF']) === 'index.php' ? 'active' : '' ?>">Home</a>
            <a href="<?= BASE_URL ?>/products.php" class="nav-link-valenor">Collections</a>

            <!-- Categories Dropdown -->
            <div class="dropdown d-inline-block">
                <a class="nav-link-valenor dropdown-toggle" href="<?= BASE_URL ?>/categories.php" id="catNavDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                    Categories
                </a>
                <ul class="dropdown-menu shadow border-0" aria-labelledby="catNavDropdown" style="font-size:0.85rem; min-width:220px;">
                    <li><a class="dropdown-item py-2 fw-bold text-uppercase" style="color:var(--caramel-dark);" href="<?= BASE_URL ?>/categories.php">All 5 Categories</a></li>
                    <li><hr class="dropdown-divider my-1"></li>
                    <?php foreach ($navCategories as $nc): ?>
                        <li><a class="dropdown-item py-2" href="<?= BASE_URL ?>/products.php?category=<?= urlencode($nc['slug']) ?>"><?= e($nc['name']) ?></a></li>
                    <?php endforeach; ?>
                </ul>
            </div>

            <a href="<?= BASE_URL ?>/products.php?filter=new_arrivals" class="nav-link-valenor">New Arrivals</a>
            <a href="<?= BASE_URL ?>/products.php?filter=sale" class="nav-link-valenor sale-highlight"><i class="bi bi-tag-fill me-1"></i>Sale</a>
            <a href="<?= BASE_URL ?>/about.php" class="nav-link-valenor">About</a>
            <a href="<?= BASE_URL ?>/contact.php" class="nav-link-valenor">Contact</a>
        </div>
    </nav>
</header>

<!-- 4. Mobile Offcanvas Navigation Drawer -->
<div class="offcanvas offcanvas-start" tabindex="-1" id="mobileMenuOffcanvas" aria-labelledby="mobileMenuOffcanvasLabel" style="background-color:var(--bg-cream); width:300px;">
    <div class="offcanvas-header border-bottom" style="border-color:var(--border-beige) !important;">
        <h5 class="offcanvas-title" id="mobileMenuOffcanvasLabel">
            <img src="<?= BASE_URL ?>/assets/images/logo.svg" alt="<?= APP_NAME ?>" height="36">
        </h5>
        <button type="button" class="btn-close text-reset" data-bs-dismiss="offcanvas" aria-label="Close"></button>
    </div>
    <div class="offcanvas-body p-0">
        <ul class="list-group list-group-flush" style="background:transparent;">
            <li class="list-group-item bg-transparent"><a href="<?= BASE_URL ?>/index.php" class="nav-link-valenor px-0"><i class="bi bi-house me-2"></i>Home</a></li>
            <li class="list-group-item bg-transparent"><a href="<?= BASE_URL ?>/products.php" class="nav-link-valenor px-0"><i class="bi bi-grid me-2"></i>Collections</a></li>
            <li class="list-group-item bg-transparent"><a href="<?= BASE_URL ?>/categories.php" class="nav-link-valenor px-0"><i class="bi bi-bookmark me-2"></i>Categories</a></li>
            <li class="list-group-item bg-transparent"><a href="<?= BASE_URL ?>/products.php?filter=new_arrivals" class="nav-link-valenor px-0"><i class="bi bi-stars me-2"></i>New Arrivals</a></li>
            <li class="list-group-item bg-transparent"><a href="<?= BASE_URL ?>/products.php?filter=sale" class="nav-link-valenor sale-highlight px-0"><i class="bi bi-percent me-2"></i>Special Offers</a></li>
            <li class="list-group-item bg-transparent"><a href="<?= BASE_URL ?>/compare.php" class="nav-link-valenor px-0"><i class="bi bi-arrow-left-right me-2"></i>Compare (<span class="compare-badge"><?= $compareCount ?></span>)</a></li>
            <li class="list-group-item bg-transparent"><a href="<?= BASE_URL ?>/wishlist.php" class="nav-link-valenor px-0"><i class="bi bi-heart me-2"></i>Wishlist (<span class="wishlist-badge"><?= $wishlistCount ?></span>)</a></li>
            <li class="list-group-item bg-transparent"><a href="<?= e($cartUrl) ?>" class="nav-link-valenor px-0"><i class="bi bi-bag me-2"></i>Shopping Bag (<span class="cart-badge"><?= $cartCount ?></span>)</a></li>
            <li class="list-group-item bg-transparent"><a href="<?= BASE_URL ?>/about.php" class="nav-link-valenor px-0"><i class="bi bi-info-circle me-2"></i>About Us</a></li>
            <li class="list-group-item bg-transparent"><a href="<?= BASE_URL ?>/contact.php" class="nav-link-valenor px-0"><i class="bi bi-envelope me-2"></i>Contact Us</a></li>
            <li class="list-group-item bg-transparent"><a href="<?= BASE_URL ?>/track-order.php" class="nav-link-valenor px-0"><i class="bi bi-geo-alt me-2"></i>Track Order</a></li>
        </ul>
        <div class="p-3 border-top mt-3" style="border-color:var(--border-beige) !important;">
            <?php if (is_logged_in() && $currentUser): ?>
                <div class="mb-2 fw-bold text-dark"><?= e($currentUser['first_name'] . ' ' . $currentUser['last_name']) ?></div>
                <a href="<?= BASE_URL ?>/my-account.php" class="btn btn-sm btn-outline-dark w-100 mb-2">My Account</a>
                <a href="<?= BASE_URL ?>/logout.php" class="btn btn-sm btn-danger w-100">Sign Out</a>
            <?php else: ?>
                <a href="<?= BASE_URL ?>/login.php" class="btn btn-sm btn-dark w-100 mb-2">Sign In</a>
                <a href="<?= BASE_URL ?>/register.php" class="btn btn-sm text-white w-100" style="background:var(--caramel-primary);">Create Account</a>
            <?php endif; ?>
        </div>
    </div>
</div>
