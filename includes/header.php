<?php
// =============================================================================
// VALENOR HOROLOGY — REUSABLE DOCUMENT HEADER COMPONENT
// =============================================================================

require_once __DIR__ . '/../config/config.php';

$pageTitle = isset($pageTitle) ? $pageTitle . ' — ' . APP_NAME . ' Horology' : APP_NAME . ' — Swiss-Caliber Luxury Timepieces';
$pageDesc  = $pageDesc ?? 'VALENOR Horology — Precision-engineered Swiss caliber luxury watches for men, women, and connoisseurs with insured nationwide Cash on Delivery.';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle) ?></title>
    <meta name="description" content="<?= e($pageDesc) ?>">
    <link rel="icon" type="image/svg+xml" href="<?= BASE_URL ?>/assets/images/logo.svg">

    <!-- Preconnect Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,400;0,500;0,600;0,700;1,400;1,600&family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <!-- Bootstrap 5 CSS & Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">

    <!-- Custom Luxury White & Caramel Stylesheet -->
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
</head>
<body>

<?= display_flash() ?>
