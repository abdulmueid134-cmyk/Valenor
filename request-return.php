<?php
// =============================================================================
// VALENOR HOROLOGY — CUSTOMER REQUEST RETURN REDIRECTOR / HANDLER
// =============================================================================

require_once __DIR__ . '/config/config.php';
require_login('my-orders.php');

$orderNumber = sanitize($_GET['order'] ?? '');
if (!empty($orderNumber)) {
    header('Location: ' . BASE_URL . '/order-details.php?order=' . urlencode($orderNumber) . '#returnModal');
    exit;
} else {
    header('Location: ' . BASE_URL . '/my-orders.php');
    exit;
}
