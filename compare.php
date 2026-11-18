<?php
// =============================================================================
// VALENOR HOROLOGY — PRODUCT COMPARISON SYSTEM (Section 6 & Phase 05)
// Side-by-side comparison table for 2 to 4 watches with dynamic specs,
// differences highlighting, duplicate prevention, and responsive layout
// =============================================================================

require_once __DIR__ . '/config/config.php';

$db = getDB();

// 1. CLEAR ALL
if (isset($_GET['clear'])) {
    $_SESSION['compare'] = [];
    set_flash('info', 'Comparison list cleared.');
    header('Location: ' . BASE_URL . '/compare.php');
    exit;
}

// 2. REMOVE PRODUCT
if (isset($_GET['remove'])) {
    $remId = (int)$_GET['remove'];
    if (!empty($_SESSION['compare'])) {
        $pos = array_search($remId, $_SESSION['compare']);
        if ($pos !== false) {
            unset($_SESSION['compare'][$pos]);
            $_SESSION['compare'] = array_values(array_unique($_SESSION['compare']));
            set_flash('info', 'Timepiece removed from comparison.');
        }
    }
    header('Location: ' . BASE_URL . '/compare.php');
    exit;
}

// 3. ADD PRODUCT
if (isset($_GET['add'])) {
    $addId = (int)$_GET['add'];
    if ($addId > 0) {
        if (!isset($_SESSION['compare']) || !is_array($_SESSION['compare'])) {
            $_SESSION['compare'] = [];
        }
        if (in_array($addId, $_SESSION['compare'], true)) {
            set_flash('info', 'Timepiece is already in your comparison table.');
        } elseif (count($_SESSION['compare']) >= 4) {
            set_flash('warning', 'You can compare a maximum of 4 watches at a time. Please remove an item first.');
        } else {
            $_SESSION['compare'][] = $addId;
            $_SESSION['compare'] = array_values(array_unique($_SESSION['compare']));
            set_flash('success', 'Timepiece added to comparison.');
        }
    }
    header('Location: ' . BASE_URL . '/compare.php');
    exit;
}

$compareIds = get_compare_ids();
$products = [];
$availableOtherWatches = [];

