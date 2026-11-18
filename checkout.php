<?php
// =============================================================================
// VALENOR HOROLOGY — CHECKOUT & ORDER PLACEMENT (Phase 07)
// Mandatory login, stock verification, Cash on Delivery, and atomic order creation
// =============================================================================

require_once __DIR__ . '/config/config.php';

// 1. Mandatory customer authentication rule
require_login('checkout.php');

$db = getDB();
$customerId = current_user_id();
$user = current_user();

// 2. Fetch cart items & validate cart not empty
$cartItems = get_cart_items();
if (empty($cartItems)) {
    set_flash('info', 'Your shopping bag is empty. Please select a timepiece before proceeding to checkout.');
    header('Location: ' . BASE_URL . '/cart.php');
    exit;
}

// 3. Stock & Price Validation against live MySQL database
$stockErrors = [];
foreach ($cartItems as $item) {
    $stmt = $db->prepare("SELECT id, name, stock_quantity, status FROM products WHERE id = ? LIMIT 1");
    $stmt->execute([(int)$item['product_id']]);
    $prod = $stmt->fetch();

    if (!$prod || $prod['status'] !== 'active') {
        $stockErrors[] = "Timepiece '{$item['name']}' is no longer active.";
    } elseif ($prod['stock_quantity'] < (int)$item['quantity']) {
        $stockErrors[] = "Requested quantity for '{$item['name']}' exceeds current vault inventory ({$prod['stock_quantity']} available).";
    }
}

if (!empty($stockErrors)) {
    set_flash('danger', implode('<br>', $stockErrors));
    header('Location: ' . BASE_URL . '/cart.php');
    exit;
}

// Handle Coupon Application/Removal during Checkout
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['checkout_coupon_action'])) {
    if (verify_csrf()) {
        $couponAction = sanitize($_POST['checkout_coupon_action'] ?? '');
        if ($couponAction === 'apply') {
            $code = strtoupper(sanitize($_POST['checkout_coupon_code'] ?? ''));
            $rawTotals = calculate_cart_totals(null);
            $cRes = validate_coupon_code($code, $rawTotals['subtotal'], $customerId);
            if ($cRes['valid']) {
                $_SESSION['applied_coupon'] = $cRes['coupon'];
                set_flash('success', $cRes['message']);
            } else {
                set_flash('danger', $cRes['message']);
            }
        } elseif ($couponAction === 'remove') {
            unset($_SESSION['applied_coupon']);
            set_flash('info', 'Privilege coupon removed.');
        }
    }
    header('Location: ' . BASE_URL . '/checkout.php');
    exit;
}

// Calculate totals using validated database prices
$appliedCoupon = $_SESSION['applied_coupon'] ?? null;
$totals = calculate_cart_totals($appliedCoupon);

// Fetch saved default address if available
$stmtAddr = $db->prepare("SELECT * FROM customer_addresses WHERE customer_id = ? ORDER BY is_default DESC, id DESC LIMIT 1");
$stmtAddr->execute([$customerId]);
$defaultAddr = $stmtAddr->fetch();

$error = '';

