<?php
// =============================================================================
// VALENOR HOROLOGY — ADMIN PRODUCT EDIT & IMAGE MANAGEMENT (Section 4 & 5)
// Full Product Updating + Complete Image Suite: Upload 1-10, Reorder, Delete, Set Primary
// =============================================================================

require_once __DIR__ . '/../config/config.php';
require_admin();

$db = getDB();

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    set_flash('error', 'Invalid product selected.');
    header('Location: ' . ADMIN_URL . '/products.php');
    exit;
}

// Fetch existing product
$stmt = $db->prepare("SELECT * FROM products WHERE id = ? LIMIT 1");
$stmt->execute([$id]);
$product = $stmt->fetch();

if (!$product) {
    set_flash('error', 'Product not found in the catalogue.');
    header('Location: ' . ADMIN_URL . '/products.php');
    exit;
}

$adminTitle      = 'Edit ' . $product['name'];
$adminPageHeader = 'Edit Timepiece & Gallery';
$adminPageSub    = 'Refine product details, horological specifications, stock, and manage up to 10 product images.';

$errors = [];
$success = '';

// =============================================================================
// HANDLE SPECIAL IMAGE ACTIONS (Set Primary, Delete Image, Upload Extra Images)
// =============================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['image_action'])) {
    if (!verify_csrf()) {
        $errors[] = 'Security token mismatch. Action aborted.';
    } else {
        $imgAction = $_POST['image_action'];

        // 1. SET PRIMARY IMAGE
        if ($imgAction === 'set_primary') {
            $imgId = (int)($_POST['image_id'] ?? 0);
            if ($imgId > 0) {
                $db->prepare("UPDATE product_images SET is_primary = 0 WHERE product_id = ?")->execute([$id]);
                $db->prepare("UPDATE product_images SET is_primary = 1 WHERE id = ? AND product_id = ?")->execute([$imgId, $id]);
                set_flash('success', 'Primary featured image updated.');
                header('Location: ' . ADMIN_URL . '/product-edit.php?id=' . $id);
                exit;
            }
        }

        // 2. DELETE INDIVIDUAL IMAGE (Section 4)
        if ($imgAction === 'delete_image') {
            $imgId = (int)($_POST['image_id'] ?? 0);
            // Check total current images
            $totalImgCount = (int)$db->prepare("SELECT COUNT(*) FROM product_images WHERE product_id = ?")->execute([$id]);
            $currentCount = (int)$db->query("SELECT COUNT(*) FROM product_images WHERE product_id = {$id}")->fetchColumn();

            if ($currentCount <= 1) {
                $errors[] = 'Cannot delete image. Every product must retain at least ONE image (minimum 1, maximum 10).';
            } else {
                $imgRow = $db->prepare("SELECT * FROM product_images WHERE id = ? AND product_id = ? LIMIT 1");
                $imgRow->execute([$imgId, $id]);
                $imgData = $imgRow->fetch();

                if ($imgData) {
                    $wasPrimary = (int)$imgData['is_primary'];
                    $db->prepare("DELETE FROM product_images WHERE id = ?")->execute([$imgId]);

                    // If was primary, designate first remaining as primary
                    if ($wasPrimary) {
                        $firstRem = $db->query("SELECT id FROM product_images WHERE product_id = {$id} ORDER BY sort_order ASC, id ASC LIMIT 1")->fetchColumn();
                        if ($firstRem) {
                            $db->prepare("UPDATE product_images SET is_primary = 1 WHERE id = ?")->execute([$firstRem]);
                        }
                    }

                    // Remove file if custom upload
                    if (str_starts_with($imgData['image_path'], 'uploads/products/img_') || str_starts_with($imgData['image_path'], 'uploads/products/watch_')) {
                        $fullFile = ROOT_PATH . '/' . ltrim($imgData['image_path'], '/');
                        if (is_file($fullFile)) @unlink($fullFile);
                    }

                    set_flash('success', 'Product photo removed successfully.');
                    header('Location: ' . ADMIN_URL . '/product-edit.php?id=' . $id);
                    exit;
                }
            }
        }

        // 3. UPLOAD ADDITIONAL IMAGES (Section 4: up to 10 total)
        if ($imgAction === 'upload_extra_images') {
            $currentCount = (int)$db->query("SELECT COUNT(*) FROM product_images WHERE product_id = {$id}")->fetchColumn();
            $files = $_FILES['extra_images'] ?? null;

            if (!$files || empty($files['name'][0])) {
                $errors[] = 'Please select at least one image file to upload.';
            } else {
                $fileCount = count($files['name']);
                if ($currentCount + $fileCount > 10) {
                    $errors[] = 'Exceeds maximum image limit. Currently ' . $currentCount . ' images exist; you can upload at most ' . (10 - $currentCount) . ' additional images.';
                } else {
                    $added = 0;
                    for ($i = 0; $i < $fileCount; $i++) {
                        if ($files['error'][$i] === UPLOAD_ERR_NO_FILE) continue;

                        $singleFile = [
                            'name'     => $files['name'][$i],
                            'type'     => $files['type'][$i],
                            'tmp_name' => $files['tmp_name'][$i],
                            'error'    => $files['error'][$i],
                            'size'     => $files['size'][$i],
                        ];

                        $res = upload_image($singleFile, PRODUCT_UPLOADS_PATH, 'watch');
                        if (!$res['success']) {
                            $errors[] = "Image #" . ($i + 1) . ": " . $res['error'];
                        } else {
                            $maxSort = (int)$db->query("SELECT COALESCE(MAX(sort_order), 0) FROM product_images WHERE product_id = {$id}")->fetchColumn();
                            $db->prepare("
                                INSERT INTO product_images (product_id, image_path, is_primary, sort_order, created_at)
                                VALUES (?, ?, 0, ?, NOW())
                            ")->execute([$id, $res['rel_path'], $maxSort + 1]);
                            $added++;
                        }
                    }

                    if ($added > 0) {
                        set_flash('success', "{$added} additional image(s) uploaded successfully.");
                        header('Location: ' . ADMIN_URL . '/product-edit.php?id=' . $id);
                        exit;
                    }
                }
            }
        }

        // 4. REORDER IMAGES (Section 4)
        if ($imgAction === 'reorder_images') {
            $sortOrders = $_POST['sort_order'] ?? [];
            foreach ($sortOrders as $imgId => $orderVal) {
                $db->prepare("UPDATE product_images SET sort_order = ? WHERE id = ? AND product_id = ?")
                   ->execute([(int)$orderVal, (int)$imgId, $id]);
            }
            set_flash('success', 'Image order sequence saved.');
            header('Location: ' . ADMIN_URL . '/product-edit.php?id=' . $id);
            exit;
        }
    }
}

