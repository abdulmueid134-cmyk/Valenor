<?php
// =============================================================================
// VALENOR HOROLOGY — AJAX CUSTOMER REVIEW SUBMISSION & EDIT HANDLER (Phase 09)
// =============================================================================

require_once __DIR__ . '/../config/config.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit;
}

// 1. Ensure customer is authenticated
if (!is_logged_in()) {
    echo json_encode([
        'success'       => false,
        'require_login' => true,
        'message'       => 'Please sign in to your Valenor account to submit or manage a review.'
    ]);
    exit;
}

$customerId = (int)current_user_id();
$productId  = (int)($_POST['product_id'] ?? 0);
$action     = sanitize($_POST['action'] ?? 'create');
$reviewId   = (int)($_POST['review_id'] ?? 0);
$rating     = (int)($_POST['rating'] ?? 5);
$title      = sanitize($_POST['title'] ?? '');
$reviewText = sanitize($_POST['review_text'] ?? '');

if ($productId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid timepiece reference.']);
    exit;
}

if ($rating < 1 || $rating > 5) {
    echo json_encode(['success' => false, 'message' => 'Rating must be between 1 and 5 stars.']);
    exit;
}

if (empty($title) || strlen($title) < 3) {
    echo json_encode(['success' => false, 'message' => 'Please provide a descriptive review headline (at least 3 characters).']);
    exit;
}

if (empty($reviewText) || strlen($reviewText) < 10) {
    echo json_encode(['success' => false, 'message' => 'Review description must be at least 10 characters long.']);
    exit;
}

$db = getDB();

// 2. Verify product exists
$pStmt = $db->prepare("SELECT id, name FROM products WHERE id = ? AND status = 'active'");
$pStmt->execute([$productId]);
$prod = $pStmt->fetch();

if (!$prod) {
    echo json_encode(['success' => false, 'message' => 'Timepiece not found in vault catalog.']);
    exit;
}

// 3. Verified Delivered Purchase Check
// "Only customers with verified delivered purchases can submit verified-purchase reviews."
$vStmt = $db->prepare("
    SELECT o.id 
    FROM order_items oi
    JOIN orders o ON oi.order_id = o.id
    WHERE oi.product_id = ? AND o.customer_id = ? AND o.order_status = 'Delivered'
    ORDER BY o.id DESC
    LIMIT 1
");
$vStmt->execute([$productId, $customerId]);
$deliveredOrder = $vStmt->fetch();

if (!$deliveredOrder) {
    echo json_encode([
        'success' => false,
        'message' => 'Only collectors with a verified delivered purchase can submit reviews for this timepiece.'
    ]);
    exit;
}

$orderId = (int)$deliveredOrder['id'];
$isVerified = 1;

// 4. Handle Image Upload if provided
$uploadedImagePath = null;
$uploadedImagesJson = null;

if (!empty($_FILES['review_image']) && $_FILES['review_image']['error'] !== UPLOAD_ERR_NO_FILE) {
    $uploadRes = upload_image($_FILES['review_image'], UPLOADS_PATH . '/reviews', 'rev');
    if ($uploadRes['success']) {
        $uploadedImagePath = $uploadRes['rel_path'];
        $uploadedImagesJson = json_encode([$uploadRes['rel_path']]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Photo upload failed: ' . $uploadRes['error']]);
        exit;
    }
}

try {
    if ($action === 'edit' || $reviewId > 0) {
        // Edit own existing review
        $chk = $db->prepare("SELECT id, review_image, review_images FROM product_reviews WHERE (id = ? OR (product_id = ? AND customer_id = ?)) AND customer_id = ? LIMIT 1");
        $chk->execute([$reviewId, $productId, $customerId, $customerId]);
        $existing = $chk->fetch();

        if (!$existing) {
            echo json_encode(['success' => false, 'message' => 'No existing review found to edit.']);
            exit;
        }

        $revTargetId = (int)$existing['id'];
        $finalImage  = $uploadedImagePath ?: $existing['review_image'];
        $finalImages = $uploadedImagesJson ?: $existing['review_images'];

        $upd = $db->prepare("
            UPDATE product_reviews SET
                rating = ?,
                title = ?,
                review_text = ?,
                review_image = ?,
                review_images = ?,
                is_verified_purchase = 1,
                order_id = ?,
                status = 'approved',
                updated_at = NOW()
            WHERE id = ? AND customer_id = ?
        ");
        $upd->execute([$rating, $title, $reviewText, $finalImage, $finalImages, $orderId, $revTargetId, $customerId]);

        echo json_encode([
            'success' => true,
            'action'  => 'updated',
            'message' => 'Your review has been updated and published to the timepiece showcase.'
        ]);
        exit;
    } else {
        // Create new review
        // Check if customer already submitted review for this product
        $existStmt = $db->prepare("SELECT id FROM product_reviews WHERE product_id = ? AND customer_id = ? LIMIT 1");
        $existStmt->execute([$productId, $customerId]);
        $already = $existStmt->fetch();

        if ($already) {
            // Automatically update existing review if customer submits again
            $upd = $db->prepare("
                UPDATE product_reviews SET
                    rating = ?,
                    title = ?,
                    review_text = ?,
                    review_image = COALESCE(?, review_image),
                    review_images = COALESCE(?, review_images),
                    is_verified_purchase = 1,
                    order_id = ?,
                    status = 'approved',
                    updated_at = NOW()
                WHERE id = ? AND customer_id = ?
            ");
            $upd->execute([$rating, $title, $reviewText, $uploadedImagePath, $uploadedImagesJson, $orderId, $already['id'], $customerId]);

            echo json_encode([
                'success' => true,
                'action'  => 'updated',
                'message' => 'Your review has been updated and saved.'
            ]);
            exit;
        }

        $ins = $db->prepare("
            INSERT INTO product_reviews 
            (product_id, customer_id, order_id, rating, title, review_text, review_image, review_images, is_verified_purchase, status, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1, 'approved', NOW(), NOW())
        ");
        $ins->execute([$productId, $customerId, $orderId, $rating, $title, $reviewText, $uploadedImagePath, $uploadedImagesJson]);

        echo json_encode([
            'success' => true,
            'action'  => 'created',
            'message' => 'Thank you! Your verified timepiece review has been published.'
        ]);
        exit;
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Database error while saving review. Please try again.']);
}
