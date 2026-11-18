<?php
// =============================================================================
// VALENOR HOROLOGY — CUSTOMER ACCOUNT DASHBOARD (Section 14)
// =============================================================================

require_once __DIR__ . '/config/config.php';
require_login('my-account.php');

$db = getDB();
$user = current_user();
$customerId = current_user_id();

// Fetch customer address
$addrStmt = $db->prepare("SELECT * FROM customer_addresses WHERE customer_id = ? ORDER BY is_default DESC, id DESC");
$addrStmt->execute([$customerId]);
$addresses = $addrStmt->fetchAll();

// Fetch recent orders count
$ordCount = $db->prepare("SELECT COUNT(*) FROM orders WHERE customer_id = ?");
$ordCount->execute([$customerId]);
$totalOrders = (int)$ordCount->fetchColumn();

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    if (!verify_csrf()) {
        set_flash('danger', 'Security session expired.');
    } else {
        $fn = sanitize($_POST['first_name'] ?? '');
        $ln = sanitize($_POST['last_name'] ?? '');
        $ph = sanitize($_POST['phone'] ?? '');
        $pw = $_POST['new_password'] ?? '';

        if (!empty($fn) && !empty($ln) && !empty($ph)) {
            if (!empty($pw)) {
                if (strlen($pw) < 6) {
                    set_flash('danger', 'New password must be at least 6 characters.');
                } else {
                    $db->prepare("UPDATE customers SET first_name = ?, last_name = ?, phone = ?, password_hash = ?, updated_at = NOW() WHERE id = ?")
                       ->execute([$fn, $ln, $ph, password_hash($pw, PASSWORD_BCRYPT), $customerId]);
                    $_SESSION['customer_name'] = $fn . ' ' . $ln;
                    set_flash('success', 'Profile and password updated successfully.');
                }
            } else {
                $db->prepare("UPDATE customers SET first_name = ?, last_name = ?, phone = ?, updated_at = NOW() WHERE id = ?")
                   ->execute([$fn, $ln, $ph, $customerId]);
                $_SESSION['customer_name'] = $fn . ' ' . $ln;
                set_flash('success', 'Profile updated successfully.');
            }
        } else {
            set_flash('danger', 'First name, last name, and phone cannot be empty.');
        }
    }
    header('Location: ' . BASE_URL . '/my-account.php');
    exit;
}

$pageTitle = 'Collector Account Profile';
require_once INCLUDES_PATH . '/header.php';
require_once INCLUDES_PATH . '/navbar.php';
?>

<div class="py-4 border-bottom" style="background-color: var(--bg-white); border-color: var(--border-beige) !important;">
    <div class="container">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-1" style="font-size:0.82rem;">
                <li class="breadcrumb-item"><a href="<?= BASE_URL ?>/index.php">Home</a></li>
                <li class="breadcrumb-item active" aria-current="page">My Account</li>
            </ol>
        </nav>
        <h1 class="h2 mb-0 font-serif fw-bold">Collector Account</h1>
    </div>
</div>

