<?php
// =============================================================================
// VALENOR HOROLOGY — ADMIN PRODUCTS MANAGEMENT (Section 5)
// View All Products with search, category filtering, stock, and status management
// =============================================================================

require_once __DIR__ . '/../config/config.php';
require_admin();

$db = getDB();

// Handle POST Actions (e.g. Quick Toggle Status Active/Inactive)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) {
        set_flash('danger', 'Security verification token invalid. Please try again.');
        header('Location: ' . ADMIN_URL . '/products.php');
        exit;
    }

    $action = sanitize($_POST['action'] ?? '');

    if ($action === 'toggle_status') {
        $productId = (int)($_POST['product_id'] ?? 0);
        $stmt = $db->prepare("SELECT id, name, status FROM products WHERE id = ?");
        $stmt->execute([$productId]);
        $prod = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($prod) {
            $newStatus = ($prod['status'] === 'active') ? 'inactive' : 'active';
            $update = $db->prepare("UPDATE products SET status = ?, updated_at = NOW() WHERE id = ?");
            $update->execute([$newStatus, $productId]);

            $statusLabel = ($newStatus === 'active') ? 'ACTIVE (Visible in Storefront)' : 'INACTIVE (Hidden from Storefront)';
            set_flash('success', "Timepiece '{$prod['name']}' status updated to {$statusLabel}.");
        } else {
            set_flash('danger', 'Specified timepiece record not found.');
        }

        $returnUrl = sanitize($_POST['return_url'] ?? '');
        if (empty($returnUrl) || !str_contains($returnUrl, 'products.php')) {
            $returnUrl = ADMIN_URL . '/products.php';
        }
        header('Location: ' . $returnUrl);
        exit;
    }
}

$adminTitle      = 'Manage Products';
$adminPageHeader = 'Timepiece Inventory & Catalog';
$adminPageSub    = 'Create, update, toggle visibility, and oversee product image galleries.';

$search   = sanitize($_GET['q'] ?? '');
$catId    = (int)($_GET['category_id'] ?? 0);
$status   = sanitize($_GET['status'] ?? '');
$stockFilter = sanitize($_GET['stock'] ?? '');

$where = ['1=1'];
$params = [];

if (!empty($search)) {
    $where[] = "(p.name LIKE ? OR p.sku LIKE ? OR p.brand LIKE ?)";
    $term = "%{$search}%";
    $params = array_merge($params, [$term, $term, $term]);
}

if ($catId > 0) {
    $where[] = "p.category_id = ?";
    $params[] = $catId;
}

if (!empty($status)) {
    $where[] = "p.status = ?";
    $params[] = $status;
}

if ($stockFilter === 'low') {
    $where[] = "p.stock_quantity <= 5";
} elseif ($stockFilter === 'out') {
    $where[] = "p.stock_quantity = 0";
}

$whereSql = implode(' AND ', $where);

$sql = "
    SELECT p.*, c.name AS category_name,
           (SELECT image_path FROM product_images WHERE product_id = p.id AND is_primary = 1 LIMIT 1) AS primary_image,
           (SELECT COUNT(*) FROM product_images WHERE product_id = p.id) AS image_count,
           (SELECT COUNT(*) FROM product_variants WHERE product_id = p.id) AS variant_count
    FROM products p
    JOIN categories c ON p.category_id = c.id
    WHERE {$whereSql}
    ORDER BY p.id DESC
";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$products = $stmt->fetchAll();

// Fetch categories for filter dropdown
$categories = $db->query("SELECT id, name FROM categories ORDER BY sort_order ASC")->fetchAll();

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';
require_once __DIR__ . '/includes/navbar.php';
?>

<div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-3">
    <div>
        <h4 class="font-serif fw-bold mb-1" style="color:var(--text-charcoal);">All Timepieces (<?= count($products) ?>)</h4>
        <div class="small text-muted">Manage specifications, stock levels, images, and catalog curations.</div>
    </div>
    <div>
        <a href="<?= ADMIN_URL ?>/product-add.php" class="btn btn-caramel">
            <i class="bi bi-plus-circle me-1"></i> Add New Product
        </a>
    </div>
</div>

