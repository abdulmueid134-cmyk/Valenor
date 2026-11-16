<?php
// =============================================================================
// VALENOR B2C E-COMMERCE PLATFORM — CORE REUSABLE HELPERS & SECURITY
// =============================================================================

/**
 * Escape HTML output to prevent XSS (Cross-Site Scripting).
 */
function e($value) {
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

/**
 * Sanitize user input (string or recursive array).
 */
function sanitize($data) {
    if (is_array($data)) {
        return array_map('sanitize', $data);
    }
    return trim((string)$data);
}

/**
 * Generate or retrieve CSRF token from session.
 */
function csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Output HTML hidden input for CSRF protection.
 */
function csrf_field() {
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

/**
 * Verify CSRF token securely using hash_equals().
 */
function verify_csrf($token = null) {
    $token = $token ?? ($_POST['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ''));
    if (empty($token) || empty($_SESSION['csrf_token'])) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], (string)$token);
}

/**
 * Set a session flash message (success, error, warning, info).
 */
function set_flash($type, $message) {
    $_SESSION['flash'] = [
        'type'    => $type,
        'message' => $message
    ];
}

/**
 * Retrieve and clear the session flash message.
 */
function get_flash() {
    if (!empty($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

/**
 * Render Bootstrap alert for any pending flash message.
 */
function display_flash() {
    $flash = get_flash();
    if (!$flash) return '';

    $typeClass = match ($flash['type']) {
        'error', 'danger' => 'alert-danger',
        'warning'         => 'alert-warning',
        'info'            => 'alert-info',
        default           => 'alert-success',
    };

    return sprintf(
        '<div class="alert %s alert-dismissible fade show" role="alert">' .
        '%s' .
        '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>' .
        '</div>',
        e($typeClass),
        e($flash['message'])
    );
}

// =============================================================================
// CUSTOMER AUTHENTICATION HELPERS
// =============================================================================

function is_logged_in() {
    return !empty($_SESSION['customer_id']) && is_numeric($_SESSION['customer_id']);
}

function current_user_id() {
    return is_logged_in() ? (int)$_SESSION['customer_id'] : null;
}

function current_user() {
    if (!is_logged_in()) return null;
    static $user = null;
    if ($user === null) {
        $stmt = getDB()->prepare("SELECT id, first_name, last_name, email, phone, status, created_at FROM customers WHERE id = ? LIMIT 1");
        $stmt->execute([current_user_id()]);
        $user = $stmt->fetch() ?: false;
    }
    return $user ?: null;
}

/**
 * Protect customer routes. If not logged in, redirect to login page
 * and preserve intended destination.
 */
function require_login($redirectUrl = null) {
    if (!is_logged_in()) {
        $target = $redirectUrl ?: ($_SERVER['REQUEST_URI'] ?? 'cart.php');
        set_flash('warning', 'Please sign in or create an account to view and manage your shopping cart.');
        header('Location: ' . BASE_URL . '/login.php?redirect=' . urlencode($target));
        exit;
    }
}

// =============================================================================
// ADMIN AUTHENTICATION HELPERS
// =============================================================================

function is_admin_logged_in() {
    return !empty($_SESSION['admin_id']) && is_numeric($_SESSION['admin_id']);
}

function current_admin_id() {
    return is_admin_logged_in() ? (int)$_SESSION['admin_id'] : null;
}

function current_admin() {
    if (!is_admin_logged_in()) return null;
    static $admin = null;
    if ($admin === null) {
        $stmt = getDB()->prepare("SELECT id, name, email, role, status FROM admins WHERE id = ? LIMIT 1");
        $stmt->execute([current_admin_id()]);
        $admin = $stmt->fetch() ?: false;
    }
    return $admin ?: null;
}

function require_admin() {
    if (!is_admin_logged_in()) {
        set_flash('error', 'Administrator authentication required.');
        header('Location: ' . ADMIN_URL . '/login.php');
        exit;
    }
}

// =============================================================================
// SHOPPING CART & GUEST CART MERGING WORKFLOW (Section 20)
// =============================================================================

/**
 * Returns cart items array for either guest (session) or authenticated user (MySQL).
 */
function get_cart_items() {
    $db = getDB();

    if (is_logged_in()) {
        $customerId = current_user_id();
        $stmt = $db->prepare("
            SELECT ci.id AS cart_item_id, ci.product_id, ci.variant_id, ci.quantity,
                   p.name, p.slug, p.sku, p.regular_price, p.discount_price, p.stock_quantity,
                   pv.color, pv.size,
                   (SELECT image_path FROM product_images WHERE product_id = p.id AND is_primary = 1 LIMIT 1) AS primary_image
            FROM carts c
            JOIN cart_items ci ON c.id = ci.cart_id
            JOIN products p ON ci.product_id = p.id
            LEFT JOIN product_variants pv ON ci.variant_id = pv.id
            WHERE c.customer_id = ? AND p.status = 'active'
            ORDER BY ci.created_at DESC
        ");
        $stmt->execute([$customerId]);
        return $stmt->fetchAll();
    }

    // Guest cart from session
    if (empty($_SESSION['guest_cart']) || !is_array($_SESSION['guest_cart'])) {
        return [];
    }

    $items = [];
    foreach ($_SESSION['guest_cart'] as $key => $gItem) {
        $stmt = $db->prepare("
            SELECT p.id AS product_id, p.name, p.slug, p.sku, p.regular_price, p.discount_price, p.stock_quantity,
                   (SELECT image_path FROM product_images WHERE product_id = p.id AND is_primary = 1 LIMIT 1) AS primary_image
            FROM products p
            WHERE p.id = ? AND p.status = 'active'
            LIMIT 1
        ");
        $stmt->execute([(int)$gItem['product_id']]);
        $product = $stmt->fetch();
        if ($product) {
            $variantColor = null;
            $variantSize = null;
            if (!empty($gItem['variant_id'])) {
                $vStmt = $db->prepare("SELECT color, size FROM product_variants WHERE id = ? AND product_id = ? LIMIT 1");
                $vStmt->execute([(int)$gItem['variant_id'], (int)$gItem['product_id']]);
                $var = $vStmt->fetch();
                if ($var) {
                    $variantColor = $var['color'];
                    $variantSize = $var['size'];
                }
            }

            $items[] = [
                'cart_item_id'   => $key,
                'product_id'     => (int)$product['product_id'],
                'variant_id'     => !empty($gItem['variant_id']) ? (int)$gItem['variant_id'] : null,
                'quantity'       => (int)$gItem['quantity'],
                'name'           => $product['name'],
                'slug'           => $product['slug'],
                'sku'            => $product['sku'],
                'regular_price'  => $product['regular_price'],
                'discount_price' => $product['discount_price'],
                'stock_quantity' => $product['stock_quantity'],
                'color'          => $variantColor,
                'size'           => $variantSize,
                'primary_image'  => $product['primary_image']
            ];
        }
    }

    return $items;
}

/**
 * Returns total count of items in cart.
 */
function get_cart_count() {
    $items = get_cart_items();
    $total = 0;
    foreach ($items as $item) {
        $total += (int)$item['quantity'];
    }
    return $total;
}

/**
 * Calculate cart subtotal, discount, shipping, and grand total.
 */
function calculate_cart_totals($coupon = null) {
    $items = get_cart_items();
    $subtotal = 0.0;

    foreach ($items as $item) {
        $price = !empty($item['discount_price']) && $item['discount_price'] > 0
            ? (float)$item['discount_price']
            : (float)$item['regular_price'];
        $subtotal += $price * (int)$item['quantity'];
    }

    $discount = 0.0;
    if ($coupon && !empty($coupon['code'])) {
        if ($coupon['discount_type'] === 'percentage') {
            $discount = ($subtotal * (float)$coupon['discount_value']) / 100.0;
        } else {
            $discount = (float)$coupon['discount_value'];
        }
        $discount = min($discount, $subtotal);
    }

    $shipping = 0.0;
    if ($subtotal > 0) {
        $shipping = ($subtotal >= FREE_SHIPPING_THRESHOLD) ? 0.0 : STANDARD_SHIPPING_FEE;
    }

    $total = max(0.0, ($subtotal - $discount) + $shipping);

    return [
        'subtotal'       => $subtotal,
        'discount'       => $discount,
        'shipping'       => $shipping,
        'total'          => $total,
        'free_shipping'  => ($subtotal >= FREE_SHIPPING_THRESHOLD && $subtotal > 0)
    ];
}

/**
 * Merges guest session cart items into customer's database cart upon login or registration.
 * Combines quantities for matching product_id + variant_id to avoid duplicates.
 */
function merge_guest_cart_to_db($customerId) {
    if (empty($_SESSION['guest_cart']) || !is_array($_SESSION['guest_cart'])) {
        return;
    }

    $db = getDB();

    try {
        $db->beginTransaction();

        // 1. Get or create customer cart
        $stmt = $db->prepare("SELECT id FROM carts WHERE customer_id = ? LIMIT 1");
        $stmt->execute([(int)$customerId]);
        $cartId = $stmt->fetchColumn();

        if (!$cartId) {
            $stmt = $db->prepare("INSERT INTO carts (customer_id, created_at, updated_at) VALUES (?, NOW(), NOW())");
            $stmt->execute([(int)$customerId]);
            $cartId = $db->lastInsertId();
        }

        // 2. Loop through each guest item and insert/update in cart_items
        foreach ($_SESSION['guest_cart'] as $item) {
            $prodId = (int)$item['product_id'];
            $varId  = !empty($item['variant_id']) ? (int)$item['variant_id'] : null;
            $qty    = max(1, (int)$item['quantity']);

            if ($varId) {
                $checkStmt = $db->prepare("SELECT id, quantity FROM cart_items WHERE cart_id = ? AND product_id = ? AND variant_id = ? LIMIT 1");
                $checkStmt->execute([$cartId, $prodId, $varId]);
            } else {
                $checkStmt = $db->prepare("SELECT id, quantity FROM cart_items WHERE cart_id = ? AND product_id = ? AND variant_id IS NULL LIMIT 1");
                $checkStmt->execute([$cartId, $prodId]);
            }
            $existing = $checkStmt->fetch();

            if ($existing) {
                $newQty = (int)$existing['quantity'] + $qty;
                $upStmt = $db->prepare("UPDATE cart_items SET quantity = ?, updated_at = NOW() WHERE id = ?");
                $upStmt->execute([$newQty, (int)$existing['id']]);
            } else {
                $inStmt = $db->prepare("INSERT INTO cart_items (cart_id, product_id, variant_id, quantity, created_at, updated_at) VALUES (?, ?, ?, ?, NOW(), NOW())");
                $inStmt->execute([$cartId, $prodId, $varId, $qty]);
            }
        }

        // 3. Update cart timestamp
        $db->prepare("UPDATE carts SET updated_at = NOW() WHERE id = ?")->execute([$cartId]);

        $db->commit();

        // Clear guest cart from session once merged
        unset($_SESSION['guest_cart']);
    } catch (Exception $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        error_log("Failed to merge guest cart: " . $e->getMessage());
    }
}

/**
 * Merges guest session wishlist items into customer's database wishlist upon login or registration.
 */
function merge_guest_wishlist_to_db($customerId) {
    if (empty($_SESSION['wishlist']) || !is_array($_SESSION['wishlist'])) {
        return;
    }

    $db = getDB();

    try {
        $stmt = $db->prepare("SELECT id FROM wishlists WHERE customer_id = ? LIMIT 1");
        $stmt->execute([(int)$customerId]);
        $wishlistId = $stmt->fetchColumn();

        if (!$wishlistId) {
            $stmt = $db->prepare("INSERT INTO wishlists (customer_id, created_at, updated_at) VALUES (?, NOW(), NOW())");
            $stmt->execute([(int)$customerId]);
            $wishlistId = $db->lastInsertId();
        }

        foreach ($_SESSION['wishlist'] as $prodId) {
            $prodId = (int)$prodId;
            if ($prodId <= 0) continue;

            $chk = $db->prepare("SELECT id FROM wishlist_items WHERE wishlist_id = ? AND product_id = ? LIMIT 1");
            $chk->execute([$wishlistId, $prodId]);
            if (!$chk->fetchColumn()) {
                $ins = $db->prepare("INSERT INTO wishlist_items (wishlist_id, product_id, created_at) VALUES (?, ?, NOW())");
                $ins->execute([$wishlistId, $prodId]);
            }
        }

        unset($_SESSION['wishlist']);
    } catch (Exception $e) {
        error_log("Failed to merge guest wishlist: " . $e->getMessage());
    }
}

/**
 * Moves a product from wishlist to cart for an authenticated customer or guest.
 * Returns array with success status, message, and updated counts.
 */
function move_wishlist_to_cart($productId, $customerId = null) {
    $db = getDB();
    $productId = (int)$productId;
    if ($productId <= 0) {
        return ['success' => false, 'message' => 'Invalid product specified.'];
    }

    // Check stock & active status
    $stmt = $db->prepare("SELECT id, name, stock_quantity, status FROM products WHERE id = ? LIMIT 1");
    $stmt->execute([$productId]);
    $prod = $stmt->fetch();
    if (!$prod || $prod['status'] !== 'active') {
        return ['success' => false, 'message' => 'This timepiece is currently unavailable.'];
    }
    if ($prod['stock_quantity'] < 1) {
        return ['success' => false, 'message' => 'This timepiece is currently out of stock.'];
    }

    if (is_logged_in() || $customerId) {
        $cid = $customerId ?: current_user_id();

        // 1. Ensure cart exists
        $stmt = $db->prepare("SELECT id FROM carts WHERE customer_id = ? LIMIT 1");
        $stmt->execute([$cid]);
        $cartId = $stmt->fetchColumn();
        if (!$cartId) {
            $stmt = $db->prepare("INSERT INTO carts (customer_id, created_at, updated_at) VALUES (?, NOW(), NOW())");
            $stmt->execute([$cid]);
            $cartId = $db->lastInsertId();
        }

        // 2. Add or increment in cart_items
        $stmt = $db->prepare("SELECT id, quantity FROM cart_items WHERE cart_id = ? AND product_id = ? AND variant_id IS NULL LIMIT 1");
        $stmt->execute([$cartId, $productId]);
        $existing = $stmt->fetch();
        if ($existing) {
            $db->prepare("UPDATE cart_items SET quantity = quantity + 1, updated_at = NOW() WHERE id = ?")->execute([$existing['id']]);
        } else {
            $db->prepare("INSERT INTO cart_items (cart_id, product_id, quantity, created_at, updated_at) VALUES (?, ?, 1, NOW(), NOW())")->execute([$cartId, $productId]);
        }
        $db->prepare("UPDATE carts SET updated_at = NOW() WHERE id = ?")->execute([$cartId]);

        // 3. Remove from wishlist_items
        $stmt = $db->prepare("SELECT id FROM wishlists WHERE customer_id = ? LIMIT 1");
        $stmt->execute([$cid]);
        $wishlistId = $stmt->fetchColumn();
        if ($wishlistId) {
            $db->prepare("DELETE FROM wishlist_items WHERE wishlist_id = ? AND product_id = ?")->execute([$wishlistId, $productId]);
        }

        return [
            'success'        => true,
            'message'        => '<strong>' . e($prod['name']) . '</strong> moved to your shopping bag.',
            'cart_count'     => get_cart_count(),
            'wishlist_count' => get_wishlist_count()
        ];
    } else {
        // Guest mode fallback
        if (!isset($_SESSION['guest_cart'])) $_SESSION['guest_cart'] = [];
        $cartKey = (string)$productId;
        if (isset($_SESSION['guest_cart'][$cartKey])) {
            $_SESSION['guest_cart'][$cartKey]['quantity'] += 1;
        } else {
            $_SESSION['guest_cart'][$cartKey] = [
                'product_id' => $productId,
                'variant_id' => null,
                'quantity'   => 1,
                'added_at'   => time()
            ];
        }
        if (isset($_SESSION['wishlist']) && is_array($_SESSION['wishlist'])) {
            $pos = array_search($productId, $_SESSION['wishlist']);
            if ($pos !== false) {
                unset($_SESSION['wishlist'][$pos]);
                $_SESSION['wishlist'] = array_values($_SESSION['wishlist']);
            }
        }
        return [
            'success'        => true,
            'message'        => '<strong>' . e($prod['name']) . '</strong> moved to your shopping bag.',
            'cart_count'     => get_cart_count(),
            'wishlist_count' => count($_SESSION['wishlist'] ?? [])
        ];
    }
}

// =============================================================================
// WISHLIST & COMPARISON HELPERS
// =============================================================================

function get_wishlist_ids() {
    if (is_logged_in()) {
        $stmt = getDB()->prepare("
            SELECT wi.product_id
            FROM wishlists w
            JOIN wishlist_items wi ON w.id = wi.wishlist_id
            WHERE w.customer_id = ?
        ");
        $stmt->execute([current_user_id()]);
        return $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
    }
    return $_SESSION['wishlist'] ?? [];
}

function is_wishlisted($productId) {
    return in_array((int)$productId, get_wishlist_ids(), true);
}

function get_wishlist_count() {
    return count(get_wishlist_ids());
}

function get_compare_ids() {
    return $_SESSION['compare'] ?? [];
}

function is_compared($productId) {
    return in_array((int)$productId, get_compare_ids(), true);
}

function get_compare_count() {
    return count(get_compare_ids());
}

// =============================================================================
// PRESENTATION, FORMATTING & UI HELPERS
// =============================================================================

function format_price($amount) {
    return CURRENCY_SYMBOL . number_format((float)$amount, 2);
}

function format_date($datetime, $format = 'M d, Y h:i A') {
    if (!$datetime) return '—';
    return date($format, strtotime($datetime));
}

function slugify($text) {
    $text = preg_replace('~[^\pL\d]+~u', '-', $text);
    $text = iconv('utf-8', 'us-ascii//TRANSLIT', $text);
    $text = preg_replace('~[^-\w]+~', '', $text);
    $text = trim($text, '-');
    $text = preg_replace('~-+~', '-', $text);
    $text = strtolower($text);
    return empty($text) ? 'item-' . time() : $text;
}

function star_rating_html($rating, $reviewsCount = null) {
    $r = round((float)$rating * 2) / 2;
    $html = '<div class="star-rating d-inline-flex align-items-center gap-1" title="' . e(number_format($rating, 1)) . ' out of 5 stars">';
    for ($i = 1; $i <= 5; $i++) {
        if ($r >= $i) {
            $html .= '<i class="bi bi-star-fill text-warning"></i>';
        } elseif ($r >= $i - 0.5) {
            $html .= '<i class="bi bi-star-half text-warning"></i>';
        } else {
            $html .= '<i class="bi bi-star text-muted"></i>';
        }
    }
    if ($reviewsCount !== null) {
        $html .= '<span class="ms-1 text-muted small">(' . (int)$reviewsCount . ')</span>';
    }
    $html .= '</div>';
    return $html;
}

// 14 Order Statuses (Section 10)
function get_order_statuses() {
    return [
        'Pending',
        'Confirmed',
        'Processing',
        'Packed',
        'Dispatched',
        'Shipped / In Transit',
        'Out for Delivery',
        'Delivered',
        'Cancelled',
        'Return Requested',
        'Return Approved',
        'Return Rejected',
        'Returned',
        'Refunded'
    ];
}

function get_order_status_badge($status) {
    $map = [
        'Pending'              => 'bg-warning text-dark',
        'Confirmed'            => 'bg-info text-dark',
        'Processing'           => 'bg-primary text-white',
        'Packed'               => 'bg-secondary text-white',
        'Dispatched'           => 'bg-info text-white',
        'Shipped / In Transit' => 'bg-primary text-white',
        'Out for Delivery'     => 'bg-warning text-dark',
        'Delivered'            => 'bg-success text-white',
        'Cancelled'            => 'bg-danger text-white',
        'Return Requested'     => 'bg-warning text-dark',
        'Return Approved'      => 'bg-info text-white',
        'Return Rejected'      => 'bg-dark text-white',
        'Returned'             => 'bg-secondary text-white',
        'Refunded'             => 'bg-success text-white',
    ];
    $cls = $map[$status] ?? 'bg-secondary text-white';
    return '<span class="badge ' . $cls . '">' . e($status) . '</span>';
}

// =============================================================================
// SECURE FILE UPLOAD HANDLER (Section 4 & 16)
// =============================================================================

/**
 * Validate and move uploaded image file securely.
 *
 * @param array $file Single $_FILES item array
 * @param string $destinationDir Full directory path
 * @param string $prefix Filename prefix
 * @return array ['success' => bool, 'path' => string, 'error' => string]
 */
function upload_image($file, $destinationDir, $prefix = 'img') {
    if (!isset($file['error']) || is_array($file['error'])) {
        return ['success' => false, 'error' => 'Invalid file upload parameters.'];
    }

    if ($file['error'] !== UPLOAD_ERR_OK) {
        $uploadErrors = [
            UPLOAD_ERR_INI_SIZE   => 'The uploaded file exceeds the upload_max_filesize directive in php.ini.',
            UPLOAD_ERR_FORM_SIZE  => 'The uploaded file exceeds the MAX_FILE_SIZE directive specified in the HTML form.',
            UPLOAD_ERR_PARTIAL    => 'The uploaded file was only partially uploaded.',
            UPLOAD_ERR_NO_FILE    => 'No file was uploaded.',
            UPLOAD_ERR_NO_TMP_DIR => 'Missing a temporary folder.',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk.',
            UPLOAD_ERR_EXTENSION  => 'A PHP extension stopped the file upload.'
        ];
        return ['success' => false, 'error' => $uploadErrors[$file['error']] ?? 'File upload error.'];
    }

    if ($file['size'] > MAX_UPLOAD_SIZE_BYTES) {
        return ['success' => false, 'error' => 'File size exceeds maximum allowed limit (5 MB).'];
    }

    // Verify MIME type using finfo
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mimeType = $finfo->file($file['tmp_name']);
    if (!in_array($mimeType, ALLOWED_IMAGE_TYPES, true)) {
        return ['success' => false, 'error' => 'Invalid file type. Only JPG, PNG, and WebP images are permitted.'];
    }

    // Verify extension
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ALLOWED_IMAGE_EXTS, true)) {
        return ['success' => false, 'error' => 'Invalid file extension.'];
    }

    if (!is_dir($destinationDir)) {
        mkdir($destinationDir, 0755, true);
    }

    $uniqueName = sprintf('%s_%s_%s.%s', $prefix, date('Ymd_His'), bin2hex(random_bytes(4)), $ext);
    $targetPath = rtrim($destinationDir, '/\\') . DIRECTORY_SEPARATOR . $uniqueName;

    if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
        return ['success' => false, 'error' => 'Failed to save uploaded file.'];
    }

    // Compute path relative to project root (e.g. uploads/products/...)
    $normTarget = str_replace('\\', '/', $targetPath);
    $normUploads = str_replace('\\', '/', UPLOADS_PATH);
    $relPath = ltrim(str_replace($normUploads, '', $normTarget), '/');

    return [
        'success'   => true,
        'filename'  => $uniqueName,
        'rel_path'  => 'uploads/' . $relPath,
        'full_path' => $targetPath
    ];
}

