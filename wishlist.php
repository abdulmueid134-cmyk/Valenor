<?php
// =============================================================================
// VALENOR HOROLOGY — COLLECTOR WISHLIST
// =============================================================================

require_once __DIR__ . '/config/config.php';
require_once INCLUDES_PATH . '/product-card.php';

$db = getDB();
$wishlistIds = get_wishlist_ids();
$products = [];

if (!empty($wishlistIds)) {
    $placeholders = implode(',', array_fill(0, count($wishlistIds), '?'));
    $stmt = $db->prepare("
        SELECT p.*, c.name AS category_name, c.slug AS category_slug,
               (SELECT image_path FROM product_images WHERE product_id = p.id AND is_primary = 1 LIMIT 1) AS primary_image,
               (SELECT AVG(rating) FROM product_reviews WHERE product_id = p.id AND status = 'approved') AS avg_rating,
               (SELECT COUNT(*) FROM product_reviews WHERE product_id = p.id AND status = 'approved') AS reviews_count
        FROM products p
        JOIN categories c ON p.category_id = c.id
        WHERE p.id IN ({$placeholders})
    ");
    $stmt->execute($wishlistIds);
    $products = $stmt->fetchAll();
}

$pageTitle = 'My Saved Timepieces (' . count($products) . ' items)';
require_once INCLUDES_PATH . '/header.php';
require_once INCLUDES_PATH . '/navbar.php';
?>

<div class="py-4 border-bottom" style="background-color: var(--bg-white); border-color: var(--border-beige) !important;">
    <div class="container">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-1" style="font-size:0.82rem;">
                <li class="breadcrumb-item"><a href="<?= BASE_URL ?>/index.php">Home</a></li>
                <li class="breadcrumb-item active" aria-current="page">Wishlist</li>
            </ol>
        </nav>
        <h1 class="h2 mb-0 font-serif fw-bold">My Saved Wishlist</h1>
    </div>
</div>

<div class="container py-5">
    <?php if (!empty($products)): ?>
        <div class="row g-4">
            <?php foreach ($products as $prod): ?>
                <div class="col-sm-6 col-lg-4 col-xl-3">
                    <?= render_product_card($prod) ?>
                </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="text-center py-5 bg-white rounded border p-5" style="border-color:var(--border-beige) !important; max-width:600px; margin:0 auto;">
            <i class="bi bi-heart fs-1 text-muted mb-3 d-block"></i>
            <h2 class="font-serif">Your Wishlist is Empty</h2>
            <p class="text-muted">Save your favorite luxury chronographs and automatic watches for future consideration.</p>
            <a href="<?= BASE_URL ?>/products.php" class="btn-valenor-primary mt-2">Discover Collection &rarr;</a>
        </div>
    <?php endif; ?>
</div>

<?php require_once INCLUDES_PATH . '/footer.php'; ?>
