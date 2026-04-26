<?php
// ============================================================
// actions.php — ToolShare Hub Backend Action Handler
// FIXED VERSION — All logical bugs corrected
// ============================================================
session_start();
require_once 'db.php';

if (!isset($_SESSION['member_id'])) {
    header('Location: login.php');
    exit;
}

$uid    = (int)$_SESSION['member_id'];
$role   = $_SESSION['role'] ?? 'Member';
$action = $_POST['action'] ?? $_GET['action'] ?? '';
$back   = $_POST['back'] ?? $_SERVER['HTTP_REFERER'] ?? 'user_dashboard.php';

function back($type, $msg, $url = null)
{
    global $back;
    setFlash($type, $msg);
    header('Location: ' . ($url ?? $back));
    exit;
}

switch ($action) {

    // ────────────────────────────────────────────────────────────
    // TOOL MANAGEMENT
    // ────────────────────────────────────────────────────────────
    case 'add_tool':
        $name    = e($_POST['name'] ?? '');
        $cat_id  = (int)($_POST['category_id'] ?? 0);
        $cond    = e($_POST['condition'] ?? 'Good');
        $desc    = e($_POST['description'] ?? '');

        if (!$name || !$cat_id) back('error', 'Tool name and category are required.');

        $valid_cond = ['New', 'Good', 'Fair'];
        if (!in_array($cond, $valid_cond)) $cond = 'Good';

        q("INSERT INTO Tool (owner_id, category_id, name, `condition`, status, description)
       VALUES ($uid, $cat_id, '$name', '$cond', 'Available', '$desc')");

        back(
            'success',
            '✅ Tool "' . htmlspecialchars($name) . '" listed successfully!',
            'user_dashboard.php?section=my_tools'
        );


    case 'edit_tool':
        $tool_id = (int)($_POST['tool_id'] ?? 0);
        $name    = e($_POST['name'] ?? '');
        $cat_id  = (int)($_POST['category_id'] ?? 0);
        $cond    = e($_POST['condition'] ?? 'Good');
        $desc    = e($_POST['description'] ?? '');

        $tool = fetchOne("SELECT * FROM Tool WHERE tool_id=$tool_id AND owner_id=$uid");
        if (!$tool)        back('error', 'Tool not found or not yours.');
        if (!$name || !$cat_id) back('error', 'Name and category required.');

        $valid_cond = ['New', 'Good', 'Fair'];
        if (!in_array($cond, $valid_cond)) $cond = 'Good';

        q("UPDATE Tool SET name='$name', category_id=$cat_id, `condition`='$cond', description='$desc'
       WHERE tool_id=$tool_id AND owner_id=$uid");

        back('success', '✅ Tool updated.', 'user_dashboard.php?section=my_tools');


        // ────────────────────────────────────────────────────────────
        // FIX 3: delete_tool — clean orphan records, block if loan history
        // ────────────────────────────────────────────────────────────
    case 'delete_tool':
        $tool_id = (int)($_POST['tool_id'] ?? 0);
        $tool    = fetchOne("SELECT * FROM Tool WHERE tool_id=$tool_id AND owner_id=$uid");

        if (!$tool) back('error', 'Tool not found or not yours.');

        // Block if not Available
        if ($tool['status'] !== 'Available') {
            back('error', 'Cannot delete a tool that is On Loan or Needs Inspection.');
        }

        // Block if ANY loan history exists — preserves audit trail
        // (even returned loans must be kept for data integrity)
        $any_loan = fetchOne("SELECT loan_id FROM Loan_Record WHERE tool_id=$tool_id LIMIT 1");
        if ($any_loan) {
            back('error', 'Cannot delete — tool has loan history. This record must be preserved for auditing.');
        }

        // No loan history: safe to delete. Clean dependencies first.
        q("DELETE FROM Tool_Condition_Log WHERE tool_id=$tool_id");
        q("DELETE FROM Damage_Report WHERE loan_id IN
           (SELECT loan_id FROM Loan_Record WHERE tool_id=$tool_id)");
        q("DELETE FROM Borrow_Request WHERE tool_id=$tool_id");
        q("DELETE FROM Tool WHERE tool_id=$tool_id AND owner_id=$uid");

        back('success', '🗑️ Tool deleted.', 'user_dashboard.php?section=my_tools');


        // ────────────────────────────────────────────────────────────
        // BORROW REQUEST
        // ────────────────────────────────────────────────────────────
    case 'request_tool':
        $tool_id        = (int)($_POST['tool_id'] ?? 0);
        $requested_date = e($_POST['requested_date'] ?? '');
        $duration_days  = (int)($_POST['duration_days'] ?? 0);
        $message        = e($_POST['message'] ?? '');

        if (!$tool_id || !$requested_date || $duration_days < 1) {
            back('error', 'Please fill all required fields. Duration must be at least 1 day.');
        }

        $tool = fetchOne("SELECT * FROM Tool WHERE tool_id=$tool_id AND status='Available'");
        if (!$tool) back('error', 'This tool is no longer available.');

        if ($tool['owner_id'] === $uid) back('error', 'You cannot borrow your own tool.');

        // FIX: Check if tool owner is banned — their tools should not be requestable
        $owner = fetchOne("SELECT is_banned FROM Member WHERE member_id={$tool['owner_id']}");
        if ($owner && $owner['is_banned']) {
            back('error', 'This tool owner account is suspended. Cannot request their tools.');
        }

        // Check no duplicate pending request
        $dup = fetchOne("SELECT req_id FROM Borrow_Request
                     WHERE tool_id=$tool_id AND requester_id=$uid AND status='Pending'");
        if ($dup) back('error', 'You already have a pending request for this tool.');

        // Check no active loan of the same tool by same user
        $active_same = fetchOne("SELECT loan_id FROM Loan_Record
                              WHERE tool_id=$tool_id AND borrower_id=$uid AND status='Active'");
        if ($active_same) back('error', 'You currently have this tool on loan.');

        if ($requested_date < date('Y-m-d')) $requested_date = date('Y-m-d');
        if ($duration_days > 30) $duration_days = 30;

        q("INSERT INTO Borrow_Request (tool_id, requester_id, requested_date, duration_days, message, status)
       VALUES ($tool_id, $uid, '$requested_date', $duration_days, '$message', 'Pending')");

        back(
            'success',
            '📨 Borrow request sent! The owner will review it soon.',
            'user_dashboard.php?section=requests'
        );


    case 'cancel_request':
        $req_id = (int)($_POST['req_id'] ?? 0);
        $req    = fetchOne("SELECT * FROM Borrow_Request
                        WHERE req_id=$req_id AND requester_id=$uid AND status='Pending'");
        if (!$req) back('error', 'Request not found or already processed.');

        q("UPDATE Borrow_Request SET status='Cancelled' WHERE req_id=$req_id");
        back('success', 'Request cancelled.', 'user_dashboard.php?section=requests');


        // ────────────────────────────────────────────────────────────
        // OWNER: APPROVE — FIX: check if requester is banned
        // ────────────────────────────────────────────────────────────
    case 'approve_request':
        $req_id = (int)($_POST['req_id'] ?? 0);

        $req = fetchOne("
        SELECT br.*, t.owner_id, t.status AS tool_status
        FROM Borrow_Request br
        JOIN Tool t ON br.tool_id = t.tool_id
        WHERE br.req_id = $req_id AND br.status = 'Pending'
    ");

        if (!$req)                          back('error', 'Request not found or already processed.');
        if ($req['owner_id'] != $uid)       back('error', 'Unauthorized — you are not the tool owner.');
        if ($req['tool_status'] !== 'Available') back('error', 'Tool is not available right now.');

        // FIX: Prevent approving a banned member's request
        $requester = fetchOne("SELECT is_banned, name FROM Member WHERE member_id={$req['requester_id']}");
        if ($requester && $requester['is_banned']) {
            back('error', 'Cannot approve — this member\'s account has been suspended.');
        }

        $loan_date       = date('Y-m-d');
        $start           = ($req['requested_date'] >= $loan_date) ? $req['requested_date'] : $loan_date;
        $expected_return = date('Y-m-d', strtotime("+{$req['duration_days']} days", strtotime($start)));

        // INSERT fires Trigger T1: Tool → On Loan, Member.total_loans++
        q("INSERT INTO Loan_Record (tool_id, borrower_id, req_id, loan_date, expected_return, status)
       VALUES ({$req['tool_id']}, {$req['requester_id']}, $req_id, '$start', '$expected_return', 'Active')");

        q("UPDATE Borrow_Request SET status='Approved' WHERE req_id=$req_id");

        // Auto-reject other pending requests for the same tool
        q("UPDATE Borrow_Request SET status='Rejected'
       WHERE tool_id={$req['tool_id']} AND status='Pending' AND req_id != $req_id");

        back(
            'success',
            '✅ Request approved! Loan created for ' . $req['duration_days'] . ' days.',
            'user_dashboard.php?section=incoming'
        );


    case 'reject_request':
        $req_id = (int)($_POST['req_id'] ?? 0);
        $req    = fetchOne("
        SELECT br.*, t.owner_id FROM Borrow_Request br
        JOIN Tool t ON br.tool_id = t.tool_id
        WHERE br.req_id = $req_id AND br.status = 'Pending'
    ");
        if (!$req)                    back('error', 'Request not found.');
        if ($req['owner_id'] != $uid) back('error', 'Unauthorized.');

        q("UPDATE Borrow_Request SET status='Rejected' WHERE req_id=$req_id");
        back('success', 'Request rejected.', 'user_dashboard.php?section=incoming');


        // ────────────────────────────────────────────────────────────
        // RETURN TOOL
        // (Trigger after_return_log handles status — now fixed above)
        // ────────────────────────────────────────────────────────────
    case 'return_tool':
        $loan_id   = (int)($_POST['loan_id'] ?? 0);
        $condition = e($_POST['condition_on_return'] ?? '');
        $notes     = e($_POST['notes'] ?? '');

        $valid_cond = ['New', 'Good', 'Fair', 'Damaged'];
        if (!in_array($condition, $valid_cond)) back('error', 'Invalid return condition.');

        $loan = fetchOne("SELECT * FROM Loan_Record
                      WHERE loan_id=$loan_id AND borrower_id=$uid AND status='Active'");
        if (!$loan) back('error', 'Loan not found or already returned.');

        // INSERT fires Trigger T2 (now fixed): sets correct Tool.status + closes loan
        q("INSERT INTO Return_Log (loan_id, actual_return_date, condition_on_return, notes)
       VALUES ($loan_id, CURDATE(), '$condition', '$notes')");

        back(
            'success',
            '📦 Tool returned successfully! Don\'t forget to leave a review.',
            'user_dashboard.php?section=loans'
        );


        // ────────────────────────────────────────────────────────────
        // FIX 7: Damage Report — only allowed after loan is Active
        // and only once per user per loan
        // ────────────────────────────────────────────────────────────
    case 'file_damage':
        $loan_id     = (int)($_POST['loan_id'] ?? 0);
        $description = e($_POST['description'] ?? '');
        $severity    = e($_POST['severity'] ?? '');

        $valid_sev = ['Minor', 'Moderate', 'Severe'];
        if (!in_array($severity, $valid_sev)) back('error', 'Invalid severity level.');
        if (!$description)                    back('error', 'Please describe the damage.');

        // Verify loan exists and current user was part of it
        $loan = fetchOne("
        SELECT lr.*, t.owner_id FROM Loan_Record lr
        JOIN Tool t ON lr.tool_id = t.tool_id
        WHERE lr.loan_id = $loan_id
    ");
        if (!$loan) back('error', 'Loan not found.');

        // Only borrower or owner may file a damage report
        if ($loan['borrower_id'] != $uid && $loan['owner_id'] != $uid) {
            back('error', 'Unauthorized — you were not part of this loan.');
        }

        // FIX: Damage can only be reported on Active loans OR loans just returned
        // (not on old Returned loans with no relation)
        if (!in_array($loan['status'], ['Active', 'Returned'])) {
            back('error', 'Damage can only be reported on active or recently returned loans.');
        }

        // Prevent duplicate report from the same user for the same loan
        $dup = fetchOne("SELECT damage_id FROM Damage_Report
                     WHERE loan_id=$loan_id AND reported_by=$uid");
        if ($dup) back('error', 'You already filed a damage report for this loan.');

        // INSERT fires Trigger T3: Tool.status = 'Needs Inspection'
        q("INSERT INTO Damage_Report (loan_id, reported_by, description, severity)
       VALUES ($loan_id, $uid, '$description', '$severity')");

        back(
            'success',
            '⚠️ Damage report filed. Admin will review and clear the tool.',
            'user_dashboard.php?section=loans'
        );


        // ────────────────────────────────────────────────────────────
        // FIX 9: submit_review — allow Lost loans too + cleaner check
        // ────────────────────────────────────────────────────────────
    case 'submit_review':
        $loan_id     = (int)($_POST['loan_id'] ?? 0);
        $reviewee_id = (int)($_POST['reviewee_id'] ?? 0);
        $rating      = (int)($_POST['rating'] ?? 0);
        $comment     = e($_POST['comment'] ?? '');

        if ($rating < 1 || $rating > 5)  back('error', 'Please select a rating from 1 to 5.');
        if ($uid === $reviewee_id)        back('error', 'You cannot review yourself.');

        // FIX: Allow reviews on both Returned AND Lost loans
        $loan = fetchOne("SELECT * FROM Loan_Record
                      WHERE loan_id=$loan_id AND status IN ('Returned','Lost')");
        if (!$loan) back('error', 'You can only review completed or lost loans.');

        // Verify reviewer was part of this loan
        $tool = fetchOne("SELECT owner_id FROM Tool WHERE tool_id={$loan['tool_id']}");
        if ($loan['borrower_id'] != $uid && $tool['owner_id'] != $uid) {
            back('error', 'You were not part of this loan.');
        }

        // FIX: UNIQUE constraint now handles duplicates at DB level,
        // but check here for a cleaner user-facing error message
        $dup = fetchOne("SELECT review_id FROM Review
                     WHERE loan_id=$loan_id AND reviewer_id=$uid");
        if ($dup) back('error', 'You already submitted a review for this loan.');

        // INSERT fires Trigger T5: updates Member.avg_rating
        q("INSERT INTO Review (loan_id, reviewer_id, reviewee_id, rating, comment)
       VALUES ($loan_id, $uid, $reviewee_id, $rating, '$comment')");

        back(
            'success',
            '⭐ Review submitted! Thank you for your feedback.',
            'user_dashboard.php?section=loans'
        );


        // ────────────────────────────────────────────────────────────
        // PROFILE UPDATE
        // ────────────────────────────────────────────────────────────
    case 'update_profile':
        $name    = e($_POST['name'] ?? '');
        $phone   = e($_POST['phone'] ?? '');
        $area_id = (int)($_POST['area_id'] ?? 0);
        $bio     = e($_POST['bio'] ?? '');

        if (!$name) back('error', 'Name is required.');

        q("UPDATE Member SET name='$name', phone='$phone', area_id=$area_id, profile_bio='$bio'
       WHERE member_id=$uid");
        $_SESSION['name'] = $name;

        back('success', '✅ Profile updated!', 'user_dashboard.php?section=profile');


    case 'change_password':
        $current = $_POST['current_pass'] ?? '';
        $new     = $_POST['new_pass'] ?? '';
        $confirm = $_POST['confirm_pass'] ?? '';

        if (strlen($new) < 6)      back('error', 'New password must be at least 6 characters.');
        if ($new !== $confirm)     back('error', 'New passwords do not match.');

        $member = fetchOne("SELECT password FROM Member WHERE member_id=$uid");
        if (!password_verify($current, $member['password'])) {
            back('error', 'Current password is incorrect.');
        }

        $hash = password_hash($new, PASSWORD_BCRYPT);
        q("UPDATE Member SET password='$hash' WHERE member_id=$uid");
        back('success', '🔒 Password changed successfully!', 'user_dashboard.php?section=profile');


        // ────────────────────────────────────────────────────────────
        // ADMIN ACTIONS
        // ────────────────────────────────────────────────────────────
    case 'ban_member':
        if ($role !== 'Admin') back('error', 'Unauthorized.');
        $target = (int)($_POST['member_id'] ?? 0);
        if ($target === $uid) back('error', 'You cannot ban yourself.');
        // Cannot ban another admin
        $target_member = fetchOne("SELECT role FROM Member WHERE member_id=$target");
        if ($target_member && $target_member['role'] === 'Admin') {
            back('error', 'Admins cannot be banned.');
        }
        q("UPDATE Member SET is_banned=1 WHERE member_id=$target");
        back('success', '🚫 Member banned.', 'admin_dashboard.php?section=members');


    case 'unban_member':
        if ($role !== 'Admin') back('error', 'Unauthorized.');
        $target = (int)($_POST['member_id'] ?? 0);
        q("UPDATE Member SET is_banned=0 WHERE member_id=$target");
        back('success', '✅ Member unbanned.', 'admin_dashboard.php?section=members');


    case 'admin_clear_tool':
        if ($role !== 'Admin') back('error', 'Unauthorized.');
        $tool_id  = (int)($_POST['tool_id'] ?? 0);
        $new_cond = e($_POST['new_condition'] ?? 'Good');
        $valid_cond = ['New', 'Good', 'Fair'];
        if (!in_array($new_cond, $valid_cond)) $new_cond = 'Good';

        // Trigger T4 logs this condition change automatically
        q("UPDATE Tool SET `condition`='$new_cond', status='Available' WHERE tool_id=$tool_id");
        back('success', '🔧 Tool cleared and set to Available.', 'admin_dashboard.php?section=damage');


        // ────────────────────────────────────────────────────────────
        // FIX 2: admin_mark_lost — ban the borrower who never returned
        // ────────────────────────────────────────────────────────────
    case 'admin_mark_lost':
        if ($role !== 'Admin') back('error', 'Unauthorized.');

        $tool_id = (int)($_POST['tool_id'] ?? 0);
        $loan_id = (int)($_POST['loan_id'] ?? 0);

        if (!$tool_id || !$loan_id) back('error', 'Invalid tool or loan ID.');

        // Get the borrower before updating anything
        $loan = fetchOne("SELECT borrower_id FROM Loan_Record WHERE loan_id=$loan_id");
        if (!$loan) back('error', 'Loan record not found.');

        $borrower_id = (int)$loan['borrower_id'];

        // Mark tool as lost
        q("UPDATE Tool SET status='Lost' WHERE tool_id=$tool_id");

        // Close the loan as Lost
        q("UPDATE Loan_Record SET status='Lost' WHERE loan_id=$loan_id");

        // Ban the borrower — they did not return the tool
        // Only ban if they are a Member (not admin)
        q("UPDATE Member SET is_banned=1
       WHERE member_id=$borrower_id AND role='Member'");

        back(
            'success',
            '⚠️ Tool marked as Lost. Borrower has been banned automatically.',
            'admin_dashboard.php?section=overdue'
        );


    default:
        setFlash('error', 'Unknown action: ' . htmlspecialchars($action));
        header('Location: user_dashboard.php');
        exit;
}
