<?php
// =============================================================================
// VALENOR HOROLOGY — PRODUCT DETAIL PAGE (Section 3.D & Section 4)
// Professional 1–10 Image Gallery with Interactive Thumbnails, Zoom, Variants, 
// Specs, Customer Reviews & Verified Buyer Submission Modal
// =============================================================================

require_once __DIR__ . '/config/config.php';
require_once INCLUDES_PATH . '/product-card.php';

$db = getDB();

$id   = (int)($_GET['id'] ?? 0);
$slug = sanitize($_GET['slug'] ?? '');

if ($id > 0) {
    $stmt = $db->prepare("
        SELECT p.*, c.name AS category_name, c.slug AS category_slug
        FROM products p
        JOIN categories c ON p.category_id = c.id
        WHERE p.id = ? AND p.status = 'active'
        LIMIT 1
    ");
    $stmt->execute([$id]);
} else {
    $stmt = $db->prepare("
        SELECT p.*, c.name AS category_name, c.slug AS category_slug
        FROM products p
        JOIN categories c ON p.category_id = c.id
        WHERE p.slug = ? AND p.status = 'active'
        LIMIT 1
    ");
    $stmt->execute([$slug]);
}

$product = $stmt->fetch();

if (!$product) {
    header("HTTP/1.0 404 Not Found");
    $pageTitle = "Product Not Found";
    require_once INCLUDES_PATH . '/header.php';
    require_once INCLUDES_PATH . '/navbar.php';
    echo '<div class="container py-5 text-center my-5">' .
         '<i class="bi bi-watch fs-1 text-muted d-block mb-3"></i>' .
         '<h2 class="font-serif">Timepiece Not Found</h2>' .
         '<p class="text-muted">The timepiece you requested is currently unavailable or does not exist.</p>' .
         '<a href="' . BASE_URL . '/products.php" class="btn btn-dark rounded-pill px-4 mt-2">Explore All Collections</a>' .
         '</div>';
    require_once INCLUDES_PATH . '/footer.php';
    exit;
}

$productId = (int)$product['id'];

// Increment views count in database
$db->prepare("UPDATE products SET views_count = views_count + 1 WHERE id = ?")->execute([$productId]);

// 1. Fetch all product images (1–10 images, Section 4)
$imgStmt = $db->prepare("SELECT * FROM product_images WHERE product_id = ? ORDER BY is_primary DESC, sort_order ASC, id ASC");
$imgStmt->execute([$productId]);
$images = $imgStmt->fetchAll();

// 2. Fetch product tags & hashtags (Section 5)
$tagStmt = $db->prepare("SELECT tag_name, tag_type FROM product_tags WHERE product_id = ? ORDER BY tag_type DESC, id ASC");
$tagStmt->execute([$productId]);
$tags = $tagStmt->fetchAll();

// 3. Fetch product variants (colors/sizes)
$varStmt = $db->prepare("SELECT * FROM product_variants WHERE product_id = ? ORDER BY id ASC");
$varStmt->execute([$productId]);
$variants = $varStmt->fetchAll();

// 4. Fetch customer reviews for this product
$revStmt = $db->prepare("
    SELECT pr.*, CONCAT(c.first_name, ' ', SUBSTRING(c.last_name, 1, 1), '.') AS customer_name
    FROM product_reviews pr
    JOIN customers c ON pr.customer_id = c.id
    WHERE pr.product_id = ? AND pr.status = 'approved'
    ORDER BY pr.id DESC
");
$revStmt->execute([$productId]);
$reviews = $revStmt->fetchAll();

// Check logged in user status & review eligibility
$loggedInCustomerId = is_logged_in() ? (int)current_user_id() : 0;
$hasDeliveredPurchase = false;
$myExistingReview = null;

if ($loggedInCustomerId > 0) {
    // Verified Delivered purchase check
    $dpStmt = $db->prepare("
        SELECT o.id 
        FROM order_items oi
        JOIN orders o ON oi.order_id = o.id
        WHERE oi.product_id = ? AND o.customer_id = ? AND o.order_status = 'Delivered'
        LIMIT 1
    ");
    $dpStmt->execute([$productId, $loggedInCustomerId]);
    $hasDeliveredPurchase = (bool)$dpStmt->fetch();

    $myRevStmt = $db->prepare("SELECT * FROM product_reviews WHERE product_id = ? AND customer_id = ? LIMIT 1");
    $myRevStmt->execute([$productId, $loggedInCustomerId]);
    $myExistingReview = $myRevStmt->fetch();
}

// Compute average rating and count
$totalRatings = count($reviews);
$avgRating = $totalRatings > 0 ? array_sum(array_column($reviews, 'rating')) / $totalRatings : 5.0;

// Specifications array
$specs = !empty($product['specifications']) ? json_decode($product['specifications'], true) : [];

// Related products in same category
$relStmt = $db->prepare("
    SELECT p.*, c.name AS category_name, c.slug AS category_slug,
           (SELECT image_path FROM product_images WHERE product_id = p.id AND is_primary = 1 LIMIT 1) AS primary_image,
           (SELECT AVG(rating) FROM product_reviews WHERE product_id = p.id AND status = 'approved') AS avg_rating,
           (SELECT COUNT(*) FROM product_reviews WHERE product_id = p.id AND status = 'approved') AS reviews_count
    FROM products p
    JOIN categories c ON p.category_id = c.id
    WHERE p.category_id = ? AND p.id != ? AND p.status = 'active'
    LIMIT 4
");
$relStmt->execute([$product['category_id'], $productId]);
$related = $relStmt->fetchAll();

$pageTitle = $product['name'] . ' — ' . $product['category_name'];
$pageDesc  = $product['short_description'];

$isWishlisted = is_wishlisted($productId);
$isCompared   = is_compared($productId);

require_once INCLUDES_PATH . '/header.php';
require_once INCLUDES_PATH . '/navbar.php';
?>

<!-- Breadcrumbs -->
<div class="py-3 border-bottom" style="background-color: var(--bg-white); border-color: var(--border-beige) !important;">
    <div class="container">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0" style="font-size:0.82rem;">
                <li class="breadcrumb-item"><a href="<?= BASE_URL ?>/index.php">Home</a></li>
                <li class="breadcrumb-item"><a href="<?= BASE_URL ?>/products.php">Collections</a></li>
                <li class="breadcrumb-item"><a href="<?= BASE_URL ?>/products.php?category=<?= urlencode($product['category_slug']) ?>"><?= e($product['category_name']) ?></a></li>
                <li class="breadcrumb-item active" aria-current="page"><?= e($product['name']) ?></li>
            </ol>
        </nav>
    </div>
</div>

<div class="container py-5">
    <div class="row g-5">

        <!-- ==================================================================
             LEFT: IMAGE GALLERY (Section 4: 1 to 10 Images + Interactive Zoom)
             ================================================================== -->
        <div class="col-lg-6">
            <div class="sticky-top" style="top:90px; z-index:1;">
                
                <!-- Main Showcase Image with Zoom -->
                <div class="main-gallery-view mb-3 rounded border overflow-hidden position-relative bg-white" id="mainGalleryBox" style="border-color:var(--border-beige) !important; padding-top:95%; cursor:zoom-in;">
                    <?php 
                    $primaryImg = !empty($images) ? $images[0]['image_path'] : 'assets/images/watch-mens-chronograph.jpg';
                    ?>
                    <img id="main-product-img" src="<?= e(BASE_URL . '/' . ltrim($primaryImg, '/')) ?>" alt="<?= e($product['name']) ?>" style="position:absolute; top:0; left:0; width:100%; height:100%; object-fit:cover; transition:transform 0.25s ease, opacity 0.2s ease;">
                    
                    <?php if (!empty($product['discount_percentage']) && $product['discount_percentage'] > 0): ?>
                        <span class="valenor-badge valenor-badge-sale position-absolute top-0 start-0 m-3 fs-6">
                            -<?= (int)$product['discount_percentage'] ?>% PRIVILEGE
                        </span>
                    <?php endif; ?>

                    <div class="position-absolute bottom-0 end-0 m-3 d-flex gap-2">
                        <button type="button" class="btn btn-sm btn-light border rounded-circle shadow-sm" id="btn-zoom-modal" title="Click to Expand Fullscreen" style="width:36px; height:36px;">
                            <i class="bi bi-arrows-fullscreen"></i>
                        </button>
                    </div>
                </div>

                <!-- Thumbnails Strip (1 to 10 images) -->
                <?php if (count($images) > 1): ?>
                    <div class="d-flex flex-wrap gap-2 justify-content-start" id="gallery-thumbnails">
                        <?php foreach ($images as $idx => $img): ?>
                            <button type="button" class="btn p-0 border rounded overflow-hidden gallery-thumb-btn <?= $idx === 0 ? 'border-primary' : '' ?>" data-src="<?= e(BASE_URL . '/' . ltrim($img['image_path'], '/')) ?>" style="width:72px; height:72px; background:#fff; border-color:var(--border-beige) !important;" aria-label="View photo <?= $idx + 1 ?>">
                                <img src="<?= e(BASE_URL . '/' . ltrim($img['image_path'], '/')) ?>" alt="Thumbnail <?= $idx + 1 ?>" style="width:100%; height:100%; object-fit:cover;">
                            </button>
                        <?php endforeach; ?>
                    </div>
                    <div class="text-muted small mt-2">
                        <i class="bi bi-info-circle me-1"></i> Showing <?= count($images) ?> high-resolution angles of this timepiece. Hover image to zoom.
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- ==================================================================
             RIGHT: PRODUCT SPECIFICATION & ORDER CONTROLS (Section 3.D)
             ================================================================== -->
        <div class="col-lg-6">
            <!-- Brand & Category -->
            <div class="d-flex align-items-center justify-content-between mb-1">
                <span class="text-uppercase fw-bold" style="color:var(--caramel-primary); letter-spacing:0.18em; font-size:0.78rem;">
                    <?= e($product['brand']) ?> &bull; <?= e($product['category_name']) ?>
                </span>
                <span class="badge bg-light text-muted border" style="font-size:0.75rem;">
                    SKU: <strong id="product-sku-display"><?= e($product['sku']) ?></strong>
                </span>
            </div>

            <h1 class="display-6 font-serif fw-bold mt-1 mb-2" style="color:var(--text-charcoal); line-height:1.2;">
                <?= e($product['name']) ?>
            </h1>

            <!-- Ratings, Reviews & Views -->
            <div class="d-flex align-items-center gap-3 mb-3">
                <a href="#reviews-pane" onclick="document.getElementById('reviews-tab').click();" class="text-decoration-none">
                    <?= star_rating_html($avgRating, $totalRatings) ?>
                </a>
                <span class="text-muted">&bull;</span>
                <span class="text-muted small"><i class="bi bi-eye me-1"></i><?= (int)$product['views_count'] ?> views</span>
                <span class="text-muted">&bull;</span>
                <span class="text-success small fw-semibold"><i class="bi bi-patch-check-fill me-1"></i>Official Warranty</span>
            </div>

            <!-- Price Display (Section 3.D) -->
            <div class="p-3 rounded mb-4" style="background-color:var(--bg-cream); border:1px solid var(--border-beige);">
                <div class="d-flex align-items-baseline gap-3">
                    <span class="fs-2 fw-bold" id="product-display-price" style="color:var(--caramel-dark);">
                        <?= format_price($product['discount_price'] ?: $product['regular_price']) ?>
                    </span>
                    <?php if (!empty($product['discount_price']) && $product['discount_price'] < $product['regular_price']): ?>
                        <span class="fs-5 text-muted text-decoration-line-through" id="product-regular-price">
                            <?= format_price($product['regular_price']) ?>
                        </span>
                        <span class="badge bg-danger" id="product-save-badge">Save <?= format_price($product['regular_price'] - $product['discount_price']) ?></span>
                    <?php endif; ?>
                </div>
                <div class="mt-2 text-muted small d-flex flex-wrap gap-3">
                    <span><i class="bi bi-truck text-success me-1"></i> Free Insured Delivery</span>
                    <span><i class="bi bi-cash me-1 text-primary"></i> Cash on Delivery (COD) Eligible</span>
                    <span><i class="bi bi-box-seam me-1 text-warning"></i> Luxury Presentation Box</span>
                </div>
            </div>

            <!-- Short Description (Section 3.D) -->
            <p class="lead mb-4" style="font-size:1.02rem; line-height:1.7; color:#444;">
                <?= e($product['short_description']) ?>
            </p>

            <!-- Product Variants: Colors and Sizes (Section 3.D & 5) -->
            <?php if (!empty($variants)): ?>
                <div class="mb-4 p-3 bg-white rounded border" style="border-color:var(--border-beige) !important;">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <label class="form-label fw-bold small text-uppercase mb-0" style="letter-spacing:0.08em; color:var(--text-charcoal);">
                            Available Editions &amp; Straps:
                        </label>
                        <span class="small text-muted" id="selected-variant-label">Selected: <?= e($variants[0]['color'] ?: 'Standard') ?></span>
                    </div>
                    
                    <div class="d-flex flex-wrap gap-2" id="variant-selector-group">
                        <?php foreach ($variants as $idx => $v): ?>
                            <button type="button" 
                                    class="btn btn-sm btn-outline-dark variant-btn <?= $idx === 0 ? 'active' : '' ?>" 
                                    data-variant-id="<?= (int)$v['id'] ?>" 
                                    data-variant-name="<?= e($v['color'] ?: 'Standard') ?> <?= $v['size'] ? '— ' . e($v['size']) : '' ?>"
                                    data-additional="<?= (float)$v['additional_price'] ?>"
                                    data-stock="<?= (int)$v['stock_quantity'] ?>"
                                    data-sku="<?= e($v['sku']) ?>"
                                    style="font-size:0.84rem; border-color:var(--border-beige);">
                                <i class="bi bi-circle-fill me-1 small" style="color:var(--caramel-primary);"></i>
                                <?= e($v['color'] ?: 'Standard') ?> <?= $v['size'] ? '— ' . e($v['size']) : '' ?>
                                <?php if ($v['additional_price'] > 0): ?>
                                    <span class="badge bg-light text-dark ms-1">+<?= format_price($v['additional_price']) ?></span>
                                <?php endif; ?>
                            </button>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Stock Availability & Quantity Selector (Section 3.D) -->
            <div class="row g-3 align-items-center mb-4">
                <div class="col-auto">
                    <label class="form-label fw-bold small text-uppercase mb-1" style="letter-spacing:0.08em;">Quantity:</label>
                    <div class="input-group input-group-sm" style="width:130px;">
                        <button class="btn btn-outline-secondary px-3" type="button" id="qty-minus" aria-label="Decrease quantity">-</button>
                        <input type="number" id="detail-qty" class="form-control text-center fw-bold" value="1" min="1" max="<?= max(1, (int)$product['stock_quantity']) ?>">
                        <button class="btn btn-outline-secondary px-3" type="button" id="qty-plus" aria-label="Increase quantity">+</button>
                    </div>
                </div>
                <div class="col">
                    <div class="small fw-semibold mt-3" id="stock-status-box">
                        <?php if ($product['stock_quantity'] > 0): ?>
                            <span class="text-success"><i class="bi bi-check-circle-fill me-1"></i> In Stock (<span id="available-stock-count"><?= (int)$product['stock_quantity'] ?></span> timepieces available)</span>
                        <?php else: ?>
                            <span class="text-danger"><i class="bi bi-x-circle-fill me-1"></i> Temporarily Out of Stock</span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Action Buttons: Add to Cart, Buy Now, Wishlist, Compare (Section 3.D) -->
            <div class="d-flex flex-wrap gap-2 mb-4">
                <!-- Add to Cart (AJAX) -->
                <button type="button" class="btn-valenor-primary flex-grow-1 justify-content-center btn-detail-add-cart" id="btn-detail-add" data-product-id="<?= $productId ?>">
                    <i class="bi bi-bag-plus"></i> Add to Shopping Bag
                </button>

                <!-- Buy Now (Direct Add & Redirect) -->
                <button type="button" class="btn btn-dark rounded px-4 py-2 fw-semibold d-inline-flex align-items-center gap-2" id="btn-buy-now" style="font-size:0.85rem; letter-spacing:0.05em; text-transform:uppercase;">
                    <i class="bi bi-lightning-charge-fill text-warning"></i> Buy Now
                </button>

                <!-- Wishlist Button -->
                <button type="button" class="btn btn-outline-dark rounded btn-toggle-wishlist <?= $isWishlisted ? 'active' : '' ?>" data-product-id="<?= $productId ?>" title="<?= $isWishlisted ? 'Remove from Wishlist' : 'Save to Wishlist' ?>" style="border-color:var(--border-beige); min-width:44px;" aria-label="Add to Wishlist">
                    <i class="bi <?= $isWishlisted ? 'bi-heart-fill text-danger' : 'bi-heart' ?>"></i>
                </button>

                <!-- Compare Button -->
                <button type="button" class="btn btn-outline-dark rounded btn-toggle-compare <?= $isCompared ? 'active' : '' ?>" data-product-id="<?= $productId ?>" title="<?= $isCompared ? 'Remove from Comparison' : 'Add to Comparison' ?>" style="border-color:var(--border-beige); min-width:44px;" aria-label="Compare Product">
                    <i class="bi bi-arrow-left-right"></i>
                </button>
            </div>

            <!-- Product Tags & Hashtags (Section 3.D & 5) -->
            <?php if (!empty($tags)): ?>
                <div class="mb-4">
                    <span class="small text-muted me-2"><i class="bi bi-tags me-1"></i>Tags:</span>
                    <?php foreach ($tags as $t): ?>
                        <a href="<?= BASE_URL ?>/products.php?q=<?= urlencode($t['tag_name']) ?>" class="badge bg-light text-dark border me-1 mb-1 text-decoration-none" style="font-size:0.75rem; border-color:var(--border-beige) !important;">
                            <?= e($t['tag_name']) ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <!-- Trust Markers Banner -->
            <div class="p-3 border rounded bg-white" style="border-color:var(--border-beige) !important; font-size:0.84rem;">
                <div class="row g-2">
                    <div class="col-sm-6"><i class="bi bi-patch-check-fill text-success me-1"></i> 100% Certified Authentic</div>
                    <div class="col-sm-6"><i class="bi bi-truck text-primary me-1"></i> BlueEx Insured Courier Delivery</div>
                    <div class="col-sm-6"><i class="bi bi-arrow-counterclockwise text-warning me-1"></i> 14-Day Complimentary Returns</div>
                    <div class="col-sm-6"><i class="bi bi-shield-lock-fill text-success me-1"></i> 2-Year International Warranty</div>
                </div>
            </div>

        </div>
    </div>

    <!-- ==================================================================
         TABS: DESCRIPTION, SPECIFICATIONS & REVIEWS (Section 3.D)
         ================================================================== -->
    <div class="mt-5 pt-4 border-top" style="border-color:var(--border-beige) !important;">
        <ul class="nav nav-tabs justify-content-center border-0 gap-3" id="productDetailTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active px-4 py-2 fw-bold font-serif fs-5" id="desc-tab" data-bs-toggle="tab" data-bs-target="#desc-pane" type="button" role="tab" style="color:var(--text-charcoal); border:none; border-bottom:2px solid var(--caramel-primary);">
                    Full Description
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link px-4 py-2 fw-bold font-serif fs-5 text-muted" id="specs-tab" data-bs-toggle="tab" data-bs-target="#specs-pane" type="button" role="tab" style="border:none;">
                    Horological Specifications
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link px-4 py-2 fw-bold font-serif fs-5 text-muted" id="reviews-tab" data-bs-toggle="tab" data-bs-target="#reviews-pane" type="button" role="tab" style="border:none;">
                    Client Reviews (<?= $totalRatings ?>)
                </button>
            </li>
        </ul>

        <div class="tab-content pt-4" id="productDetailTabsContent">
            
            <!-- Tab 1: Full Description -->
            <div class="tab-pane fade show active" id="desc-pane" role="tabpanel" tabindex="0">
                <div class="mx-auto bg-white p-4 rounded border" style="max-width:850px; line-height:1.8; color:#333; border-color:var(--border-beige) !important;">
                    <h4 class="font-serif fw-bold mb-3" style="color:var(--caramel-dark);">Master Craftsmanship &amp; Heritage</h4>
                    <p><?= nl2br(e($product['full_description'])) ?></p>
                </div>
            </div>

            <!-- Tab 2: Specifications Table (Section 3.D) -->
            <div class="tab-pane fade" id="specs-pane" role="tabpanel" tabindex="0">
                <div class="mx-auto" style="max-width:850px;">
                    <div class="bg-white rounded border overflow-hidden" style="border-color:var(--border-beige) !important;">
                        <table class="table table-bordered table-striped mb-0" style="border-color:var(--border-beige);">
                            <tbody>
                                <tr>
                                    <th style="width:35%; background:var(--bg-cream); color:var(--text-charcoal);">Brand</th>
                                    <td><?= e($product['brand']) ?></td>
                                </tr>
                                <tr>
                                    <th style="background:var(--bg-cream); color:var(--text-charcoal);">Model / Series</th>
                                    <td><?= e($product['name']) ?></td>
                                </tr>
                                <tr>
                                    <th style="background:var(--bg-cream); color:var(--text-charcoal);">Master Category</th>
                                    <td><?= e($product['category_name']) ?></td>
                                </tr>
                                <tr>
                                    <th style="background:var(--bg-cream); color:var(--text-charcoal);">Model Reference (SKU)</th>
                                    <td><?= e($product['sku']) ?></td>
                                </tr>
                                <?php if (!empty($specs)): ?>
                                    <?php foreach ($specs as $s): ?>
                                        <tr>
                                            <th style="background:var(--bg-cream); color:var(--text-charcoal);"><?= e($s['key']) ?></th>
                                            <td><?= e($s['value']) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Tab 3: Customer Reviews & Submission Modal (Section 3.D) -->
            <div class="tab-pane fade" id="reviews-pane" role="tabpanel" tabindex="0">
                <div class="mx-auto" style="max-width:850px;">
                    
                    <!-- Review Aggregate Bar -->
                    <div class="p-4 bg-white border rounded mb-4 d-flex flex-wrap justify-content-between align-items-center gap-3" style="border-color:var(--border-beige) !important;">
                        <div>
                            <h4 class="mb-1 font-serif fw-bold" style="color:var(--caramel-dark);">Customer Experience</h4>
                            <div class="d-flex align-items-center gap-2">
                                <span class="display-6 fw-bold text-dark"><?= number_format($avgRating, 1) ?></span>
                                <div>
                                    <?= star_rating_html($avgRating) ?>
                                    <div class="text-muted small">Based on <?= $totalRatings ?> verified reviews</div>
                                </div>
                            </div>
                        </div>
                        <div>
                            <?php if (!empty($myExistingReview)): ?>
                                <button type="button" class="btn btn-outline-dark rounded-pill px-4 py-2 edit-my-review-btn" 
                                    data-rating="<?= (int)$myExistingReview['rating'] ?>"
                                    data-title="<?= htmlspecialchars($myExistingReview['title'], ENT_QUOTES, 'UTF-8') ?>"
                                    data-text="<?= htmlspecialchars($myExistingReview['review_text'], ENT_QUOTES, 'UTF-8') ?>"
                                    data-id="<?= (int)$myExistingReview['id'] ?>"
                                    data-image="<?= htmlspecialchars($myExistingReview['review_image'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                                    <i class="bi bi-pencil me-1"></i> Edit Your Review
                                </button>
                            <?php elseif ($hasDeliveredPurchase): ?>
                                <button type="button" class="btn btn-dark rounded-pill px-4 py-2" data-bs-toggle="modal" data-bs-target="#reviewModal" onclick="resetReviewModal()">
                                    <i class="bi bi-patch-check-fill text-warning me-1"></i> Write a Verified Review
                                </button>
                            <?php elseif (!is_logged_in()): ?>
                                <a href="<?= BASE_URL ?>/login.php?redirect=product-detail.php?id=<?= $productId ?>" class="btn btn-outline-dark rounded-pill px-4 py-2">
                                    <i class="bi bi-box-arrow-in-right me-1"></i> Sign In to Review
                                </a>
                            <?php else: ?>
                                <div class="text-end">
                                    <button type="button" class="btn btn-outline-secondary rounded-pill px-3 py-2 disabled" title="Available to verified owners after order delivery">
                                        <i class="bi bi-shield-lock me-1"></i> Verified Buyer Exclusive
                                    </button>
                                    <div class="small text-muted mt-1" style="font-size:0.72rem;">Available after delivery completion.</div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Reviews List -->
                    <?php if (!empty($reviews)): ?>
                        <?php foreach ($reviews as $rev): 
                            $isMyRev = ($loggedInCustomerId > 0 && (int)$rev['customer_id'] === $loggedInCustomerId);
                        ?>
                            <div class="p-4 bg-white border rounded mb-3 <?= $isMyRev ? 'border-primary-subtle shadow-sm' : '' ?>" style="border-color:var(--border-beige) !important;">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <div class="d-flex align-items-center gap-2">
                                        <div class="rounded-circle bg-light d-flex align-items-center justify-content-center fw-bold" style="width:36px; height:36px; color:var(--caramel-dark); border:1px solid var(--border-beige);">
                                            <?= strtoupper(substr($rev['customer_name'], 0, 1)) ?>
                                        </div>
                                        <div>
                                            <strong style="color:var(--text-charcoal);"><?= e($rev['customer_name']) ?></strong>
                                            <?php if ($rev['is_verified_purchase']): ?>
                                                <span class="badge bg-success-subtle text-success small ms-2"><i class="bi bi-patch-check-fill me-1"></i>Verified Buyer</span>
                                            <?php endif; ?>
                                            <?php if ($isMyRev): ?>
                                                <span class="badge bg-primary-subtle text-primary small ms-1">Your Review</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <div class="d-flex align-items-center gap-2">
                                        <span class="text-muted small"><?= format_date($rev['created_at'], 'M d, Y') ?></span>
                                        <?php if ($isMyRev): ?>
                                            <button type="button" class="btn btn-sm btn-outline-secondary p-0 px-2 edit-my-review-btn" 
                                                data-rating="<?= (int)$rev['rating'] ?>"
                                                data-title="<?= htmlspecialchars($rev['title'], ENT_QUOTES, 'UTF-8') ?>"
                                                data-text="<?= htmlspecialchars($rev['review_text'], ENT_QUOTES, 'UTF-8') ?>"
                                                data-id="<?= (int)$rev['id'] ?>"
                                                data-image="<?= htmlspecialchars($rev['review_image'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                                                title="Edit Review">
                                                <i class="bi bi-pencil"></i>
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="mb-2"><?= star_rating_html($rev['rating']) ?></div>
                                <h6 class="fw-bold mb-1" style="color:var(--text-charcoal);"><?= e($rev['title']) ?></h6>
                                <p class="text-muted small mb-2" style="line-height:1.6;"><?= e($rev['review_text']) ?></p>

                                <?php if (!empty($rev['review_image'])): 
                                    $revImgSrc = BASE_URL . '/' . ltrim($rev['review_image'], '/');
                                ?>
                                    <div class="mt-2">
                                        <a href="<?= e($revImgSrc) ?>" target="_blank" class="d-inline-block text-decoration-none" title="View Customer Photo">
                                            <img src="<?= e($revImgSrc) ?>" alt="Customer Photo" class="rounded border shadow-sm" style="height:70px; width:70px; object-fit:cover; border-color:var(--border-beige) !important;">
                                        </a>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="text-center py-5 bg-white rounded border" style="border-color:var(--border-beige) !important;">
                            <i class="bi bi-chat-heart fs-1 text-muted d-block mb-2"></i>
                            <h5 class="font-serif">No Reviews Yet</h5>
                            <p class="text-muted small mb-3">Be the first distinguished owner to share your experience with this timepiece.</p>
                            <?php if ($hasDeliveredPurchase): ?>
                                <button type="button" class="btn btn-outline-dark rounded-pill px-4" data-bs-toggle="modal" data-bs-target="#reviewModal" onclick="resetReviewModal()">
                                    Leave the First Review
                                </button>
                            <?php elseif (!is_logged_in()): ?>
                                <a href="<?= BASE_URL ?>/login.php?redirect=product-detail.php?id=<?= $productId ?>" class="btn btn-outline-dark rounded-pill px-4">
                                    Sign In to Review
                                </a>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>

                </div>
            </div>

        </div>
    </div>

    <!-- Related Products Showcase -->
    <?php if (!empty($related)): ?>
        <div class="mt-5 pt-5 border-top" style="border-color:var(--border-beige) !important;">
            <div class="text-center mb-4">
                <span class="section-eyebrow">Complementary Horology</span>
                <h3 class="font-serif fw-bold">More From <?= e($product['category_name']) ?></h3>
            </div>
            <div class="row g-4">
                <?php foreach ($related as $rel): ?>
                    <div class="col-md-6 col-lg-3">
                        <?= render_product_card($rel) ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>

</div>

<!-- ======================================================================
     FULLSCREEN IMAGE LIGHTBOX MODAL
     ====================================================================== -->
<div class="modal fade" id="imageLightboxModal" tabindex="-1" aria-labelledby="imageLightboxLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content bg-transparent border-0">
            <div class="modal-header border-0 pb-0">
                <button type="button" class="btn-close btn-close-white ms-auto" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body text-center p-0">
                <img id="lightboxImg" src="<?= e(BASE_URL . '/' . ltrim($primaryImg, '/')) ?>" alt="<?= e($product['name']) ?>" class="img-fluid rounded shadow-lg" style="max-height:85vh; object-fit:contain;">
            </div>
        </div>
    </div>
</div>

<!-- ======================================================================
     REVIEW SUBMISSION & EDIT MODAL
     ====================================================================== -->
<div class="modal fade" id="reviewModal" tabindex="-1" aria-labelledby="reviewModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border-color:var(--border-beige);">
            <div class="modal-header" style="background-color:var(--bg-cream); border-color:var(--border-beige);">
                <h5 class="modal-title font-serif fw-bold" id="reviewModalLabel">Submit Horological Review</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="reviewSubmitForm" enctype="multipart/form-data">
                <div class="modal-body p-4">
                    <input type="hidden" name="product_id" value="<?= $productId ?>">
                    <input type="hidden" name="action" id="reviewFormAction" value="create">
                    <input type="hidden" name="review_id" id="reviewFormId" value="">
                    
                    <?php if (!is_logged_in()): ?>
                        <div class="alert alert-warning small mb-3">
                            <i class="bi bi-exclamation-triangle-fill me-1"></i> Please <a href="<?= BASE_URL ?>/login.php?redirect=product-detail.php?id=<?= $productId ?>" class="fw-bold alert-link">sign in to your account</a> to submit a verified timepiece review.
                        </div>
                    <?php endif; ?>

                    <div class="mb-3 text-center">
                        <label class="form-label fw-bold small text-uppercase d-block">Overall Rating</label>
                        <div class="star-rating-selector fs-3" id="starSelector" style="color:var(--caramel-primary); cursor:pointer;">
                            <i class="bi bi-star-fill" data-rating="1"></i>
                            <i class="bi bi-star-fill" data-rating="2"></i>
                            <i class="bi bi-star-fill" data-rating="3"></i>
                            <i class="bi bi-star-fill" data-rating="4"></i>
                            <i class="bi bi-star-fill" data-rating="5"></i>
                        </div>
                        <input type="hidden" name="rating" id="selectedRatingInput" value="5">
                    </div>

                    <div class="mb-3">
                        <label for="reviewTitleInput" class="form-label small fw-bold text-uppercase">Review Headline</label>
                        <input type="text" name="title" id="reviewTitleInput" class="form-control" placeholder="e.g. Masterful finish and exceptional precision" required>
                    </div>

                    <div class="mb-3">
                        <label for="reviewTextInput" class="form-label small fw-bold text-uppercase">Your Detailed Experience</label>
                        <textarea name="review_text" id="reviewTextInput" class="form-control" rows="4" placeholder="Detail your thoughts regarding case finishing, caliber accuracy, and strap comfort..." required></textarea>
                    </div>

                    <div class="mb-3">
                        <label for="reviewImageInput" class="form-label small fw-bold text-uppercase">Customer Wristshot / Supporting Photo</label>
                        <input type="file" name="review_image" id="reviewImageInput" class="form-control form-control-sm" accept="image/jpeg,image/png,image/webp">
                        <small class="text-muted d-block mt-1" style="font-size:0.75rem;">Optional photo showing case detail or wrist presence (JPG, PNG, WebP up to 5MB).</small>
                        <div id="reviewImageCurrentPreview" class="mt-2"></div>
                    </div>
                </div>
                <div class="modal-footer" style="border-color:var(--border-beige);">
                    <button type="button" class="btn btn-outline-secondary rounded-pill px-4" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" id="reviewSubmitBtn" class="btn btn-dark rounded-pill px-4" <?= !is_logged_in() ? 'disabled' : '' ?>>
                        Publish Review
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// State variables
let currentSelectedVariantId = <?= !empty($variants) ? (int)$variants[0]['id'] : 'null' ?>;
const baseRegularPrice = <?= (float)$product['regular_price'] ?>;
const baseDiscountPrice = <?= !empty($product['discount_price']) ? (float)$product['discount_price'] : 0 ?>;

// 1. Thumbnail Switcher
document.querySelectorAll('.gallery-thumb-btn').forEach(btn => {
    btn.addEventListener('click', function () {
        const src = this.dataset.src;
        const mainImg = document.getElementById('main-product-img');
        const lightboxImg = document.getElementById('lightboxImg');
        if (src && mainImg) {
            mainImg.style.opacity = '0.3';
            setTimeout(() => {
                mainImg.src = src;
                if (lightboxImg) lightboxImg.src = src;
                mainImg.style.opacity = '1';
            }, 120);
            document.querySelectorAll('.gallery-thumb-btn').forEach(b => b.classList.remove('border-primary'));
            this.classList.add('border-primary');
        }
    });
});

// 2. Interactive Image Zoom on Hover
const galleryBox = document.getElementById('mainGalleryBox');
const mainImg = document.getElementById('main-product-img');
if (galleryBox && mainImg) {
    galleryBox.addEventListener('mousemove', function (e) {
        const rect = galleryBox.getBoundingClientRect();
        const x = ((e.clientX - rect.left) / rect.width) * 100;
        const y = ((e.clientY - rect.top) / rect.height) * 100;
        mainImg.style.transformOrigin = `${x}% ${y}%`;
        mainImg.style.transform = 'scale(1.65)';
    });
    galleryBox.addEventListener('mouseleave', function () {
        mainImg.style.transform = 'scale(1)';
        mainImg.style.transformOrigin = 'center center';
    });
}

// Lightbox modal toggle
document.getElementById('btn-zoom-modal')?.addEventListener('click', () => {
    const modal = new bootstrap.Modal(document.getElementById('imageLightboxModal'));
    modal.show();
});

// 3. Variant Selector Interactions
document.querySelectorAll('.variant-btn').forEach(btn => {
    btn.addEventListener('click', function () {
        document.querySelectorAll('.variant-btn').forEach(b => b.classList.remove('active'));
        this.classList.add('active');

        currentSelectedVariantId = parseInt(this.dataset.variantId);
        const addPrice = parseFloat(this.dataset.additional) || 0;
        const sku = this.dataset.sku;
        const stock = parseInt(this.dataset.stock) || 0;
        const vName = this.dataset.variantName;

        // Update SKU display
        if (sku) document.getElementById('product-sku-display').innerText = sku;
        if (vName) document.getElementById('selected-variant-label').innerText = 'Selected: ' + vName;

        // Update Price display
        const displayPriceEl = document.getElementById('product-display-price');
        const finalPrice = (baseDiscountPrice > 0 ? baseDiscountPrice : baseRegularPrice) + addPrice;
        displayPriceEl.innerText = 'Rs. ' + finalPrice.toLocaleString('en-PK', {minimumFractionDigits: 2});

        // Update Stock
        const stockCountEl = document.getElementById('available-stock-count');
        if (stockCountEl) stockCountEl.innerText = stock;
    });
});

// 4. Quantity Controls
const qtyInput = document.getElementById('detail-qty');
document.getElementById('qty-minus')?.addEventListener('click', () => {
    let val = parseInt(qtyInput.value) || 1;
    if (val > 1) qtyInput.value = val - 1;
});
document.getElementById('qty-plus')?.addEventListener('click', () => {
    let val = parseInt(qtyInput.value) || 1;
    let max = parseInt(qtyInput.max) || 99;
    if (val < max) qtyInput.value = val + 1;
});

// 5. Add to Shopping Bag (AJAX)
document.getElementById('btn-detail-add')?.addEventListener('click', function () {
    const qty = parseInt(qtyInput.value) || 1;
    const prodId = <?= $productId ?>;
    
    fetch('<?= BASE_URL ?>/ajax/cart.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({
            action: 'add',
            product_id: prodId,
            variant_id: currentSelectedVariantId || '',
            quantity: qty
        })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            document.querySelectorAll('.cart-badge').forEach(b => b.textContent = data.cart_count);
            showValenorToast(data.message, 'success');
        } else {
            showValenorToast(data.message, 'danger');
        }
    })
    .catch(() => showValenorToast('Could not add to shopping bag. Please try again.', 'danger'));
});

// 6. Buy Now (Direct Add & Redirect to Cart/Checkout)
document.getElementById('btn-buy-now')?.addEventListener('click', function () {
    const qty = parseInt(qtyInput.value) || 1;
    const prodId = <?= $productId ?>;

    fetch('<?= BASE_URL ?>/ajax/cart.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({
            action: 'add',
            product_id: prodId,
            variant_id: currentSelectedVariantId || '',
            quantity: qty
        })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            window.location.href = '<?= BASE_URL ?>/cart.php';
        } else {
            showValenorToast(data.message, 'danger');
        }
    })
    .catch(() => {
        window.location.href = '<?= BASE_URL ?>/cart.php';
    });
});