// 4. Handle Order Placement POST Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['checkout_coupon_action'])) {
    if (!verify_csrf()) {
        $error = 'Security session expired. Please resubmit your order.';
    } else {
        $customerName = sanitize($_POST['customer_name'] ?? ($user['first_name'] . ' ' . $user['last_name']));
        $customerEmail = sanitize($_POST['customer_email'] ?? $user['email']);
        $customerPhone = sanitize($_POST['customer_phone'] ?? $user['phone']);
        $streetAddress = sanitize($_POST['shipping_street'] ?? '');
        $city          = sanitize($_POST['shipping_city'] ?? '');
        $postalCode    = sanitize($_POST['shipping_postal_code'] ?? '');
        $instructions  = sanitize($_POST['delivery_instructions'] ?? '');
        $saveAddress   = !empty($_POST['save_address']);

        // Validation
        if (empty($customerName) || empty($customerPhone) || empty($customerEmail) || empty($streetAddress) || empty($city)) {
            $error = 'Please complete all required contact and delivery address fields.';
        } elseif (!filter_var($customerEmail, FILTER_VALIDATE_EMAIL)) {
            $error = 'Please provide a valid delivery contact email address.';
        } else {
            try {
                $db->beginTransaction();

                // Re-verify coupon eligibility at final checkout submission
                if ($appliedCoupon) {
                    $cVal = validate_coupon_code($appliedCoupon['code'], $totals['subtotal'], $customerId);
                    if (!$cVal['valid']) {
                        unset($_SESSION['applied_coupon']);
                        throw new Exception('Coupon rule violation: ' . $cVal['message']);
                    }
                }

                // Generate unique order number (format: VAL-YYYYMMDD-XXXX)
                $orderNumber = sprintf('VAL-%s-%04d', date('Ymd'), rand(1000, 9999));

                // Insert into orders table
                $stmtOrder = $db->prepare("
                    INSERT INTO orders (
                        order_number, customer_id, customer_name, customer_email, customer_phone,
                        shipping_street, shipping_city, shipping_postal_code, delivery_instructions,
                        payment_method, payment_status, subtotal, discount_amount, shipping_fee,
                        total_amount, coupon_code, order_status, notes, created_at, updated_at
                    ) VALUES (
                        ?, ?, ?, ?, ?,
                        ?, ?, ?, ?,
                        'COD', 'Unpaid', ?, ?, ?,
                        ?, ?, 'Pending', ?, NOW(), NOW()
                    )
                ");

                $couponCode = !empty($appliedCoupon['code']) ? $appliedCoupon['code'] : null;
                $stmtOrder->execute([
                    $orderNumber,
                    $customerId,
                    $customerName,
                    $customerEmail,
                    $customerPhone,
                    $streetAddress,
                    $city,
                    $postalCode,
                    $instructions,
                    $totals['subtotal'],
                    $totals['discount'],
                    $totals['shipping'],
                    $totals['total'],
                    $couponCode,
                    'Placed via Cash on Delivery'
                ]);

                $orderId = $db->lastInsertId();

                // Insert each cart item into order_items and decrement stock
                $stmtItem = $db->prepare("
                    INSERT INTO order_items (
                        order_id, product_id, variant_id, product_name, product_sku,
                        variant_details, price_at_purchase, quantity, total_price, created_at
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
                ");

                $stmtDecStock = $db->prepare("
                    UPDATE products SET stock_quantity = GREATEST(0, stock_quantity - ?) WHERE id = ?
                ");

                foreach ($cartItems as $cItem) {
                    $unitPrice = !empty($cItem['discount_price']) && $cItem['discount_price'] > 0
                        ? (float)$cItem['discount_price']
                        : (float)$cItem['regular_price'];
                    $itemTotal = $unitPrice * (int)$cItem['quantity'];

                    $variantDesc = null;
                    if (!empty($cItem['color']) || !empty($cItem['size'])) {
                        $variantDesc = trim(($cItem['color'] ?? '') . ' ' . ($cItem['size'] ?? ''));
                    }

                    $stmtItem->execute([
                        $orderId,
                        $cItem['product_id'],
                        $cItem['variant_id'] ?? null,
                        $cItem['name'],
                        $cItem['sku'],
                        $variantDesc,
                        $unitPrice,
                        $cItem['quantity'],
                        $itemTotal
                    ]);

                    // Decrement stock in database
                    $stmtDecStock->execute([$cItem['quantity'], $cItem['product_id']]);
                }

                // Add to order status history audit trail
                $stmtHistory = $db->prepare("
                    INSERT INTO order_status_history (order_id, status, comment, updated_by, created_at)
                    VALUES (?, 'Pending', 'Order placed via Cash on Delivery (COD).', 'customer', NOW())
                ");
                $stmtHistory->execute([$orderId]);

                // Track coupon usage if applicable
                if ($couponCode && !empty($appliedCoupon['id'])) {
                    $db->prepare("UPDATE coupons SET usage_count = usage_count + 1 WHERE id = ?")->execute([$appliedCoupon['id']]);
                    $db->prepare("
                        INSERT INTO coupon_usage (coupon_id, order_id, customer_id, discount_amount, used_at)
                        VALUES (?, ?, ?, ?, NOW())
                    ")->execute([$appliedCoupon['id'], $orderId, $customerId, $totals['discount']]);
                }

                // Optionally save/update address in customer_addresses
                if ($saveAddress) {
                    $db->prepare("UPDATE customer_addresses SET is_default = 0 WHERE customer_id = ?")->execute([$customerId]);
                    $stmtInsAddr = $db->prepare("
                        INSERT INTO customer_addresses (
                            customer_id, address_title, recipient_name, phone, street_address,
                            city, postal_code, delivery_instructions, is_default, created_at, updated_at
                        ) VALUES (?, 'Primary Residence', ?, ?, ?, ?, ?, ?, 1, NOW(), NOW())
                    ");
                    $stmtInsAddr->execute([
                        $customerId,
                        $customerName,
                        $customerPhone,
                        $streetAddress,
                        $city,
                        $postalCode,
                        $instructions
                    ]);
                }

                // Clear customer's database shopping cart
                $cartIdStmt = $db->prepare("SELECT id FROM carts WHERE customer_id = ? LIMIT 1");
                $cartIdStmt->execute([$customerId]);
                $cartId = $cartIdStmt->fetchColumn();
                if ($cartId) {
                    $db->prepare("DELETE FROM cart_items WHERE cart_id = ?")->execute([$cartId]);
                }

                // Clear applied coupon
                unset($_SESSION['applied_coupon']);

                $db->commit();

                // Redirect to order success confirmation
                set_flash('success', "Order #{$orderNumber} placed successfully!");
                header("Location: " . BASE_URL . "/order-success.php?order=" . urlencode($orderNumber));
                exit;

            } catch (Exception $e) {
                if ($db->inTransaction()) {
                    $db->rollBack();
                }
                error_log("Order placement failed: " . $e->getMessage());
                $error = 'An error occurred while creating your order. Please try again.';
            }
        }
    }
}

$pageTitle = 'Secure Checkout — Cash on Delivery';
require_once INCLUDES_PATH . '/header.php';
require_once INCLUDES_PATH . '/navbar.php';
?>

<div class="py-4 border-bottom" style="background-color: var(--bg-white); border-color: var(--border-beige) !important;">
    <div class="container">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-1" style="font-size:0.82rem;">
                <li class="breadcrumb-item"><a href="<?= BASE_URL ?>/index.php" style="color:var(--text-dark);">Home</a></li>
                <li class="breadcrumb-item"><a href="<?= BASE_URL ?>/cart.php" style="color:var(--text-dark);">Shopping Bag</a></li>
                <li class="breadcrumb-item active text-muted" aria-current="page">Checkout</li>
            </ol>
        </nav>
        <h1 class="h2 mb-0 font-serif fw-bold" style="color:var(--text-dark);">Insured Delivery Checkout</h1>
    </div>
</div>

<div class="container py-5">
    <?php if (!empty($error)): ?>
        <div class="alert alert-danger py-2 mb-4"><?= e($error) ?></div>
    <?php endif; ?>

    <form method="POST" action="<?= BASE_URL ?>/checkout.php">
        <?= csrf_field() ?>

        <div class="row g-5">
            <!-- Left Column: Delivery & Contact Form -->
            <div class="col-lg-7">
                
                <!-- 1. Contact Information -->
                <div class="card p-4 bg-white border mb-4 shadow-sm" style="border-color:var(--border-beige) !important; border-radius:8px;">
                    <h5 class="font-serif fw-bold mb-3 pb-2 border-bottom" style="color:var(--caramel-dark); border-color:var(--border-beige) !important;">
                        <i class="bi bi-person-circle me-1"></i> 1. Contact & Recipient Information
                    </h5>
                    
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label small fw-bold text-uppercase" style="letter-spacing:0.06em;">Recipient Full Name <span class="text-danger">*</span></label>
                            <input type="text" name="customer_name" class="form-control" value="<?= e($_POST['customer_name'] ?? ($defaultAddr['recipient_name'] ?? ($user['first_name'] . ' ' . $user['last_name']))) ?>" required>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label small fw-bold text-uppercase" style="letter-spacing:0.06em;">Contact Phone <span class="text-danger">*</span></label>
                            <input type="tel" name="customer_phone" class="form-control" placeholder="+92 300 1234567" value="<?= e($_POST['customer_phone'] ?? ($defaultAddr['phone'] ?? $user['phone'])) ?>" required>
                            <div class="form-text small" style="font-size:0.72rem;">For courier dispatch coordination.</div>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label small fw-bold text-uppercase" style="letter-spacing:0.06em;">Email Address <span class="text-danger">*</span></label>
                            <input type="email" name="customer_email" class="form-control" value="<?= e($_POST['customer_email'] ?? $user['email']) ?>" required>
                        </div>
                    </div>
                </div>

                <!-- 2. Delivery Address -->
                <div class="card p-4 bg-white border mb-4 shadow-sm" style="border-color:var(--border-beige) !important; border-radius:8px;">
                    <h5 class="font-serif fw-bold mb-3 pb-2 border-bottom" style="color:var(--caramel-dark); border-color:var(--border-beige) !important;">
                        <i class="bi bi-geo-alt me-1"></i> 2. Delivery Destination
                    </h5>

                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label small fw-bold text-uppercase" style="letter-spacing:0.06em;">Complete Street Address <span class="text-danger">*</span></label>
                            <input type="text" name="shipping_street" class="form-control" placeholder="House/Apartment #, Street, Block or Sector" value="<?= e($_POST['shipping_street'] ?? ($defaultAddr['street_address'] ?? '')) ?>" required>
                        </div>

                        <div class="col-md-7">
                            <label class="form-label small fw-bold text-uppercase" style="letter-spacing:0.06em;">City <span class="text-danger">*</span></label>
                            <input type="text" name="shipping_city" class="form-control" placeholder="e.g. Karachi, Lahore, Islamabad" value="<?= e($_POST['shipping_city'] ?? ($defaultAddr['city'] ?? 'Karachi')) ?>" required>
                        </div>

                        <div class="col-md-5">
                            <label class="form-label small fw-bold text-uppercase" style="letter-spacing:0.06em;">Postal Code</label>
                            <input type="text" name="shipping_postal_code" class="form-control" placeholder="75500" value="<?= e($_POST['shipping_postal_code'] ?? ($defaultAddr['postal_code'] ?? '75500')) ?>">
                        </div>

                        <div class="col-12">
                            <label class="form-label small fw-bold text-uppercase" style="letter-spacing:0.06em;">Delivery Instructions (Optional)</label>
                            <textarea name="delivery_instructions" class="form-control" rows="2" placeholder="e.g. Please ring gate bell, deliver before 5 PM..."><?= e($_POST['delivery_instructions'] ?? ($defaultAddr['delivery_instructions'] ?? '')) ?></textarea>
                        </div>

                        <div class="col-12">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="save_address" value="1" id="save_address_check" <?= !empty($defaultAddr) ? 'checked' : '' ?>>
                                <label class="form-check-label small" for="save_address_check">
                                    Save this address as my primary delivery destination
                                </label>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 3. Payment Method: Cash on Delivery -->
                <div class="card p-4 bg-white border shadow-sm" style="border-color:var(--border-beige) !important; border-radius:8px;">
                    <h5 class="font-serif fw-bold mb-3 pb-2 border-bottom" style="color:var(--caramel-dark); border-color:var(--border-beige) !important;">
                        <i class="bi bi-cash-stack me-1"></i> 3. Payment Method
                    </h5>

                    <div class="p-3 rounded border" style="background-color:var(--bg-cream); border-color:var(--border-beige) !important;">
                        <div class="form-check d-flex align-items-center gap-2 mb-2">
                            <input class="form-check-input" type="radio" name="payment_method" id="payment_cod" value="COD" checked>
                            <label class="form-check-label fw-bold" for="payment_cod">
                                Cash on Delivery (COD) &bull; White-Glove Handover
                            </label>
                            <span class="badge ms-auto" style="background:var(--caramel-primary); font-size:0.7rem;">Verified Service</span>
                        </div>
                        <p class="small text-muted mb-0 ps-4">
                            Pay in cash or through guaranteed courier mobile payment upon inspection and doorstep handover by our insured delivery courier (BlueEx).
                        </p>
                    </div>
                </div>

            </div>

            <!-- Right Column: Order Review & Financial Breakdown -->
            <div class="col-lg-5">
                <div class="card p-4 bg-white border shadow-sm sticky-top" style="top:90px; border-color:var(--border-beige) !important; border-radius:8px;">
                    <h5 class="font-serif fw-bold mb-3 pb-2 border-bottom" style="border-color:var(--border-beige) !important;">
                        Order Summary (<?= count($cartItems) ?> <?= count($cartItems) === 1 ? 'Timepiece' : 'Timepieces' ?>)
                    </h5>

                    <!-- Itemized List -->
                    <div class="mb-4" style="max-height:300px; overflow-y:auto;">
                        <?php foreach ($cartItems as $it): 
                            $price = !empty($it['discount_price']) && $it['discount_price'] > 0 ? (float)$it['discount_price'] : (float)$it['regular_price'];
                            $lineTotal = $price * (int)$it['quantity'];
                            $img = BASE_URL . '/' . ltrim($it['primary_image'] ?: 'assets/images/watch-mens-chronograph.jpg', '/');
                        ?>
                            <div class="d-flex align-items-center gap-3 py-2 border-bottom" style="border-color:var(--border-beige) !important;">
                                <img src="<?= e($img) ?>" alt="<?= e($it['name']) ?>" class="rounded border" style="width:56px; height:56px; object-fit:cover; border-color:var(--border-beige) !important;">
                                <div class="flex-grow-1">
                                    <div class="fw-bold font-serif small mb-0 text-truncate" style="max-width:210px;"><?= e($it['name']) ?></div>
                                    <div class="text-muted small">Qty: <?= (int)$it['quantity'] ?> &bull; Ref: <?= e($it['sku']) ?></div>
                                </div>
                                <div class="text-end fw-semibold small" style="color:var(--caramel-dark);">
                                    <?= format_price($lineTotal) ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- Totals Breakdown -->
                    <div class="d-flex justify-content-between py-2 border-bottom text-muted small" style="border-color:var(--border-beige) !important;">
                        <span>Bag Subtotal:</span>
                        <span class="fw-semibold text-dark"><?= format_price($totals['subtotal']) ?></span>
                    </div>

                    <!-- Privilege Coupon Section -->
                    <div class="py-2 border-bottom" style="border-color:var(--border-beige) !important;">
                        <?php if (!empty($appliedCoupon)): ?>
                            <div class="d-flex align-items-center justify-content-between p-2 rounded" style="background:var(--bg-cream); border:1px dashed var(--caramel-primary);">
                                <div>
                                    <span class="badge" style="background:var(--caramel-dark);"><i class="bi bi-tag-fill me-1"></i><?= e($appliedCoupon['code']) ?></span>
                                    <span class="small text-success fw-bold ms-1">-<?= format_price($totals['discount']) ?></span>
                                </div>
                                <button type="button" class="btn btn-sm btn-outline-danger p-0 px-2" onclick="submitCouponAction('remove')" title="Remove Coupon">
                                    <i class="bi bi-x"></i> Remove
                                </button>
                            </div>
                        <?php else: ?>
                            <div class="input-group input-group-sm">
                                <input type="text" id="checkoutCouponInput" class="form-control text-uppercase" placeholder="Privilege Code (e.g. VALENOR20)">
                                <button type="button" class="btn btn-dark" onclick="submitCouponAction('apply')">Apply</button>
                            </div>
                        <?php endif; ?>
                    </div>

                    <?php if ($totals['discount'] > 0): ?>
                        <div class="d-flex justify-content-between py-2 border-bottom text-success small" style="border-color:var(--border-beige) !important;">
                            <span>Privilege Discount:</span>
                            <span class="fw-semibold">-<?= format_price($totals['discount']) ?></span>
                        </div>
                    <?php endif; ?>

                    <div class="d-flex justify-content-between py-2 border-bottom text-muted small" style="border-color:var(--border-beige) !important;">
                        <span>Insured Delivery:</span>
                        <span class="fw-semibold text-dark">
                            <?= $totals['shipping'] == 0 ? '<span class="text-success fw-bold">Complimentary</span>' : format_price($totals['shipping']) ?>
                        </span>
                    </div>

                    <div class="d-flex justify-content-between py-3 fs-5 fw-bold" style="color:var(--caramel-dark);">
                        <span>Total Payable (PKR):</span>
                        <span><?= format_price($totals['total']) ?></span>
                    </div>

                    <!-- Submit Button -->
                    <button type="submit" class="btn-valenor-primary w-100 justify-content-center py-3 text-uppercase fw-bold shadow-sm" style="letter-spacing:0.08em; font-size:0.9rem;">
                        <i class="bi bi-shield-check me-1"></i> Place Order (Cash on Delivery)
                    </button>

                    <!-- Trust Markers -->
                    <div class="mt-4 pt-3 border-top text-center text-muted small" style="border-color:var(--border-beige) !important; font-size:0.78rem;">
                        <div><i class="bi bi-patch-check-fill text-success me-1"></i> 100% Certified Authentic Swiss Movements</div>
                        <div class="mt-1"><i class="bi bi-truck text-primary me-1"></i> BlueEx Insured Air Courier Delivery</div>
                        <div class="mt-1"><i class="bi bi-arrow-counterclockwise text-warning me-1"></i> 14-Day Complimentary Returns</div>
                    </div>

                </div>
            </div>
        </div>

    </form>
</div>

<!-- Hidden standalone form for Coupon Actions during checkout -->
<form id="hiddenCouponForm" method="POST" action="<?= BASE_URL ?>/checkout.php" style="display:none;">
    <?= csrf_field() ?>
    <input type="hidden" name="checkout_coupon_action" id="hiddenCouponAction" value="">
    <input type="hidden" name="checkout_coupon_code" id="hiddenCouponCode" value="">
</form>

<script>
function submitCouponAction(action) {
    document.getElementById('hiddenCouponAction').value = action;
    if (action === 'apply') {
        const input = document.getElementById('checkoutCouponInput');
        const val = input ? input.value.trim() : '';
        if (!val) {
            alert('Please enter a privilege coupon code.');
            return;
        }
        document.getElementById('hiddenCouponCode').value = val;
    }
    document.getElementById('hiddenCouponForm').submit();
}
</script>

<?php require_once INCLUDES_PATH . '/footer.php'; ?>

