<?php
// ============================================================
// register.php — ToolShare Hub Registration
// ============================================================
session_start();
require_once 'db.php';

if (isset($_SESSION['member_id'])) {
    header('Location: user_dashboard.php'); exit;
}

$areas  = fetchAll("SELECT * FROM Area ORDER BY area_name");
$errors = [];
$data   = ['name'=>'','email'=>'','phone'=>'','area_id'=>''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data['name']    = trim($_POST['name'] ?? '');
    $data['email']   = trim($_POST['email'] ?? '');
    $data['phone']   = trim($_POST['phone'] ?? '');
    $data['area_id'] = (int)($_POST['area_id'] ?? 0);
    $pass            = trim($_POST['password'] ?? '');
    $pass2           = trim($_POST['password2'] ?? '');

    // Validate
    if (!$data['name'])             $errors[] = 'Full name is required.';
    if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) $errors[] = 'Invalid email address.';
    if (!$data['area_id'])          $errors[] = 'Please select your area.';
    if (strlen($pass) < 6)          $errors[] = 'Password must be at least 6 characters.';
    if ($pass !== $pass2)           $errors[] = 'Passwords do not match.';

    // Check email uniqueness
    if (!$errors) {
        $em = e($data['email']);
        $exists = fetchOne("SELECT member_id FROM Member WHERE email='$em'");
        if ($exists) $errors[] = 'That email is already registered. Try logging in.';
    }

    // Create account
    if (!$errors) {
        $hash = password_hash($pass, PASSWORD_BCRYPT);
        $nm   = e($data['name']);
        $em   = e($data['email']);
        $ph   = e($data['phone']);
        $aid  = $data['area_id'];

        $sql = "INSERT INTO Member (name, email, phone, password, area_id, role)
                VALUES ('$nm', '$em', '$ph', '$hash', $aid, 'Member')";
        q($sql);
        $new_id = mysqli_insert_id($GLOBALS['conn']);

        // Auto-login
        session_regenerate_id(true);
        $_SESSION['member_id'] = $new_id;
        $_SESSION['name']      = $data['name'];
        $_SESSION['email']     = $data['email'];
        $_SESSION['role']      = 'Member';
        $_SESSION['area_id']   = $aid;
        $_SESSION['is_banned'] = 0;

        setFlash('success', 'Welcome to ToolShare Hub, ' . $data['name'] . '! 🎉');
        header('Location: user_dashboard.php'); exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register — ToolShare Hub</title>
    <link rel="stylesheet" href="assets/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body>
<div class="auth-page">

    <div class="auth-left">
        <div class="auth-brand">
            <div class="brand-icon"><i class="fa-solid fa-wrench"></i></div>
            <h1>Join ToolShare Hub</h1>
            <p>Create your free account and start borrowing or lending tools with your community.</p>
        </div>
        <div class="auth-features">
            <div class="auth-feature">
                <div class="auth-feature-icon"><i class="fa-solid fa-list-check"></i></div>
                <div class="auth-feature-text">
                    <h4>List Your Tools</h4>
                    <p>Tools sitting idle? List them — your neighbours might need them this weekend.</p>
                </div>
            </div>
            <div class="auth-feature">
                <div class="auth-feature-icon"><i class="fa-solid fa-coins"></i></div>
                <div class="auth-feature-text">
                    <h4>Save Money</h4>
                    <p>Why buy a tool you use twice? Borrow instead. Free for the community.</p>
                </div>
            </div>
            <div class="auth-feature">
                <div class="auth-feature-icon"><i class="fa-solid fa-map-marker-alt"></i></div>
                <div class="auth-feature-text">
                    <h4>Area-Based</h4>
                    <p>Tools listed by people near you. Less travel, faster borrowing.</p>
                </div>
            </div>
        </div>
    </div>

    <div class="auth-right" style="overflow-y:auto;">
        <h2>Create your account</h2>
        <p class="subtitle">Join thousands of neighbours sharing tools</p>

        <?php if ($errors): ?>
        <div class="flash-msg flash-error">
            <?php foreach($errors as $e): ?>
            <div><?= htmlspecialchars($e) ?></div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <form method="POST" action="">
            <div class="form-group">
                <label class="form-label">Full Name <span>*</span></label>
                <input type="text" name="name" class="form-control" placeholder="Rahim Uddin"
                       value="<?= htmlspecialchars($data['name']) ?>" required>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Email Address <span>*</span></label>
                    <input type="email" name="email" class="form-control" placeholder="you@email.com"
                           value="<?= htmlspecialchars($data['email']) ?>" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Phone Number</label>
                    <input type="text" name="phone" class="form-control" placeholder="017XXXXXXXX"
                           value="<?= htmlspecialchars($data['phone']) ?>">
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">Your Area <span>*</span></label>
                <select name="area_id" class="form-control" required>
                    <option value="">— Select your area —</option>
                    <?php foreach($areas as $a): ?>
                    <option value="<?= $a['area_id'] ?>" <?= $data['area_id']==$a['area_id']?'selected':'' ?>>
                        <?= htmlspecialchars($a['area_name']) ?>, <?= htmlspecialchars($a['district']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Password <span>*</span></label>
                    <input type="password" name="password" class="form-control" placeholder="Min. 6 characters" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Confirm Password <span>*</span></label>
                    <input type="password" name="password2" class="form-control" placeholder="Repeat password" required>
                </div>
            </div>

            <p style="font-size:.8rem;color:var(--text-muted);margin-bottom:16px;">
                By registering, you agree to use this platform responsibly and return borrowed tools on time.
            </p>

            <button type="submit" class="btn btn-primary btn-lg btn-full">
                <i class="fa-solid fa-user-plus"></i> Create Account
            </button>
        </form>

        <div class="auth-divider" style="margin-top:20px;">
            Already have an account? <a href="login.php" style="font-weight:600;">Sign in →</a>
        </div>
    </div>
</div>
</body>
</html>