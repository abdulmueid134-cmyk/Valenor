<?php
// =============================================================================
// VALENOR HOROLOGY — ORDER TRACKING & STATUS TIMELINE
// =============================================================================

require_once __DIR__ . '/config/config.php';

$db = getDB();

$orderNumber = sanitize($_GET['order_number'] ?? ($_POST['order_number'] ?? ''));
$order = null;
$history = [];
$items = [];
$error = '';

if (!empty($orderNumber)) {
    $stmt = $db->prepare("SELECT * FROM orders WHERE order_number = ? LIMIT 1");
    $stmt->execute([$orderNumber]);
    $order = $stmt->fetch();

    if ($order) {
        // Fetch order items
        $iStmt = $db->prepare("SELECT * FROM order_items WHERE order_id = ?");
        $iStmt->execute([(int)$order['id']]);
        $items = $iStmt->fetchAll();

        // Fetch status history timeline
        $hStmt = $db->prepare("SELECT * FROM order_status_history WHERE order_id = ? ORDER BY created_at ASC");
        $hStmt->execute([(int)$order['id']]);
        $history = $hStmt->fetchAll();
    } else {
        $error = 'No order found matching reference "' . e($orderNumber) . '". Please check your order ID.';
    }
}

$pageTitle = 'Track Your Timepiece Delivery';
require_once INCLUDES_PATH . '/header.php';
require_once INCLUDES_PATH . '/navbar.php';
?>

<div class="py-4 border-bottom" style="background-color: var(--bg-white); border-color: var(--border-beige) !important;">
    <div class="container">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-1" style="font-size:0.82rem;">
                <li class="breadcrumb-item"><a href="<?= BASE_URL ?>/index.php">Home</a></li>
                <li class="breadcrumb-item active" aria-current="page">Track Order</li>
            </ol>
        </nav>
        <h1 class="h2 mb-0 font-serif fw-bold">Live Order Tracking</h1>
    </div>
</div>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-8">

            <!-- Search Form Card -->
            <div class="card p-4 bg-white border shadow-sm mb-4" style="border-color:var(--border-beige) !important; border-radius:8px;">
                <h4 class="font-serif fw-bold mb-2">Track Your Shipment</h4>
                <p class="text-muted small mb-3">Enter your unique order number provided upon checkout confirmation.</p>

                <form method="GET" action="<?= BASE_URL ?>/track-order.php" class="d-flex gap-2">
                    <input type="text" name="order_number" class="form-control text-uppercase" placeholder="e.g. VAL-20261115-001" value="<?= e($orderNumber) ?>" required>
                    <button type="submit" class="btn-valenor-primary" style="white-space:nowrap; padding:10px 24px;">
                        <i class="bi bi-geo-alt me-1"></i> Track
                    </button>
                </form>
                <div class="mt-2 text-muted small" style="font-size:0.75rem;">
                    Demo order reference to test: <strong>VAL-20261115-001</strong>
                </div>
            </div>

            <?php if (!empty($error)): ?>
                <div class="alert alert-danger py-2 small mb-4"><?= e($error) ?></div>
            <?php endif; ?>

            <?php if ($order): ?>
                <div class="card p-4 p-md-5 bg-white border shadow-sm" style="border-color:var(--border-beige) !important; border-radius:8px;">
                    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 pb-3 border-bottom" style="border-color:var(--border-beige) !important;">
                        <div>
                            <span class="text-uppercase small text-muted" style="letter-spacing:0.1em;">Order Reference</span>
                            <h2 class="font-serif fw-bold mb-0" style="color:var(--caramel-dark);"><?= e($order['order_number']) ?></h2>
                            <span class="text-muted small">Placed on <?= format_date($order['created_at']) ?></span>
                        </div>
                        <div class="mt-2 mt-md-0 text-md-end">
                            <span class="text-uppercase small text-muted d-block mb-1">Status</span>
                            <?= get_order_status_badge($order['order_status']) ?>
                        </div>
                    </div>

                    <!-- Status History Timeline -->
                    <h5 class="font-serif fw-bold mb-3">Delivery Timeline</h5>
                    <div class="timeline ps-3 mb-4" style="border-left: 2px solid var(--caramel-primary); margin-left: 10px;">
                        <?php foreach ($history as $h): ?>
                            <div class="mb-3 position-relative ps-3">
                                <i class="bi bi-circle-fill position-absolute text-primary" style="left:-22px; top:3px; font-size:0.75rem; color:var(--caramel-primary) !important;"></i>
                                <div class="fw-bold fs-6"><?= e($h['status']) ?></div>
                                <div class="text-muted small"><?= e($h['comment']) ?></div>
                                <div class="text-muted" style="font-size:0.72rem;"><?= format_date($h['created_at']) ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- Items Summary -->
                    <h5 class="font-serif fw-bold mb-3">Timepieces in this Order</h5>
                    <div class="table-responsive">
                        <table class="table align-middle" style="border-color:var(--border-beige);">
                            <tbody>
                                <?php foreach ($items as $it): ?>
                                    <tr>
                                        <td>
                                            <div class="fw-bold"><?= e($it['product_name']) ?></div>
                                            <div class="text-muted small">SKU: <?= e($it['product_sku']) ?> &bull; <?= e($it['variant_details'] ?? '') ?></div>
                                        </td>
                                        <td class="text-center text-muted">Qty: <?= (int)$it['quantity'] ?></td>
                                        <td class="text-end fw-bold" style="color:var(--caramel-dark);"><?= format_price($it['total_price']) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <div class="d-flex justify-content-between align-items-center mt-3 pt-3 border-top" style="border-color:var(--border-beige) !important;">
                        <div>
                            <span class="text-muted small">Payment Method:</span>
                            <strong class="ms-1"><?= e($order['payment_method']) ?> (<?= e($order['payment_status']) ?>)</strong>
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
