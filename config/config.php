<?php
// =============================================================================
// VALENOR B2C E-COMMERCE PLATFORM — SYSTEM CONFIGURATION
// =============================================================================

// Start session securely if not already started
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.cookie_samesite', 'Lax');
    session_start();
}

// Environment & Error Reporting
define('APP_ENV', getenv('APP_ENV') ?: 'development');
if (APP_ENV === 'development') {
    ini_set('display_errors', '1');
    ini_set('display_startup_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
    error_reporting(0);
}

// Directory Paths
define('ROOT_PATH', realpath(__DIR__ . '/..'));
define('CONFIG_PATH', ROOT_PATH . '/config');
define('INCLUDES_PATH', ROOT_PATH . '/includes');
define('UPLOADS_PATH', ROOT_PATH . '/uploads');
define('PRODUCT_UPLOADS_PATH', UPLOADS_PATH . '/products');
define('REVIEW_UPLOADS_PATH', UPLOADS_PATH . '/reviews');
define('RETURN_UPLOADS_PATH', UPLOADS_PATH . '/returns');

// Dynamically compute base URL for both CLI and Apache/built-in server
if (php_sapi_name() === 'cli') {
    define('BASE_URL', 'http://localhost:8000');
} else {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $scriptDir = dirname($_SERVER['SCRIPT_NAME'] ?? '');
    
    // Normalize script directory
    $scriptDir = str_replace('\\', '/', $scriptDir);
    // If script is in admin or ajax subfolder, step back to root
    if (preg_match('#/(admin|ajax)(/.*)?$#i', $scriptDir)) {
        $scriptDir = preg_replace('#/(admin|ajax)(/.*)?$#i', '', $scriptDir);
    }
    $scriptDir = rtrim($scriptDir, '/');
    define('BASE_URL', $protocol . $host . $scriptDir);
}

define('ADMIN_URL', BASE_URL . '/admin');
define('UPLOADS_URL', BASE_URL . '/uploads');

// Brand & Theme Configuration (White & Caramel Theme)
define('APP_NAME', 'VALENOR');
define('APP_TAGLINE', 'Premium Horology & Luxury Timepieces');
define('COLOR_PRIMARY_WHITE', '#FFFFFF');
define('COLOR_WARM_CREAM',    '#F8F3EB');
define('COLOR_PRIMARY_CARAMEL','#B8895B');
define('COLOR_DARK_CARAMEL',   '#8B6342');
define('COLOR_TEXT_CHARCOAL',  '#252525');
define('COLOR_BORDER_BEIGE',   '#E8DED2');

// E-Commerce Policy & Limits
define('DEFAULT_CURRENCY', 'PKR');
define('CURRENCY_SYMBOL', 'Rs. ');
define('RETURN_WINDOW_DAYS', 14);
define('FREE_SHIPPING_THRESHOLD', 15000.00);
define('STANDARD_SHIPPING_FEE', 250.00);

// File Upload Restrictions (Section 4 & 16)
define('MAX_PRODUCT_IMAGES', 10);
define('MIN_PRODUCT_IMAGES', 1);
define('MAX_UPLOAD_SIZE_BYTES', 5 * 1024 * 1024); // 5 MB
define('ALLOWED_IMAGE_TYPES', ['image/jpeg', 'image/png', 'image/webp']);
define('ALLOWED_IMAGE_EXTS', ['jpg', 'jpeg', 'png', 'webp']);

// Require core dependencies
require_once CONFIG_PATH . '/database.php';
require_once CONFIG_PATH . '/helpers.php';

