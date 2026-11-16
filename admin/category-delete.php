<?php
// =============================================================================
// VALENOR HOROLOGY — ADMIN CATEGORY DELETE HANDLER (Section 3.B)
// =============================================================================

require_once __DIR__ . '/../config/config.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    set_flash('error', 'Invalid deletion request method.');
    header('Location: ' . ADMIN_URL . '/categories.php');
    exit;
}

if (!verify_csrf()) {
    set_flash('error', 'Security token mismatch. Action aborted.');
    header('Location: ' . ADMIN_URL . '/categories.php');
    exit;
}

$id = (int)($_POST['id'] ?? 0);
if ($id <= 0) {
    set_flash('error', 'Invalid category identifier.');
    header('Location: ' . ADMIN_URL . '/categories.php');
    exit;
}

$db = getDB();

$stmt = $db->prepare("SELECT id, name FROM categories WHERE id = ? LIMIT 1");
$stmt->execute([$id]);
$category = $stmt->fetch();

if (!$category) {
    set_flash('error', 'Category not found.');
    header('Location: ' . ADMIN_URL . '/categories.php');
    exit;
}

// Check if products are assigned
$countStmt = $db->prepare("SELECT COUNT(*) FROM products WHERE category_id = ?");
$countStmt->execute([$id]);
$assignedCount = (int)$countStmt->fetchColumn();

if ($assignedCount > 0) {
    set_flash('error', "Cannot delete category '{$category['name']}' because it currently contains {$assignedCount} timepieces. Please reassign or delete the products first.");
    header('Location: ' . ADMIN_URL . '/categories.php');
    exit;
}

try {
    $db->prepare("DELETE FROM categories WHERE id = ?")->execute([$id]);
    set_flash('success', "Category '{$category['name']}' deleted successfully.");
} catch (Exception $e) {
    set_flash('error', 'Failed to delete category: ' . $e->getMessage());
}

header('Location: ' . ADMIN_URL . '/categories.php');
exit;

