<?php
// =============================================================================
// VALENOR HOROLOGY — PRODUCT DISCOVERY SYSTEM (Phase 06)
// Search, Comprehensive Multi-Filters, Dynamic Dual-Slider Price, Sorting,
// Result Counters, Pagination, and Server-Side Parameter Validation
// =============================================================================

require_once __DIR__ . '/config/config.php';
require_once INCLUDES_PATH . '/product-card.php';

$db = getDB();

// -----------------------------------------------------------------------------
// 1. DYNAMIC PRICE RANGE BOUNDS (Requirement: Never hardcoded, updates with DB)
// -----------------------------------------------------------------------------
$maxPriceFromDb = (float)$db->query("
    SELECT CEIL(COALESCE(MAX(regular_price), 100000)) 
    FROM products 
    WHERE status = 'active'
")->fetchColumn();

// ponytail: minimum bound starts at 0 per requirement; max bound strictly dynamic from DB
$globalMinPrice = 0.0;
$globalMaxPrice = max(1000.0, $maxPriceFromDb);

// -----------------------------------------------------------------------------
// 2. VALIDATE & SANITIZE INPUT PARAMETERS (Security: Whitelist & Typed Bounds)
// -----------------------------------------------------------------------------
$q           = sanitize($_GET['q'] ?? '');
$catSlug     = sanitize($_GET['category'] ?? '');
$brand       = sanitize($_GET['brand'] ?? '');
$filter      = sanitize($_GET['filter'] ?? '');
$minRating   = isset($_GET['rating']) && is_numeric($_GET['rating']) ? max(1.0, min(5.0, (float)$_GET['rating'])) : null;
$minDiscount = isset($_GET['discount']) && is_numeric($_GET['discount']) ? max(1, min(90, (int)$_GET['discount'])) : null;
$inStockOnly = !empty($_GET['in_stock']);

// Price filtering bounds
$minPrice = isset($_GET['min_price']) && is_numeric($_GET['min_price']) ? max(0.0, (float)$_GET['min_price']) : $globalMinPrice;
$maxPrice = isset($_GET['max_price']) && is_numeric($_GET['max_price']) ? (float)$_GET['max_price'] : $globalMaxPrice;

// Sorting validation
$validSorts = ['newest', 'price_low', 'price_high', 'best_selling', 'rating', 'name'];
$sortBy = in_array($_GET['sort'] ?? '', $validSorts, true) ? $_GET['sort'] : 'newest';

// Pagination settings (ponytail: 6 items per page ensures pagination is easily testable with 10 seed products)
$perPage = 6;
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? max(1, (int)$_GET['page']) : 1;

// -----------------------------------------------------------------------------
// 3. BUILD PREPARED MYSQL QUERY
// -----------------------------------------------------------------------------
$where = ["p.status = 'active'"];
$params = [];

// Search across Name, SKU, Brand, Category Name, Tags, and Hashtags
if (!empty($q)) {
    $where[] = "(
        p.name LIKE ? 
        OR p.sku LIKE ? 
        OR p.brand LIKE ? 
        OR c.name LIKE ?
        OR EXISTS (
            SELECT 1 FROM product_tags pt 
            WHERE pt.product_id = p.id AND (pt.tag_name LIKE ? OR pt.tag_name LIKE ?)
        )
    )";
    $term = "%{$q}%";
    $hashTerm = "%#" . ltrim($q, '#') . "%";
    $params[] = $term;
    $params[] = $term;
    $params[] = $term;
    $params[] = $term;
    $params[] = $term;
    $params[] = $hashTerm;
}

// Category filter
if (!empty($catSlug)) {
    $where[] = "c.slug = ?";
    $params[] = $catSlug;
}

// Brand filter
if (!empty($brand)) {
    $where[] = "p.brand = ?";
    $params[] = $brand;
}

// Curations filter
if ($filter === 'new_arrivals') {
    $where[] = "p.is_new_arrival = 1";
} elseif ($filter === 'featured') {
    $where[] = "p.is_featured = 1";
} elseif ($filter === 'best_sellers') {
    $where[] = "p.is_best_seller = 1";
} elseif ($filter === 'sale') {
    $where[] = "(p.discount_price IS NOT NULL AND p.discount_price > 0)";
}

// Dynamic Price Range
if ($minPrice > $maxPrice) {
    $where[] = "1 = 0"; // Impossible range yields 0 results
} else {
    if ($minPrice > $globalMinPrice) {
        $where[] = "COALESCE(p.discount_price, p.regular_price) >= ?";
        $params[] = $minPrice;
    }
    if ($maxPrice < $globalMaxPrice) {
        $where[] = "COALESCE(p.discount_price, p.regular_price) <= ?";
        $params[] = $maxPrice;
    }
}

