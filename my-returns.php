<?php
// =============================================================================
// VALENOR HOROLOGY — CUSTOMER RETURNS & REFUNDS LIST (Phase 09)
// =============================================================================

require_once __DIR__ . '/config/config.php';
require_login('my-returns.php');

$db = getDB();
$customerId = current_user_id();

$stmt = $db->prepare("
    SELECT rr.*, o.order_number, o.created_at AS order_date, o.total_amount AS order_total
    FROM return_requests rr
    JOIN orders o ON rr.order_id = o.id
    WHERE rr.customer_id = ?
    ORDER BY rr.id DESC
");
$stmt->execute([$customerId]);
$returns = $stmt->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = 'My Returns & Refunds';
require_once INCLUDES_PATH . '/header.php';
require_once INCLUDES_PATH . '/navbar.php';
?>

<div class="py-4 border-bottom" style="background-color: var(--bg-white); border-color: var(--border-beige) !important;">
    <div class="container">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-1" style="font-size:0.82rem;">
                <li class="breadcrumb-item"><a href="<?= BASE_URL ?>/index.php">Home</a></li>
                <li class="breadcrumb-item"><a href="<?= BASE_URL ?>/my-account.php">My Account</a></li>
                <li class="breadcrumb-item active" aria-current="page">Returns &amp; Refunds</li>
            </ol>
        </nav>
        <h1 class="h2 mb-0 font-serif fw-bold">Returns &amp; Refunds</h1>
    </div>
</div>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-10">
            
            <div class="card p-3 mb-4 bg-white border" style="border-color:var(--border-beige) !important; border-radius:8px;">
                <div class="d-flex align-items-center gap-3">
                    <div class="rounded-circle p-3 text-white" style="background:var(--caramel-dark);">
                        <i class="bi bi-shield-check fs-4"></i>
                    </div>
                    <div>
                        <h6 class="font-serif fw-bold mb-1" style="color:var(--text-charcoal);">Valenor After-Sales Guarantee</h6>
                        <p class="small text-muted mb-0">Complimentary <?= (int)get_setting('return_window_days', 14) ?>-day return and exchange privilege for all certified timepieces in mint, unworn condition with complete documentation.</p>
                    </div>
                </div>
            </div>

            <?php if (!empty($returns)): ?>
                <?php foreach ($returns as $ret): ?>
                    <div class="card p-4 bg-white border shadow-sm mb-3" style="border-color:var(--border-beige) !important; border-radius:8px;">
                        <div class="d-flex flex-wrap justify-content-between align-items-center pb-3 border-bottom mb-3" style="border-color:var(--border-beige) !important;">
                            <div>
                                <span class="text-muted small text-uppercase" style="letter-spacing:0.08em;">Return Reference</span>
                                <h4 class="font-serif fw-bold mb-0" style="color:var(--caramel-dark);">
                                    <?= e($ret['return_number']) ?>
                                </h4>
                                <span class="text-muted small">Submitted <?= format_date($ret['created_at']) ?></span>
                            </div>
                            <div class="mt-2 mt-md-0 text-md-end">
                                <span class="d-block mb-1"><?= get_return_status_badge($ret['status']) ?></span>
                                <span class="fw-bold text-dark">Refund: <?= format_price($ret['refund_amount']) ?></span>
                            </div>
                        </div>

                        <div class="row g-2 mb-3">
                            <div class="col-md-6 small">
                                <span class="text-muted">Associated Order:</span>
                                <a href="<?= BASE_URL ?>/order-details.php?order=<?= urlencode($ret['order_number']) ?>" class="fw-bold text-decoration-none">
                                    <?= e($ret['order_number']) ?>
                                </a>
                            </div>
                            <div class="col-md-6 small">
                                <span class="text-muted">Primary Reason:</span>
                                <span class="fw-semibold text-dark"><?= e($ret['reason']) ?></span>
                            </div>
                            <div class="col-12 small mt-2">
                                <span class="text-muted d-block">Description:</span>
                                <div class="p-2 rounded bg-light border mt-1" style="border-color:var(--border-beige) !important;">
                                    <?= nl2br(e($ret['description'])) ?>
                                </div>
                            </div>
                            <?php if ($ret['status'] === 'Return Rejected' && !empty($ret['rejection_reason'])): ?>
                                <div class="col-12 mt-2">
                                    <div class="alert alert-danger mb-0 small">
                                        <strong>Concierge Notice:</strong> <?= e($ret['rejection_reason']) ?>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="d-flex justify-content-between align-items-center pt-2 border-top" style="border-color:var(--border-beige) !important;">
                            <div class="small text-muted">
                                Refund Status: <span class="fw-semibold text-dark"><?= e($ret['refund_status']) ?></span>
                                <?php if (!empty($ret['refunded_at'])): ?>
                                    &bull; Paid on <?= format_date($ret['refunded_at']) ?>
                                <?php endif; ?>
                            </div>
                            <div>
                                <a href="<?= BASE_URL ?>/order-details.php?order=<?= urlencode($ret['order_number']) ?>" class="btn btn-sm btn-dark rounded-pill">
                                    <i class="bi bi-eye me-1"></i> View Order &amp; Return Status
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="text-center py-5 bg-white rounded border p-5" style="border-color:var(--border-beige) !important;">
                    <i class="bi bi-arrow-return-left fs-1 text-muted mb-3 d-block"></i>
                    <h2 class="font-serif">No Return Requests</h2>
                    <p class="text-muted">You have no active or previous return requests on record.</p>
                    <a href="<?= BASE_URL ?>/my-orders.php" class="btn-valenor-primary mt-2">View My Orders &rarr;</a>
                </div>
            <?php endif; ?>

        </div>
    </div>
</div>

<?php require_once INCLUDES_PATH . '/footer.php'; ?>
