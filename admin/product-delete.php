<?php
// =============================================================================
// VALENOR HOROLOGY — ADMIN PRODUCT DELETE HANDLER (Section 5)
// =============================================================================

require_once __DIR__ . '/../config/config.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    set_flash('error', 'Invalid request method for deletion.');
    header('Location: ' . ADMIN_URL . '/products.php');
    exit;
}

if (!verify_csrf()) {
    set_flash('error', 'Security token mismatch. Action aborted.');
    header('Location: ' . ADMIN_URL . '/products.php');
    exit;
}

$id = (int)($_POST['id'] ?? 0);
if ($id <= 0) {
    set_flash('error', 'Invalid product identifier.');
    header('Location: ' . ADMIN_URL . '/products.php');
    exit;
}

$db = getDB();

// Fetch product to confirm existence
$stmt = $db->prepare("SELECT id, name FROM products WHERE id = ? LIMIT 1");
$stmt->execute([$id]);
$product = $stmt->fetch();

if (!$product) {
    set_flash('error', 'Product not found.');
    header('Location: ' . ADMIN_URL . '/products.php');
    exit;
}

try {
    $db->beginTransaction();

    // Fetch images to potentially delete uploaded files
    $imgStmt = $db->prepare("SELECT image_path FROM product_images WHERE product_id = ?");
    $imgStmt->execute([$id]);
    $images = $imgStmt->fetchAll(PDO::FETCH_COLUMN);

    // Delete product (cascades to images, variants, tags)
    $delStmt = $db->prepare("DELETE FROM products WHERE id = ?");
    $delStmt->execute([$id]);

    $db->commit();

    // Safely remove custom uploaded images (don't delete core seed files)
    foreach ($images as $imgPath) {
        if (str_starts_with($imgPath, 'uploads/products/img_')) {
            $fullPath = ROOT_PATH . '/' . ltrim($imgPath, '/');
            if (is_file($fullPath)) {
                @unlink($fullPath);
            }
        }
    }

    set_flash('success', 'Timepiece "' . e($product['name']) . '" has been permanently deleted from the catalogue.');
} catch (Exception $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    set_flash('error', 'Failed to delete product: ' . $e->getMessage());
}

header('Location: ' . ADMIN_URL . '/products.php');
exit;
