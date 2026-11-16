<?php
// =============================================================================
// VALENOR HOROLOGY — REUSABLE PRODUCT CARD COMPONENT (Section 3.C)
// Renders product image, badges, ratings, prices, and 4 required actions
// =============================================================================

function render_product_card($product) {
    if (!$product) return '';

    $id             = (int)$product['id'];
    $name           = $product['name'];
    $slug           = $product['slug'];
    $categoryName   = $product['category_name'] ?? 'Luxury Watch';
    $regularPrice   = (float)$product['regular_price'];
    $discountPrice  = !empty($product['discount_price']) && $product['discount_price'] > 0 ? (float)$product['discount_price'] : null;
    $discountPct    = (int)($product['discount_percentage'] ?? 0);
    $shortDesc      = $product['short_description'] ?? '';
    $imagePath      = $product['primary_image'] ?? 'assets/images/watch-mens-chronograph.jpg';
    $isFeatured     = !empty($product['is_featured']);
    $isNewArrival   = !empty($product['is_new_arrival']);
    $isBestSeller   = !empty($product['is_best_seller']);
    $stock          = (int)($product['stock_quantity'] ?? 0);

    // Wishlist & Compare Active States
    $isWishlisted   = is_wishlisted($id);
    $isCompared     = is_compared($id);

    // Average Rating (default 5.0 or 4.8)
    $avgRating      = !empty($product['avg_rating']) ? (float)$product['avg_rating'] : 4.9;
    $reviewsCount   = !empty($product['reviews_count']) ? (int)$product['reviews_count'] : 14;

    $detailUrl = BASE_URL . '/product-detail.php?id=' . $id;

    ob_start();
    ?>
    <div class="product-card">
        <!-- Thumbnail Container -->
        <div class="product-thumb">
            <!-- Badges Stack -->
            <div class="badge-stack">
                <?php if ($discountPrice && $discountPct > 0): ?>
                    <span class="valenor-badge valenor-badge-sale">-<?= $discountPct ?>% OFF</span>
                <?php endif; ?>
                <?php if ($isNewArrival): ?>
                    <span class="valenor-badge valenor-badge-new">New Arrival</span>
                <?php endif; ?>
                <?php if ($isBestSeller): ?>
                    <span class="valenor-badge valenor-badge-bestseller">Best Seller</span>
                <?php endif; ?>
            </div>

            <!-- Main Product Image -->
            <a href="<?= e($detailUrl) ?>" title="<?= e($name) ?>">
                <img src="<?= e(BASE_URL . '/' . ltrim($imagePath, '/')) ?>" alt="<?= e($name) ?>" loading="lazy">
            </a>

            <!-- Quick Action Overlay Buttons (Wishlist, Compare, Quick View) -->
            <div class="product-quick-actions">
                <button type="button" class="quick-action-btn btn-toggle-wishlist <?= $isWishlisted ? 'active' : '' ?>" data-product-id="<?= $id ?>" title="<?= $isWishlisted ? 'Remove from Wishlist' : 'Add to Wishlist' ?>" aria-label="Add to Wishlist">
                    <i class="bi <?= $isWishlisted ? 'bi-heart-fill text-danger' : 'bi-heart' ?>"></i>
                </button>
                <button type="button" class="quick-action-btn btn-toggle-compare <?= $isCompared ? 'active' : '' ?>" data-product-id="<?= $id ?>" title="<?= $isCompared ? 'Remove from Comparison' : 'Add to Compare' ?>" aria-label="Compare Product">
                    <i class="bi bi-arrow-left-right"></i>
                </button>
                <a href="<?= e($detailUrl) ?>" class="quick-action-btn" title="View Details" aria-label="View Product Details">
                    <i class="bi bi-eye"></i>
                </a>
            </div>
        </div>

        <!-- Product Card Body -->
        <div class="product-content">
            <span class="product-cat-name"><?= e($categoryName) ?></span>
            
            <h4 class="product-name-heading">
                <a href="<?= e($detailUrl) ?>" class="product-name-link" title="<?= e($name) ?>">
                    <?= e($name) ?>
                </a>
            </h4>

            <!-- Star Rating (Section 3.C) -->
            <div class="mb-2">
                <?= star_rating_html($avgRating, $reviewsCount) ?>
            </div>

            <!-- Short Description (Section 3.C) -->
            <p class="product-short-desc">
                <?= e($shortDesc) ?>
            </p>

            <!-- Price Row (Section 3.C) -->
            <div class="product-price-row">
                <div>
                    <span class="price-current"><?= format_price($discountPrice ?: $regularPrice) ?></span>
                    <?php if ($discountPrice && $discountPrice < $regularPrice): ?>
                        <span class="price-regular"><?= format_price($regularPrice) ?></span>
                    <?php endif; ?>
                </div>
                <div>
                    <?php if ($stock > 0): ?>
                        <span class="badge bg-success-subtle text-success border border-success-subtle" style="font-size:0.7rem;">In Stock</span>
                    <?php else: ?>
                        <span class="badge bg-danger-subtle text-danger border border-danger-subtle" style="font-size:0.7rem;">Out of Stock</span>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Action Buttons: Add to Cart & View Details (Section 3.C) -->
            <div class="product-card-actions">
                <button type="button" class="btn-add-cart" data-product-id="<?= $id ?>" data-quantity="1" <?= $stock <= 0 ? 'disabled' : '' ?>>
                    <i class="bi bi-bag-plus"></i> Add to Cart
                </button>
                <a href="<?= e($detailUrl) ?>" class="btn-view-details" title="Full Specifications & Gallery">
                    Details
                </a>
            </div>
        </div>
    </div>
    <?php
    return ob_get_clean();
}

