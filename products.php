<?php
// =============================================================================
// VALENOR HOROLOGY — PRODUCT CATALOG & DYNAMIC SEARCH/FILTER SYSTEM (Section 7 & 8)
// =============================================================================

require_once __DIR__ . '/config/config.php';
require_once INCLUDES_PATH . '/product-card.php';

$db = getDB();

// Dynamic Maximum Price Calculation from MySQL (Requirement 7: never hardcoded)
$priceStats = $db->query("
    SELECT 
        FLOOR(MIN(COALESCE(discount_price, regular_price))) AS min_bound,
        CEIL(MAX(regular_price)) AS max_bound
    FROM products 
    WHERE status = 'active'
")->fetch();

$globalMinPrice = 0;
$globalMaxPrice = (float)($priceStats['max_bound'] ?? 150000);

// Filter Parameters
$q           = sanitize($_GET['q'] ?? '');
$catSlug     = sanitize($_GET['category'] ?? '');
$filter      = sanitize($_GET['filter'] ?? '');
$sortBy      = sanitize($_GET['sort'] ?? 'newest');
$minPrice    = isset($_GET['min_price']) && is_numeric($_GET['min_price']) ? max(0, (float)$_GET['min_price']) : 0;
$maxPrice    = isset($_GET['max_price']) && is_numeric($_GET['max_price']) ? min($globalMaxPrice, (float)$_GET['max_price']) : $globalMaxPrice;
$inStockOnly = !empty($_GET['in_stock']);

$where = ["p.status = 'active'"];
$params = [];

// 1. Text Search (Name, Brand, SKU, Description, Tags, Hashtags)
if (!empty($q)) {
    $where[] = "(
        p.name LIKE ? 
        OR p.brand LIKE ? 
        OR p.sku LIKE ? 
        OR p.short_description LIKE ?
        OR EXISTS (
            SELECT 1 FROM product_tags pt 
            WHERE pt.product_id = p.id AND pt.tag_name LIKE ?
        )
    )";
    $term = "%{$q}%";
    $params = array_merge($params, [$term, $term, $term, $term, $term]);
}

// 2. Category Filter
if (!empty($catSlug)) {
    $where[] = "c.slug = ?";
    $params[] = $catSlug;
}

// 3. Special Curations Filter
if ($filter === 'new_arrivals') {
    $where[] = "p.is_new_arrival = 1";
} elseif ($filter === 'featured') {
    $where[] = "p.is_featured = 1";
} elseif ($filter === 'best_sellers') {
    $where[] = "p.is_best_seller = 1";
} elseif ($filter === 'sale') {
    $where[] = "(p.discount_price IS NOT NULL AND p.discount_price > 0)";
}

// 4. Dynamic Price Range Filter (Requirement 7)
if ($minPrice > 0) {
    $where[] = "COALESCE(p.discount_price, p.regular_price) >= ?";
    $params[] = $minPrice;
}
if ($maxPrice < $globalMaxPrice) {
    $where[] = "COALESCE(p.discount_price, p.regular_price) <= ?";
    $params[] = $maxPrice;
}

// 5. Stock Availability Filter
if ($inStockOnly) {
    $where[] = "p.stock_quantity > 0";
}

// Sorting logic
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

