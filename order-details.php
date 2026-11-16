<?php
// =============================================================================
// VALENOR HOROLOGY — CUSTOMER ORDER DETAILS & CANCELLATION (Phase 08)
// View ordered items, tracking progress timeline, COD status, and cancel if eligible
// =============================================================================

require_once __DIR__ . '/config/config.php';
require_login('my-orders.php');

$db = getDB();
$customerId = current_user_id();

$orderNumber = sanitize($_GET['order'] ?? ($_GET['order_number'] ?? ''));
$orderId = (int)($_GET['id'] ?? 0);

if ($orderId > 0) {
    $stmt = $db->prepare("SELECT * FROM orders WHERE id = ? AND customer_id = ? LIMIT 1");
    $stmt->execute([$orderId, $customerId]);
} elseif (!empty($orderNumber)) {
    $stmt = $db->prepare("SELECT * FROM orders WHERE order_number = ? AND customer_id = ? LIMIT 1");
    $stmt->execute([$orderNumber, $customerId]);
} else {
    set_flash('danger', 'Order reference required.');
    header('Location: ' . BASE_URL . '/my-orders.php');
    exit;
}

$order = $stmt->fetch();
if (!$order) {
    set_flash('danger', 'Order not found in your account history.');
    header('Location: ' . BASE_URL . '/my-orders.php');
    exit;
}

// Handle Order Cancellation Request by Customer (Eligible if Pending or Confirmed)
$eligibleForCancel = in_array($order['order_status'], ['Pending', 'Confirmed'], true);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cancel_order'])) {
    if (!verify_csrf()) {
        set_flash('danger', 'Security session expired.');
    } elseif (!$eligibleForCancel) {
        set_flash('danger', 'This order is already being processed or dispatched and cannot be cancelled online. Please contact concierge support.');
    } else {
        $cancelReason = sanitize($_POST['cancel_reason'] ?? 'Cancelled by client.');

        // Restore stock
        $iStmt = $db->prepare("SELECT product_id, quantity FROM order_items WHERE order_id = ?");
        $iStmt->execute([(int)$order['id']]);
        $itemsToRestore = $iStmt->fetchAll();
        foreach ($itemsToRestore as $rItem) {
            if (!empty($rItem['product_id'])) {
                $db->prepare("UPDATE products SET stock_quantity = stock_quantity + ? WHERE id = ?")
                   ->execute([(int)$rItem['quantity'], (int)$rItem['product_id']]);
            }
        }

        // Update status
        $db->prepare("UPDATE orders SET order_status = 'Cancelled', updated_at = NOW() WHERE id = ?")
           ->execute([(int)$order['id']]);

        // Audit log
        $db->prepare("
            INSERT INTO order_status_history (order_id, status, comment, updated_by, created_at)
            VALUES (?, 'Cancelled', ?, 'customer', NOW())
        ")->execute([(int)$order['id'], "Cancelled by client: {$cancelReason}"]);

        set_flash('info', "Your order #{$order['order_number']} has been successfully cancelled.");
        header('Location: ' . BASE_URL . '/order-details.php?order=' . urlencode($order['order_number']));
        exit;
    }
}

