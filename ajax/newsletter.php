<?php
// =============================================================================
// VALENOR HOROLOGY — AJAX NEWSLETTER SUBSCRIPTION HANDLER
// =============================================================================

require_once __DIR__ . '/../config/config.php';

header('Content-Type: application/json; charset=utf-8');

$email = sanitize($_POST['email'] ?? '');

if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'message' => 'Please provide a valid email address.']);
    exit;
}

// In production, insert into a newsletter subscribers list or CRM.
echo json_encode([
    'success' => true,
    'message' => 'Thank you for subscribing to the VALENOR Horology Gazette. Welcome code <strong>WELCOME10</strong> is now active.'
]);
