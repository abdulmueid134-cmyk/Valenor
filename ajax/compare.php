<?php
// =============================================================================
// VALENOR HOROLOGY — AJAX PRODUCT COMPARISON HANDLER
// =============================================================================

require_once __DIR__ . '/../config/config.php';

header('Content-Type: application/json; charset=utf-8');

$action = sanitize($_POST['action'] ?? '');
$productId = (int)($_POST['product_id'] ?? 0);

if ($productId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid product.']);
    exit;
}

if (!isset($_SESSION['compare']) || !is_array($_SESSION['compare'])) {
    $_SESSION['compare'] = [];
}

if ($action === 'toggle') {
    $pos = array_search($productId, $_SESSION['compare']);
    if ($pos !== false) {
        unset($_SESSION['compare'][$pos]);
        $_SESSION['compare'] = array_values($_SESSION['compare']);
        $isCompared = false;
        $msg = 'Removed from product comparison.';
    } else {
        if (count($_SESSION['compare']) >= 4) {
            echo json_encode([
                'success'       => false,
                'compare_count' => count($_SESSION['compare']),
                'message'       => 'You can compare a maximum of 4 watches at a time. Remove an item to add another.'
            ]);
            exit;
        }
        $_SESSION['compare'][] = $productId;
        $isCompared = true;
        $msg = 'Added to comparison list.';
    }

    echo json_encode([
        'success'       => true,
        'is_compared'   => $isCompared,
        'compare_count' => count($_SESSION['compare']),
        'message'       => $msg
    ]);
    exit;
}

echo json_encode(['success' => false, 'message' => 'Invalid request.']);

