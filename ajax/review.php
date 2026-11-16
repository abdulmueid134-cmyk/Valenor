<?php
// =============================================================================
// VALENOR HOROLOGY — AJAX CUSTOMER REVIEW SUBMISSION HANDLER
// =============================================================================

require_once __DIR__ . '/../config/config.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit;
}

// Ensure customer is logged in
if (empty($_SESSION['customer_id'])) {
    echo json_encode([
        'success' => false,
        'require_login' => true,
        'message' => 'Please sign in to submit a verified timepiece review.'
    ]);
    exit;
}

$customerId = (int)$_SESSION['customer_id'];
$productId  = (int)($_POST['product_id'] ?? 0);
$rating     = (int)($_POST['rating'] ?? 5);
$title      = sanitize($_POST['title'] ?? '');
$reviewText = sanitize($_POST['review_text'] ?? '');

if ($productId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid product reference.']);
    exit;
}

if ($rating < 1 || $rating > 5) {
    echo json_encode(['success' => false, 'message' => 'Rating must be between 1 and 5 stars.']);
    exit;
}

if (empty($title) || strlen($title) < 3) {
    echo json_encode(['success' => false, 'message' => 'Please provide a descriptive review title.']);
    exit;
}

if (empty($reviewText) || strlen($reviewText) < 10) {
    echo json_encode(['success' => false, 'message' => 'Review text must be at least 10 characters long.']);
    exit;
}

$db = getDB();

// Verify product exists
$pStmt = $db->prepare("SELECT id, name FROM products WHERE id = ? AND status = 'active'");
$pStmt->execute([$productId]);
$prod = $pStmt->fetch();

if (!$prod) {
    echo json_encode(['success' => false, 'message' => 'Product not found.']);
    exit;
}

// Check if customer already submitted review for this product
$existStmt = $db->prepare("SELECT id FROM product_reviews WHERE product_id = ? AND customer_id = ? LIMIT 1");
$existStmt->execute([$productId, $customerId]);
if ($existStmt->fetch()) {
    echo json_encode(['success' => false, 'message' => 'You have already submitted a review for this timepiece.']);
    exit;
}

// Check if verified purchase
$vStmt = $db->prepare("
    SELECT oi.id 
    FROM order_items oi
    JOIN orders o ON oi.order_id = o.id
    WHERE oi.product_id = ? AND o.customer_id = ? AND o.status IN ('delivered', 'confirmed', 'dispatched')
    LIMIT 1
");
$vStmt->execute([$productId, $customerId]);
$isVerified = $vStmt->fetch() ? 1 : 0;

try {
    $ins = $db->prepare("
        INSERT INTO product_reviews 
        (product_id, customer_id, rating, title, review_text, is_verified_purchase, status, created_at)
        VALUES (?, ?, ?, ?, ?, ?, 'approved', NOW())
    ");
    $ins->execute([$productId, $customerId, $rating, $title, $reviewText, $isVerified]);

    echo json_encode([
        'success' => true,
        'message' => 'Thank you! Your verified review has been published.'
    ]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Failed to save review. Please try again.']);
}