// Rating filter
if ($minRating !== null) {
    $where[] = "COALESCE((SELECT AVG(rating) FROM product_reviews WHERE product_id = p.id AND status = 'approved'), 5.0) >= ?";
    $params[] = $minRating;
}

// Discount percentage filter
if ($minDiscount !== null) {
    $where[] = "(p.discount_price IS NOT NULL AND p.discount_price > 0 AND ((p.regular_price - p.discount_price) / p.regular_price * 100) >= ?)";
    $params[] = $minDiscount;
}

// Availability filter
if ($inStockOnly) {
    $where[] = "p.stock_quantity > 0";
}

$whereSql = implode(' AND ', $where);

// Sorting clause
$orderSql = match ($sortBy) {
    'price_low'    => 'COALESCE(p.discount_price, p.regular_price) ASC, p.id DESC',
    'price_high'   => 'COALESCE(p.discount_price, p.regular_price) DESC, p.id DESC',
    'best_selling' => 'p.is_best_seller DESC, p.views_count DESC, p.id DESC',
    'rating'       => 'avg_rating DESC, reviews_count DESC, p.id DESC',
    'name'         => 'p.name ASC',
    default        => 'p.id DESC',
};

// Count total matching products for pagination
$countSql = "
    SELECT COUNT(*) 
    FROM products p 
    JOIN categories c ON p.category_id = c.id 
    WHERE {$whereSql}
";
$countStmt = $db->prepare($countSql);
$countStmt->execute($params);
$totalProducts = (int)$countStmt->fetchColumn();

$totalPages = max(1, (int)ceil($totalProducts / $perPage));
if ($page > $totalPages) {
    $page = $totalPages;
}
$offset = ($page - 1) * $perPage;

// Fetch paginated products
$sql = "
    SELECT p.*, c.name AS category_name, c.slug AS category_slug,
           (SELECT image_path FROM product_images WHERE product_id = p.id AND is_primary = 1 LIMIT 1) AS primary_image,
           (SELECT AVG(rating) FROM product_reviews WHERE product_id = p.id AND status = 'approved') AS avg_rating,
           (SELECT COUNT(*) FROM product_reviews WHERE product_id = p.id AND status = 'approved') AS reviews_count
    FROM products p
    JOIN categories c ON p.category_id = c.id
    WHERE {$whereSql}
    ORDER BY {$orderSql}
    LIMIT {$perPage} OFFSET {$offset}
";
$stmt = $db->prepare($sql);
$stmt->execute($params);
$products = $stmt->fetchAll();