// =============================================================================
// HANDLE MAIN PRODUCT DETAILS UPDATE
// =============================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['image_action'])) {
    if (!verify_csrf()) {
        $errors[] = 'Security token mismatch. Please resubmit.';
    } else {
        $name        = sanitize($_POST['name'] ?? '');
        $sku         = strtoupper(sanitize($_POST['sku'] ?? ''));
        $categoryId  = (int)($_POST['category_id'] ?? 0);
        $brand       = sanitize($_POST['brand'] ?? 'VALENOR');
        $shortDesc   = sanitize($_POST['short_description'] ?? '');
        $fullDesc    = sanitize($_POST['full_description'] ?? '');
        $regPrice    = (float)($_POST['regular_price'] ?? 0);
        $discPrice   = !empty($_POST['discount_price']) ? (float)$_POST['discount_price'] : null;
        $colors      = sanitize($_POST['colors'] ?? '');
        $sizes       = sanitize($_POST['sizes'] ?? '');
        $stock       = (int)($_POST['stock_quantity'] ?? 0);
        $status      = in_array($_POST['status'] ?? '', ['active', 'draft', 'inactive']) ? $_POST['status'] : 'active';
        $isFeatured  = !empty($_POST['is_featured']) ? 1 : 0;
        $isNew       = !empty($_POST['is_new_arrival']) ? 1 : 0;
        $isBest      = !empty($_POST['is_best_seller']) ? 1 : 0;
        $tagsInput   = sanitize($_POST['tags'] ?? '');
        $hashInput   = sanitize($_POST['hashtags'] ?? '');

        if (empty($name)) $errors[] = 'Product name is required.';
        if (empty($sku))  $errors[] = 'SKU reference is required.';
        else {
            $skuCheck = $db->prepare("SELECT id FROM products WHERE sku = ? AND id != ? LIMIT 1");
            $skuCheck->execute([$sku, $id]);
            if ($skuCheck->fetch()) {
                $errors[] = "SKU '{$sku}' is already assigned to another timepiece.";
            }
        }
        if ($categoryId <= 0) $errors[] = 'Please select a valid master category.';
        if ($regPrice <= 0)   $errors[] = 'Regular price must be greater than zero.';
        if ($discPrice !== null && $discPrice >= $regPrice) {
            $errors[] = 'Discounted price must be strictly less than the regular price.';
        }
        if (empty($shortDesc)) $errors[] = 'Short description is required.';
        if (empty($fullDesc))  $errors[] = 'Full craftsmanship description is required.';

        // Specifications
        $specKeys   = $_POST['spec_key'] ?? [];
        $specValues = $_POST['spec_val'] ?? [];
        $specsArray = [];
        for ($i = 0; $i < count($specKeys); $i++) {
            $k = trim($specKeys[$i] ?? '');
            $v = trim($specValues[$i] ?? '');
            if (!empty($k) && !empty($v)) {
                $specsArray[] = ['key' => $k, 'value' => $v];
            }
        }
        $specsJson = !empty($specsArray) ? json_encode($specsArray) : null;

        if (empty($errors)) {
            try {
                $db->beginTransaction();

                $slug = slugify($name . '-' . $sku);

                $upStmt = $db->prepare("
                    UPDATE products SET 
                        name = ?,
                        slug = ?,
                        sku = ?,
                        category_id = ?,
                        brand = ?,
                        short_description = ?,
                        full_description = ?,
                        regular_price = ?,
                        discount_price = ?,
                        specifications = ?,
                        colors = ?,
                        sizes = ?,
                        stock_quantity = ?,
                        status = ?,
                        is_featured = ?,
                        is_new_arrival = ?,
                        is_best_seller = ?,
                        updated_at = NOW()
                    WHERE id = ?
                ");

                $upStmt->execute([
                    $name,
                    $slug,
                    $sku,
                    $categoryId,
                    $brand,
                    $shortDesc,
                    $fullDesc,
                    $regPrice,
                    $discPrice,
                    $specsJson,
                    $colors,
                    $sizes,
                    $stock,
                    $status,
                    $isFeatured,
                    $isNew,
                    $isBest,
                    $id
                ]);

                // Update tags
                $db->prepare("DELETE FROM product_tags WHERE product_id = ?")->execute([$id]);

                if (!empty($tagsInput)) {
                    $tagParts = array_map('trim', explode(',', $tagsInput));
                    $tStmt = $db->prepare("INSERT INTO product_tags (product_id, tag_name, tag_type) VALUES (?, ?, 'tag')");
                    foreach ($tagParts as $t) {
                        if (!empty($t)) $tStmt->execute([$id, $t]);
                    }
                }

                if (!empty($hashInput)) {
                    $hashParts = preg_split('/[\s,]+/', $hashInput);
                    $hStmt = $db->prepare("INSERT INTO product_tags (product_id, tag_name, tag_type) VALUES (?, ?, 'hashtag')");
                    foreach ($hashParts as $h) {
                        $hClean = ltrim($h, '#');
                        if (!empty($hClean)) $hStmt->execute([$id, '#' . $hClean]);
                    }
                }

                $db->commit();

                set_flash('success', 'Timepiece details updated successfully.');
                header('Location: ' . ADMIN_URL . '/product-edit.php?id=' . $id);
                exit;

            } catch (Exception $e) {
                if ($db->inTransaction()) {
                    $db->rollBack();
                }
                $errors[] = 'Failed to update timepiece: ' . $e->getMessage();
            }
        }
    }
}

