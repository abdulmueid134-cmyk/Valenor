<?php
// =============================================================================
// VALENOR HOROLOGY — ADMIN LOGOUT
// =============================================================================

require_once __DIR__ . '/../config/config.php';

unset($_SESSION['admin_id']);
unset($_SESSION['admin_name']);
unset($_SESSION['admin_role']);

set_flash('info', 'You have been securely signed out of the Administrator portal.');
header('Location: ' . ADMIN_URL . '/login.php');
exit;
