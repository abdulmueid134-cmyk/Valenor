<?php
// =============================================================================
// VALENOR HOROLOGY — ADMIN EDIT CATEGORY (Section 3.B)
// =============================================================================

require_once __DIR__ . '/../config/config.php';
require_admin();

$db = getDB();

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    set_flash('error', 'Invalid category identifier.');
    header('Location: ' . ADMIN_URL . '/categories.php');
    exit;
}

$stmt = $db->prepare("SELECT * FROM categories WHERE id = ? LIMIT 1");
$stmt->execute([$id]);
$category = $stmt->fetch();

if (!$category) {
    set_flash('error', 'Category not found.');
    header('Location: ' . ADMIN_URL . '/categories.php');
    exit;
}

$adminTitle      = 'Edit ' . $category['name'];
$adminPageHeader = 'Edit Master Category';
$adminPageSub    = 'Update collection naming, banner artwork, sort sequence, and visibility.';

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) {
        $errors[] = 'Security token expired. Please refresh and try again.';
    } else {
        $name        = sanitize($_POST['name'] ?? '');
        $slug        = sanitize($_POST['slug'] ?? '');
        $description = sanitize($_POST['description'] ?? '');
        $sortOrder   = (int)($_POST['sort_order'] ?? 1);
        $status      = in_array($_POST['status'] ?? '', ['active', 'inactive']) ? $_POST['status'] : 'active';

        if (empty($name)) {
            $errors[] = 'Category name is required.';
        }

        if (empty($slug)) {
            $slug = slugify($name);
        } else {
            $slug = slugify($slug);
        }

        // Verify slug uniqueness
        $slugCheck = $db->prepare("SELECT id FROM categories WHERE slug = ? AND id != ? LIMIT 1");
        $slugCheck->execute([$slug, $id]);
        if ($slugCheck->fetch()) {
            $errors[] = "A category with slug '{$slug}' already exists. Please choose another slug.";
        }

        if (empty($description)) {
            $errors[] = 'Category description is required.';
        }

        // Handle Optional Image Replacement
        $imagePath = $category['image_path'];
        if (!empty($_FILES['image']['name'])) {
            $uploadRes = upload_image($_FILES['image'], PRODUCT_UPLOADS_PATH, 'cat');
            if (!$uploadRes['success']) {
                $errors[] = 'Artwork replacement error: ' . $uploadRes['error'];
            } else {
                $imagePath = $uploadRes['rel_path'];
            }
        }

        if (empty($errors)) {
            try {
                $upStmt = $db->prepare("
                    UPDATE categories SET 
                        name = ?,
                        slug = ?,
                        description = ?,
                        image_path = ?,
                        sort_order = ?,
                        status = ?,
                        updated_at = NOW()
                    WHERE id = ?
                ");
                $upStmt->execute([$name, $slug, $description, $imagePath, $sortOrder, $status, $id]);

                set_flash('success', "Category '{$name}' updated successfully.");
                header('Location: ' . ADMIN_URL . '/categories.php');
                exit;
            } catch (Exception $e) {
                $errors[] = 'Failed to update category: ' . $e->getMessage();
            }
        }
    }
}

// Reload category data
$stmt = $db->prepare("SELECT * FROM categories WHERE id = ? LIMIT 1");
$stmt->execute([$id]);
$category = $stmt->fetch();

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
                    <li class="breadcrumb-item"><a href="<?= ADMIN_URL ?>/categories.php">Categories</a></li>
                    <li class="breadcrumb-item active"><?= e($category['name']) ?></li>
                </ol>
            </nav>
            <h4 class="font-serif fw-bold mb-0" style="color:var(--text-charcoal);"><?= e($category['name']) ?></h4>
        </div>
        <div class="d-flex gap-2">
            <a href="<?= BASE_URL ?>/products.php?category=<?= urlencode($category['slug']) ?>" target="_blank" class="btn btn-sm btn-outline-secondary">
                <i class="bi bi-box-arrow-up-right me-1"></i> View Collection
            </a>
            <a href="<?= ADMIN_URL ?>/categories.php" class="btn btn-sm btn-outline-dark" style="border-color:var(--border-beige);">
                <i class="bi bi-arrow-left me-1"></i> Back to Categories
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

<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm p-4" style="border:1px solid var(--border-beige) !important; background:#fff;">
            <form method="POST" action="<?= ADMIN_URL ?>/category-edit.php?id=<?= $id ?>" enctype="multipart/form-data">
                <?= csrf_field() ?>

                <div class="row g-3">
                    <div class="col-md-7">
                        <label class="form-label small fw-bold text-uppercase">Category Name <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control" value="<?= e($category['name']) ?>" required>
                    </div>

                    <div class="col-md-5">
                        <label class="form-label small fw-bold text-uppercase">URL Slug <span class="text-danger">*</span></label>
                        <input type="text" name="slug" class="form-control font-monospace" value="<?= e($category['slug']) ?>" required>
                    </div>

                    <div class="col-12">
                        <label class="form-label small fw-bold text-uppercase">Description <span class="text-danger">*</span></label>
                        <textarea name="description" class="form-control" rows="3" required><?= e($category['description']) ?></textarea>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label small fw-bold text-uppercase">Current Banner Artwork</label>
                        <div class="d-flex align-items-center gap-3 p-2 bg-light rounded border mb-2">
                            <img src="<?= e(BASE_URL . '/' . ltrim($category['image_path'], '/')) ?>" alt="" style="width:56px; height:56px; object-fit:cover; border-radius:6px; border:1px solid var(--border-beige);">
                            <div class="small text-muted text-truncate"><?= e($category['image_path']) ?></div>
                        </div>
                        <label class="form-label small fw-bold text-uppercase">Replace Artwork (Optional)</label>
                        <input type="file" name="image" class="form-control" accept="image/jpeg,image/png,image/webp">
                    </div>

                    <div class="col-md-3">
                        <label class="form-label small fw-bold text-uppercase">Sort Order</label>
                        <input type="number" name="sort_order" class="form-control text-center" value="<?= (int)$category['sort_order'] ?>" min="1">
                    </div>

                    <div class="col-md-3">
                        <label class="form-label small fw-bold text-uppercase">Status</label>
                        <select name="status" class="form-select">
                            <option value="active" <?= $category['status'] === 'active' ? 'selected' : '' ?>>Active</option>
                            <option value="inactive" <?= $category['status'] === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                        </select>
                    </div>
                </div>

                <hr class="my-4" style="border-color:var(--border-beige);">

                <div class="d-flex justify-content-end gap-2">
                    <a href="<?= ADMIN_URL ?>/categories.php" class="btn btn-outline-secondary">Cancel</a>
                    <button type="submit" class="btn btn-caramel px-4">Update Category</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

