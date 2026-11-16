<?php
// =============================================================================
// VALENOR HOROLOGY — AJAX PRODUCT COMPARISON HANDLER
// Supports Toggle, Add, Remove, and Clear with Duplicate Prevention
// =============================================================================

require_once __DIR__ . '/../config/config.php';

header('Content-Type: application/json; charset=utf-8');

$action = sanitize($_POST['action'] ?? '');
$productId = (int)($_POST['product_id'] ?? 0);

if (!isset($_SESSION['compare']) || !is_array($_SESSION['compare'])) {
    $_SESSION['compare'] = [];
}

// 1. CLEAR ALL COMPARISONS
if ($action === 'clear') {
    $_SESSION['compare'] = [];
    echo json_encode([
        'success'       => true,
        'compare_count' => 0,
        'message'       => 'Comparison list cleared.'
    ]);
    exit;
}

if ($productId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid product specified.']);
    exit;
}

// 2. REMOVE PRODUCT
if ($action === 'remove') {
    $pos = array_search($productId, $_SESSION['compare']);
    if ($pos !== false) {
        unset($_SESSION['compare'][$pos]);
        $_SESSION['compare'] = array_values(array_unique($_SESSION['compare']));
    }

    echo json_encode([
        'success'       => true,
        'is_compared'   => false,
        'compare_count' => count($_SESSION['compare']),
        'message'       => 'Timepiece removed from comparison.'
    ]);
    exit;
}

// 3. EXPLICIT ADD (Duplicate Prevention)
if ($action === 'add') {
    if (in_array($productId, $_SESSION['compare'], true)) {
        echo json_encode([
            'success'       => true,
            'is_compared'   => true,
            'compare_count' => count($_SESSION['compare']),
            'message'       => 'Timepiece is already in your comparison list.'
        ]);
        exit;
    }

    if (count($_SESSION['compare']) >= 4) {
        echo json_encode([
            'success'       => false,
            'compare_count' => count($_SESSION['compare']),
            'message'       => 'You can compare a maximum of 4 timepieces simultaneously. Remove an item to add another.'
        ]);
        exit;
    }

    $_SESSION['compare'][] = $productId;
    $_SESSION['compare'] = array_values(array_unique($_SESSION['compare']));

    echo json_encode([
        'success'       => true,
        'is_compared'   => true,
        'compare_count' => count($_SESSION['compare']),
        'message'       => 'Timepiece added to comparison list.'
    ]);
    exit;
}

// 4. TOGGLE
if ($action === 'toggle') {
    $pos = array_search($productId, $_SESSION['compare']);
    if ($pos !== false) {
        unset($_SESSION['compare'][$pos]);
        $_SESSION['compare'] = array_values(array_unique($_SESSION['compare']));
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
        $_SESSION['compare'] = array_values(array_unique($_SESSION['compare']));
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

echo json_encode(['success' => false, 'message' => 'Invalid action request.']);
