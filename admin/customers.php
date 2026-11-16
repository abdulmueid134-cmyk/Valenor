<?php
// =============================================================================
// VALENOR HOROLOGY — ADMIN CUSTOMER DIRECTORY
// =============================================================================

require_once __DIR__ . '/../config/config.php';
require_admin();

$db = getDB();

$adminTitle      = 'Registered Customers';
$adminPageHeader = 'Client Accounts & Profiles';
$adminPageSub    = 'Customer membership, contact details, total orders placed, and account statuses.';

$search = sanitize($_GET['q'] ?? '');

$where = ['1=1'];
$params = [];

if (!empty($search)) {
    $where[] = "(c.first_name LIKE ? OR c.last_name LIKE ? OR c.email LIKE ? OR c.phone LIKE ?)";
    $term = "%{$search}%";
    $params = array_merge($params, [$term, $term, $term, $term]);
}

$whereSql = implode(' AND ', $where);

$stmt = $db->prepare("
    SELECT c.*,
           COUNT(o.id) AS total_orders,
           COALESCE(SUM(o.total_amount), 0) AS total_spent
    FROM customers c
    LEFT JOIN orders o ON c.id = o.customer_id
    WHERE {$whereSql}
    GROUP BY c.id
    ORDER BY c.id DESC
");
$stmt->execute($params);
$customers = $stmt->fetchAll();

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';
require_once __DIR__ . '/includes/navbar.php';
?>

<div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-3">
    <div>
        <h4 class="font-serif fw-bold mb-1" style="color:var(--text-charcoal);">Registered Clients (<?= count($customers) ?>)</h4>
        <div class="small text-muted">Client profiles and order history records.</div>
    </div>
</div>

<!-- Search -->
<div class="card border-0 shadow-sm mb-4" style="border:1px solid var(--border-beige) !important;">
    <div class="card-body p-3">
        <form method="GET" action="<?= ADMIN_URL ?>/customers.php" class="row g-2 align-items-center">
            <div class="col-md-9">
                <input type="text" name="q" class="form-control form-control-sm" placeholder="Search by client name, email, or telephone..." value="<?= e($search) ?>">
            </div>
            <div class="col-md-3 d-flex gap-1">
                <button type="submit" class="btn btn-sm btn-dark w-100">Search</button>
                <?php if (!empty($search)): ?>
                    <a href="<?= ADMIN_URL ?>/customers.php" class="btn btn-sm btn-outline-secondary"><i class="bi bi-x"></i></a>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<div class="card border-0 shadow-sm admin-table">
    <div class="table-responsive">
        <table class="table mb-0">
            <thead>
                <tr>
                    <th>Client Name</th>
                    <th>Email Address</th>
                    <th>Telephone</th>
                    <th class="text-center">Orders Placed</th>
                    <th>Total Spend</th>
                    <th>Status</th>
                    <th>Registered</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($customers)): ?>
                    <?php foreach ($customers as $cust): ?>
                        <tr>
                            <td>
                                <div class="fw-bold"><?= e($cust['first_name'] . ' ' . $cust['last_name']) ?></div>
                                <small class="text-muted">ID: #<?= (int)$cust['id'] ?></small>
                            </td>
                            <td><?= e($cust['email']) ?></td>
                            <td><?= e($cust['phone'] ?: '—') ?></td>
                            <td class="text-center">
                                <span class="badge bg-light text-dark border"><?= (int)$cust['total_orders'] ?> orders</span>
                            </td>
                            <td class="fw-bold"><?= format_price($cust['total_spent']) ?></td>
                            <td>
                                <?php if ($cust['status'] === 'active'): ?>
                                    <span class="badge bg-success">Active</span>
                                <?php else: ?>
                                    <span class="badge bg-danger"><?= e($cust['status']) ?></span>
                                <?php endif; ?>
                            </td>
                            <td class="small text-muted"><?= format_date($cust['created_at'], 'M d, Y') ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="7" class="text-center py-5 text-muted">No client profiles found.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