<!-- Filters Bar -->
<div class="card border-0 shadow-sm mb-4" style="border:1px solid var(--border-beige) !important;">
    <div class="card-body p-3">
        <form method="GET" action="<?= ADMIN_URL ?>/products.php" class="row g-2 align-items-center">
            <div class="col-md-4">
                <div class="input-group input-group-sm">
                    <span class="input-group-text bg-light border-beige"><i class="bi bi-search"></i></span>
                    <input type="text" name="q" class="form-control" placeholder="Search by name, SKU, brand..." value="<?= e($search) ?>">
                </div>
            </div>
            <div class="col-md-3">
                <select name="category_id" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="">All Categories</option>
                    <?php foreach ($categories as $c): ?>
                        <option value="<?= $c['id'] ?>" <?= $catId === (int)$c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <select name="status" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="">All Statuses</option>
                    <option value="active" <?= $status === 'active' ? 'selected' : '' ?>>Active</option>
                    <option value="draft" <?= $status === 'draft' ? 'selected' : '' ?>>Draft</option>
                    <option value="inactive" <?= $status === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                </select>
            </div>
            <div class="col-md-2">
                <select name="stock" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="">All Stock</option>
                    <option value="low" <?= $stockFilter === 'low' ? 'selected' : '' ?>>Low Stock (&le; 5)</option>
                    <option value="out" <?= $stockFilter === 'out' ? 'selected' : '' ?>>Out of Stock (0)</option>
                </select>
            </div>
            <div class="col-md-1 d-flex gap-1">
                <button type="submit" class="btn btn-sm btn-dark w-100">Filter</button>
                <?php if (!empty($search) || $catId > 0 || !empty($status) || !empty($stockFilter)): ?>
                    <a href="<?= ADMIN_URL ?>/products.php" class="btn btn-sm btn-outline-secondary" title="Reset Filters"><i class="bi bi-x"></i></a>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<!-- Products Table -->
