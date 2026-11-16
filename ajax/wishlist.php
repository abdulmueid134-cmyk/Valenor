<?php
// =============================================================================
// VALENOR HOROLOGY — AJAX WISHLIST HANDLER (Toggle, Remove, Move To Cart, Clear)
// =============================================================================

require_once __DIR__ . '/../config/config.php';

header('Content-Type: application/json; charset=utf-8');

$action = sanitize($_POST['action'] ?? '');
$productId = (int)($_POST['product_id'] ?? 0);

$db = getDB();

// 1. CLEAR ENTIRE WISHLIST
if ($action === 'clear') {
    if (is_logged_in()) {
        $customerId = current_user_id();
        $stmt = $db->prepare("SELECT id FROM wishlists WHERE customer_id = ? LIMIT 1");
        $stmt->execute([$customerId]);
        $wishlistId = $stmt->fetchColumn();
        if ($wishlistId) {
            $db->prepare("DELETE FROM wishlist_items WHERE wishlist_id = ?")->execute([$wishlistId]);
        }
    } else {
        $_SESSION['wishlist'] = [];
    }

    echo json_encode([
        'success'        => true,
        'wishlist_count' => 0,
        'message'        => 'Your private wishlist has been cleared.'
    ]);
    exit;
}

// For remaining actions, valid productId is required
if ($productId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid product specified.']);
    exit;
}

// 2. MOVE TO CART
if ($action === 'move_to_cart') {
    $res = move_wishlist_to_cart($productId);
    echo json_encode($res);
    exit;
}

// 3. REMOVE EXPLICITLY FROM WISHLIST
if ($action === 'remove') {
    if (is_logged_in()) {
        $customerId = current_user_id();
        $stmt = $db->prepare("SELECT id FROM wishlists WHERE customer_id = ? LIMIT 1");
        $stmt->execute([$customerId]);
        $wishlistId = $stmt->fetchColumn();
        if ($wishlistId) {
            $db->prepare("DELETE FROM wishlist_items WHERE wishlist_id = ? AND product_id = ?")->execute([$wishlistId, $productId]);
        }
    } else {
        if (isset($_SESSION['wishlist']) && is_array($_SESSION['wishlist'])) {
            $pos = array_search($productId, $_SESSION['wishlist']);
            if ($pos !== false) {
                unset($_SESSION['wishlist'][$pos]);
                $_SESSION['wishlist'] = array_values($_SESSION['wishlist']);
            }
        }
    }

    echo json_encode([
        'success'        => true,
        'is_wishlisted'  => false,
        'wishlist_count' => get_wishlist_count(),
        'message'        => 'Timepiece removed from your private wishlist.'
    ]);
    exit;
}

// 4. TOGGLE WISHLIST (Add if absent, Remove if present)
if ($action === 'toggle') {
    if (is_logged_in()) {
        $customerId = current_user_id();

        // Ensure wishlist header exists
        $stmt = $db->prepare("SELECT id FROM wishlists WHERE customer_id = ? LIMIT 1");
        $stmt->execute([$customerId]);
        $wishlistId = $stmt->fetchColumn();

        if (!$wishlistId) {
            $stmt = $db->prepare("INSERT INTO wishlists (customer_id, created_at, updated_at) VALUES (?, NOW(), NOW())");
            $stmt->execute([$customerId]);
            $wishlistId = $db->lastInsertId();
        }

        // Check if item exists
        $stmt = $db->prepare("SELECT id FROM wishlist_items WHERE wishlist_id = ? AND product_id = ? LIMIT 1");
        $stmt->execute([$wishlistId, $productId]);
        $exists = $stmt->fetchColumn();

        if ($exists) {
            $db->prepare("DELETE FROM wishlist_items WHERE id = ?")->execute([$exists]);
            $isWishlisted = false;
            $msg = 'Removed from your saved wishlist.';
        } else {
            $db->prepare("INSERT INTO wishlist_items (wishlist_id, product_id, created_at) VALUES (?, ?, NOW())")->execute([$wishlistId, $productId]);
            $isWishlisted = true;
            $msg = 'Saved to your luxury wishlist.';
        }
    } else {
        if (!isset($_SESSION['wishlist']) || !is_array($_SESSION['wishlist'])) {
            $_SESSION['wishlist'] = [];
        }
        $pos = array_search($productId, $_SESSION['wishlist']);
        if ($pos !== false) {
            unset($_SESSION['wishlist'][$pos]);
            $_SESSION['wishlist'] = array_values($_SESSION['wishlist']);
            $isWishlisted = false;
            $msg = 'Removed from your saved wishlist.';
        } else {
            $_SESSION['wishlist'][] = $productId;
            $_SESSION['wishlist'] = array_values(array_unique($_SESSION['wishlist']));
            $isWishlisted = true;
            $msg = 'Saved to your luxury wishlist.';
        }
    }

    echo json_encode([
        'success'        => true,
        'is_wishlisted'  => $isWishlisted,
        'wishlist_count' => get_wishlist_count(),
        'message'        => $msg
    ]);
    exit;
}

echo json_encode(['success' => false, 'message' => 'Invalid action request.']);
