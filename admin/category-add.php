<?php
// =============================================================================
// VALENOR HOROLOGY — ADMIN ADD CATEGORY (Section 3.B)
// =============================================================================

require_once __DIR__ . '/../config/config.php';
require_admin();

$db = getDB();

$adminTitle      = 'Add Category';
$adminPageHeader = 'New Master Category';
$adminPageSub    = 'Create a new master collection category with custom banner artwork and description.';

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
        $slugCheck = $db->prepare("SELECT id FROM categories WHERE slug = ? LIMIT 1");
        $slugCheck->execute([$slug]);
        if ($slugCheck->fetch()) {
            $errors[] = "A category with slug '{$slug}' already exists. Please use a distinct name.";
        }

        if (empty($description)) {
            $errors[] = 'Category description is required.';
        }

        // Handle Image Upload
        $imagePath = 'uploads/products/watch-mens-chronograph.jpg'; // default
        if (!empty($_FILES['image']['name'])) {
            $uploadRes = upload_image($_FILES['image'], PRODUCT_UPLOADS_PATH, 'cat');
            if (!$uploadRes['success']) {
                $errors[] = 'Artwork upload error: ' . $uploadRes['error'];
            } else {
                $imagePath = $uploadRes['rel_path'];
            }
        }

        if (empty($errors)) {
            try {
                $stmt = $db->prepare("
                    INSERT INTO categories (name, slug, description, image_path, sort_order, status, created_at, updated_at)
                    VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())
                ");
                $stmt->execute([$name, $slug, $description, $imagePath, $sortOrder, $status]);

                set_flash('success', "Category '{$name}' created successfully.");
                header('Location: ' . ADMIN_URL . '/categories.php');
                exit;
            } catch (Exception $e) {
                $errors[] = 'Database insertion failed: ' . $e->getMessage();
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
                    <li class="breadcrumb-item"><a href="<?= ADMIN_URL ?>/categories.php">Categories</a></li>
                    <li class="breadcrumb-item active">Add Category</li>
                </ol>
            </nav>
            <h4 class="font-serif fw-bold mb-0" style="color:var(--text-charcoal);">Create Master Category</h4>
        </div>
        <a href="<?= ADMIN_URL ?>/categories.php" class="btn btn-sm btn-outline-dark" style="border-color:var(--border-beige);">
            <i class="bi bi-arrow-left me-1"></i> Back to Categories
        </a>
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
            <form method="POST" action="<?= ADMIN_URL ?>/category-add.php" enctype="multipart/form-data">
                <?= csrf_field() ?>

                <div class="row g-3">
                    <div class="col-md-7">
                        <label class="form-label small fw-bold text-uppercase">Category Name <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control" placeholder="e.g. Tourbillon Masterpieces" value="<?= e($_POST['name'] ?? '') ?>" required autofocus>
                    </div>

                    <div class="col-md-5">
                        <label class="form-label small fw-bold text-uppercase">URL Slug (Optional)</label>
                        <input type="text" name="slug" class="form-control font-monospace" placeholder="tourbillon-masterpieces" value="<?= e($_POST['slug'] ?? '') ?>">
                        <div class="form-text small text-muted">Leave blank to auto-generate from name.</div>
                    </div>

                    <div class="col-12">
                        <label class="form-label small fw-bold text-uppercase">Description <span class="text-danger">*</span></label>
                        <textarea name="description" class="form-control" rows="3" placeholder="Brief summary of this horological collection..." required><?= e($_POST['description'] ?? '') ?></textarea>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label small fw-bold text-uppercase">Category Banner Artwork</label>
                        <input type="file" name="image" class="form-control" accept="image/jpeg,image/png,image/webp">
                        <div class="form-text small text-muted">Supports JPG, PNG, WebP (Max 5 MB).</div>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label small fw-bold text-uppercase">Sort Order</label>
                        <input type="number" name="sort_order" class="form-control text-center" value="<?= (int)($_POST['sort_order'] ?? 6) ?>" min="1">
                    </div>

                    <div class="col-md-3">
                        <label class="form-label small fw-bold text-uppercase">Status</label>
                        <select name="status" class="form-select">
                            <option value="active" <?= (($_POST['status'] ?? 'active') === 'active') ? 'selected' : '' ?>>Active</option>
                            <option value="inactive" <?= (($_POST['status'] ?? '') === 'inactive') ? 'selected' : '' ?>>Inactive</option>
                        </select>
                    </div>
                </div>

                <hr class="my-4" style="border-color:var(--border-beige);">

                <div class="d-flex justify-content-end gap-2">
                    <a href="<?= ADMIN_URL ?>/categories.php" class="btn btn-outline-secondary">Cancel</a>
                    <button type="submit" class="btn btn-caramel px-4">Save Category</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
