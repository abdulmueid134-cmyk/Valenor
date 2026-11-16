<?php
// =============================================================================
// VALENOR HOROLOGY — AJAX CART HANDLER (Add, Update, Remove)
// Supports Guest Session Cart with Mandatory Login for Cart Viewing
// =============================================================================

require_once __DIR__ . '/../config/config.php';

header('Content-Type: application/json; charset=utf-8');

$action = sanitize($_POST['action'] ?? '');
$productId = (int)($_POST['product_id'] ?? 0);
$variantId = !empty($_POST['variant_id']) ? (int)$_POST['variant_id'] : null;
$quantity = max(1, (int)($_POST['quantity'] ?? 1));

$db = getDB();

if ($action === 'add') {
    if ($productId <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid product selected.']);
        exit;
    }

    // Verify product exists and is active
    $stmt = $db->prepare("SELECT id, name, stock_quantity, status FROM products WHERE id = ? LIMIT 1");
    $stmt->execute([$productId]);
    $product = $stmt->fetch();

    if (!$product || $product['status'] !== 'active') {
        echo json_encode(['success' => false, 'message' => 'Product is not available.']);
        exit;
    }

    if ($product['stock_quantity'] < $quantity) {
        echo json_encode(['success' => false, 'message' => 'Requested quantity exceeds available stock (' . $product['stock_quantity'] . ' available).']);
        exit;
    }

    // 1. If customer is authenticated, store in MySQL carts/cart_items
    if (is_logged_in()) {
        $customerId = current_user_id();

        // Ensure cart header exists
        $stmt = $db->prepare("SELECT id FROM carts WHERE customer_id = ? LIMIT 1");
        $stmt->execute([$customerId]);
        $cartId = $stmt->fetchColumn();

        if (!$cartId) {
            $stmt = $db->prepare("INSERT INTO carts (customer_id, created_at, updated_at) VALUES (?, NOW(), NOW())");
            $stmt->execute([$customerId]);
            $cartId = $db->lastInsertId();
        }

        // Check if item already exists
        if ($variantId) {
            $stmt = $db->prepare("SELECT id, quantity FROM cart_items WHERE cart_id = ? AND product_id = ? AND variant_id = ? LIMIT 1");
            $stmt->execute([$cartId, $productId, $variantId]);
        } else {
            $stmt = $db->prepare("SELECT id, quantity FROM cart_items WHERE cart_id = ? AND product_id = ? AND variant_id IS NULL LIMIT 1");
            $stmt->execute([$cartId, $productId]);
        }
        $existing = $stmt->fetch();

        if ($existing) {
            $newQty = $existing['quantity'] + $quantity;
            $db->prepare("UPDATE cart_items SET quantity = ?, updated_at = NOW() WHERE id = ?")
               ->execute([$newQty, $existing['id']]);
        } else {
            $db->prepare("INSERT INTO cart_items (cart_id, product_id, variant_id, quantity, created_at, updated_at) VALUES (?, ?, ?, ?, NOW(), NOW())")
               ->execute([$cartId, $productId, $variantId, $quantity]);
        }
        $db->prepare("UPDATE carts SET updated_at = NOW() WHERE id = ?")->execute([$cartId]);

        $cartCount = get_cart_count();

        echo json_encode([
            'success'      => true,
            'is_logged_in' => true,
            'cart_count'   => $cartCount,
            'message'      => '<strong>' . e($product['name']) . '</strong> added to your luxury shopping bag.'
        ]);
        exit;
    }

    // 2. If guest, store in session cart
    if (!isset($_SESSION['guest_cart']) || !is_array($_SESSION['guest_cart'])) {
        $_SESSION['guest_cart'] = [];
    }

    $cartKey = $variantId ? "{$productId}_{$variantId}" : (string)$productId;

    if (isset($_SESSION['guest_cart'][$cartKey])) {
        $_SESSION['guest_cart'][$cartKey]['quantity'] += $quantity;
    } else {
        $_SESSION['guest_cart'][$cartKey] = [
            'product_id' => $productId,
            'variant_id' => $variantId,
            'quantity'   => $quantity,
            'added_at'   => time()
        ];
    }

    $cartCount = get_cart_count();

    echo json_encode([
        'success'      => true,
        'is_logged_in' => false,
        'cart_count'   => $cartCount,
        'message'      => '<strong>' . e($product['name']) . '</strong> added. Sign in to review your cart.'
    ]);
    exit;
}

echo json_encode(['success' => false, 'message' => 'Unsupported action.']);

