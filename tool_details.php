<?php
// ============================================================
// tool_details.php — ToolShare Hub Full Tool Details Page
//
// Shows everything about one tool:
//   - Full description, condition, status, category
//   - Owner profile card with rating
//   - Borrow request button (if available)
//   - Complete loan history for this tool
//   - Condition change audit log
//   - Reviews about the owner (from loans of this tool)
//
// URL: tool_details.php?id=3
// ============================================================
session_start();
require_once 'db.php';
requireLogin();

$uid     = (int)$_SESSION['member_id'];
$tool_id = (int)($_GET['id'] ?? 0);

// ── LOAD TOOL + JOIN owner + category + area ──────────────────
$tool = fetchOne("
    SELECT t.*,
           c.name AS cat_name,
           m.name AS owner_name, m.member_id AS owner_id,
           m.avg_rating AS owner_rating, m.total_loans AS owner_loans,
           m.created_at AS owner_since, m.profile_bio AS owner_bio,
           m.is_banned AS owner_banned,
           a.area_name, a.district
    FROM Tool t
    JOIN Category c ON t.category_id=c.category_id
    JOIN Member m   ON t.owner_id=m.member_id
    JOIN Area a     ON m.area_id=a.area_id
    WHERE t.tool_id=$tool_id
");

// Redirect if tool not found
if (!$tool) {
    setFlash('error', 'Tool not found.');
    header('Location: user_dashboard.php?section=browse');
    exit;
}

$is_owner = ($uid === (int)$tool['owner_id']);
$is_admin = ($_SESSION['role'] === 'Admin');

// ── Check if current user already has a pending request ──────
$my_pending = fetchOne("
    SELECT req_id FROM Borrow_Request
    WHERE tool_id=$tool_id AND requester_id=$uid AND status='Pending'
");

// ── Check if current user has an active loan on this tool ─────
$my_active_loan = fetchOne("
    SELECT loan_id FROM Loan_Record
    WHERE tool_id=$tool_id AND borrower_id=$uid AND status='Active'
");

// ── LOAN HISTORY for this tool ────────────────────────────────
// LEFT JOIN Return_Log so active loans (no return yet) still show
$loan_history = fetchAll("
    SELECT lr.*,
           borrower.name  AS borrower_name,
           borrower.member_id AS borrower_mid,
           rl.actual_return_date,
           rl.condition_on_return,
           DATEDIFF(CURDATE(), lr.expected_return) AS days_overdue
    FROM Loan_Record lr
    JOIN Member borrower ON lr.borrower_id=borrower.member_id
    LEFT JOIN Return_Log rl ON rl.loan_id=lr.loan_id
    WHERE lr.tool_id=$tool_id
    ORDER BY lr.loan_date DESC
    LIMIT 15
");

// ── CONDITION AUDIT LOG ───────────────────────────────────────
// Tool_Condition_Log is filled by Trigger 4 whenever condition changes
$condition_log = fetchAll("
    SELECT * FROM Tool_Condition_Log
    WHERE tool_id=$tool_id
    ORDER BY changed_at DESC
");

// ── REVIEWS about the owner from loans of THIS tool ───────────
$tool_reviews = fetchAll("
    SELECT r.*,
           reviewer.name AS reviewer_name
    FROM Review r
    JOIN Loan_Record lr ON r.loan_id=lr.loan_id
    JOIN Member reviewer ON r.reviewer_id=reviewer.member_id
    WHERE lr.tool_id=$tool_id
    ORDER BY r.created_at DESC
");

// ── SIMILAR TOOLS (same category, same area, available) ───────
$similar_tools = fetchAll("
    SELECT t.*, m.name AS owner_name, m.avg_rating AS owner_rating
    FROM Tool t
    JOIN Member m ON t.owner_id=m.member_id
    JOIN Area a ON m.area_id=a.area_id
    WHERE t.category_id={$tool['category_id']}
      AND t.tool_id != $tool_id
      AND t.status='Available'
    ORDER BY m.avg_rating DESC
    LIMIT 4
");

// ── Decide which button to show ───────────────────────────────
// can_request = tool is available AND not yours AND no pending req AND not banned
$can_request = (
    $tool['status'] === 'Available'
    && !$is_owner
    && !$my_pending
    && !$my_active_loan
    && !$tool['owner_banned']
);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($tool['name']) ?> — ToolShare Hub</title>
    <link rel="stylesheet" href="assets/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        /* ── Tool Details page specific styles ── */
        .details-layout {
            display: grid;
            grid-template-columns: 1fr 320px;
            gap: 24px;
            align-items: start;
        }

        .tool-hero {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: var(--radius-xl);
            overflow: hidden;
            box-shadow: var(--shadow-sm);
        }

        .tool-hero-top {
            background: linear-gradient(135deg, var(--primary-dark), var(--primary));
            padding: 36px;
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .tool-big-icon {
            width: 80px; height: 80px;
            background: rgba(255,255,255,.15);
            border-radius: 22px;
            display: flex; align-items: center; justify-content: center;
            color: white; font-size: 2.2rem;
            backdrop-filter: blur(8px);
            flex-shrink: 0;
        }

        .tool-hero-title { color: white; font-size: 1.5rem; font-weight: 800; margin-bottom: 6px; }
        .tool-hero-cat   { color: rgba(255,255,255,.7); font-size: .875rem; text-transform: uppercase; letter-spacing: .06em; font-weight: 500; }

        .tool-hero-body  { padding: 28px; }

        .detail-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
            margin-bottom: 20px;
        }

        .detail-item .di-label { font-size: .75rem; color: var(--text-muted); font-weight: 600; text-transform: uppercase; letter-spacing: .05em; margin-bottom: 4px; }
        .detail-item .di-val   { font-size: .9rem; font-weight: 600; color: var(--text); display: flex; align-items: center; gap: 6px; }

        /* Owner sidebar card */
        .owner-card {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: var(--radius-xl);
            overflow: hidden;
            box-shadow: var(--shadow-sm);
        }

        .owner-card-header {
            background: var(--bg);
            padding: 12px 20px;
            font-size: .75rem;
            font-weight: 700;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: .07em;
            border-bottom: 1px solid var(--border);
        }

        .owner-card-body  { padding: 20px; }

        /* Request button state styles */
        .request-btn-area { padding: 20px 28px; border-top: 1px solid var(--border); background: var(--bg); }

        /* History timeline dot */
        .timeline-dot { width: 10px; height: 10px; border-radius: 50%; flex-shrink: 0; margin-top: 5px; }

        @media (max-width: 768px) {
            .details-layout { grid-template-columns: 1fr; }
            .detail-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body style="background:var(--bg);">

<!-- ── SIMPLE NAV ──────────────────────────────────────────────── -->
<nav style="background:var(--card);border-bottom:1px solid var(--border);padding:0 28px;height:60px;display:flex;align-items:center;gap:16px;position:sticky;top:0;z-index:100;">
    <a href="user_dashboard.php?section=browse" class="btn btn-ghost btn-sm">
        <i class="fa-solid fa-arrow-left"></i> Back to Browse
    </a>
    <span style="font-weight:700;display:flex;align-items:center;gap:7px;">
        <i class="fa-solid fa-wrench" style="color:var(--primary);"></i> ToolShare Hub
    </span>
    <div style="margin-left:auto;display:flex;gap:8px;">
        <?php if ($is_admin): ?>
        <a href="admin_dashboard.php" class="btn btn-ghost btn-sm">
            <i class="fa-solid fa-shield-halved"></i> Admin Panel
        </a>
        <?php endif; ?>
        <a href="user_dashboard.php" class="btn btn-ghost btn-sm">
            <i class="fa-solid fa-grid-2"></i> Dashboard
        </a>
    </div>
</nav>

<div style="padding:28px;max-width:1120px;margin:0 auto;" class="fade-in">
    <?= showFlash() ?>

    <!-- BREADCRUMB -->
    <div style="font-size:.8rem;color:var(--text-muted);margin-bottom:16px;display:flex;align-items:center;gap:6px;">
        <a href="user_dashboard.php?section=browse" style="color:var(--text-muted);">Browse</a>
        <i class="fa-solid fa-chevron-right" style="font-size:.65rem;"></i>
        <span style="color:var(--text-muted);"><?= htmlspecialchars($tool['cat_name']) ?></span>
        <i class="fa-solid fa-chevron-right" style="font-size:.65rem;"></i>
        <span style="color:var(--text);"><?= htmlspecialchars($tool['name']) ?></span>
    </div>

    <!-- ════════════════════════════════════════════════════════
         MAIN TWO-COLUMN LAYOUT
    ════════════════════════════════════════════════════════════ -->
    <div class="details-layout">

        <!-- ── LEFT COLUMN: Tool hero + details + history ─────── -->
        <div>

            <!-- Tool Hero Card -->
            <div class="tool-hero" style="margin-bottom:20px;">
                <!-- Colorful header band -->
                <div class="tool-hero-top">
                    <div class="tool-big-icon">
                        <i class="<?= categoryIcon($tool['cat_name']) ?>"></i>
                    </div>
                    <div>
                        <div class="tool-hero-title"><?= htmlspecialchars($tool['name']) ?></div>
                        <div class="tool-hero-cat"><?= htmlspecialchars($tool['cat_name']) ?></div>
                        <div style="display:flex;gap:8px;margin-top:10px;">
                            <?= badge($tool['status']) ?>
                            <?= badge($tool['condition']) ?>
                        </div>
                    </div>
                </div>

                <!-- Detail grid -->
                <div class="tool-hero-body">
                    <div class="detail-grid">
                        <div class="detail-item">
                            <div class="di-label">Status</div>
                            <div class="di-val"><?= badge($tool['status']) ?></div>
                        </div>
                        <div class="detail-item">
                            <div class="di-label">Condition</div>
                            <div class="di-val"><?= badge($tool['condition']) ?></div>
                        </div>
                        <div class="detail-item">
                            <div class="di-label">Location</div>
                            <div class="di-val">
                                <i class="fa-solid fa-location-dot" style="color:var(--primary);"></i>
                                <?= htmlspecialchars($tool['area_name']) ?>, <?= htmlspecialchars($tool['district']) ?>
                            </div>
                        </div>
                        <div class="detail-item">
                            <div class="di-label">Times Borrowed</div>
                            <div class="di-val">
                                <i class="fa-solid fa-arrow-right-arrow-left" style="color:var(--primary);"></i>
                                <?= count($loan_history) ?> time<?= count($loan_history)!=1?'s':'' ?>
                            </div>
                        </div>
                        <div class="detail-item">
                            <div class="di-label">Listed On</div>
                            <div class="di-val"><?= date('d M Y', strtotime($tool['created_at'])) ?></div>
                        </div>
                        <div class="detail-item">
                            <div class="di-label">Category</div>
                            <div class="di-val"><?= htmlspecialchars($tool['cat_name']) ?></div>
                        </div>
                    </div>

                    <!-- Full Description -->
                    <?php if ($tool['description']): ?>
                    <div style="margin-bottom:0;">
                        <div style="font-size:.75rem;color:var(--text-muted);font-weight:700;text-transform:uppercase;letter-spacing:.05em;margin-bottom:8px;">Description</div>
                        <div style="font-size:.9rem;line-height:1.7;color:var(--text);">
                            <?= nl2br(htmlspecialchars($tool['description'])) ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Request Button Area (bottom of hero card) -->
                <div class="request-btn-area">
                    <?php if ($is_owner): ?>
                    <!-- Owner can't request their own tool -->
                    <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
                        <span class="badge badge-primary"><i class="fa-solid fa-toolbox"></i> Your Tool</span>
                        <a href="user_dashboard.php?section=my_tools" class="btn btn-ghost btn-sm">Manage in Dashboard →</a>
                    </div>

                    <?php elseif ($my_active_loan): ?>
                    <!-- Already borrowed — show return button -->
                    <div style="display:flex;align-items:center;gap:12px;">
                        <span class="badge badge-info"><i class="fa-solid fa-clock"></i> Currently borrowed by you</span>
                        <button class="btn btn-success"
                            onclick="openReturnModal(<?= $my_active_loan['loan_id'] ?>, '<?= htmlspecialchars($tool['name'],ENT_QUOTES) ?>')">
                            <i class="fa-solid fa-box-archive"></i> Return This Tool
                        </button>
                    </div>

                    <?php elseif ($my_pending): ?>
                    <!-- Already requested — show cancel button -->
                    <div style="display:flex;align-items:center;gap:12px;">
                        <span class="badge badge-warning"><i class="fa-solid fa-clock"></i> Request pending owner approval</span>
                        <form method="POST" action="actions.php" style="display:inline;">
                            <input type="hidden" name="action" value="cancel_request">
                            <input type="hidden" name="req_id" value="<?= $my_pending['req_id'] ?>">
                            <input type="hidden" name="back" value="tool_details.php?id=<?= $tool_id ?>">
                            <button type="submit" class="btn btn-ghost btn-sm"
                                    data-confirm="Cancel your borrow request for this tool?">
                                <i class="fa-solid fa-xmark"></i> Cancel Request
                            </button>
                        </form>
                    </div>

                    <?php elseif ($can_request): ?>
                    <!-- Show request button -->
                    <button class="btn btn-primary btn-lg btn-full"
                        onclick="openRequestModal(<?= $tool['tool_id'] ?>, '<?= htmlspecialchars($tool['name'],ENT_QUOTES) ?>')">
                        <i class="fa-solid fa-paper-plane"></i> Request to Borrow
                    </button>
                    <div style="text-align:center;font-size:.78rem;color:var(--text-muted);margin-top:8px;">
                        <i class="fa-solid fa-info-circle"></i>
                        The tool owner will review your request before approving.
                    </div>

                    <?php elseif ($tool['status'] !== 'Available'): ?>
                    <!-- Not available -->
                    <div style="text-align:center;">
                        <?= badge($tool['status']) ?>
                        <div style="font-size:.84rem;color:var(--text-muted);margin-top:8px;">
                            This tool is currently <?= $tool['status'] ?> and cannot be requested.
                        </div>
                    </div>

                    <?php elseif ($tool['owner_banned']): ?>
                    <span class="badge badge-danger">Owner account suspended</span>

                    <?php endif; ?>
                </div>
            </div>

            <!-- ── LOAN HISTORY ───────────────────────────────── -->
            <?php if ($loan_history): ?>
            <div class="card" style="margin-bottom:20px;">
                <div class="card-header">
                    <span><i class="fa-solid fa-clock-rotate-left" style="color:var(--primary);margin-right:6px;"></i>Loan History</span>
                    <span class="badge badge-muted"><?= count($loan_history) ?> records</span>
                </div>
                <div class="table-wrap" style="border:none;border-radius:0;">
                <table>
                <thead>
                    <tr>
                        <th>Borrower</th><th>Loan Date</th><th>Return Date</th>
                        <th>Condition Returned</th><th>Status</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach($loan_history as $lh): ?>
                <tr>
                    <td>
                        <a href="profile.php?id=<?= $lh['borrower_mid'] ?>" style="font-weight:600;color:var(--text);">
                            <?= htmlspecialchars($lh['borrower_name']) ?>
                        </a>
                    </td>
                    <td><?= $lh['loan_date'] ?></td>
                    <td>
                        <?= $lh['actual_return_date'] ?? '<span class="text-muted text-small">Not yet</span>' ?>
                        <?php if($lh['status']==='Active' && $lh['days_overdue']>0): ?>
                        <span class="overdue-badge"><?= $lh['days_overdue'] ?>d late</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($lh['condition_on_return']): ?>
                        <?= badge($lh['condition_on_return']) ?>
                        <?php else: ?>
                        <span class="text-muted text-small">—</span>
                        <?php endif; ?>
                    </td>
                    <td><?= badge($lh['status']) ?></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
                </table>
                </div>
            </div>
            <?php endif; ?>

            <!-- ── CONDITION HISTORY (Trigger 4 audit log) ───── -->
            <?php if ($condition_log): ?>
            <div class="card" style="margin-bottom:20px;">
                <div class="card-header">
                    <span>
                        <i class="fa-solid fa-timeline" style="color:var(--primary);margin-right:6px;"></i>
                        Condition History
                    </span>
                    <span style="font-size:.78rem;color:var(--text-muted);">Auto-logged by database trigger</span>
                </div>
                <div class="card-body">
                <?php foreach($condition_log as $cl): ?>
                <div style="display:flex;gap:12px;align-items:flex-start;padding:10px 0;border-bottom:1px solid var(--border);">
                    <!-- Coloured dot shows new condition -->
                    <div class="timeline-dot" style="background:<?=
                        $cl['new_condition']==='New'?'var(--info)':
                        ($cl['new_condition']==='Good'?'var(--success)':
                        ($cl['new_condition']==='Fair'?'var(--warning)':'var(--danger)'))
                    ?>;margin-top:6px;"></div>
                    <div style="flex:1;">
                        <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
                            <?php if ($cl['previous_condition']): ?>
                            <?= badge($cl['previous_condition']) ?>
                            <i class="fa-solid fa-arrow-right" style="font-size:.7rem;color:var(--text-muted);"></i>
                            <?php endif; ?>
                            <?= badge($cl['new_condition']) ?>
                            <span style="font-size:.78rem;color:var(--text-muted);">
                                <?= date('d M Y, H:i', strtotime($cl['changed_at'])) ?>
                            </span>
                        </div>
                        <?php if ($cl['reason']): ?>
                        <div style="font-size:.8rem;color:var(--text-muted);margin-top:3px;">
                            <?= htmlspecialchars($cl['reason']) ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

        </div><!-- left column -->

        <!-- ── RIGHT COLUMN: Owner card + reviews + similar ───── -->
        <div>

            <!-- Owner Card -->
            <div class="owner-card" style="margin-bottom:18px;">
                <div class="owner-card-header">
                    <i class="fa-regular fa-user" style="margin-right:4px;"></i> Tool Owner
                </div>
                <div class="owner-card-body">
                    <div style="display:flex;align-items:center;gap:14px;margin-bottom:16px;">
                        <div class="avatar lg">
                            <?= strtoupper(substr($tool['owner_name'],0,1)) ?>
                        </div>
                        <div>
                            <div style="font-weight:700;font-size:1rem;"><?= htmlspecialchars($tool['owner_name']) ?></div>
                            <div style="font-size:.8rem;color:var(--text-muted);margin-bottom:4px;">
                                <i class="fa-solid fa-location-dot"></i> <?= htmlspecialchars($tool['area_name']) ?>
                            </div>
                            <?= stars($tool['owner_rating']) ?>
                            <span style="font-weight:700;font-size:.875rem;margin-left:4px;">
                                <?= number_format($tool['owner_rating'],2) ?>
                            </span>
                        </div>
                    </div>

                    <?php if ($tool['owner_bio']): ?>
                    <div style="font-size:.82rem;color:var(--text-muted);line-height:1.5;margin-bottom:14px;font-style:italic;background:var(--bg);padding:10px 12px;border-radius:8px;">
                        "<?= htmlspecialchars($tool['owner_bio']) ?>"
                    </div>
                    <?php endif; ?>

                    <!-- Quick stats -->
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-bottom:16px;">
                        <div style="background:var(--bg);border-radius:8px;padding:10px;text-align:center;">
                            <div style="font-size:1.1rem;font-weight:800;color:var(--text);"><?= $tool['owner_loans'] ?></div>
                            <div style="font-size:.7rem;color:var(--text-muted);font-weight:600;">Total Loans</div>
                        </div>
                        <div style="background:var(--bg);border-radius:8px;padding:10px;text-align:center;">
                            <div style="font-size:1.1rem;font-weight:800;color:var(--text);"><?= date('Y', strtotime($tool['owner_since'])) ?></div>
                            <div style="font-size:.7rem;color:var(--text-muted);font-weight:600;">Member Since</div>
                        </div>
                    </div>

                    <a href="profile.php?id=<?= $tool['owner_id'] ?>" class="btn btn-outline btn-sm btn-full">
                        <i class="fa-regular fa-user"></i> View Full Profile
                    </a>
                </div>
            </div>

            <!-- Reviews about owner from this tool's loans -->
            <?php if ($tool_reviews): ?>
            <div class="card" style="margin-bottom:18px;">
                <div class="card-header" style="font-size:.875rem;">
                    <span><i class="fa-solid fa-star" style="color:#F59E0B;margin-right:6px;"></i>Reviews</span>
                    <span class="text-small text-muted"><?= count($tool_reviews) ?> total</span>
                </div>
                <?php foreach(array_slice($tool_reviews, 0, 5) as $rv): ?>
                <div style="padding:14px 16px;border-bottom:1px solid var(--border);">
                    <div style="display:flex;align-items:center;gap:8px;margin-bottom:5px;">
                        <div class="avatar sm"><?= strtoupper(substr($rv['reviewer_name'],0,1)) ?></div>
                        <span style="font-weight:600;font-size:.84rem;"><?= htmlspecialchars($rv['reviewer_name']) ?></span>
                        <?= stars($rv['rating']) ?>
                        <span style="font-size:.7rem;color:var(--text-muted);margin-left:auto;"><?= timeAgo($rv['created_at']) ?></span>
                    </div>
                    <?php if ($rv['comment']): ?>
                    <div style="font-size:.8rem;color:var(--text-muted);padding-left:34px;line-height:1.5;">
                        "<?= htmlspecialchars(trunc($rv['comment'],100)) ?>"
                    </div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
                <?php if (count($tool_reviews) > 5): ?>
                <div style="padding:10px 16px;text-align:center;">
                    <a href="profile.php?id=<?= $tool['owner_id'] ?>" style="font-size:.8rem;color:var(--primary);">
                        View all <?= count($tool_reviews) ?> reviews →
                    </a>
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <!-- Similar Tools -->
            <?php if ($similar_tools): ?>
            <div class="card">
                <div class="card-header" style="font-size:.875rem;">
                    <span><i class="fa-solid fa-layer-group" style="color:var(--primary);margin-right:6px;"></i>Similar Tools</span>
                </div>
                <?php foreach($similar_tools as $st): ?>
                <div style="padding:12px 16px;border-bottom:1px solid var(--border);display:flex;align-items:center;gap:10px;">
                    <div class="td-icon"><i class="<?= categoryIcon($tool['cat_name']) ?>"></i></div>
                    <div style="flex:1;">
                        <a href="tool_details.php?id=<?= $st['tool_id'] ?>"
                           style="font-weight:600;font-size:.875rem;color:var(--text);">
                            <?= htmlspecialchars($st['name']) ?>
                        </a>
                        <div style="font-size:.75rem;color:var(--text-muted);">
                            by <?= htmlspecialchars($st['owner_name']) ?>
                            <?= stars($st['owner_rating']) ?>
                        </div>
                    </div>
                    <?= badge($st['status']) ?>
                </div>
                <?php endforeach; ?>
                <div style="padding:10px 16px;">
                    <a href="user_dashboard.php?section=browse&cat=<?= $tool['category_id'] ?>"
                       style="font-size:.8rem;color:var(--primary);">
                        See all <?= htmlspecialchars($tool['cat_name']) ?> →
                    </a>
                </div>
            </div>
            <?php endif; ?>

        </div><!-- right column -->
    </div><!-- .details-layout -->
</div><!-- outer container -->

<!-- ════════════════════════════════════════════════════════════
     MODALS (Request + Return — reused from user_dashboard)
════════════════════════════════════════════════════════════════ -->

<!-- Request Tool Modal -->
<div class="modal-overlay" id="modal-request">
  <div class="modal">
    <div class="modal-header">
        <h3><i class="fa-solid fa-paper-plane" style="color:var(--primary);margin-right:6px;"></i>Request: <span id="req_tool_name"></span></h3>
        <button class="modal-close">✕</button>
    </div>
    <form method="POST" action="actions.php">
        <input type="hidden" name="action"  value="request_tool">
        <input type="hidden" name="back"    value="tool_details.php?id=<?= $tool_id ?>">
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
                <textarea name="message" class="form-control" rows="3"
                          placeholder="Why do you need it? When will you pick it up? (optional)"></textarea>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-ghost modal-close">Cancel</button>
            <button type="submit" class="btn btn-primary">
                <i class="fa-solid fa-paper-plane"></i> Send Request
            </button>
        </div>
    </form>
  </div>
</div>

<!-- Return Tool Modal (for currently borrowed tool) -->
<div class="modal-overlay" id="modal-return">
  <div class="modal">
    <div class="modal-header">
        <h3><i class="fa-solid fa-box-archive" style="color:var(--success);margin-right:6px;"></i>Return: <span id="ret_tool_name"></span></h3>
        <button class="modal-close">✕</button>
    </div>
    <form method="POST" action="actions.php">
        <input type="hidden" name="action"  value="return_tool">
        <input type="hidden" name="back"    value="tool_details.php?id=<?= $tool_id ?>">
        <input type="hidden" name="loan_id" id="ret_loan_id">
        <div class="modal-body">
            <div class="form-group">
                <label class="form-label">Condition on Return <span>*</span></label>
                <select name="condition_on_return" class="form-control" required>
                    <option value="New">New — No change at all</option>
                    <option value="Good" selected>Good — Minor use, fully functional</option>
                    <option value="Fair">Fair — Visible wear, still works</option>
                    <option value="Damaged">Damaged — Something broke or cracked</option>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">Return Notes</label>
                <textarea name="notes" class="form-control" rows="2"
                          placeholder="Any notes about accessories, damage, or the condition?"></textarea>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-ghost modal-close">Cancel</button>
            <button type="submit" class="btn btn-success">
                <i class="fa-solid fa-check"></i> Confirm Return
            </button>
        </div>
    </form>
  </div>
</div>

<script src="assets/app.js"></script>
</body>
</html>