<?php
// =============================================================================
// VALENOR HOROLOGY — ADMIN COUPON MANAGEMENT (Phase 09)
// =============================================================================

require_once __DIR__ . '/../config/config.php';
require_admin();

$db = getDB();

$adminTitle      = 'Privilege Coupons';
$adminPageHeader = 'Privilege Coupon Management';
$adminPageSub    = 'Create, configure, and monitor promotional discount vouchers and collector privilege privileges.';

// Handle Form Submissions (Create, Edit, Toggle Status, Delete)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) {
        set_flash('danger', 'CSRF verification failed.');
        header('Location: ' . ADMIN_URL . '/coupons.php');
        exit;
    }

    $action = sanitize($_POST['action'] ?? '');

    // 1. Create Coupon
    if ($action === 'create') {
        $code             = strtoupper(trim(sanitize($_POST['code'] ?? '')));
        $description      = sanitize($_POST['description'] ?? '');
        $discountType     = in_array($_POST['discount_type'] ?? '', ['percentage', 'fixed']) ? $_POST['discount_type'] : 'percentage';
        $discountValue    = (float)($_POST['discount_value'] ?? 0);
        $minOrderValue    = (float)($_POST['min_order_value'] ?? 0);
        $maxDiscountValue = !empty($_POST['max_discount_value']) ? (float)$_POST['max_discount_value'] : null;
        $usageLimit       = !empty($_POST['usage_limit']) ? (int)$_POST['usage_limit'] : null;
        $startDate        = !empty($_POST['start_date']) ? sanitize($_POST['start_date']) : date('Y-m-d');
        $expiryDate       = sanitize($_POST['expiry_date'] ?? '');
        $status           = in_array($_POST['status'] ?? '', ['active', 'inactive']) ? $_POST['status'] : 'active';

        if (empty($code) || $discountValue <= 0 || empty($expiryDate)) {
            set_flash('danger', 'Please provide a coupon code, valid discount value, and expiration date.');
        } else {
            // Check if code already exists
            $stmt = $db->prepare("SELECT id FROM coupons WHERE UPPER(code) = ? LIMIT 1");
            $stmt->execute([$code]);
            if ($stmt->fetch()) {
                set_flash('danger', "Coupon code '{$code}' already exists. Please choose a unique code.");
            } else {
                $ins = $db->prepare("
                    INSERT INTO coupons 
                    (code, description, discount_type, discount_value, min_order_value, max_discount_value, usage_limit, start_date, expiry_date, status, created_at, updated_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
                ");
                $ins->execute([
                    $code, $description, $discountType, $discountValue, $minOrderValue, $maxDiscountValue, $usageLimit, $startDate, $expiryDate, $status
                ]);
                set_flash('success', "Privilege coupon '{$code}' created successfully.");
            }
        }
        header('Location: ' . ADMIN_URL . '/coupons.php');
        exit;
    }

    // 2. Edit Coupon
    if ($action === 'edit') {
        $couponId         = (int)($_POST['coupon_id'] ?? 0);
        $code             = strtoupper(trim(sanitize($_POST['code'] ?? '')));
        $description      = sanitize($_POST['description'] ?? '');
        $discountType     = in_array($_POST['discount_type'] ?? '', ['percentage', 'fixed']) ? $_POST['discount_type'] : 'percentage';
        $discountValue    = (float)($_POST['discount_value'] ?? 0);
        $minOrderValue    = (float)($_POST['min_order_value'] ?? 0);
        $maxDiscountValue = !empty($_POST['max_discount_value']) ? (float)$_POST['max_discount_value'] : null;
        $usageLimit       = !empty($_POST['usage_limit']) ? (int)$_POST['usage_limit'] : null;
        $startDate        = !empty($_POST['start_date']) ? sanitize($_POST['start_date']) : date('Y-m-d');
        $expiryDate       = sanitize($_POST['expiry_date'] ?? '');
        $status           = in_array($_POST['status'] ?? '', ['active', 'inactive']) ? $_POST['status'] : 'active';

        if ($couponId <= 0 || empty($code) || $discountValue <= 0 || empty($expiryDate)) {
            set_flash('danger', 'Please provide valid coupon parameters.');
        } else {
            // Check duplicate code on other coupons
            $stmt = $db->prepare("SELECT id FROM coupons WHERE UPPER(code) = ? AND id != ? LIMIT 1");
            $stmt->execute([$code, $couponId]);
            if ($stmt->fetch()) {
                set_flash('danger', "Coupon code '{$code}' is already used by another record.");
            } else {
                $upd = $db->prepare("
                    UPDATE coupons SET
                        code = ?, description = ?, discount_type = ?, discount_value = ?,
                        min_order_value = ?, max_discount_value = ?, usage_limit = ?,
                        start_date = ?, expiry_date = ?, status = ?, updated_at = NOW()
                    WHERE id = ?
                ");
                $upd->execute([
                    $code, $description, $discountType, $discountValue, $minOrderValue, $maxDiscountValue, $usageLimit, $startDate, $expiryDate, $status, $couponId
                ]);
                set_flash('success', "Coupon '{$code}' updated successfully.");
            }
        }
        header('Location: ' . ADMIN_URL . '/coupons.php');
        exit;
    }

    // 3. Toggle Status (Enable/Disable)
    if ($action === 'toggle_status') {
        $couponId = (int)($_POST['coupon_id'] ?? 0);
        $stmt = $db->prepare("SELECT status, code FROM coupons WHERE id = ?");
        $stmt->execute([$couponId]);
        $row = $stmt->fetch();
        if ($row) {
            $newStatus = ($row['status'] === 'active') ? 'inactive' : 'active';
            $db->prepare("UPDATE coupons SET status = ?, updated_at = NOW() WHERE id = ?")->execute([$newStatus, $couponId]);
            set_flash('success', "Coupon '{$row['code']}' marked as " . strtoupper($newStatus) . ".");
        }
        header('Location: ' . ADMIN_URL . '/coupons.php');
        exit;
    }

    // 4. Delete Coupon
    if ($action === 'delete') {
        $couponId = (int)($_POST['coupon_id'] ?? 0);
        $stmt = $db->prepare("SELECT code FROM coupons WHERE id = ?");
        $stmt->execute([$couponId]);
        $row = $stmt->fetch();
        if ($row) {
            $db->prepare("DELETE FROM coupons WHERE id = ?")->execute([$couponId]);
            set_flash('success', "Coupon '{$row['code']}' removed permanently.");
        }
        header('Location: ' . ADMIN_URL . '/coupons.php');
        exit;
    }
}

// Fetch Metrics
$totalCoupons   = (int)$db->query("SELECT COUNT(*) FROM coupons")->fetchColumn();
$activeCoupons  = (int)$db->query("SELECT COUNT(*) FROM coupons WHERE status = 'active' AND expiry_date >= CURDATE()")->fetchColumn();
$totalUsedCount = (int)$db->query("SELECT SUM(usage_count) FROM coupons")->fetchColumn();
$totalSavings   = (float)$db->query("SELECT SUM(discount_amount) FROM coupon_usage")->fetchColumn();

// Fetch All Coupons
$coupons = $db->query("SELECT * FROM coupons ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);

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
                    <span class="text-muted small text-uppercase fw-bold" style="font-size:0.7rem;">Total Coupons</span>
                    <h3 class="fw-bold mb-0 mt-1"><?= $totalCoupons ?></h3>
                </div>
                <div class="rounded-circle p-3 text-white" style="background:var(--caramel-dark);">
                    <i class="bi bi-tags fs-4"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card border-0 shadow-sm p-3 bg-white">
            <div class="d-flex align-items-center justify-content-between">
                <div>
                    <span class="text-muted small text-uppercase fw-bold" style="font-size:0.7rem;">Active Privileges</span>
                    <h3 class="fw-bold mb-0 mt-1 text-success"><?= $activeCoupons ?></h3>
                </div>
                <div class="rounded-circle p-3 text-white bg-success">
                    <i class="bi bi-check-circle fs-4"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card border-0 shadow-sm p-3 bg-white">
            <div class="d-flex align-items-center justify-content-between">
                <div>
                    <span class="text-muted small text-uppercase fw-bold" style="font-size:0.7rem;">Times Claimed</span>
                    <h3 class="fw-bold mb-0 mt-1 text-primary"><?= $totalUsedCount ?></h3>
                </div>
                <div class="rounded-circle p-3 text-white bg-primary">
                    <i class="bi bi-cart-check fs-4"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card border-0 shadow-sm p-3 bg-white">
            <div class="d-flex align-items-center justify-content-between">
                <div>
                    <span class="text-muted small text-uppercase fw-bold" style="font-size:0.7rem;">Customer Savings</span>
                    <h4 class="fw-bold mb-0 mt-1" style="color:var(--caramel-dark);"><?= format_price($totalSavings) ?></h4>
                </div>
                <div class="rounded-circle p-3 text-white" style="background:#555;">
                    <i class="bi bi-cash-stack fs-4"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Header & Add Button -->
<div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-3">
    <div>
        <h4 class="font-serif fw-bold mb-1" style="color:var(--text-charcoal);">Timepiece Privilege Codes</h4>
        <div class="small text-muted">Manage promotional voucher codes, minimum purchase requirements, and maximum usage caps.</div>
    </div>
    <button type="button" class="btn btn-dark rounded-pill px-4" data-bs-toggle="modal" data-bs-target="#createCouponModal">
        <i class="bi bi-plus-circle me-1"></i> Create Coupon
    </button>
</div>

<!-- Coupons Table -->
<div class="card border-0 shadow-sm admin-table">
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead>
                <tr>
                    <th>Code &amp; Details</th>
                    <th>Discount</th>
                    <th>Order Threshold</th>
                    <th>Redemptions</th>
                    <th>Validity Period</th>
                    <th>Status</th>
                    <th class="text-end" style="width:140px;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($coupons)): ?>
                    <?php foreach ($coupons as $c): 
                        $isExpired = ($c['expiry_date'] < date('Y-m-d'));
                        $isExhausted = (!empty($c['usage_limit']) && $c['usage_count'] >= $c['usage_limit']);
                    ?>
                        <tr>
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    <span class="badge font-monospace fs-6 px-2 py-1" style="background:var(--caramel-dark);">
                                        <?= e($c['code']) ?>
                                    </span>
                                </div>
                                <div class="small text-muted mt-1" style="max-width:240px;"><?= e($c['description']) ?></div>
                            </td>
                            <td>
                                <?php if ($c['discount_type'] === 'percentage'): ?>
                                    <div class="fw-bold text-dark fs-6"><?= (float)$c['discount_value'] ?>% OFF</div>
                                    <?php if (!empty($c['max_discount_value'])): ?>
                                        <small class="text-muted d-block">Cap: <?= format_price($c['max_discount_value']) ?></small>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <div class="fw-bold text-dark fs-6"><?= format_price($c['discount_value']) ?> OFF</div>
                                    <small class="text-muted d-block">Fixed Deduction</small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="small">
                                    Min: <span class="fw-semibold"><?= (float)$c['min_order_value'] > 0 ? format_price($c['min_order_value']) : 'None' ?></span>
                                </div>
                            </td>
                            <td>
                                <div>
                                    <span class="fw-bold"><?= (int)$c['usage_count'] ?></span>
                                    <span class="text-muted">/ <?= !empty($c['usage_limit']) ? (int)$c['usage_limit'] : '&infin;' ?></span>
                                </div>
                                <?php if ($isExhausted): ?>
                                    <span class="badge bg-danger text-white" style="font-size:0.68rem;">Exhausted</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="small">
                                    <div><?= date('M d, Y', strtotime($c['start_date'])) ?> &rarr;</div>
                                    <div class="<?= $isExpired ? 'text-danger fw-bold' : '' ?>">
                                        <?= date('M d, Y', strtotime($c['expiry_date'])) ?>
                                    </div>
                                    <?php if ($isExpired): ?>
                                        <span class="badge bg-danger-subtle text-danger" style="font-size:0.65rem;">Expired</span>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td>
                                <?php if ($c['status'] === 'active' && !$isExpired && !$isExhausted): ?>
                                    <span class="badge bg-success">Active</span>
                                <?php elseif ($c['status'] === 'inactive'): ?>
                                    <span class="badge bg-secondary">Disabled</span>
                                <?php else: ?>
                                    <span class="badge bg-warning text-dark">Inactive</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-end text-nowrap">
                                <!-- Quick Toggle Active / Disable -->
                                <form method="POST" action="<?= ADMIN_URL ?>/coupons.php" class="d-inline">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="toggle_status">
                                    <input type="hidden" name="coupon_id" value="<?= $c['id'] ?>">
                                    <button type="submit" class="btn btn-sm <?= $c['status'] === 'active' ? 'btn-outline-warning' : 'btn-outline-success' ?> p-1 px-2" title="<?= $c['status'] === 'active' ? 'Disable' : 'Enable' ?>">
                                        <i class="bi <?= $c['status'] === 'active' ? 'bi-pause-circle' : 'bi-play-circle' ?>"></i>
                                    </button>
                                </form>

                                <!-- Edit Button triggers modal -->
                                <button type="button" class="btn btn-sm btn-outline-dark p-1 px-2 ms-1 edit-coupon-btn" 
                                    data-coupon='<?= htmlspecialchars(json_encode($c), ENT_QUOTES, 'UTF-8') ?>'
                                    title="Edit Coupon">
                                    <i class="bi bi-pencil"></i>
                                </button>

                                <!-- Delete Form -->
                                <form method="POST" action="<?= ADMIN_URL ?>/coupons.php" class="d-inline" onsubmit="return confirm('Delete coupon <?= e($c['code']) ?> permanently?');">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="coupon_id" value="<?= $c['id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-danger p-1 px-2 ms-1" title="Delete">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="7" class="text-center py-5 text-muted">No privilege coupons created yet. Click "Create Coupon" above.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- ======================================================================
     CREATE COUPON MODAL
     ====================================================================== -->
<div class="modal fade" id="createCouponModal" tabindex="-1" aria-labelledby="createCouponModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header" style="background:var(--bg-cream); border-color:var(--border-beige);">
                <h5 class="modal-title font-serif fw-bold" id="createCouponModalLabel">Create Privilege Coupon</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="<?= ADMIN_URL ?>/coupons.php">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="create">

                <div class="modal-body p-4">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-bold text-uppercase">Coupon Code <span class="text-danger">*</span></label>
                            <input type="text" name="code" class="form-control text-uppercase font-monospace fw-bold" placeholder="e.g. VALENOR25" required>
                            <small class="text-muted">Unique alphanumeric voucher code.</small>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold text-uppercase">Status</label>
                            <select name="status" class="form-select">
                                <option value="active" selected>Active / Enabled</option>
                                <option value="inactive">Inactive / Disabled</option>
                            </select>
                        </div>

                        <div class="col-12">
                            <label class="form-label small fw-bold text-uppercase">Description</label>
                            <input type="text" name="description" class="form-control" placeholder="e.g. 25% VIP Privilege on Tourbillon Series">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label small fw-bold text-uppercase">Discount Type <span class="text-danger">*</span></label>
                            <select name="discount_type" id="createDiscountType" class="form-select" required>
                                <option value="percentage">Percentage (%)</option>
                                <option value="fixed">Fixed Amount (PKR)</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold text-uppercase">Discount Value <span class="text-danger">*</span></label>
                            <input type="number" step="0.01" min="0.01" name="discount_value" class="form-control" placeholder="e.g. 20 for 20% or 5000 for Rs. 5,000" required>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label small fw-bold text-uppercase">Minimum Order Value (PKR)</label>
                            <input type="number" step="0.01" min="0" name="min_order_value" class="form-control" value="0.00">
                            <small class="text-muted">Order subtotal required to qualify (0 for none).</small>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold text-uppercase">Maximum Discount Cap (PKR)</label>
                            <input type="number" step="0.01" min="0" name="max_discount_value" class="form-control" placeholder="Optional cap for percentage discounts">
                        </div>

                        <div class="col-md-4">
                            <label class="form-label small fw-bold text-uppercase">Usage Limit (Total)</label>
                            <input type="number" min="1" name="usage_limit" class="form-control" placeholder="Unlimited if blank">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-bold text-uppercase">Start Date</label>
                            <input type="date" name="start_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-bold text-uppercase">Expiry Date <span class="text-danger">*</span></label>
                            <input type="date" name="expiry_date" class="form-control" value="<?= date('Y-m-d', strtotime('+1 year')) ?>" required>
                        </div>
                    </div>
                </div>

                <div class="modal-footer" style="border-color:var(--border-beige);">
                    <button type="button" class="btn btn-outline-secondary rounded-pill px-4" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-dark rounded-pill px-4">Create Privilege Coupon</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ======================================================================
     EDIT COUPON MODAL
     ====================================================================== -->
<div class="modal fade" id="editCouponModal" tabindex="-1" aria-labelledby="editCouponModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header" style="background:var(--bg-cream); border-color:var(--border-beige);">
                <h5 class="modal-title font-serif fw-bold" id="editCouponModalLabel">Edit Privilege Coupon</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="<?= ADMIN_URL ?>/coupons.php">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="coupon_id" id="editCouponId" value="">

                <div class="modal-body p-4">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-bold text-uppercase">Coupon Code <span class="text-danger">*</span></label>
                            <input type="text" name="code" id="editCode" class="form-control text-uppercase font-monospace fw-bold" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold text-uppercase">Status</label>
                            <select name="status" id="editStatus" class="form-select">
                                <option value="active">Active / Enabled</option>
                                <option value="inactive">Inactive / Disabled</option>
                            </select>
                        </div>

                        <div class="col-12">
                            <label class="form-label small fw-bold text-uppercase">Description</label>
                            <input type="text" name="description" id="editDescription" class="form-control">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label small fw-bold text-uppercase">Discount Type <span class="text-danger">*</span></label>
                            <select name="discount_type" id="editDiscountType" class="form-select" required>
                                <option value="percentage">Percentage (%)</option>
                                <option value="fixed">Fixed Amount (PKR)</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold text-uppercase">Discount Value <span class="text-danger">*</span></label>
                            <input type="number" step="0.01" min="0.01" name="discount_value" id="editDiscountValue" class="form-control" required>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label small fw-bold text-uppercase">Minimum Order Value (PKR)</label>
                            <input type="number" step="0.01" min="0" name="min_order_value" id="editMinOrder" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold text-uppercase">Maximum Discount Cap (PKR)</label>
                            <input type="number" step="0.01" min="0" name="max_discount_value" id="editMaxDiscount" class="form-control">
                        </div>

                        <div class="col-md-4">
                            <label class="form-label small fw-bold text-uppercase">Usage Limit (Total)</label>
                            <input type="number" min="1" name="usage_limit" id="editUsageLimit" class="form-control" placeholder="Unlimited if blank">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-bold text-uppercase">Start Date</label>
                            <input type="date" name="start_date" id="editStartDate" class="form-control" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-bold text-uppercase">Expiry Date <span class="text-danger">*</span></label>
                            <input type="date" name="expiry_date" id="editExpiryDate" class="form-control" required>
                        </div>
                    </div>
                </div>

                <div class="modal-footer" style="border-color:var(--border-beige);">
                    <button type="button" class="btn btn-outline-secondary rounded-pill px-4" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-dark rounded-pill px-4">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.querySelectorAll('.edit-coupon-btn').forEach(btn => {
    btn.addEventListener('click', function () {
        const c = JSON.parse(this.dataset.coupon);
        document.getElementById('editCouponId').value = c.id;
        document.getElementById('editCode').value = c.code;
        document.getElementById('editDescription').value = c.description || '';
        document.getElementById('editDiscountType').value = c.discount_type;
        document.getElementById('editDiscountValue').value = c.discount_value;
        document.getElementById('editMinOrder').value = c.min_order_value;
        document.getElementById('editMaxDiscount').value = c.max_discount_value || '';
        document.getElementById('editUsageLimit').value = c.usage_limit || '';
        document.getElementById('editStartDate').value = c.start_date;
        document.getElementById('editExpiryDate').value = c.expiry_date;
        document.getElementById('editStatus').value = c.status;

        const editModal = new bootstrap.Modal(document.getElementById('editCouponModal'));
        editModal.show();
    });
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
