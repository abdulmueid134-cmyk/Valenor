<?php
// =============================================================================
// VALENOR HOROLOGY — ADMIN ORDERS OVERSIGHT (Section 10)
// =============================================================================

require_once __DIR__ . '/../config/config.php';
require_admin();

$db = getDB();

$adminTitle      = 'Manage Orders';
$adminPageHeader = 'Client Order Management';
$adminPageSub    = 'Track order fulfillment, 14 order statuses, courier tracking, and payment verification.';

// Filter parameters
$statusFilter  = sanitize($_GET['status'] ?? '');
$paymentFilter = sanitize($_GET['payment_status'] ?? '');
$search        = sanitize($_GET['q'] ?? '');

$where = ['1=1'];
$params = [];

if (!empty($statusFilter)) {
    $where[] = "o.order_status = ?";
    $params[] = $statusFilter;
}

if (!empty($paymentFilter)) {
    $where[] = "o.payment_status = ?";
    $params[] = $paymentFilter;
}

if (!empty($search)) {
    $where[] = "(o.order_number LIKE ? OR o.customer_name LIKE ? OR o.customer_email LIKE ? OR o.customer_phone LIKE ?)";
    $term = "%{$search}%";
    $params = array_merge($params, [$term, $term, $term, $term]);
}

$whereSql = implode(' AND ', $where);

// Handle status update if submitted via POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    if (verify_csrf()) {
        $orderId   = (int)($_POST['order_id'] ?? 0);
        $newStatus = sanitize($_POST['new_status'] ?? '');
        $notes     = sanitize($_POST['status_notes'] ?? 'Status updated by administrator');

        if ($orderId > 0 && in_array($newStatus, get_order_statuses())) {
            $db->prepare("UPDATE orders SET order_status = ?, updated_at = NOW() WHERE id = ?")->execute([$newStatus, $orderId]);
            $db->prepare("
                INSERT INTO order_status_history (order_id, status, notes, changed_by, created_at)
                VALUES (?, ?, ?, 'admin', NOW())
            ")->execute([$orderId, $newStatus, $notes]);

            set_flash('success', "Order status successfully updated to '{$newStatus}'.");
            header('Location: ' . ADMIN_URL . '/orders.php');
            exit;
        }
    }
}

