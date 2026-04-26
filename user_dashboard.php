<?php
// ============================================================
// user_dashboard.php — ToolShare Hub Member Dashboard
// All member features in one file, section-based navigation
// ============================================================
session_start();
require_once 'db.php';
requireLogin();

$uid     = (int)$_SESSION['member_id'];
$section = $_GET['section'] ?? 'overview';
$member  = fetchOne("SELECT m.*, a.area_name, a.district FROM Member m JOIN Area a ON m.area_id=a.area_id WHERE m.member_id=$uid");

// Count badges for sidebar
$pending_incoming = fetchOne("SELECT COUNT(*) AS cnt FROM Borrow_Request br JOIN Tool t ON br.tool_id=t.tool_id WHERE t.owner_id=$uid AND br.status='Pending'")['cnt'];
$pending_sent     = fetchOne("SELECT COUNT(*) AS cnt FROM Borrow_Request WHERE requester_id=$uid AND status='Pending'")['cnt'];
$active_loans     = fetchOne("SELECT COUNT(*) AS cnt FROM Loan_Record WHERE borrower_id=$uid AND status='Active'")['cnt'];
$overdue_mine     = fetchOne("SELECT COUNT(*) AS cnt FROM Loan_Record WHERE borrower_id=$uid AND status='Active' AND expected_return < CURDATE()")['cnt'];

$categories = fetchAll("SELECT * FROM Category ORDER BY name");
$areas      = fetchAll("SELECT * FROM Area ORDER BY area_name");

// Page titles
$titles = [
    'overview'  => 'Overview',
    'browse'    => 'Browse Tools',
    'requests'  => 'My Requests',
    'loans'     => 'My Loans',
    'my_tools'  => 'My Tools',
    'incoming'  => 'Incoming Requests',
    'history'   => 'Lending History',
    'profile'   => 'My Profile',
];
$pageTitle = $titles[$section] ?? 'Dashboard';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?> — ToolShare Hub</title>
    <link rel="stylesheet" href="assets/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>

<body>
    <div class="layout">

        <!-- ── SIDEBAR ──────────────────────────────────────────────── -->
        <div class="sidebar-overlay" id="sidebar-overlay"></div>
        <aside class="sidebar" id="sidebar">
            <div class="sidebar-logo">
                <div class="logo-icon"><i class="fa-solid fa-wrench"></i></div>
                <div>
                    <div class="logo-text">ToolShare Hub</div>
                    <span class="logo-sub">Community Lending</span>
                </div>
            </div>

            <nav class="sidebar-nav">
                <div class="sidebar-section">Main</div>
                <a href="?section=overview" class="nav-link <?= $section == 'overview' ? 'active' : '' ?>"><i class="fa-solid fa-grid-2"></i> Overview</a>
                <a href="?section=browse" class="nav-link <?= $section == 'browse' ? 'active' : '' ?>"><i class="fa-solid fa-magnifying-glass"></i> Browse Tools</a>

                <div class="sidebar-section">My Activity</div>
                <a href="?section=requests" class="nav-link <?= $section == 'requests' ? 'active' : '' ?>">
                    <i class="fa-regular fa-paper-plane"></i> My Requests
                    <?php if ($pending_sent): ?><span class="badge-count"><?= $pending_sent ?></span><?php endif; ?>
                </a>
                <a href="?section=loans" class="nav-link <?= $section == 'loans' ? 'active' : '' ?>">
                    <i class="fa-solid fa-arrow-right-arrow-left"></i> My Loans
                    <?php if ($overdue_mine): ?><span class="badge-count"><?= $overdue_mine ?></span><?php endif; ?>
                </a>

                <div class="sidebar-section">My Tools</div>
                <a href="?section=my_tools" class="nav-link <?= $section == 'my_tools' ? 'active' : '' ?>"><i class="fa-solid fa-toolbox"></i> My Listings</a>
                <a href="?section=incoming" class="nav-link <?= $section == 'incoming' ? 'active' : '' ?>">
                    <i class="fa-solid fa-inbox"></i> Incoming Requests
                    <?php if ($pending_incoming): ?><span class="badge-count"><?= $pending_incoming ?></span><?php endif; ?>
                </a>
                <a href="?section=history" class="nav-link <?= $section == 'history' ? 'active' : '' ?>"><i class="fa-regular fa-clock"></i> Lending History</a>

                <div class="sidebar-section">Account</div>
                <a href="?section=profile" class="nav-link <?= $section == 'profile' ? 'active' : '' ?>"><i class="fa-regular fa-user"></i> Profile & Settings</a>
            </nav>

            <div class="sidebar-footer">
                <div class="sidebar-user">
                    <div class="avatar"><?= strtoupper(substr($member['name'], 0, 1)) ?></div>
                    <div class="sidebar-user-info">
                        <div class="sidebar-user-name"><?= htmlspecialchars($member['name']) ?></div>
                        <div class="sidebar-user-role"><?= $member['area_name'] ?></div>
                    </div>
                </div>
                <a href="logout.php" class="nav-link" style="margin-top:6px;"><i class="fa-solid fa-arrow-right-from-bracket"></i> Logout</a>
            </div>
        </aside>

        <!-- ── MAIN ────────────────────────────────────────────────── -->
        <div class="main">
            <header class="topbar">
                <button class="hamburger" id="hamburger"><i class="fa-solid fa-bars"></i></button>
                <div class="topbar-title"><?= $pageTitle ?></div>
                <div class="topbar-actions">
                    <?php if ($overdue_mine): ?>
                        <span class="badge badge-danger"><i class="fa-solid fa-triangle-exclamation"></i> <?= $overdue_mine ?> Overdue</span>
                    <?php endif; ?>
                    <a href="?section=profile" style="display:flex;align-items:center;gap:8px;color:var(--text);font-weight:500;font-size:.875rem;">
                        <div class="avatar sm"><?= strtoupper(substr($member['name'], 0, 1)) ?></div>
                        <?= htmlspecialchars($member['name']) ?>
                    </a>
                </div>
            </header>

            <div class="page-content fade-in">
                <?= showFlash() ?>

                <?php if ($overdue_mine && $section !== 'loans'): ?>
                    <div class="alert-banner">
                        <i class="fa-solid fa-triangle-exclamation"></i>
                        You have <strong><?= $overdue_mine ?> overdue loan<?= $overdue_mine > 1 ? 's' : '' ?></strong>. Please return them immediately.
                        <a href="?section=loans" style="margin-left:8px;color:inherit;text-decoration:underline;">View loans →</a>
                    </div>
                <?php endif; ?>

                <!-- ══════════════════════════════════════════════════════════════
     SECTION: OVERVIEW
