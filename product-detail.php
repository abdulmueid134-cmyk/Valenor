<?php
// =============================================================================
// VALENOR HOROLOGY — PRODUCT DETAIL PAGE (Section 3.D)
// 1–10 Image Gallery with Interactive Thumbnail Switcher, Specs, Reviews & Actions
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
    echo '<div class="container py-5 text-center">' .
         '<h2 class="font-serif">Timepiece Not Found</h2>' .
         '<p class="text-muted">The timepiece you requested is currently unavailable or does not exist.</p>' .
         '<a href="' . BASE_URL . '/products.php" class="btn btn-dark rounded-pill px-4">Browse Collection</a>' .
         '</div>';
    require_once INCLUDES_PATH . '/footer.php';
    exit;
}

$productId = (int)$product['id'];

// Increment views count
$db->prepare("UPDATE products SET views_count = views_count + 1 WHERE id = ?")->execute([$productId]);

// 1. Fetch all product images (1–10 images, Section 4)
$imgStmt = $db->prepare("SELECT * FROM product_images WHERE product_id = ? ORDER BY is_primary DESC, sort_order ASC, id ASC");
$imgStmt->execute([$productId]);
$images = $imgStmt->fetchAll();

// 2. Fetch product tags & hashtags
$tagStmt = $db->prepare("SELECT tag_name, tag_type FROM product_tags WHERE product_id = ?");
$tagStmt->execute([$productId]);
$tags = $tagStmt->fetchAll();

// 3. Fetch product variants (colors/sizes)
$varStmt = $db->prepare("SELECT * FROM product_variants WHERE product_id = ?");
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

// Compute average rating
$totalRatings = count($reviews);
$avgRating = $totalRatings > 0 ? array_sum(array_column($reviews, 'rating')) / $totalRatings : 5.0;

// Specifications array
$specs = !empty($product['specifications']) ? json_decode($product['specifications'], true) : [];