// -----------------------------------------------------------------------------
// 4. METADATA FOR SIDEBAR FILTERS (Categories & Brands)
// -----------------------------------------------------------------------------
$catList = $db->query("
    SELECT c.*, COUNT(p.id) AS products_count 
    FROM categories c 
    LEFT JOIN products p ON c.id = p.category_id AND p.status = 'active'
    WHERE c.status = 'active' 
    GROUP BY c.id 
    ORDER BY c.sort_order ASC
")->fetchAll();

$brandList = $db->query("
    SELECT brand, COUNT(id) AS products_count 
    FROM products 
    WHERE status = 'active' AND brand IS NOT NULL AND brand != ''
    GROUP BY brand 
    ORDER BY brand ASC
")->fetchAll();

// Active category information
$currentCat = null;
if (!empty($catSlug)) {
    foreach ($catList as $c) {
        if ($c['slug'] === $catSlug) {
            $currentCat = $c;
            break;
        }
    }
}

// Compute active filters count for badge
$activeFiltersCount = 0;
if (!empty($catSlug)) $activeFiltersCount++;
if (!empty($brand)) $activeFiltersCount++;
if ($minPrice > $globalMinPrice || $maxPrice < $globalMaxPrice) $activeFiltersCount++;
if ($minRating !== null) $activeFiltersCount++;
if ($minDiscount !== null) $activeFiltersCount++;
if ($inStockOnly) $activeFiltersCount++;

// Helper to build preserved query string for pagination & sorting
function build_catalog_url($overrides = []) {
    $params = $_GET;
    foreach ($overrides as $k => $v) {
        if ($v === null) {
            unset($params[$k]);
        } else {
            $params[$k] = $v;
        }
    }
    return BASE_URL . '/products.php?' . http_build_query($params);
}

$pageTitle = !empty($q) ? 'Search: "' . $q . '"' : ($currentCat ? $currentCat['name'] . ' Collection' : 'Master Horology Catalogue');
$pageDesc  = $currentCat ? $currentCat['description'] : 'Explore the full spectrum of VALENOR Swiss-caliber luxury timepieces.';

require_once INCLUDES_PATH . '/header.php';
require_once INCLUDES_PATH . '/navbar.php';
?>

<!-- Dual Range Slider Custom Styling (White & Caramel Aesthetic) -->
<style>
.dual-slider-box {
    position: relative;
    width: 100%;
    height: 28px;
    margin: 12px 0 8px;
}
.dual-slider-track {
    position: absolute;
    top: 50%;
    transform: translateY(-50%);
    width: 100%;
    height: 6px;
    background: #E8DED2;
    border-radius: 4px;
    z-index: 1;
}
.dual-slider-highlight {
    position: absolute;
    top: 50%;
    transform: translateY(-50%);
    height: 6px;
    background: var(--caramel-primary, #B8895B);
    border-radius: 4px;
    z-index: 2;
}
.dual-slider-range {
    position: absolute;
    top: 50%;
    transform: translateY(-50%);
    width: 100%;
    margin: 0;
    padding: 0;
    pointer-events: none;
    -webkit-appearance: none;
    background: none;
    z-index: 3;
}
.dual-slider-range::-webkit-slider-thumb {
    height: 18px;
    width: 18px;
    border-radius: 50%;
    background: var(--caramel-primary, #B8895B);
    border: 2px solid #FFFFFF;
    box-shadow: 0 1px 4px rgba(0,0,0,0.25);
    pointer-events: auto;
    -webkit-appearance: none;
    cursor: pointer;
    transition: transform 0.15s ease;
}
.dual-slider-range::-webkit-slider-thumb:hover {
    transform: scale(1.15);
}
.dual-slider-range::-moz-range-thumb {
    height: 18px;
    width: 18px;
    border-radius: 50%;
    background: var(--caramel-primary, #B8895B);
    border: 2px solid #FFFFFF;
    box-shadow: 0 1px 4px rgba(0,0,0,0.25);
    pointer-events: auto;
    cursor: pointer;
}
.active-filter-pill {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    background: var(--bg-cream);
    border: 1px solid var(--border-beige);
    color: var(--caramel-dark);
    font-size: 0.75rem;
    padding: 2px 8px;
    border-radius: 12px;
}
.active-filter-pill a {
    color: var(--caramel-dark);
    text-decoration: none;
    font-weight: bold;
    margin-left: 2px;
}
</style>

<!-- Breadcrumbs and Header -->
<div class="py-4 border-bottom" style="background-color: var(--bg-white); border-color: var(--border-beige) !important;">
    <div class="container">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-1" style="font-size:0.82rem;">
                <li class="breadcrumb-item"><a href="<?= BASE_URL ?>/index.php" style="color:var(--text-dark);">Home</a></li>
                <li class="breadcrumb-item"><a href="<?= BASE_URL ?>/products.php" style="color:var(--text-dark);">Collections</a></li>
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
                <h1 class="h2 mb-0 font-serif fw-bold" style="color:var(--text-dark);">
                    <?= e($pageTitle) ?>
                </h1>
                <?php if ($currentCat && !empty($currentCat['description'])): ?>
                    <p class="text-muted small mb-0 mt-1"><?= e($currentCat['description']) ?></p>
                <?php endif; ?>
            </div>
            <div class="d-flex align-items-center gap-2">
                <span class="badge px-3 py-2" style="background:var(--caramel-dark); font-size:0.8rem;">
                    <?= $totalProducts ?> <?= $totalProducts === 1 ? 'Timepiece' : 'Timepieces' ?> Found
                </span>
            </div>
        </div>
    </div>
</div>

<div class="container py-5" id="catalog-main-wrapper">
    <div class="row g-4">
        
        <!-- ==================================================================
             SIDEBAR: SEARCH & FILTERS (Section 7 & 8)
             ================================================================== -->
        <aside class="col-lg-3">
            <form action="<?= BASE_URL ?>/products.php" method="GET" id="catalog-filter-form">
                <?php if (!empty($filter)): ?>
                    <input type="hidden" name="filter" value="<?= e($filter) ?>">
                <?php endif; ?>
                <?php if (!empty($sortBy)): ?>
                    <input type="hidden" name="sort" value="<?= e($sortBy) ?>">
                <?php endif; ?>

                <div class="p-4 bg-white rounded border shadow-sm" style="border-color:var(--border-beige) !important;">
                    
                    <!-- Sidebar Header with Reset Link -->
                    <div class="d-flex justify-content-between align-items-center pb-3 mb-3 border-bottom" style="border-color:var(--border-beige) !important;">
                        <h5 class="mb-0 font-serif fw-bold" style="color:var(--caramel-dark);">
                            <i class="bi bi-funnel me-1"></i> Refine Search
                            <?php if ($activeFiltersCount > 0): ?>
                                <span class="badge bg-secondary rounded-pill small ms-1"><?= $activeFiltersCount ?></span>
                            <?php endif; ?>
                        </h5>
                        <a href="<?= BASE_URL ?>/products.php" class="small text-danger fw-semibold" style="text-decoration:none;">
                            Reset All
                        </a>
                    </div>

                    <!-- 1. Text Search Input -->
                    <div class="mb-4">
                        <label class="fw-bold small text-uppercase mb-2 d-block" style="letter-spacing:0.06em; color:var(--text-charcoal);">Keyword Search</label>
                        <div class="input-group input-group-sm">
                            <input type="text" name="q" class="form-control" placeholder="Name, SKU, Brand, #tag..." value="<?= e($q) ?>">
                            <button class="btn btn-outline-dark" type="submit" aria-label="Search" style="border-color:var(--border-beige);">
                                <i class="bi bi-search"></i>
                            </button>
                        </div>
                    </div>

                    <!-- 2. Master Categories -->
                    <div class="mb-4 pt-3 border-top" style="border-color:var(--border-beige) !important;">
                        <label class="fw-bold small text-uppercase mb-2 d-block" style="letter-spacing:0.06em; color:var(--text-charcoal);">Categories</label>
                        <div class="list-group list-group-flush border-0">
                            <a href="<?= build_catalog_url(['category' => null, 'page' => 1]) ?>" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center px-0 py-1 border-0 <?= empty($catSlug) ? 'fw-bold' : 'text-muted' ?>" style="font-size:0.85rem; <?= empty($catSlug) ? 'color:var(--caramel-primary) !important;' : '' ?>">
                                <span><i class="bi bi-chevron-right small me-1"></i> All Timepieces</span>
                                <span class="badge rounded-pill bg-light text-dark border"><?= array_sum(array_column($catList, 'products_count')) ?></span>
                            </a>
                            <?php foreach ($catList as $c): ?>
                                <a href="<?= build_catalog_url(['category' => $c['slug'], 'page' => 1]) ?>" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center px-0 py-1 border-0 <?= $catSlug === $c['slug'] ? 'fw-bold' : 'text-muted' ?>" style="font-size:0.85rem; <?= $catSlug === $c['slug'] ? 'color:var(--caramel-primary) !important;' : '' ?>">
                                    <span><i class="bi bi-chevron-right small me-1"></i> <?= e($c['name']) ?></span>
                                    <span class="badge rounded-pill <?= $catSlug === $c['slug'] ? 'bg-primary text-white' : 'bg-light text-dark border' ?>"><?= (int)$c['products_count'] ?></span>
                                </a>
                            <?php endforeach; ?>
                        </div>
                        <?php if (!empty($catSlug)): ?>
                            <input type="hidden" name="category" value="<?= e($catSlug) ?>">
                        <?php endif; ?>
                    </div>

                    <!-- 3. Brand Filter -->
                    <?php if (!empty($brandList)): ?>
                        <div class="mb-4 pt-3 border-top" style="border-color:var(--border-beige) !important;">
                            <label class="fw-bold small text-uppercase mb-2 d-block" style="letter-spacing:0.06em; color:var(--text-charcoal);">Manufacture Brand</label>
                            <div class="d-flex flex-column gap-1">
                                <?php foreach ($brandList as $b): ?>
                                    <div class="form-check small">
                                        <input class="form-check-input filter-auto-submit" type="radio" name="brand" value="<?= e($b['brand']) ?>" id="brand_<?= e(slugify($b['brand'])) ?>" <?= $brand === $b['brand'] ? 'checked' : '' ?>>
                                        <label class="form-check-label d-flex justify-content-between align-items-center w-100" for="brand_<?= e(slugify($b['brand'])) ?>">
                                            <span><?= e($b['brand']) ?></span>
                                            <span class="text-muted">(<?= (int)$b['products_count'] ?>)</span>
                                        </label>
                                    </div>
                                <?php endforeach; ?>
                                <?php if (!empty($brand)): ?>
                                    <a href="<?= build_catalog_url(['brand' => null, 'page' => 1]) ?>" class="small text-muted mt-1">&times; Clear Brand Filter</a>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- 4. Dynamic Dual-Handle Price Range Slider -->
                    <div class="mb-4 pt-3 border-top" style="border-color:var(--border-beige) !important;">
                        <label class="fw-bold small text-uppercase mb-1 d-block" style="letter-spacing:0.06em; color:var(--text-charcoal);">
                            Price Range (PKR)
                        </label>
                        <small class="text-muted d-block mb-2" style="font-size:0.75rem;">Dynamic max bound: <?= format_price($globalMaxPrice) ?></small>

                        <!-- Dual Range Interactive Slider -->
                        <div class="dual-slider-box position-relative">
                            <div class="dual-slider-track"></div>
                            <div class="dual-slider-highlight" id="sliderHighlight"></div>
                            <input type="range" class="dual-slider-range" id="sliderMin" min="0" max="<?= (int)$globalMaxPrice ?>" step="1000" value="<?= (int)$minPrice ?>" aria-label="Minimum Price Slider">
                            <input type="range" class="dual-slider-range" id="sliderMax" min="0" max="<?= (int)$globalMaxPrice ?>" step="1000" value="<?= (int)$maxPrice ?>" aria-label="Maximum Price Slider">
                        </div>

                        <!-- Manual Numeric Inputs -->
                        <div class="d-flex align-items-center gap-2 mb-2">
                            <div class="flex-grow-1">
                                <label for="min_price_input" class="visually-hidden">Min Price</label>
                                <div class="input-group input-group-sm">
                                    <span class="input-group-text p-1 text-muted small">Rs</span>
                                    <input type="number" id="min_price_input" name="min_price" class="form-control form-control-sm text-center px-1" value="<?= (int)$minPrice ?>" min="0" max="<?= (int)$globalMaxPrice ?>" step="1000" placeholder="0">
                                </div>
                            </div>
                            <span class="text-muted">—</span>
                            <div class="flex-grow-1">
                                <label for="max_price_input" class="visually-hidden">Max Price</label>
                                <div class="input-group input-group-sm">
                                    <span class="input-group-text p-1 text-muted small">Rs</span>
                                    <input type="number" id="max_price_input" name="max_price" class="form-control form-control-sm text-center px-1" value="<?= (int)$maxPrice ?>" min="0" max="<?= (int)$globalMaxPrice ?>" step="1000" placeholder="<?= (int)$globalMaxPrice ?>">
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- 5. Minimum Rating Filter -->
                    <div class="mb-4 pt-3 border-top" style="border-color:var(--border-beige) !important;">
                        <label class="fw-bold small text-uppercase mb-2 d-block" style="letter-spacing:0.06em; color:var(--text-charcoal);">Collector Rating</label>
                        <select name="rating" class="form-select form-select-sm filter-auto-submit" aria-label="Filter by rating">
                            <option value="">All Ratings</option>
                            <option value="4.5" <?= $minRating == 4.5 ? 'selected' : '' ?>>★ 4.5 & Above (Masterpieces)</option>
                            <option value="4.0" <?= $minRating == 4.0 ? 'selected' : '' ?>>★ 4.0 & Above (Highly Rated)</option>
                            <option value="3.0" <?= $minRating == 3.0 ? 'selected' : '' ?>>★ 3.0 & Above</option>
                        </select>
                    </div>

                    <!-- 6. Promotional Discount Filter -->
                    <div class="mb-4 pt-3 border-top" style="border-color:var(--border-beige) !important;">
                        <label class="fw-bold small text-uppercase mb-2 d-block" style="letter-spacing:0.06em; color:var(--text-charcoal);">Privilege Discount</label>
                        <select name="discount" class="form-select form-select-sm filter-auto-submit" aria-label="Filter by discount">
                            <option value="">All Offers</option>
                            <option value="10" <?= $minDiscount === 10 ? 'selected' : '' ?>>10% Off or Greater</option>
                            <option value="20" <?= $minDiscount === 20 ? 'selected' : '' ?>>20% Off or Greater</option>
                            <option value="30" <?= $minDiscount === 30 ? 'selected' : '' ?>>30% Off or Greater</option>
                        </select>
                    </div>

                    <!-- 7. Stock Availability Filter -->
                    <div class="mb-4 pt-3 border-top" style="border-color:var(--border-beige) !important;">
                        <label class="fw-bold small text-uppercase mb-2 d-block" style="letter-spacing:0.06em; color:var(--text-charcoal);">Availability</label>
                        <div class="form-check">
                            <input class="form-check-input filter-auto-submit" type="checkbox" name="in_stock" value="1" id="in_stock_check" <?= $inStockOnly ? 'checked' : '' ?>>
                            <label class="form-check-label small" for="in_stock_check">
                                In Stock Only
                            </label>
                        </div>
                    </div>

                    <!-- Apply & Reset Buttons -->
                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-sm btn-dark rounded-pill py-2 fw-semibold" style="letter-spacing:0.05em;">
                            Apply Filters
                        </button>
                        <a href="<?= BASE_URL ?>/products.php" class="btn btn-sm btn-outline-secondary rounded-pill py-2">
                            Reset All Filters
                        </a>
                    </div>

                    <!-- Quick Curations -->
                    <div class="mt-4 pt-3 border-top" style="border-color:var(--border-beige) !important;">
                        <label class="fw-bold small text-uppercase mb-2 d-block" style="letter-spacing:0.06em; color:var(--text-charcoal);">Quick Curations</label>
                        <ul class="list-unstyled mb-0" style="font-size:0.84rem;">
                            <li class="mb-2"><a href="<?= BASE_URL ?>/products.php?filter=new_arrivals" class="text-muted text-decoration-none <?= $filter === 'new_arrivals' ? 'fw-bold text-dark' : '' ?>"><i class="bi bi-stars me-1 text-warning"></i> New Arrivals</a></li>
                            <li class="mb-2"><a href="<?= BASE_URL ?>/products.php?filter=featured" class="text-muted text-decoration-none <?= $filter === 'featured' ? 'fw-bold text-dark' : '' ?>"><i class="bi bi-gem me-1 text-primary"></i> Featured Highlights</a></li>
                            <li class="mb-2"><a href="<?= BASE_URL ?>/products.php?filter=best_sellers" class="text-muted text-decoration-none <?= $filter === 'best_sellers' ? 'fw-bold text-dark' : '' ?>"><i class="bi bi-trophy me-1 text-warning"></i> Best Sellers</a></li>
                            <li class="mb-2"><a href="<?= BASE_URL ?>/products.php?filter=sale" class="text-danger fw-semibold text-decoration-none <?= $filter === 'sale' ? 'fw-bold text-danger' : '' ?>"><i class="bi bi-tag-fill me-1"></i> Privilege Sale</a></li>
                        </ul>
                    </div>

                </div>
            </form>
        </aside>

        <!-- ==================================================================
             MAIN: TOP BAR, RESULTS GRID & PAGINATION
             ================================================================== -->
        <main class="col-lg-9" id="catalog-products-section">
            
            <!-- Top Controls Bar: Results Count & Sorter -->
            <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 p-3 bg-white rounded border gap-3 shadow-sm" style="border-color:var(--border-beige) !important;">
                <div class="small text-muted" id="results-count-container">
                    <?php if ($totalProducts > 0): ?>
                        Showing <strong><?= $offset + 1 ?></strong>–<strong><?= min($offset + $perPage, $totalProducts) ?></strong> of <strong><?= $totalProducts ?></strong> handcrafted timepieces
                    <?php else: ?>
                        Showing <strong>0</strong> timepieces
                    <?php endif; ?>

                    <!-- Active Filter Tags -->
                    <div class="d-flex flex-wrap gap-1 mt-1">
                        <?php if (!empty($q)): ?>
                            <span class="active-filter-pill">Search: "<?= e($q) ?>" <a href="<?= build_catalog_url(['q' => null, 'page' => 1]) ?>">&times;</a></span>
                        <?php endif; ?>
                        <?php if (!empty($catSlug) && $currentCat): ?>
                            <span class="active-filter-pill"><?= e($currentCat['name']) ?> <a href="<?= build_catalog_url(['category' => null, 'page' => 1]) ?>">&times;</a></span>
                        <?php endif; ?>
                        <?php if (!empty($brand)): ?>
                            <span class="active-filter-pill">Brand: <?= e($brand) ?> <a href="<?= build_catalog_url(['brand' => null, 'page' => 1]) ?>">&times;</a></span>
                        <?php endif; ?>
                        <?php if ($minPrice > $globalMinPrice || $maxPrice < $globalMaxPrice): ?>
                            <span class="active-filter-pill">Rs. <?= number_format($minPrice) ?> – <?= number_format($maxPrice) ?> <a href="<?= build_catalog_url(['min_price' => null, 'max_price' => null, 'page' => 1]) ?>">&times;</a></span>
                        <?php endif; ?>
                        <?php if ($minRating !== null): ?>
                            <span class="active-filter-pill">★ <?= $minRating ?>+ <a href="<?= build_catalog_url(['rating' => null, 'page' => 1]) ?>">&times;</a></span>
                        <?php endif; ?>
                        <?php if ($minDiscount !== null): ?>
                            <span class="active-filter-pill">≥ <?= $minDiscount ?>% Off <a href="<?= build_catalog_url(['discount' => null, 'page' => 1]) ?>">&times;</a></span>
                        <?php endif; ?>
                        <?php if ($inStockOnly): ?>
                            <span class="active-filter-pill">In Stock Only <a href="<?= build_catalog_url(['in_stock' => null, 'page' => 1]) ?>">&times;</a></span>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Sorting Dropdown (Requirement: low to high, high to low, newest, best selling, highest rated) -->
                <div class="d-flex align-items-center gap-2">
                    <label for="catalogSortSelect" class="small text-muted text-nowrap mb-0 fw-semibold">Sort By:</label>
                    <select id="catalogSortSelect" class="form-select form-select-sm" style="width:200px;" onchange="updateSort(this.value);">
                        <option value="newest" <?= $sortBy === 'newest' ? 'selected' : '' ?>>Newest First</option>
                        <option value="price_low" <?= $sortBy === 'price_low' ? 'selected' : '' ?>>Price: Low to High</option>
                        <option value="price_high" <?= $sortBy === 'price_high' ? 'selected' : '' ?>>Price: High to Low</option>
                        <option value="best_selling" <?= $sortBy === 'best_selling' ? 'selected' : '' ?>>Best Selling</option>
                        <option value="rating" <?= $sortBy === 'rating' ? 'selected' : '' ?>>Highest Rated</option>
                        <option value="name" <?= $sortBy === 'name' ? 'selected' : '' ?>>Name: A to Z</option>
                    </select>
                </div>
            </div>

            <!-- Products Grid Container -->
            <div id="products-grid-container">
                <?php if (!empty($products)): ?>
                    <div class="row g-4">
                        <?php foreach ($products as $prod): ?>
                            <div class="col-md-6 col-xl-4">
                                <?= render_product_card($prod) ?>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- Bootstrap 5 Pagination -->
                    <?php if ($totalPages > 1): ?>
                        <nav aria-label="Catalog pagination" class="mt-5" id="pagination-container">
                            <ul class="pagination justify-content-center">
                                <!-- Previous -->
                                <li class="page-item <?= ($page <= 1) ? 'disabled' : '' ?>">
                                    <a class="page-link" href="<?= build_catalog_url(['page' => $page - 1]) ?>" aria-label="Previous">
                                        &laquo; Previous
                                    </a>
                                </li>

                                <!-- Page Numbers -->
                                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                    <li class="page-item <?= ($page === $i) ? 'active' : '' ?>">
                                        <a class="page-link" href="<?= build_catalog_url(['page' => $i]) ?>" style="<?= ($page === $i) ? 'background-color:var(--caramel-primary); border-color:var(--caramel-primary); color:#fff;' : 'color:var(--text-dark);' ?>">
                                            <?= $i ?>
                                        </a>
                                    </li>
                                <?php endfor; ?>

                                <!-- Next -->
                                <li class="page-item <?= ($page >= $totalPages) ? 'disabled' : '' ?>">
                                    <a class="page-link" href="<?= build_catalog_url(['page' => $page + 1]) ?>" aria-label="Next">
                                        Next &raquo;
                                    </a>
                                </li>
                            </ul>
                        </nav>
                    <?php endif; ?>

                <?php else: ?>
                    <!-- Informative Empty State When No Products Match -->
                    <div class="text-center py-5 bg-white rounded border shadow-sm p-4" style="border-color:var(--border-beige) !important;">
                        <div class="mb-3">
                            <span class="d-inline-flex align-items-center justify-content-center rounded-circle" style="width:70px; height:70px; background-color:var(--bg-cream); border:1px solid var(--border-beige);">
                                <i class="bi bi-search fs-2" style="color:var(--caramel-primary);"></i>
                            </span>
                        </div>
                        <h3 class="font-serif fw-bold">No Matching Timepieces Found</h3>
                        <p class="text-muted mx-auto" style="max-width:480px;">
                            We could not find any timepieces matching your active criteria:
                            <?php if (!empty($q)): ?><strong>"<?= e($q) ?>"</strong><?php endif; ?>
                            <?php if ($minPrice > $globalMinPrice || $maxPrice < $globalMaxPrice): ?>
                                in price range <strong>Rs. <?= number_format($minPrice) ?> – <?= number_format($maxPrice) ?></strong>
                            <?php endif; ?>.
                            Try loosening filters or reset your selection to view our entire collection.
                        </p>
                        <div class="d-flex justify-content-center gap-2 mt-3">
                            <a href="<?= BASE_URL ?>/products.php" class="btn btn-valenor-primary">
                                Reset All Filters
                            </a>
                            <a href="<?= BASE_URL ?>/categories.php" class="btn btn-outline-dark" style="border-color:var(--border-beige);">
                                Browse Categories
                            </a>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

        </main>
    </div>
</div>

<!-- Dynamic Dual Range Slider & AJAX Fetch Script (Ponytail: native browser APIs, zero external libraries) -->
<script>
document.addEventListener('DOMContentLoaded', function () {
    const sliderMin = document.getElementById('sliderMin');
    const sliderMax = document.getElementById('sliderMax');
    const inputMin = document.getElementById('min_price_input');
    const inputMax = document.getElementById('max_price_input');
    const highlight = document.getElementById('sliderHighlight');
    const filterForm = document.getElementById('catalog-filter-form');

    const globalMax = <?= (float)$globalMaxPrice ?>;

    // 1. Dual Slider Synchronization
    function updateSliderVisuals() {
        if (!sliderMin || !sliderMax || !highlight) return;
        const minVal = parseInt(sliderMin.value);
        const maxVal = parseInt(sliderMax.value);

        const leftPercent = (minVal / globalMax) * 100;
        const rightPercent = 100 - (maxVal / globalMax) * 100;

        highlight.style.left = `${leftPercent}%`;
        highlight.style.right = `${rightPercent}%`;

        if (inputMin) inputMin.value = minVal;
        if (inputMax) inputMax.value = maxVal;
    }

    if (sliderMin && sliderMax) {
        sliderMin.addEventListener('input', function () {
            if (parseInt(sliderMin.value) > parseInt(sliderMax.value) - 1000) {
                sliderMin.value = parseInt(sliderMax.value) - 1000;
            }
            updateSliderVisuals();
        });

        sliderMax.addEventListener('input', function () {
            if (parseInt(sliderMax.value) < parseInt(sliderMin.value) + 1000) {
                sliderMax.value = parseInt(sliderMin.value) + 1000;
            }
            updateSliderVisuals();
        });

        if (inputMin) {
            inputMin.addEventListener('change', function () {
                let val = Math.max(0, Math.min(parseInt(this.value) || 0, parseInt(sliderMax.value) - 1000));
                sliderMin.value = val;
                updateSliderVisuals();
            });
        }

        if (inputMax) {
            inputMax.addEventListener('change', function () {
                let val = Math.min(globalMax, Math.max(parseInt(this.value) || globalMax, parseInt(sliderMin.value) + 1000));
                sliderMax.value = val;
                updateSliderVisuals();
            });
        }

        updateSliderVisuals();
    }

    // 2. Fetch API for smooth AJAX catalog loading (ponytail: progressive enhancement, ladder rung 4)
    function fetchFilteredCatalog(url) {
        const section = document.getElementById('catalog-products-section');
        if (section) section.style.opacity = '0.5';

        fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(res => res.text())
            .then(html => {
                const parser = new DOMParser();
                const doc = parser.parseFromString(html, 'text/html');

                const newGrid = doc.getElementById('products-grid-container');
                const newCounts = doc.getElementById('results-count-container');

                if (newGrid && document.getElementById('products-grid-container')) {
                    document.getElementById('products-grid-container').innerHTML = newGrid.innerHTML;
                }
                if (newCounts && document.getElementById('results-count-container')) {
                    document.getElementById('results-count-container').innerHTML = newCounts.innerHTML;
                }

                if (section) section.style.opacity = '1';
                window.history.pushState(null, '', url);
            })
            .catch(() => {
                window.location.href = url;
            });
    }

    // Intercept pagination clicks for AJAX
    document.addEventListener('click', function (e) {
        const pageLink = e.target.closest('#pagination-container a.page-link');
        if (pageLink && pageLink.href && !pageLink.parentElement.classList.contains('disabled')) {
            e.preventDefault();
            fetchFilteredCatalog(pageLink.href);
        }
    });

    // Auto-submit quick filters
    document.querySelectorAll('.filter-auto-submit').forEach(el => {
        el.addEventListener('change', function () {
            if (filterForm) filterForm.submit();
        });
    });
});

// Sorting update
function updateSort(val) {
    const url = new URL(window.location.href);
    url.searchParams.set('sort', val);
    url.searchParams.set('page', '1');
    window.location.href = url.toString();
}
</script>

<?php require_once INCLUDES_PATH . '/footer.php'; ?>
