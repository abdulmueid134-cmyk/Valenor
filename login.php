<?php
// =============================================================================
// VALENOR HOROLOGY — CUSTOMER LOGIN (Section 14 & 20)
// Mandatory login gatekeeper for Shopping Cart with Cart Merging
// =============================================================================

require_once __DIR__ . '/config/config.php';

$redirect = sanitize($_GET['redirect'] ?? ($_POST['redirect'] ?? 'index.php'));
// Prevent open redirects
if (!preg_match('#^[a-zA-Z0-9_\-\./\?=&]+$#', $redirect) || str_contains($redirect, '://')) {
    $redirect = 'index.php';
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) {
        $error = 'Security session expired. Please try submitting again.';
    } else {
        $identifier = sanitize($_POST['identifier'] ?? '');
        $password   = $_POST['password'] ?? '';

        if (empty($identifier) || empty($password)) {
            $error = 'Please enter your registered email/phone and password.';
        } else {
            $db = getDB();
            $stmt = $db->prepare("SELECT * FROM customers WHERE (email = ? OR phone = ?) LIMIT 1");
            $stmt->execute([$identifier, $identifier]);
            $customer = $stmt->fetch();

            if ($customer && password_verify($password, $customer['password_hash'])) {
                if ($customer['status'] !== 'active') {
                    $error = 'Your account is suspended. Please contact concierge support.';
                } else {
                    // Set session
                    $_SESSION['customer_id'] = (int)$customer['id'];
                    $_SESSION['customer_name'] = $customer['first_name'] . ' ' . $customer['last_name'];

                    // MERGE GUEST CART & WISHLIST INTO MYSQL
                    merge_guest_cart_to_db($customer['id']);
                    merge_guest_wishlist_to_db($customer['id']);

                    set_flash('success', 'Welcome back, ' . $customer['first_name'] . '! You are now signed in.');
                    header('Location: ' . BASE_URL . '/' . ltrim($redirect, '/'));
                    exit;
                }
            } else {
                $error = 'Invalid email/phone or password credentials.';
            }
        }
    }
}

$pageTitle = 'Sign In to Your Account';
require_once INCLUDES_PATH . '/header.php';
require_once INCLUDES_PATH . '/navbar.php';
?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-7 col-lg-5">
            <div class="card p-4 p-md-5 border bg-white shadow-sm" style="border-color:var(--border-beige) !important; border-radius:8px;">
                <div class="text-center mb-4">
                    <span class="text-uppercase fw-bold" style="color:var(--caramel-primary); letter-spacing:0.18em; font-size:0.75rem;">Exclusive Access</span>
                    <h1 class="font-serif fw-bold h2 mt-1 mb-2">Collector Sign In</h1>
                    <p class="text-muted small">Access your saved wishlist, order history, and luxury shopping bag.</p>
                </div>

                <?php if ($redirect === 'cart.php'): ?>
                    <div class="alert alert-info py-2 px-3 small d-flex align-items-center gap-2 mb-4" role="alert" style="background-color:var(--bg-cream); border-color:var(--border-beige); color:var(--caramel-dark);">
                        <i class="bi bi-bag-check fs-5"></i>
                        <div>Please sign in to view and manage your shopping bag. Your items are safely saved.</div>
                    </div>
                <?php elseif ($redirect === 'wishlist.php'): ?>
                    <div class="alert alert-info py-2 px-3 small d-flex align-items-center gap-2 mb-4" role="alert" style="background-color:var(--bg-cream); border-color:var(--border-beige); color:var(--caramel-dark);">
                        <i class="bi bi-heart fs-5"></i>
                        <div>Please sign in to access your saved timepieces. Your curated wishlist is protected.</div>
                    </div>
                <?php endif; ?>

                <?php if (!empty($error)): ?>
                    <div class="alert alert-danger py-2 small mb-4"><?= e($error) ?></div>
                <?php endif; ?>

                <form method="POST" action="<?= BASE_URL ?>/login.php">
                    <?= csrf_field() ?>
                    <input type="hidden" name="redirect" value="<?= e($redirect) ?>">

                    <div class="mb-3">
                        <label for="identifier" class="form-label small fw-bold text-uppercase" style="letter-spacing:0.06em;">Email or Phone Number</label>
                        <input type="text" id="identifier" name="identifier" class="form-control" placeholder="customer@valenor.com or +923001234567" value="<?= e($_POST['identifier'] ?? '') ?>" required autofocus>
                    </div>

                    <div class="mb-3">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <label for="password" class="form-label small fw-bold text-uppercase mb-0" style="letter-spacing:0.06em;">Password</label>
                            <a href="<?= BASE_URL ?>/forgot-password.php" class="small text-muted">Forgot Password?</a>
                        </div>
                        <input type="password" id="password" name="password" class="form-control" placeholder="••••••••" required>
                    </div>

                    <button type="submit" class="btn-valenor-primary w-100 justify-content-center mt-3 py-2">
                        Sign In &rarr;
                    </button>
                </form>

                <div class="mt-4 pt-3 text-center border-top small" style="border-color:var(--border-beige) !important;">
                    <span class="text-muted">New to VALENOR?</span>
                    <a href="<?= BASE_URL ?>/register.php?redirect=<?= urlencode($redirect) ?>" class="fw-bold ms-1" style="color:var(--caramel-dark);">Create an Account</a>
                </div>

                <div class="mt-4 p-3 bg-light rounded text-center small text-muted border" style="border-color:var(--border-beige) !important; font-size:0.75rem;">
                    <strong>Demo Customer:</strong> customer@valenor.com &bull; <strong>Password:</strong> Admin@12345
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once INCLUDES_PATH . '/footer.php'; ?>