<div class="container py-5">
    <div class="row g-4">
        <!-- Sidebar Navigation -->
        <div class="col-lg-3">
            <div class="p-4 bg-white rounded border" style="border-color:var(--border-beige) !important;">
                <div class="text-center pb-3 mb-3 border-bottom" style="border-color:var(--border-beige) !important;">
                    <div class="reviewer-avatar mx-auto mb-2" style="width:56px; height:56px; font-size:1.3rem;">
                        <?= strtoupper(substr($user['first_name'], 0, 1)) ?>
                    </div>
                    <h5 class="font-serif fw-bold mb-0"><?= e($user['first_name'] . ' ' . $user['last_name']) ?></h5>
                    <span class="text-muted small"><?= e($user['email']) ?></span>
                </div>

                <ul class="list-unstyled mb-0" style="font-size:0.9rem;">
                    <li class="mb-2"><a href="<?= BASE_URL ?>/my-account.php" class="fw-bold" style="color:var(--caramel-dark);"><i class="bi bi-person me-2"></i>Account Overview</a></li>
                    <li class="mb-2"><a href="<?= BASE_URL ?>/my-orders.php" class="text-muted"><i class="bi bi-box-seam me-2"></i>My Orders (<?= $totalOrders ?>)</a></li>
                    <li class="mb-2"><a href="<?= BASE_URL ?>/wishlist.php" class="text-muted"><i class="bi bi-heart me-2"></i>Wishlist</a></li>
                    <li class="mb-2"><a href="<?= BASE_URL ?>/my-returns.php" class="text-muted"><i class="bi bi-arrow-return-left me-2"></i>Returns &amp; Refunds</a></li>
                    <li class="pt-2 border-top" style="border-color:var(--border-beige) !important;">
                        <a href="<?= BASE_URL ?>/logout.php" class="text-danger"><i class="bi bi-box-arrow-right me-2"></i>Sign Out</a>
                    </li>
                </ul>
            </div>
        </div>

        <!-- Main Account Content -->
        <div class="col-lg-9">
            <div class="row g-4 mb-4">
                <div class="col-md-4">
                    <div class="p-4 bg-white border rounded text-center" style="border-color:var(--border-beige) !important;">
                        <i class="bi bi-box-seam fs-2 mb-2 d-block" style="color:var(--caramel-primary);"></i>
                        <h3 class="font-serif fw-bold mb-0"><?= $totalOrders ?></h3>
                        <span class="text-muted small">Orders Placed</span>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="p-4 bg-white border rounded text-center" style="border-color:var(--border-beige) !important;">
                        <i class="bi bi-heart fs-2 mb-2 d-block" style="color:var(--caramel-primary);"></i>
                        <h3 class="font-serif fw-bold mb-0"><?= get_wishlist_count() ?></h3>
                        <span class="text-muted small">Saved Timepieces</span>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="p-4 bg-white border rounded text-center" style="border-color:var(--border-beige) !important;">
                        <i class="bi bi-geo-alt fs-2 mb-2 d-block" style="color:var(--caramel-primary);"></i>
                        <h3 class="font-serif fw-bold mb-0"><?= count($addresses) ?></h3>
                        <span class="text-muted small">Saved Addresses</span>
                    </div>
                </div>
            </div>

            <!-- Profile Overview Card -->
            <div class="card p-4 bg-white border mb-4" style="border-color:var(--border-beige) !important; border-radius:8px;">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h4 class="font-serif fw-bold mb-0">Profile Details</h4>
                    <button class="btn btn-sm btn-outline-dark" type="button" data-bs-toggle="collapse" data-bs-target="#editProfileCollapse" style="border-color:var(--border-beige);">
                        <i class="bi bi-pencil-square me-1"></i> Edit Profile
                    </button>
                </div>

                <!-- Edit Profile Collapse -->
                <div class="collapse mb-4" id="editProfileCollapse">
                    <form method="POST" action="<?= BASE_URL ?>/my-account.php" class="p-3 bg-light rounded border" style="border-color:var(--border-beige) !important;">
                        <?= csrf_field() ?>
                        <input type="hidden" name="update_profile" value="1">

                        <div class="row g-3">
                            <div class="col-sm-6">
                                <label class="form-label small fw-bold text-uppercase">First Name</label>
                                <input type="text" name="first_name" class="form-control form-control-sm" value="<?= e($user['first_name']) ?>" required>
                            </div>
                            <div class="col-sm-6">
                                <label class="form-label small fw-bold text-uppercase">Last Name</label>
                                <input type="text" name="last_name" class="form-control form-control-sm" value="<?= e($user['last_name']) ?>" required>
                            </div>
                            <div class="col-sm-6">
                                <label class="form-label small fw-bold text-uppercase">Phone</label>
                                <input type="tel" name="phone" class="form-control form-control-sm" value="<?= e($user['phone']) ?>" required>
                            </div>
                            <div class="col-sm-6">
                                <label class="form-label small fw-bold text-uppercase">New Password (Optional)</label>
                                <input type="password" name="new_password" class="form-control form-control-sm" placeholder="Leave blank to keep current">
                            </div>
                            <div class="col-12 text-end">
                                <button type="button" class="btn btn-sm btn-secondary me-2" data-bs-toggle="collapse" data-bs-target="#editProfileCollapse">Cancel</button>
                                <button type="submit" class="btn btn-sm btn-dark">Save Changes</button>
                            </div>
                        </div>
                    </form>
                </div>

                <div class="row g-3">
                    <div class="col-sm-6">
                        <label class="text-muted small">First Name</label>
                        <div class="fw-semibold"><?= e($user['first_name']) ?></div>
                    </div>
                    <div class="col-sm-6">
                        <label class="text-muted small">Last Name</label>
                        <div class="fw-semibold"><?= e($user['last_name']) ?></div>
                    </div>
                    <div class="col-sm-6">
                        <label class="text-muted small">Email Address</label>
                        <div class="fw-semibold"><?= e($user['email']) ?></div>
                    </div>
                    <div class="col-sm-6">
                        <label class="text-muted small">Phone Number</label>
                        <div class="fw-semibold"><?= e($user['phone']) ?></div>
                    </div>
                    <div class="col-12">
                        <label class="text-muted small">Member Since</label>
                        <div class="fw-semibold"><?= format_date($user['created_at'], 'F d, Y') ?></div>
                    </div>
                </div>
            </div>

            <!-- Saved Addresses Card -->
            <div class="card p-4 bg-white border" style="border-color:var(--border-beige) !important; border-radius:8px;">
                <h4 class="font-serif fw-bold mb-3">Delivery Address</h4>
                <?php if (!empty($addresses)): ?>
                    <?php foreach ($addresses as $a): ?>
                        <div class="p-3 border rounded mb-2" style="border-color:var(--border-beige) !important; background:var(--bg-cream);">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <strong style="color:var(--caramel-dark);"><?= e($a['address_title']) ?></strong>
                                <?php if ($a['is_default']): ?>
                                    <span class="badge bg-dark">Default Address</span>
                                <?php endif; ?>
                            </div>
                            <div class="small text-muted"><?= e($a['recipient_name']) ?> &bull; <?= e($a['phone']) ?></div>
                            <div class="small mt-1"><?= e($a['street_address']) ?>, <?= e($a['city']) ?> <?= e($a['postal_code']) ?></div>
                            <?php if ($a['delivery_instructions']): ?>
                                <div class="small text-muted fst-italic mt-1">Instructions: <?= e($a['delivery_instructions']) ?></div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p class="text-muted small">No delivery addresses saved yet. You can add one during checkout.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once INCLUDES_PATH . '/footer.php'; ?>