══════════════════════════════════════════════════════════════════ -->
                <?php if ($section === 'overview'): ?>

                    <?php
                    $my_tools   = fetchOne("SELECT COUNT(*) AS cnt FROM Tool WHERE owner_id=$uid")['cnt'];
                    $total_loans = $member['total_loans'];
                    $my_rating   = $member['avg_rating'];
                    $review_cnt  = fetchOne("SELECT COUNT(*) AS cnt FROM Review WHERE reviewee_id=$uid")['cnt'];
                    $returned_loans = fetchOne("SELECT COUNT(*) AS cnt FROM Loan_Record WHERE borrower_id=$uid AND status='Returned'")['cnt'];
                    ?>

                    <div class="stats-grid">
                        <div class="stat-card">
                            <div class="stat-icon indigo"><i class="fa-solid fa-toolbox"></i></div>
                            <div class="stat-value"><?= $my_tools ?></div>
                            <div class="stat-label">Tools Listed</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-icon blue"><i class="fa-solid fa-arrow-right-arrow-left"></i></div>
                            <div class="stat-value"><?= $total_loans ?></div>
                            <div class="stat-label">Total Borrows</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-icon green"><i class="fa-solid fa-check-circle"></i></div>
                            <div class="stat-value"><?= $returned_loans ?></div>
                            <div class="stat-label">Completed Returns</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-icon amber"><i class="fa-solid fa-star"></i></div>
                            <div class="stat-value"><?= number_format($my_rating, 1) ?></div>
                            <div class="stat-label">Avg. Rating (<?= $review_cnt ?> reviews)</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-icon <?= $pending_incoming ? 'red' : 'green' ?>"><i class="fa-solid fa-inbox"></i></div>
                            <div class="stat-value"><?= $pending_incoming ?></div>
                            <div class="stat-label">Pending Requests (received)</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-icon <?= $overdue_mine ? 'red' : 'green' ?>"><i class="fa-solid fa-clock"></i></div>
                            <div class="stat-value"><?= $overdue_mine ?></div>
                            <div class="stat-label">Overdue Loans</div>
                        </div>
                    </div>

                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:18px;margin-top:4px;">
                        <!-- Recent Tools Available -->
                        <div class="card">
                            <div class="card-header">
                                <span><i class="fa-solid fa-clock-rotate-left" style="color:var(--primary);margin-right:6px;"></i>Recently Listed Tools</span>
                                <a href="?section=browse" class="btn btn-ghost btn-sm">View all →</a>
                            </div>
                            <?php
                            $recent_tools = fetchAll("
            SELECT t.name, t.status, c.name AS cat, m.name AS owner
            FROM Tool t
            JOIN Category c ON t.category_id=c.category_id
            JOIN Member m ON t.owner_id=m.member_id
            WHERE t.status='Available' AND t.owner_id != $uid
            ORDER BY t.created_at DESC LIMIT 5
        ");
                            ?>
                            <?php if ($recent_tools): ?>
                                <div style="padding:6px 0;">
                                    <?php foreach ($recent_tools as $t): ?>
                                        <div style="display:flex;align-items:center;gap:12px;padding:10px 16px;border-bottom:1px solid var(--border);">
                                            <div class="td-icon"><i class="<?= categoryIcon($t['cat']) ?>" style="font-size:.8rem;"></i></div>
                                            <div style="flex:1;">
                                                <div style="font-size:.875rem;font-weight:600;"><?= htmlspecialchars($t['name']) ?></div>
                                                <div style="font-size:.75rem;color:var(--text-muted);"><?= htmlspecialchars($t['cat']) ?> • <?= htmlspecialchars($t['owner']) ?></div>
                                            </div>
                                            <?= badge($t['status']) ?>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <div class="empty-state" style="padding:30px 20px;"><i class="fa-solid fa-toolbox"></i>
                                    <p>No tools available right now</p>
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- My Recent Activity -->
                        <div class="card">
                            <div class="card-header">
                                <span><i class="fa-solid fa-list-check" style="color:var(--primary);margin-right:6px;"></i>My Recent Loans</span>
                                <a href="?section=loans" class="btn btn-ghost btn-sm">View all →</a>
                            </div>
                            <?php
                            $recent_loans = fetchAll("
            SELECT lr.loan_id, lr.status, lr.expected_return, t.name AS tool, lr.loan_date,
                   DATEDIFF(CURDATE(), lr.expected_return) AS days_late
            FROM Loan_Record lr JOIN Tool t ON lr.tool_id=t.tool_id
            WHERE lr.borrower_id=$uid
            ORDER BY lr.loan_date DESC LIMIT 5
        ");
                            ?>
                            <?php if ($recent_loans): ?>
                                <div style="padding:6px 0;">
                                    <?php foreach ($recent_loans as $l): ?>
                                        <div style="display:flex;align-items:center;gap:12px;padding:10px 16px;border-bottom:1px solid var(--border);">
                                            <div class="td-icon"><i class="fa-solid fa-arrow-right-arrow-left"></i></div>
                                            <div style="flex:1;">
                                                <div style="font-size:.875rem;font-weight:600;"><?= htmlspecialchars($l['tool']) ?></div>
                                                <div style="font-size:.75rem;color:var(--text-muted);">Due: <?= $l['expected_return'] ?></div>
                                            </div>
                                            <?= badge($l['status']) ?>
                                            <?php if ($l['status'] === 'Active' && $l['days_late'] > 0): ?>
                                                <span class="overdue-badge"><?= $l['days_late'] ?>d late</span>
                                            <?php endif; ?>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <div class="empty-state" style="padding:30px 20px;"><i class="fa-solid fa-arrow-right-arrow-left"></i>
                                    <p>No loans yet</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- ══════════════════════════════════════════════════════════════
     SECTION: BROWSE TOOLS
══════════════════════════════════════════════════════════════════ -->
                <?php elseif ($section === 'browse'): ?>

                    <?php
                    $cat_filter  = (int)($_GET['cat'] ?? 0);
                    $area_filter = (int)($_GET['area'] ?? 0);
                    $search      = e($_GET['q'] ?? '');
                    $cond_filter = e($_GET['cond'] ?? '');

                    $where = "t.status='Available' AND t.owner_id != $uid";
                    if ($cat_filter)  $where .= " AND t.category_id=$cat_filter";
                    if ($area_filter) $where .= " AND m.area_id=$area_filter";
                    if ($search)      $where .= " AND (t.name LIKE '%$search%' OR t.description LIKE '%$search%')";
                    if ($cond_filter) $where .= " AND t.`condition`='$cond_filter'";

                    $tools = fetchAll("
    SELECT t.*, c.name AS cat_name, m.name AS owner_name,
           m.avg_rating AS owner_rating, m.member_id AS owner_id,
           a.area_name
    FROM Tool t
    JOIN Category c ON t.category_id=c.category_id
    JOIN Member m   ON t.owner_id=m.member_id
    JOIN Area a     ON m.area_id=a.area_id
    WHERE $where
    ORDER BY t.created_at DESC
");
                    ?>

                    <div class="filter-bar">
                        <form method="GET" action="" style="display:flex;flex-wrap:wrap;gap:8px;align-items:center;width:100%;">
                            <input type="hidden" name="section" value="browse">
                            <div class="search-box" style="flex:1;min-width:180px;">
                                <i class="fa-solid fa-magnifying-glass"></i>
                                <input type="text" name="q" class="form-control" placeholder="Search tools…" value="<?= htmlspecialchars($_GET['q'] ?? '') ?>">
                            </div>
                            <select name="cat" class="form-control" style="width:auto;">
                                <option value="">All Categories</option>
                                <?php foreach ($categories as $c): ?>
                                    <option value="<?= $c['category_id'] ?>" <?= $cat_filter == $c['category_id'] ? 'selected' : '' ?>><?= htmlspecialchars($c['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <select name="area" class="form-control" style="width:auto;">
                                <option value="">All Areas</option>
                                <?php foreach ($areas as $a): ?>
                                    <option value="<?= $a['area_id'] ?>" <?= $area_filter == $a['area_id'] ? 'selected' : '' ?>><?= htmlspecialchars($a['area_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <select name="cond" class="form-control" style="width:auto;">
                                <option value="">Any Condition</option>
                                <option value="New" <?= $cond_filter == 'New' ? 'selected' : '' ?>>New</option>
                                <option value="Good" <?= $cond_filter == 'Good' ? 'selected' : '' ?>>Good</option>
                                <option value="Fair" <?= $cond_filter == 'Fair' ? 'selected' : '' ?>>Fair</option>
                            </select>
                            <button type="submit" class="btn btn-primary btn-sm"><i class="fa-solid fa-filter"></i> Filter</button>
                            <?php if ($cat_filter || $area_filter || $search || $cond_filter): ?>
                                <a href="?section=browse" class="btn btn-ghost btn-sm">Clear</a>
                            <?php endif; ?>
                        </form>
                    </div>

                    <div class="section-header">
                        <h2><?= count($tools) ?> Tool<?= count($tools) != 1 ? 's' : '' ?> Available</h2>
                    </div>

                    <?php if ($tools): ?>
                        <div class="tools-grid">
                            <?php foreach ($tools as $t): ?>
                                <div class="tool-card">
                                    <div class="tool-card-top">
                                        <div class="tool-icon-wrap">
                                            <i class="<?= categoryIcon($t['cat_name']) ?>"></i>
                                        </div>
                                        <div class="tool-badges">
                                            <?= badge($t['status']) ?>
                                            <?= badge($t['condition']) ?>
                                        </div>
                                    </div>
                                    <div class="tool-card-body">
                                        <div class="tool-name"><?= htmlspecialchars($t['name']) ?></div>
                                        <div class="tool-category"><?= htmlspecialchars($t['cat_name']) ?></div>
                                        <div class="tool-desc"><?= htmlspecialchars(trunc($t['description'], 80)) ?></div>
                                        <div class="tool-meta">
                                            <div class="tool-owner">
                                                <div class="avatar sm"><?= strtoupper(substr($t['owner_name'], 0, 1)) ?></div>
                                                <div>
                                                    <div class="tool-owner-name"><?= htmlspecialchars($t['owner_name']) ?></div>
                                                    <div><?= stars($t['owner_rating']) ?></div>
                                                </div>
                                            </div>
                                            <div class="tool-area"><i class="fa-solid fa-location-dot"></i> <?= htmlspecialchars($t['area_name']) ?></div>
                                        </div>
                                    </div>
                                    <div class="tool-card-footer">
                                        <a href="tool_details.php?id=<?= $t['tool_id'] ?>" class="btn btn-ghost btn-sm"><i class="fa-regular fa-eye"></i> Details</a>
                                        <button class="btn btn-primary btn-sm" onclick="openRequestModal(<?= $t['tool_id'] ?>, '<?= htmlspecialchars($t['name'], ENT_QUOTES) ?>')">
                                            <i class="fa-solid fa-paper-plane"></i> Request
                                        </button>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fa-solid fa-toolbox"></i>
                            <h3>No tools found</h3>
                            <p>Try adjusting your filters or check back later.</p>
                        </div>
                    <?php endif; ?>

                    <!-- ══════════════════════════════════════════════════════════════
     SECTION: MY REQUESTS (sent)
══════════════════════════════════════════════════════════════════ -->
                <?php elseif ($section === 'requests'): ?>

                    <?php
                    $requests = fetchAll("
    SELECT br.*, t.name AS tool_name, t.status AS tool_status,
           m.name AS owner_name, m.avg_rating AS owner_rating,
           c.name AS cat_name
    FROM Borrow_Request br
    JOIN Tool t ON br.tool_id=t.tool_id
    JOIN Member m ON t.owner_id=m.member_id
    JOIN Category c ON t.category_id=c.category_id
    WHERE br.requester_id=$uid
    ORDER BY br.created_at DESC
");
                    ?>

                    <div class="section-header">
                        <h2>My Borrow Requests</h2>
                        <a href="?section=browse" class="btn btn-primary btn-sm"><i class="fa-solid fa-plus"></i> Find Tools to Borrow</a>
                    </div>

                    <?php if ($requests): ?>
                        <div class="table-wrap">
                            <table>
                                <thead>
                                    <tr>
                                        <th>Tool</th>
                                        <th>Owner</th>
                                        <th>Requested Date</th>
                                        <th>Duration</th>
                                        <th>Status</th>
                                        <th>Sent</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($requests as $r): ?>
                                        <tr class="searchable-row">
                                            <td>
                                                <div class="td-tool">
                                                    <div class="td-icon"><i class="<?= categoryIcon($r['cat_name']) ?>"></i></div>
                                                    <div>
                                                        <div style="font-weight:600;"><?= htmlspecialchars($r['tool_name']) ?></div>
                                                        <div class="text-small text-muted"><?= htmlspecialchars($r['cat_name']) ?></div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td><?= htmlspecialchars($r['owner_name']) ?></td>
                                            <td><?= $r['requested_date'] ?></td>
                                            <td><?= $r['duration_days'] ?> days</td>
                                            <td><?= badge($r['status']) ?></td>
                                            <td class="text-muted text-small"><?= timeAgo($r['created_at']) ?></td>
                                            <td>
                                                <?php if ($r['status'] === 'Pending'): ?>
                                                    <form method="POST" action="actions.php" style="display:inline;">
                                                        <input type="hidden" name="action" value="cancel_request">
                                                        <input type="hidden" name="req_id" value="<?= $r['req_id'] ?>">
                                                        <input type="hidden" name="back" value="user_dashboard.php?section=requests">
                                                        <button type="submit" class="btn btn-ghost btn-sm" data-confirm="Cancel this request?"><i class="fa-solid fa-xmark"></i> Cancel</button>
                                                    </form>
                                                <?php elseif ($r['status'] === 'Approved'): ?>
                                                    <span class="text-small text-muted">Loan created</span>
                                                <?php else: ?>
                                                    —
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fa-regular fa-paper-plane"></i>
                            <h3>No requests yet</h3>
                            <p>Browse available tools and send a borrow request to get started.</p>
                            <a href="?section=browse" class="btn btn-primary" style="margin-top:12px;">Browse Tools</a>
                        </div>
                    <?php endif; ?>

                    <!-- ══════════════════════════════════════════════════════════════
     SECTION: MY LOANS (as borrower)
══════════════════════════════════════════════════════════════════ -->
                <?php elseif ($section === 'loans'): ?>

                    <?php
                    $loans = fetchAll("
    SELECT lr.*,
           t.name AS tool_name, t.tool_id, t.owner_id, c.name AS cat_name,
           own.name AS owner_name, own.member_id AS owner_mid,
           DATEDIFF(CURDATE(), lr.expected_return) AS days_overdue,
           rl.return_id, rl.condition_on_return, rl.actual_return_date,
           (SELECT review_id FROM Review WHERE loan_id=lr.loan_id AND reviewer_id=$uid LIMIT 1) AS reviewed
    FROM Loan_Record lr
    JOIN Tool t ON lr.tool_id=t.tool_id
    JOIN Category c ON t.category_id=c.category_id
    JOIN Member own ON t.owner_id=own.member_id
    LEFT JOIN Return_Log rl ON rl.loan_id=lr.loan_id
    WHERE lr.borrower_id=$uid
    ORDER BY lr.loan_date DESC
");
                    ?>

                    <div class="section-header">
                        <h2>My Borrowed Tools</h2>
                    </div>

                    <?php if ($loans): ?>
                        <?php foreach ($loans as $l): ?>
                            <div class="card" style="margin-bottom:14px;">
                                <div class="card-header">
                                    <div style="display:flex;align-items:center;gap:10px;">
                                        <div class="td-icon"><i class="<?= categoryIcon($l['cat_name']) ?>"></i></div>
                                        <div>
                                            <strong><?= htmlspecialchars($l['tool_name']) ?></strong>
                                            <span style="color:var(--text-muted);font-size:.8rem;margin-left:8px;">from <?= htmlspecialchars($l['owner_name']) ?></span>
                                        </div>
                                    </div>
                                    <div style="display:flex;gap:8px;align-items:center;">
                                        <?= badge($l['status']) ?>
                                        <?php if ($l['status'] === 'Active' && $l['days_overdue'] > 0): ?>
                                            <span class="overdue-badge"><?= $l['days_overdue'] ?>d overdue!</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="card-body" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(160px,1fr));gap:12px;">
                                    <div>
                                        <div class="text-small text-muted">Loan Date</div>
                                        <div style="font-weight:600;"><?= $l['loan_date'] ?></div>
                                    </div>
                                    <div>
                                        <div class="text-small text-muted">Expected Return</div>
                                        <div style="font-weight:600;color:<?= ($l['status'] === 'Active' && $l['days_overdue'] > 0) ? 'var(--danger)' : 'inherit' ?>;">
                                            <?= $l['expected_return'] ?>
                                            <?php if ($l['status'] === 'Active' && $l['days_overdue'] > 0): ?>
                                                <small style="color:var(--danger);"> (<?= $l['days_overdue'] ?>d late)</small>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <?php if ($l['return_id']): ?>
                                        <div>
                                            <div class="text-small text-muted">Returned On</div>
                                            <div style="font-weight:600;"><?= htmlspecialchars($l['actual_return_date']) ?></div>
                                        </div>
                                        <div>
                                            <div class="text-small text-muted">Condition on Return</div>
                                            <?= badge($l['condition_on_return']) ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div class="card-footer" style="display:flex;gap:8px;flex-wrap:wrap;">
                                    <?php if ($l['status'] === 'Active'): ?>
                                        <!-- Return Tool Button -->
                                        <button class="btn btn-success btn-sm" onclick="openReturnModal(<?= $l['loan_id'] ?>, '<?= htmlspecialchars($l['tool_name'], ENT_QUOTES) ?>')">
                                            <i class="fa-solid fa-box-archive"></i> Return Tool
                                        </button>
                                        <!-- Damage Report -->
                                        <?php $has_dmg = fetchOne("SELECT damage_id FROM Damage_Report WHERE loan_id={$l['loan_id']} AND reported_by=$uid"); ?>
                                        <?php if (!$has_dmg): ?>
                                            <button class="btn btn-warning btn-sm" onclick="openDamageModal(<?= $l['loan_id'] ?>)">
                                                <i class="fa-solid fa-triangle-exclamation"></i> Report Damage
                                            </button>
                                        <?php endif; ?>

                                    <?php elseif ($l['status'] === 'Returned' && !$l['reviewed']): ?>
                                        <!-- Leave Review -->
                                        <button class="btn btn-outline btn-sm" onclick="openReviewModal(<?= $l['loan_id'] ?>, <?= $l['owner_mid'] ?>, '<?= htmlspecialchars($l['owner_name'], ENT_QUOTES) ?>', 'Tool Owner')">
                                            <i class="fa-solid fa-star"></i> Review Owner
                                        </button>
                                    <?php elseif ($l['status'] === 'Returned' && $l['reviewed']): ?>
                                        <span class="badge badge-success"><i class="fa-solid fa-check"></i> Reviewed</span>
                                    <?php endif; ?>

                                    <a href="profile.php?id=<?= $l['owner_mid'] ?>" class="btn btn-ghost btn-sm"><i class="fa-regular fa-user"></i> View Owner</a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fa-solid fa-arrow-right-arrow-left"></i>
                            <h3>No loans yet</h3>
                            <p>You haven't borrowed any tools yet. Browse the community listings!</p>
                            <a href="?section=browse" class="btn btn-primary" style="margin-top:12px;">Browse Tools</a>
                        </div>
                    <?php endif; ?>

                    <!-- ══════════════════════════════════════════════════════════════
     SECTION: MY TOOLS (listings I own)
══════════════════════════════════════════════════════════════════ -->
                <?php elseif ($section === 'my_tools'): ?>

                    <?php
                    $my_tools = fetchAll("
    SELECT t.*, c.name AS cat_name,
           COUNT(DISTINCT lr.loan_id) AS total_borrows,
           SUM(CASE WHEN lr.status='Active' THEN 1 ELSE 0 END) AS active_borrows
    FROM Tool t
    JOIN Category c ON t.category_id=c.category_id
    LEFT JOIN Loan_Record lr ON lr.tool_id=t.tool_id
    WHERE t.owner_id=$uid
    GROUP BY t.tool_id
    ORDER BY t.created_at DESC
");
                    ?>

                    <div class="section-header">
                        <h2><?= count($my_tools) ?> Tool<?= count($my_tools) != 1 ? 's' : '' ?> Listed</h2>
                        <button class="btn btn-primary btn-sm" data-modal-open="modal-add-tool">
                            <i class="fa-solid fa-plus"></i> List New Tool
                        </button>
                    </div>

                    <?php if ($my_tools): ?>
                        <div class="table-wrap">
                            <table>
                                <thead>
                                    <tr>
                                        <th>Tool</th>
                                        <th>Category</th>
                                        <th>Condition</th>
                                        <th>Status</th>
                                        <th>Total Borrows</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($my_tools as $t): ?>
                                        <tr class="searchable-row">
                                            <td>
                                                <div class="td-tool">
                                                    <div class="td-icon"><i class="<?= categoryIcon($t['cat_name']) ?>"></i></div>
                                                    <div>
                                                        <div style="font-weight:600;"><?= htmlspecialchars($t['name']) ?></div>
                                                        <div class="text-small text-muted"><?= htmlspecialchars(trunc($t['description'], 50)) ?></div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td><?= htmlspecialchars($t['cat_name']) ?></td>
                                            <td><?= badge($t['condition']) ?></td>
                                            <td><?= badge($t['status']) ?></td>
                                            <td style="font-weight:700;color:var(--primary);"><?= $t['total_borrows'] ?></td>
                                            <td>
                                                <div style="display:flex;gap:6px;flex-wrap:wrap;">
                                                    <button class="btn btn-ghost btn-sm"
                                                        onclick="openEditToolModal(<?= $t['tool_id'] ?>, '<?= htmlspecialchars($t['name'], ENT_QUOTES) ?>', <?= $t['category_id'] ?>, '<?= $t['condition'] ?>', '<?= htmlspecialchars($t['description'], ENT_QUOTES) ?>')">
                                                        <i class="fa-regular fa-pen-to-square"></i> Edit
                                                    </button>
                                                    <?php if ($t['status'] === 'Available' && !$t['active_borrows']): ?>
                                                        <form method="POST" action="actions.php" style="display:inline;">
                                                            <input type="hidden" name="action" value="delete_tool">
                                                            <input type="hidden" name="tool_id" value="<?= $t['tool_id'] ?>">
                                                            <input type="hidden" name="back" value="user_dashboard.php?section=my_tools">
                                                            <button type="submit" class="btn btn-danger btn-sm" data-confirm="Delete this tool permanently?">
                                                                <i class="fa-solid fa-trash"></i>
                                                            </button>
                                                        </form>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fa-solid fa-toolbox"></i>
                            <h3>No tools listed yet</h3>
                            <p>List tools you own so your neighbours can borrow them.</p>
                            <button class="btn btn-primary" style="margin-top:12px;" data-modal-open="modal-add-tool">List Your First Tool</button>
                        </div>
                    <?php endif; ?>

                    <!-- ══════════════════════════════════════════════════════════════
     SECTION: INCOMING REQUESTS (as owner)
══════════════════════════════════════════════════════════════════ -->
                <?php elseif ($section === 'incoming'): ?>

                    <?php
                    $incoming = fetchAll("
    SELECT br.*, t.name AS tool_name, t.status AS tool_status,
           m.name AS requester_name, m.avg_rating AS req_rating,
           m.total_loans AS req_loans, m.member_id AS req_mid,
           a.area_name AS req_area
    FROM Borrow_Request br
    JOIN Tool t ON br.tool_id=t.tool_id
    JOIN Member m ON br.requester_id=m.member_id
    JOIN Area a ON m.area_id=a.area_id
    WHERE t.owner_id=$uid
    ORDER BY FIELD(br.status,'Pending','Approved','Rejected','Cancelled'), br.created_at DESC
");
                    $pending_count = count(array_filter($incoming, fn($r) => $r['status'] === 'Pending'));
                    ?>

                    <div class="section-header">
                        <h2>Requests for My Tools</h2>
                        <?php if ($pending_count): ?>
                            <span class="badge badge-warning"><?= $pending_count ?> Pending Review</span>
                        <?php endif; ?>
                    </div>

                    <?php if ($incoming): ?>
                        <?php foreach ($incoming as $r): ?>
                            <div class="card" style="margin-bottom:14px;<?= $r['status'] === 'Pending' ? 'border-left:3px solid var(--warning);' : '' ?>">
                                <div class="card-header">
                                    <div>
                                        <strong><?= htmlspecialchars($r['tool_name']) ?></strong>
                                        <span style="color:var(--text-muted);font-size:.8rem;margin-left:8px;">• <?= $r['duration_days'] ?> days • Starting <?= $r['requested_date'] ?></span>
                                    </div>
                                    <?= badge($r['status']) ?>
                                </div>
                                <div class="card-body" style="display:flex;gap:20px;align-items:flex-start;flex-wrap:wrap;">
                                    <div style="display:flex;align-items:center;gap:10px;min-width:180px;">
                                        <div class="avatar"><?= strtoupper(substr($r['requester_name'], 0, 1)) ?></div>
                                        <div>
                                            <div style="font-weight:600;"><?= htmlspecialchars($r['requester_name']) ?></div>
                                            <div><?= stars($r['req_rating']) ?></div>
                                            <div class="text-small text-muted"><?= $r['req_loans'] ?> total loans • <?= htmlspecialchars($r['req_area']) ?></div>
                                        </div>
                                    </div>
                                    <?php if ($r['message']): ?>
                                        <div style="flex:1;background:var(--bg);border-radius:var(--radius-sm);padding:10px 14px;">
                                            <div class="text-small text-muted" style="margin-bottom:4px;">Message from requester:</div>
                                            <div style="font-size:.875rem;"><?= htmlspecialchars($r['message']) ?></div>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <?php if ($r['status'] === 'Pending' && $r['tool_status'] === 'Available'): ?>
                                    <div class="card-footer" style="display:flex;gap:8px;">
                                        <form method="POST" action="actions.php" style="display:inline;">
                                            <input type="hidden" name="action" value="approve_request">
                                            <input type="hidden" name="req_id" value="<?= $r['req_id'] ?>">
                                            <input type="hidden" name="back" value="user_dashboard.php?section=incoming">
                                            <button type="submit" class="btn btn-success btn-sm"><i class="fa-solid fa-check"></i> Approve & Create Loan</button>
                                        </form>
                                        <form method="POST" action="actions.php" style="display:inline;">
                                            <input type="hidden" name="action" value="reject_request">
                                            <input type="hidden" name="req_id" value="<?= $r['req_id'] ?>">
                                            <input type="hidden" name="back" value="user_dashboard.php?section=incoming">
                                            <button type="submit" class="btn btn-danger btn-sm" data-confirm="Reject this request?"><i class="fa-solid fa-xmark"></i> Reject</button>
                                        </form>
                                        <a href="profile.php?id=<?= $r['req_mid'] ?>" class="btn btn-ghost btn-sm"><i class="fa-regular fa-user"></i> View Profile</a>
                                    </div>
                                <?php elseif ($r['status'] === 'Pending' && $r['tool_status'] !== 'Available'): ?>
                                    <div class="card-footer">
                                        <span class="text-small text-muted"><i class="fa-solid fa-info-circle"></i> Tool is currently <?= $r['tool_status'] ?> — cannot approve.</span>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fa-solid fa-inbox"></i>
                            <h3>No requests received yet</h3>
                            <p>When someone requests to borrow your tool, it will appear here.</p>
                        </div>
                    <?php endif; ?>

                    <!-- ══════════════════════════════════════════════════════════════
     SECTION: LENDING HISTORY (as owner)
══════════════════════════════════════════════════════════════════ -->
                <?php elseif ($section === 'history'): ?>

                    <?php
                    $history = fetchAll("
    SELECT lr.*, t.name AS tool_name, c.name AS cat,
           m.name AS borrower_name, m.member_id AS borrower_mid,
           rl.actual_return_date,
           DATEDIFF(CURDATE(), lr.expected_return) AS days_late,
           (SELECT review_id FROM Review WHERE loan_id=lr.loan_id AND reviewer_id=$uid LIMIT 1) AS reviewed
    FROM Loan_Record lr
    JOIN Tool t ON lr.tool_id=t.tool_id
    JOIN Category c ON t.category_id=c.category_id
    JOIN Member m ON lr.borrower_id=m.member_id
    LEFT JOIN Return_Log rl ON rl.loan_id=lr.loan_id
    WHERE t.owner_id=$uid
    ORDER BY lr.loan_date DESC
");
                    ?>

                    <div class="section-header">
                        <h2>My Lending History</h2>
                    </div>

                    <?php if ($history): ?>
                        <div class="table-wrap">
                            <table>
                                <thead>
                                    <tr>
                                        <th>Tool</th>
                                        <th>Borrower</th>
                                        <th>Loan Date</th>
                                        <th>Expected Return</th>
                                        <th>Returned On</th>
                                        <th>Status</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($history as $h): ?>
                                        <tr class="searchable-row <?= ($h['status'] === 'Active' && $h['days_late'] > 0) ? 'overdue-row' : '' ?>">
                                            <td><strong><?= htmlspecialchars($h['tool_name']) ?></strong></td>
                                            <td>
                                                <a href="profile.php?id=<?= $h['borrower_mid'] ?>" style="color:var(--text);font-weight:500;">
                                                    <?= htmlspecialchars($h['borrower_name']) ?>
                                                </a>
                                            </td>
                                            <td><?= $h['loan_date'] ?></td>
                                            <td><?= $h['expected_return'] ?>
                                                <?php if ($h['status'] === 'Active' && $h['days_late'] > 0): ?>
                                                    <span class="overdue-badge"><?= $h['days_late'] ?>d late</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?= $h['actual_return_date'] ?? '<span class="text-muted">Not yet</span>' ?></td>
                                            <td><?= badge($h['status']) ?></td>
                                            <td>
                                                <?php if ($h['status'] === 'Returned' && !$h['reviewed']): ?>
                                                    <button class="btn btn-outline btn-sm" onclick="openReviewModal(<?= $h['loan_id'] ?>, <?= $h['borrower_mid'] ?>, '<?= htmlspecialchars($h['borrower_name'], ENT_QUOTES) ?>', 'Borrower')">
                                                        <i class="fa-solid fa-star"></i> Review
                                                    </button>
                                                <?php elseif ($h['reviewed']): ?>
                                                    <span class="badge badge-success">Reviewed</span>
                                                    <?php else: ?>—<?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="empty-state"><i class="fa-solid fa-clock-rotate-left"></i>
                            <h3>No lending history yet</h3>
                            <p>When others borrow your tools, history will appear here.</p>
                        </div>
                    <?php endif; ?>

                    <!-- ══════════════════════════════════════════════════════════════
     SECTION: PROFILE
══════════════════════════════════════════════════════════════════ -->
                <?php elseif ($section === 'profile'): ?>

                    <?php
                    $reviews = fetchAll("
    SELECT r.*, m.name AS reviewer_name, t.name AS tool_name
    FROM Review r
    JOIN Member m ON r.reviewer_id=m.member_id
    JOIN Loan_Record lr ON r.loan_id=lr.loan_id
    JOIN Tool t ON lr.tool_id=t.tool_id
    WHERE r.reviewee_id=$uid
    ORDER BY r.created_at DESC LIMIT 10
");
                    $review_cnt = fetchOne("SELECT COUNT(*) AS cnt FROM Review WHERE reviewee_id=$uid")['cnt'];
                    ?>

                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:18px;">
                        <!-- Edit Profile -->
                        <div class="card">
                            <div class="card-header"><i class="fa-regular fa-user" style="color:var(--primary);margin-right:6px;"></i>Edit Profile</div>
                            <div class="card-body">
                                <form method="POST" action="actions.php">
                                    <input type="hidden" name="action" value="update_profile">
                                    <input type="hidden" name="back" value="user_dashboard.php?section=profile">
                                    <div class="form-group">
                                        <label class="form-label">Full Name</label>
                                        <input type="text" name="name" class="form-control" value="<?= htmlspecialchars($member['name']) ?>" required>
                                    </div>
                                    <div class="form-group">
                                        <label class="form-label">Phone</label>
                                        <input type="text" name="phone" class="form-control" value="<?= htmlspecialchars($member['phone']) ?>">
                                    </div>
                                    <div class="form-group">
                                        <label class="form-label">Area</label>
                                        <select name="area_id" class="form-control">
                                            <?php foreach ($areas as $a): ?>
                                                <option value="<?= $a['area_id'] ?>" <?= $member['area_id'] == $a['area_id'] ? 'selected' : '' ?>>
                                                    <?= htmlspecialchars($a['area_name']) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="form-group">
                                        <label class="form-label">Bio</label>
                                        <textarea name="bio" class="form-control" placeholder="Tell the community about yourself…" rows="3"><?= htmlspecialchars($member['profile_bio'] ?? '') ?></textarea>
                                    </div>
                                    <button type="submit" class="btn btn-primary btn-sm"><i class="fa-solid fa-floppy-disk"></i> Save Changes</button>
                                </form>
                            </div>
                        </div>

                        <!-- Change Password -->
                        <div class="card">
                            <div class="card-header"><i class="fa-solid fa-lock" style="color:var(--primary);margin-right:6px;"></i>Change Password</div>
                            <div class="card-body">
                                <form method="POST" action="actions.php">
                                    <input type="hidden" name="action" value="change_password">
                                    <input type="hidden" name="back" value="user_dashboard.php?section=profile">
                                    <div class="form-group">
                                        <label class="form-label">Current Password</label>
                                        <input type="password" name="current_pass" class="form-control" required>
                                    </div>
                                    <div class="form-group">
                                        <label class="form-label">New Password</label>
                                        <input type="password" name="new_pass" class="form-control" placeholder="Min. 6 characters" required>
                                    </div>
                                    <div class="form-group">
                                        <label class="form-label">Confirm New Password</label>
                                        <input type="password" name="confirm_pass" class="form-control" required>
                                    </div>
                                    <button type="submit" class="btn btn-primary btn-sm"><i class="fa-solid fa-key"></i> Update Password</button>
                                </form>
                            </div>
                        </div>
                    </div>

                    <!-- My Reviews -->
                    <div class="card" style="margin-top:18px;">
                        <div class="card-header">
                            <span><i class="fa-solid fa-star" style="color:#F59E0B;margin-right:6px;"></i>My Reviews (<?= $review_cnt ?>)</span>
                            <span style="font-size:.875rem;font-weight:400;color:var(--text-muted);">
                                <?= stars($member['avg_rating']) ?> <?= number_format($member['avg_rating'], 2) ?> avg
                            </span>
                        </div>
                        <?php if ($reviews): ?>
                            <?php foreach ($reviews as $rv): ?>
                                <div style="padding:14px 20px;border-bottom:1px solid var(--border);display:flex;gap:12px;align-items:flex-start;">
                                    <div class="avatar sm"><?= strtoupper(substr($rv['reviewer_name'], 0, 1)) ?></div>
                                    <div style="flex:1;">
                                        <div style="display:flex;align-items:center;gap:10px;margin-bottom:4px;">
                                            <strong style="font-size:.875rem;"><?= htmlspecialchars($rv['reviewer_name']) ?></strong>
                                            <?= stars($rv['rating']) ?>
                                            <span class="text-small text-muted"><?= timeAgo($rv['created_at']) ?></span>
                                        </div>
                                        <div style="font-size:.875rem;color:var(--text-muted);">Re: <?= htmlspecialchars($rv['tool_name']) ?></div>
                                        <?php if ($rv['comment']): ?>
                                            <div style="margin-top:6px;font-size:.875rem;">"<?= htmlspecialchars($rv['comment']) ?>"</div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="empty-state" style="padding:30px;"><i class="fa-regular fa-star"></i>
                                <p>No reviews yet</p>
                            </div>
                        <?php endif; ?>
                    </div>

                <?php endif; ?>

            </div><!-- .page-content -->
        </div><!-- .main -->
    </div><!-- .layout -->

    <!-- ════════════════════════════════════════════════════════
     GLOBAL MODALS
════════════════════════════════════════════════════════════ -->

    <!-- Request Tool Modal -->
    <div class="modal-overlay" id="modal-request">
        <div class="modal">
            <div class="modal-header">
                <h3><i class="fa-solid fa-paper-plane" style="color:var(--primary);margin-right:6px;"></i>Request to Borrow: <span id="req_tool_name"></span></h3>
                <button class="modal-close">✕</button>
            </div>
            <form method="POST" action="actions.php">
                <input type="hidden" name="action" value="request_tool">
                <input type="hidden" name="back" value="user_dashboard.php?section=requests">
                <input type="hidden" name="tool_id" id="req_tool_id">
                <div class="modal-body">
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Start Date <span>*</span></label>
                            <input type="date" name="requested_date" id="req_requested_date" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Duration (days) <span>*</span></label>
                            <input type="number" name="duration_days" class="form-control" min="1" max="30" value="7" required>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Message to Owner</label>
                        <textarea name="message" class="form-control" placeholder="Why do you need it? Any special dates? (optional)" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-ghost modal-close">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="fa-solid fa-paper-plane"></i> Send Request</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Return Tool Modal -->
    <div class="modal-overlay" id="modal-return">
        <div class="modal">
            <div class="modal-header">
                <h3><i class="fa-solid fa-box-archive" style="color:var(--success);margin-right:6px;"></i>Return: <span id="ret_tool_name"></span></h3>
                <button class="modal-close">✕</button>
            </div>
            <form method="POST" action="actions.php">
                <input type="hidden" name="action" value="return_tool">
                <input type="hidden" name="back" value="user_dashboard.php?section=loans">
                <input type="hidden" name="loan_id" id="ret_loan_id">
                <div class="modal-body">
                    <div class="form-group">
                        <label class="form-label">Condition on Return <span>*</span></label>
                        <select name="condition_on_return" class="form-control" required>
                            <option value="New">New — No change</option>
                            <option value="Good" selected>Good — Minor wear, fully functional</option>
                            <option value="Fair">Fair — Visible wear, still works</option>
                            <option value="Damaged">Damaged — Something broke or needs repair</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Notes</label>
                        <textarea name="notes" class="form-control" placeholder="Any notes about the return? (optional)" rows="3"></textarea>
                    </div>
                    <div class="flash-msg flash-info" style="margin-bottom:0;">
                        <i class="fa-solid fa-info-circle"></i>
                        After returning, you can leave a review for the tool owner.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-ghost modal-close">Cancel</button>
                    <button type="submit" class="btn btn-success"><i class="fa-solid fa-check"></i> Confirm Return</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Review Modal -->
    <div class="modal-overlay" id="modal-review">
        <div class="modal">
            <div class="modal-header">
                <h3><i class="fa-solid fa-star" style="color:#F59E0B;margin-right:6px;"></i>Review <span id="rev_reviewee_name"></span> <small id="rev_role" style="color:var(--text-muted);font-weight:400;"></small></h3>
                <button class="modal-close">✕</button>
            </div>
            <form method="POST" action="actions.php">
                <input type="hidden" name="action" value="submit_review">
                <input type="hidden" name="back" value="user_dashboard.php?section=loans">
                <input type="hidden" name="loan_id" id="rev_loan_id">
                <input type="hidden" name="reviewee_id" id="rev_reviewee_id">
                <div class="modal-body">
                    <div class="form-group">
                        <label class="form-label">Your Rating <span>*</span></label>
                        <div class="star-input">
                            <input type="radio" name="rating" id="s5" value="5"><label for="s5">★</label>
                            <input type="radio" name="rating" id="s4" value="4"><label for="s4">★</label>
                            <input type="radio" name="rating" id="s3" value="3" checked><label for="s3">★</label>
                            <input type="radio" name="rating" id="s2" value="2"><label for="s2">★</label>
                            <input type="radio" name="rating" id="s1" value="1"><label for="s1">★</label>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Comment</label>
                        <textarea name="comment" class="form-control" placeholder="Share your experience… (optional)" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-ghost modal-close">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="fa-solid fa-star"></i> Submit Review</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Damage Report Modal -->
    <div class="modal-overlay" id="modal-damage">
        <div class="modal">
            <div class="modal-header">
                <h3><i class="fa-solid fa-triangle-exclamation" style="color:var(--warning);margin-right:6px;"></i>File Damage Report</h3>
                <button class="modal-close">✕</button>
            </div>
            <form method="POST" action="actions.php">
                <input type="hidden" name="action" value="file_damage">
                <input type="hidden" name="back" value="user_dashboard.php?section=loans">
                <input type="hidden" name="loan_id" id="dmg_loan_id">
                <div class="modal-body">
                    <div class="form-group">
                        <label class="form-label">Damage Severity <span>*</span></label>
                        <select name="severity" class="form-control" required>
                            <option value="Minor">Minor — Small scratch, cosmetic only</option>
                            <option value="Moderate">Moderate — Part broken, partially functional</option>
                            <option value="Severe">Severe — Tool unusable or destroyed</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Description <span>*</span></label>
                        <textarea name="description" class="form-control" placeholder="Describe what happened and what was damaged…" rows="4" required></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-ghost modal-close">Cancel</button>
                    <button type="submit" class="btn btn-warning"><i class="fa-solid fa-triangle-exclamation"></i> Submit Report</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Add Tool Modal -->
    <div class="modal-overlay" id="modal-add-tool">
        <div class="modal">
            <div class="modal-header">
                <h3><i class="fa-solid fa-plus" style="color:var(--primary);margin-right:6px;"></i>List a New Tool</h3>
                <button class="modal-close">✕</button>
            </div>
            <form method="POST" action="actions.php">
                <input type="hidden" name="action" value="add_tool">
                <input type="hidden" name="back" value="user_dashboard.php?section=my_tools">
                <div class="modal-body">
                    <div class="form-group">
                        <label class="form-label">Tool Name <span>*</span></label>
                        <input type="text" name="name" class="form-control" placeholder="e.g. Bosch Corded Drill 750W" required>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Category <span>*</span></label>
                            <select name="category_id" class="form-control" required>
                                <option value="">— Select —</option>
                                <?php foreach ($categories as $c): ?>
                                    <option value="<?= $c['category_id'] ?>"><?= htmlspecialchars($c['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Condition</label>
                            <select name="condition" class="form-control">
                                <option value="New">New</option>
                                <option value="Good" selected>Good</option>
                                <option value="Fair">Fair</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Description</label>
                        <textarea name="description" class="form-control" rows="3" placeholder="What's included? Any special instructions for borrowers?"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-ghost modal-close">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="fa-solid fa-plus"></i> List Tool</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit Tool Modal -->
    <div class="modal-overlay" id="modal-edit-tool">
        <div class="modal">
            <div class="modal-header">
                <h3><i class="fa-regular fa-pen-to-square" style="color:var(--primary);margin-right:6px;"></i>Edit Tool</h3>
                <button class="modal-close">✕</button>
            </div>
            <form method="POST" action="actions.php">
                <input type="hidden" name="action" value="edit_tool">
                <input type="hidden" name="back" value="user_dashboard.php?section=my_tools">
                <input type="hidden" name="tool_id" id="edit_tool_id">
                <div class="modal-body">
                    <div class="form-group">
                        <label class="form-label">Tool Name <span>*</span></label>
                        <input type="text" name="name" id="edit_tool_name" class="form-control" required>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Category <span>*</span></label>
                            <select name="category_id" id="edit_cat_id" class="form-control" required>
                                <?php foreach ($categories as $c): ?>
                                    <option value="<?= $c['category_id'] ?>"><?= htmlspecialchars($c['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Condition</label>
                            <select name="condition" id="edit_condition" class="form-control">
                                <option value="New">New</option>
                                <option value="Good">Good</option>
                                <option value="Fair">Fair</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Description</label>
                        <textarea name="description" id="edit_desc" class="form-control" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-ghost modal-close">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="fa-solid fa-floppy-disk"></i> Save Changes</button>
                </div>
            </form>
        </div>
    </div>

    <script src="assets/app.js"></script>
</body>

</html>