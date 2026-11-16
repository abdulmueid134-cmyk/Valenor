<?php
// =============================================================================
// VALENOR HOROLOGY — ADMIN ORDER DETAILS & DISPATCH MANAGEMENT (Phase 08)
// Full order oversight, status transitions, tracking info, payment status & history
// =============================================================================

require_once __DIR__ . '/../config/config.php';
require_admin();

$db = getDB();
$orderId = (int)($_GET['id'] ?? 0);

if ($orderId <= 0) {
    set_flash('danger', 'Invalid order ID.');
    header('Location: ' . ADMIN_URL . '/orders.php');
    exit;
}

// 1. Fetch Order Details
$stmt = $db->prepare("
    SELECT o.*, c.first_name, c.last_name, c.email AS account_email, c.phone AS account_phone
    FROM orders o
    LEFT JOIN customers c ON o.customer_id = c.id
    WHERE o.id = ?
    LIMIT 1
");
$stmt->execute([$orderId]);
$order = $stmt->fetch();

if (!$order) {
    set_flash('danger', 'Order record not found in vault database.');
    header('Location: ' . ADMIN_URL . '/orders.php');
    exit;
}

$allStatuses = [
    'Pending',
    'Confirmed',
    'Processing',
    'Packed',
    'Dispatched',
    'Shipped / In Transit',
    'Out for Delivery',
    'Delivered',
    'Cancelled'
];

// 2. Handle POST Updates (Status, Payment, Tracking)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) {
        set_flash('danger', 'Security session verification expired.');
        header('Location: ' . ADMIN_URL . '/order-details.php?id=' . $orderId);
        exit;
    }

    $action = sanitize($_POST['admin_action'] ?? '');

    // A. UPDATE ORDER STATUS
    if ($action === 'update_order_status') {
        $newStatus = sanitize($_POST['order_status'] ?? '');
        $comment   = sanitize($_POST['comment'] ?? 'Status updated by Concierge Administrator.');
        $autoPaid  = !empty($_POST['mark_paid']);

        if (in_array($newStatus, $allStatuses, true)) {
            $prevStatus = $order['order_status'];

            // If cancelling an order that was not cancelled, restore stock
            if ($newStatus === 'Cancelled' && $prevStatus !== 'Cancelled') {
                $iStmt = $db->prepare("SELECT product_id, quantity FROM order_items WHERE order_id = ?");
                $iStmt->execute([$orderId]);
                $itemsToRestore = $iStmt->fetchAll();
                foreach ($itemsToRestore as $rItem) {
                    if (!empty($rItem['product_id'])) {
                        $db->prepare("UPDATE products SET stock_quantity = stock_quantity + ? WHERE id = ?")
                           ->execute([(int)$rItem['quantity'], (int)$rItem['product_id']]);
                    }
                }
            }

            // Update order status
            if ($autoPaid && $order['payment_status'] === 'Unpaid') {
                $db->prepare("UPDATE orders SET order_status = ?, payment_status = 'Paid', updated_at = NOW() WHERE id = ?")
                   ->execute([$newStatus, $orderId]);
            } else {
                $db->prepare("UPDATE orders SET order_status = ?, updated_at = NOW() WHERE id = ?")
                   ->execute([$newStatus, $orderId]);
            }

            // Record in audit trail
            $db->prepare("
                INSERT INTO order_status_history (order_id, status, comment, updated_by, admin_id, created_at)
                VALUES (?, ?, ?, 'admin', ?, NOW())
            ")->execute([$orderId, $newStatus, $comment, current_admin_id()]);

            set_flash('success', "Order status successfully updated to '{$newStatus}'.");
            header('Location: ' . ADMIN_URL . '/order-details.php?id=' . $orderId);
            exit;
        }
    }

    // B. UPDATE PAYMENT STATUS
    if ($action === 'update_payment_status') {
        $newPayStatus = sanitize($_POST['payment_status'] ?? '');
        $validPayStatuses = ['Unpaid', 'Pending', 'Paid', 'Refunded', 'Failed'];

        if (in_array($newPayStatus, $validPayStatuses, true)) {
            $db->prepare("UPDATE orders SET payment_status = ?, updated_at = NOW() WHERE id = ?")
               ->execute([$newPayStatus, $orderId]);

            $db->prepare("
                INSERT INTO order_status_history (order_id, status, comment, updated_by, admin_id, created_at)
                VALUES (?, ?, ?, 'admin', ?, NOW())
            ")->execute([$orderId, $order['order_status'], "Payment status updated to '{$newPayStatus}'.", current_admin_id()]);

            set_flash('success', "Payment status updated to '{$newPayStatus}'.");
            header('Location: ' . ADMIN_URL . '/order-details.php?id=' . $orderId);
            exit;
        }
    }

    // C. ADD / UPDATE TRACKING INFORMATION
    if ($action === 'update_tracking') {
        $courierName = sanitize($_POST['courier_name'] ?? 'BlueEx Express');
        $trackingNum = sanitize($_POST['tracking_number'] ?? '');
        $dispatchNow = !empty($_POST['auto_dispatch']);

        $db->prepare("UPDATE orders SET courier_name = ?, tracking_number = ?, updated_at = NOW() WHERE id = ?")
           ->execute([$courierName, $trackingNum, $orderId]);

        $comment = "Courier tracking assigned: {$courierName} #{$trackingNum}";

        if ($dispatchNow && in_array($order['order_status'], ['Pending', 'Confirmed', 'Processing', 'Packed'])) {
            $db->prepare("UPDATE orders SET order_status = 'Dispatched', updated_at = NOW() WHERE id = ?")
               ->execute([$orderId]);
            $db->prepare("
                INSERT INTO order_status_history (order_id, status, comment, updated_by, admin_id, created_at)
                VALUES (?, 'Dispatched', ?, 'admin', ?, NOW())
            ")->execute([$orderId, $comment, current_admin_id()]);
            set_flash('success', "Tracking details saved and order advanced to 'Dispatched'.");
        } else {
            $db->prepare("
                INSERT INTO order_status_history (order_id, status, comment, updated_by, admin_id, created_at)
                VALUES (?, ?, ?, 'admin', ?, NOW())
            ")->execute([$orderId, $order['order_status'], $comment, current_admin_id()]);
            set_flash('success', "Tracking information successfully updated.");
        }

        header('Location: ' . ADMIN_URL . '/order-details.php?id=' . $orderId);
        exit;
    }
}

// 3. Fetch Items & Status History
$itemsStmt = $db->prepare("
    SELECT oi.*, 
           (SELECT image_path FROM product_images WHERE product_id = oi.product_id AND is_primary = 1 LIMIT 1) AS primary_image
    FROM order_items oi
    WHERE oi.order_id = ?
");
$itemsStmt->execute([$orderId]);
$orderItems = $itemsStmt->fetchAll();

$historyStmt = $db->prepare("
    SELECT osh.*, a.name AS admin_name
    FROM order_status_history osh
    LEFT JOIN admins a ON osh.admin_id = a.id
    WHERE osh.order_id = ?
    ORDER BY osh.created_at ASC
");
$historyStmt->execute([$orderId]);
$history = $historyStmt->fetchAll();

$adminTitle      = 'Order #' . $order['order_number'];
$adminPageHeader = 'Order Fulfillment Concierge';
$adminPageSub    = 'Complete order lifecycle oversight, status transitions, tracking assignment & invoice generation.';

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';
require_once __DIR__ . '/includes/navbar.php';
?>

<!-- Action Bar -->
<div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-3">
    <div>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-1" style="font-size:0.82rem;">
                <li class="breadcrumb-item"><a href="<?= ADMIN_URL ?>/orders.php">Orders</a></li>
                <li class="breadcrumb-item active"><?= e($order['order_number']) ?></li>
            </ol>
        </nav>
        <h4 class="font-serif fw-bold mb-0">
            Order <?= e($order['order_number']) ?>
            <?= get_order_status_badge($order['order_status']) ?>
        </h4>
        <div class="small text-muted">
            Placed on <?= format_date($order['created_at']) ?> &bull; Payment: 
            <span class="badge <?= $order['payment_status'] === 'Paid' ? 'bg-success' : 'bg-warning text-dark' ?>">
                <?= e($order['payment_status']) ?> (<?= e($order['payment_method']) ?>)
            </span>
        </div>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= ADMIN_URL ?>/invoice.php?id=<?= $orderId ?>" target="_blank" class="btn btn-sm btn-outline-dark" style="border-color:var(--border-beige);">
            <i class="bi bi-printer me-1"></i> Print Invoice
        </a>
        <a href="<?= ADMIN_URL ?>/orders.php" class="btn btn-sm btn-dark">
            &larr; Back to Orders
        </a>
    </div>
</div>

<div class="row g-4">
    <!-- Left Column: Order Items, Financials & Audit History -->
    <div class="col-lg-8">
        
        <!-- Ordered Products Table -->
        <div class="card border-0 shadow-sm mb-4" style="border:1px solid var(--border-beige) !important;">
            <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center" style="border-color:var(--border-beige) !important;">
                <h5 class="font-serif fw-bold mb-0" style="color:var(--caramel-dark);">
                    <i class="bi bi-bag-check me-1"></i> Ordered Timepieces (<?= count($orderItems) ?>)
                </h5>
                <span class="badge bg-light text-dark border">Vault Verification</span>
            </div>
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead style="background-color:var(--bg-cream); font-size:0.75rem; text-transform:uppercase;">
                        <tr>
                            <th class="ps-3 py-2">Timepiece</th>
                            <th class="text-center py-2">Unit Price</th>
                            <th class="text-center py-2">Quantity</th>
                            <th class="text-end pe-3 py-2">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($orderItems as $item): 
                            $imgSrc = !empty($item['primary_image']) ? BASE_URL . '/' . ltrim($item['primary_image'], '/') : BASE_URL . '/assets/images/watch-mens-chronograph.jpg';
                        ?>
                            <tr>
                                <td class="ps-3 py-3">
                                    <div class="d-flex align-items-center gap-3">
                                        <img src="<?= e($imgSrc) ?>" alt="<?= e($item['product_name']) ?>" class="rounded border" style="width:60px; height:60px; object-fit:cover; border-color:var(--border-beige) !important;">
                                        <div>
                                            <div class="fw-bold font-serif fs-6"><?= e($item['product_name']) ?></div>
                                            <div class="small text-muted">SKU: <code><?= e($item['product_sku']) ?></code></div>
                                            <?php if (!empty($item['variant_details'])): ?>
                                                <div class="small text-muted"><?= e($item['variant_details']) ?></div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </td>
                                <td class="text-center py-3 fw-semibold">
                                    <?= format_price($item['price_at_purchase']) ?>
                                </td>
                                <td class="text-center py-3">
                                    <span class="badge bg-light text-dark border px-2 py-1"><?= (int)$item['quantity'] ?></span>
                                </td>
                                <td class="text-end pe-3 py-3 fw-bold" style="color:var(--caramel-dark);">
                                    <?= format_price($item['total_price']) ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Financial Summary Footer -->
            <div class="card-footer bg-light p-3 border-top" style="border-color:var(--border-beige) !important;">
                <div class="row justify-content-end">
                    <div class="col-md-6 col-lg-5">
                        <div class="d-flex justify-content-between small text-muted mb-1">
                            <span>Subtotal:</span>
                            <span class="fw-semibold text-dark"><?= format_price($order['subtotal']) ?></span>
                        </div>
                        <?php if ($order['discount_amount'] > 0): ?>
                            <div class="d-flex justify-content-between small text-success mb-1">
                                <span>Discount (<?= e($order['coupon_code'] ?? 'Privilege') ?>):</span>
                                <span class="fw-semibold">-<?= format_price($order['discount_amount']) ?></span>
                            </div>
                        <?php endif; ?>
                        <div class="d-flex justify-content-between small text-muted mb-1">
                            <span>Insured Delivery:</span>
                            <span class="fw-semibold text-dark"><?= $order['shipping_fee'] == 0 ? 'Complimentary' : format_price($order['shipping_fee']) ?></span>
                        </div>
                        <div class="d-flex justify-content-between fs-6 fw-bold pt-2 border-top" style="color:var(--caramel-dark); border-color:var(--border-beige) !important;">
                            <span>Grand Total:</span>
                            <span><?= format_price($order['total_amount']) ?></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Order Lifecycle Audit Trail -->
        <div class="card border-0 shadow-sm" style="border:1px solid var(--border-beige) !important;">
            <div class="card-header bg-white py-3 border-bottom" style="border-color:var(--border-beige) !important;">
                <h5 class="font-serif fw-bold mb-0" style="color:var(--caramel-dark);">
                    <i class="bi bi-clock-history me-1"></i> Order Status Audit Trail
                </h5>
            </div>
            <div class="card-body p-4">
                <div class="timeline ps-3" style="border-left: 2px solid var(--caramel-primary); margin-left: 10px;">
                    <?php foreach ($history as $h): ?>
                        <div class="mb-3 position-relative ps-3">
                            <i class="bi bi-circle-fill position-absolute" style="left:-22px; top:3px; font-size:0.75rem; color:var(--caramel-primary);"></i>
                            <div class="d-flex justify-content-between align-items-center">
                                <span class="fw-bold"><?= get_order_status_badge($h['status']) ?></span>
                                <span class="text-muted small"><?= format_date($h['created_at']) ?></span>
                            </div>
                            <div class="small text-muted mt-1"><?= e($h['comment']) ?></div>
                            <div class="text-muted" style="font-size:0.72rem;">
                                Initiated by: <strong><?= e($h['updated_by'] === 'admin' ? ($h['admin_name'] ?: 'Administrator') : ucfirst($h['updated_by'])) ?></strong>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

    </div>

    <!-- Right Column: Customer Details, Status Actions & Tracking -->
    <div class="col-lg-4">
        
        <!-- 1. Customer & Delivery Destination Card -->
        <div class="card border-0 shadow-sm mb-4" style="border:1px solid var(--border-beige) !important;">
            <div class="card-header bg-white py-3 border-bottom" style="border-color:var(--border-beige) !important;">
                <h6 class="font-serif fw-bold mb-0 text-uppercase small" style="color:var(--caramel-dark);">
                    <i class="bi bi-person me-1"></i> Customer &amp; Shipping
                </h6>
            </div>
            <div class="card-body p-3">
                <div class="mb-3">
                    <label class="small text-muted d-block">Recipient Name</label>
                    <div class="fw-bold"><?= e($order['customer_name']) ?></div>
                </div>
                <div class="mb-3">
                    <label class="small text-muted d-block">Contact Phone</label>
                    <div class="fw-semibold"><a href="tel:<?= e($order['customer_phone']) ?>" class="text-decoration-none text-dark"><i class="bi bi-telephone me-1"></i><?= e($order['customer_phone']) ?></a></div>
                </div>
                <div class="mb-3">
                    <label class="small text-muted d-block">Email Address</label>
                    <div class="small"><a href="mailto:<?= e($order['customer_email']) ?>"><?= e($order['customer_email']) ?></a></div>
                </div>
                <div class="mb-3">
                    <label class="small text-muted d-block">Shipping Address</label>
                    <div class="small"><?= e($order['shipping_street']) ?></div>
                    <div class="small fw-semibold"><?= e($order['shipping_city']) ?> <?= e($order['shipping_postal_code']) ?></div>
                </div>
                <?php if (!empty($order['delivery_instructions'])): ?>
                    <div class="p-2 rounded bg-light border" style="font-size:0.75rem;">
                        <strong>Instructions:</strong> <?= e($order['delivery_instructions']) ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- 2. Update Fulfillment Status Form -->
        <div class="card border-0 shadow-sm mb-4" style="border:1px solid var(--border-beige) !important;">
            <div class="card-header bg-white py-3 border-bottom" style="border-color:var(--border-beige) !important;">
                <h6 class="font-serif fw-bold mb-0 text-uppercase small" style="color:var(--caramel-dark);">
                    <i class="bi bi-arrow-repeat me-1"></i> Update Order Status
                </h6>
            </div>
            <div class="card-body p-3">
                <form method="POST" action="<?= ADMIN_URL ?>/order-details.php?id=<?= $orderId ?>">
                    <?= csrf_field() ?>
                    <input type="hidden" name="admin_action" value="update_order_status">

                    <div class="mb-3">
                        <label class="form-label small fw-bold text-uppercase">Fulfillment Status</label>
                        <select name="order_status" class="form-select form-select-sm" required>
                            <?php foreach ($allStatuses as $st): ?>
                                <option value="<?= e($st) ?>" <?= $order['order_status'] === $st ? 'selected' : '' ?>>
                                    <?= e($st) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold text-uppercase">Audit Trail Note</label>
                        <textarea name="comment" class="form-control form-control-sm" rows="2" placeholder="e.g. Package inspected & dispatched via courier."></textarea>
                    </div>

                    <?php if ($order['payment_status'] === 'Unpaid' && $order['payment_method'] === 'COD'): ?>
                        <div class="form-check mb-3">
                            <input class="form-check-input" type="checkbox" name="mark_paid" value="1" id="mark_paid_check">
                            <label class="form-check-label small" for="mark_paid_check">
                                Mark COD payment as collected / Paid
                            </label>
                        </div>
                    <?php endif; ?>

                    <button type="submit" class="btn btn-sm btn-dark w-100">
                        Save Status Transition
                    </button>
                </form>
            </div>
        </div>

        <!-- 3. Add / Update Courier Tracking -->
        <div class="card border-0 shadow-sm mb-4" style="border:1px solid var(--border-beige) !important;">
            <div class="card-header bg-white py-3 border-bottom" style="border-color:var(--border-beige) !important;">
                <h6 class="font-serif fw-bold mb-0 text-uppercase small" style="color:var(--caramel-dark);">
                    <i class="bi bi-truck me-1"></i> Courier Tracking
                </h6>
            </div>
            <div class="card-body p-3">
                <form method="POST" action="<?= ADMIN_URL ?>/order-details.php?id=<?= $orderId ?>">
                    <?= csrf_field() ?>
                    <input type="hidden" name="admin_action" value="update_tracking">

                    <div class="mb-2">
                        <label class="form-label small fw-bold text-uppercase">Courier Partner</label>
                        <input type="text" name="courier_name" class="form-control form-control-sm" value="<?= e($order['courier_name'] ?: 'BlueEx Express') ?>" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold text-uppercase">Tracking / Consignment #</label>
                        <input type="text" name="tracking_number" class="form-control form-control-sm font-monospace" placeholder="e.g. BX-984021-PK" value="<?= e($order['tracking_number'] ?? '') ?>" required>
                    </div>

                    <?php if (in_array($order['order_status'], ['Pending', 'Confirmed', 'Processing', 'Packed'])): ?>
                        <div class="form-check mb-3">
                            <input class="form-check-input" type="checkbox" name="auto_dispatch" value="1" id="auto_dispatch_check" checked>
                            <label class="form-check-label small" for="auto_dispatch_check">
                                Automatically advance status to <strong>Dispatched</strong>
                            </label>
                        </div>
                    <?php endif; ?>

                    <button type="submit" class="btn btn-sm btn-outline-dark w-100" style="border-color:var(--border-beige);">
                        Update Tracking Info
                    </button>
                </form>
            </div>
        </div>

        <!-- 4. Payment Status Management -->
        <div class="card border-0 shadow-sm" style="border:1px solid var(--border-beige) !important;">
            <div class="card-header bg-white py-3 border-bottom" style="border-color:var(--border-beige) !important;">
                <h6 class="font-serif fw-bold mb-0 text-uppercase small" style="color:var(--caramel-dark);">
                    <i class="bi bi-credit-card me-1"></i> Payment Settlement (COD)
                </h6>
            </div>
            <div class="card-body p-3">
                <form method="POST" action="<?= ADMIN_URL ?>/order-details.php?id=<?= $orderId ?>">
                    <?= csrf_field() ?>
                    <input type="hidden" name="admin_action" value="update_payment_status">

                    <div class="mb-3">
                        <label class="form-label small fw-bold text-uppercase">Payment Status</label>
                        <select name="payment_status" class="form-select form-select-sm">
                            <option value="Unpaid" <?= $order['payment_status'] === 'Unpaid' ? 'selected' : '' ?>>Unpaid (Awaiting Handover)</option>
                            <option value="Paid" <?= $order['payment_status'] === 'Paid' ? 'selected' : '' ?>>Paid (Cash Collected)</option>
                            <option value="Refunded" <?= $order['payment_status'] === 'Refunded' ? 'selected' : '' ?>>Refunded</option>
                            <option value="Failed" <?= $order['payment_status'] === 'Failed' ? 'selected' : '' ?>>Failed</option>
                        </select>
                    </div>

                    <button type="submit" class="btn btn-sm btn-outline-dark w-100" style="border-color:var(--border-beige);">
                        Update Payment Status
                    </button>
                </form>
            </div>
        </div>

    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