// Fetch Items & History
$itemsStmt = $db->prepare("
    SELECT oi.*, 
           (SELECT image_path FROM product_images WHERE product_id = oi.product_id AND is_primary = 1 LIMIT 1) AS primary_image
    FROM order_items oi 
    WHERE oi.order_id = ?
");
$itemsStmt->execute([(int)$order['id']]);
$items = $itemsStmt->fetchAll();

$historyStmt = $db->prepare("SELECT * FROM order_status_history WHERE order_id = ? ORDER BY created_at ASC");
$historyStmt->execute([(int)$order['id']]);
$history = $historyStmt->fetchAll();

// 8 Timeline Milestones
$milestones = [
    'Pending'             => ['title' => 'Order Placed',      'icon' => 'bi-file-earmark-check'],
    'Confirmed'           => ['title' => 'Order Confirmed',   'icon' => 'bi-check2-circle'],
    'Processing'          => ['title' => 'Processing in Vault', 'icon' => 'bi-gear'],
    'Packed'              => ['title' => 'Packed for Courier', 'icon' => 'bi-box-seam'],
    'Dispatched'          => ['title' => 'Dispatched',        'icon' => 'bi-send'],
    'Shipped / In Transit'=> ['title' => 'In Transit',        'icon' => 'bi-truck'],
    'Out for Delivery'    => ['title' => 'Out for Delivery',  'icon' => 'bi-geo-alt'],
    'Delivered'           => ['title' => 'Delivered',         'icon' => 'bi-house-check']
];

$statusOrder = array_keys($milestones);
$currentStatusIdx = array_search($order['order_status'], $statusOrder, true);
if ($currentStatusIdx === false) {
    $currentStatusIdx = -1; // e.g. Cancelled
}

$pageTitle = 'Order Details #' . $order['order_number'];
require_once INCLUDES_PATH . '/header.php';
require_once INCLUDES_PATH . '/navbar.php';
?>

<!-- Custom Timeline Styling -->
<style>
.stepper-wrapper {
    display: flex;
    justify-content: space-between;
    position: relative;
    margin-bottom: 2rem;
}
.stepper-item {
    position: relative;
    display: flex;
    flex-direction: column;
    align-items: center;
    flex: 1;
    text-align: center;
}
.stepper-item::before {
    position: absolute;
    content: "";
    border-bottom: 2px solid #E8DED2;
    width: 100%;
    top: 18px;
    left: -50%;
    z-index: 1;
}
.stepper-item:first-child::before {
    content: none;
}
.stepper-item .step-counter {
    position: relative;
    z-index: 2;
    display: flex;
    justify-content: center;
    align-items: center;
    width: 36px;
    height: 36px;
    border-radius: 50%;
    background: #FFFFFF;
    border: 2px solid #E8DED2;
    color: #888;
    font-size: 0.85rem;
    font-weight: 600;
    margin-bottom: 6px;
}
.stepper-item.completed .step-counter {
    background: var(--caramel-primary, #B8895B);
    border-color: var(--caramel-primary, #B8895B);
    color: #FFFFFF;
}
.stepper-item.completed::before {
    border-color: var(--caramel-primary, #B8895B);
}
.stepper-item.active .step-counter {
    background: #FFFFFF;
    border-color: var(--caramel-primary, #B8895B);
    color: var(--caramel-primary, #B8895B);
    box-shadow: 0 0 0 3px rgba(184, 137, 91, 0.2);
}
.stepper-item .step-name {
    font-size: 0.72rem;
    color: #777;
    font-weight: 500;
}
.stepper-item.completed .step-name,
.stepper-item.active .step-name {
    color: var(--text-dark, #252525);
    font-weight: 600;
}
@media (max-width: 768px) {
    .stepper-wrapper {
        flex-direction: column;
        align-items: flex-start;
        padding-left: 20px;
    }
    .stepper-item {
        flex-direction: row;
        margin-bottom: 12px;
        width: 100%;
    }
    .stepper-item::before {
        left: 17px;
        top: 36px;
        height: 100%;
        width: 2px;
        border-left: 2px solid #E8DED2;
        border-bottom: none;
    }
    .stepper-item .step-counter {
        margin-right: 12px;
        margin-bottom: 0;
    }
}
</style>

<div class="py-4 border-bottom" style="background-color: var(--bg-white); border-color: var(--border-beige) !important;">
    <div class="container d-flex flex-wrap justify-content-between align-items-center gap-3">
        <div>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-1" style="font-size:0.82rem;">
                    <li class="breadcrumb-item"><a href="<?= BASE_URL ?>/index.php" style="color:var(--text-dark);">Home</a></li>
                    <li class="breadcrumb-item"><a href="<?= BASE_URL ?>/my-orders.php" style="color:var(--text-dark);">My Orders</a></li>
                    <li class="breadcrumb-item active text-muted" aria-current="page"><?= e($order['order_number']) ?></li>
                </ol>
            </nav>
            <h1 class="h2 mb-0 font-serif fw-bold" style="color:var(--text-dark);">
                Order <?= e($order['order_number']) ?>
            </h1>
        </div>
        <div class="d-flex align-items-center gap-2">
            <a href="<?= BASE_URL ?>/admin/invoice.php?order=<?= urlencode($order['order_number']) ?>" target="_blank" class="btn btn-sm btn-outline-dark" style="border-color:var(--border-beige);">
                <i class="bi bi-printer me-1"></i> Print Receipt
            </a>
            <a href="<?= BASE_URL ?>/track-order.php?order_number=<?= urlencode($order['order_number']) ?>" class="btn btn-sm btn-valenor-primary">
                <i class="bi bi-geo-alt me-1"></i> Live Tracker
            </a>
        </div>
    </div>
</div>

<div class="container py-5">
    
    <!-- Cancellation Notice if Cancelled -->
    <?php if ($order['order_status'] === 'Cancelled'): ?>
        <div class="alert alert-danger d-flex align-items-center gap-2 mb-4 p-3 shadow-sm" role="alert">
            <i class="bi bi-x-circle-fill fs-4"></i>
            <div>
                <strong>This order has been cancelled.</strong> Inventory holds have been returned to the vault.
            </div>
        </div>
    <?php else: ?>
        <!-- Professional Tracking Stepper (8 Milestones) -->
        <div class="card p-4 p-md-5 bg-white border shadow-sm mb-4" style="border-color:var(--border-beige) !important; border-radius:8px;">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h5 class="font-serif fw-bold mb-0" style="color:var(--caramel-dark);">
                    <i class="bi bi-truck me-1"></i> Delivery Progress Timeline
                </h5>
                <span class="badge" style="background:var(--caramel-dark); font-size:0.75rem;">
                    Current: <?= e($order['order_status']) ?>
                </span>
            </div>

            <div class="stepper-wrapper">
                <?php $stepIdx = 0; foreach ($milestones as $key => $meta): 
                    $isCompleted = ($currentStatusIdx >= $stepIdx && $currentStatusIdx !== -1);
                    $isActive = ($currentStatusIdx === $stepIdx);
                ?>
                    <div class="stepper-item <?= $isCompleted ? 'completed' : '' ?> <?= $isActive ? 'active' : '' ?>">
                        <div class="step-counter">
                            <?php if ($isCompleted && !$isActive): ?>
                                <i class="bi bi-check-lg"></i>
                            <?php else: ?>
                                <i class="bi <?= $meta['icon'] ?>"></i>
                            <?php endif; ?>
                        </div>
                        <div class="step-name"><?= e($meta['title']) ?></div>
                    </div>
                <?php $stepIdx++; endforeach; ?>
            </div>

            <?php if (!empty($order['tracking_number'])): ?>
                <div class="p-3 rounded border bg-light mt-3 d-flex flex-wrap justify-content-between align-items-center gap-2" style="border-color:var(--border-beige) !important;">
                    <div>
                        <span class="small text-muted text-uppercase fw-bold" style="font-size:0.7rem;">Courier Partner</span>
                        <div class="fw-bold fs-6"><?= e($order['courier_name'] ?: 'BlueEx Express') ?></div>
                    </div>
                    <div>
                        <span class="small text-muted text-uppercase fw-bold" style="font-size:0.7rem;">Tracking / Consignment #</span>
                        <div class="font-monospace fw-bold fs-6 text-primary"><?= e($order['tracking_number']) ?></div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <div class="row g-4">
        <!-- Left: Ordered Products -->
        <div class="col-lg-8">
            <div class="card bg-white border shadow-sm mb-4" style="border-color:var(--border-beige) !important; border-radius:8px;">
                <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center" style="border-color:var(--border-beige) !important;">
                    <h5 class="font-serif fw-bold mb-0" style="color:var(--caramel-dark);">
                        <i class="bi bi-bag-check me-1"></i> Ordered Timepieces (<?= count($items) ?>)
                    </h5>
                    <span class="text-muted small">Placed <?= format_date($order['created_at']) ?></span>
                </div>
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead style="background-color:var(--bg-cream); font-size:0.75rem; text-transform:uppercase;">
                            <tr>
                                <th class="ps-3 py-2">Timepiece</th>
                                <th class="text-center py-2">Unit Price</th>
                                <th class="text-center py-2">Qty</th>
                                <th class="text-end pe-3 py-2">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($items as $it): 
                                $imgSrc = !empty($it['primary_image']) ? BASE_URL . '/' . ltrim($it['primary_image'], '/') : BASE_URL . '/assets/images/watch-mens-chronograph.jpg';
                            ?>
                                <tr>
                                    <td class="ps-3 py-3">
                                        <div class="d-flex align-items-center gap-3">
                                            <img src="<?= e($imgSrc) ?>" alt="<?= e($it['product_name']) ?>" class="rounded border" style="width:64px; height:64px; object-fit:cover; border-color:var(--border-beige) !important;">
                                            <div>
                                                <div class="fw-bold font-serif fs-6"><?= e($it['product_name']) ?></div>
                                                <div class="small text-muted">SKU: <code><?= e($it['product_sku']) ?></code></div>
                                                <?php if (!empty($it['variant_details'])): ?>
                                                    <div class="small text-muted"><?= e($it['variant_details']) ?></div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="text-center py-3 fw-semibold">
                                        <?= format_price($it['price_at_purchase']) ?>
                                    </td>
                                    <td class="text-center py-3">
                                        <span class="badge bg-light text-dark border"><?= (int)$it['quantity'] ?></span>
                                    </td>
                                    <td class="text-end pe-3 py-3 fw-bold" style="color:var(--caramel-dark);">
                                        <?= format_price($it['total_price']) ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Financial Breakdown -->
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
                                <span>Insured Shipping:</span>
                                <span class="fw-semibold text-dark"><?= $order['shipping_fee'] == 0 ? 'Complimentary' : format_price($order['shipping_fee']) ?></span>
                            </div>
                            <div class="d-flex justify-content-between fs-6 fw-bold pt-2 border-top" style="color:var(--caramel-dark); border-color:var(--border-beige) !important;">
                                <span>Total Payable (COD):</span>
                                <span><?= format_price($order['total_amount']) ?></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Cancellation Option if Eligible -->
            <?php if ($eligibleForCancel): ?>
                <div class="card p-4 bg-white border border-danger-subtle shadow-sm mb-4" style="border-radius:8px;">
                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <div>
                            <h6 class="font-serif fw-bold text-danger mb-1"><i class="bi bi-x-octagon me-1"></i> Order Cancellation Privilege</h6>
                            <p class="small text-muted mb-0">Your order is currently awaiting vault processing. You may request immediate cancellation.</p>
                        </div>
                        <button type="button" class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#cancelModal">
                            Cancel Order
                        </button>
                    </div>
                </div>

                <!-- Cancel Modal -->
                <div class="modal fade" id="cancelModal" tabindex="-1" aria-labelledby="cancelModalLabel" aria-hidden="true">
                    <div class="modal-dialog modal-dialog-centered">
                        <div class="modal-content">
                            <form method="POST" action="<?= BASE_URL ?>/order-details.php?order=<?= urlencode($order['order_number']) ?>">
                                <?= csrf_field() ?>
                                <input type="hidden" name="cancel_order" value="1">

                                <div class="modal-header">
                                    <h5 class="modal-title font-serif fw-bold text-danger" id="cancelModalLabel">Cancel Order <?= e($order['order_number']) ?></h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                </div>
                                <div class="modal-body p-4">
                                    <p class="small text-muted mb-3">Please let our concierge know why you are cancelling this order:</p>
                                    <textarea name="cancel_reason" class="form-control form-control-sm" rows="3" placeholder="e.g. Changed selection, ordered another timepiece..." required></textarea>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Keep Order</button>
                                    <button type="submit" class="btn btn-sm btn-danger">Confirm Cancellation</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

        </div>

        <!-- Right: Delivery Details & Payment Info -->
        <div class="col-lg-4">
            
            <!-- Delivery Destination Card -->
            <div class="card p-4 bg-white border shadow-sm mb-4" style="border-color:var(--border-beige) !important; border-radius:8px;">
                <h6 class="font-serif fw-bold mb-3 pb-2 border-bottom" style="color:var(--caramel-dark); border-color:var(--border-beige) !important;">
                    <i class="bi bi-geo-alt me-1"></i> Delivery Information
                </h6>
                <div class="mb-2">
                    <label class="small text-muted d-block">Recipient</label>
                    <div class="fw-bold"><?= e($order['customer_name']) ?></div>
                </div>
                <div class="mb-2">
                    <label class="small text-muted d-block">Phone Number</label>
                    <div class="fw-semibold"><i class="bi bi-telephone me-1"></i><?= e($order['customer_phone']) ?></div>
                </div>
                <div class="mb-2">
                    <label class="small text-muted d-block">Email</label>
                    <div class="small"><i class="bi bi-envelope me-1"></i><?= e($order['customer_email']) ?></div>
                </div>
                <div class="mb-2">
                    <label class="small text-muted d-block">Delivery Address</label>
                    <div class="small"><?= e($order['shipping_street']) ?></div>
                    <div class="small fw-semibold"><?= e($order['shipping_city']) ?> <?= e($order['shipping_postal_code']) ?></div>
                </div>
                <?php if (!empty($order['delivery_instructions'])): ?>
                    <div class="p-2 rounded bg-light border mt-2" style="font-size:0.75rem;">
                        <strong>Instructions:</strong> <?= e($order['delivery_instructions']) ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Payment & Security Card -->
            <div class="card p-4 bg-white border shadow-sm" style="border-color:var(--border-beige) !important; border-radius:8px;">
                <h6 class="font-serif fw-bold mb-3 pb-2 border-bottom" style="color:var(--caramel-dark); border-color:var(--border-beige) !important;">
                    <i class="bi bi-shield-lock me-1"></i> Payment Method
                </h6>
                <div class="p-3 rounded border mb-3" style="background-color:var(--bg-cream); border-color:var(--border-beige) !important;">
                    <div class="fw-bold mb-1" style="color:var(--caramel-dark);">
                        Cash on Delivery (COD)
                    </div>
                    <div class="small text-muted">
                        Payment Status: 
                        <span class="badge <?= $order['payment_status'] === 'Paid' ? 'bg-success' : 'bg-warning text-dark' ?>">
                            <?= e($order['payment_status']) ?>
                        </span>
                    </div>
                </div>
                <div class="small text-muted" style="font-size:0.78rem; line-height:1.4;">
                    <i class="bi bi-info-circle me-1"></i> Please prepare exact cash or use mobile payment upon courier physical doorstep delivery.
                </div>
            </div>

        </div>
    </div>
</div>

<?php require_once INCLUDES_PATH . '/footer.php'; ?>
