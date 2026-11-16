<?php
// =============================================================================
// VALENOR HOROLOGY — PRODUCT COMPARISON SYSTEM (Section 6)
// Side-by-side comparison table for 2 to 4 watches with specs and differences
// =============================================================================

require_once __DIR__ . '/config/config.php';

$db = getDB();

if (isset($_GET['clear'])) {
    $_SESSION['compare'] = [];
    set_flash('info', 'Comparison list cleared.');
    header('Location: ' . BASE_URL . '/compare.php');
    exit;
}

if (isset($_GET['remove'])) {
    $remId = (int)$_GET['remove'];
    if (!empty($_SESSION['compare'])) {
        $pos = array_search($remId, $_SESSION['compare']);
        if ($pos !== false) {
            unset($_SESSION['compare'][$pos]);
            $_SESSION['compare'] = array_values($_SESSION['compare']);
            set_flash('info', 'Timepiece removed from comparison.');
        }
    }
    header('Location: ' . BASE_URL . '/compare.php');
    exit;
}

$compareIds = get_compare_ids();
$products = [];

if (!empty($compareIds)) {
    $placeholders = implode(',', array_fill(0, count($compareIds), '?'));
    $stmt = $db->prepare("
        SELECT p.*, c.name AS category_name,
               (SELECT image_path FROM product_images WHERE product_id = p.id AND is_primary = 1 LIMIT 1) AS primary_image
        FROM products p
        JOIN categories c ON p.category_id = c.id
        WHERE p.id IN ({$placeholders})
    ");
    $stmt->execute($compareIds);
    $products = $stmt->fetchAll();
}

$pageTitle = 'Product Comparison (' . count($products) . ' watches)';
require_once INCLUDES_PATH . '/header.php';
require_once INCLUDES_PATH . '/navbar.php';
?>

<div class="py-4 border-bottom" style="background-color: var(--bg-white); border-color: var(--border-beige) !important;">
    <div class="container d-flex justify-content-between align-items-center">
        <div>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-1" style="font-size:0.82rem;">
                    <li class="breadcrumb-item"><a href="<?= BASE_URL ?>/index.php">Home</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Comparison</li>
                </ol>
            </nav>
            <h1 class="h2 mb-0 font-serif fw-bold">Horological Comparison</h1>
        </div>
        <?php if (!empty($products)): ?>
            <a href="<?= BASE_URL ?>/compare.php?clear=1" class="btn btn-sm btn-outline-danger" onclick="return confirm('Clear all products from comparison?');">
                <i class="bi bi-trash3 me-1"></i> Clear Comparison List
            </a>
        <?php endif; ?>
    </div>
</div>

<div class="container py-5">
    <?php if (count($products) >= 1): ?>
        <?php if (count($products) < 2): ?>
            <div class="alert alert-warning mb-4 d-flex align-items-center gap-2">
                <i class="bi bi-info-circle fs-5"></i>
                <div>You currently have 1 watch selected. Add at least one more watch from the catalog to compare differences side by side.</div>
            </div>
        <?php endif; ?>

        <div class="card bg-white border shadow-sm overflow-hidden" style="border-color:var(--border-beige) !important; border-radius:8px;">
            <div class="table-responsive">
                <table class="table table-bordered align-middle text-center mb-0" style="border-color:var(--border-beige);">
                    <thead>
                        <tr style="background-color:var(--bg-cream);">
                            <th style="width:200px; text-align:left;">Specification</th>
                            <?php foreach ($products as $p): ?>
                                <th style="min-width:230px;">
                                    <div class="position-relative p-2">
                                        <a href="<?= BASE_URL ?>/compare.php?remove=<?= (int)$p['id'] ?>" class="btn btn-sm btn-outline-danger position-absolute top-0 end-0 border-0" title="Remove">&times;</a>
                                        <img src="<?= e(BASE_URL . '/' . ltrim($p['primary_image'], '/')) ?>" alt="<?= e($p['name']) ?>" class="rounded border mb-2" style="width:110px; height:110px; object-fit:cover; border-color:var(--border-beige) !important;">
                                        <h5 class="font-serif fw-bold mb-1" style="font-size:1.1rem; line-height:1.2;"><?= e($p['name']) ?></h5>
                                        <span class="badge" style="background:var(--caramel-dark); font-size:0.7rem;"><?= e($p['category_name']) ?></span>
                                    </div>
                                </th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <th style="background-color:var(--bg-cream); text-align:left;">Price</th>
                            <?php foreach ($products as $p): ?>
                                <td>
                                    <span class="fw-bold fs-5" style="color:var(--caramel-dark);">
                                        <?= format_price($p['discount_price'] ?: $p['regular_price']) ?>
                                    </span>
                                    <?php if (!empty($p['discount_price']) && $p['discount_price'] < $p['regular_price']): ?>
                                        <div class="text-muted text-decoration-line-through small"><?= format_price($p['regular_price']) ?></div>
                                    <?php endif; ?>
                                </td>
                            <?php endforeach; ?>
                        </tr>
                        <tr>
                            <th style="background-color:var(--bg-cream); text-align:left;">Brand</th>
                            <?php foreach ($products as $p): ?>
                                <td class="fw-semibold"><?= e($p['brand']) ?></td>
                            <?php endforeach; ?>
                        </tr>
                        <tr>
                            <th style="background-color:var(--bg-cream); text-align:left;">Reference SKU</th>
                            <?php foreach ($products as $p): ?>
                                <td><code><?= e($p['sku']) ?></code></td>
                            <?php endforeach; ?>
                        </tr>
                        <tr>
                            <th style="background-color:var(--bg-cream); text-align:left;">Available Colors</th>
                            <?php foreach ($products as $p): ?>
                                <td><?= e($p['colors'] ?: 'Standard Finish') ?></td>
                            <?php endforeach; ?>
                        </tr>
                        <tr>
                            <th style="background-color:var(--bg-cream); text-align:left;">Case Size</th>
                            <?php foreach ($products as $p): ?>
                                <td class="fw-bold"><?= e($p['sizes'] ?: '40mm') ?></td>
                            <?php endforeach; ?>
                        </tr>
                        <tr>
                            <th style="background-color:var(--bg-cream); text-align:left;">Stock Availability</th>
                            <?php foreach ($products as $p): ?>
                                <td>
                                    <?php if ($p['stock_quantity'] > 0): ?>
                                        <span class="badge bg-success-subtle text-success">In Stock (<?= (int)$p['stock_quantity'] ?>)</span>
                                    <?php else: ?>
                                        <span class="badge bg-danger-subtle text-danger">Out of Stock</span>
                                    <?php endif; ?>
                                </td>
                            <?php endforeach; ?>
                        </tr>
                        <tr>
                            <th style="background-color:var(--bg-cream); text-align:left;">Actions</th>
                            <?php foreach ($products as $p): ?>
                                <td>
                                    <button type="button" class="btn-valenor-primary w-100 justify-content-center btn-sm btn-add-cart mb-2" data-product-id="<?= (int)$p['id'] ?>">
                                        <i class="bi bi-bag-plus"></i> Add to Cart
                                    </button>
                                    <a href="<?= BASE_URL ?>/product-detail.php?id=<?= (int)$p['id'] ?>" class="btn btn-sm btn-outline-dark w-100 rounded" style="border-color:var(--border-beige);">
                                        View Details &rarr;
                                    </a>
                                </td>
                            <?php endforeach; ?>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    <?php else: ?>
        <div class="text-center py-5 bg-white rounded border p-5" style="border-color:var(--border-beige) !important; max-width:600px; margin:0 auto;">
            <i class="bi bi-arrow-left-right fs-1 text-muted mb-3 d-block"></i>
            <h2 class="font-serif">No Products in Comparison</h2>
            <p class="text-muted">Browse our fine luxury collection and click the comparison icon on any watch card to compare specifications.</p>
            <a href="<?= BASE_URL ?>/products.php" class="btn-valenor-primary mt-2">Browse Watches &rarr;</a>
        </div>
    <?php endif; ?>
</div>

<?php require_once INCLUDES_PATH . '/footer.php'; ?>
