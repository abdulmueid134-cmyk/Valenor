<?php
// =============================================================================
// VALENOR HOROLOGY — ALL CATEGORIES SHOWCASE (Section 3.B)
// =============================================================================

require_once __DIR__ . '/config/config.php';

$pageTitle = 'Explore Our Five Watch Categories';
$pageDesc  = 'Discover the five distinctive categories of VALENOR luxury timepieces — Chronographs, Automatic Luxury, Classic Dress, Divers, and Minimalist Elegance.';

$db = getDB();

$stmt = $db->query("
    SELECT c.*, COUNT(p.id) AS products_count
    FROM categories c
    LEFT JOIN products p ON c.id = p.category_id AND p.status = 'active'
    WHERE c.status = 'active'
    GROUP BY c.id
    ORDER BY c.sort_order ASC
");
$categories = $stmt->fetchAll();

require_once INCLUDES_PATH . '/header.php';
require_once INCLUDES_PATH . '/navbar.php';
?>

<div class="py-4 border-bottom" style="background-color: var(--bg-white); border-color: var(--border-beige) !important;">
    <div class="container">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-1" style="font-size:0.82rem;">
                <li class="breadcrumb-item"><a href="<?= BASE_URL ?>/index.php">Home</a></li>
                <li class="breadcrumb-item active" aria-current="page">Categories</li>
            </ol>
        </nav>
        <h1 class="h2 mb-0" style="font-family:var(--font-serif); font-weight:700;">
            Five Master Horological Categories
        </h1>
    </div>
</div>

<div class="container py-5">
    <div class="row g-4">
        <?php foreach ($categories as $cat): ?>
            <div class="col-md-6 col-lg-4">
                <a href="<?= BASE_URL ?>/products.php?category=<?= urlencode($cat['slug']) ?>" class="category-card" title="Browse <?= e($cat['name']) ?>">
                    <div class="category-img-box">
                        <img src="<?= e(BASE_URL . '/' . ltrim($cat['image_path'], '/')) ?>" alt="<?= e($cat['name']) ?>" loading="lazy">
                    </div>
                    <div class="category-body">
                        <h2 class="category-name"><?= e($cat['name']) ?></h2>
                        <div class="badge align-self-center mb-2" style="background:var(--caramel-dark); font-size:0.75rem;">
                            <?= (int)$cat['products_count'] ?> Timepieces
                        </div>
                        <p class="category-desc"><?= e($cat['description']) ?></p>
                        <span class="category-link">Explore Collection <i class="bi bi-arrow-right"></i></span>
                    </div>
                </a>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<?php require_once INCLUDES_PATH . '/footer.php'; ?>