// Related products
$relStmt = $db->prepare("
    SELECT p.*, c.name AS category_name, c.slug AS category_slug,
           (SELECT image_path FROM product_images WHERE product_id = p.id AND is_primary = 1 LIMIT 1) AS primary_image
    FROM products p
    JOIN categories c ON p.category_id = c.id
    WHERE p.category_id = ? AND p.id != ? AND p.status = 'active'
    LIMIT 4
");
$relStmt->execute([$product['category_id'], $productId]);
$related = $relStmt->fetchAll();

$pageTitle = $product['name'] . ' — ' . $product['category_name'];
$pageDesc  = $product['short_description'];

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

        <!-- Left: Image Gallery (1–10 Images, Section 4) -->
        <div class="col-lg-6">
            <div class="sticky-top" style="top:90px; z-index:1;">
                <!-- Main Showcase Image -->
                <div class="main-gallery-view mb-3 rounded border overflow-hidden position-relative bg-white" style="border-color:var(--border-beige) !important; padding-top:95%;">
                    <?php 
                    $primaryImg = !empty($images) ? $images[0]['image_path'] : 'assets/images/watch-mens-chronograph.jpg';
                    ?>
                    <img id="main-product-img" src="<?= e(BASE_URL . '/' . ltrim($primaryImg, '/')) ?>" alt="<?= e($product['name']) ?>" style="position:absolute; top:0; left:0; width:100%; height:100%; object-fit:cover; transition:opacity 0.25s ease;">
                    <?php if (!empty($product['discount_percentage']) && $product['discount_percentage'] > 0): ?>
                        <span class="valenor-badge valenor-badge-sale position-absolute top-0 start-0 m-3 fs-6">
                            -<?= (int)$product['discount_percentage'] ?>% PRIVILEGE
                        </span>
                    <?php endif; ?>
                </div>

                <!-- Thumbnails Strip (Section 4: thumbnail click changes main image) -->
                <?php if (count($images) > 1): ?>
                    <div class="d-flex flex-wrap gap-2 justify-content-start" id="gallery-thumbnails">
                        <?php foreach ($images as $idx => $img): ?>
                            <button type="button" class="btn p-0 border rounded overflow-hidden gallery-thumb-btn <?= $idx === 0 ? 'border-primary' : '' ?>" data-src="<?= e(BASE_URL . '/' . ltrim($img['image_path'], '/')) ?>" style="width:72px; height:72px; background:#fff; border-color:var(--border-beige) !important;" aria-label="View photo <?= $idx + 1 ?>">
                                <img src="<?= e(BASE_URL . '/' . ltrim($img['image_path'], '/')) ?>" alt="Thumbnail <?= $idx + 1 ?>" style="width:100%; height:100%; object-fit:cover;">
                            </button>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Right: Product Information & Controls -->
        <div class="col-lg-6">
            <span class="text-uppercase fw-bold" style="color:var(--caramel-primary); letter-spacing:0.18em; font-size:0.78rem;">
                <?= e($product['brand']) ?> &bull; <?= e($product['category_name']) ?>
            </span>
            <h1 class="display-6 font-serif fw-bold mt-1 mb-2" style="color:var(--text-charcoal); line-height:1.2;">
                <?= e($product['name']) ?>
            </h1>

            <div class="d-flex align-items-center gap-3 mb-3">
                <?= star_rating_html($avgRating, $totalRatings) ?>
                <span class="text-muted">&bull;</span>
                <span class="text-muted small">SKU: <strong><?= e($product['sku']) ?></strong></span>
                <span class="text-muted">&bull;</span>
                <span class="text-muted small"><i class="bi bi-eye me-1"></i><?= (int)$product['views_count'] ?> views</span>
            </div>

            <!-- Price Display -->
            <div class="p-3 rounded mb-4" style="background-color:var(--bg-cream); border:1px solid var(--border-beige);">
                <div class="d-flex align-items-baseline gap-3">
                    <span class="fs-2 fw-bold" style="color:var(--caramel-dark);">
                        <?= format_price($product['discount_price'] ?: $product['regular_price']) ?>
                    </span>
                    <?php if (!empty($product['discount_price']) && $product['discount_price'] < $product['regular_price']): ?>
                        <span class="fs-5 text-muted text-decoration-line-through">
                            <?= format_price($product['regular_price']) ?>
                        </span>
                        <span class="badge bg-danger">Save <?= format_price($product['regular_price'] - $product['discount_price']) ?></span>
                    <?php endif; ?>
                </div>
                <div class="mt-1 text-muted small">
                    <i class="bi bi-shield-check text-success me-1"></i> Inclusive of insured BlueEx nationwide delivery and luxury presentation packaging.
                </div>
            </div>

            <!-- Short Description -->
            <p class="lead mb-4" style="font-size:1.02rem; line-height:1.7; color:#444;">
                <?= e($product['short_description']) ?>
            </p>

            <!-- Variants (Colors / Sizes) -->
            <?php if (!empty($variants)): ?>
                <div class="mb-4">
                    <label class="form-label fw-bold small text-uppercase" style="letter-spacing:0.1em; color:var(--text-charcoal);">Select Edition / Variant:</label>
                    <div class="d-flex flex-wrap gap-2" id="variant-selector">
                        <?php foreach ($variants as $idx => $v): ?>
                            <button type="button" class="btn btn-sm btn-outline-dark variant-pill <?= $idx === 0 ? 'active' : '' ?>" data-variant-id="<?= (int)$v['id'] ?>" data-additional="<?= (float)$v['additional_price'] ?>" style="font-size:0.82rem; border-color:var(--border-beige);">
                                <?= e($v['color'] ?: 'Standard') ?> <?= $v['size'] ? '— ' . e($v['size']) : '' ?>
                                <?php if ($v['additional_price'] > 0): ?>
                                    (+<?= format_price($v['additional_price']) ?>)
                                <?php endif; ?>
                            </button>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Stock Status & Quantity Selector -->
            <div class="row g-3 align-items-center mb-4">
                <div class="col-auto">
                    <label class="form-label fw-bold small text-uppercase mb-1" style="letter-spacing:0.1em;">Quantity:</label>
                    <div class="input-group input-group-sm" style="width:120px;">
                        <button class="btn btn-outline-secondary" type="button" id="qty-minus">-</button>
                        <input type="number" id="detail-qty" class="form-control text-center" value="1" min="1" max="<?= max(1, (int)$product['stock_quantity']) ?>">
                        <button class="btn btn-outline-secondary" type="button" id="qty-plus">+</button>
                    </div>
                </div>
                <div class="col">
                    <div class="small fw-semibold mt-3">
                        <?php if ($product['stock_quantity'] > 0): ?>
                            <span class="text-success"><i class="bi bi-check-circle-fill me-1"></i> In Stock (<?= (int)$product['stock_quantity'] ?> units ready for dispatch)</span>
                        <?php else: ?>
                            <span class="text-danger"><i class="bi bi-x-circle-fill me-1"></i> Temporarily Out of Stock</span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Action Buttons (Add to Cart, Buy Now, Wishlist, Compare) -->
            <div class="d-flex flex-wrap gap-3 mb-4">
                <button type="button" class="btn-valenor-primary flex-grow-1 justify-content-center btn-add-cart" data-product-id="<?= $productId ?>" data-quantity="1">
                    <i class="bi bi-bag-plus"></i> Add to Shopping Bag
                </button>
                <a href="<?= BASE_URL ?>/cart.php" class="btn btn-dark rounded px-4 py-2 fw-semibold d-inline-flex align-items-center" style="font-size:0.85rem; letter-spacing:0.05em; text-transform:uppercase;">
                    View Bag
                </a>
                <button type="button" class="btn btn-outline-dark rounded btn-toggle-wishlist <?= is_wishlisted($productId) ? 'active' : '' ?>" data-product-id="<?= $productId ?>" title="Save to Wishlist" style="border-color:var(--border-beige);">
                    <i class="bi <?= is_wishlisted($productId) ? 'bi-heart-fill text-danger' : 'bi-heart' ?>"></i>
                </button>
                <button type="button" class="btn btn-outline-dark rounded btn-toggle-compare <?= is_compared($productId) ? 'active' : '' ?>" data-product-id="<?= $productId ?>" title="Add to Comparison" style="border-color:var(--border-beige);">
                    <i class="bi bi-arrow-left-right"></i>
                </button>
            </div>

            <!-- Tags & Hashtags (Section 5) -->
            <?php if (!empty($tags)): ?>
                <div class="mb-4">
                    <span class="small text-muted me-2">Tags:</span>
                    <?php foreach ($tags as $t): ?>
                        <span class="badge bg-light text-dark border me-1 mb-1" style="font-size:0.75rem; border-color:var(--border-beige) !important;">
                            <?= e($t['tag_name']) ?>
                        </span>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <!-- Confidence Points -->
            <div class="p-3 border rounded bg-white" style="border-color:var(--border-beige) !important; font-size:0.85rem;">
                <div class="row g-2">
                    <div class="col-6"><i class="bi bi-shield-check text-success me-1"></i> 100% Authentic Timepiece</div>
                    <div class="col-6"><i class="bi bi-truck text-primary me-1"></i> Insured Courier Delivery</div>
                    <div class="col-6"><i class="bi bi-arrow-counterclockwise text-warning me-1"></i> 14-Day Inspection Window</div>
                    <div class="col-6"><i class="bi bi-cash-stack text-success me-1"></i> Cash on Delivery Allowed</div>
                </div>
            </div>

        </div>
    </div>

    <!-- Product Full Description & Specifications Tabs -->
    <div class="mt-5 pt-4 border-top" style="border-color:var(--border-beige) !important;">
        <ul class="nav nav-tabs justify-content-center border-0 gap-3" id="productTabs" role="tablist">
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
                    Customer Reviews (<?= $totalRatings ?>)
                </button>
            </li>
        </ul>

        <div class="tab-content pt-4" id="productTabsContent">
            <!-- Tab 1: Description -->
            <div class="tab-pane fade show active" id="desc-pane" role="tabpanel" tabindex="0">
                <div class="mx-auto" style="max-width:800px; line-height:1.8; color:#333;">
                    <p><?= nl2br(e($product['full_description'])) ?></p>
                </div>
            </div>

            <!-- Tab 2: Specifications -->
            <div class="tab-pane fade" id="specs-pane" role="tabpanel" tabindex="0">
                <div class="mx-auto" style="max-width:750px;">
                    <table class="table table-bordered table-striped" style="border-color:var(--border-beige);">
                        <tbody>
                            <tr><th style="width:35%; background:var(--bg-cream);">Brand &amp; Model</th><td><?= e($product['brand'] . ' ' . $product['name']) ?></td></tr>
                            <tr><th style="background:var(--bg-cream);">Master Category</th><td><?= e($product['category_name']) ?></td></tr>
                            <tr><th style="background:var(--bg-cream);">Reference SKU</th><td><?= e($product['sku']) ?></td></tr>
                            <?php if (!empty($specs)): ?>
                                <?php foreach ($specs as $s): ?>
                                    <tr>
                                        <th style="background:var(--bg-cream);"><?= e($s['key']) ?></th>
                                        <td><?= e($s['value']) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Tab 3: Reviews -->
            <div class="tab-pane fade" id="reviews-pane" role="tabpanel" tabindex="0">
                <div class="mx-auto" style="max-width:800px;">
                    <?php if (!empty($reviews)): ?>
                        <?php foreach ($reviews as $rev): ?>
                            <div class="p-3 bg-white border rounded mb-3" style="border-color:var(--border-beige) !important;">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <div class="d-flex align-items-center gap-2">
                                        <strong style="color:var(--text-charcoal);"><?= e($rev['customer_name']) ?></strong>
                                        <span class="badge bg-success-subtle text-success small"><i class="bi bi-patch-check-fill me-1"></i>Verified Purchase</span>
                                    </div>
                                    <span class="text-muted small"><?= format_date($rev['created_at'], 'M d, Y') ?></span>
                                </div>
                                <div class="mb-2"><?= star_rating_html($rev['rating']) ?></div>
                                <h6 class="fw-bold mb-1"><?= e($rev['title']) ?></h6>
                                <p class="text-muted small mb-0"><?= e($rev['review_text']) ?></p>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="text-center py-4 text-muted">
                            <i class="bi bi-chat-heart fs-2 d-block mb-2"></i>
                            No reviews submitted yet for this timepiece. Verified buyers can submit reviews after delivery.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Related Products -->
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

<script>
// Thumbnail Switcher
document.querySelectorAll('.gallery-thumb-btn').forEach(btn => {
    btn.addEventListener('click', function () {
        const src = this.dataset.src;
        const mainImg = document.getElementById('main-product-img');
        if (src && mainImg) {
            mainImg.style.opacity = '0.3';
            setTimeout(() => {
                mainImg.src = src;
                mainImg.style.opacity = '1';
            }, 150);
            document.querySelectorAll('.gallery-thumb-btn').forEach(b => b.classList.remove('border-primary'));
            this.classList.add('border-primary');
        }
    });
});

// Quantity Selector
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
</script>

<?php require_once INCLUDES_PATH . '/footer.php'; ?>