$stmt = $db->prepare("
    SELECT o.*, 
           (SELECT COUNT(*) FROM order_items WHERE order_id = o.id) AS items_count
    FROM orders o
    WHERE {$whereSql}
    ORDER BY o.id DESC
");
$stmt->execute($params);
$orders = $stmt->fetchAll();

$allStatuses = get_order_statuses();

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';
require_once __DIR__ . '/includes/navbar.php';
?>

<div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-3">
    <div>
        <h4 class="font-serif fw-bold mb-1" style="color:var(--text-charcoal);">Client Orders (<?= count($orders) ?>)</h4>
        <div class="small text-muted">Complete lifecycle management across all 14 fulfillment states.</div>
    </div>
</div>

<!-- Filters -->
<div class="card border-0 shadow-sm mb-4" style="border:1px solid var(--border-beige) !important;">
    <div class="card-body p-3">
        <form method="GET" action="<?= ADMIN_URL ?>/orders.php" class="row g-2 align-items-center">
            <div class="col-md-4">
                <input type="text" name="q" class="form-control form-control-sm" placeholder="Order #, name, phone, email..." value="<?= e($search) ?>">
            </div>
            <div class="col-md-3">
                <select name="status" class="form-select form-select-sm">
                    <option value="">All Fulfillment Statuses</option>
                    <?php foreach ($allStatuses as $st): ?>
                        <option value="<?= e($st) ?>" <?= $statusFilter === $st ? 'selected' : '' ?>><?= e($st) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <select name="payment_status" class="form-select form-select-sm">
                    <option value="">All Payment States</option>
                    <option value="Unpaid" <?= $paymentFilter === 'Unpaid' ? 'selected' : '' ?>>Unpaid (COD)</option>
                    <option value="Paid" <?= $paymentFilter === 'Paid' ? 'selected' : '' ?>>Paid</option>
                    <option value="Refunded" <?= $paymentFilter === 'Refunded' ? 'selected' : '' ?>>Refunded</option>
                </select>
            </div>
            <div class="col-md-2 d-flex gap-1">
                <button type="submit" class="btn btn-sm btn-dark w-100">Filter</button>
                <?php if (!empty($search) || !empty($statusFilter) || !empty($paymentFilter)): ?>
                    <a href="<?= ADMIN_URL ?>/orders.php" class="btn btn-sm btn-outline-secondary"><i class="bi bi-x"></i></a>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<!-- Orders Table -->
<div class="card border-0 shadow-sm admin-table">
    <div class="table-responsive">
        <table class="table mb-0">
            <thead>
                <tr>
                    <th>Order #</th>
                    <th>Customer</th>
                    <th>Total &amp; Payment</th>
                    <th>Shipping City</th>
                    <th>Current Status</th>
                    <th>Date</th>
                    <th style="width:170px;" class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($orders)): ?>
                    <?php foreach ($orders as $ord): ?>
                        <tr>
                            <td>
                                <a href="<?= ADMIN_URL ?>/order-details.php?id=<?= $ord['id'] ?>" class="text-decoration-none fw-bold font-monospace" style="color:var(--caramel-dark);">
                                    <?= e($ord['order_number']) ?>
                                </a>
                                <div class="small text-muted"><?= (int)$ord['items_count'] ?> item(s)</div>
                            </td>
                            <td>
                                <div class="fw-bold"><?= e($ord['customer_name']) ?></div>
                                <small class="text-muted"><?= e($ord['customer_email']) ?></small>
                            </td>
                            <td>
                                <div class="fw-bold"><?= format_price($ord['total_amount']) ?></div>
                                <span class="badge <?= $ord['payment_status'] === 'Paid' ? 'bg-success' : 'bg-warning text-dark' ?>">
                                    <?= e($ord['payment_status']) ?> (<?= e($ord['payment_method']) ?>)
                                </span>
                            </td>
                            <td>
                                <div><?= e($ord['shipping_city']) ?></div>
                                <small class="text-muted"><?= e($ord['customer_phone']) ?></small>
                            </td>
                            <td>
                                <?= get_order_status_badge($ord['order_status']) ?>
                                <?php if (!empty($ord['tracking_number'])): ?>
                                    <div class="small text-muted" style="font-size:0.72rem;"><code><?= e($ord['tracking_number']) ?></code></div>
                                <?php endif; ?>
                            </td>
                            <td class="small text-muted">
                                <?= format_date($ord['created_at'], 'M d, Y') ?><br>
                                <?= format_date($ord['created_at'], 'h:i A') ?>
                            </td>
                            <td class="text-end">
                                <a href="<?= ADMIN_URL ?>/order-details.php?id=<?= $ord['id'] ?>" class="btn btn-sm btn-dark p-1 px-2" title="View Order Details">
                                    <i class="bi bi-eye"></i>
                                </a>
                                <a href="<?= ADMIN_URL ?>/invoice.php?id=<?= $ord['id'] ?>" target="_blank" class="btn btn-sm btn-outline-secondary p-1 px-2" title="Print Invoice">
                                    <i class="bi bi-printer"></i>
                                </a>
                                <button type="button" class="btn btn-sm btn-outline-dark p-1 px-2" data-bs-toggle="modal" data-bs-target="#statusModal<?= $ord['id'] ?>" title="Quick Status Update">
                                    <i class="bi bi-arrow-repeat"></i>
                                </button>

                                <!-- Status Update Modal -->
                                <div class="modal fade" id="statusModal<?= $ord['id'] ?>" tabindex="-1" aria-labelledby="statusModalLabel<?= $ord['id'] ?>" aria-hidden="true">
                                    <div class="modal-dialog modal-dialog-centered text-start">
                                        <div class="modal-content" style="border-color:var(--border-beige);">
                                            <form method="POST" action="<?= ADMIN_URL ?>/orders.php">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="update_status" value="1">
                                                <input type="hidden" name="order_id" value="<?= $ord['id'] ?>">

                                                <div class="modal-header">
                                                    <h5 class="modal-title font-serif fw-bold" id="statusModalLabel<?= $ord['id'] ?>">
                                                        Order <?= e($ord['order_number']) ?> Status
                                                    </h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                </div>
                                                <div class="modal-body p-4">
                                                    <div class="mb-3">
                                                        <label class="form-label small fw-bold text-uppercase">Select New Status</label>
                                                        <select name="new_status" class="form-select">
                                                            <?php foreach ($allStatuses as $st): ?>
                                                                <option value="<?= e($st) ?>" <?= $ord['order_status'] === $st ? 'selected' : '' ?>><?= e($st) ?></option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                    </div>

                                                    <div class="mb-3">
                                                        <label class="form-label small fw-bold text-uppercase">Lifecycle Event Notes</label>
                                                        <textarea name="status_notes" class="form-control" rows="2" placeholder="e.g. Dispatched via BlueEx Courier tracking #BX-9021..."></textarea>
                                                    </div>
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                    <button type="submit" class="btn btn-sm btn-caramel">Save Status Update</button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="7" class="text-center py-5 text-muted">No orders match the selected criteria.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
