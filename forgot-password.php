<?php
// =============================================================================
// VALENOR HOROLOGY — FORGOT PASSWORD (Phase 07)
// Secure password reset token generation & email verification
// =============================================================================

require_once __DIR__ . '/config/config.php';

$error = '';
$success = '';
$resetUrl = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) {
        $error = 'Security session expired. Please resubmit the form.';
    } else {
        $email = sanitize($_POST['email'] ?? '');

        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Please provide a valid registered email address.';
        } else {
            $db = getDB();
            $stmt = $db->prepare("SELECT id, first_name, email FROM customers WHERE email = ? AND status = 'active' LIMIT 1");
            $stmt->execute([$email]);
            $customer = $stmt->fetch();

            if ($customer) {
                // Generate secure 64-character token valid for 1 hour
                $token = bin2hex(random_bytes(32));
                $stmt = $db->prepare("
                    UPDATE customers 
                    SET reset_token = ?, reset_token_expires_at = DATE_ADD(NOW(), INTERVAL 1 HOUR) 
                    WHERE id = ?
                ");
                $stmt->execute([$token, $customer['id']]);

                $resetUrl = BASE_URL . '/reset-password.php?token=' . $token;
                $success = 'Password reset instructions have been dispatched. Click the secure link below to reset your credentials.';
            } else {
                // Generic confirmation to prevent email enumeration
                $success = 'If an active account exists for that email, a password reset link has been dispatched.';
            }
        }
    }
}

$pageTitle = 'Forgot Password — Account Recovery';
require_once INCLUDES_PATH . '/header.php';
require_once INCLUDES_PATH . '/navbar.php';
?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-7 col-lg-5">
            <div class="card p-4 p-md-5 border bg-white shadow-sm" style="border-color:var(--border-beige) !important; border-radius:8px;">
                <div class="text-center mb-4">
                    <span class="text-uppercase fw-bold" style="color:var(--caramel-primary); letter-spacing:0.18em; font-size:0.75rem;">Account Concierge</span>
                    <h1 class="font-serif fw-bold h2 mt-1 mb-2">Reset Password</h1>
                    <p class="text-muted small">Enter your registered email address to receive secure access credentials.</p>
                </div>

                <?php if (!empty($error)): ?>
                    <div class="alert alert-danger py-2 small mb-4"><?= e($error) ?></div>
                <?php endif; ?>

                <?php if (!empty($success)): ?>
                    <div class="alert alert-success py-3 px-3 small mb-4" role="alert" style="background-color:var(--bg-cream); border-color:var(--border-beige); color:var(--text-dark);">
                        <div class="d-flex align-items-center gap-2 mb-2">
                            <i class="bi bi-shield-check fs-5" style="color:var(--caramel-primary);"></i>
                            <strong><?= e($success) ?></strong>
                        </div>
                        <?php if (!empty($resetUrl)): ?>
                            <div class="p-3 bg-white rounded border mt-2" style="border-color:var(--border-beige) !important;">
                                <div class="small text-muted mb-1">Direct Secure Recovery Link:</div>
                                <a href="<?= e($resetUrl) ?>" class="btn btn-sm btn-valenor-primary w-100 text-center text-truncate">
                                    Proceed to Password Reset &rarr;
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <form method="POST" action="<?= BASE_URL ?>/forgot-password.php">
                    <?= csrf_field() ?>

                    <div class="mb-3">
                        <label for="email" class="form-label small fw-bold text-uppercase" style="letter-spacing:0.06em;">Registered Email Address</label>
                        <input type="email" id="email" name="email" class="form-control" placeholder="customer@valenor.com" value="<?= e($_POST['email'] ?? '') ?>" required autofocus>
                    </div>

                    <button type="submit" class="btn-valenor-primary w-100 justify-content-center mt-3 py-2">
                        Send Recovery Link &rarr;
                    </button>
                </form>

                <div class="text-center mt-4 pt-3 border-top" style="border-color:var(--border-beige) !important;">
                    <a href="<?= BASE_URL ?>/login.php" class="small text-muted text-decoration-none">
                        &larr; Return to Sign In
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once INCLUDES_PATH . '/footer.php'; ?>
