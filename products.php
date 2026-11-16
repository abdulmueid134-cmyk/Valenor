<?php
// =============================================================================
// VALENOR HOROLOGY — PRODUCT CATALOG & SEARCH RESULTS
// =============================================================================

require_once __DIR__ . '/config/config.php';
require_once INCLUDES_PATH . '/product-card.php';

$db = getDB();

$q        = sanitize($_GET['q'] ?? '');
$catSlug  = sanitize($_GET['category'] ?? '');
$filter   = sanitize($_GET['filter'] ?? '');
$sortBy   = sanitize($_GET['sort'] ?? 'newest');

$where = ["p.status = 'active'"];
$params = [];

if (!empty($q)) {
    $where[] = "(p.name LIKE ? OR p.brand LIKE ? OR p.sku LIKE ? OR p.short_description LIKE ?)";
    $term = "%{$q}%";
    $params = array_merge($params, [$term, $term, $term, $term]);
}

if (!empty($catSlug)) {
    $where[] = "c.slug = ?";
    $params[] = $catSlug;
}

if ($filter === 'new_arrivals') {
    $where[] = "p.is_new_arrival = 1";
} elseif ($filter === 'featured') {
    $where[] = "p.is_featured = 1";
} elseif ($filter === 'best_sellers') {
    $where[] = "p.is_best_seller = 1";
} elseif ($filter === 'sale') {
    $where[] = "(p.discount_price IS NOT NULL AND p.discount_price > 0)";
}

$orderSql = match ($sortBy) {
    'price_low'  => 'COALESCE(p.discount_price, p.regular_price) ASC',
    'price_high' => 'COALESCE(p.discount_price, p.regular_price) DESC',
    'rating'     => 'avg_rating DESC',
    'name'       => 'p.name ASC',
    default      => 'p.id DESC',
};

$whereSql = implode(' AND ', $where);

$sql = "
    SELECT p.*, c.name AS category_name, c.slug AS category_slug,
           (SELECT image_path FROM product_images WHERE product_id = p.id AND is_primary = 1 LIMIT 1) AS primary_image,
           (SELECT AVG(rating) FROM product_reviews WHERE product_id = p.id AND status = 'approved') AS avg_rating,
           (SELECT COUNT(*) FROM product_reviews WHERE product_id = p.id AND status = 'approved') AS reviews_count
    FROM products p
    JOIN categories c ON p.category_id = c.id
    WHERE {$whereSql}
    ORDER BY {$orderSql}
";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$products = $stmt->fetchAll();

// Fetch categories for sidebar filter
$catList = $db->query("SELECT * FROM categories WHERE status = 'active' ORDER BY sort_order ASC")->fetchAll();

$pageTitle = !empty($q) ? 'Search Results for "' . $q . '"' : (!empty($catSlug) ? ucwords(str_replace('-', ' ', $catSlug)) . ' Collection' : 'All Timepieces');

require_once INCLUDES_PATH . '/header.php';
require_once INCLUDES_PATH . '/navbar.php';
?>

<div class="py-4 border-bottom" style="background-color: var(--bg-white); border-color: var(--border-beige) !important;">
    <div class="container">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-1" style="font-size:0.82rem;">
                <li class="breadcrumb-item"><a href="<?= BASE_URL ?>/index.php">Home</a></li>
                <li class="breadcrumb-item"><a href="<?= BASE_URL ?>/products.php">Collections</a></li>
                <?php if (!empty($catSlug)): ?>
                    <li class="breadcrumb-item active" aria-current="page"><?= e(ucwords(str_replace('-', ' ', $catSlug))) ?></li>
                <?php elseif (!empty($q)): ?>
                    <li class="breadcrumb-item active" aria-current="page">Search: "<?= e($q) ?>"</li>
                <?php else: ?>
                    <li class="breadcrumb-item active" aria-current="page">All Watches</li>
                <?php endif; ?>
            </ol>
        </nav>
        <h1 class="h2 mb-0" style="font-family:var(--font-serif); font-weight:700;">
            <?= e($pageTitle) ?> <span class="text-muted fs-6 fw-normal">(<?= count($products) ?> items)</span>
        </h1>
    </div>
</div>

