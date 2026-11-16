<?php
// =============================================================================
// VALENOR HOROLOGY — CUSTOMER REGISTRATION
// =============================================================================

require_once __DIR__ . '/config/config.php';

$redirect = sanitize($_GET['redirect'] ?? ($_POST['redirect'] ?? 'index.php'));
if (!preg_match('#^[a-zA-Z0-9_\-\./\?=&]+$#', $redirect) || str_contains($redirect, '://')) {
    $redirect = 'index.php';
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) {
        $error = 'Security session expired. Please try submitting again.';
    } else {
        $firstName = sanitize($_POST['first_name'] ?? '');
        $lastName  = sanitize($_POST['last_name'] ?? '');
        $email     = sanitize($_POST['email'] ?? '');
        $phone     = sanitize($_POST['phone'] ?? '');
        $password  = $_POST['password'] ?? '';
        $confirm   = $_POST['confirm_password'] ?? '';

        if (empty($firstName) || empty($lastName) || empty($email) || empty($phone) || empty($password)) {
            $error = 'Please fill out all required fields.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Please provide a valid email address.';
        } elseif (strlen($password) < 6) {
            $error = 'Password must be at least 6 characters in length.';
        } elseif ($password !== $confirm) {
            $error = 'Passwords do not match.';
        } else {
            $db = getDB();

            // Check if email or phone already exists
            $check = $db->prepare("SELECT id FROM customers WHERE email = ? OR phone = ? LIMIT 1");
            $check->execute([$email, $phone]);
            if ($check->fetch()) {
                $error = 'An account with this email address or phone number already exists.';
            } else {
                $hash = password_hash($password, PASSWORD_BCRYPT);
                $ins = $db->prepare("
                    INSERT INTO customers (first_name, last_name, email, phone, password_hash, status, created_at)
                    VALUES (?, ?, ?, ?, ?, 'active', NOW())
                ");
                $ins->execute([$firstName, $lastName, $email, $phone, $hash]);
                $newId = $db->lastInsertId();

                // Log in new customer
                $_SESSION['customer_id'] = (int)$newId;
                $_SESSION['customer_name'] = $firstName . ' ' . $lastName;

                // Merge guest cart & wishlist if any
                merge_guest_cart_to_db($newId);
                merge_guest_wishlist_to_db($newId);

                set_flash('success', 'Account created successfully! Welcome to VALENOR Horology.');
                header('Location: ' . BASE_URL . '/' . ltrim($redirect, '/'));
                exit;
            }
        }
    }
}

$pageTitle = 'Create Collector Account';
require_once INCLUDES_PATH . '/header.php';
require_once INCLUDES_PATH . '/navbar.php';
?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-8 col-lg-6">
            <div class="card p-4 p-md-5 border bg-white shadow-sm" style="border-color:var(--border-beige) !important; border-radius:8px;">
                <div class="text-center mb-4">
                    <span class="text-uppercase fw-bold" style="color:var(--caramel-primary); letter-spacing:0.18em; font-size:0.75rem;">New Collector</span>
                    <h1 class="font-serif fw-bold h2 mt-1 mb-2">Create an Account</h1>
                    <p class="text-muted small">Register to track orders, save wishlists, and manage your luxury shopping bag.</p>
                </div>

                <?php if (!empty($error)): ?>
                    <div class="alert alert-danger py-2 small mb-4"><?= e($error) ?></div>
                <?php endif; ?>

                <form method="POST" action="<?= BASE_URL ?>/register.php">
                    <?= csrf_field() ?>
                    <input type="hidden" name="redirect" value="<?= e($redirect) ?>">

                    <div class="row g-3 mb-3">
                        <div class="col-sm-6">
                            <label for="first_name" class="form-label small fw-bold text-uppercase" style="letter-spacing:0.06em;">First Name</label>
                            <input type="text" id="first_name" name="first_name" class="form-control" value="<?= e($_POST['first_name'] ?? '') ?>" required>
                        </div>
                        <div class="col-sm-6">
                            <label for="last_name" class="form-label small fw-bold text-uppercase" style="letter-spacing:0.06em;">Last Name</label>
                            <input type="text" id="last_name" name="last_name" class="form-control" value="<?= e($_POST['last_name'] ?? '') ?>" required>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="email" class="form-label small fw-bold text-uppercase" style="letter-spacing:0.06em;">Email Address</label>
                        <input type="email" id="email" name="email" class="form-control" placeholder="you@domain.com" value="<?= e($_POST['email'] ?? '') ?>" required>
                    </div>

                    <div class="mb-3">
                        <label for="phone" class="form-label small fw-bold text-uppercase" style="letter-spacing:0.06em;">Phone Number</label>
                        <input type="tel" id="phone" name="phone" class="form-control" placeholder="+92 300 1234567" value="<?= e($_POST['phone'] ?? '') ?>" required>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-sm-6">
                            <label for="password" class="form-label small fw-bold text-uppercase" style="letter-spacing:0.06em;">Password</label>
                            <input type="password" id="password" name="password" class="form-control" placeholder="Min. 6 chars" required>
                        </div>
                        <div class="col-sm-6">
                            <label for="confirm_password" class="form-label small fw-bold text-uppercase" style="letter-spacing:0.06em;">Confirm Password</label>
                            <input type="password" id="confirm_password" name="confirm_password" class="form-control" placeholder="Re-enter password" required>
                        </div>
                    </div>

                    <button type="submit" class="btn-valenor-primary w-100 justify-content-center mt-3 py-2">
                        Complete Registration &rarr;
                    </button>
                </form>

                <div class="mt-4 pt-3 text-center border-top small" style="border-color:var(--border-beige) !important;">
                    <span class="text-muted">Already have a VALENOR account?</span>
                    <a href="<?= BASE_URL ?>/login.php?redirect=<?= urlencode($redirect) ?>" class="fw-bold ms-1" style="color:var(--caramel-dark);">Sign In</a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once INCLUDES_PATH . '/footer.php'; ?>