if (!empty($compareIds)) {
    $placeholders = implode(',', array_fill(0, count($compareIds), '?'));
    $stmt = $db->prepare("
        SELECT p.*, c.name AS category_name, c.slug AS category_slug,
               (SELECT image_path FROM product_images WHERE product_id = p.id AND is_primary = 1 LIMIT 1) AS primary_image,
               (SELECT AVG(rating) FROM product_reviews WHERE product_id = p.id AND status = 'approved') AS avg_rating,
               (SELECT COUNT(*) FROM product_reviews WHERE product_id = p.id AND status = 'approved') AS reviews_count
        FROM products p
        JOIN categories c ON p.category_id = c.id
        WHERE p.id IN ({$placeholders})
    ");
    $stmt->execute($compareIds);
    $products = $stmt->fetchAll();

    // Query other watches that could be added
    $stmtOther = $db->prepare("
        SELECT id, name, sku FROM products 
        WHERE status = 'active' AND id NOT IN ({$placeholders})
        ORDER BY name ASC
    ");
    $stmtOther->execute($compareIds);
    $availableOtherWatches = $stmtOther->fetchAll();
} else {
    $availableOtherWatches = $db->query("SELECT id, name, sku FROM products WHERE status = 'active' ORDER BY name ASC LIMIT 10")->fetchAll();
}

// Parse specifications JSON for each product and collect all unique spec keys
$allSpecKeys = [];
$productSpecsMap = [];

foreach ($products as $p) {
    $pid = $p['id'];
    $productSpecsMap[$pid] = [];
    if (!empty($p['specifications'])) {
        $specsArr = json_decode($p['specifications'], true);
        if (is_array($specsArr)) {
            foreach ($specsArr as $spec) {
                if (isset($spec['key']) && isset($spec['value'])) {
                    $key = trim($spec['key']);
                    $productSpecsMap[$pid][$key] = trim($spec['value']);
                    if (!in_array($key, $allSpecKeys, true)) {
                        $allSpecKeys[] = $key;
                    }
                }
            }
        }
    }
}

$pageTitle = 'Horological Comparison (' . count($products) . ' Watches)';
require_once INCLUDES_PATH . '/header.php';
require_once INCLUDES_PATH . '/navbar.php';
?>

<!-- Custom Styles for Comparison Differences Highlighting -->
<style>
.spec-compare-row {
    transition: background-color 0.2s ease, border-color 0.2s ease;
}
.spec-compare-row.diff-active {
    background-color: rgba(184, 137, 91, 0.08) !important;
}
.spec-compare-row.diff-active th {
    background-color: rgba(184, 137, 91, 0.16) !important;
    border-left: 3px solid var(--caramel-primary) !important;
    color: var(--caramel-dark) !important;
    font-weight: 700 !important;
}
.spec-compare-row.diff-active td {
    background-color: rgba(184, 137, 91, 0.04) !important;
    font-weight: 600;
}
.diff-badge {
    display: none;
    font-size: 0.65rem;
    padding: 2px 6px;
    border-radius: 4px;
    background-color: var(--caramel-primary);
    color: #fff;
    margin-left: 6px;
}
.diff-active .diff-badge {
    display: inline-block;
}
</style>

<!-- Page Header & Controls -->
<div class="py-4 border-bottom" style="background-color: var(--bg-white); border-color: var(--border-beige) !important;">
    <div class="container d-flex flex-wrap justify-content-between align-items-center gap-3">
        <div>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-1" style="font-size:0.82rem;">
                    <li class="breadcrumb-item"><a href="<?= BASE_URL ?>/index.php" style="color:var(--text-dark);">Home</a></li>
                    <li class="breadcrumb-item"><a href="<?= BASE_URL ?>/products.php" style="color:var(--text-dark);">Collections</a></li>
                    <li class="breadcrumb-item active text-muted" aria-current="page">Comparison</li>
                </ol>
            </nav>
            <h1 class="h2 mb-0 font-serif fw-bold" style="color:var(--text-dark);">Horological Comparison</h1>
            <p class="text-muted small mb-0">Side-by-side technical evaluation of luxury craftsmanship, complications, and calibres.</p>
        </div>

        <?php if (!empty($products)): ?>
            <div class="d-flex flex-wrap align-items-center gap-3">
                <!-- Highlight Differences Switch -->
                <div class="form-check form-switch d-flex align-items-center gap-2 mb-0 px-3 py-2 rounded border bg-light" style="border-color:var(--border-beige) !important;">
                    <input class="form-check-input ms-0 me-1" type="checkbox" role="switch" id="toggleHighlightDiff" style="cursor:pointer;">
                    <label class="form-check-label small fw-bold text-uppercase mb-0" for="toggleHighlightDiff" style="cursor:pointer; letter-spacing:0.04em;">
                        <i class="bi bi-lightbulb me-1" style="color:var(--caramel-primary);"></i> Highlight Differences
                    </label>
                </div>

                <!-- Add Another Watch Dropdown -->
                <?php if (count($products) < 4 && !empty($availableOtherWatches)): ?>
                    <div class="dropdown">
                        <button class="btn btn-sm btn-outline-dark dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false" style="border-color:var(--border-beige);">
                            <i class="bi bi-plus-lg me-1"></i> Add Timepiece (<?= count($products) ?>/4)
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end shadow-sm" style="max-height:280px; overflow-y:auto; border-color:var(--border-beige);">
                            <li><h6 class="dropdown-header text-uppercase small">Select Watch to Add</h6></li>
                            <?php foreach ($availableOtherWatches as $ow): ?>
                                <li>
                                    <a class="dropdown-item small py-2" href="<?= BASE_URL ?>/compare.php?add=<?= (int)$ow['id'] ?>">
                                        <?= e($ow['name']) ?> <span class="text-muted">(<?= e($ow['sku']) ?>)</span>
                                    </a>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <!-- Clear Comparison -->
                <a href="<?= BASE_URL ?>/compare.php?clear=1" class="btn btn-sm btn-outline-danger" onclick="return confirm('Clear all timepieces from comparison?');">
                    <i class="bi bi-trash3 me-1"></i> Clear All
                </a>
            </div>
        <?php endif; ?>
    </div>
</div>

<div class="container py-5">
    <?php if (count($products) >= 1): ?>
        <?php if (count($products) < 2): ?>
            <div class="alert alert-warning mb-4 d-flex align-items-center justify-content-between flex-wrap gap-2 shadow-sm" style="background-color:#FFF8ED; border-color:var(--caramel-primary); color:var(--caramel-dark);">
                <div class="d-flex align-items-center gap-2">
                    <i class="bi bi-info-circle-fill fs-5" style="color:var(--caramel-primary);"></i>
                    <div>
                        <strong>Select at least 2 timepieces</strong> to perform a comprehensive side-by-side comparison. You currently have <strong>1</strong> selected.
                    </div>
                </div>
                <div>
                    <a href="<?= BASE_URL ?>/products.php" class="btn btn-sm btn-valenor-primary">
                        Browse More Timepieces &rarr;
                    </a>
                </div>
            </div>
        <?php endif; ?>

        <!-- Comparison Master Card -->
        <div class="card bg-white border shadow-sm overflow-hidden" style="border-color:var(--border-beige) !important; border-radius:8px;">
            <div class="table-responsive">
                <table class="table table-bordered align-middle text-center mb-0" id="comparisonTable" style="border-color:var(--border-beige);">
                    <thead>
                        <tr style="background-color:var(--bg-cream);">
                            <th class="text-start p-3" style="width:220px; min-width:200px; background-color:var(--bg-cream);">
                                <span class="small fw-bold text-uppercase text-muted" style="letter-spacing:0.06em;">Horological Feature</span>
                            </th>
                            <?php foreach ($products as $p): ?>
                                <?php 
                                $imgSrc = !empty($p['primary_image']) ? BASE_URL . '/' . ltrim($p['primary_image'], '/') : BASE_URL . '/assets/images/watch-mens-chronograph.jpg';
                                ?>
                                <th style="min-width:240px; width:<?= round(100 / (count($products) + 1)) ?>%;">
                                    <div class="position-relative p-2">
                                        <a href="<?= BASE_URL ?>/compare.php?remove=<?= (int)$p['id'] ?>" class="btn btn-sm btn-outline-danger position-absolute top-0 end-0 border-0 fs-5 p-1" title="Remove timepiece">&times;</a>
                                        <a href="<?= BASE_URL ?>/product-detail.php?id=<?= (int)$p['id'] ?>">
                                            <img src="<?= e($imgSrc) ?>" alt="<?= e($p['name']) ?>" loading="lazy" class="rounded border mb-2" style="width:120px; height:120px; object-fit:cover; border-color:var(--border-beige) !important;">
                                        </a>
                                        <h5 class="font-serif fw-bold mb-1" style="font-size:1.05rem; line-height:1.3;">
                                            <a href="<?= BASE_URL ?>/product-detail.php?id=<?= (int)$p['id'] ?>" class="text-decoration-none" style="color:var(--text-dark);">
                                                <?= e($p['name']) ?>
                                            </a>
                                        </h5>
                                        <span class="badge" style="background-color:var(--caramel-dark); font-size:0.7rem;"><?= e($p['category_name']) ?></span>
                                    </div>
                                </th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>

                        <!-- 1. Brand -->
                        <tr class="spec-compare-row" data-field="brand">
                            <th class="text-start ps-3 py-3" style="background-color:var(--bg-cream);">
                                Brand / Manufacture <span class="diff-badge">Differs</span>
                            </th>
                            <?php foreach ($products as $p): ?>
                                <td class="py-3 fw-semibold"><?= e($p['brand']) ?></td>
                            <?php endforeach; ?>
                        </tr>

                        <!-- 2. Reference SKU -->
                        <tr class="spec-compare-row" data-field="sku">
                            <th class="text-start ps-3 py-3" style="background-color:var(--bg-cream);">
                                Reference SKU <span class="diff-badge">Differs</span>
                            </th>
                            <?php foreach ($products as $p): ?>
                                <td class="py-3"><span class="badge font-monospace text-dark bg-light border" style="border-color:var(--border-beige); font-size:0.85rem;"><?= e($p['sku']) ?></span></td>
                            <?php endforeach; ?>
                        </tr>

                        <!-- 3. Price -->
                        <tr class="spec-compare-row" data-field="price">
                            <th class="text-start ps-3 py-3" style="background-color:var(--bg-cream);">
                                Acquisition Price <span class="diff-badge">Differs</span>
                            </th>
                            <?php foreach ($products as $p): ?>
                                <?php 
                                $finalPrice = (!empty($p['discount_price']) && $p['discount_price'] > 0) ? (float)$p['discount_price'] : (float)$p['regular_price'];
                                ?>
                                <td class="py-3">
                                    <div class="fw-bold fs-5" style="color:var(--caramel-dark);">
                                        <?= format_price($finalPrice) ?>
                                    </div>
                                    <?php if (!empty($p['discount_price']) && $p['discount_price'] < $p['regular_price']): ?>
                                        <div class="text-muted text-decoration-line-through small"><?= format_price($p['regular_price']) ?></div>
                                    <?php endif; ?>
                                </td>
                            <?php endforeach; ?>
                        </tr>

                        <!-- 4. Discount & Savings -->
                        <tr class="spec-compare-row" data-field="discount">
                            <th class="text-start ps-3 py-3" style="background-color:var(--bg-cream);">
                                Promotional Discount <span class="diff-badge">Differs</span>
                            </th>
                            <?php foreach ($products as $p): ?>
                                <td class="py-3">
                                    <?php if (!empty($p['discount_price']) && $p['discount_price'] < $p['regular_price']): ?>
                                        <?php 
                                        $savings = $p['regular_price'] - $p['discount_price'];
                                        $pct = round(($savings / $p['regular_price']) * 100);
                                        ?>
                                        <span class="badge bg-danger-subtle text-danger py-1 px-2" style="font-size:0.75rem;">
                                            Save <?= $pct ?>% (<?= format_price($savings) ?> off)
                                        </span>
                                    <?php else: ?>
                                        <span class="text-muted small">Standard Collector Price</span>
                                    <?php endif; ?>
                                </td>
                            <?php endforeach; ?>
                        </tr>

                        <!-- 5. Rating & Reviews -->
                        <tr class="spec-compare-row" data-field="rating">
                            <th class="text-start ps-3 py-3" style="background-color:var(--bg-cream);">
                                Collector Rating <span class="diff-badge">Differs</span>
                            </th>
                            <?php foreach ($products as $p): ?>
                                <td class="py-3">
                                    <?= star_rating_html($p['avg_rating'] ?: 5.0, $p['reviews_count']) ?>
                                    <div class="text-muted small mt-1"><?= number_format($p['avg_rating'] ?: 5.0, 1) ?> / 5.0</div>
                                </td>
                            <?php endforeach; ?>
                        </tr>

                        <!-- 6. Short Description -->
                        <tr class="spec-compare-row" data-field="description">
                            <th class="text-start ps-3 py-3" style="background-color:var(--bg-cream);">
                                Editorial Summary <span class="diff-badge">Differs</span>
                            </th>
                            <?php foreach ($products as $p): ?>
                                <td class="py-3 small text-muted text-start px-3" style="line-height:1.5;">
                                    <?= e($p['short_description'] ?: 'Exquisite luxury timepiece handcrafted with precision.') ?>
                                </td>
                            <?php endforeach; ?>
                        </tr>

                        <!-- 7. Colors / Dial Finishes -->
                        <tr class="spec-compare-row" data-field="colors">
                            <th class="text-start ps-3 py-3" style="background-color:var(--bg-cream);">
                                Available Colors / Finishes <span class="diff-badge">Differs</span>
                            </th>
                            <?php foreach ($products as $p): ?>
                                <td class="py-3"><?= e($p['colors'] ?: 'Standard Signature Finish') ?></td>
                            <?php endforeach; ?>
                        </tr>

                        <!-- 8. Sizes / Dimensions -->
                        <tr class="spec-compare-row" data-field="sizes">
                            <th class="text-start ps-3 py-3" style="background-color:var(--bg-cream);">
                                Case Sizes / Dimensions <span class="diff-badge">Differs</span>
                            </th>
                            <?php foreach ($products as $p): ?>
                                <td class="py-3 fw-bold"><?= e($p['sizes'] ?: '40 mm') ?></td>
                            <?php endforeach; ?>
                        </tr>

                        <!-- 9. Stock Availability -->
                        <tr class="spec-compare-row" data-field="availability">
                            <th class="text-start ps-3 py-3" style="background-color:var(--bg-cream);">
                                Stock Availability <span class="diff-badge">Differs</span>
                            </th>
                            <?php foreach ($products as $p): ?>
                                <td class="py-3">
                                    <?php if ($p['stock_quantity'] > 0): ?>
                                        <span class="badge bg-success-subtle text-success py-1 px-2">
                                            <i class="bi bi-check-circle me-1"></i> In Stock (<?= (int)$p['stock_quantity'] ?>)
                                        </span>
                                    <?php else: ?>
                                        <span class="badge bg-danger-subtle text-danger py-1 px-2">
                                            <i class="bi bi-x-circle me-1"></i> Out of Stock
                                        </span>
                                    <?php endif; ?>
                                </td>
                            <?php endforeach; ?>
                        </tr>

                        <!-- SECTION HEADER: TECHNICAL SPECIFICATIONS -->
                        <?php if (!empty($allSpecKeys)): ?>
                            <tr style="background-color:var(--bg-cream);">
                                <th colspan="<?= count($products) + 1 ?>" class="text-start ps-3 py-2 small fw-bold text-uppercase" style="letter-spacing:0.08em; color:var(--caramel-dark);">
                                    <i class="bi bi-gear-fill me-1"></i> Horological Specifications
                                </th>
                            </tr>

                            <?php foreach ($allSpecKeys as $specKey): ?>
                                <tr class="spec-compare-row" data-field="spec-<?= e(slugify($specKey)) ?>">
                                    <th class="text-start ps-3 py-3" style="background-color:var(--bg-cream);">
                                        <?= e($specKey) ?> <span class="diff-badge">Differs</span>
                                    </th>
                                    <?php foreach ($products as $p): ?>
                                        <?php 
                                        $val = $productSpecsMap[$p['id']][$specKey] ?? '—';
                                        ?>
                                        <td class="py-3"><?= e($val) ?></td>
                                    <?php endforeach; ?>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>

                        <!-- BOTTOM ROW: ACTIONS -->
                        <tr>
                            <th class="text-start ps-3 py-4" style="background-color:var(--bg-cream);">
                                Actions
                            </th>
                            <?php foreach ($products as $p): ?>
                                <td class="py-4 px-3">
                                    <?php if ($p['stock_quantity'] > 0): ?>
                                        <button type="button" class="btn-valenor-primary w-100 justify-content-center btn-sm btn-add-cart mb-2" data-product-id="<?= (int)$p['id'] ?>">
                                            <i class="bi bi-bag-plus"></i> Add to Bag
                                        </button>
                                    <?php else: ?>
                                        <button type="button" class="btn btn-secondary w-100 btn-sm mb-2" disabled>
                                            Out of Stock
                                        </button>
                                    <?php endif; ?>

                                    <div class="d-flex gap-2">
                                        <button type="button" class="btn btn-sm btn-outline-dark w-50 btn-toggle-wishlist <?= is_wishlisted($p['id']) ? 'active' : '' ?>" data-product-id="<?= (int)$p['id'] ?>" style="border-color:var(--border-beige);" title="Save to Wishlist">
                                            <i class="bi <?= is_wishlisted($p['id']) ? 'bi-heart-fill text-danger' : 'bi-heart' ?>"></i> Save
                                        </button>
                                        <a href="<?= BASE_URL ?>/product-detail.php?id=<?= (int)$p['id'] ?>" class="btn btn-sm btn-outline-dark w-50" style="border-color:var(--border-beige);">
                                            Details &rarr;
                                        </a>
                                    </div>
                                </td>
                            <?php endforeach; ?>
                        </tr>

                    </tbody>
                </table>
            </div>
        </div>

    <?php else: ?>
        <!-- Empty State -->
        <div class="text-center py-5 bg-white rounded border p-5 shadow-sm" style="border-color:var(--border-beige) !important; max-width:650px; margin:0 auto; border-radius:8px;">
            <div class="mb-3">
                <span class="d-inline-flex align-items-center justify-content-center rounded-circle" style="width:80px; height:80px; background-color:var(--bg-cream); border:1px solid var(--border-beige);">
                    <i class="bi bi-arrow-left-right fs-1" style="color:var(--caramel-primary);"></i>
                </span>
            </div>
            <span class="text-uppercase small fw-bold" style="color:var(--caramel-primary); letter-spacing:0.15em;">Horological Analysis</span>
            <h2 class="font-serif fw-bold mt-1 mb-2">No Timepieces in Comparison</h2>
            <p class="text-muted mb-4" style="max-width:440px; margin:0 auto;">
                Select up to 4 luxury timepieces from our catalog to compare dial designs, movements, case dimensions, and materials side-by-side.
            </p>
            <a href="<?= BASE_URL ?>/products.php" class="btn-valenor-primary px-4 py-2">
                <i class="bi bi-compass me-1"></i> Discover Timepieces &rarr;
            </a>
        </div>
    <?php endif; ?>
</div>

<!-- JavaScript for Highlight Differences Switch -->
<script>
document.addEventListener('DOMContentLoaded', function () {
    const toggleDiff = document.getElementById('toggleHighlightDiff');
    const table = document.getElementById('comparisonTable');

    if (!toggleDiff || !table) return;

    function evaluateDifferences() {
        const rows = table.querySelectorAll('tbody tr.spec-compare-row');
        const isHighlight = toggleDiff.checked;

        rows.forEach(row => {
            const cells = row.querySelectorAll('td');
            if (cells.length < 2) {
                row.classList.remove('diff-active');
                return;
            }

            // Extract trimmed text contents
            const values = Array.from(cells).map(cell => cell.textContent.trim().replace(/\s+/g, ' '));
            const firstVal = values[0];
            const hasDifference = values.some(val => val !== firstVal);

            if (isHighlight && hasDifference) {
                row.classList.add('diff-active');
            } else {
                row.classList.remove('diff-active');
            }
        });
    }

    toggleDiff.addEventListener('change', evaluateDifferences);
});
</script>

<?php require_once INCLUDES_PATH . '/footer.php'; ?>