<div class="card border-0 shadow-sm admin-table">
    <div class="table-responsive">
        <table class="table mb-0">
            <thead>
                <tr>
                    <th style="width:60px;">Image</th>
                    <th>Product &amp; SKU</th>
                    <th>Category</th>
                    <th>Price</th>
                    <th>Stock</th>
                    <th>Highlights</th>
                    <th>Status</th>
                    <th style="width:130px;" class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($products)): ?>
                    <?php foreach ($products as $p): ?>
                        <tr>
                            <!-- Image -->
                            <td>
                                <img src="<?= e(BASE_URL . '/' . ltrim($p['primary_image'] ?? 'assets/images/watch-mens-chronograph.jpg', '/')) ?>" alt="" style="width:48px; height:48px; object-fit:cover; border-radius:6px; border:1px solid var(--border-beige);">
                            </td>

                            <!-- Product & SKU -->
                            <td>
                                <div class="fw-bold" style="color:var(--text-charcoal);"><?= e($p['name']) ?></div>
                                <div class="d-flex align-items-center gap-2 small text-muted">
                                    <span>SKU: <strong class="font-monospace text-dark"><?= e($p['sku']) ?></strong></span>
                                    <span>&bull;</span>
                                    <span><i class="bi bi-images me-1"></i><?= (int)$p['image_count'] ?> images</span>
                                    <?php if ($p['variant_count'] > 0): ?>
                                        <span>&bull;</span>
                                        <span><i class="bi bi-palette me-1"></i><?= (int)$p['variant_count'] ?> variants</span>
                                    <?php endif; ?>
                                </div>
                            </td>

                            <!-- Category -->
                            <td>
                                <span class="badge bg-light text-dark border"><?= e($p['category_name']) ?></span>
                            </td>

                            <!-- Price -->
                            <td>
                                <div class="fw-bold"><?= format_price($p['discount_price'] ?: $p['regular_price']) ?></div>
                                <?php if (!empty($p['discount_price']) && $p['discount_price'] < $p['regular_price']): ?>
                                    <small class="text-muted text-decoration-line-through"><?= format_price($p['regular_price']) ?></small>
                                    <span class="badge bg-danger small ms-1">-<?= (int)$p['discount_percentage'] ?>%</span>
                                <?php endif; ?>
                            </td>

                            <!-- Stock -->
                            <td>
                                <?php if ($p['stock_quantity'] > 5): ?>
                                    <span class="badge bg-success-subtle text-success fw-bold"><?= (int)$p['stock_quantity'] ?> in stock</span>
                                <?php elseif ($p['stock_quantity'] > 0): ?>
                                    <span class="badge bg-warning-subtle text-warning fw-bold"><?= (int)$p['stock_quantity'] ?> low stock</span>
                                <?php else: ?>
                                    <span class="badge bg-danger-subtle text-danger fw-bold">Out of stock</span>
                                <?php endif; ?>
                            </td>

                            <!-- Highlights -->
                            <td>
                                <div class="d-flex flex-wrap gap-1">
                                    <?php if ($p['is_featured']): ?>
                                        <span class="badge bg-primary text-white" style="font-size:0.68rem;">Featured</span>
                                    <?php endif; ?>
                                    <?php if ($p['is_new_arrival']): ?>
                                        <span class="badge bg-warning text-dark" style="font-size:0.68rem;">New</span>
                                    <?php endif; ?>
                                    <?php if ($p['is_best_seller']): ?>
                                        <span class="badge bg-success text-white" style="font-size:0.68rem;">Bestseller</span>
                                    <?php endif; ?>
                                </div>
                            </td>

                            <!-- Status (1-Click Toggle Button) -->
                            <td>
                                <form method="POST" action="<?= ADMIN_URL ?>/products.php" class="d-inline" onsubmit="event.stopPropagation();">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="toggle_status">
                                    <input type="hidden" name="product_id" value="<?= $p['id'] ?>">
                                    <input type="hidden" name="return_url" value="<?= e($_SERVER['REQUEST_URI']) ?>">
                                    <?php if ($p['status'] === 'active'): ?>
                                        <button type="submit" class="btn btn-sm btn-success py-1 px-2 rounded-pill fw-semibold shadow-none border-0" 
                                                title="Status is ACTIVE. Click to switch to INACTIVE" style="font-size:0.75rem; min-width:82px;">
                                            <i class="bi bi-check-circle-fill me-1"></i> Active
                                        </button>
                                    <?php elseif ($p['status'] === 'draft'): ?>
                                        <button type="submit" class="btn btn-sm btn-secondary py-1 px-2 rounded-pill fw-semibold shadow-none border-0" 
                                                title="Status is DRAFT. Click to switch to ACTIVE" style="font-size:0.75rem; min-width:82px;">
                                            <i class="bi bi-file-earmark me-1"></i> Draft
                                        </button>
                                    <?php else: ?>
                                        <button type="submit" class="btn btn-sm btn-outline-danger py-1 px-2 rounded-pill fw-semibold shadow-none" 
                                                title="Status is INACTIVE. Click to switch to ACTIVE" style="font-size:0.75rem; min-width:82px; background:#fff;">
                                            <i class="bi bi-slash-circle me-1"></i> Inactive
                                        </button>
                                    <?php endif; ?>
                                </form>
                            </td>

                            <!-- Actions -->
                            <td class="text-end text-nowrap">
                                <!-- 1-Click Status Toggle Quick Action -->
                                <form method="POST" action="<?= ADMIN_URL ?>/products.php" class="d-inline" onsubmit="event.stopPropagation();">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="toggle_status">
                                    <input type="hidden" name="product_id" value="<?= $p['id'] ?>">
                                    <input type="hidden" name="return_url" value="<?= e($_SERVER['REQUEST_URI']) ?>">
                                    <button type="submit" class="btn btn-sm <?= $p['status'] === 'active' ? 'btn-outline-warning' : 'btn-outline-success' ?> p-1 px-2" 
                                            title="<?= $p['status'] === 'active' ? 'Click to make Inactive' : 'Click to make Active' ?>">
                                        <i class="bi <?= $p['status'] === 'active' ? 'bi-pause-circle' : 'bi-play-circle' ?>"></i>
                                    </button>
                                </form>
                                <a href="<?= BASE_URL ?>/product-detail.php?id=<?= $p['id'] ?>" target="_blank" class="btn btn-sm btn-outline-secondary p-1 px-2 ms-1" title="View on Storefront">
                                    <i class="bi bi-eye"></i>
                                </a>
                                <a href="<?= ADMIN_URL ?>/product-edit.php?id=<?= $p['id'] ?>" class="btn btn-sm btn-outline-dark p-1 px-2 ms-1" title="Edit Timepiece">
                                    <i class="bi bi-pencil"></i>
                                </a>
                                <button type="button" class="btn btn-sm btn-outline-danger p-1 px-2 ms-1" data-bs-toggle="modal" data-bs-target="#deleteProductModal<?= $p['id'] ?>" title="Delete Timepiece">
                                    <i class="bi bi-trash"></i>
                                </button>

                                <!-- Delete Confirmation Modal -->
                                <div class="modal fade" id="deleteProductModal<?= $p['id'] ?>" tabindex="-1" aria-labelledby="delModalLabel<?= $p['id'] ?>" aria-hidden="true">
                                    <div class="modal-dialog modal-dialog-centered text-start">
                                        <div class="modal-content" style="border-color:var(--border-beige);">
                                            <div class="modal-header">
                                                <h5 class="modal-title font-serif fw-bold text-danger" id="delModalLabel<?= $p['id'] ?>">Confirm Timepiece Deletion</h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                            </div>
                                            <div class="modal-body">
                                                <p>Are you sure you want to permanently delete <strong><?= e($p['name']) ?></strong> (SKU: <?= e($p['sku']) ?>)?</p>
                                                <p class="text-danger small mb-0"><i class="bi bi-exclamation-triangle-fill me-1"></i> This will delete the product, its associated images, tags, and variants from the catalogue.</p>
                                            </div>
                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                <form action="<?= ADMIN_URL ?>/product-delete.php" method="POST">
                                                    <?= csrf_field() ?>
                                                    <input type="hidden" name="id" value="<?= $p['id'] ?>">
                                                    <button type="submit" class="btn btn-sm btn-danger">Confirm Delete</button>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="8" class="text-center py-5 text-muted">
                            <i class="bi bi-watch fs-1 d-block mb-2 text-muted"></i>
                            No timepieces matched your active filter criteria.
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
