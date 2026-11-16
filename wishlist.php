<?php
// =============================================================================
// VALENOR HOROLOGY — PRIVATE COLLECTOR WISHLIST (Section 11)
// Protected luxury wishlist with Move to Bag, Remove, and MySQL persistence
// =============================================================================

require_once __DIR__ . '/config/config.php';

// Mandatory login requirement for accessing private wishlist
if (!is_logged_in()) {
    set_flash('info', 'Please sign in to access your private horological wishlist.');
    header('Location: ' . BASE_URL . '/login.php?redirect=wishlist.php');
    exit;
}

$db = getDB();
$customerId = current_user_id();

// =============================================================================
// HANDLE SERVER-SIDE FORM ACTIONS (Fallback for non-JS)
// =============================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) {
        set_flash('danger', 'Security session expired. Please try again.');
        header('Location: ' . BASE_URL . '/wishlist.php');
        exit;
    }

    $action = sanitize($_POST['action'] ?? '');
    $prodId = (int)($_POST['product_id'] ?? 0);

    // 1. Move to Bag
    if ($action === 'move_to_cart' && $prodId > 0) {
        $res = move_wishlist_to_cart($prodId, $customerId);
        if ($res['success']) {
            set_flash('success', $res['message']);
        } else {
            set_flash('warning', $res['message']);
        }
        header('Location: ' . BASE_URL . '/wishlist.php');
        exit;
    }

    // 2. Remove single item
    if ($action === 'remove' && $prodId > 0) {
        $stmt = $db->prepare("SELECT id FROM wishlists WHERE customer_id = ? LIMIT 1");
        $stmt->execute([$customerId]);
        $wishlistId = $stmt->fetchColumn();
        if ($wishlistId) {
            $db->prepare("DELETE FROM wishlist_items WHERE wishlist_id = ? AND product_id = ?")->execute([$wishlistId, $prodId]);
            set_flash('info', 'Timepiece removed from your private wishlist.');
        }
        header('Location: ' . BASE_URL . '/wishlist.php');
        exit;
    }

    // 3. Clear all items
    if ($action === 'clear_all') {
        $stmt = $db->prepare("SELECT id FROM wishlists WHERE customer_id = ? LIMIT 1");
        $stmt->execute([$customerId]);
        $wishlistId = $stmt->fetchColumn();
        if ($wishlistId) {
            $db->prepare("DELETE FROM wishlist_items WHERE wishlist_id = ?")->execute([$wishlistId]);
            set_flash('info', 'Your private wishlist has been cleared.');
        }
        header('Location: ' . BASE_URL . '/wishlist.php');
        exit;
    }
}

