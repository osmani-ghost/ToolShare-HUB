<?php
// ============================================================
// login.php — ToolShare Hub Authentication
// ============================================================
session_start();
require_once 'db.php';

// If already logged in, redirect to correct dashboard
if (isset($_SESSION['member_id'])) {
    header('Location: ' . ($_SESSION['role'] === 'Admin' ? 'admin_dashboard.php' : 'user_dashboard.php'));
    exit;
}

$error = '';

// ── PROCESS LOGIN ─────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $pass  = trim($_POST['password'] ?? '');

    if (!$email || !$pass) {
        $error = 'Please fill in both fields.';
    } else {
        $em  = e($email);
        $row = fetchOne("SELECT * FROM Member WHERE email = '$em' LIMIT 1");

        if (!$row) {
            $error = 'No account found with that email.';
        } elseif ($row['is_banned']) {
            $error = '🚫 Your account has been banned. Contact admin for help.';
        } elseif (!password_verify($pass, $row['password'])) {
            $error = 'Incorrect password. Try again.';
        } else {
            // ✅ Login successful
            session_regenerate_id(true);
            $_SESSION['member_id'] = $row['member_id'];
            $_SESSION['name']      = $row['name'];
            $_SESSION['email']     = $row['email'];
            $_SESSION['role']      = $row['role'];
            $_SESSION['area_id']   = $row['area_id'];
            $_SESSION['is_banned'] = $row['is_banned'];

            $dest = $row['role'] === 'Admin' ? 'admin_dashboard.php' : 'user_dashboard.php';
            header("Location: $dest");
            exit;
        }
    }
}

$banned = isset($_GET['banned']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login — ToolShare Hub</title>
    <link rel="stylesheet" href="assets/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        .auth-page { padding-top: 0; }
        .form-check { display: flex; align-items: center; gap: 8px; margin-bottom: 18px; }
        .form-check input { width: auto; accent-color: var(--primary); }
        .form-check label { font-size: 0.875rem; color: var(--text-muted); }
        .forgot { font-size: 0.84rem; color: var(--primary); }
    </style>
</head>
<body>
<div class="auth-page">

    <!-- ── LEFT PANEL ─────────────────────────────────── -->
    <div class="auth-left">
        <div class="auth-brand">
            <div class="brand-icon"><i class="fa-solid fa-wrench"></i></div>
            <h1>ToolShare Hub</h1>
            <p>Bangladesh's community-first tool lending platform. Share tools, build trust, save money.</p>
        </div>
        <div class="auth-features">
            <div class="auth-feature">
                <div class="auth-feature-icon"><i class="fa-solid fa-handshake"></i></div>
                <div class="auth-feature-text">
                    <h4>Borrow from Neighbours</h4>
                    <p>Find tools listed by people in your area — drills, ladders, and more.</p>
                </div>
            </div>
            <div class="auth-feature">
                <div class="auth-feature-icon"><i class="fa-solid fa-star"></i></div>
                <div class="auth-feature-text">
                    <h4>Trust-Based Ratings</h4>
                    <p>Both parties rate each loan. High-rated members get faster approvals.</p>
                </div>
            </div>
            <div class="auth-feature">
                <div class="auth-feature-icon"><i class="fa-solid fa-shield-halved"></i></div>
                <div class="auth-feature-text">
                    <h4>Protected & Transparent</h4>
                    <p>Damage reports, condition history, and admin oversight keep things fair.</p>
                </div>
            </div>
        </div>
    </div>

    <!-- ── RIGHT PANEL ────────────────────────────────── -->
    <div class="auth-right">
        <h2>Welcome back 👋</h2>
        <p class="subtitle">Sign in to your ToolShare account</p>

        <?php if ($banned): ?>
        <div class="flash-msg flash-error">🚫 Your account has been suspended. Contact admin@toolshare.com for support.</div>
        <?php endif; ?>

        <?php if ($error): ?>
        <div class="flash-msg flash-error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST" action="">
            <div class="form-group">
                <label class="form-label">Email Address</label>
                <input type="email" name="email" class="form-control" placeholder="you@email.com"
                       value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required autofocus>
            </div>

            <div class="form-group">
                <label class="form-label">Password</label>
                <input type="password" name="password" class="form-control" placeholder="Enter your password" required>
            </div>

            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;">
                <div class="form-check" style="margin-bottom:0;">
                    <input type="checkbox" id="remember" name="remember">
                    <label for="remember">Remember me</label>
                </div>
            </div>

            <button type="submit" class="btn btn-primary btn-lg btn-full">
                <i class="fa-solid fa-arrow-right-to-bracket"></i> Sign In
            </button>
        </form>

        <div class="auth-divider" style="margin-top:24px;">
            Don't have an account?
            <a href="register.php" style="font-weight:600;">Create one free →</a>
        </div>

        <div style="margin-top:32px;padding-top:24px;border-top:1px solid var(--border);">
            <p style="font-size:0.78rem;color:var(--text-light);margin-bottom:8px;font-weight:600;text-transform:uppercase;letter-spacing:.06em;">Demo Credentials</p>
            <div style="display:grid;gap:8px;">
                <div style="background:var(--bg);border-radius:8px;padding:10px 14px;">
                    <span class="badge badge-danger" style="margin-bottom:4px;">Admin</span>
                    <div style="font-size:0.8rem;color:var(--text-muted);font-family:'DM Mono',monospace;">
                        admin@toolshare.com / admin123
                    </div>
                </div>
                <div style="background:var(--bg);border-radius:8px;padding:10px 14px;">
                    <span class="badge badge-primary" style="margin-bottom:4px;">Member</span>
                    <div style="font-size:0.8rem;color:var(--text-muted);font-family:'DM Mono',monospace;">
                        rahim@email.com / password
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<script src="assets/app.js"></script>
</body>
</html>