<?php
// =============================================================================
// VALENOR HOROLOGY — ADMIN SECURE LOGIN (Security & Auth)
// =============================================================================

require_once __DIR__ . '/../config/config.php';

if (is_admin_logged_in()) {
    header('Location: ' . ADMIN_URL . '/index.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) {
        $error = 'Invalid security token. Please refresh the page and try again.';
    } else {
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        if (empty($email) || empty($password)) {
            $error = 'Please enter both your email address and password.';
        } else {
            $db = getDB();
            $stmt = $db->prepare("SELECT * FROM admins WHERE email = ? LIMIT 1");
            $stmt->execute([$email]);
            $admin = $stmt->fetch();

            if ($admin && password_verify($password, $admin['password_hash'])) {
                if ($admin['status'] !== 'active') {
                    $error = 'This administrator account has been deactivated. Please consult system governance.';
                } else {
                    // Regenerate session ID for security
                    session_regenerate_id(true);
                    
                    $_SESSION['admin_id']   = (int)$admin['id'];
                    $_SESSION['admin_name'] = $admin['name'];
                    $_SESSION['admin_role'] = $admin['role'];

                    set_flash('success', 'Welcome back, ' . e($admin['name']) . '. You are authenticated as ' . e($admin['role']) . '.');
                    header('Location: ' . ADMIN_URL . '/index.php');
                    exit;
                }
            } else {
                $error = 'Invalid credentials. Please verify your administrative email and password.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff Authentication — VALENOR Horology</title>
    <link rel="icon" type="image/svg+xml" href="<?= BASE_URL ?>/assets/images/logo.svg">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@600;700&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    
    <style>
        :root {
            --caramel-primary: #B8895B;
            --caramel-dark: #8B6342;
            --bg-cream: #F8F3EB;
            --text-charcoal: #252525;
            --border-beige: #E8DED2;
            --font-serif: 'Cormorant Garamond', Georgia, serif;
            --font-sans: 'Inter', -apple-system, sans-serif;
        }

        body {
            font-family: var(--font-sans);
            background-color: #171513;
            color: #f5ede6;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .login-card {
            width: 100%;
            max-width: 440px;
            background: #231f1c;
            border: 1px solid rgba(232, 222, 210, 0.15);
            border-radius: 12px;
            padding: 40px 32px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.4);
        }

        .btn-caramel {
            background-color: var(--caramel-primary);
            color: #fff;
            border: none;
            font-weight: 600;
            padding: 12px;
            border-radius: 6px;
            letter-spacing: 0.05em;
            transition: all 0.2s ease;
        }

        .btn-caramel:hover {
            background-color: var(--caramel-dark);
            color: #fff;
        }

        .form-control {
            background: #171513;
            border: 1px solid rgba(232, 222, 210, 0.2);
            color: #fff;
            padding: 12px 14px;
        }

        .form-control:focus {
            background: #171513;
            border-color: var(--caramel-primary);
            color: #fff;
            box-shadow: 0 0 0 0.25rem rgba(184, 137, 91, 0.25);
        }
    </style>
</head>
<body>

<div class="login-card">
    <div class="text-center mb-4">
        <div class="font-serif fw-bold display-6 tracking-wide text-white" style="letter-spacing:0.18em;">VALENOR</div>
        <div class="text-uppercase small fw-bold mt-1" style="color:var(--caramel-primary); letter-spacing:0.12em; font-size:0.75rem;">
            Concierge Staff &amp; Operations Portal
        </div>
    </div>

    <?= display_flash() ?>

    <?php if (!empty($error)): ?>
        <div class="alert alert-danger py-2 small mb-4" role="alert">
            <i class="bi bi-shield-x me-1"></i> <?= e($error) ?>
        </div>
    <?php endif; ?>

    <form method="POST" action="<?= ADMIN_URL ?>/login.php">
        <?= csrf_field() ?>

        <div class="mb-3">
            <label for="adminEmail" class="form-label small fw-bold text-uppercase" style="letter-spacing:0.06em; color:#c9bfb5;">Administrator Email</label>
            <div class="input-group">
                <span class="input-group-text bg-dark border-secondary text-secondary"><i class="bi bi-envelope"></i></span>
                <input type="email" name="email" id="adminEmail" class="form-control" placeholder="admin@valenor.com" value="<?= e($_POST['email'] ?? 'admin@valenor.com') ?>" required autofocus>
            </div>
        </div>

        <div class="mb-4">
            <label for="adminPassword" class="form-label small fw-bold text-uppercase" style="letter-spacing:0.06em; color:#c9bfb5;">Password</label>
            <div class="input-group">
                <span class="input-group-text bg-dark border-secondary text-secondary"><i class="bi bi-key"></i></span>
                <input type="password" name="password" id="adminPassword" class="form-control" placeholder="••••••••••••" value="Admin@12345" required>
            </div>
            <div class="form-text text-muted small mt-1">Default credentials prefilled for evaluation: <code>Admin@12345</code></div>
        </div>

        <button type="submit" class="btn btn-caramel w-100 text-uppercase fw-bold">
            <i class="bi bi-shield-lock me-1"></i> Authenticate &amp; Access Dashboard
        </button>
    </form>

    <div class="text-center mt-4 pt-3 border-top border-secondary small text-muted">
        <a href="<?= BASE_URL ?>/index.php" class="text-decoration-none text-secondary">
            <i class="bi bi-arrow-left me-1"></i> Return to Public Storefront
        </a>
    </div>
</div>

</body>
</html>
