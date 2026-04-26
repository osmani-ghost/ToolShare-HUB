<?php
// ============================================================
// db.php — ToolShare Hub Database Connection & Helpers
// ============================================================

// ── CONFIGURE THESE ─────────────────────────────────────────
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');          // Default XAMPP has no password
define('DB_NAME', 'toolshare_hub');
define('SITE_URL', 'http://localhost/toolshare');
// ────────────────────────────────────────────────────────────

$conn = mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);

if (!$conn) {
    die('
    <div style="font-family:sans-serif;padding:60px;max-width:600px;margin:40px auto;background:#FEF2F2;border:1px solid #FECACA;border-radius:16px;">
        <h2 style="color:#DC2626;margin:0 0 12px">❌ Database Connection Failed</h2>
        <p style="color:#7F1D1D;margin:0 0 8px"><strong>Error:</strong> ' . mysqli_connect_error() . '</p>
        <p style="color:#991B1B;margin:0">Make sure XAMPP MySQL is running and the database <strong>toolshare_hub</strong> exists.</p>
    </div>');
}

mysqli_set_charset($conn, 'utf8mb4');

// ── HELPER FUNCTIONS ─────────────────────────────────────────

/**
 * Escape a string for safe SQL use
 */
function e($val)
{
    global $conn;
    return mysqli_real_escape_string($conn, trim((string)$val));
}

/**
 * Run a SQL query and return the result (or false on failure)
 */
function q($sql)
{
    global $conn;
    $r = mysqli_query($conn, $sql);
    if (!$r) {
        // Log error — in production, never show raw SQL errors to users
        error_log('[ToolShare SQL Error] ' . mysqli_error($conn) . ' | SQL: ' . $sql);
    }
    return $r;
}

/**
 * Fetch all rows from a SELECT query into an array
 */
function fetchAll($sql)
{
    $result = q($sql);
    if (!$result) return [];
    $rows = [];
    while ($row = mysqli_fetch_assoc($result)) $rows[] = $row;
    return $rows;
}

/**
 * Fetch a single row from a SELECT query
 */
function fetchOne($sql)
{
    $result = q($sql);
    if (!$result) return null;
    return mysqli_fetch_assoc($result);
}

/**
 * Set a flash message to display on next page load
 */
function setFlash($type, $message)
{
    $_SESSION['flash'] = ['type' => $type, 'msg' => $message];
}

/**
 * Render and clear the current flash message
 */
function showFlash()
{
    if (!isset($_SESSION['flash'])) return '';
    $f = $_SESSION['flash'];
    unset($_SESSION['flash']);
    $icons = [
        'success' => '✅',
        'error'   => '❌',
        'warning' => '⚠️',
        'info'    => 'ℹ️',
    ];
    $classes = [
        'success' => 'flash-success',
        'error'   => 'flash-error',
        'warning' => 'flash-warning',
        'info'    => 'flash-info',
    ];
    $icon = $icons[$f['type']] ?? 'ℹ️';
    $cls  = $classes[$f['type']] ?? 'flash-info';
    return "<div class='flash-msg {$cls}'>{$icon} " . htmlspecialchars($f['msg']) . "</div>";
}

/**
 * Redirect to login if not authenticated
 */
function requireLogin()
{
    if (!isset($_SESSION['member_id'])) {
        header('Location: ' . SITE_URL . '/login.php');
        exit;
    }
    if ($_SESSION['is_banned'] ?? false) {
        session_destroy();
        header('Location: ' . SITE_URL . '/login.php?banned=1');
        exit;
    }
}

/**
 * Refresh is_banned status from DB on every request.
 * Ensures a banned user is logged out even mid-session.
 */
function refreshBanStatus()
{
    if (!isset($_SESSION['member_id'])) return;
    global $conn;
    $uid = (int)$_SESSION['member_id'];
    $row = mysqli_fetch_assoc(mysqli_query(
        $conn,
        "SELECT is_banned FROM Member WHERE member_id=$uid LIMIT 1"
    ));
    if ($row && $row['is_banned']) {
        session_destroy();
        header('Location: login.php?banned=1');
        exit;
    }
}
/**
 * Redirect non-admins away
 */
function requireAdmin()
{
    if (!isset($_SESSION['member_id']) || ($_SESSION['role'] ?? '') !== 'Admin') {
        header('Location: ' . SITE_URL . '/login.php');
        exit;
    }
}

// ── DISPLAY HELPERS ──────────────────────────────────────────

/**
 * Return a Font Awesome icon class for a tool category
 */
function categoryIcon($name)
{
    $map = [
        'Power Tools'        => 'fa-solid fa-plug-circle-bolt',
        'Hand Tools'         => 'fa-solid fa-wrench',
        'Garden Tools'       => 'fa-solid fa-seedling',
        'Kitchen Appliances' => 'fa-solid fa-blender',
        'Cleaning Equipment' => 'fa-solid fa-broom',
        'Electrical'         => 'fa-solid fa-bolt',
        'Plumbing'           => 'fa-solid fa-faucet-drip',
        'Painting'           => 'fa-solid fa-paint-brush',
        'Automotive'         => 'fa-solid fa-car-side',
        'Outdoor & Camping'  => 'fa-solid fa-tent',
    ];
    return $map[$name] ?? 'fa-solid fa-toolbox';
}

/**
 * Return an HTML status badge
 */
function badge($status)
{
    $map = [
        'Available'        => 'badge-success',
        'On Loan'          => 'badge-primary',
        'Needs Inspection' => 'badge-warning',
        'Lost'             => 'badge-danger',
        'Active'           => 'badge-info',
        'Returned'         => 'badge-success',
        'Overdue'          => 'badge-danger',
        'Pending'          => 'badge-warning',
        'Approved'         => 'badge-success',
        'Rejected'         => 'badge-danger',
        'Cancelled'        => 'badge-muted',
        'New'              => 'badge-info',
        'Good'             => 'badge-success',
        'Fair'             => 'badge-warning',
        'Damaged'          => 'badge-danger',
        'Minor'            => 'badge-warning',
        'Moderate'         => 'badge-orange',
        'Severe'           => 'badge-danger',
    ];
    $cls = $map[$status] ?? 'badge-muted';
    return "<span class='badge {$cls}'>" . htmlspecialchars($status) . "</span>";
}

/**
 * Render star rating HTML
 */
function stars($rating, $count = null)
{
    $full = (int)round($rating);
    $html = '<span class="star-row">';
    for ($i = 1; $i <= 5; $i++) {
        $html .= $i <= $full ? '<i class="fa-solid fa-star star-on"></i>' : '<i class="fa-regular fa-star star-off"></i>';
    }
    $html .= '</span>';
    if ($count !== null) $html .= " <small class='text-muted'>({$count})</small>";
    return $html;
}

/**
 * Time ago format
 */
function timeAgo($datetime)
{
    $diff = time() - strtotime($datetime);
    if ($diff < 60)     return 'just now';
    if ($diff < 3600)   return (int)($diff / 60) . 'm ago';
    if ($diff < 86400)  return (int)($diff / 3600) . 'h ago';
    if ($diff < 604800) return (int)($diff / 86400) . 'd ago';
    return date('d M Y', strtotime($datetime));
}

/**
 * Truncate text with ellipsis
 */
function trunc($text, $len = 80)
{
    return strlen($text) > $len ? substr($text, 0, $len) . '…' : $text;
}