<div class="container py-5">
    <div class="row g-4">
        <!-- Sidebar Filter -->
        <aside class="col-lg-3">
            <div class="p-4 bg-white rounded border" style="border-color:var(--border-beige) !important;">
                <h5 class="mb-3 font-serif fw-bold" style="color:var(--caramel-dark);">Filter by Category</h5>
                <ul class="list-unstyled mb-4">
                    <li class="mb-2">
                        <a href="<?= BASE_URL ?>/products.php" class="<?= empty($catSlug) && empty($filter) ? 'fw-bold' : 'text-muted' ?>" style="color:var(--text-charcoal);">
                            <i class="bi bi-chevron-right small me-1"></i> All Watches
                        </a>
                    </li>
                    <?php foreach ($catList as $c): ?>
                        <li class="mb-2">
                            <a href="<?= BASE_URL ?>/products.php?category=<?= urlencode($c['slug']) ?>" class="<?= $catSlug === $c['slug'] ? 'fw-bold text-primary' : 'text-muted' ?>" style="<?= $catSlug === $c['slug'] ? 'color:var(--caramel-primary) !important;' : '' ?>">
                                <i class="bi bi-chevron-right small me-1"></i> <?= e($c['name']) ?>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>

                <h5 class="mb-3 font-serif fw-bold" style="color:var(--caramel-dark);">Special Curations</h5>
                <ul class="list-unstyled mb-0">
                    <li class="mb-2"><a href="<?= BASE_URL ?>/products.php?filter=new_arrivals" class="text-muted"><i class="bi bi-stars me-1 text-warning"></i> New Arrivals</a></li>
                    <li class="mb-2"><a href="<?= BASE_URL ?>/products.php?filter=featured" class="text-muted"><i class="bi bi-gem me-1 text-primary"></i> Featured Highlights</a></li>
                    <li class="mb-2"><a href="<?= BASE_URL ?>/products.php?filter=best_sellers" class="text-muted"><i class="bi bi-trophy me-1 text-warning"></i> Best Sellers</a></li>
                    <li class="mb-2"><a href="<?= BASE_URL ?>/products.php?filter=sale" class="text-danger fw-semibold"><i class="bi bi-tag-fill me-1"></i> 20% Privilege Sale</a></li>
                </ul>
            </div>
        </aside>

        <!-- Product Grid -->
        <main class="col-lg-9">
            <!-- Sort Bar -->
            <div class="d-flex justify-content-between align-items-center mb-4 p-3 bg-white rounded border" style="border-color:var(--border-beige) !important;">
                <div class="small text-muted">Showing <strong><?= count($products) ?></strong> luxury watches</div>
                <div class="d-flex align-items-center gap-2">
                    <label for="sortBySelect" class="small text-muted text-nowrap mb-0">Sort By:</label>
                    <select id="sortBySelect" class="form-select form-select-sm" style="width:180px;" onchange="location = this.value;">
                        <option value="<?= BASE_URL ?>/products.php?category=<?= urlencode($catSlug) ?>&q=<?= urlencode($q) ?>&filter=<?= urlencode($filter) ?>&sort=newest" <?= $sortBy === 'newest' ? 'selected' : '' ?>>Newest First</option>
                        <option value="<?= BASE_URL ?>/products.php?category=<?= urlencode($catSlug) ?>&q=<?= urlencode($q) ?>&filter=<?= urlencode($filter) ?>&sort=price_low" <?= $sortBy === 'price_low' ? 'selected' : '' ?>>Price: Low to High</option>
                        <option value="<?= BASE_URL ?>/products.php?category=<?= urlencode($catSlug) ?>&q=<?= urlencode($q) ?>&filter=<?= urlencode($filter) ?>&sort=price_high" <?= $sortBy === 'price_high' ? 'selected' : '' ?>>Price: High to Low</option>
                        <option value="<?= BASE_URL ?>/products.php?category=<?= urlencode($catSlug) ?>&q=<?= urlencode($q) ?>&filter=<?= urlencode($filter) ?>&sort=rating" <?= $sortBy === 'rating' ? 'selected' : '' ?>>Highest Rated</option>
                    </select>
                </div>
            </div>

            <?php if (!empty($products)): ?>
                <div class="row g-4">
                    <?php foreach ($products as $prod): ?>
                        <div class="col-md-6 col-xl-4">
                            <?= render_product_card($prod) ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="text-center py-5 bg-white rounded border" style="border-color:var(--border-beige) !important;">
                    <i class="bi bi-search fs-1 text-muted mb-3 d-block"></i>
                    <h3 class="font-serif">No Timepieces Found</h3>
                    <p class="text-muted">No products matched your search or category criteria.</p>
                    <a href="<?= BASE_URL ?>/products.php" class="btn btn-dark rounded-pill px-4">View All Collections</a>
                </div>
            <?php endif; ?>
        </main>
    </div>
</div>

<?php require_once INCLUDES_PATH . '/footer.php'; ?>
