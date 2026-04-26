    <?php
    // ============================================================
    // admin_dashboard.php — ToolShare Hub Admin Control Panel
    // Only accessible by members with role = 'Admin'
    // All data is pulled live from MySQL — no fake numbers
    // ============================================================
    session_start();
    require_once 'db.php';

    // ── SECURITY: Block non-admins ────────────────────────────────
    // requireAdmin() is defined in db.php
    // It checks $_SESSION['role'] === 'Admin', redirects if not
    requireAdmin();

    $uid     = (int)$_SESSION['member_id'];
    $section = $_GET['section'] ?? 'overview'; // Which tab is active?

    // ── PAGE TITLES (for topbar) ──────────────────────────────────
    $titles = [
        'overview' => 'Admin Overview',
        'members'  => 'Member Management',
        'tools'    => 'Tools Management',
        'loans'    => 'Active Loans',
        'overdue'  => 'Overdue Loans',
        'damage'   => 'Damage Reports',
        'reviews'  => 'All Reviews',
    ];
    $pageTitle = $titles[$section] ?? 'Admin Panel';

    // ── GLOBAL STATS (used in sidebar badges + overview cards) ────
    // These run on every page load — they're fast indexed queries
    $stat_members  = fetchOne("SELECT COUNT(*) AS cnt FROM Member WHERE role='Member'")['cnt'];
    $stat_tools    = fetchOne("SELECT COUNT(*) AS cnt FROM Tool")['cnt'];
    $stat_loans    = fetchOne("SELECT COUNT(*) AS cnt FROM Loan_Record WHERE status='Active'")['cnt'];
    $stat_overdue  = fetchOne("SELECT COUNT(*) AS cnt FROM Loan_Record WHERE status='Active' AND expected_return < CURDATE()")['cnt'];
    $stat_damage   = fetchOne("SELECT COUNT(*) AS cnt FROM Damage_Report dr JOIN Loan_Record lr ON dr.loan_id=lr.loan_id JOIN Tool t ON lr.tool_id=t.tool_id WHERE t.status='Needs Inspection'")['cnt'];
    $stat_banned   = fetchOne("SELECT COUNT(*) AS cnt FROM Member WHERE is_banned=1")['cnt'];
    ?>
    <!DOCTYPE html>
    <html lang="en">

    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title><?= $pageTitle ?> — ToolShare Admin</title>
        <link rel="stylesheet" href="assets/style.css">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
        <style>
            /* ── Admin-specific extra styles ── */
            .admin-badge {
                background: linear-gradient(135deg, #4F46E5, #7C3AED);
                color: white;
                font-size: 0.68rem;
                padding: 2px 8px;
                border-radius: 20px;
                font-weight: 700;
                letter-spacing: .05em;
                text-transform: uppercase;
            }

            .member-row-banned {
                opacity: .6;
                background: #FFF5F5 !important;
            }

            .member-row-banned td {
                color: #9CA3AF;
            }

            .kpi-bar {
                background: var(--bg);
                border-radius: var(--radius);
                padding: 16px 20px;
                border: 1px solid var(--border);
                display: flex;
                align-items: center;
                gap: 14px;
            }

            .kpi-bar .kpi-icon {
                width: 38px;
                height: 38px;
                border-radius: 10px;
                display: flex;
                align-items: center;
                justify-content: center;
                font-size: 1rem;
                flex-shrink: 0;
            }

            .kpi-bar .kpi-label {
                font-size: .8rem;
                color: var(--text-muted);
                font-weight: 500;
            }

            .kpi-bar .kpi-val {
                font-size: 1.25rem;
                font-weight: 800;
                color: var(--text);
            }

            .progress-wrap {
                background: var(--border);
                border-radius: 20px;
                height: 6px;
                margin-top: 6px;
                overflow: hidden;
            }

            .progress-fill {
                height: 100%;
                border-radius: 20px;
                background: var(--primary);
                transition: width .6s ease;
            }

            .action-group {
                display: flex;
                gap: 6px;
                flex-wrap: wrap;
            }

            .section-tabs {
                display: flex;
                gap: 4px;
                flex-wrap: wrap;
                margin-bottom: 24px;
            }

            .section-tab {
                padding: 8px 16px;
                border-radius: 8px;
                font-size: .84rem;
                font-weight: 500;
                color: var(--text-muted);
                text-decoration: none;
                border: 1px solid transparent;
                transition: all .2s;
                display: flex;
                align-items: center;
                gap: 6px;
            }

            .section-tab:hover {
                background: var(--card);
                border-color: var(--border);
                color: var(--text);
            }

            .section-tab.active {
                background: var(--primary);
                color: white;
                border-color: var(--primary);
            }

            .section-tab .cnt {
                background: rgba(255, 255, 255, .25);
                border-radius: 20px;
                padding: 1px 6px;
                font-size: .7rem;
                font-weight: 700;
            }

            .section-tab:not(.active) .cnt {
                background: var(--danger);
                color: white;
            }

            table.sortable thead th {
                cursor: pointer;
                user-select: none;
            }

            table.sortable thead th:hover {
                color: var(--primary);
            }
        </style>
    </head>

    <body>
        <div class="layout">

            <!-- ════════════════════════════════════════════════════════════
     SIDEBAR
════════════════════════════════════════════════════════════════ -->
            <div class="sidebar-overlay" id="sidebar-overlay"></div>
            <aside class="sidebar" id="sidebar">
                <div class="sidebar-logo">
                    <div class="logo-icon"><i class="fa-solid fa-shield-halved"></i></div>
                    <div>
                        <div class="logo-text">ToolShare Admin</div>
                        <span class="logo-sub">Control Panel</span>
                    </div>
                </div>

                <nav class="sidebar-nav">
                    <div class="sidebar-section">Dashboard</div>
                    <a href="?section=overview" class="nav-link <?= $section == 'overview' ? 'active' : '' ?>">
                        <i class="fa-solid fa-chart-pie"></i> Overview
                    </a>

                    <div class="sidebar-section">Management</div>
                    <a href="?section=members" class="nav-link <?= $section == 'members' ? 'active' : '' ?>">
                        <i class="fa-solid fa-users"></i> Members
                        <?php if ($stat_banned): ?><span class="badge-count"><?= $stat_banned ?></span><?php endif; ?>
                    </a>
                    <a href="?section=tools" class="nav-link <?= $section == 'tools' ? 'active' : '' ?>">
                        <i class="fa-solid fa-toolbox"></i> Tools
                    </a>

                    <div class="sidebar-section">Monitoring</div>
                    <a href="?section=loans" class="nav-link <?= $section == 'loans' ? 'active' : '' ?>">
                        <i class="fa-solid fa-arrow-right-arrow-left"></i> Active Loans
                        <?php if ($stat_loans): ?><span class="badge-count"><?= $stat_loans ?></span><?php endif; ?>
                    </a>
                    <a href="?section=overdue" class="nav-link <?= $section == 'overdue' ? 'active' : '' ?>">
                        <i class="fa-solid fa-clock"></i> Overdue Loans
                        <?php if ($stat_overdue): ?><span class="badge-count"><?= $stat_overdue ?></span><?php endif; ?>
                    </a>
                    <a href="?section=damage" class="nav-link <?= $section == 'damage' ? 'active' : '' ?>">
                        <i class="fa-solid fa-triangle-exclamation"></i> Damage Reports
                        <?php if ($stat_damage): ?><span class="badge-count"><?= $stat_damage ?></span><?php endif; ?>
                    </a>
                    <a href="?section=reviews" class="nav-link <?= $section == 'reviews' ? 'active' : '' ?>">
                        <i class="fa-solid fa-star"></i> Reviews
                    </a>

                    <div class="sidebar-section">Account</div>
                    <a href="user_dashboard.php" class="nav-link">
                        <i class="fa-solid fa-arrow-left"></i> Back to User View
                    </a>
                </nav>

                <div class="sidebar-footer">
                    <div class="sidebar-user">
                        <div class="avatar" style="background:var(--danger);">
                            <?= strtoupper(substr($_SESSION['name'], 0, 1)) ?>
                        </div>
                        <div class="sidebar-user-info">
                            <div class="sidebar-user-name"><?= htmlspecialchars($_SESSION['name']) ?></div>
                            <div class="sidebar-user-role"><span class="admin-badge">Admin</span></div>
                        </div>
                    </div>
                    <a href="logout.php" class="nav-link" style="margin-top:6px;">
                        <i class="fa-solid fa-arrow-right-from-bracket"></i> Logout
                    </a>
                </div>
            </aside>

            <!-- ════════════════════════════════════════════════════════════
     MAIN CONTENT
════════════════════════════════════════════════════════════════ -->
            <div class="main">
                <!-- TOP BAR -->
                <header class="topbar">
                    <button class="hamburger" id="hamburger"><i class="fa-solid fa-bars"></i></button>
                    <div class="topbar-title"><?= $pageTitle ?></div>
                    <div class="topbar-actions">
                        <?php if ($stat_overdue): ?>
                            <span class="badge badge-danger">
                                <i class="fa-solid fa-triangle-exclamation"></i>
                                <?= $stat_overdue ?> Overdue
                            </span>
                        <?php endif; ?>
                        <?php if ($stat_damage): ?>
                            <span class="badge badge-warning">
                                <i class="fa-solid fa-wrench"></i>
                                <?= $stat_damage ?> Need Repair
                            </span>
                        <?php endif; ?>
                        <span style="font-size:.875rem;color:var(--text-muted);">
                            <i class="fa-solid fa-shield-halved" style="color:var(--primary);"></i>
                            <?= htmlspecialchars($_SESSION['name']) ?>
                        </span>
                    </div>
                </header>

                <!-- PAGE BODY -->
                <div class="page-content fade-in">
                    <?= showFlash() ?>

                    <!-- ════════════════════════════════════════════════════════════
     SECTION: OVERVIEW
════════════════════════════════════════════════════════════════ -->
                    <?php if ($section === 'overview'): ?>

                        <?php
                        // ── Richer stats for overview ─────────────────────────────────
                        $stat_available = fetchOne("SELECT COUNT(*) AS cnt FROM Tool WHERE status='Available'")['cnt'];
                        $stat_onloan    = fetchOne("SELECT COUNT(*) AS cnt FROM Tool WHERE status='On Loan'")['cnt'];
                        $stat_inspect   = fetchOne("SELECT COUNT(*) AS cnt FROM Tool WHERE status='Needs Inspection'")['cnt'];
                        $stat_returned  = fetchOne("SELECT COUNT(*) AS cnt FROM Loan_Record WHERE status='Returned'")['cnt'];
                        $stat_reviews   = fetchOne("SELECT COUNT(*) AS cnt FROM Review")['cnt'];
                        $platform_rating = fetchOne("SELECT ROUND(AVG(avg_rating),2) AS avg FROM Member WHERE role='Member' AND avg_rating>0")['avg'] ?? 0;

                        // Most recent activity (last 5 loan events)
                        $recent_activity = fetchAll("
    SELECT lr.loan_id, lr.loan_date, lr.status,
           t.name AS tool, m.name AS borrower,
           own.name AS owner
    FROM Loan_Record lr
    JOIN Tool t   ON lr.tool_id=t.tool_id
    JOIN Member m  ON lr.borrower_id=m.member_id
    JOIN Member own ON t.owner_id=own.member_id
    ORDER BY lr.loan_id DESC LIMIT 8
");

                        // Area activity breakdown
                        $area_stats = fetchAll("
    SELECT a.area_name,
           COUNT(DISTINCT m.member_id) AS members,
           COUNT(DISTINCT t.tool_id)   AS tools
    FROM Area a
    JOIN Member m ON m.area_id=a.area_id
    LEFT JOIN Tool t ON t.owner_id=m.member_id
    GROUP BY a.area_id
    ORDER BY members DESC
");
                        ?>

                        <!-- TOP KPI CARDS -->
                        <div class="stats-grid">
                            <div class="stat-card">
                                <div class="stat-icon indigo"><i class="fa-solid fa-users"></i></div>
                                <div class="stat-value"><?= $stat_members ?></div>
                                <div class="stat-label">Total Members</div>
                                <?php if ($stat_banned): ?>
                                    <div class="stat-change" style="color:var(--danger);">
                                        <?= $stat_banned ?> banned
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="stat-card">
                                <div class="stat-icon blue"><i class="fa-solid fa-toolbox"></i></div>
                                <div class="stat-value"><?= $stat_tools ?></div>
                                <div class="stat-label">Total Tools</div>
                                <div class="stat-change"><?= $stat_available ?> available now</div>
                            </div>
                            <div class="stat-card">
                                <div class="stat-icon green"><i class="fa-solid fa-arrow-right-arrow-left"></i></div>
                                <div class="stat-value"><?= $stat_loans ?></div>
                                <div class="stat-label">Active Loans</div>
                                <div class="stat-change"><?= $stat_returned ?> all-time returned</div>
                            </div>
                            <div class="stat-card">
                                <div class="stat-icon <?= $stat_overdue ? 'red' : 'green' ?>">
                                    <i class="fa-solid fa-clock"></i>
                                </div>
                                <div class="stat-value"><?= $stat_overdue ?></div>
                                <div class="stat-label">Overdue Loans</div>
                                <?php if ($stat_overdue): ?>
                                    <div class="stat-change" style="color:var(--danger);">Needs attention!</div>
                                <?php else: ?>
                                    <div class="stat-change">All on time ✓</div>
                                <?php endif; ?>
                            </div>
                            <div class="stat-card">
                                <div class="stat-icon <?= $stat_damage ? 'amber' : 'green' ?>">
                                    <i class="fa-solid fa-triangle-exclamation"></i>
                                </div>
                                <div class="stat-value"><?= $stat_damage ?></div>
                                <div class="stat-label">Tools Needing Repair</div>
                            </div>
                            <div class="stat-card">
                                <div class="stat-icon amber"><i class="fa-solid fa-star"></i></div>
                                <div class="stat-value"><?= number_format($platform_rating, 2) ?></div>
                                <div class="stat-label">Platform Avg. Rating</div>
                                <div class="stat-change"><?= $stat_reviews ?> total reviews</div>
                            </div>
                        </div>

                        <!-- TOOL STATUS BREAKDOWN + RECENT ACTIVITY -->
                        <div style="display:grid;grid-template-columns:1fr 2fr;gap:18px;margin-bottom:18px;">

                            <!-- Tool Status Donut-style breakdown -->
                            <div class="card">
                                <div class="card-header">
                                    <span><i class="fa-solid fa-chart-pie" style="color:var(--primary);margin-right:6px;"></i>Tool Status</span>
                                </div>
                                <div class="card-body" style="display:flex;flex-direction:column;gap:14px;">
                                    <?php
                                    $statuses = [
                                        ['label' => 'Available',        'count' => $stat_available, 'color' => 'var(--success)',  'icon' => 'fa-check-circle'],
                                        ['label' => 'On Loan',          'count' => $stat_onloan,    'color' => 'var(--primary)', 'icon' => 'fa-arrow-right-arrow-left'],
                                        ['label' => 'Needs Inspection', 'count' => $stat_inspect,   'color' => 'var(--warning)', 'icon' => 'fa-triangle-exclamation'],
                                        ['label' => 'Lost',             'count' => fetchOne("SELECT COUNT(*) AS cnt FROM Tool WHERE status='Lost'")['cnt'], 'color' => 'var(--danger)', 'icon' => 'fa-circle-xmark'],
                                    ];
                                    $total_tools = max($stat_tools, 1);
                                    foreach ($statuses as $s):
                                        $pct = round(($s['count'] / $total_tools) * 100);
                                    ?>
                                        <div>
                                            <div style="display:flex;justify-content:space-between;margin-bottom:5px;">
                                                <span style="font-size:.84rem;font-weight:500;">
                                                    <i class="fa-solid <?= $s['icon'] ?>" style="color:<?= $s['color'] ?>;margin-right:5px;"></i>
                                                    <?= $s['label'] ?>
                                                </span>
                                                <span style="font-size:.84rem;font-weight:700;"><?= $s['count'] ?></span>
                                            </div>
                                            <div class="progress-wrap">
                                                <div class="progress-fill" style="width:<?= $pct ?>%;background:<?= $s['color'] ?>;"></div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>

                            <!-- Recent Loan Activity -->
                            <div class="card">
                                <div class="card-header">
                                    <span><i class="fa-solid fa-clock-rotate-left" style="color:var(--primary);margin-right:6px;"></i>Recent Loan Activity</span>
                                    <a href="?section=loans" class="btn btn-ghost btn-sm">View all →</a>
                                </div>
                                <div class="table-wrap" style="border:none;border-radius:0;">
                                    <table>
                                        <thead>
                                            <tr>
                                                <th>#</th>
                                                <th>Tool</th>
                                                <th>Borrower</th>
                                                <th>Owner</th>
                                                <th>Date</th>
                                                <th>Status</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($recent_activity as $ra): ?>
                                                <tr>
                                                    <td style="color:var(--text-muted);font-size:.8rem;">#<?= $ra['loan_id'] ?></td>
                                                    <td style="font-weight:600;"><?= htmlspecialchars($ra['tool']) ?></td>
                                                    <td><?= htmlspecialchars($ra['borrower']) ?></td>
                                                    <td style="color:var(--text-muted);"><?= htmlspecialchars($ra['owner']) ?></td>
                                                    <td style="font-size:.8rem;color:var(--text-muted);"><?= $ra['loan_date'] ?></td>
                                                    <td><?= badge($ra['status']) ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>

                        <!-- AREA BREAKDOWN -->
                        <div class="card">
                            <div class="card-header">
                                <span><i class="fa-solid fa-map-location-dot" style="color:var(--primary);margin-right:6px;"></i>Activity by Area</span>
                            </div>
                            <div class="table-wrap" style="border:none;border-radius:0;">
                                <table>
                                    <thead>
                                        <tr>
                                            <th>Area</th>
                                            <th>Members</th>
                                            <th>Tools Listed</th>
                                            <th>Coverage</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($area_stats as $as):
                                            $cov = $stat_members > 0 ? round(($as['members'] / $stat_members) * 100) : 0;
                                        ?>
                                            <tr>
                                                <td style="font-weight:600;"><?= htmlspecialchars($as['area_name']) ?></td>
                                                <td><?= $as['members'] ?></td>
                                                <td><?= $as['tools'] ?></td>
                                                <td style="width:200px;">
                                                    <div style="display:flex;align-items:center;gap:8px;">
                                                        <div class="progress-wrap" style="flex:1;">
                                                            <div class="progress-fill" style="width:<?= $cov ?>%"></div>
                                                        </div>
                                                        <span style="font-size:.78rem;font-weight:600;width:28px;"><?= $cov ?>%</span>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- ════════════════════════════════════════════════════════════
     SECTION: MEMBER MANAGEMENT
════════════════════════════════════════════════════════════════ -->
                    <?php elseif ($section === 'members'): ?>

                        <?php
                        // JOIN Area to show area_name, count their tools and loans
                        $members = fetchAll("
    SELECT m.*,
           a.area_name,
           COUNT(DISTINCT t.tool_id)   AS tools_listed,
           COUNT(DISTINCT lr.loan_id)  AS total_loan_records,
           SUM(CASE WHEN lr.status='Active' AND lr.expected_return < CURDATE() THEN 1 ELSE 0 END) AS overdue_count
    FROM Member m
    JOIN Area a ON m.area_id=a.area_id
    LEFT JOIN Tool t ON t.owner_id=m.member_id
    LEFT JOIN Loan_Record lr ON lr.borrower_id=m.member_id
    WHERE m.role='Member'
    GROUP BY m.member_id
    ORDER BY m.created_at DESC
");
                        ?>

                        <div class="section-header">
                            <h2><?= count($members) ?> Members Registered</h2>
                            <!-- Search box — JS filters rows client-side -->
                            <div class="search-box">
                                <i class="fa-solid fa-magnifying-glass"></i>
                                <input type="text" id="table-search" class="form-control" placeholder="Search members…" style="width:220px;">
                            </div>
                        </div>

                        <div class="table-wrap">
                            <table class="sortable">
                                <thead>
                                    <tr>
                                        <th>Member</th>
                                        <th>Area</th>
                                        <th>Rating</th>
                                        <th>Loans</th>
                                        <th>Tools</th>
                                        <th>Overdue</th>
                                        <th>Joined</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($members as $m): ?>
                                        <tr class="searchable-row <?= $m['is_banned'] ? 'member-row-banned' : '' ?>">
                                            <td>
                                                <!-- Member identity cell with avatar + name + email -->
                                                <div style="display:flex;align-items:center;gap:10px;">
                                                    <div class="avatar sm" style="<?= $m['is_banned'] ? 'background:var(--danger)' : '' ?>">
                                                        <?= strtoupper(substr($m['name'], 0, 1)) ?>
                                                    </div>
                                                    <div>
                                                        <a href="profile.php?id=<?= $m['member_id'] ?>"
                                                            style="font-weight:600;color:var(--text);font-size:.875rem;">
                                                            <?= htmlspecialchars($m['name']) ?>
                                                        </a>
                                                        <div class="text-small text-muted"><?= htmlspecialchars($m['email']) ?></div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="text-muted"><?= htmlspecialchars($m['area_name']) ?></td>
                                            <td>
                                                <span style="font-weight:700;color:var(--warning);">
                                                    <?= number_format($m['avg_rating'], 1) ?>
                                                </span>
                                                <i class="fa-solid fa-star" style="color:#F59E0B;font-size:.75rem;"></i>
                                            </td>
                                            <td style="font-weight:600;text-align:center;"><?= $m['total_loans'] ?></td>
                                            <td style="text-align:center;"><?= $m['tools_listed'] ?></td>
                                            <td style="text-align:center;">
                                                <?php if ($m['overdue_count'] > 0): ?>
                                                    <span class="overdue-badge"><?= $m['overdue_count'] ?> overdue</span>
                                                <?php else: ?>
                                                    <span style="color:var(--success);font-size:.8rem;">✓</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-small text-muted"><?= date('d M Y', strtotime($m['created_at'])) ?></td>
                                            <td>
                                                <?php if ($m['is_banned']): ?>
                                                    <span class="badge badge-danger">Banned</span>
                                                <?php else: ?>
                                                    <span class="badge badge-success">Active</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <div class="action-group">
                                                    <a href="profile.php?id=<?= $m['member_id'] ?>"
                                                        class="btn btn-ghost btn-sm">
                                                        <i class="fa-regular fa-eye"></i>
                                                    </a>
                                                    <?php if ($m['is_banned']): ?>
                                                        <!-- UNBAN form — POSTs to actions.php -->
                                                        <form method="POST" action="actions.php" style="display:inline;">
                                                            <input type="hidden" name="action" value="unban_member">
                                                            <input type="hidden" name="member_id" value="<?= $m['member_id'] ?>">
                                                            <input type="hidden" name="back" value="admin_dashboard.php?section=members">
                                                            <button type="submit" class="btn btn-success btn-sm">
                                                                <i class="fa-solid fa-lock-open"></i> Unban
                                                            </button>
                                                        </form>
                                                    <?php else: ?>
                                                        <!-- BAN form -->
                                                        <form method="POST" action="actions.php" style="display:inline;">
                                                            <input type="hidden" name="action" value="ban_member">
                                                            <input type="hidden" name="member_id" value="<?= $m['member_id'] ?>">
                                                            <input type="hidden" name="back" value="admin_dashboard.php?section=members">
                                                            <button type="submit" class="btn btn-danger btn-sm"
                                                                data-confirm="Ban <?= htmlspecialchars($m['name']) ?>? They will be logged out immediately.">
                                                                <i class="fa-solid fa-ban"></i> Ban
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

                        <!-- ════════════════════════════════════════════════════════════
     SECTION: TOOLS MANAGEMENT
════════════════════════════════════════════════════════════════ -->
                    <?php elseif ($section === 'tools'): ?>

                        <?php
                        // Admin sees ALL tools with owner + category + loan count
                        $tools = fetchAll("
    SELECT t.*,
           c.name AS cat_name,
           m.name AS owner_name,
           m.member_id AS owner_id,
           a.area_name,
           COUNT(DISTINCT lr.loan_id) AS total_loans
    FROM Tool t
    JOIN Category c ON t.category_id=c.category_id
    JOIN Member m   ON t.owner_id=m.member_id
    JOIN Area a     ON m.area_id=a.area_id
    LEFT JOIN Loan_Record lr ON lr.tool_id=t.tool_id
    GROUP BY t.tool_id
    ORDER BY t.created_at DESC
");

                        // Count per status for filter bar
                        $avail_cnt   = count(array_filter($tools, fn($t) => $t['status'] === 'Available'));
                        $onloan_cnt  = count(array_filter($tools, fn($t) => $t['status'] === 'On Loan'));
                        $inspect_cnt = count(array_filter($tools, fn($t) => $t['status'] === 'Needs Inspection'));
                        ?>

                        <div class="section-header">
                            <h2><?= count($tools) ?> Tools in System</h2>
                            <div class="search-box">
                                <i class="fa-solid fa-magnifying-glass"></i>
                                <input type="text" id="table-search" class="form-control" placeholder="Search tools…" style="width:220px;">
                            </div>
                        </div>

                        <!-- Quick filter tabs (JS-powered) -->
                        <div style="display:flex;gap:8px;margin-bottom:16px;flex-wrap:wrap;">
                            <button class="btn btn-ghost btn-sm" onclick="filterTable('')">All (<?= count($tools) ?>)</button>
                            <button class="btn btn-ghost btn-sm" onclick="filterTable('Available')">
                                <span class="badge badge-success">Available <?= $avail_cnt ?></span>
                            </button>
                            <button class="btn btn-ghost btn-sm" onclick="filterTable('On Loan')">
                                <span class="badge badge-primary">On Loan <?= $onloan_cnt ?></span>
                            </button>
                            <button class="btn btn-ghost btn-sm" onclick="filterTable('Needs Inspection')">
                                <span class="badge badge-warning">Needs Inspection <?= $inspect_cnt ?></span>
                            </button>
                        </div>

                        <div class="table-wrap">
                            <table>
                                <thead>
                                    <tr>
                                        <th>Tool</th>
                                        <th>Category</th>
                                        <th>Owner</th>
                                        <th>Area</th>
                                        <th>Condition</th>
                                        <th>Status</th>
                                        <th>Loans</th>
                                        <th>Listed</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($tools as $t): ?>
                                        <tr class="searchable-row">
                                            <td>
                                                <div class="td-tool">
                                                    <div class="td-icon"><i class="<?= categoryIcon($t['cat_name']) ?>"></i></div>
                                                    <div>
                                                        <div style="font-weight:600;"><?= htmlspecialchars($t['name']) ?></div>
                                                        <div class="text-small text-muted"><?= htmlspecialchars(trunc($t['description'], 45)) ?></div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td><?= htmlspecialchars($t['cat_name']) ?></td>
                                            <td>
                                                <a href="profile.php?id=<?= $t['owner_id'] ?>" style="color:var(--text);font-weight:500;">
                                                    <?= htmlspecialchars($t['owner_name']) ?>
                                                </a>
                                            </td>
                                            <td class="text-muted"><?= htmlspecialchars($t['area_name']) ?></td>
                                            <td><?= badge($t['condition']) ?></td>
                                            <td><?= badge($t['status']) ?></td>
                                            <td style="font-weight:700;color:var(--primary);text-align:center;"><?= $t['total_loans'] ?></td>
                                            <td class="text-small text-muted"><?= date('d M Y', strtotime($t['created_at'])) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <!-- ════════════════════════════════════════════════════════════
     SECTION: ACTIVE LOANS
════════════════════════════════════════════════════════════════ -->
                    <?php elseif ($section === 'loans'): ?>

                        <?php
                        // All active loans with full context
                        $loans = fetchAll("
    SELECT lr.*,
           t.name AS tool_name, c.name AS cat_name,
           borrow.name AS borrower_name, borrow.phone AS borrower_phone,
           own.name  AS owner_name,
           DATEDIFF(lr.expected_return, CURDATE()) AS days_left,
           DATEDIFF(CURDATE(), lr.expected_return) AS days_late
    FROM Loan_Record lr
    JOIN Tool t      ON lr.tool_id=t.tool_id
    JOIN Category c  ON t.category_id=c.category_id
    JOIN Member borrow ON lr.borrower_id=borrow.member_id
    JOIN Member own    ON t.owner_id=own.member_id
    WHERE lr.status='Active'
    ORDER BY lr.expected_return ASC
");
                        ?>

                        <div class="section-header">
                            <h2><?= count($loans) ?> Active Loan<?= count($loans) != 1 ? 's' : '' ?></h2>
                            <div class="search-box">
                                <i class="fa-solid fa-magnifying-glass"></i>
                                <input type="text" id="table-search" class="form-control" placeholder="Search…" style="width:220px;">
                            </div>
                        </div>

                        <?php if ($stat_overdue): ?>
                            <div class="alert-banner">
                                <i class="fa-solid fa-triangle-exclamation"></i>
                                <strong><?= $stat_overdue ?> loan<?= $stat_overdue > 1 ? 's are' : ' is' ?> overdue.</strong>
                                Borrowers need to return these tools immediately.
                                <a href="?section=overdue" style="margin-left:8px;color:inherit;text-decoration:underline;">View overdue →</a>
                            </div>
                        <?php endif; ?>

                        <?php if ($loans): ?>
                            <div class="table-wrap">
                                <table>
                                    <thead>
                                        <tr>
                                            <th>#</th>
                                            <th>Tool</th>
                                            <th>Borrower</th>
                                            <th>Owner</th>
                                            <th>Loan Date</th>
                                            <th>Expected Return</th>
                                            <th>Days Left</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($loans as $l):
                                            $is_overdue = $l['days_left'] < 0;
                                        ?>
                                            <tr class="searchable-row <?= $is_overdue ? 'overdue-row' : '' ?>">
                                                <td style="color:var(--text-muted);font-size:.8rem;">#<?= $l['loan_id'] ?></td>
                                                <td>
                                                    <div class="td-tool">
                                                        <div class="td-icon"><i class="<?= categoryIcon($l['cat_name']) ?>"></i></div>
                                                        <div style="font-weight:600;"><?= htmlspecialchars($l['tool_name']) ?></div>
                                                    </div>
                                                </td>
                                                <td>
                                                    <?= htmlspecialchars($l['borrower_name']) ?>
                                                    <div class="text-small text-muted"><?= htmlspecialchars($l['borrower_phone']) ?></div>
                                                </td>
                                                <td class="text-muted"><?= htmlspecialchars($l['owner_name']) ?></td>
                                                <td><?= $l['loan_date'] ?></td>
                                                <td style="font-weight:600;color:<?= $is_overdue ? 'var(--danger)' : 'inherit' ?>;">
                                                    <?= $l['expected_return'] ?>
                                                </td>
                                                <td>
                                                    <?php if ($is_overdue): ?>
                                                        <span class="days-overdue">
                                                            <i class="fa-solid fa-triangle-exclamation"></i>
                                                            <?= abs($l['days_late']) ?>d overdue
                                                        </span>
                                                    <?php elseif ($l['days_left'] <= 2): ?>
                                                        <span style="color:var(--warning);font-weight:700;">
                                                            <?= $l['days_left'] ?> day<?= $l['days_left'] != 1 ? 's' : '' ?> left
                                                        </span>
                                                    <?php else: ?>
                                                        <span style="color:var(--success);font-weight:600;">
                                                            <?= $l['days_left'] ?> days
                                                        </span>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="empty-state">
                                <i class="fa-solid fa-check-circle"></i>
                                <h3>No active loans right now</h3>
                                <p>All tools are accounted for.</p>
                            </div>
                        <?php endif; ?>

                        <!-- ════════════════════════════════════════════════════════════
     SECTION: OVERDUE LOANS
════════════════════════════════════════════════════════════════ -->
                    <?php elseif ($section === 'overdue'): ?>

                        <?php
                        // Query: loans where expected_return is BEFORE today AND still Active
                        // DATEDIFF(CURDATE(), expected_return) gives positive value = days overdue
                        $overdue = fetchAll("
    SELECT lr.*,
           t.name AS tool_name, t.tool_id,
           c.name AS cat_name,
           borrow.name  AS borrower_name,
           borrow.phone AS borrower_phone,
           borrow.email AS borrower_email,
           borrow.member_id AS borrower_mid,
           own.name  AS owner_name,
           own.email AS owner_email,
           DATEDIFF(CURDATE(), lr.expected_return) AS days_overdue
    FROM Loan_Record lr
    JOIN Tool t        ON lr.tool_id=t.tool_id
    JOIN Category c    ON t.category_id=c.category_id
    JOIN Member borrow ON lr.borrower_id=borrow.member_id
    JOIN Member own    ON t.owner_id=own.member_id
    WHERE lr.expected_return < CURDATE()
      AND lr.status = 'Active'
    ORDER BY days_overdue DESC
");
                        ?>

                        <div class="section-header">
                            <h2>
                                <?php if ($overdue): ?>
                                    <span style="color:var(--danger);">
                                        <i class="fa-solid fa-triangle-exclamation"></i>
                                        <?= count($overdue) ?> Overdue Loan<?= count($overdue) != 1 ? 's' : '' ?>
                                    </span>
                                <?php else: ?>
                                    Overdue Loans
                                <?php endif; ?>
                            </h2>
                        </div>

                        <?php if ($overdue): ?>
                            <?php foreach ($overdue as $ov): ?>
                                <div class="card" style="margin-bottom:14px;border-left:4px solid var(--danger);">
                                    <div class="card-header" style="background:#FFF5F5;">
                                        <div style="display:flex;align-items:center;gap:10px;">
                                            <div class="td-icon" style="background:var(--danger-light);color:var(--danger);">
                                                <i class="<?= categoryIcon($ov['cat_name']) ?>"></i>
                                            </div>
                                            <div>
                                                <strong><?= htmlspecialchars($ov['tool_name']) ?></strong>
                                                <span style="color:var(--text-muted);font-size:.8rem;margin-left:6px;">Loan #<?= $ov['loan_id'] ?></span>
                                            </div>
                                        </div>
                                        <!-- Days overdue badge — larger the number, redder it feels -->
                                        <span class="days-overdue" style="font-size:1rem;">
                                            <i class="fa-solid fa-clock"></i> <?= $ov['days_overdue'] ?> days overdue
                                        </span>
                                    </div>
                                    <div class="card-body" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:12px;">
                                        <div>
                                            <div class="text-small text-muted">Borrower</div>
                                            <a href="profile.php?id=<?= $ov['borrower_mid'] ?>" style="font-weight:600;color:var(--text);">
                                                <?= htmlspecialchars($ov['borrower_name']) ?>
                                            </a>
                                            <div class="text-small text-muted"><?= htmlspecialchars($ov['borrower_phone']) ?></div>
                                            <div class="text-small text-muted"><?= htmlspecialchars($ov['borrower_email']) ?></div>
                                        </div>
                                        <div>
                                            <div class="text-small text-muted">Owner</div>
                                            <div style="font-weight:600;"><?= htmlspecialchars($ov['owner_name']) ?></div>
                                            <div class="text-small text-muted"><?= htmlspecialchars($ov['owner_email']) ?></div>
                                        </div>
                                        <div>
                                            <div class="text-small text-muted">Loan Date</div>
                                            <div style="font-weight:600;"><?= $ov['loan_date'] ?></div>
                                        </div>
                                        <div>
                                            <div class="text-small text-muted">Was Due</div>
                                            <div style="font-weight:600;color:var(--danger);"><?= $ov['expected_return'] ?></div>
                                        </div>
                                    </div>
                                    <div class="card-footer" style="display:flex;gap:8px;">
                                        <!-- Admin can mark as lost if tool is never returned -->
                                        <form method="POST" action="actions.php" style="display:inline;">
                                            <input type="hidden" name="action" value="admin_mark_lost">
                                            <input type="hidden" name="tool_id" value="<?= $ov['tool_id'] ?>">
                                            <input type="hidden" name="loan_id" value="<?= $ov['loan_id'] ?>">
                                            <input type="hidden" name="back" value="admin_dashboard.php?section=overdue">
                                            <button type="submit" class="btn btn-danger btn-sm"
                                                data-confirm="Mark this tool as Lost? This will close the loan.">
                                                <i class="fa-solid fa-circle-xmark"></i> Mark as Lost
                                            </button>
                                        </form>
                                        <a href="profile.php?id=<?= $ov['borrower_mid'] ?>" class="btn btn-ghost btn-sm">
                                            <i class="fa-regular fa-user"></i> View Borrower Profile
                                        </a>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="empty-state">
                                <i class="fa-solid fa-circle-check" style="color:var(--success);opacity:1;"></i>
                                <h3>No overdue loans! 🎉</h3>
                                <p>All active loans are within their return dates.</p>
                            </div>
                        <?php endif; ?>

                        <!-- ════════════════════════════════════════════════════════════
     SECTION: DAMAGE REPORTS
════════════════════════════════════════════════════════════════ -->
                    <?php elseif ($section === 'damage'): ?>

                        <?php
                        // Join chain: Damage_Report → Loan_Record → Tool → Member (reporter + owner + borrower)
                        $damage_reports = fetchAll("
    SELECT dr.*,
           t.name AS tool_name, t.tool_id, t.status AS tool_status, t.`condition` AS tool_cond,
           c.name AS cat_name,
           reporter.name AS reporter_name,
           borrower.name AS borrower_name,
           owner.name    AS owner_name,
           owner.email   AS owner_email
    FROM Damage_Report dr
    JOIN Loan_Record lr   ON dr.loan_id=lr.loan_id
    JOIN Tool t           ON lr.tool_id=t.tool_id
    JOIN Category c       ON t.category_id=c.category_id
    JOIN Member reporter  ON dr.reported_by=reporter.member_id
    JOIN Member borrower  ON lr.borrower_id=borrower.member_id
    JOIN Member owner     ON t.owner_id=owner.member_id
    ORDER BY dr.reported_at DESC
");

                        $categories = fetchAll("SELECT * FROM Category ORDER BY name");
                        ?>

                        <div class="section-header">
                            <h2><?= count($damage_reports) ?> Damage Report<?= count($damage_reports) != 1 ? 's' : '' ?></h2>
                        </div>

                        <?php if ($damage_reports): ?>
                            <?php foreach ($damage_reports as $dr): ?>
                                <div class="card" style="margin-bottom:14px;border-left:4px solid <?= $dr['severity'] === 'Severe' ? 'var(--danger)' : ($dr['severity'] === 'Moderate' ? 'var(--orange)' : 'var(--warning)') ?>;">
                                    <div class="card-header">
                                        <div style="display:flex;align-items:center;gap:10px;">
                                            <div class="td-icon" style="background:var(--warning-light);color:var(--warning);">
                                                <i class="<?= categoryIcon($dr['cat_name']) ?>"></i>
                                            </div>
                                            <div>
                                                <strong><?= htmlspecialchars($dr['tool_name']) ?></strong>
                                                <span style="color:var(--text-muted);font-size:.8rem;margin-left:6px;">
                                                    reported by <?= htmlspecialchars($dr['reporter_name']) ?>
                                                </span>
                                            </div>
                                        </div>
                                        <div style="display:flex;gap:8px;align-items:center;">
                                            <?= badge($dr['severity']) ?>
                                            <?= badge($dr['tool_status']) ?>
                                        </div>
                                    </div>
                                    <div class="card-body">
                                        <!-- Damage Description -->
                                        <div style="background:var(--bg);border-radius:var(--radius-sm);padding:12px 16px;margin-bottom:14px;">
                                            <div class="text-small text-muted" style="margin-bottom:4px;">Damage Description:</div>
                                            <div style="font-size:.875rem;line-height:1.5;">
                                                <?= htmlspecialchars($dr['description']) ?>
                                            </div>
                                        </div>
                                        <!-- Loan details grid -->
                                        <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(150px,1fr));gap:10px;">
                                            <div>
                                                <div class="text-small text-muted">Borrower</div>
                                                <div style="font-weight:600;"><?= htmlspecialchars($dr['borrower_name']) ?></div>
                                            </div>
                                            <div>
                                                <div class="text-small text-muted">Tool Owner</div>
                                                <div style="font-weight:600;"><?= htmlspecialchars($dr['owner_name']) ?></div>
                                            </div>
                                            <div>
                                                <div class="text-small text-muted">Current Condition</div>
                                                <?= badge($dr['tool_cond']) ?>
                                            </div>
                                            <div>
                                                <div class="text-small text-muted">Reported</div>
                                                <div style="font-size:.8rem;"><?= date('d M Y H:i', strtotime($dr['reported_at'])) ?></div>
                                            </div>
                                        </div>
                                    </div>
                                    <?php if ($dr['tool_status'] === 'Needs Inspection'): ?>
                                        <!-- Admin action: clear the tool back to Available after repair -->
                                        <div class="card-footer" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
                                            <span style="font-size:.8rem;color:var(--text-muted);">Admin action — set final condition after repair:</span>
                                            <form method="POST" action="actions.php" style="display:flex;gap:8px;align-items:center;">
                                                <input type="hidden" name="action" value="admin_clear_tool">
                                                <input type="hidden" name="tool_id" value="<?= $dr['tool_id'] ?>">
                                                <input type="hidden" name="back" value="admin_dashboard.php?section=damage">
                                                <select name="new_condition" class="form-control" style="width:auto;padding:6px 10px;font-size:.8rem;">
                                                    <option value="Good">Good (repaired)</option>
                                                    <option value="Fair">Fair (acceptable)</option>
                                                    <option value="New">New (replaced)</option>
                                                </select>
                                                <button type="submit" class="btn btn-success btn-sm">
                                                    <i class="fa-solid fa-wrench"></i> Clear & Set Available
                                                </button>
                                            </form>
                                        </div>
                                    <?php else: ?>
                                        <div class="card-footer">
                                            <span class="badge badge-success"><i class="fa-solid fa-check"></i> Tool cleared — now <?= $dr['tool_status'] ?></span>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="empty-state">
                                <i class="fa-solid fa-shield-halved" style="color:var(--success);opacity:1;"></i>
                                <h3>No damage reports</h3>
                                <p>All tools have been handled responsibly so far.</p>
                            </div>
                        <?php endif; ?>

                        <!-- ════════════════════════════════════════════════════════════
     SECTION: ALL REVIEWS
════════════════════════════════════════════════════════════════ -->
                    <?php elseif ($section === 'reviews'): ?>

                        <?php
                        $reviews = fetchAll("
    SELECT r.*,
           rev.name AS reviewer_name,
           ree.name AS reviewee_name,
           t.name   AS tool_name,
           c.name   AS cat_name
    FROM Review r
    JOIN Member rev ON r.reviewer_id=rev.member_id
    JOIN Member ree ON r.reviewee_id=ree.member_id
    JOIN Loan_Record lr ON r.loan_id=lr.loan_id
    JOIN Tool t         ON lr.tool_id=t.tool_id
    JOIN Category c     ON t.category_id=c.category_id
    ORDER BY r.created_at DESC
");
                        ?>

                        <div class="section-header">
                            <h2><?= count($reviews) ?> Reviews Submitted</h2>
                            <div class="search-box">
                                <i class="fa-solid fa-magnifying-glass"></i>
                                <input type="text" id="table-search" class="form-control" placeholder="Search reviews…" style="width:220px;">
                            </div>
                        </div>

                        <div class="table-wrap">
                            <table>
                                <thead>
                                    <tr>
                                        <th>Tool</th>
                                        <th>Reviewer</th>
                                        <th>Reviewee</th>
                                        <th>Rating</th>
                                        <th>Comment</th>
                                        <th>Date</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($reviews as $rv): ?>
                                        <tr class="searchable-row">
                                            <td>
                                                <div class="td-tool">
                                                    <div class="td-icon"><i class="<?= categoryIcon($rv['cat_name']) ?>"></i></div>
                                                    <div style="font-weight:600;font-size:.85rem;"><?= htmlspecialchars($rv['tool_name']) ?></div>
                                                </div>
                                            </td>
                                            <td><a href="profile.php?id=<?= $rv['reviewer_id'] ?>" style="color:var(--text);font-weight:500;"><?= htmlspecialchars($rv['reviewer_name']) ?></a></td>
                                            <td><a href="profile.php?id=<?= $rv['reviewee_id'] ?>" style="color:var(--text);font-weight:500;"><?= htmlspecialchars($rv['reviewee_name']) ?></a></td>
                                            <td><?= stars($rv['rating']) ?></td>
                                            <td style="max-width:220px;font-size:.84rem;color:var(--text-muted);">
                                                <?= htmlspecialchars(trunc($rv['comment'], 70)) ?>
                                            </td>
                                            <td class="text-small text-muted"><?= timeAgo($rv['created_at']) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                    <?php endif; ?>

                </div><!-- .page-content -->
            </div><!-- .main -->
        </div><!-- .layout -->

        <script src="assets/app.js"></script>
        <script>
            // ── Tool status quick filter (admin tools page) ────────────────
            // Searches table rows for the given status text
            function filterTable(status) {
                document.querySelectorAll('tbody tr').forEach(row => {
                    if (!status) {
                        row.style.display = '';
                    } else {
                        row.style.display = row.textContent.includes(status) ? '' : 'none';
                    }
                });
            }
        </script>
    </body>

    </html>