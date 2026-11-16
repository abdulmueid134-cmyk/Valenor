<?php
// =============================================================================
// VALENOR HOROLOGY — ORDER CONFIRMATION / SUCCESS (Phase 07)
// =============================================================================

require_once __DIR__ . '/config/config.php';

$orderNumber = sanitize($_GET['order'] ?? '');
if (empty($orderNumber)) {
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

$db = getDB();
$stmt = $db->prepare("
    SELECT o.*, 
           (SELECT COUNT(*) FROM order_items WHERE order_id = o.id) AS items_count
    FROM orders o 
    WHERE o.order_number = ?
    LIMIT 1
");
$stmt->execute([$orderNumber]);
$order = $stmt->fetch();

if (!$order) {
    set_flash('danger', 'Order not found.');
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

// Fetch items
$itemsStmt = $db->prepare("SELECT * FROM order_items WHERE order_id = ?");
$itemsStmt->execute([$order['id']]);
$items = $itemsStmt->fetchAll();

$pageTitle = 'Order Confirmed — ' . $order['order_number'];
require_once INCLUDES_PATH . '/header.php';
require_once INCLUDES_PATH . '/navbar.php';
?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="card p-4 p-md-5 bg-white border shadow-sm text-center" style="border-color:var(--border-beige) !important; border-radius:8px;">
                
                <div class="mb-3">
                    <span class="d-inline-flex align-items-center justify-content-center rounded-circle" style="width:80px; height:80px; background-color:var(--bg-cream); border:1px solid var(--border-beige);">
                        <i class="bi bi-check-lg fs-1 text-success"></i>
                    </span>
                </div>

                <span class="text-uppercase small fw-bold" style="color:var(--caramel-primary); letter-spacing:0.18em;">Order Confirmed</span>
                <h1 class="font-serif fw-bold h2 mt-1 mb-2">Thank You for Your Patronage</h1>
                <p class="text-muted mb-4" style="max-width:540px; margin:0 auto;">
                    Your acquisition has been safely entered into our vault order queue. Our concierge team is preparing your timepiece for insured courier dispatch.
                </p>

                <!-- Order Reference Number Pill -->
                <div class="p-3 bg-light rounded border mb-4 d-inline-block mx-auto text-start" style="border-color:var(--border-beige) !important; min-width:320px;">
                    <div class="d-flex justify-content-between align-items-center gap-3">
                        <div>
                            <span class="small text-muted d-block" style="font-size:0.75rem;">ORDER REFERENCE</span>
                            <span class="font-monospace fw-bold fs-6" style="color:var(--caramel-dark);"><?= e($order['order_number']) ?></span>
                        </div>
                        <span class="badge bg-success-subtle text-success py-1 px-2">
                            <?= e($order['order_status']) ?>
                        </span>
                    </div>
                </div>

                <!-- Order Details Grid -->
                <div class="row g-3 text-start mb-4">
                    <div class="col-sm-6">
                        <div class="p-3 border rounded bg-white" style="border-color:var(--border-beige) !important;">
                            <h6 class="small fw-bold text-uppercase text-muted mb-2">Delivery Destination</h6>
                            <div class="fw-bold"><?= e($order['customer_name']) ?></div>
                            <div class="small text-muted"><?= e($order['shipping_street']) ?></div>
                            <div class="small text-muted"><?= e($order['shipping_city']) ?> <?= e($order['shipping_postal_code']) ?></div>
                            <div class="small text-muted mt-1"><i class="bi bi-telephone me-1"></i><?= e($order['customer_phone']) ?></div>
                        </div>
                    </div>

                    <div class="col-sm-6">
                        <div class="p-3 border rounded bg-white" style="border-color:var(--border-beige) !important;">
                            <h6 class="small fw-bold text-uppercase text-muted mb-2">Payment Breakdown</h6>
                            <div class="d-flex justify-content-between small text-muted mb-1">
                                <span>Method:</span>
                                <span class="fw-semibold text-dark">Cash on Delivery (COD)</span>
                            </div>
                            <div class="d-flex justify-content-between small text-muted mb-1">
                                <span>Subtotal:</span>
                                <span><?= format_price($order['subtotal']) ?></span>
                            </div>
                            <?php if ($order['discount_amount'] > 0): ?>
                                <div class="d-flex justify-content-between small text-success mb-1">
                                    <span>Discount:</span>
                                    <span>-<?= format_price($order['discount_amount']) ?></span>
                                </div>
                            <?php endif; ?>
                            <div class="d-flex justify-content-between small text-muted mb-1">
                                <span>Insured Shipping:</span>
                                <span><?= $order['shipping_fee'] == 0 ? 'Complimentary' : format_price($order['shipping_fee']) ?></span>
                            </div>
                            <div class="d-flex justify-content-between fw-bold pt-2 border-top" style="color:var(--caramel-dark);">
                                <span>Total Payable:</span>
                                <span><?= format_price($order['total_amount']) ?></span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Itemized Summary -->
                <div class="text-start mb-4">
                    <h6 class="small fw-bold text-uppercase text-muted mb-2">Timepieces Ordered</h6>
                    <div class="list-group">
                        <?php foreach ($items as $it): ?>
                            <div class="list-group-item d-flex justify-content-between align-items-center" style="border-color:var(--border-beige);">
                                <div>
                                    <div class="fw-bold small"><?= e($it['product_name']) ?></div>
                                    <div class="text-muted small">Ref: <?= e($it['product_sku']) ?> &bull; Qty: <?= (int)$it['quantity'] ?></div>
                                </div>
                                <span class="fw-bold" style="color:var(--caramel-dark);"><?= format_price($it['total_price']) ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Action Buttons -->
                <div class="d-flex flex-wrap justify-content-center gap-3">
                    <a href="<?= BASE_URL ?>/track-order.php?order=<?= urlencode($order['order_number']) ?>" class="btn btn-valenor-primary px-4 py-2">
                        <i class="bi bi-geo-alt me-1"></i> Track Vault Dispatch &rarr;
                    </a>
                    <a href="<?= BASE_URL ?>/products.php" class="btn btn-outline-dark px-4 py-2" style="border-color:var(--border-beige);">
                        Continue Exploring
                    </a>
                </div>

            </div>
        </div>
    </div>
</div>

<?php require_once INCLUDES_PATH . '/footer.php'; ?>