// Categories with live product counts for filter sidebar
$catList = $db->query("
    SELECT c.*, COUNT(p.id) AS products_count 
    FROM categories c 
    LEFT JOIN products p ON c.id = p.category_id AND p.status = 'active'
    WHERE c.status = 'active' 
    GROUP BY c.id 
    ORDER BY c.sort_order ASC
")->fetchAll();

// Current active category object
$currentCat = null;
if (!empty($catSlug)) {
    foreach ($catList as $c) {
        if ($c['slug'] === $catSlug) {
            $currentCat = $c;
            break;
        }
    }
}

$pageTitle = !empty($q) ? 'Search: "' . $q . '"' : ($currentCat ? $currentCat['name'] . ' Collection' : 'Master Horology Catalogue');
$pageDesc  = $currentCat ? $currentCat['description'] : 'Explore the full spectrum of VALENOR Swiss-caliber luxury timepieces.';

require_once INCLUDES_PATH . '/header.php';
require_once INCLUDES_PATH . '/navbar.php';
?>

<!-- Breadcrumbs and Header -->
<div class="py-4 border-bottom" style="background-color: var(--bg-white); border-color: var(--border-beige) !important;">
    <div class="container">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-1" style="font-size:0.82rem;">
                <li class="breadcrumb-item"><a href="<?= BASE_URL ?>/index.php">Home</a></li>
                <li class="breadcrumb-item"><a href="<?= BASE_URL ?>/products.php">Collections</a></li>
                <?php if ($currentCat): ?>
                    <li class="breadcrumb-item active" aria-current="page"><?= e($currentCat['name']) ?></li>
                <?php elseif (!empty($q)): ?>
                    <li class="breadcrumb-item active" aria-current="page">Search: "<?= e($q) ?>"</li>
                <?php else: ?>
                    <li class="breadcrumb-item active" aria-current="page">All Timepieces</li>
                <?php endif; ?>
            </ol>
        </nav>
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
            <div>
                <h1 class="h2 mb-0" style="font-family:var(--font-serif); font-weight:700;">
                    <?= e($pageTitle) ?>
                </h1>
                <?php if ($currentCat && !empty($currentCat['description'])): ?>
                    <p class="text-muted small mb-0 mt-1"><?= e($currentCat['description']) ?></p>
                <?php endif; ?>
            </div>
            <span class="badge px-3 py-2" style="background:var(--caramel-dark); font-size:0.8rem;">
                <?= count($products) ?> Timepieces Displayed
            </span>
        </div>
    </div>
</div>

<div class="container py-5">
    <div class="row g-4">
        
        <!-- Sidebar Filter System (Requirements 7 & 8) -->
        <aside class="col-lg-3">
            <form action="<?= BASE_URL ?>/products.php" method="GET" id="catalog-filter-form">
                <?php if (!empty($q)): ?>
                    <input type="hidden" name="q" value="<?= e($q) ?>">
                <?php endif; ?>
                <?php if (!empty($filter)): ?>
                    <input type="hidden" name="filter" value="<?= e($filter) ?>">
                <?php endif; ?>
                <?php if (!empty($sortBy)): ?>
                    <input type="hidden" name="sort" value="<?= e($sortBy) ?>">
                <?php endif; ?>

                <div class="p-4 bg-white rounded border" style="border-color:var(--border-beige) !important;">
                    
                    <!-- Header with Reset Button -->
                    <div class="d-flex justify-content-between align-items-center pb-3 mb-3 border-bottom" style="border-color:var(--border-beige) !important;">
                        <h5 class="mb-0 font-serif fw-bold" style="color:var(--caramel-dark);"><i class="bi bi-funnel me-1"></i> Filters</h5>
                        <a href="<?= BASE_URL ?>/products.php<?= !empty($catSlug) ? '?category=' . urlencode($catSlug) : '' ?>" class="small text-muted" style="text-decoration:underline;">Reset</a>
                    </div>

                    <!-- Category Filter -->
                    <div class="mb-4">
                        <label class="fw-bold small text-uppercase mb-2 d-block" style="letter-spacing:0.08em; color:var(--text-charcoal);">Categories</label>
                        <div class="list-group list-group-flush border-0">
                            <a href="<?= BASE_URL ?>/products.php<?= !empty($q) ? '?q=' . urlencode($q) : '' ?>" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center px-0 py-1 border-0 <?= empty($catSlug) ? 'fw-bold' : 'text-muted' ?>" style="font-size:0.88rem; <?= empty($catSlug) ? 'color:var(--caramel-primary) !important;' : '' ?>">
                                <span><i class="bi bi-chevron-right small me-1"></i> All Timepieces</span>
                                <span class="badge rounded-pill bg-light text-dark border"><?= array_sum(array_column($catList, 'products_count')) ?></span>
                            </a>
                            <?php foreach ($catList as $c): ?>
                                <a href="<?= BASE_URL ?>/products.php?category=<?= urlencode($c['slug']) ?><?= !empty($q) ? '&q=' . urlencode($q) : '' ?>" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center px-0 py-1 border-0 <?= $catSlug === $c['slug'] ? 'fw-bold' : 'text-muted' ?>" style="font-size:0.88rem; <?= $catSlug === $c['slug'] ? 'color:var(--caramel-primary) !important;' : '' ?>">
                                    <span><i class="bi bi-chevron-right small me-1"></i> <?= e($c['name']) ?></span>
                                    <span class="badge rounded-pill <?= $catSlug === $c['slug'] ? 'bg-primary text-white' : 'bg-light text-dark border' ?>"><?= (int)$c['products_count'] ?></span>
                                </a>
                            <?php endforeach; ?>
                        </div>
                        <?php if (!empty($catSlug)): ?>
                            <input type="hidden" name="category" value="<?= e($catSlug) ?>">
                        <?php endif; ?>
                    </div>

                    <!-- Dynamic Price Range Filter (Requirement 7) -->
                    <div class="mb-4 pt-3 border-top" style="border-color:var(--border-beige) !important;">
                        <label class="fw-bold small text-uppercase mb-2 d-block" style="letter-spacing:0.08em; color:var(--text-charcoal);">Price Range (PKR)</label>
                        
                        <div class="d-flex align-items-center gap-2 mb-3">
                            <div>
                                <label for="min_price_input" class="visually-hidden">Minimum Price</label>
                                <input type="number" id="min_price_input" name="min_price" class="form-control form-control-sm text-center" value="<?= (int)$minPrice ?>" min="0" max="<?= (int)$globalMaxPrice ?>" step="1000" placeholder="Min">
                            </div>
                            <span class="text-muted">—</span>
                            <div>
                                <label for="max_price_input" class="visually-hidden">Maximum Price</label>
                                <input type="number" id="max_price_input" name="max_price" class="form-control form-control-sm text-center" value="<?= (int)$maxPrice ?>" min="0" max="<?= (int)$globalMaxPrice ?>" step="1000" placeholder="Max">
                            </div>
                        </div>

                        <!-- Price Slider -->
                        <label for="price_slider" class="visually-hidden">Price Upper Limit Slider</label>
                        <input type="range" class="form-range" id="price_slider" min="0" max="<?= (int)$globalMaxPrice ?>" step="2000" value="<?= (int)$maxPrice ?>" oninput="document.getElementById('max_price_input').value = this.value; document.getElementById('price_display_val').innerText = Number(this.value).toLocaleString();">
                        
                        <div class="d-flex justify-content-between text-muted small">
                            <span>Rs. 0</span>
                            <span>Max: Rs. <strong id="price_display_val"><?= number_format($maxPrice) ?></strong></span>
                        </div>
                    </div>

                    <!-- In Stock Filter -->
                    <div class="mb-4 pt-3 border-top" style="border-color:var(--border-beige) !important;">
                        <label class="fw-bold small text-uppercase mb-2 d-block" style="letter-spacing:0.08em; color:var(--text-charcoal);">Stock Availability</label>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="in_stock" value="1" id="in_stock_check" <?= $inStockOnly ? 'checked' : '' ?>>
                            <label class="form-check-label small" for="in_stock_check">
                                In Stock Only
                            </label>
                        </div>
                    </div>

                    <!-- Filter Apply Buttons -->
                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-sm btn-dark rounded-pill py-2 fw-semibold" style="letter-spacing:0.05em;">
                            Apply Filters
                        </button>
                        <a href="<?= BASE_URL ?>/products.php" class="btn btn-sm btn-outline-secondary rounded-pill py-2">
                            Clear All Filters
                        </a>
                    </div>

                    <!-- Curated Highlights -->
                    <div class="mt-4 pt-3 border-top" style="border-color:var(--border-beige) !important;">
                        <label class="fw-bold small text-uppercase mb-2 d-block" style="letter-spacing:0.08em; color:var(--text-charcoal);">Quick Curations</label>
                        <ul class="list-unstyled mb-0" style="font-size:0.86rem;">
                            <li class="mb-2"><a href="<?= BASE_URL ?>/products.php?filter=new_arrivals" class="text-muted"><i class="bi bi-stars me-1 text-warning"></i> New Arrivals</a></li>
                            <li class="mb-2"><a href="<?= BASE_URL ?>/products.php?filter=featured" class="text-muted"><i class="bi bi-gem me-1 text-primary"></i> Featured Highlights</a></li>
                            <li class="mb-2"><a href="<?= BASE_URL ?>/products.php?filter=best_sellers" class="text-muted"><i class="bi bi-trophy me-1 text-warning"></i> Best Sellers</a></li>
                            <li class="mb-2"><a href="<?= BASE_URL ?>/products.php?filter=sale" class="text-danger fw-semibold"><i class="bi bi-tag-fill me-1"></i> 20% Privilege Sale</a></li>
                        </ul>
                    </div>

                </div>
            </form>
        </aside>

        <!-- Product Grid (9 Columns) -->
        <main class="col-lg-9">
            
            <!-- Top Controls Bar: Results Count & Sorter -->
            <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 p-3 bg-white rounded border gap-3" style="border-color:var(--border-beige) !important;">
                <div class="small text-muted">
                    Showing <strong><?= count($products) ?></strong> of <strong>10</strong> handcrafted timepieces
                    <?php if (!empty($q)): ?>
                        for <span class="badge bg-light text-dark border">"<?= e($q) ?>"</span>
                    <?php endif; ?>
                    <?php if ($minPrice > 0 || $maxPrice < $globalMaxPrice): ?>
                        within <span class="badge bg-light text-dark border">Rs. <?= number_format($minPrice) ?> – Rs. <?= number_format($maxPrice) ?></span>
                    <?php endif; ?>
                </div>

                <!-- Sorting Dropdown (Requirement 8) -->
                <div class="d-flex align-items-center gap-2">
                    <label for="catalogSortSelect" class="small text-muted text-nowrap mb-0">Sort By:</label>
                    <select id="catalogSortSelect" class="form-select form-select-sm" style="width:200px;" onchange="updateSort(this.value);">
                        <option value="newest" <?= $sortBy === 'newest' ? 'selected' : '' ?>>Newest First</option>
                        <option value="price_low" <?= $sortBy === 'price_low' ? 'selected' : '' ?>>Price: Low to High</option>
                        <option value="price_high" <?= $sortBy === 'price_high' ? 'selected' : '' ?>>Price: High to Low</option>
                        <option value="rating" <?= $sortBy === 'rating' ? 'selected' : '' ?>>Highest Rated</option>
                        <option value="name" <?= $sortBy === 'name' ? 'selected' : '' ?>>Name: A to Z</option>
                    </select>
                </div>
            </div>

            <!-- Product Cards Grid (Section 3.C) -->
            <?php if (!empty($products)): ?>
                <div class="row g-4">
                    <?php foreach ($products as $prod): ?>
                        <div class="col-md-6 col-xl-4">
                            <?= render_product_card($prod) ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <!-- No Results Empty State -->
                <div class="text-center py-5 bg-white rounded border" style="border-color:var(--border-beige) !important;">
                    <i class="bi bi-search fs-1 text-muted mb-3 d-block"></i>
                    <h3 class="font-serif">No Matching Timepieces Found</h3>
                    <p class="text-muted mx-auto" style="max-width:480px;">
                        No products matched your active filters or price range. Try broadening your criteria or reset filters.
                    </p>
                    <a href="<?= BASE_URL ?>/products.php" class="btn btn-dark rounded-pill px-4 py-2 mt-2">
                        Reset All Filters
                    </a>
                </div>
            <?php endif; ?>

        </main>
    </div>
</div>

<script>
function updateSort(val) {
    const url = new URL(window.location.href);
    url.searchParams.set('sort', val);
    window.location.href = url.toString();
}
</script>

<?php require_once INCLUDES_PATH . '/footer.php'; ?>
