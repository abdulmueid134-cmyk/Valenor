<?php
// =============================================================================
// VALENOR HOROLOGY — CUSTOMER LOGOUT
// =============================================================================

require_once __DIR__ . '/config/config.php';

unset($_SESSION['customer_id']);
unset($_SESSION['customer_name']);

set_flash('info', 'You have been safely signed out. Thank you for visiting VALENOR Horology.');
header('Location: ' . BASE_URL . '/index.php');
exit;

