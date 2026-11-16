<?php
// =============================================================================
// VALENOR HOROLOGY — ADMIN ADD NEW PRODUCT (Section 4 & 5)
// Supports all 20 specification fields + 1 to 10 image uploads with validation
// =============================================================================

require_once __DIR__ . '/../config/config.php';
require_admin();

$db = getDB();

$adminTitle      = 'Add New Timepiece';
$adminPageHeader = 'Catalogue Entry Concierge';
$adminPageSub    = 'Register a new luxury timepiece with complete horological specifications and 1–10 high-resolution images.';

$errors = [];
$success = '';

// Fetch active categories
$categories = $db->query("SELECT id, name FROM categories WHERE status = 'active' ORDER BY sort_order ASC")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) {
        $errors[] = 'Security token expired. Please refresh and resubmit.';
    } else {
        // Collect & sanitize fields
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

        // Validation
        if (empty($name)) {
            $errors[] = 'Product name is required.';
        }
        if (empty($sku)) {
            $errors[] = 'Unique product SKU is required.';
        } else {
            // Check SKU uniqueness
            $skuCheck = $db->prepare("SELECT id FROM products WHERE sku = ? LIMIT 1");
            $skuCheck->execute([$sku]);
            if ($skuCheck->fetch()) {
                $errors[] = "SKU '{$sku}' already exists in the catalogue. Please use a unique reference.";
            }
        }
        if ($categoryId <= 0) {
            $errors[] = 'Please select a valid master category.';
        }
        if ($regPrice <= 0) {
            $errors[] = 'Regular price must be greater than zero.';
        }
        if ($discPrice !== null && $discPrice >= $regPrice) {
            $errors[] = 'Discounted price must be less than the regular price.';
        }
        if (empty($shortDesc)) {
            $errors[] = 'Short description is required.';
        }
        if (empty($fullDesc)) {
            $errors[] = 'Full description is required.';
        }

        // Validate specifications
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

        // Image validation (Minimum 1, Maximum 10 images)
        $files = $_FILES['images'] ?? null;
        $validUploads = [];

        if (!$files || empty($files['name'][0])) {
            $errors[] = 'At least ONE product image is mandatory (minimum 1, maximum 10).';
        } else {
            $fileCount = count($files['name']);
            if ($fileCount > 10) {
                $errors[] = 'Maximum of 10 images permitted per timepiece (you selected ' . $fileCount . ').';
            } else {
                for ($i = 0; $i < $fileCount; $i++) {
                    if ($files['error'][$i] === UPLOAD_ERR_NO_FILE) continue;

                    $singleFile = [
                        'name'     => $files['name'][$i],
                        'type'     => $files['type'][$i],
                        'tmp_name' => $files['tmp_name'][$i],
                        'error'    => $files['error'][$i],
                        'size'     => $files['size'][$i],
                    ];

                    $uploadRes = upload_image($singleFile, PRODUCT_UPLOADS_PATH, 'watch');
                    if (!$uploadRes['success']) {
                        $errors[] = "Image #" . ($i + 1) . ": " . $uploadRes['error'];
                    } else {
                        $validUploads[] = $uploadRes['rel_path'];
                    }
                }

                if (empty($errors) && empty($validUploads)) {
                    $errors[] = 'At least ONE valid product image must be uploaded.';
                }
            }
        }

        // If no errors, insert into MySQL database
        if (empty($errors)) {
            try {
                $db->beginTransaction();

                $slug = slugify($name . '-' . $sku);

                // Insert into products
                $stmt = $db->prepare("
                    INSERT INTO products 
                    (name, slug, sku, category_id, brand, short_description, full_description, regular_price, discount_price, specifications, colors, sizes, stock_quantity, status, is_featured, is_new_arrival, is_best_seller, created_at, updated_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
                ");

                $stmt->execute([
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
                    $isBest
                ]);

                $newProductId = (int)$db->lastInsertId();

                // Insert product images
                $primaryIndex = (int)($_POST['primary_image_index'] ?? 0);
                if ($primaryIndex >= count($validUploads)) $primaryIndex = 0;

                $imgStmt = $db->prepare("
                    INSERT INTO product_images (product_id, image_path, is_primary, sort_order, created_at)
                    VALUES (?, ?, ?, ?, NOW())
                ");

                foreach ($validUploads as $idx => $relPath) {
                    $isPri = ($idx === $primaryIndex) ? 1 : 0;
                    $imgStmt->execute([$newProductId, $relPath, $isPri, $idx + 1]);
                }

                // Insert tags
                if (!empty($tagsInput)) {
                    $tagParts = array_map('trim', explode(',', $tagsInput));
                    $tStmt = $db->prepare("INSERT INTO product_tags (product_id, tag_name, tag_type) VALUES (?, ?, 'tag')");
                    foreach ($tagParts as $t) {
                        if (!empty($t)) $tStmt->execute([$newProductId, $t]);
                    }
                }

                // Insert hashtags
                if (!empty($hashInput)) {
                    $hashParts = preg_split('/[\s,]+/', $hashInput);
                    $hStmt = $db->prepare("INSERT INTO product_tags (product_id, tag_name, tag_type) VALUES (?, ?, 'hashtag')");
                    foreach ($hashParts as $h) {
                        $hClean = ltrim($h, '#');
                        if (!empty($hClean)) $hStmt->execute([$newProductId, '#' . $hClean]);
                    }
                }

                // Insert default variants if colors/sizes given
                if (!empty($colors)) {
                    $colorList = array_map('trim', explode(',', $colors));
                    $varStmt = $db->prepare("
                        INSERT INTO product_variants (product_id, sku, color, size, additional_price, stock_quantity)
                        VALUES (?, ?, ?, ?, 0.00, ?)
                    ");
                    foreach ($colorList as $cIdx => $cName) {
                        $varSku = $sku . '-' . strtoupper(substr(preg_replace('/[^a-zA-Z0-9]/', '', $cName), 0, 3));
                        $varStmt->execute([$newProductId, $varSku, $cName, $sizes ?: null, max(1, (int)($stock / count($colorList)))]);
                    }
                }

                $db->commit();

                set_flash('success', "Timepiece <strong>" . e($name) . "</strong> has been successfully added to the catalogue with " . count($validUploads) . " images.");
                header('Location: ' . ADMIN_URL . '/products.php');
                exit;

            } catch (Exception $e) {
                if ($db->inTransaction()) {
                    $db->rollBack();
                }
                $errors[] = 'Database transaction failed: ' . $e->getMessage();
            }
        }
    }
}

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
                    <li class="breadcrumb-item active">Add Timepiece</li>
                </ol>
            </nav>
            <h4 class="font-serif fw-bold mb-0" style="color:var(--text-charcoal);">Register New Timepiece</h4>
        </div>
        <a href="<?= ADMIN_URL ?>/products.php" class="btn btn-sm btn-outline-dark" style="border-color:var(--border-beige);">
            <i class="bi bi-arrow-left me-1"></i> Back to Inventory
        </a>
    </div>
</div>

<?php if (!empty($errors)): ?>
    <div class="alert alert-danger mb-4 shadow-sm" role="alert">
        <h6 class="fw-bold mb-2"><i class="bi bi-exclamation-octagon-fill me-1"></i> Please resolve the following errors:</h6>
        <ul class="mb-0 small ps-3">
            <?php foreach ($errors as $err): ?>
                <li><?= e($err) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<form method="POST" action="<?= ADMIN_URL ?>/product-add.php" enctype="multipart/form-data">
    <?= csrf_field() ?>

    <div class="row g-4">
        
        <!-- Left: Primary Details -->
        <div class="col-lg-8">
            
            <!-- Basic Information Card -->
            <div class="card border-0 shadow-sm p-4 mb-4" style="border:1px solid var(--border-beige) !important; background:#fff;">
                <h5 class="font-serif fw-bold mb-3 pb-2 border-bottom" style="color:var(--caramel-dark);">
                    1. Core Watch Identification
                </h5>

                <div class="row g-3">
                    <div class="col-md-8">
                        <label class="form-label small fw-bold text-uppercase">Timepiece Name <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control" placeholder="e.g. VALENOR Carrera Chronograph Edition" value="<?= e($_POST['name'] ?? '') ?>" required>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label small fw-bold text-uppercase">SKU Reference <span class="text-danger">*</span></label>
                        <input type="text" name="sku" class="form-control font-monospace" placeholder="e.g. VAL-CHR-003" value="<?= e($_POST['sku'] ?? '') ?>" required>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label small fw-bold text-uppercase">Master Category <span class="text-danger">*</span></label>
                        <select name="category_id" class="form-select" required>
                            <option value="">Select Category...</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?= $cat['id'] ?>" <?= ((int)($_POST['category_id'] ?? 0) === (int)$cat['id']) ? 'selected' : '' ?>>
                                    <?= e($cat['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label small fw-bold text-uppercase">Brand</label>
                        <input type="text" name="brand" class="form-control" value="<?= e($_POST['brand'] ?? 'VALENOR') ?>" required>
                    </div>

                    <div class="col-12">
                        <label class="form-label small fw-bold text-uppercase">Short Description <span class="text-danger">*</span></label>
                        <textarea name="short_description" class="form-control" rows="2" placeholder="Concise overview for catalog cards (e.g. Caliber 16 automatic movement with ceramic tachymeter bezel)..." required><?= e($_POST['short_description'] ?? '') ?></textarea>
                    </div>

                    <div class="col-12">
                        <label class="form-label small fw-bold text-uppercase">Full Craftsmanship Description <span class="text-danger">*</span></label>
                        <textarea name="full_description" class="form-control" rows="5" placeholder="Detailed horological provenance, movement finishing, power reserve, and water resistance testing..." required><?= e($_POST['full_description'] ?? '') ?></textarea>
                    </div>
                </div>
            </div>

            <!-- Pricing & Inventory Card -->
            <div class="card border-0 shadow-sm p-4 mb-4" style="border:1px solid var(--border-beige) !important; background:#fff;">
                <h5 class="font-serif fw-bold mb-3 pb-2 border-bottom" style="color:var(--caramel-dark);">
                    2. Pricing &amp; Inventory Quantities
                </h5>

                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label small fw-bold text-uppercase">Regular Price (PKR) <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text bg-light border-beige">Rs.</span>
                            <input type="number" step="0.01" name="regular_price" class="form-control" placeholder="48500.00" value="<?= e($_POST['regular_price'] ?? '') ?>" required>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label small fw-bold text-uppercase">Discounted Sale Price (Optional)</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light border-beige">Rs.</span>
                            <input type="number" step="0.01" name="discount_price" class="form-control" placeholder="38800.00" value="<?= e($_POST['discount_price'] ?? '') ?>">
                        </div>
                        <div class="form-text small text-muted">Discount percentage will be automatically calculated.</div>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label small fw-bold text-uppercase">Available Stock <span class="text-danger">*</span></label>
                        <input type="number" name="stock_quantity" class="form-control" min="0" value="<?= e($_POST['stock_quantity'] ?? '15') ?>" required>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label small fw-bold text-uppercase">Colorways (Comma-separated)</label>
                        <input type="text" name="colors" class="form-control" placeholder="e.g. Obsidian Black, Deep Sunray Blue" value="<?= e($_POST['colors'] ?? '') ?>">
                    </div>

                    <div class="col-md-4">
                        <label class="form-label small fw-bold text-uppercase">Sizes (Case Diameters)</label>
                        <input type="text" name="sizes" class="form-control" placeholder="e.g. 41mm, 43mm" value="<?= e($_POST['sizes'] ?? '42mm') ?>">
                    </div>
                </div>
            </div>

            <!-- Horological Specifications Card -->
            <div class="card border-0 shadow-sm p-4 mb-4" style="border:1px solid var(--border-beige) !important; background:#fff;">
                <div class="d-flex justify-content-between align-items-center mb-3 pb-2 border-bottom">
                    <h5 class="font-serif fw-bold mb-0" style="color:var(--caramel-dark);">
                        3. Horological Specifications (Table Data)
                    </h5>
                    <button type="button" class="btn btn-sm btn-outline-dark" id="addSpecRowBtn">
                        <i class="bi bi-plus-circle me-1"></i> Add Spec Row
                    </button>
                </div>

                <div id="specRowsContainer">
                    <!-- Default Standard Horological Specs -->
                    <div class="row g-2 mb-2 spec-row">
                        <div class="col-5">
                            <input type="text" name="spec_key[]" class="form-control form-control-sm" value="Movement Caliber" placeholder="Feature">
                        </div>
                        <div class="col-6">
                            <input type="text" name="spec_val[]" class="form-control form-control-sm" value="VAL-8902 Automatic Chronometer" placeholder="Value">
                        </div>
                        <div class="col-1 text-center">
                            <button type="button" class="btn btn-sm btn-outline-danger p-1 remove-spec-btn"><i class="bi bi-trash"></i></button>
                        </div>
                    </div>

                    <div class="row g-2 mb-2 spec-row">
                        <div class="col-5">
                            <input type="text" name="spec_key[]" class="form-control form-control-sm" value="Water Resistance" placeholder="Feature">
                        </div>
                        <div class="col-6">
                            <input type="text" name="spec_val[]" class="form-control form-control-sm" value="100 Meters / 10 ATM" placeholder="Value">
                        </div>
                        <div class="col-1 text-center">
                            <button type="button" class="btn btn-sm btn-outline-danger p-1 remove-spec-btn"><i class="bi bi-trash"></i></button>
                        </div>
                    </div>

                    <div class="row g-2 mb-2 spec-row">
                        <div class="col-5">
                            <input type="text" name="spec_key[]" class="form-control form-control-sm" value="Crystal" placeholder="Feature">
                        </div>
                        <div class="col-6">
                            <input type="text" name="spec_val[]" class="form-control form-control-sm" value="Domed Sapphire Crystal with Anti-Reflective Coating" placeholder="Value">
                        </div>
                        <div class="col-1 text-center">
                            <button type="button" class="btn btn-sm btn-outline-danger p-1 remove-spec-btn"><i class="bi bi-trash"></i></button>
                        </div>
                    </div>
                </div>
            </div>

        </div>

        <!-- Right: Image Management & Meta -->
        <div class="col-lg-4">
            
            <!-- Image Upload Card (Section 4: 1 to 10 images) -->
            <div class="card border-0 shadow-sm p-4 mb-4" style="border:1px solid var(--border-beige) !important; background:#fff;">
                <h5 class="font-serif fw-bold mb-3 pb-2 border-bottom" style="color:var(--caramel-dark);">
                    4. Image Gallery (1 to 10 Images) <span class="text-danger">*</span>
                </h5>

                <div class="mb-3">
                    <label class="form-label small fw-bold text-uppercase">Select Product Photos</label>
                    <input type="file" name="images[]" id="productImagesInput" class="form-control" multiple accept="image/jpeg,image/png,image/webp" required>
                    <div class="form-text small text-muted">
                        Select <strong>1 to 10 images</strong>. Supported: JPG, PNG, WebP (Max 5 MB each).
                    </div>
                </div>

                <!-- Live Client-Side Image Previews -->
                <div id="imagePreviewStrip" class="row g-2 mb-3"></div>

                <div class="mb-3">
                    <label class="form-label small fw-bold text-uppercase">Select Primary Featured Image</label>
                    <select name="primary_image_index" id="primaryImgSelect" class="form-select form-select-sm">
                        <option value="0">Image 1 (First uploaded file)</option>
                    </select>
                </div>
            </div>

            <!-- Publishing & Status Card -->
            <div class="card border-0 shadow-sm p-4 mb-4" style="border:1px solid var(--border-beige) !important; background:#fff;">
                <h5 class="font-serif fw-bold mb-3 pb-2 border-bottom" style="color:var(--caramel-dark);">
                    5. Status &amp; Highlights
                </h5>

                <div class="mb-3">
                    <label class="form-label small fw-bold text-uppercase">Catalogue Status</label>
                    <select name="status" class="form-select">
                        <option value="active" <?= (($_POST['status'] ?? 'active') === 'active') ? 'selected' : '' ?>>Active (Visible on Storefront)</option>
                        <option value="draft" <?= (($_POST['status'] ?? '') === 'draft') ? 'selected' : '' ?>>Draft (Hidden)</option>
                        <option value="inactive" <?= (($_POST['status'] ?? '') === 'inactive') ? 'selected' : '' ?>>Inactive (Archived)</option>
                    </select>
                </div>

                <div class="form-check form-switch mb-2">
                    <input class="form-check-input" type="checkbox" name="is_featured" value="1" id="isFeaturedSwitch" <?= !empty($_POST['is_featured']) ? 'checked' : '' ?>>
                    <label class="form-check-label small fw-semibold" for="isFeaturedSwitch">Featured Highlight</label>
                </div>

                <div class="form-check form-switch mb-2">
                    <input class="form-check-input" type="checkbox" name="is_new_arrival" value="1" id="isNewSwitch" <?= (!isset($_POST['name']) || !empty($_POST['is_new_arrival'])) ? 'checked' : '' ?>>
                    <label class="form-check-label small fw-semibold" for="isNewSwitch">New Arrival</label>
                </div>

                <div class="form-check form-switch mb-3">
                    <input class="form-check-input" type="checkbox" name="is_best_seller" value="1" id="isBestSwitch" <?= !empty($_POST['is_best_seller']) ? 'checked' : '' ?>>
                    <label class="form-check-label small fw-semibold" for="isBestSwitch">Best Seller</label>
                </div>

                <div class="mb-3">
                    <label class="form-label small fw-bold text-uppercase">Search Tags (Comma-separated)</label>
                    <input type="text" name="tags" class="form-control form-control-sm" placeholder="Chronograph, Automatic, Swiss" value="<?= e($_POST['tags'] ?? '') ?>">
                </div>

                <div class="mb-3">
                    <label class="form-label small fw-bold text-uppercase">Hashtags (Space-separated)</label>
                    <input type="text" name="hashtags" class="form-control form-control-sm" placeholder="#LuxuryWatch #ValenorHorology" value="<?= e($_POST['hashtags'] ?? '') ?>">
                </div>

                <hr class="my-3" style="border-color:var(--border-beige);">

                <div class="d-grid gap-2">
                    <button type="submit" class="btn btn-caramel py-2 fw-bold text-uppercase">
                        <i class="bi bi-cloud-arrow-up me-1"></i> Publish Timepiece
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
// Dynamic Spec Rows
document.getElementById('addSpecRowBtn')?.addEventListener('click', () => {
    const container = document.getElementById('specRowsContainer');
    const row = document.createElement('div');
    row.className = 'row g-2 mb-2 spec-row';
    row.innerHTML = `
        <div class="col-5">
            <input type="text" name="spec_key[]" class="form-control form-control-sm" placeholder="Feature (e.g. Bezel)">
        </div>
        <div class="col-6">
            <input type="text" name="spec_val[]" class="form-control form-control-sm" placeholder="Value (e.g. Ceramic 120-Click)">
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

// Live Image Previews & Primary Selector
const imgInput = document.getElementById('productImagesInput');
const previewStrip = document.getElementById('imagePreviewStrip');
const primarySelect = document.getElementById('primaryImgSelect');

if (imgInput) {
    imgInput.addEventListener('change', function () {
        previewStrip.innerHTML = '';
        primarySelect.innerHTML = '';
        
        const files = Array.from(this.files).slice(0, 10);
        if (files.length === 0) return;

        files.forEach((file, idx) => {
            const opt = document.createElement('option');
            opt.value = idx;
            opt.textContent = `Image ${idx + 1} (${file.name})`;
            primarySelect.appendChild(opt);

            const reader = new FileReader();
            reader.onload = (e) => {
                const col = document.createElement('div');
                col.className = 'col-3';
                col.innerHTML = `
                    <div class="border rounded overflow-hidden position-relative" style="aspect-ratio:1;">
                        <img src="${e.target.result}" style="width:100%; height:100%; object-fit:cover;">
                        <span class="badge bg-dark position-absolute bottom-0 start-0 m-1" style="font-size:0.65rem;">#${idx + 1}</span>
                    </div>
                `;
                previewStrip.appendChild(col);
            };
            reader.readAsDataURL(file);
        });
    });
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
