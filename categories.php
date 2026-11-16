<?php
// =============================================================================
// VALENOR HOROLOGY — ALL FIVE CATEGORIES SHOWCASE (Section 3.B)
// Dynamic display of exactly 5 categories, each containing 2 luxury timepieces
// =============================================================================

require_once __DIR__ . '/config/config.php';
require_once INCLUDES_PATH . '/product-card.php';

$pageTitle = 'The Five Master Horological Categories — VALENOR';
$pageDesc  = 'Discover the five distinctive categories of VALENOR luxury timepieces — Chronographs, Automatic Luxury, Classic Dress, Divers, and Minimalist Elegance.';

$db = getDB();

// 1. Fetch exactly the 5 active categories
$stmt = $db->query("
    SELECT c.*, COUNT(p.id) AS products_count
    FROM categories c
    LEFT JOIN products p ON c.id = p.category_id AND p.status = 'active'
    WHERE c.status = 'active'
    GROUP BY c.id
    ORDER BY c.sort_order ASC
");
$categories = $stmt->fetchAll();

// 2. Pre-fetch the products for each category
$categoryProducts = [];
foreach ($categories as $cat) {
    $pStmt = $db->prepare("
        SELECT p.*, c.name AS category_name, c.slug AS category_slug,
               (SELECT image_path FROM product_images WHERE product_id = p.id AND is_primary = 1 LIMIT 1) AS primary_image,
               (SELECT AVG(rating) FROM product_reviews WHERE product_id = p.id AND status = 'approved') AS avg_rating,
               (SELECT COUNT(*) FROM product_reviews WHERE product_id = p.id AND status = 'approved') AS reviews_count
        FROM products p
        JOIN categories c ON p.category_id = c.id
        WHERE p.category_id = ? AND p.status = 'active'
        ORDER BY p.id ASC
    ");
    $pStmt->execute([$cat['id']]);
    $categoryProducts[$cat['id']] = $pStmt->fetchAll();
}

require_once INCLUDES_PATH . '/header.php';
require_once INCLUDES_PATH . '/navbar.php';
?>

<!-- Breadcrumbs Header -->
<div class="py-4 border-bottom" style="background-color: var(--bg-white); border-color: var(--border-beige) !important;">
    <div class="container">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-1" style="font-size:0.82rem;">
                <li class="breadcrumb-item"><a href="<?= BASE_URL ?>/index.php">Home</a></li>
                <li class="breadcrumb-item active" aria-current="page">Categories</li>
            </ol>
        </nav>
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
            <div>
                <h1 class="h2 mb-1" style="font-family:var(--font-serif); font-weight:700;">
                    Five Master Horological Categories
                </h1>
                <p class="text-muted small mb-0">
                    Precision engineering meets enduring Swiss tradition across our five specialized watchmaking collections.
                </p>
            </div>
            <a href="<?= BASE_URL ?>/products.php" class="btn btn-outline-dark rounded-pill px-4 btn-sm">
                View All Timepieces (10)
            </a>
        </div>
    </div>
</div>

<div class="container py-5">
    
    <!-- Top 5 Categories Cards Strip -->
    <div class="row g-4 mb-5">
        <?php foreach ($categories as $cat): ?>
            <div class="col-md-6 col-lg-4">
                <a href="#cat-section-<?= $cat['id'] ?>" class="category-card" title="Browse <?= e($cat['name']) ?>">
                    <div class="category-img-box">
                        <img src="<?= e(BASE_URL . '/' . ltrim($cat['image_path'], '/')) ?>" alt="<?= e($cat['name']) ?>" loading="lazy">
                    </div>
                    <div class="category-body">
                        <h2 class="category-name"><?= e($cat['name']) ?></h2>
                        <div class="badge align-self-center mb-2" style="background:var(--caramel-dark); font-size:0.75rem;">
                            <?= (int)$cat['products_count'] ?> Sample Timepieces
                        </div>
                        <p class="category-desc"><?= e($cat['description']) ?></p>
                        <span class="category-link">View Timepieces <i class="bi bi-arrow-down"></i></span>
                    </div>
                </a>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- Category by Category Deep Dive with their 2 Sample Products -->
    <?php foreach ($categories as $idx => $cat): ?>
        <section id="cat-section-<?= $cat['id'] ?>" class="mb-5 pt-4 <?= $idx > 0 ? 'border-top' : '' ?>" style="border-color:var(--border-beige) !important;">
            <div class="d-flex flex-wrap justify-content-between align-items-end mb-4 gap-2">
                <div>
                    <span class="section-eyebrow">Category <?= $idx + 1 ?> of 5</span>
                    <h2 class="font-serif fw-bold mb-1" style="color:var(--text-charcoal);"><?= e($cat['name']) ?></h2>
                    <p class="text-muted small mb-0" style="max-width:600px;"><?= e($cat['description']) ?></p>
                </div>
                <a href="<?= BASE_URL ?>/products.php?category=<?= urlencode($cat['slug']) ?>" class="btn btn-sm btn-dark rounded-pill px-4 py-2">
                    Open <?= e($cat['name']) ?> Collection <i class="bi bi-arrow-right ms-1"></i>
                </a>
            </div>

            <div class="row g-4">
                <?php if (!empty($categoryProducts[$cat['id']])): ?>
                    <?php foreach ($categoryProducts[$cat['id']] as $prod): ?>
                        <div class="col-md-6 col-lg-6">
                            <?= render_product_card($prod) ?>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="col-12 text-muted small">No timepieces currently assigned to this category.</div>
                <?php endif; ?>
            </div>
        </section>
    <?php endforeach; ?>

</div>

<?php require_once INCLUDES_PATH . '/footer.php'; ?>