// Reload current data
$pStmt = $db->prepare("SELECT * FROM products WHERE id = ?");
$pStmt->execute([$id]);
$product = $pStmt->fetch();

// Fetch images (Section 4)
$imgStmt = $db->prepare("SELECT * FROM product_images WHERE product_id = ? ORDER BY is_primary DESC, sort_order ASC, id ASC");
$imgStmt->execute([$id]);
$images = $imgStmt->fetchAll();

// Fetch tags
$tStmt = $db->prepare("SELECT tag_name, tag_type FROM product_tags WHERE product_id = ?");
$tStmt->execute([$id]);
$allTags = $tStmt->fetchAll();
$plainTags = [];
$hashTags  = [];
foreach ($allTags as $at) {
    if ($at['tag_type'] === 'hashtag') $hashTags[] = $at['tag_name'];
    else $plainTags[] = $at['tag_name'];
}

$categories = $db->query("SELECT id, name FROM categories ORDER BY sort_order ASC")->fetchAll();
$specs = !empty($product['specifications']) ? json_decode($product['specifications'], true) : [];

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';
require_once __DIR__ . '/includes/navbar.php';
?>

<div class="mb-4">
    <div class="d-flex align-items-center justify-content-between">
        <div>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-1" style="font-size:0.8rem;">
                    <li class="breadcrumb-item"><a href="<?= ADMIN_URL ?>/index.php">Dashboard</a></li>
                    <li class="breadcrumb-item"><a href="<?= ADMIN_URL ?>/products.php">Products</a></li>
                    <li class="breadcrumb-item active"><?= e($product['name']) ?></li>
                </ol>
            </nav>
            <h4 class="font-serif fw-bold mb-0" style="color:var(--text-charcoal);"><?= e($product['name']) ?></h4>
            <span class="small text-muted font-monospace">SKU: <?= e($product['sku']) ?></span>
        </div>
        <div class="d-flex gap-2">
            <a href="<?= BASE_URL ?>/product-detail.php?id=<?= $id ?>" target="_blank" class="btn btn-sm btn-outline-secondary">
                <i class="bi bi-box-arrow-up-right me-1"></i> View Live
            </a>
            <a href="<?= ADMIN_URL ?>/products.php" class="btn btn-sm btn-outline-dark" style="border-color:var(--border-beige);">
                <i class="bi bi-arrow-left me-1"></i> Inventory List
            </a>
        </div>
    </div>
