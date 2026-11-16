<?php
// =============================================================================
// VALENOR HOROLOGY — RESET PASSWORD (Phase 07)
// Token verification, secure bcrypt update & reset token invalidation
// =============================================================================

require_once __DIR__ . '/config/config.php';

$token = sanitize($_GET['token'] ?? ($_POST['token'] ?? ''));
$error = '';
$customer = null;

$db = getDB();

if (empty($token)) {
    $error = 'Password reset token is missing or malformed.';
} else {
    $stmt = $db->prepare("
        SELECT id, first_name, email 
        FROM customers 
        WHERE reset_token = ? AND reset_token_expires_at > NOW() AND status = 'active'
        LIMIT 1
    ");
    $stmt->execute([$token]);
    $customer = $stmt->fetch();

    if (!$customer) {
        $error = 'This password reset link is invalid or has expired. Please request a new recovery link.';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $customer) {
    if (!verify_csrf()) {
        $error = 'Security session expired. Please resubmit the form.';
    } else {
        $newPassword     = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';

        if (strlen($newPassword) < 6) {
            $error = 'Password must contain at least 6 characters.';
        } elseif ($newPassword !== $confirmPassword) {
            $error = 'Passwords do not match.';
        } else {
            $hash = password_hash($newPassword, PASSWORD_BCRYPT);
            $stmt = $db->prepare("
                UPDATE customers 
                SET password_hash = ?, reset_token = NULL, reset_token_expires_at = NULL, updated_at = NOW() 
                WHERE id = ?
            ");
            $stmt->execute([$hash, $customer['id']]);

            set_flash('success', 'Your password has been updated successfully. Please sign in with your new credentials.');
            header('Location: ' . BASE_URL . '/login.php');
            exit;
        }
    }
}

$pageTitle = 'Reset Password — Valenor Concierge';
require_once INCLUDES_PATH . '/header.php';
require_once INCLUDES_PATH . '/navbar.php';
?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-7 col-lg-5">
            <div class="card p-4 p-md-5 border bg-white shadow-sm" style="border-color:var(--border-beige) !important; border-radius:8px;">
                <div class="text-center mb-4">
                    <span class="text-uppercase fw-bold" style="color:var(--caramel-primary); letter-spacing:0.18em; font-size:0.75rem;">Security Credentials</span>
                    <h1 class="font-serif fw-bold h2 mt-1 mb-2">Set New Password</h1>
                    <p class="text-muted small">
                        <?php if ($customer): ?>
                            Welcome, <strong><?= e($customer['first_name']) ?></strong>. Enter your new account password below.
                        <?php else: ?>
                            Recover your luxury collector account credentials.
                        <?php endif; ?>
                    </p>
                </div>

                <?php if (!empty($error)): ?>
                    <div class="alert alert-danger py-2 small mb-4"><?= e($error) ?></div>
                <?php endif; ?>

                <?php if ($customer): ?>
                    <form method="POST" action="<?= BASE_URL ?>/reset-password.php">
                        <?= csrf_field() ?>
                        <input type="hidden" name="token" value="<?= e($token) ?>">

                        <div class="mb-3">
                            <label for="new_password" class="form-label small fw-bold text-uppercase" style="letter-spacing:0.06em;">New Password</label>
                            <input type="password" id="new_password" name="new_password" class="form-control" placeholder="••••••••" minlength="6" required autofocus>
                            <div class="form-text small" style="font-size:0.72rem;">Minimum 6 characters.</div>
                        </div>

                        <div class="mb-4">
                            <label for="confirm_password" class="form-label small fw-bold text-uppercase" style="letter-spacing:0.06em;">Confirm New Password</label>
                            <input type="password" id="confirm_password" name="confirm_password" class="form-control" placeholder="••••••••" minlength="6" required>
                        </div>

                        <button type="submit" class="btn-valenor-primary w-100 justify-content-center py-2">
                            Update Password &rarr;
                        </button>
                    </form>
                <?php else: ?>
                    <div class="text-center mt-3">
                        <a href="<?= BASE_URL ?>/forgot-password.php" class="btn btn-sm btn-dark rounded-pill px-4 py-2">
                            Request New Link &rarr;
                        </a>
                    </div>
                <?php endif; ?>

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

