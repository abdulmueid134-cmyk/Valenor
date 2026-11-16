<?php
// =============================================================================
// VALENOR Horology — Vercel Serverless Entrypoint Dispatcher
// =============================================================================

// Root directory
$rootDir = realpath(__DIR__ . '/..');
chdir($rootDir);

$uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$path = ltrim($uri, '/');

// Direct to index.php if root requested
if (empty($path) || $path === 'index.html' || $path === 'index.php') {
    require $rootDir . '/index.php';
    exit;
}

// Check if target PHP file exists
$target = $rootDir . '/' . $path;
if (is_file($target) && substr($target, -4) === '.php') {
    require $target;
    exit;
}

// Fallback to index.php
require $rootDir . '/index.php';
