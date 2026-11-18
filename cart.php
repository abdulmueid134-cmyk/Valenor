<?php
// =============================================================================
// VALENOR HOROLOGY — SHOPPING CART (Section 9 & 20)
// PROTECTED ROUTE — MANDATORY CUSTOMER LOGIN ENFORCED
// =============================================================================

require_once __DIR__ . '/config/config.php';

// ENFORCE MANDATORY LOGIN (Section 20)
require_login('cart.php');

$db = getDB();
$customerId = current_user_id();

// Handle Form Submissions (Update Quantities, Remove Item, Apply Coupon)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) {
        set_flash('error', 'Session verification expired. Please try again.');
        header('Location: ' . BASE_URL . '/cart.php');
        exit;
    }

    $action = sanitize($_POST['action'] ?? '');

    if ($action === 'update_qty') {
        $cartItemId = (int)($_POST['cart_item_id'] ?? 0);
        $newQty     = max(1, (int)($_POST['quantity'] ?? 1));

        $stmt = $db->prepare("
            UPDATE cart_items ci
            JOIN carts c ON ci.cart_id = c.id
            SET ci.quantity = ?, ci.updated_at = NOW()
            WHERE ci.id = ? AND c.customer_id = ?
        ");
        $stmt->execute([$newQty, $cartItemId, $customerId]);
        set_flash('success', 'Shopping bag quantity updated.');
        header('Location: ' . BASE_URL . '/cart.php');
        exit;
    }

    if ($action === 'remove_item') {
        $cartItemId = (int)($_POST['cart_item_id'] ?? 0);
        $stmt = $db->prepare("
            DELETE ci FROM cart_items ci
            JOIN carts c ON ci.cart_id = c.id
            WHERE ci.id = ? AND c.customer_id = ?
        ");
        $stmt->execute([$cartItemId, $customerId]);
        set_flash('info', 'Item removed from your shopping bag.');
        header('Location: ' . BASE_URL . '/cart.php');
        exit;
    }

    if ($action === 'apply_coupon') {
        $code = strtoupper(sanitize($_POST['coupon_code'] ?? ''));
        $rawTotals = calculate_cart_totals(null);
        $result = validate_coupon_code($code, $rawTotals['subtotal'], $customerId);

        if ($result['valid']) {
            $_SESSION['applied_coupon'] = $result['coupon'];
            set_flash('success', $result['message']);
        } else {
            set_flash('danger', $result['message']);
        }
        header('Location: ' . BASE_URL . '/cart.php');
        exit;
    }

    if ($action === 'remove_coupon') {
        unset($_SESSION['applied_coupon']);
        set_flash('info', 'Coupon removed.');
        header('Location: ' . BASE_URL . '/cart.php');
        exit;
    }
}

// Fetch Cart Items for Authenticated Customer
$cartItems = get_cart_items();
$appliedCoupon = $_SESSION['applied_coupon'] ?? null;
$totals = calculate_cart_totals($appliedCoupon);

$pageTitle = 'Shopping Bag (' . get_cart_count() . ' items)';
require_once INCLUDES_PATH . '/header.php';
require_once INCLUDES_PATH . '/navbar.php';
?>

<div class="py-4 border-bottom" style="background-color: var(--bg-white); border-color: var(--border-beige) !important;">
    <div class="container">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-1" style="font-size:0.82rem;">
                <li class="breadcrumb-item"><a href="<?= BASE_URL ?>/index.php">Home</a></li>
                <li class="breadcrumb-item active" aria-current="page">Shopping Bag</li>
            </ol>
        </nav>
        <h1 class="h2 mb-0" style="font-family:var(--font-serif); font-weight:700;">
            Your Shopping Bag <span class="text-muted fs-6 fw-normal">(<?= get_cart_count() ?> timepieces)</span>
        </h1>
    </div>
</div>

<div class="container py-5">
    <?php if (!empty($cartItems)): ?>
        <div class="row g-5">
            <!-- Left: Cart Items List -->
            <div class="col-lg-8">
                <div class="card bg-white border p-3 p-md-4 mb-4" style="border-color:var(--border-beige) !important; border-radius:8px;">
                    <div class="table-responsive">
                        <table class="table align-middle mb-0" style="border-color:var(--border-beige);">
                            <thead class="text-uppercase small text-muted" style="letter-spacing:0.06em; font-size:0.75rem;">
                                <tr>
                                    <th style="width:50%;">Timepiece</th>
                                    <th class="text-center" style="width:20%;">Quantity</th>
                                    <th class="text-end" style="width:20%;">Price</th>
                                    <th class="text-center" style="width:10%;">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($cartItems as $item): 
                                    $unitPrice = !empty($item['discount_price']) && $item['discount_price'] > 0 ? (float)$item['discount_price'] : (float)$item['regular_price'];
                                    $itemSubtotal = $unitPrice * (int)$item['quantity'];
                                    $imgUrl = BASE_URL . '/' . ltrim($item['primary_image'] ?: 'assets/images/watch-mens-chronograph.jpg', '/');
                                ?>
                                    <tr>
                                        <td>
                                            <div class="d-flex align-items-center gap-3">
                                                <a href="<?= BASE_URL ?>/product-detail.php?id=<?= (int)$item['product_id'] ?>">
                                                    <img src="<?= e($imgUrl) ?>" alt="<?= e($item['name']) ?>" loading="lazy" class="rounded border" style="width:72px; height:72px; object-fit:cover; border-color:var(--border-beige) !important;">
                                                </a>
                                                <div>
                                                    <a href="<?= BASE_URL ?>/product-detail.php?id=<?= (int)$item['product_id'] ?>" class="fw-bold text-dark text-decoration-none font-serif fs-6">
                                                        <?= e($item['name']) ?>
                                                    </a>
                                                    <div class="text-muted small">SKU: <?= e($item['sku']) ?></div>
                                                    <?php if (!empty($item['color']) || !empty($item['size'])): ?>
                                                        <div class="text-muted small"><?= e($item['color'] ?? '') ?> <?= !empty($item['size']) ? ' &bull; ' . e($item['size']) : '' ?></div>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="text-center">
                                            <form method="POST" action="<?= BASE_URL ?>/cart.php" class="d-inline-flex align-items-center justify-content-center">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="action" value="update_qty">
                                                <input type="hidden" name="cart_item_id" value="<?= (int)$item['cart_item_id'] ?>">
                                                <div class="input-group input-group-sm" style="width:110px;">
                                                    <button class="btn btn-outline-secondary px-2" type="button" onclick="const inp=this.nextElementSibling; if(parseInt(inp.value)>1){inp.value=parseInt(inp.value)-1; inp.form.submit();}" aria-label="Decrease quantity">-</button>
                                                    <input type="number" name="quantity" class="form-control form-control-sm text-center fw-bold px-1" value="<?= (int)$item['quantity'] ?>" min="1" max="<?= (int)$item['stock_quantity'] ?>" onchange="this.form.submit()">
                                                    <button class="btn btn-outline-secondary px-2" type="button" onclick="const inp=this.previousElementSibling; if(parseInt(inp.value)<parseInt(inp.max)){inp.value=parseInt(inp.value)+1; inp.form.submit();}" aria-label="Increase quantity">+</button>
                                                </div>
                                            </form>
                                        </td>
                                        <td class="text-end">
                                            <div class="fw-bold" style="color:var(--caramel-dark);"><?= format_price($itemSubtotal) ?></div>
                                            <div class="text-muted small"><?= format_price($unitPrice) ?> each</div>
                                        </td>
                                        <td class="text-center">
                                            <form method="POST" action="<?= BASE_URL ?>/cart.php" onsubmit="return confirm('Remove this timepiece from your shopping bag?');">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="action" value="remove_item">
                                                <input type="hidden" name="cart_item_id" value="<?= (int)$item['cart_item_id'] ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-danger border-0" title="Remove Item">
                                                    <i class="bi bi-trash3"></i>
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="d-flex justify-content-between align-items-center">
                    <a href="<?= BASE_URL ?>/products.php" class="btn btn-outline-dark rounded-pill px-4" style="border-color:var(--border-beige); font-size:0.85rem;">
                        &larr; Continue Exploring Collections
                    </a>
                </div>
            </div>

            <!-- Right: Order Summary & Coupon -->
            <div class="col-lg-4">
                <div class="card bg-white border p-4 mb-4" style="border-color:var(--border-beige) !important; border-radius:8px;">
                    <h5 class="font-serif fw-bold mb-3" style="color:var(--caramel-dark);">Privilege Coupon</h5>

                    <?php if ($appliedCoupon): ?>
                        <div class="alert alert-success d-flex justify-content-between align-items-center py-2 px-3 small mb-0">
                            <div>
                                <i class="bi bi-check-circle-fill me-1"></i>
                                <strong><?= e($appliedCoupon['code']) ?></strong> applied
                            </div>
                            <form method="POST" action="<?= BASE_URL ?>/cart.php" class="mb-0">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="remove_coupon">
                                <button type="submit" class="btn btn-sm btn-link text-danger p-0" title="Remove Coupon">&times;</button>
                            </form>
                        </div>
                    <?php else: ?>
                        <form method="POST" action="<?= BASE_URL ?>/cart.php" class="d-flex gap-2">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="apply_coupon">
                            <input type="text" name="coupon_code" class="form-control form-control-sm text-uppercase" placeholder="Enter code (e.g. VALENOR20)" required>
                            <button type="submit" class="btn btn-sm btn-dark" style="font-size:0.8rem;">Apply</button>
                        </form>
                    <?php endif; ?>
                </div>

                <!-- Summary Breakdown Card -->
                <div class="card bg-white border p-4 shadow-sm" style="border-color:var(--border-beige) !important; border-radius:8px;">
                    <h5 class="font-serif fw-bold mb-3">Order Summary</h5>

                    <div class="d-flex justify-content-between py-2 border-bottom" style="border-color:var(--border-beige) !important;">
                        <span class="text-muted">Subtotal:</span>
                        <span class="fw-semibold"><?= format_price($totals['subtotal']) ?></span>
                    </div>

                    <?php if ($totals['discount'] > 0): ?>
                        <div class="d-flex justify-content-between py-2 border-bottom text-success" style="border-color:var(--border-beige) !important;">
                            <span>Privilege Discount:</span>
                            <span class="fw-semibold">-<?= format_price($totals['discount']) ?></span>
                        </div>
                    <?php endif; ?>

                    <div class="d-flex justify-content-between py-2 border-bottom" style="border-color:var(--border-beige) !important;">
                        <span class="text-muted">Insured Delivery:</span>
                        <span class="fw-semibold">
                            <?= $totals['shipping'] == 0 ? '<span class="text-success">Complimentary</span>' : format_price($totals['shipping']) ?>
                        </span>
                    </div>

                    <div class="d-flex justify-content-between py-3 fs-5 fw-bold" style="color:var(--caramel-dark);">
                        <span>Total (PKR):</span>
                        <span><?= format_price($totals['total']) ?></span>
                    </div>

                    <a href="<?= BASE_URL ?>/checkout.php" class="btn-valenor-primary w-100 justify-content-center py-3 text-center text-uppercase fw-bold" style="letter-spacing:0.1em; font-size:0.88rem;">
                        Proceed to Checkout &rarr;
                    </a>

                    <div class="mt-3 text-center text-muted small">
                        <i class="bi bi-shield-lock me-1 text-success"></i> Cash on Delivery &bull; 14-Day Returns Guaranteed
                    </div>
                </div>
            </div>
        </div>
    <?php else: ?>
        <div class="text-center py-5 bg-white rounded border p-5" style="border-color:var(--border-beige) !important; max-width:650px; margin:0 auto;">
            <i class="bi bi-bag-x fs-1 text-muted mb-3 d-block"></i>
            <h2 class="font-serif">Your Shopping Bag is Empty</h2>
            <p class="text-muted">Explore our fine collection of Swiss-caliber timepieces to begin curation.</p>
            <a href="<?= BASE_URL ?>/products.php" class="btn-valenor-primary mt-2">
                Discover Master Collection &rarr;
            </a>
        </div>
    <?php endif; ?>
</div>

<?php require_once INCLUDES_PATH . '/footer.php'; ?>

