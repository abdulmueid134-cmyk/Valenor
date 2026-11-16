<?php
// =============================================================================
// VALENOR HOROLOGY — ORDER TRACKING & STATUS TIMELINE (Phase 08)
// 8 Milestone Stepper: Placed, Confirmed, Processing, Packed, Dispatched,
// In Transit, Out for Delivery, Delivered + Courier Tracking Information
// =============================================================================

require_once __DIR__ . '/config/config.php';

$db = getDB();

$orderNumber = sanitize($_GET['order_number'] ?? ($_GET['order'] ?? ($_POST['order_number'] ?? '')));
$order = null;
$history = [];
$items = [];
$error = '';

if (!empty($orderNumber)) {
    $stmt = $db->prepare("SELECT * FROM orders WHERE order_number = ? LIMIT 1");
    $stmt->execute([$orderNumber]);
    $order = $stmt->fetch();

    if ($order) {
        // Fetch order items with primary image
        $iStmt = $db->prepare("
            SELECT oi.*, 
                   (SELECT image_path FROM product_images WHERE product_id = oi.product_id AND is_primary = 1 LIMIT 1) AS primary_image
            FROM order_items oi 
            WHERE oi.order_id = ?
        ");
        $iStmt->execute([(int)$order['id']]);
        $items = $iStmt->fetchAll();

        // Fetch status history timeline
        $hStmt = $db->prepare("SELECT * FROM order_status_history WHERE order_id = ? ORDER BY created_at ASC");
        $hStmt->execute([(int)$order['id']]);
        $history = $hStmt->fetchAll();
    } else {
        $error = 'No order found matching reference "' . e($orderNumber) . '". Please verify your order number.';
    }
}

// 8 Timeline Milestones required by Phase 08
$milestones = [
    'Pending'             => ['title' => 'Order Placed',        'icon' => 'bi-file-earmark-check'],
    'Confirmed'           => ['title' => 'Order Confirmed',     'icon' => 'bi-check2-circle'],
    'Processing'          => ['title' => 'Processing',          'icon' => 'bi-gear'],
    'Packed'              => ['title' => 'Packed',              'icon' => 'bi-box-seam'],
    'Dispatched'          => ['title' => 'Dispatched',          'icon' => 'bi-send'],
    'Shipped / In Transit'=> ['title' => 'In Transit',          'icon' => 'bi-truck'],
    'Out for Delivery'    => ['title' => 'Out for Delivery',    'icon' => 'bi-geo-alt'],
    'Delivered'           => ['title' => 'Delivered',           'icon' => 'bi-house-check']
];

$statusOrder = array_keys($milestones);
$currentStatusIdx = $order ? array_search($order['order_status'], $statusOrder, true) : false;
if ($currentStatusIdx === false) {
    $currentStatusIdx = -1;
}

$pageTitle = 'Track Delivery — VALENOR Horology';
require_once INCLUDES_PATH . '/header.php';
require_once INCLUDES_PATH . '/navbar.php';
?>

<!-- Stepper Progress Styling -->
<style>
.tracking-stepper {
    display: flex;
    justify-content: space-between;
    position: relative;
    margin: 25px 0 20px;
}
.tracking-step {
    position: relative;
    display: flex;
    flex-direction: column;
    align-items: center;
    flex: 1;
    text-align: center;
}
.tracking-step::before {
    position: absolute;
    content: "";
    border-bottom: 2px solid #E8DED2;
    width: 100%;
    top: 18px;
    left: -50%;
    z-index: 1;
}
.tracking-step:first-child::before {
    content: none;
}
.tracking-step .step-node {
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
.tracking-step.done .step-node {
    background: var(--caramel-primary, #B8895B);
    border-color: var(--caramel-primary, #B8895B);
    color: #FFFFFF;
}
.tracking-step.done::before {
    border-color: var(--caramel-primary, #B8895B);
}
.tracking-step.current .step-node {
    background: #FFFFFF;
    border-color: var(--caramel-primary, #B8895B);
    color: var(--caramel-primary, #B8895B);
    box-shadow: 0 0 0 3px rgba(184, 137, 91, 0.25);
}
.tracking-step .step-label {
    font-size: 0.72rem;
    color: #888;
    font-weight: 500;
}
.tracking-step.done .step-label,
.tracking-step.current .step-label {
    color: var(--text-dark, #252525);
    font-weight: 600;
}
@media (max-width: 768px) {
    .tracking-stepper {
        flex-direction: column;
        align-items: flex-start;
        padding-left: 20px;
    }
    .tracking-step {
        flex-direction: row;
        margin-bottom: 12px;
        width: 100%;
    }
    .tracking-step::before {
        left: 17px;
        top: 36px;
        height: 100%;
        width: 2px;
        border-left: 2px solid #E8DED2;
        border-bottom: none;
    }
    .tracking-step .step-node {
        margin-right: 12px;
        margin-bottom: 0;
    }
}
</style>

<div class="py-4 border-bottom" style="background-color: var(--bg-white); border-color: var(--border-beige) !important;">
    <div class="container">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-1" style="font-size:0.82rem;">
                <li class="breadcrumb-item"><a href="<?= BASE_URL ?>/index.php" style="color:var(--text-dark);">Home</a></li>
                <li class="breadcrumb-item active text-muted" aria-current="page">Track Order</li>
            </ol>
        </nav>
        <h1 class="h2 mb-0 font-serif fw-bold" style="color:var(--text-dark);">Vault Delivery Tracker</h1>
    </div>
</div>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-9">

            <!-- Search Form Card -->
            <div class="card p-4 bg-white border shadow-sm mb-4" style="border-color:var(--border-beige) !important; border-radius:8px;">
                <h4 class="font-serif fw-bold mb-1" style="color:var(--caramel-dark);">Track Your Vault Shipment</h4>
                <p class="text-muted small mb-3">Enter your unique order number provided upon checkout confirmation.</p>

                <form method="GET" action="<?= BASE_URL ?>/track-order.php" class="d-flex gap-2">
                    <input type="text" name="order_number" class="form-control text-uppercase font-monospace" placeholder="e.g. VAL-20261116-4083" value="<?= e($orderNumber) ?>" required>
                    <button type="submit" class="btn-valenor-primary" style="white-space:nowrap; padding:10px 24px;">
                        <i class="bi bi-geo-alt me-1"></i> Track Order
                    </button>
                </form>
            </div>

            <?php if (!empty($error)): ?>
                <div class="alert alert-danger py-2 small mb-4 shadow-sm"><?= e($error) ?></div>
            <?php endif; ?>

            <?php if ($order): ?>
                <div class="card p-4 p-md-5 bg-white border shadow-sm" style="border-color:var(--border-beige) !important; border-radius:8px;">
                    
                    <!-- Order Top Banner -->
                    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 pb-3 border-bottom" style="border-color:var(--border-beige) !important;">
                        <div>
                            <span class="text-uppercase small text-muted" style="letter-spacing:0.1em; font-size:0.75rem;">Order Reference</span>
                            <h2 class="font-serif fw-bold mb-0" style="color:var(--caramel-dark);"><?= e($order['order_number']) ?></h2>
                            <span class="text-muted small">Placed on <?= format_date($order['created_at']) ?></span>
                        </div>
                        <div class="mt-2 mt-md-0 text-md-end">
                            <span class="text-uppercase small text-muted d-block mb-1" style="font-size:0.75rem;">Current Status</span>
                            <?= get_order_status_badge($order['order_status']) ?>
                        </div>
                    </div>

                    <!-- 8 Milestone Progress Stepper -->
                    <?php if ($order['order_status'] === 'Cancelled'): ?>
                        <div class="alert alert-danger d-flex align-items-center gap-2 mb-4 p-3 shadow-sm">
                            <i class="bi bi-x-octagon-fill fs-4"></i>
                            <div><strong>This order is Cancelled.</strong> Shipment has been stopped.</div>
                        </div>
                    <?php else: ?>
                        <div class="mb-4">
                            <h5 class="font-serif fw-bold mb-1" style="color:var(--caramel-dark);">
                                <i class="bi bi-box-seam me-1"></i> Delivery Progress Timeline
                            </h5>
                            <div class="tracking-stepper">
                                <?php $sIdx = 0; foreach ($milestones as $k => $m): 
                                    $isDone = ($currentStatusIdx >= $sIdx && $currentStatusIdx !== -1);
                                    $isCurr = ($currentStatusIdx === $sIdx);
                                ?>
                                    <div class="tracking-step <?= $isDone ? 'done' : '' ?> <?= $isCurr ? 'current' : '' ?>">
                                        <div class="step-node">
                                            <?php if ($isDone && !$isCurr): ?>
                                                <i class="bi bi-check-lg"></i>
                                            <?php else: ?>
                                                <i class="bi <?= $m['icon'] ?>"></i>
                                            <?php endif; ?>
                                        </div>
                                        <div class="step-label"><?= e($m['title']) ?></div>
                                    </div>
                                <?php $sIdx++; endforeach; ?>
                            </div>
                        </div>

                        <!-- Courier Partner & Tracking Pill -->
                        <?php if (!empty($order['tracking_number'])): ?>
                            <div class="p-3 rounded border bg-light mb-4 d-flex flex-wrap justify-content-between align-items-center gap-2" style="border-color:var(--border-beige) !important;">
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
                    <?php endif; ?>

                    <!-- Status History Audit Trail -->
                    <h5 class="font-serif fw-bold mb-3" style="color:var(--caramel-dark);">
                        <i class="bi bi-clock-history me-1"></i> Activity Logs
                    </h5>
                    <div class="timeline ps-3 mb-4" style="border-left: 2px solid var(--caramel-primary); margin-left: 10px;">
                        <?php foreach ($history as $h): ?>
                            <div class="mb-3 position-relative ps-3">
                                <i class="bi bi-circle-fill position-absolute" style="left:-22px; top:3px; font-size:0.75rem; color:var(--caramel-primary) !important;"></i>
                                <div class="fw-bold fs-6"><?= e($h['status']) ?></div>
                                <div class="text-muted small"><?= e($h['comment']) ?></div>
                                <div class="text-muted" style="font-size:0.72rem;"><?= format_date($h['created_at']) ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- Items Summary -->
                    <h5 class="font-serif fw-bold mb-3" style="color:var(--caramel-dark);">
                        <i class="bi bi-bag me-1"></i> Timepieces in this Order (<?= count($items) ?>)
                    </h5>
                    <div class="table-responsive mb-4">
                        <table class="table align-middle" style="border-color:var(--border-beige);">
                            <tbody>
                                <?php foreach ($items as $it): 
                                    $img = !empty($it['primary_image']) ? BASE_URL . '/' . ltrim($it['primary_image'], '/') : BASE_URL . '/assets/images/watch-mens-chronograph.jpg';
                                ?>
                                    <tr>
                                        <td style="width:70px;">
                                            <img src="<?= e($img) ?>" alt="<?= e($it['product_name']) ?>" class="rounded border" style="width:54px; height:54px; object-fit:cover; border-color:var(--border-beige) !important;">
                                        </td>
                                        <td>
                                            <div class="fw-bold font-serif fs-6"><?= e($it['product_name']) ?></div>
                                            <div class="text-muted small">Ref: <?= e($it['product_sku']) ?> <?= !empty($it['variant_details']) ? ' &bull; ' . e($it['variant_details']) : '' ?></div>
                                        </td>
                                        <td class="text-center text-muted">Qty: <?= (int)$it['quantity'] ?></td>
                                        <td class="text-end fw-bold" style="color:var(--caramel-dark);"><?= format_price($it['total_price']) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Bottom Details Bar -->
                    <div class="d-flex flex-wrap justify-content-between align-items-center mt-3 pt-3 border-top gap-2" style="border-color:var(--border-beige) !important;">
                        <div>
                            <span class="text-muted small">Payment Method:</span>
                            <strong class="ms-1"><?= e($order['payment_method']) ?> (<?= e($order['payment_status']) ?>)</strong>
                            <span class="mx-2">&bull;</span>
                            <span class="text-muted small">Ship to:</span>
                            <strong class="ms-1"><?= e($order['shipping_city']) ?></strong>
                        </div>
                        <div class="fs-5 fw-bold" style="color:var(--caramel-dark);">
                            Total: <?= format_price($order['total_amount']) ?>
                        </div>
                    </div>

                </div>
            <?php endif; ?>

        </div>
    </div>
</div>

<?php require_once INCLUDES_PATH . '/footer.php'; ?>