</div>

<?php if (!empty($errors)): ?>
    <div class="alert alert-danger mb-4 shadow-sm" role="alert">
        <h6 class="fw-bold mb-2"><i class="bi bi-exclamation-octagon-fill me-1"></i> Please resolve:</h6>
        <ul class="mb-0 small ps-3">
            <?php foreach ($errors as $err): ?>
                <li><?= e($err) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<!-- ======================================================================
     IMAGE MANAGEMENT SUITE (Section 4: 1 to 10 images, reorder, primary, delete)
     ====================================================================== -->
<div class="card border-0 shadow-sm p-4 mb-4" style="border:1px solid var(--border-beige) !important; background:#fff;">
    <div class="d-flex justify-content-between align-items-center mb-3 pb-2 border-bottom">
        <div>
            <h5 class="font-serif fw-bold mb-0" style="color:var(--caramel-dark);">
                <i class="bi bi-images me-1"></i> Product Image Gallery Management (<?= count($images) ?> / 10 Images)
            </h5>
            <small class="text-muted">Minimum 1 image, maximum 10 images. Designate primary featured photo, reorder, or delete.</small>
        </div>
        <?php if (count($images) < 10): ?>
            <button type="button" class="btn btn-sm btn-caramel" data-bs-toggle="collapse" data-bs-target="#uploadImagesCollapse">
                <i class="bi bi-cloud-arrow-up me-1"></i> Upload More Images
            </button>
        <?php else: ?>
            <span class="badge bg-secondary">Maximum 10 Images Reached</span>
        <?php endif; ?>
    </div>

    <!-- Upload More Images Collapse Form -->
    <div class="collapse mb-4 <?= count($images) < 1 ? 'show' : '' ?>" id="uploadImagesCollapse">
        <div class="p-3 bg-light rounded border">
            <h6 class="small fw-bold text-uppercase mb-2">Upload Additional Photos (Max <?= 10 - count($images) ?> files)</h6>
            <form method="POST" action="<?= ADMIN_URL ?>/product-edit.php?id=<?= $id ?>" enctype="multipart/form-data" class="d-flex flex-wrap gap-2 align-items-center">
                <?= csrf_field() ?>
                <input type="hidden" name="image_action" value="upload_extra_images">
                <input type="file" name="extra_images[]" class="form-control form-control-sm" multiple accept="image/jpeg,image/png,image/webp" style="max-width:350px;" required>
                <button type="submit" class="btn btn-sm btn-dark">Start Upload</button>
            </form>
        </div>
    </div>

    <!-- Images Gallery Grid & Reordering Form -->
    <form method="POST" action="<?= ADMIN_URL ?>/product-edit.php?id=<?= $id ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="image_action" value="reorder_images">

        <div class="row g-3 mb-3">
            <?php foreach ($images as $idx => $img): ?>
                <div class="col-6 col-sm-4 col-md-3 col-lg-2">
                    <div class="img-manage-card <?= $img['is_primary'] ? 'is-primary' : '' ?>">
                        <img src="<?= e(BASE_URL . '/' . ltrim($img['image_path'], '/')) ?>" alt="Timepiece angle <?= $idx + 1 ?>">
                        
                        <?php if ($img['is_primary']): ?>
                            <span class="img-badge-primary"><i class="bi bi-star-fill me-1"></i>Primary</span>
                        <?php endif; ?>

                        <!-- Quick Actions Overlay -->
                        <div class="img-actions">
                            <?php if (!$img['is_primary']): ?>
                                <button type="submit" form="setPrimaryForm<?= $img['id'] ?>" class="btn btn-sm btn-light p-1" title="Make Primary Featured Image">
                                    <i class="bi bi-star"></i>
                                </button>
                            <?php endif; ?>
                            <?php if (count($images) > 1): ?>
                                <button type="submit" form="deleteImgForm<?= $img['id'] ?>" class="btn btn-sm btn-danger p-1" onclick="return confirm('Delete this product photo?');" title="Delete Image">
                                    <i class="bi bi-trash"></i>
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Sort Order Input -->
                    <div class="d-flex align-items-center justify-content-between mt-1 px-1">
                        <label class="small text-muted" style="font-size:0.7rem;">Sort #</label>
                        <input type="number" name="sort_order[<?= $img['id'] ?>]" value="<?= (int)$img['sort_order'] ?>" class="form-control form-control-sm text-center py-0" style="width:55px; font-size:0.75rem;">
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="d-flex justify-content-end">
            <button type="submit" class="btn btn-sm btn-outline-dark" style="border-color:var(--border-beige);">
                <i class="bi bi-arrow-down-up me-1"></i> Save Image Sequence
            </button>
        </div>
    </form>

    <!-- Hidden Sub-forms for Set Primary and Delete -->
    <?php foreach ($images as $img): ?>
        <form id="setPrimaryForm<?= $img['id'] ?>" method="POST" action="<?= ADMIN_URL ?>/product-edit.php?id=<?= $id ?>" style="display:none;">
            <?= csrf_field() ?>
            <input type="hidden" name="image_action" value="set_primary">
            <input type="hidden" name="image_id" value="<?= $img['id'] ?>">
        </form>

        <form id="deleteImgForm<?= $img['id'] ?>" method="POST" action="<?= ADMIN_URL ?>/product-edit.php?id=<?= $id ?>" style="display:none;">
            <?= csrf_field() ?>
            <input type="hidden" name="image_action" value="delete_image">
            <input type="hidden" name="image_id" value="<?= $img['id'] ?>">
        </form>
    <?php endforeach; ?>
