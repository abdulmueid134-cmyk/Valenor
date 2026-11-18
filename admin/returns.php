<?php
// =============================================================================
// VALENOR HOROLOGY — ADMIN RETURNS & REFUNDS MANAGEMENT (Phase 09)
// =============================================================================

require_once __DIR__ . '/../config/config.php';
require_admin();

$db = getDB();

$adminTitle      = 'Returns & Refunds';
$adminPageHeader = 'After-Sales & Returns Management';
$adminPageSub    = 'Review return requests, process customer proofs, approve or reject claims, and authorize refunds.';

// Handle POST actions: Status Updates, Approvals, Rejections, Refunds, and Settings
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) {
        set_flash('danger', 'CSRF verification failed.');
        header('Location: ' . ADMIN_URL . '/returns.php');
        exit;
    }

    $action = sanitize($_POST['action'] ?? '');

    // 1. Update Return Settings (Deadline Days & Policy)
    if ($action === 'update_settings') {
        $windowDays = max(1, (int)($_POST['return_window_days'] ?? 14));
        $policyText = sanitize($_POST['return_policy_text'] ?? '');

        $db->prepare("INSERT INTO website_settings (setting_key, setting_value, setting_group) VALUES ('return_window_days', ?, 'returns') ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)")->execute([$windowDays]);
        $db->prepare("INSERT INTO website_settings (setting_key, setting_value, setting_group) VALUES ('return_policy_text', ?, 'returns') ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)")->execute([$policyText]);

        set_flash('success', "Return deadline updated to {$windowDays} days from delivery.");
        header('Location: ' . ADMIN_URL . '/returns.php');
        exit;
    }

    // 2. Approve Return
    if ($action === 'approve_return') {
        $returnId = (int)($_POST['return_id'] ?? 0);
        $notes    = sanitize($_POST['admin_notes'] ?? 'Return authorized by concierge.');

        $stmt = $db->prepare("SELECT * FROM return_requests WHERE id = ? LIMIT 1");
        $stmt->execute([$returnId]);
        $ret = $stmt->fetch();

        if ($ret) {
            $db->prepare("
                UPDATE return_requests SET 
                    status = 'Return Approved',
                    admin_notes = ?,
                    updated_at = NOW()
                WHERE id = ?
            ")->execute([$notes, $returnId]);

            // Update order status
            $db->prepare("UPDATE orders SET order_status = 'Return Approved', updated_at = NOW() WHERE id = ?")->execute([$ret['order_id']]);

            // Order status audit
            $db->prepare("
                INSERT INTO order_status_history (order_id, status, comment, updated_by, admin_id, created_at)
                VALUES (?, 'Return Approved', ?, 'admin', ?, NOW())
            ")->execute([$ret['order_id'], "Return #{$ret['return_number']} approved: {$notes}", $_SESSION['admin_id'] ?? null]);

            set_flash('success', "Return #{$ret['return_number']} approved successfully.");
        }
        header('Location: ' . ADMIN_URL . '/returns.php');
        exit;
    }

    // 3. Reject Return
    if ($action === 'reject_return') {
        $returnId = (int)($_POST['return_id'] ?? 0);
        $rejectionReason = sanitize($_POST['rejection_reason'] ?? '');

        if (empty($rejectionReason)) {
            set_flash('danger', 'A rejection reason is mandatory when rejecting a customer return claim.');
            header('Location: ' . ADMIN_URL . '/returns.php');
            exit;
        }

        $stmt = $db->prepare("SELECT * FROM return_requests WHERE id = ? LIMIT 1");
        $stmt->execute([$returnId]);
        $ret = $stmt->fetch();

        if ($ret) {
            $db->prepare("
                UPDATE return_requests SET 
                    status = 'Return Rejected',
                    rejection_reason = ?,
                    updated_at = NOW()
                WHERE id = ?
            ")->execute([$rejectionReason, $returnId]);

            // Update order status
            $db->prepare("UPDATE orders SET order_status = 'Return Rejected', updated_at = NOW() WHERE id = ?")->execute([$ret['order_id']]);

            // Order status audit
            $db->prepare("
                INSERT INTO order_status_history (order_id, status, comment, updated_by, admin_id, created_at)
                VALUES (?, 'Return Rejected', ?, 'admin', ?, NOW())
            ")->execute([$ret['order_id'], "Return #{$ret['return_number']} rejected: {$rejectionReason}", $_SESSION['admin_id'] ?? null]);

            set_flash('info', "Return #{$ret['return_number']} rejected.");
        }
        header('Location: ' . ADMIN_URL . '/returns.php');
        exit;
    }

    // 4. Update Return Lifecycle Status (Return in Transit, Returned, Refund Processing)
    if ($action === 'update_status') {
        $returnId  = (int)($_POST['return_id'] ?? 0);
        $newStatus = sanitize($_POST['status'] ?? '');
        $allowedStatuses = [
            'Return Requested', 'Return Approved', 'Return Rejected', 
            'Return in Transit', 'Returned', 'Refund Processing', 'Refunded'
        ];

        if ($returnId > 0 && in_array($newStatus, $allowedStatuses, true)) {
            $stmt = $db->prepare("SELECT * FROM return_requests WHERE id = ? LIMIT 1");
            $stmt->execute([$returnId]);
            $ret = $stmt->fetch();

            if ($ret) {
                $db->prepare("UPDATE return_requests SET status = ?, updated_at = NOW() WHERE id = ?")->execute([$newStatus, $returnId]);
                $db->prepare("UPDATE orders SET order_status = ?, updated_at = NOW() WHERE id = ?")->execute([$newStatus, $ret['order_id']]);

                $db->prepare("
                    INSERT INTO order_status_history (order_id, status, comment, updated_by, admin_id, created_at)
                    VALUES (?, ?, ?, 'admin', ?, NOW())
                ")->execute([$ret['order_id'], $newStatus, "Return status updated to {$newStatus}", $_SESSION['admin_id'] ?? null]);

                // If marked 'Returned', optionally restore stock immediately
                if ($newStatus === 'Returned') {
                    $rItems = $db->prepare("SELECT product_id, quantity FROM return_items WHERE return_request_id = ?");
                    $rItems->execute([$returnId]);
                    foreach ($rItems->fetchAll() as $rit) {
                        if (!empty($rit['product_id'])) {
                            $db->prepare("UPDATE products SET stock_quantity = stock_quantity + ? WHERE id = ?")->execute([(int)$rit['quantity'], (int)$rit['product_id']]);
                        }
                    }
                }

                set_flash('success', "Return #{$ret['return_number']} status progressed to '{$newStatus}'.");
            }
        }
        header('Location: ' . ADMIN_URL . '/returns.php');
        exit;
    }

    // 5. Manage & Authorize Refund
    if ($action === 'process_refund') {
        $returnId     = (int)($_POST['return_id'] ?? 0);
        $refundAmount = (float)($_POST['refund_amount'] ?? 0);
        $refundMethod = sanitize($_POST['refund_method'] ?? 'Bank Transfer / Original Payment');
        $refundNotes  = sanitize($_POST['refund_notes'] ?? 'Authorized by administrator');

        $stmt = $db->prepare("SELECT * FROM return_requests WHERE id = ? LIMIT 1");
        $stmt->execute([$returnId]);
        $ret = $stmt->fetch();

        if (!$ret) {
            set_flash('danger', 'Return request not found.');
        } elseif ($ret['status'] === 'Refunded' || $ret['refund_status'] === 'Refunded') {
            set_flash('warning', 'This return request has already been refunded. Duplicate refunds are prevented.');
        } elseif ($refundAmount <= 0) {
            set_flash('danger', 'Please enter a valid refund amount greater than zero.');
        } else {
            // Fetch associated order
            $oStmt = $db->prepare("SELECT id, order_number, total_amount, payment_status FROM orders WHERE id = ?");
            $oStmt->execute([(int)$ret['order_id']]);
            $order = $oStmt->fetch();

            if (!$order) {
                set_flash('danger', 'Associated order not found.');
            } else {
                // Ensure refund amount does not exceed order total
                if ($refundAmount > (float)$order['total_amount']) {
                    set_flash('danger', 'Refund amount cannot exceed total order amount (' . format_price($order['total_amount']) . ').');
                } else {
                    $db->beginTransaction();
                    try {
                        // 1. Update return_requests record
                        $db->prepare("
                            UPDATE return_requests SET
                                status = 'Refunded',
                                refund_status = 'Refunded',
                                refund_amount = ?,
                                refund_method = ?,
                                refund_notes = ?,
                                refunded_at = NOW(),
                                updated_at = NOW()
                            WHERE id = ?
                        ")->execute([$refundAmount, $refundMethod, $refundNotes, $returnId]);

                        // 2. Update orders payment & order status
                        $db->prepare("
                            UPDATE orders SET 
                                payment_status = 'Refunded',
                                order_status = 'Refunded',
                                updated_at = NOW()
                            WHERE id = ?
                        ")->execute([(int)$order['id']]);

                        // 3. Restore inventory if not already returned
                        if ($ret['status'] !== 'Returned') {
                            $rItems = $db->prepare("SELECT product_id, quantity FROM return_items WHERE return_request_id = ?");
                            $rItems->execute([$returnId]);
                            foreach ($rItems->fetchAll() as $rit) {
                                if (!empty($rit['product_id'])) {
                                    $db->prepare("UPDATE products SET stock_quantity = stock_quantity + ? WHERE id = ?")->execute([(int)$rit['quantity'], (int)$rit['product_id']]);
                                }
                            }
                        }

                        // 4. Record in order_status_history
                        $db->prepare("
                            INSERT INTO order_status_history (order_id, status, comment, updated_by, admin_id, created_at)
                            VALUES (?, 'Refunded', ?, 'admin', ?, NOW())
                        ")->execute([
                            (int)$order['id'],
                            "Refund of " . format_price($refundAmount) . " processed via {$refundMethod}. Ref: Return #{$ret['return_number']}",
                            $_SESSION['admin_id'] ?? null
                        ]);

                        $db->commit();
                        set_flash('success', "Refund of " . format_price($refundAmount) . " successfully authorized and recorded for Return #{$ret['return_number']}.");
                    } catch (Exception $e) {
                        $db->rollBack();
                        set_flash('danger', 'Failed to process refund: ' . $e->getMessage());
                    }
                }
            }
        }
        header('Location: ' . ADMIN_URL . '/returns.php');
        exit;
    }
}

// Fetch Filter and Search Parameters
$statusFilter = sanitize($_GET['status'] ?? '');
$searchQuery  = sanitize($_GET['q'] ?? '');

$whereClauses = [];
$params = [];

if (!empty($statusFilter)) {
    $whereClauses[] = "rr.status = ?";
    $params[] = $statusFilter;
}

if (!empty($searchQuery)) {
    $whereClauses[] = "(rr.return_number LIKE ? OR o.order_number LIKE ? OR c.first_name LIKE ? OR c.last_name LIKE ? OR c.email LIKE ? OR c.phone LIKE ?)";
    $like = "%{$searchQuery}%";
    $params = array_merge($params, [$like, $like, $like, $like, $like, $like]);
}

$whereSql = !empty($whereClauses) ? 'WHERE ' . implode(' AND ', $whereClauses) : '';

// Metrics
$totalReturns    = (int)$db->query("SELECT COUNT(*) FROM return_requests")->fetchColumn();
$pendingReturns  = (int)$db->query("SELECT COUNT(*) FROM return_requests WHERE status = 'Return Requested'")->fetchColumn();
$approvedReturns = (int)$db->query("SELECT COUNT(*) FROM return_requests WHERE status = 'Return Approved'")->fetchColumn();
$refundedReturns = (int)$db->query("SELECT COUNT(*) FROM return_requests WHERE status = 'Refunded'")->fetchColumn();
$totalRefunded   = (float)$db->query("SELECT SUM(refund_amount) FROM return_requests WHERE refund_status = 'Refunded'")->fetchColumn();

// Fetch Returns List with Joined Order & Customer Information
$stmtList = $db->prepare("
    SELECT rr.*,
           o.order_number, o.created_at AS order_date, o.total_amount AS order_total, o.payment_method, o.payment_status AS order_payment_status,
           CONCAT(c.first_name, ' ', c.last_name) AS customer_name, c.email AS customer_email, c.phone AS customer_phone
    FROM return_requests rr
    JOIN orders o ON rr.order_id = o.id
    JOIN customers c ON rr.customer_id = c.id
    {$whereSql}
    ORDER BY rr.id DESC
");
$stmtList->execute($params);
$returnRequests = $stmtList->fetchAll(PDO::FETCH_ASSOC);

// Return settings
$currentReturnWindow = (int)get_setting('return_window_days', 14);
$currentPolicyText   = get_setting('return_policy_text', 'Returns accepted within 14 days of delivery.');

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';
require_once __DIR__ . '/includes/navbar.php';
?>

<!-- Statistics Overview -->
<div class="row g-3 mb-4">
    <div class="col-sm-6 col-xl-3">
        <div class="card border-0 shadow-sm p-3 bg-white">
            <div class="d-flex align-items-center justify-content-between">
                <div>
                    <span class="text-muted small text-uppercase fw-bold" style="font-size:0.7rem;">Total Requests</span>
                    <h3 class="fw-bold mb-0 mt-1"><?= $totalReturns ?></h3>
                </div>
                <div class="rounded-circle p-3 text-white" style="background:var(--caramel-dark);">
                    <i class="bi bi-arrow-return-left fs-4"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card border-0 shadow-sm p-3 bg-white">
            <div class="d-flex align-items-center justify-content-between">
                <div>
                    <span class="text-muted small text-uppercase fw-bold" style="font-size:0.7rem;">Pending Review</span>
                    <h3 class="fw-bold mb-0 mt-1 <?= $pendingReturns > 0 ? 'text-warning' : 'text-muted' ?>"><?= $pendingReturns ?></h3>
                </div>
                <div class="rounded-circle p-3 text-white bg-warning">
                    <i class="bi bi-hourglass-split fs-4"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card border-0 shadow-sm p-3 bg-white">
            <div class="d-flex align-items-center justify-content-between">
                <div>
                    <span class="text-muted small text-uppercase fw-bold" style="font-size:0.7rem;">Approved Returns</span>
                    <h3 class="fw-bold mb-0 mt-1 text-info"><?= $approvedReturns ?></h3>
                </div>
                <div class="rounded-circle p-3 text-white bg-info">
                    <i class="bi bi-check2-circle fs-4"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card border-0 shadow-sm p-3 bg-white">
            <div class="d-flex align-items-center justify-content-between">
                <div>
                    <span class="text-muted small text-uppercase fw-bold" style="font-size:0.7rem;">Refunds Settled</span>
                    <h4 class="fw-bold mb-0 mt-1 text-success"><?= format_price($totalRefunded) ?></h4>
                </div>
                <div class="rounded-circle p-3 text-white bg-success">
                    <i class="bi bi-cash-stack fs-4"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Configurable Return Policy & Eligibility Settings Modal Trigger -->
<div class="card border-0 shadow-sm mb-4 bg-white p-3">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
        <div>
            <span class="badge" style="background:var(--caramel-dark);">Policy Rule</span>
            <span class="fw-bold ms-2">Return Window Deadline:</span> <?= $currentReturnWindow ?> Days from Physical Delivery
            <div class="text-muted small mt-1"><?= e($currentPolicyText) ?></div>
        </div>
        <div>
            <button type="button" class="btn btn-sm btn-outline-dark" data-bs-toggle="modal" data-bs-target="#settingsModal">
                <i class="bi bi-gear me-1"></i> Configure Policy &amp; Deadlines
            </button>
        </div>
    </div>
</div>

<!-- Filters & Search Form -->
<div class="card border-0 shadow-sm mb-4 bg-white p-3">
    <form method="GET" action="<?= ADMIN_URL ?>/returns.php" class="row g-2 align-items-center">
        <div class="col-md-5">
            <div class="input-group input-group-sm">
                <span class="input-group-text bg-light"><i class="bi bi-search"></i></span>
                <input type="text" name="q" class="form-control" placeholder="Search Return #, Order #, Customer, Phone..." value="<?= e($searchQuery) ?>">
            </div>
        </div>
        <div class="col-md-4">
            <select name="status" class="form-select form-select-sm" onchange="this.form.submit()">
                <option value="">All Return Statuses</option>
                <?php 
                $statuses = ['Return Requested', 'Return Approved', 'Return Rejected', 'Return in Transit', 'Returned', 'Refund Processing', 'Refunded'];
                foreach ($statuses as $st): ?>
                    <option value="<?= $st ?>" <?= $statusFilter === $st ? 'selected' : '' ?>><?= $st ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-3 d-flex gap-2">
            <button type="submit" class="btn btn-sm btn-dark px-3">Filter</button>
            <a href="<?= ADMIN_URL ?>/returns.php" class="btn btn-sm btn-outline-secondary">Reset</a>
        </div>
    </form>
</div>

<!-- Returns Table -->
<div class="card border-0 shadow-sm admin-table">
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead>
                <tr>
                    <th>Return Ref &amp; Date</th>
                    <th>Order Ref</th>
                    <th>Customer Info</th>
                    <th>Reason &amp; Issue</th>
                    <th>Proof</th>
                    <th>Status</th>
                    <th>Refund</th>
                    <th class="text-end" style="width:130px;">Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($returnRequests)): ?>
                    <?php foreach ($returnRequests as $rr): ?>
                        <tr>
                            <td>
                                <div class="fw-bold font-monospace text-dark"><?= e($rr['return_number']) ?></div>
                                <small class="text-muted"><?= format_date($rr['created_at'], 'M d, Y') ?></small>
                            </td>
                            <td>
                                <div>
                                    <a href="<?= ADMIN_URL ?>/order-details.php?id=<?= $rr['order_id'] ?>" class="text-decoration-none fw-bold" style="color:var(--caramel-dark);">
                                        <?= e($rr['order_number']) ?>
                                    </a>
                                </div>
                                <small class="text-muted"><?= format_price($rr['order_total']) ?></small>
                            </td>
                            <td>
                                <div class="fw-bold"><?= e($rr['customer_name']) ?></div>
                                <small class="text-muted d-block"><?= e($rr['customer_phone']) ?></small>
                                <small class="text-muted d-block"><?= e($rr['customer_email']) ?></small>
                            </td>
                            <td>
                                <div class="fw-bold small"><?= e($rr['reason']) ?></div>
                                <div class="small text-muted" style="max-width:240px;"><?= e(truncate($rr['description'], 65)) ?></div>
                            </td>
                            <td>
                                <?php if (!empty($rr['proof_image'])): 
                                    $proofUrl = BASE_URL . '/' . ltrim($rr['proof_image'], '/');
                                ?>
                                    <a href="<?= e($proofUrl) ?>" target="_blank" title="View Proof Photo">
                                        <img src="<?= e($proofUrl) ?>" alt="Proof" class="rounded border" style="width:44px; height:44px; object-fit:cover;">
                                    </a>
                                <?php else: ?>
                                    <span class="text-muted small">&mdash;</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?= get_return_status_badge($rr['status']) ?>
                            </td>
                            <td>
                                <div class="fw-bold"><?= format_price($rr['refund_amount']) ?></div>
                                <span class="badge bg-light text-dark border" style="font-size:0.68rem;"><?= e($rr['refund_status']) ?></span>
                            </td>
                            <td class="text-end text-nowrap">
                                <button type="button" class="btn btn-sm btn-dark review-return-btn"
                                    data-return='<?= htmlspecialchars(json_encode($rr), ENT_QUOTES, 'UTF-8') ?>'
                                    title="Manage Return &amp; Refund">
                                    <i class="bi bi-sliders me-1"></i> Review
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="8" class="text-center py-5 text-muted">No return requests found matching criteria.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- ======================================================================
     RETURN MANAGEMENT MODAL (Approve, Reject, Update Status, Refund)
     ====================================================================== -->
<div class="modal fade" id="reviewReturnModal" tabindex="-1" aria-labelledby="reviewReturnModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header" style="background:var(--bg-cream); border-color:var(--border-beige);">
                <div>
                    <h5 class="modal-title font-serif fw-bold mb-0" id="modalReturnTitle">Review Return Request</h5>
                    <span class="small text-muted" id="modalReturnSub"></span>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            
            <div class="modal-body p-4">
                <!-- Return Overview Info -->
                <div class="row g-3 mb-4 pb-3 border-bottom" style="border-color:var(--border-beige) !important;">
                    <div class="col-md-6">
                        <label class="small text-muted d-block">Client</label>
                        <div class="fw-bold" id="mCustName"></div>
                        <div class="small text-muted" id="mCustContact"></div>
                    </div>
                    <div class="col-md-6">
                        <label class="small text-muted d-block">Associated Order</label>
                        <div class="fw-bold" id="mOrderNum"></div>
                        <div class="small text-muted" id="mOrderDetails"></div>
                    </div>
                    <div class="col-12">
                        <label class="small text-muted d-block">Primary Reason</label>
                        <div class="fw-bold text-dark" id="mReason"></div>
                    </div>
                    <div class="col-12">
                        <label class="small text-muted d-block">Customer Problem Description</label>
                        <div class="p-3 bg-light rounded border small" id="mDescription" style="border-color:var(--border-beige) !important;"></div>
                    </div>
                    <div class="col-12" id="mProofContainer">
                        <label class="small text-muted d-block mb-1">Supporting Proof Image</label>
                        <div id="mProofImgWrapper"></div>
                    </div>
                </div>

                <!-- Action Tabs -->
                <ul class="nav nav-pills mb-3" id="returnActionTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active btn-sm" id="tab-status-btn" data-bs-toggle="pill" data-bs-target="#tab-status" type="button" role="tab">Update Status</button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link btn-sm" id="tab-approve-btn" data-bs-toggle="pill" data-bs-target="#tab-approve" type="button" role="tab">Approve Return</button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link btn-sm text-danger" id="tab-reject-btn" data-bs-toggle="pill" data-bs-target="#tab-reject" type="button" role="tab">Reject Return</button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link btn-sm text-success fw-bold" id="tab-refund-btn" data-bs-toggle="pill" data-bs-target="#tab-refund" type="button" role="tab">Manage Refund</button>
                    </li>
                </ul>

                <div class="tab-content" id="returnActionTabsContent">
                    <!-- Tab 1: Update Status -->
                    <div class="tab-pane fade show active" id="tab-status" role="tabpanel">
                        <form method="POST" action="<?= ADMIN_URL ?>/returns.php">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="update_status">
                            <input type="hidden" name="return_id" class="target-return-id" value="">

                            <div class="mb-3">
                                <label class="form-label small fw-bold text-uppercase">Progress Return Status</label>
                                <select name="status" class="form-select" id="mSelectStatus" required>
                                    <option value="Return Requested">Return Requested (Awaiting Concierge)</option>
                                    <option value="Return Approved">Return Approved</option>
                                    <option value="Return in Transit">Return in Transit (Courier Pickup Active)</option>
                                    <option value="Returned">Returned (Physically Received at Vault)</option>
                                    <option value="Refund Processing">Refund Processing</option>
                                    <option value="Refunded">Refunded (Settlement Completed)</option>
                                    <option value="Return Rejected">Return Rejected</option>
                                </select>
                            </div>
                            <button type="submit" class="btn btn-dark rounded-pill px-4">Update Return Status</button>
                        </form>
                    </div>

                    <!-- Tab 2: Approve Return -->
                    <div class="tab-pane fade" id="tab-approve" role="tabpanel">
                        <form method="POST" action="<?= ADMIN_URL ?>/returns.php">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="approve_return">
                            <input type="hidden" name="return_id" class="target-return-id" value="">

                            <div class="mb-3">
                                <label class="form-label small fw-bold text-uppercase">Concierge Approval Notes</label>
                                <textarea name="admin_notes" class="form-control" rows="3" placeholder="Provide instructions for courier pickup or vault processing..."></textarea>
                            </div>
                            <button type="submit" class="btn btn-success rounded-pill px-4">Authorize &amp; Approve Return</button>
                        </form>
                    </div>

                    <!-- Tab 3: Reject Return -->
                    <div class="tab-pane fade" id="tab-reject" role="tabpanel">
                        <form method="POST" action="<?= ADMIN_URL ?>/returns.php">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="reject_return">
                            <input type="hidden" name="return_id" class="target-return-id" value="">

                            <div class="mb-3">
                                <label class="form-label small fw-bold text-uppercase text-danger">Rejection Reason <span class="text-danger">*</span></label>
                                <textarea name="rejection_reason" class="form-control" rows="3" placeholder="State clear justification (e.g. past 14-day window, timepiece shows evident wear or scratches, missing box)..." required></textarea>
                            </div>
                            <button type="submit" class="btn btn-danger rounded-pill px-4">Reject Return Claim</button>
                        </form>
                    </div>

                    <!-- Tab 4: Manage & Authorize Refund -->
                    <div class="tab-pane fade" id="tab-refund" role="tabpanel">
                        <form method="POST" action="<?= ADMIN_URL ?>/returns.php" onsubmit="return confirm('Authorize and execute this refund? Stock will be restored and order payment status set to Refunded.');">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="process_refund">
                            <input type="hidden" name="return_id" class="target-return-id" value="">

                            <div class="alert alert-warning small mb-3">
                                <i class="bi bi-shield-check me-1"></i> <strong>Financial Control:</strong> Authorizing this refund will record the payout, set payment status to <code>Refunded</code>, and return the timepiece inventory back into vault stock.
                            </div>

                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label small fw-bold text-uppercase">Refund Amount (PKR) <span class="text-danger">*</span></label>
                                    <input type="number" step="0.01" min="1" name="refund_amount" id="mRefundAmount" class="form-control" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label small fw-bold text-uppercase">Disbursement Method</label>
                                    <select name="refund_method" class="form-select">
                                        <option value="Bank Transfer">Bank Transfer (Direct IBFT)</option>
                                        <option value="Cash on Delivery Refund">Cash on Delivery Physical Remittance</option>
                                        <option value="Store Privilege Credit">Store Privilege Credit Voucher</option>
                                        <option value="Original Payment Method">Original Payment Method</option>
                                    </select>
                                </div>
                                <div class="col-12">
                                    <label class="form-label small fw-bold text-uppercase">Transaction Reference / Internal Notes</label>
                                    <input type="text" name="refund_notes" class="form-control" placeholder="e.g. Bank IBFT Ref #99281, approved by Finance Director">
                                </div>
                            </div>
                            <div class="mt-3">
                                <button type="submit" class="btn btn-success rounded-pill px-4">Authorize Payout &amp; Release Refund</button>
                            </div>
                        </form>
                    </div>
                </div>

            </div>
            <div class="modal-footer" style="border-color:var(--border-beige);">
                <button type="button" class="btn btn-outline-secondary rounded-pill px-4" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- ======================================================================
     CONFIGURABLE RETURN POLICY & DEADLINE MODAL
     ====================================================================== -->
<div class="modal fade" id="settingsModal" tabindex="-1" aria-labelledby="settingsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header" style="background:var(--bg-cream); border-color:var(--border-beige);">
                <h5 class="modal-title font-serif fw-bold" id="settingsModalLabel">Configure Return Policy &amp; Rules</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="<?= ADMIN_URL ?>/returns.php">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="update_settings">

                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-uppercase">Return Window Deadline (Days from Delivery)</label>
                        <input type="number" min="1" max="90" name="return_window_days" class="form-control" value="<?= $currentReturnWindow ?>" required>
                        <small class="text-muted">Customers cannot initiate return requests once this period has elapsed after delivery.</small>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold text-uppercase">Public Return Policy Statement</label>
                        <textarea name="return_policy_text" class="form-control" rows="3" required><?= e($currentPolicyText) ?></textarea>
                    </div>
                </div>

                <div class="modal-footer" style="border-color:var(--border-beige);">
                    <button type="button" class="btn btn-outline-secondary rounded-pill px-4" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-dark rounded-pill px-4">Save Return Settings</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.querySelectorAll('.review-return-btn').forEach(btn => {
    btn.addEventListener('click', function () {
        const r = JSON.parse(this.dataset.return);
        
        document.getElementById('modalReturnTitle').textContent = 'Return Request ' + r.return_number;
        document.getElementById('modalReturnSub').textContent = 'Created ' + r.created_at + ' • Status: ' + r.status;
        
        document.querySelectorAll('.target-return-id').forEach(el => el.value = r.id);
        
        document.getElementById('mCustName').textContent = r.customer_name;
        document.getElementById('mCustContact').textContent = r.customer_phone + ' • ' + r.customer_email;
        
        document.getElementById('mOrderNum').textContent = r.order_number;
        document.getElementById('mOrderDetails').textContent = 'Order Total: Rs. ' + Number(r.order_total).toLocaleString() + ' • Status: ' + r.order_payment_status;
        
        document.getElementById('mReason').textContent = r.reason;
        document.getElementById('mDescription').textContent = r.description;
        
        const proofWrap = document.getElementById('mProofImgWrapper');
        if (r.proof_image) {
            proofWrap.innerHTML = `<a href="<?= BASE_URL ?>/${r.proof_image.replace(/^\//, '')}" target="_blank"><img src="<?= BASE_URL ?>/${r.proof_image.replace(/^\//, '')}" class="rounded border shadow-sm" style="max-height:120px; object-fit:cover;"></a>`;
            document.getElementById('mProofContainer').style.display = 'block';
        } else {
            proofWrap.innerHTML = '<span class="text-muted small">No photo uploaded by client.</span>';
        }

        document.getElementById('mSelectStatus').value = r.status;
        document.getElementById('mRefundAmount').value = Number(r.refund_amount) > 0 ? r.refund_amount : r.order_total;

        const modal = new bootstrap.Modal(document.getElementById('reviewReturnModal'));
        modal.show();
    });
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
