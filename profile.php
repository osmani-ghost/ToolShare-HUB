<?php
// ============================================================
// profile.php — ToolShare Hub Public Member Profile
//
// This page can be visited by:
//   1. The member themselves  → sees Edit options
//   2. Another member         → sees public info only
//   3. Admin                  → sees everything + ban button
//
// URL format: profile.php?id=5
// ============================================================
session_start();
require_once 'db.php';
requireLogin(); // Must be logged in to view any profile

$viewer_id   = (int)$_SESSION['member_id'];  // Who is looking
$viewer_role = $_SESSION['role'] ?? 'Member';
$target_id   = (int)($_GET['id'] ?? $viewer_id); // Whose profile to show

// ── LOAD TARGET MEMBER ────────────────────────────────────────
// JOIN Area to get area_name and district in one query
$member = fetchOne("
    SELECT m.*, a.area_name, a.district
    FROM Member m
    JOIN Area a ON m.area_id=a.area_id
    WHERE m.member_id=$target_id
");

// If profile doesn't exist, redirect with error
if (!$member) {
    setFlash('error', 'Member profile not found.');
    header('Location: user_dashboard.php');
    exit;
}

// ── FLAGS ─────────────────────────────────────────────────────
$is_own_profile = ($viewer_id === $target_id);
$is_admin       = ($viewer_role === 'Admin');

// ── STATS for the profile header ─────────────────────────────
// Count reviews received (not total reviews written)
$review_count = fetchOne("
    SELECT COUNT(*) AS cnt FROM Review WHERE reviewee_id=$target_id
")['cnt'];

// Count tools this member owns and has listed
$tools_count = fetchOne("
    SELECT COUNT(*) AS cnt FROM Tool WHERE owner_id=$target_id
")['cnt'];

// Count completed loans as borrower
$loans_count = fetchOne("
    SELECT COUNT(*) AS cnt FROM Loan_Record
    WHERE borrower_id=$target_id AND status='Returned'
")['cnt'];

// ── THEIR TOOLS (public listing) ─────────────────────────────
$their_tools = fetchAll("
    SELECT t.*, c.name AS cat_name,
           COUNT(lr.loan_id) AS borrow_count
    FROM Tool t
    JOIN Category c ON t.category_id=c.category_id
    LEFT JOIN Loan_Record lr ON lr.tool_id=t.tool_id
    WHERE t.owner_id=$target_id
    GROUP BY t.tool_id
    ORDER BY t.status='Available' DESC, t.created_at DESC
");

// ── REVIEWS RECEIVED ──────────────────────────────────────────
$reviews = fetchAll("
    SELECT r.*,
           reviewer.name AS reviewer_name,
           t.name AS tool_name
    FROM Review r
    JOIN Member reviewer ON r.reviewer_id=reviewer.member_id
    JOIN Loan_Record lr  ON r.loan_id=lr.loan_id
    JOIN Tool t          ON lr.tool_id=t.tool_id
    WHERE r.reviewee_id=$target_id
    ORDER BY r.created_at DESC
    LIMIT 20
");

// ── AREAS (for profile edit form) ────────────────────────────
$areas = fetchAll("SELECT * FROM Area ORDER BY area_name");

// ── BACK BUTTON target ───────────────────────────────────────
$back_url = $is_admin ? 'admin_dashboard.php?section=members' : 'user_dashboard.php?section=browse';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($member['name']) ?> — ToolShare Hub</title>
    <link rel="stylesheet" href="assets/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        /* ── Profile-specific styles ── */
        .profile-page {
            max-width: 960px;
            margin: 0 auto;
        }

        .profile-hero {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: var(--radius-xl);
            padding: 36px;
            display: flex;
            align-items: flex-start;
            gap: 28px;
            margin-bottom: 24px;
            box-shadow: var(--shadow-sm);
        }

        .profile-hero-right {
            flex: 1;
        }

        .profile-hero-name {
            font-size: 1.5rem;
            font-weight: 800;
            margin-bottom: 4px;
        }

        .profile-hero-sub {
            color: var(--text-muted);
            font-size: .9rem;
            margin-bottom: 12px;
            display: flex;
            align-items: center;
            gap: 6px;
            flex-wrap: wrap;
        }

        .profile-hero-bio {
            color: var(--text-muted);
            font-size: .875rem;
            line-height: 1.6;
            margin-bottom: 16px;
            font-style: italic;
        }

        .profile-stats-row {
            display: flex;
            gap: 24px;
            flex-wrap: wrap;
            margin-top: 12px;
        }

        .profile-stat-item {
            text-align: center;
            padding: 12px 20px;
            background: var(--bg);
            border-radius: var(--radius);
            border: 1px solid var(--border);
        }

        .profile-stat-item .val {
            font-size: 1.5rem;
            font-weight: 800;
            color: var(--text);
            line-height: 1;
        }

        .profile-stat-item .lbl {
            font-size: .72rem;
            color: var(--text-muted);
            font-weight: 500;
            margin-top: 3px;
            text-transform: uppercase;
            letter-spacing: .05em;
        }

        .tab-nav {
            display: flex;
            gap: 4px;
            border-bottom: 2px solid var(--border);
            margin-bottom: 24px;
        }

        .tab-btn {
            padding: 10px 18px;
            border: none;
            background: transparent;
            font-family: inherit;
            font-size: .875rem;
            font-weight: 500;
            color: var(--text-muted);
            cursor: pointer;
            border-bottom: 2px solid transparent;
            margin-bottom: -2px;
            transition: all .2s;
        }

        .tab-btn.active {
            color: var(--primary);
            border-bottom-color: var(--primary);
            font-weight: 600;
        }

        .tab-btn:hover:not(.active) {
            color: var(--text);
        }

        .tab-panel {
            display: none;
        }

        .tab-panel.active {
            display: block;
        }

        .tool-mini-card {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            padding: 14px 16px;
            display: flex;
            align-items: center;
            gap: 12px;
            transition: box-shadow .2s;
        }

        .tool-mini-card:hover {
            box-shadow: var(--shadow-md);
        }

        .review-item {
            padding: 16px;
            border-bottom: 1px solid var(--border);
            display: flex;
            gap: 12px;
        }

        .review-item:last-child {
            border-bottom: none;
        }

        .banned-banner {
            background: var(--danger-light);
            border: 1px solid #FECACA;
            border-radius: var(--radius);
            padding: 12px 18px;
            color: var(--danger);
            font-weight: 500;
            font-size: .875rem;
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 20px;
        }
    </style>
</head>

<body style="background:var(--bg);">

    <!-- ── SIMPLE TOP NAV (not the sidebar layout) ───────────────── -->
    <!-- Profile page uses a lighter layout — no sidebar clutter -->
    <nav style="background:var(--card);border-bottom:1px solid var(--border);padding:0 28px;height:60px;display:flex;align-items:center;gap:16px;position:sticky;top:0;z-index:100;">
        <a href="<?= $back_url ?>" class="btn btn-ghost btn-sm">
            <i class="fa-solid fa-arrow-left"></i> Back
        </a>
        <span style="font-weight:700;color:var(--text);display:flex;align-items:center;gap:7px;">
            <i class="fa-solid fa-wrench" style="color:var(--primary);"></i>
            ToolShare Hub
        </span>
        <div style="margin-left:auto;display:flex;gap:8px;">
            <a href="<?= $is_admin ? 'admin_dashboard.php' : 'user_dashboard.php' ?>" class="btn btn-ghost btn-sm">
                <i class="fa-solid fa-grid-2"></i> Dashboard
            </a>
            <a href="logout.php" class="btn btn-ghost btn-sm">
                <i class="fa-solid fa-arrow-right-from-bracket"></i> Logout
            </a>
        </div>
    </nav>

    <div style="padding:28px;max-width:980px;margin:0 auto;" class="fade-in">
        <?= showFlash() ?>

        <!-- ── BANNED WARNING (visible to all, including the banned user) ── -->
        <?php if ($member['is_banned']): ?>
            <div class="banned-banner">
                <i class="fa-solid fa-ban"></i>
                This account has been <strong>suspended</strong> by admin.
                <?php if ($is_admin): ?>
                    — You can unban them below.
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <!-- ════════════════════════════════════════════════════════
         PROFILE HERO SECTION
    ════════════════════════════════════════════════════════════ -->
        <div class="profile-hero">
            <!-- Large Avatar -->
            <div class="avatar xl" style="
            flex-shrink:0;
            font-size:2rem;
            background:<?= $member['role'] === 'Admin' ? 'var(--danger)' : 'var(--primary)' ?>;
        ">
                <?= strtoupper(substr($member['name'], 0, 1)) ?>
            </div>

            <div class="profile-hero-right">
                <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
                    <div class="profile-hero-name"><?= htmlspecialchars($member['name']) ?></div>
                    <?php if ($member['role'] === 'Admin'): ?>
                        <span class="badge badge-danger"><i class="fa-solid fa-shield-halved"></i> Admin</span>
                    <?php endif; ?>
                    <?php if ($member['is_banned']): ?>
                        <span class="badge badge-danger">Banned</span>
                    <?php endif; ?>
                </div>

                <div class="profile-hero-sub">
                    <i class="fa-solid fa-location-dot" style="color:var(--primary);"></i>
                    <?= htmlspecialchars($member['area_name']) ?>, <?= htmlspecialchars($member['district']) ?>
                    &nbsp;·&nbsp;
                    <i class="fa-regular fa-calendar"></i>
                    Joined <?= date('F Y', strtotime($member['created_at'])) ?>
                    <?php if ($member['phone'] && ($is_own_profile || $is_admin)): ?>
                        &nbsp;·&nbsp;
                        <i class="fa-solid fa-phone"></i>
                        <?= htmlspecialchars($member['phone']) ?>
                    <?php endif; ?>
                </div>

                <?php if ($member['profile_bio']): ?>
                    <div class="profile-hero-bio">"<?= htmlspecialchars($member['profile_bio']) ?>"</div>
                <?php endif; ?>

                <!-- Rating stars -->
                <div style="display:flex;align-items:center;gap:8px;margin-bottom:16px;">
                    <?= stars($member['avg_rating'], $review_count) ?>
                    <span style="font-size:1.1rem;font-weight:800;color:var(--text);">
                        <?= number_format($member['avg_rating'], 2) ?>
                    </span>
                    <span style="color:var(--text-muted);font-size:.875rem;">
                        / 5.00
                    </span>
                </div>

                <!-- 4 key stats -->
                <div class="profile-stats-row">
                    <div class="profile-stat-item">
                        <div class="val"><?= $tools_count ?></div>
                        <div class="lbl">Tools Listed</div>
                    </div>
                    <div class="profile-stat-item">
                        <div class="val"><?= $member['total_loans'] ?></div>
                        <div class="lbl">Total Borrows</div>
                    </div>
                    <div class="profile-stat-item">
                        <div class="val"><?= $loans_count ?></div>
                        <div class="lbl">Completed Returns</div>
                    </div>
                    <div class="profile-stat-item">
                        <div class="val"><?= $review_count ?></div>
                        <div class="lbl">Reviews Received</div>
                    </div>
                </div>
            </div>

            <!-- Action buttons (top-right of hero) -->
            <div style="display:flex;flex-direction:column;gap:8px;align-items:flex-end;">
                <?php if ($is_own_profile): ?>
                    <!-- Own profile → Edit button opens modal -->
                    <button class="btn btn-outline btn-sm" data-modal-open="modal-edit-profile">
                        <i class="fa-regular fa-pen-to-square"></i> Edit Profile
                    </button>
                    <button class="btn btn-ghost btn-sm" data-modal-open="modal-change-password">
                        <i class="fa-solid fa-key"></i> Change Password
                    </button>

                <?php elseif ($is_admin): ?>
                    <!-- Admin viewing another profile → ban/unban buttons -->
                    <?php if ($member['is_banned']): ?>
                        <form method="POST" action="actions.php">
                            <input type="hidden" name="action" value="unban_member">
                            <input type="hidden" name="member_id" value="<?= $target_id ?>">
                            <input type="hidden" name="back" value="profile.php?id=<?= $target_id ?>">
                            <button type="submit" class="btn btn-success btn-sm">
                                <i class="fa-solid fa-lock-open"></i> Unban Member
                            </button>
                        </form>
                    <?php elseif ($member['role'] !== 'Admin'): ?>
                        <form method="POST" action="actions.php">
                            <input type="hidden" name="action" value="ban_member">
                            <input type="hidden" name="member_id" value="<?= $target_id ?>">
                            <input type="hidden" name="back" value="profile.php?id=<?= $target_id ?>">
                            <button type="submit" class="btn btn-danger btn-sm"
                                data-confirm="Ban this member? They will be locked out.">
                                <i class="fa-solid fa-ban"></i> Ban Member
                            </button>
                        </form>
                    <?php endif; ?>

                <?php else: ?>
                    <!-- Another member's profile → show contact info only -->
                    <?php if ($member['phone']): ?>
                        <div style="display:flex;align-items:center;gap:6px;font-size:.84rem;color:var(--text-muted);">
                            <i class="fa-solid fa-phone" style="color:var(--primary);"></i>
                            <?= htmlspecialchars($member['phone']) ?>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- ════════════════════════════════════════════════════════
         TAB NAVIGATION
    ════════════════════════════════════════════════════════════ -->
        <div class="tab-nav">
            <button class="tab-btn active" onclick="switchTab('tools', this)">
                <i class="fa-solid fa-toolbox"></i> Tools (<?= $tools_count ?>)
            </button>
            <button class="tab-btn" onclick="switchTab('reviews', this)">
                <i class="fa-solid fa-star"></i> Reviews (<?= $review_count ?>)
            </button>
            <?php if ($is_own_profile || $is_admin): ?>
                <button class="tab-btn" onclick="switchTab('history', this)">
                    <i class="fa-solid fa-clock-rotate-left"></i> Loan History
                </button>
            <?php endif; ?>
        </div>

        <!-- ════════════════════════════════════════════════════════
         TAB: TOOLS
    ════════════════════════════════════════════════════════════ -->
        <div class="tab-panel active" id="tab-tools">
            <?php if ($their_tools): ?>
                <div class="tools-grid">
                    <?php foreach ($their_tools as $t): ?>
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
                                <div style="font-size:.78rem;color:var(--text-muted);">
                                    <i class="fa-solid fa-arrow-right-arrow-left"></i>
                                    Borrowed <?= $t['borrow_count'] ?> time<?= $t['borrow_count'] != 1 ? 's' : '' ?>
                                </div>
                            </div>
                            <div class="tool-card-footer">
                                <a href="tool_details.php?id=<?= $t['tool_id'] ?>" class="btn btn-ghost btn-sm">
                                    <i class="fa-regular fa-eye"></i> View
                                </a>
                                <?php if ($t['status'] === 'Available' && !$is_own_profile && !$member['is_banned']): ?>
                                    <!-- Request button — triggers the request modal in user_dashboard via redirect -->
                                    <a href="user_dashboard.php?section=browse" class="btn btn-primary btn-sm">
                                        <i class="fa-solid fa-paper-plane"></i> Request via Browse
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fa-solid fa-toolbox"></i>
                    <h3>No tools listed yet</h3>
                    <?php if ($is_own_profile): ?>
                        <p>List your first tool so neighbours can borrow it.</p>
                        <a href="user_dashboard.php?section=my_tools" class="btn btn-primary" style="margin-top:12px;">
                            List a Tool
                        </a>
                    <?php else: ?>
                        <p>This member hasn't listed any tools yet.</p>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- ════════════════════════════════════════════════════════
         TAB: REVIEWS
    ════════════════════════════════════════════════════════════ -->
        <div class="tab-panel" id="tab-reviews">
            <?php if ($reviews): ?>
                <div class="card">
                    <?php foreach ($reviews as $rv): ?>
                        <div class="review-item">
                            <!-- Reviewer avatar -->
                            <div class="avatar sm" style="flex-shrink:0;margin-top:2px;">
                                <?= strtoupper(substr($rv['reviewer_name'], 0, 1)) ?>
                            </div>
                            <div style="flex:1;">
                                <div style="display:flex;align-items:center;gap:10px;margin-bottom:4px;flex-wrap:wrap;">
                                    <strong style="font-size:.875rem;">
                                        <a href="profile.php?id=<?= $rv['reviewer_id'] ?>" style="color:var(--text);">
                                            <?= htmlspecialchars($rv['reviewer_name']) ?>
                                        </a>
                                    </strong>
                                    <!-- Stars -->
                                    <?= stars($rv['rating']) ?>
                                    <span style="font-weight:700;font-size:.875rem;"><?= $rv['rating'] ?>/5</span>
                                    <span class="text-small text-muted"><?= timeAgo($rv['created_at']) ?></span>
                                </div>
                                <!-- Context: which tool loan this review is for -->
                                <div style="font-size:.78rem;color:var(--text-muted);margin-bottom:6px;">
                                    <i class="fa-solid fa-toolbox"></i> Regarding: <?= htmlspecialchars($rv['tool_name']) ?>
                                </div>
                                <!-- Comment text -->
                                <?php if ($rv['comment']): ?>
                                    <div style="font-size:.875rem;color:var(--text);background:var(--bg);padding:10px 14px;border-radius:8px;line-height:1.55;">
                                        "<?= htmlspecialchars($rv['comment']) ?>"
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fa-regular fa-star"></i>
                    <h3>No reviews yet</h3>
                    <p>Reviews appear here after completed loan transactions.</p>
                </div>
            <?php endif; ?>
        </div>

        <!-- ════════════════════════════════════════════════════════
         TAB: LOAN HISTORY (own profile or admin only)
    ════════════════════════════════════════════════════════════ -->
        <?php if ($is_own_profile || $is_admin): ?>
            <?php
            // Load loan history for this member (as borrower)
            $loan_history = fetchAll("
    SELECT lr.*,
           t.name AS tool_name, c.name AS cat,
           own.name AS owner_name,
           rl.actual_return_date,
           rl.condition_on_return,
           DATEDIFF(CURDATE(), lr.expected_return) AS days_late
    FROM Loan_Record lr
    JOIN Tool t      ON lr.tool_id=t.tool_id
    JOIN Category c  ON t.category_id=c.category_id
    JOIN Member own  ON t.owner_id=own.member_id
    LEFT JOIN Return_Log rl ON rl.loan_id=lr.loan_id
    WHERE lr.borrower_id=$target_id
    ORDER BY lr.loan_date DESC
");
            ?>
            <div class="tab-panel" id="tab-history">
                <?php if ($loan_history): ?>
                    <div class="table-wrap">
                        <table>
                            <thead>
                                <tr>
                                    <th>Tool</th>
                                    <th>Owner</th>
                                    <th>Loan Date</th>
                                    <th>Expected Return</th>
                                    <th>Returned On</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($loan_history as $lh): ?>
                                    <tr>
                                        <td style="font-weight:600;"><?= htmlspecialchars($lh['tool_name']) ?></td>
                                        <td class="text-muted"><?= htmlspecialchars($lh['owner_name']) ?></td>
                                        <td><?= $lh['loan_date'] ?></td>
                                        <td style="color:<?= ($lh['status'] === 'Active' && $lh['days_late'] > 0) ? 'var(--danger)' : 'inherit' ?>;">
                                            <?= $lh['expected_return'] ?>
                                            <?php if ($lh['status'] === 'Active' && $lh['days_late'] > 0): ?>
                                                <span class="overdue-badge"><?= $lh['days_late'] ?>d late</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= $lh['actual_return_date'] ?? '<span class="text-muted text-small">Not yet</span>' ?></td>
                                        <td><?= badge($lh['status']) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="empty-state"><i class="fa-solid fa-arrow-right-arrow-left"></i>
                        <h3>No loan history</h3>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>

    </div><!-- main container -->

    <!-- ════════════════════════════════════════════════════════════
     MODALS (only shown on own profile)
════════════════════════════════════════════════════════════════ -->
    <?php if ($is_own_profile): ?>

        <!-- Edit Profile Modal -->
        <div class="modal-overlay" id="modal-edit-profile">
            <div class="modal">
                <div class="modal-header">
                    <h3><i class="fa-regular fa-pen-to-square" style="color:var(--primary);margin-right:6px;"></i>Edit Profile</h3>
                    <button class="modal-close">✕</button>
                </div>
                <form method="POST" action="actions.php">
                    <input type="hidden" name="action" value="update_profile">
                    <input type="hidden" name="back" value="profile.php?id=<?= $viewer_id ?>">
                    <div class="modal-body">
                        <div class="form-group">
                            <label class="form-label">Full Name <span>*</span></label>
                            <input type="text" name="name" class="form-control"
                                value="<?= htmlspecialchars($member['name']) ?>" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Phone</label>
                            <input type="text" name="phone" class="form-control"
                                value="<?= htmlspecialchars($member['phone'] ?? '') ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Area</label>
                            <select name="area_id" class="form-control">
                                <?php foreach ($areas as $a): ?>
                                    <option value="<?= $a['area_id'] ?>"
                                        <?= $member['area_id'] == $a['area_id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($a['area_name']) ?>, <?= htmlspecialchars($a['district']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Bio</label>
                            <textarea name="bio" class="form-control" rows="3"
                                placeholder="Tell the community about yourself…"><?=
                                                                                    htmlspecialchars($member['profile_bio'] ?? '')
                                                                                    ?></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-ghost modal-close">Cancel</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fa-solid fa-floppy-disk"></i> Save Changes
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Change Password Modal -->
        <div class="modal-overlay" id="modal-change-password">
            <div class="modal">
                <div class="modal-header">
                    <h3><i class="fa-solid fa-key" style="color:var(--primary);margin-right:6px;"></i>Change Password</h3>
                    <button class="modal-close">✕</button>
                </div>
                <form method="POST" action="actions.php">
                    <input type="hidden" name="action" value="change_password">
                    <input type="hidden" name="back" value="profile.php?id=<?= $viewer_id ?>">
                    <div class="modal-body">
                        <div class="form-group">
                            <label class="form-label">Current Password</label>
                            <input type="password" name="current_pass" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">New Password <span>*</span></label>
                            <input type="password" name="new_pass" class="form-control"
                                placeholder="Min. 6 characters" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Confirm New Password <span>*</span></label>
                            <input type="password" name="confirm_pass" class="form-control" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-ghost modal-close">Cancel</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fa-solid fa-key"></i> Update Password
                        </button>
                    </div>
                </form>
            </div>
        </div>

    <?php endif; ?>

    <script src="assets/app.js"></script>
    <script>
        // ── Tab switching logic ────────────────────────────────────────
        // Each tab button calls switchTab(panelId, buttonEl)
        function switchTab(panelId, btn) {
            // Hide all panels
            document.querySelectorAll('.tab-panel').forEach(p => p.classList.remove('active'));
            // Deactivate all tab buttons
            document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
            // Show selected panel
            document.getElementById('tab-' + panelId).classList.add('active');
            // Activate clicked button
            btn.classList.add('active');
        }
    </script>
</body>

</html>