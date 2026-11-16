<?php
// =============================================================================
// VALENOR HOROLOGY — CUSTOMER ORDER HISTORY (Section 10 & 14)
// =============================================================================

require_once __DIR__ . '/config/config.php';
require_login('my-orders.php');

$db = getDB();
$customerId = current_user_id();

$stmt = $db->prepare("
    SELECT o.*,
           (SELECT COUNT(*) FROM order_items WHERE order_id = o.id) AS items_count
    FROM orders o
    WHERE o.customer_id = ?
    ORDER BY o.id DESC
");
$stmt->execute([$customerId]);
$orders = $stmt->fetchAll();

$pageTitle = 'My Order History';
require_once INCLUDES_PATH . '/header.php';
require_once INCLUDES_PATH . '/navbar.php';
?>

<div class="py-4 border-bottom" style="background-color: var(--bg-white); border-color: var(--border-beige) !important;">
    <div class="container">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-1" style="font-size:0.82rem;">
                <li class="breadcrumb-item"><a href="<?= BASE_URL ?>/index.php">Home</a></li>
                <li class="breadcrumb-item"><a href="<?= BASE_URL ?>/my-account.php">My Account</a></li>
                <li class="breadcrumb-item active" aria-current="page">Orders</li>
            </ol>
        </nav>
        <h1 class="h2 mb-0 font-serif fw-bold">My Order History</h1>
    </div>
</div>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-10">
            <?php if (!empty($orders)): ?>
                <?php foreach ($orders as $ord): ?>
                    <div class="card p-4 bg-white border shadow-sm mb-3" style="border-color:var(--border-beige) !important; border-radius:8px;">
                        <div class="d-flex flex-wrap justify-content-between align-items-center pb-3 border-bottom mb-3" style="border-color:var(--border-beige) !important;">
                            <div>
                                <span class="text-muted small text-uppercase" style="letter-spacing:0.08em;">Order</span>
                                <h4 class="font-serif fw-bold mb-0">
                                    <a href="<?= BASE_URL ?>/order-details.php?order=<?= urlencode($ord['order_number']) ?>" class="text-decoration-none" style="color:var(--caramel-dark);">
                                        <?= e($ord['order_number']) ?>
                                    </a>
                                </h4>
                                <span class="text-muted small"><?= format_date($ord['created_at']) ?></span>
                            </div>
                            <div class="mt-2 mt-md-0 text-md-end">
                                <span class="d-block mb-1"><?= get_order_status_badge($ord['order_status']) ?></span>
                                <span class="fw-bold" style="color:var(--text-charcoal);"><?= format_price($ord['total_amount']) ?></span>
                            </div>
                        </div>

                        <div class="d-flex flex-wrap justify-content-between align-items-center">
                            <div class="small text-muted">
                                <span><i class="bi bi-box-seam me-1"></i> <?= (int)$ord['items_count'] ?> item(s)</span>
                                <span class="mx-2">&bull;</span>
                                <span><i class="bi bi-geo-alt me-1"></i> Ship to: <?= e($ord['shipping_city']) ?></span>
                                <span class="mx-2">&bull;</span>
                                <span><i class="bi bi-cash me-1"></i> <?= e($ord['payment_method']) ?> (<?= e($ord['payment_status']) ?>)</span>
                            </div>
                            <div class="mt-2 mt-md-0 d-flex gap-2">
                                <a href="<?= BASE_URL ?>/order-details.php?order=<?= urlencode($ord['order_number']) ?>" class="btn btn-sm btn-dark rounded-pill" style="font-size:0.8rem;">
                                    <i class="bi bi-eye me-1"></i> View Details
                                </a>
                                <a href="<?= BASE_URL ?>/track-order.php?order_number=<?= urlencode($ord['order_number']) ?>" class="btn btn-sm btn-outline-dark rounded-pill" style="border-color:var(--border-beige); font-size:0.8rem;">
                                    <i class="bi bi-geo-alt me-1"></i> Track Order
                                </a>
                                <?php if ($ord['order_status'] === 'Delivered'): ?>
                                    <a href="<?= BASE_URL ?>/request-return.php?order=<?= urlencode($ord['order_number']) ?>" class="btn btn-sm btn-outline-secondary rounded-pill" style="font-size:0.8rem;">
                                        Request Return
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="text-center py-5 bg-white rounded border p-5" style="border-color:var(--border-beige) !important;">
                    <i class="bi bi-box-seam fs-1 text-muted mb-3 d-block"></i>
                    <h2 class="font-serif">No Orders Found</h2>
                    <p class="text-muted">You have not placed any orders yet. Discover our premier horology collection to make your first selection.</p>
                    <a href="<?= BASE_URL ?>/products.php" class="btn-valenor-primary mt-2">Discover Timepieces &rarr;</a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once INCLUDES_PATH . '/footer.php'; ?>