// =============================================================================
// FETCH SAVED TIMEPIECES FROM MYSQL
// =============================================================================
$stmt = $db->prepare("
    SELECT p.*, c.name AS category_name, c.slug AS category_slug,
           wi.id AS wishlist_item_id, wi.created_at AS date_saved,
           (SELECT image_path FROM product_images WHERE product_id = p.id AND is_primary = 1 LIMIT 1) AS primary_image,
           (SELECT AVG(rating) FROM product_reviews WHERE product_id = p.id AND status = 'approved') AS avg_rating,
           (SELECT COUNT(*) FROM product_reviews WHERE product_id = p.id AND status = 'approved') AS reviews_count
    FROM wishlists w
    JOIN wishlist_items wi ON w.id = wi.wishlist_id
    JOIN products p ON wi.product_id = p.id
    JOIN categories c ON p.category_id = c.id
    WHERE w.customer_id = ?
    ORDER BY wi.created_at DESC
");
$stmt->execute([$customerId]);
$savedItems = $stmt->fetchAll();

$pageTitle = 'My Saved Wishlist (' . count($savedItems) . ' Timepieces)';
require_once INCLUDES_PATH . '/header.php';
require_once INCLUDES_PATH . '/navbar.php';
?>

<!-- Breadcrumbs & Header -->
<div class="py-4 border-bottom" style="background-color: var(--bg-white); border-color: var(--border-beige) !important;">
    <div class="container d-flex flex-wrap justify-content-between align-items-center gap-3">
        <div>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-1" style="font-size:0.82rem;">
                    <li class="breadcrumb-item"><a href="<?= BASE_URL ?>/index.php" style="color:var(--text-dark);">Home</a></li>
                    <li class="breadcrumb-item"><a href="<?= BASE_URL ?>/my-account.php" style="color:var(--text-dark);">Account</a></li>
                    <li class="breadcrumb-item active text-muted" aria-current="page">Private Wishlist</li>
                </ol>
            </nav>
            <h1 class="h2 mb-0 font-serif fw-bold" style="color:var(--text-dark);">Private Wishlist</h1>
        </div>

        <?php if (!empty($savedItems)): ?>
            <div class="d-flex align-items-center gap-2">
                <a href="<?= BASE_URL ?>/products.php" class="btn btn-sm btn-outline-dark" style="border-color:var(--border-beige);">
                    <i class="bi bi-compass me-1"></i> Continue Exploring
                </a>
                <form method="POST" action="<?= BASE_URL ?>/wishlist.php" class="d-inline" onsubmit="return confirm('Clear your entire private wishlist?');">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="clear_all">
                    <button type="submit" class="btn btn-sm btn-outline-danger">
                        <i class="bi bi-trash3 me-1"></i> Clear Wishlist
                    </button>
                </form>
            </div>
        <?php endif; ?>
    </div>
</div>

<div class="container py-5">
    <?php if (!empty($savedItems)): ?>
        <div class="card bg-white border shadow-sm mb-4" style="border-color:var(--border-beige) !important; border-radius:8px;">
            <div class="card-header bg-white py-3 px-4 border-bottom d-flex justify-content-between align-items-center" style="border-color:var(--border-beige) !important;">
                <span class="small text-uppercase fw-bold text-muted" style="letter-spacing:0.08em;">
                    <i class="bi bi-heart-fill text-danger me-1"></i> Curated Collection (<?= count($savedItems) ?> <?= count($savedItems) === 1 ? 'Timepiece' : 'Timepieces' ?>)
                </span>
                <span class="badge" style="background-color:var(--caramel-primary); font-size:0.75rem;">Collector Archive</span>
            </div>

            <!-- Responsive Table (Desktop & Tablet) -->
            <div class="table-responsive d-none d-md-block">
                <table class="table align-middle mb-0" style="border-color:var(--border-beige);">
                    <thead style="background-color:var(--bg-cream);">
                        <tr>
                            <th class="ps-4 py-3" style="width:110px;">Timepiece</th>
                            <th class="py-3">Details & Complications</th>
                            <th class="py-3 text-center" style="width:150px;">Price</th>
                            <th class="py-3 text-center" style="width:140px;">Availability</th>
                            <th class="py-3 text-end pe-4" style="width:230px;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($savedItems as $item): ?>
                            <?php 
                            $finalPrice = (!empty($item['discount_price']) && $item['discount_price'] > 0) ? (float)$item['discount_price'] : (float)$item['regular_price'];
                            $hasDiscount = (!empty($item['discount_price']) && $item['discount_price'] < $item['regular_price']);
                            $inStock = ((int)$item['stock_quantity'] > 0);
                            $imgSrc = !empty($item['primary_image']) ? BASE_URL . '/' . ltrim($item['primary_image'], '/') : BASE_URL . '/assets/images/watch-mens-chronograph.jpg';
                            ?>
                            <tr class="wishlist-row" id="wishlist-row-<?= (int)$item['id'] ?>" style="border-bottom:1px solid var(--border-beige);">
                                <td class="ps-4 py-3">
                                    <a href="<?= BASE_URL ?>/product-detail.php?id=<?= (int)$item['id'] ?>">
                                        <img src="<?= e($imgSrc) ?>" alt="<?= e($item['name']) ?>" class="rounded border" style="width:80px; height:80px; object-fit:cover; border-color:var(--border-beige) !important;">
                                    </a>
                                </td>
                                <td class="py-3">
                                    <div class="d-flex align-items-center gap-2 mb-1">
                                        <span class="badge" style="background-color:var(--bg-cream); color:var(--caramel-dark); border:1px solid var(--border-beige); font-size:0.7rem;">
                                            <?= e($item['category_name']) ?>
                                        </span>
                                        <span class="text-muted small">Ref: <?= e($item['sku']) ?></span>
                                    </div>
                                    <h5 class="font-serif fw-bold mb-1" style="font-size:1.05rem;">
                                        <a href="<?= BASE_URL ?>/product-detail.php?id=<?= (int)$item['id'] ?>" class="text-decoration-none" style="color:var(--text-dark);">
                                            <?= e($item['name']) ?>
                                        </a>
                                    </h5>
                                    <div class="d-flex align-items-center gap-2 small">
                                        <?= star_rating_html($item['avg_rating'] ?: 5.0, $item['reviews_count']) ?>
                                        <span class="text-muted">Saved on <?= date('M d, Y', strtotime($item['date_saved'])) ?></span>
                                    </div>
                                </td>
                                <td class="py-3 text-center">
                                    <div class="fw-bold fs-6" style="color:var(--caramel-dark);">
                                        <?= format_price($finalPrice) ?>
                                    </div>
                                    <?php if ($hasDiscount): ?>
                                        <div class="text-muted text-decoration-line-through small"><?= format_price($item['regular_price']) ?></div>
                                        <span class="badge bg-danger-subtle text-danger" style="font-size:0.65rem;">
                                            Save <?= round((($item['regular_price'] - $item['discount_price']) / $item['regular_price']) * 100) ?>%
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td class="py-3 text-center">
                                    <?php if ($inStock): ?>
                                        <span class="badge bg-success-subtle text-success py-1 px-2" style="font-size:0.75rem;">
                                            <i class="bi bi-check-circle me-1"></i> In Stock (<?= (int)$item['stock_quantity'] ?>)
                                        </span>
                                    <?php else: ?>
                                        <span class="badge bg-danger-subtle text-danger py-1 px-2" style="font-size:0.75rem;">
                                            <i class="bi bi-x-circle me-1"></i> Out of Stock
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td class="py-3 text-end pe-4">
                                    <div class="d-flex justify-content-end align-items-center gap-2">
                                        <?php if ($inStock): ?>
                                            <button type="button" class="btn btn-sm btn-valenor-primary btn-move-cart" data-product-id="<?= (int)$item['id'] ?>" title="Move to Shopping Bag">
                                                <i class="bi bi-bag-plus me-1"></i> Move to Bag
                                            </button>
                                        <?php else: ?>
                                            <button type="button" class="btn btn-sm btn-secondary" disabled>
                                                Out of Stock
                                            </button>
                                        <?php endif; ?>

                                        <button type="button" class="btn btn-sm btn-outline-danger btn-remove-wishlist" data-product-id="<?= (int)$item['id'] ?>" title="Remove from Wishlist">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Mobile Card Layout -->
            <div class="d-md-none p-3">
                <?php foreach ($savedItems as $item): ?>
                    <?php 
                    $finalPrice = (!empty($item['discount_price']) && $item['discount_price'] > 0) ? (float)$item['discount_price'] : (float)$item['regular_price'];
                    $hasDiscount = (!empty($item['discount_price']) && $item['discount_price'] < $item['regular_price']);
                    $inStock = ((int)$item['stock_quantity'] > 0);
                    $imgSrc = !empty($item['primary_image']) ? BASE_URL . '/' . ltrim($item['primary_image'], '/') : BASE_URL . '/assets/images/watch-mens-chronograph.jpg';
                    ?>
                    <div class="card border p-3 mb-3 bg-white wishlist-row" id="wishlist-row-m-<?= (int)$item['id'] ?>" style="border-color:var(--border-beige) !important; border-radius:6px;">
                        <div class="d-flex gap-3">
                            <a href="<?= BASE_URL ?>/product-detail.php?id=<?= (int)$item['id'] ?>">
                                <img src="<?= e($imgSrc) ?>" alt="<?= e($item['name']) ?>" class="rounded border" style="width:75px; height:75px; object-fit:cover; border-color:var(--border-beige) !important;">
                            </a>
                            <div class="flex-grow-1">
                                <span class="badge mb-1" style="background-color:var(--bg-cream); color:var(--caramel-dark); font-size:0.65rem;">
                                    <?= e($item['category_name']) ?>
                                </span>
                                <h6 class="font-serif fw-bold mb-1">
                                    <a href="<?= BASE_URL ?>/product-detail.php?id=<?= (int)$item['id'] ?>" class="text-decoration-none text-dark">
                                        <?= e($item['name']) ?>
                                    </a>
                                </h6>
                                <div class="fw-bold fs-6" style="color:var(--caramel-dark);">
                                    <?= format_price($finalPrice) ?>
                                    <?php if ($hasDiscount): ?>
                                        <span class="text-muted text-decoration-line-through small fw-normal ms-1"><?= format_price($item['regular_price']) ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <div class="d-flex justify-content-between align-items-center mt-3 pt-2 border-top" style="border-color:var(--border-beige) !important;">
                            <div>
                                <?php if ($inStock): ?>
                                    <span class="badge bg-success-subtle text-success small">In Stock</span>
                                <?php else: ?>
                                    <span class="badge bg-danger-subtle text-danger small">Out of Stock</span>
                                <?php endif; ?>
                            </div>
                            <div class="d-flex gap-2">
                                <?php if ($inStock): ?>
                                    <button type="button" class="btn btn-sm btn-valenor-primary btn-move-cart" data-product-id="<?= (int)$item['id'] ?>">
                                        <i class="bi bi-bag-plus"></i> Move to Bag
                                    </button>
                                <?php endif; ?>
                                <button type="button" class="btn btn-sm btn-outline-danger btn-remove-wishlist" data-product-id="<?= (int)$item['id'] ?>">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

    <?php else: ?>
        <!-- Empty Wishlist Presentation -->
        <div class="text-center py-5 bg-white rounded border p-5 shadow-sm" style="border-color:var(--border-beige) !important; max-width:650px; margin:0 auto; border-radius:8px;">
            <div class="mb-3">
                <span class="d-inline-flex align-items-center justify-content-center rounded-circle" style="width:80px; height:80px; background-color:var(--bg-cream); border:1px solid var(--border-beige);">
                    <i class="bi bi-heart fs-1" style="color:var(--caramel-primary);"></i>
                </span>
            </div>
            <span class="text-uppercase small fw-bold" style="color:var(--caramel-primary); letter-spacing:0.15em;">Collector Archive</span>
            <h2 class="font-serif fw-bold mt-1 mb-2">Your Wishlist is Empty</h2>
            <p class="text-muted mb-4" style="max-width:440px; margin:0 auto;">
                Explore the VALENOR horological catalog and save your coveted chronographs, automatics, and complications for private consideration.
            </p>
            <a href="<?= BASE_URL ?>/products.php" class="btn-valenor-primary px-4 py-2">
                <i class="bi bi-compass me-1"></i> Discover Timepieces &rarr;
            </a>
        </div>
    <?php endif; ?>
</div>

<?php require_once INCLUDES_PATH . '/footer.php'; ?>