</div>

<!-- ======================================================================
     MAIN PRODUCT SPECIFICATIONS & FIELDS FORM (Section 5)
     ====================================================================== -->
<form method="POST" action="<?= ADMIN_URL ?>/product-edit.php?id=<?= $id ?>">
    <?= csrf_field() ?>

    <div class="row g-4">
        
        <!-- Left: Details, Description, Pricing, Specs -->
        <div class="col-lg-8">
            
            <!-- Basic Details -->
            <div class="card border-0 shadow-sm p-4 mb-4" style="border:1px solid var(--border-beige) !important; background:#fff;">
                <h5 class="font-serif fw-bold mb-3 pb-2 border-bottom" style="color:var(--caramel-dark);">
                    1. Timepiece Details
                </h5>

                <div class="row g-3">
                    <div class="col-md-8">
                        <label class="form-label small fw-bold text-uppercase">Timepiece Name <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control" value="<?= e($product['name']) ?>" required>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label small fw-bold text-uppercase">SKU Reference <span class="text-danger">*</span></label>
                        <input type="text" name="sku" class="form-control font-monospace" value="<?= e($product['sku']) ?>" required>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label small fw-bold text-uppercase">Master Category <span class="text-danger">*</span></label>
                        <select name="category_id" class="form-select" required>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?= $cat['id'] ?>" <?= ((int)$product['category_id'] === (int)$cat['id']) ? 'selected' : '' ?>>
                                    <?= e($cat['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label small fw-bold text-uppercase">Brand</label>
                        <input type="text" name="brand" class="form-control" value="<?= e($product['brand']) ?>" required>
                    </div>

                    <div class="col-12">
                        <label class="form-label small fw-bold text-uppercase">Short Description <span class="text-danger">*</span></label>
                        <textarea name="short_description" class="form-control" rows="2" required><?= e($product['short_description']) ?></textarea>
                    </div>

                    <div class="col-12">
                        <label class="form-label small fw-bold text-uppercase">Full Craftsmanship Description <span class="text-danger">*</span></label>
                        <textarea name="full_description" class="form-control" rows="6" required><?= e($product['full_description']) ?></textarea>
                    </div>
                </div>
            </div>

            <!-- Pricing & Inventory -->
            <div class="card border-0 shadow-sm p-4 mb-4" style="border:1px solid var(--border-beige) !important; background:#fff;">
                <h5 class="font-serif fw-bold mb-3 pb-2 border-bottom" style="color:var(--caramel-dark);">
                    2. Pricing &amp; Stock Levels
                </h5>

                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label small fw-bold text-uppercase">Regular Price (PKR) <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text bg-light border-beige">Rs.</span>
                            <input type="number" step="0.01" name="regular_price" class="form-control" value="<?= e($product['regular_price']) ?>" required>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label small fw-bold text-uppercase">Discounted Sale Price (PKR)</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light border-beige">Rs.</span>
                            <input type="number" step="0.01" name="discount_price" class="form-control" value="<?= e($product['discount_price']) ?>">
                        </div>
                        <?php if ($product['discount_percentage'] > 0): ?>
                            <div class="form-text small text-success fw-bold">Active discount: -<?= (int)$product['discount_percentage'] ?>% OFF</div>
                        <?php endif; ?>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label small fw-bold text-uppercase">Stock Quantity <span class="text-danger">*</span></label>
                        <input type="number" name="stock_quantity" class="form-control" min="0" value="<?= (int)$product['stock_quantity'] ?>" required>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label small fw-bold text-uppercase">Available Colors</label>
                        <input type="text" name="colors" class="form-control" value="<?= e($product['colors']) ?>" placeholder="e.g. Steel, Black, Gold">
                    </div>

                    <div class="col-md-4">
                        <label class="form-label small fw-bold text-uppercase">Available Sizes</label>
                        <input type="text" name="sizes" class="form-control" value="<?= e($product['sizes']) ?>" placeholder="e.g. 41mm, 43mm">
                    </div>
                </div>
            </div>

            <!-- Horological Specifications -->
            <div class="card border-0 shadow-sm p-4 mb-4" style="border:1px solid var(--border-beige) !important; background:#fff;">
                <div class="d-flex justify-content-between align-items-center mb-3 pb-2 border-bottom">
                    <h5 class="font-serif fw-bold mb-0" style="color:var(--caramel-dark);">
                        3. Horological Specifications
                    </h5>
                    <button type="button" class="btn btn-sm btn-outline-dark" id="addSpecRowBtn">
                        <i class="bi bi-plus-circle me-1"></i> Add Spec Row
                    </button>
                </div>

                <div id="specRowsContainer">
                    <?php if (!empty($specs)): ?>
                        <?php foreach ($specs as $s): ?>
                            <div class="row g-2 mb-2 spec-row">
                                <div class="col-5">
                                    <input type="text" name="spec_key[]" class="form-control form-control-sm" value="<?= e($s['key']) ?>">
                                </div>
                                <div class="col-6">
                                    <input type="text" name="spec_val[]" class="form-control form-control-sm" value="<?= e($s['value']) ?>">
                                </div>
                                <div class="col-1 text-center">
                                    <button type="button" class="btn btn-sm btn-outline-danger p-1 remove-spec-btn"><i class="bi bi-trash"></i></button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="row g-2 mb-2 spec-row">
                            <div class="col-5">
                                <input type="text" name="spec_key[]" class="form-control form-control-sm" placeholder="Caliber / Bezel / Crystal">
                            </div>
                            <div class="col-6">
                                <input type="text" name="spec_val[]" class="form-control form-control-sm" placeholder="Value">
                            </div>
                            <div class="col-1 text-center">
                                <button type="button" class="btn btn-sm btn-outline-danger p-1 remove-spec-btn"><i class="bi bi-trash"></i></button>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

        </div>

        <!-- Right: Status, Curations, Tags, Submit -->
        <div class="col-lg-4">
            
            <div class="card border-0 shadow-sm p-4 mb-4" style="border:1px solid var(--border-beige) !important; background:#fff;">
                <h5 class="font-serif fw-bold mb-3 pb-2 border-bottom" style="color:var(--caramel-dark);">
                    4. Publishing &amp; Curations
                </h5>

                <div class="mb-3">
                    <label class="form-label small fw-bold text-uppercase">Status</label>
                    <select name="status" class="form-select">
                        <option value="active" <?= $product['status'] === 'active' ? 'selected' : '' ?>>Active (Visible on Storefront)</option>
                        <option value="draft" <?= $product['status'] === 'draft' ? 'selected' : '' ?>>Draft (Hidden)</option>
                        <option value="inactive" <?= $product['status'] === 'inactive' ? 'selected' : '' ?>>Inactive (Archived)</option>
                    </select>
                </div>

                <div class="form-check form-switch mb-2">
                    <input class="form-check-input" type="checkbox" name="is_featured" value="1" id="isFeaturedSwitch" <?= $product['is_featured'] ? 'checked' : '' ?>>
                    <label class="form-check-label small fw-semibold" for="isFeaturedSwitch">Featured Highlight</label>
                </div>

                <div class="form-check form-switch mb-2">
                    <input class="form-check-input" type="checkbox" name="is_new_arrival" value="1" id="isNewSwitch" <?= $product['is_new_arrival'] ? 'checked' : '' ?>>
                    <label class="form-check-label small fw-semibold" for="isNewSwitch">New Arrival</label>
                </div>

                <div class="form-check form-switch mb-3">
                    <input class="form-check-input" type="checkbox" name="is_best_seller" value="1" id="isBestSwitch" <?= $product['is_best_seller'] ? 'checked' : '' ?>>
                    <label class="form-check-label small fw-semibold" for="isBestSwitch">Best Seller</label>
                </div>

                <div class="mb-3">
                    <label class="form-label small fw-bold text-uppercase">Search Tags (Comma-separated)</label>
                    <input type="text" name="tags" class="form-control form-control-sm" value="<?= e(implode(', ', $plainTags)) ?>" placeholder="Chronograph, Automatic, Swiss">
                </div>

                <div class="mb-3">
                    <label class="form-label small fw-bold text-uppercase">Hashtags (Space-separated)</label>
                    <input type="text" name="hashtags" class="form-control form-control-sm" value="<?= e(implode(' ', $hashTags)) ?>" placeholder="#Valenor #Chronograph">
                </div>

                <div class="p-3 bg-light rounded small text-muted mb-3">
                    <div>Views Count: <strong><?= (int)$product['views_count'] ?></strong></div>
                    <div>Created: <strong><?= format_date($product['created_at'], 'M d, Y') ?></strong></div>
                    <div>Last Updated: <strong><?= format_date($product['updated_at'], 'M d, Y') ?></strong></div>
                </div>

                <div class="d-grid gap-2">
                    <button type="submit" class="btn btn-caramel py-2 fw-bold text-uppercase">
                        <i class="bi bi-save me-1"></i> Save Changes
                    </button>
                    <a href="<?= ADMIN_URL ?>/products.php" class="btn btn-outline-secondary btn-sm">
                        Cancel
                    </a>
                </div>
            </div>

        </div>

    </div>
</form>

<script>
// Dynamic spec row management
document.getElementById('addSpecRowBtn')?.addEventListener('click', () => {
    const container = document.getElementById('specRowsContainer');
    const row = document.createElement('div');
    row.className = 'row g-2 mb-2 spec-row';
    row.innerHTML = `
        <div class="col-5">
            <input type="text" name="spec_key[]" class="form-control form-control-sm" placeholder="Feature">
        </div>
        <div class="col-6">
            <input type="text" name="spec_val[]" class="form-control form-control-sm" placeholder="Value">
        </div>
        <div class="col-1 text-center">
            <button type="button" class="btn btn-sm btn-outline-danger p-1 remove-spec-btn"><i class="bi bi-trash"></i></button>
        </div>
    `;
    container.appendChild(row);
});

document.addEventListener('click', (e) => {
    if (e.target.closest('.remove-spec-btn')) {
        const row = e.target.closest('.spec-row');
        if (row) row.remove();
    }
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