// 7. Interactive Star Rating in Review Modal
function setModalStars(rating) {
    document.getElementById('selectedRatingInput').value = rating;
    document.querySelectorAll('#starSelector i').forEach((s, idx) => {
        s.className = (idx < rating) ? 'bi bi-star-fill' : 'bi bi-star';
    });
}

const stars = document.querySelectorAll('#starSelector i');
stars.forEach(star => {
    star.addEventListener('click', function () {
        const rating = parseInt(this.dataset.rating);
        setModalStars(rating);
    });
});

function resetReviewModal() {
    document.getElementById('reviewModalLabel').textContent = 'Submit Horological Review';
    document.getElementById('reviewSubmitBtn').textContent = 'Publish Review';
    document.getElementById('reviewFormAction').value = 'create';
    document.getElementById('reviewFormId').value = '';
    document.getElementById('reviewTitleInput').value = '';
    document.getElementById('reviewTextInput').value = '';
    document.getElementById('reviewImageInput').value = '';
    document.getElementById('reviewImageCurrentPreview').innerHTML = '';
    setModalStars(5);
}

// Edit Existing Review handler
document.querySelectorAll('.edit-my-review-btn').forEach(btn => {
    btn.addEventListener('click', function () {
        const rating = parseInt(this.dataset.rating) || 5;
        const title = this.dataset.title || '';
        const text = this.dataset.text || '';
        const revId = this.dataset.id || '';
        const img = this.dataset.image || '';

        document.getElementById('reviewModalLabel').textContent = 'Edit Your Horological Review';
        document.getElementById('reviewSubmitBtn').textContent = 'Update Review';
        document.getElementById('reviewFormAction').value = 'edit';
        document.getElementById('reviewFormId').value = revId;
        document.getElementById('reviewTitleInput').value = title;
        document.getElementById('reviewTextInput').value = text;
        document.getElementById('reviewImageInput').value = '';

        const prevContainer = document.getElementById('reviewImageCurrentPreview');
        if (img) {
            prevContainer.innerHTML = `<div class="small text-muted mb-1">Current Image:</div><img src="${img.startsWith('http') ? img : '<?= BASE_URL ?>/' + img}" class="rounded border" style="height:60px; object-fit:cover;">`;
        } else {
            prevContainer.innerHTML = '';
        }

        setModalStars(rating);

        const modal = new bootstrap.Modal(document.getElementById('reviewModal'));
        modal.show();
    });
});

// 8. Review Form Submission (AJAX with FormData for File Uploads)
document.getElementById('reviewSubmitForm')?.addEventListener('submit', function (e) {
    e.preventDefault();
    const formData = new FormData(this);
    const submitBtn = document.getElementById('reviewSubmitBtn');
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Saving...';
    
    fetch('<?= BASE_URL ?>/ajax/review.php', {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        submitBtn.disabled = false;
        submitBtn.textContent = 'Publish Review';
        if (data.success) {
            showValenorToast(data.message, 'success');
            bootstrap.Modal.getInstance(document.getElementById('reviewModal')).hide();
            setTimeout(() => location.reload(), 1000);
        } else {
            showValenorToast(data.message, 'danger');
        }
    })
    .catch(() => {
        submitBtn.disabled = false;
        submitBtn.textContent = 'Publish Review';
        showValenorToast('Failed to submit review. Please try again.', 'danger');
    });
});
</script>

<?php require_once INCLUDES_PATH . '/footer.php'; ?>
