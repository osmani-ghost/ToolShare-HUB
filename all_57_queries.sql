USE toolshare_hub;

-- ============================================================
-- AGGREGATE QUERIES (AGG-1 to AGG-8)
-- ============================================================

-- AGG-1: Count total tools per category
SELECT c.name AS category,
       COUNT(t.tool_id) AS total_tools
FROM Category c
LEFT JOIN Tool t ON c.category_id = t.category_id
GROUP BY c.category_id, c.name
ORDER BY total_tools DESC;

-- AGG-2: Average rating of all reviewed members
SELECT m.name,
       ROUND(AVG(r.rating), 2) AS avg_rating,
       COUNT(r.review_id) AS total_reviews
FROM Member m
JOIN Review r ON r.reviewee_id = m.member_id
GROUP BY m.member_id, m.name
ORDER BY avg_rating DESC;

-- AGG-3: Total loans per borrower
SELECT m.name, COUNT(lr.loan_id) AS total_loans
FROM Member m
JOIN Loan_Record lr ON lr.borrower_id = m.member_id
GROUP BY m.member_id, m.name
ORDER BY total_loans DESC;

-- AGG-4: Max, min, and average duration of approved requests
SELECT MAX(duration_days) AS max_duration,
       MIN(duration_days) AS min_duration,
       ROUND(AVG(duration_days), 1) AS avg_duration
FROM Borrow_Request
WHERE status = 'Approved';

-- AGG-5: Damage reports grouped by severity
SELECT severity, COUNT(*) AS total_reports
FROM Damage_Report
GROUP BY severity
ORDER BY total_reports DESC;

-- AGG-6: Borrow count per tool
SELECT t.name AS tool_name,
       COUNT(lr.loan_id) AS times_borrowed
FROM Tool t
LEFT JOIN Loan_Record lr ON lr.tool_id = t.tool_id
GROUP BY t.tool_id, t.name
ORDER BY times_borrowed DESC;

-- AGG-7: Member count per area
SELECT a.area_name, a.district,
       COUNT(m.member_id) AS member_count
FROM Area a
LEFT JOIN Member m ON m.area_id = a.area_id
GROUP BY a.area_id
ORDER BY member_count DESC;

-- AGG-8: Loan count by status (now includes 'Overdue' since T8 auto-sets it)
SELECT status, COUNT(*) AS total
FROM Loan_Record
GROUP BY status;

-- ============================================================
-- JOIN QUERIES (JOIN-1 to JOIN-8)
-- ============================================================

-- JOIN-1: All tools with owner and category
SELECT t.name AS tool, t.status, t.`condition`,
       m.name AS owner, c.name AS category
FROM Tool t
JOIN Member   m ON t.owner_id    = m.member_id
JOIN Category c ON t.category_id = c.category_id
ORDER BY c.name, t.name;

-- JOIN-2: Active and overdue loans with borrower, tool, owner (updated for T8)
SELECT lr.loan_id,
       b.name AS borrower, t.name AS tool,
       o.name AS owner,
       lr.loan_date, lr.expected_return,
       lr.status,
       DATEDIFF(CURDATE(), lr.expected_return) AS days_overdue
FROM Loan_Record lr
JOIN Member b ON lr.borrower_id = b.member_id
JOIN Tool   t ON lr.tool_id     = t.tool_id
JOIN Member o ON t.owner_id     = o.member_id
WHERE lr.status IN ('Active', 'Overdue');

-- JOIN-3: Returned loans with return log details
SELECT lr.loan_id, m.name AS borrower,
       t.name AS tool,
       rl.actual_return_date,
       rl.condition_on_return, rl.notes
FROM Loan_Record lr
JOIN Return_Log rl ON rl.loan_id     = lr.loan_id
JOIN Member     m  ON lr.borrower_id = m.member_id
JOIN Tool       t  ON lr.tool_id     = t.tool_id;

-- JOIN-4: Damage reports with reporter and tool
SELECT dr.damage_id, rep.name AS reported_by,
       t.name AS tool,
       dr.severity, dr.description, dr.reported_at
FROM Damage_Report dr
JOIN Member      rep ON dr.reported_by = rep.member_id
JOIN Loan_Record lr  ON dr.loan_id     = lr.loan_id
JOIN Tool        t   ON lr.tool_id     = t.tool_id;

-- JOIN-5: Reviews with reviewer and reviewee names
SELECT rv.review_id,
       r.name AS reviewer, e.name AS reviewee,
       t.name AS tool, rv.rating, rv.comment
FROM Review rv
JOIN Member      r  ON rv.reviewer_id = r.member_id
JOIN Member      e  ON rv.reviewee_id = e.member_id
JOIN Loan_Record lr ON rv.loan_id     = lr.loan_id
JOIN Tool        t  ON lr.tool_id     = t.tool_id;

-- JOIN-6: Borrow requests with requester and owner
SELECT br.req_id, req.name AS requester,
       t.name AS tool, own.name AS owner,
       br.duration_days, br.status
FROM Borrow_Request br
JOIN Member req ON br.requester_id = req.member_id
JOIN Tool   t   ON br.tool_id      = t.tool_id
JOIN Member own ON t.owner_id      = own.member_id
ORDER BY br.created_at DESC;

-- JOIN-7: Tool condition audit log
SELECT tcl.cond_id, t.name AS tool,
       m.name AS owner,
       tcl.previous_condition, tcl.new_condition,
       tcl.changed_at, tcl.reason
FROM Tool_Condition_Log tcl
JOIN Tool   t ON tcl.tool_id = t.tool_id
JOIN Member m ON t.owner_id  = m.member_id
ORDER BY tcl.changed_at DESC;

-- JOIN-8: Members with area and statistics
SELECT m.member_id, m.name, m.email, m.role,
       a.area_name, a.district,
       m.avg_rating, m.total_loans
FROM Member m
LEFT JOIN Area a ON m.area_id = a.area_id
ORDER BY m.name;

-- ============================================================
-- SELECT AND GROUP BY QUERIES (SEL-1 to SEL-8)
-- ============================================================

-- SEL-1: All available tools (browse page query)
SELECT t.tool_id, t.name, t.`condition`,
       c.name AS category, m.name AS owner
FROM Tool t
JOIN Category c ON t.category_id = c.category_id
JOIN Member   m ON t.owner_id    = m.member_id
WHERE t.status = 'Available'
ORDER BY c.name;

-- SEL-2: All banned members
SELECT member_id, name, email, role, created_at
FROM Member
WHERE is_banned = 1;

-- SEL-3: All currently overdue loans (updated — T8 auto-sets status to Overdue)
SELECT lr.loan_id, m.name AS borrower,
       t.name AS tool, lr.expected_return,
       DATEDIFF(CURDATE(), lr.expected_return) AS days_overdue
FROM Loan_Record lr
JOIN Member m ON lr.borrower_id = m.member_id
JOIN Tool   t ON lr.tool_id     = t.tool_id
WHERE lr.status = 'Overdue'
ORDER BY days_overdue DESC;

-- SEL-4: Tools needing admin inspection
SELECT t.tool_id, t.name, t.`condition`,
       m.name AS owner, a.area_name
FROM Tool t
JOIN Member m ON t.owner_id = m.member_id
JOIN Area   a ON m.area_id  = a.area_id
WHERE t.status = 'Needs Inspection';

-- SEL-5: Pending request count per tool
SELECT t.name AS tool,
       COUNT(br.req_id) AS pending_requests
FROM Borrow_Request br
JOIN Tool t ON br.tool_id = t.tool_id
WHERE br.status = 'Pending'
GROUP BY t.tool_id, t.name
ORDER BY pending_requests DESC;

-- SEL-6: Members who have never borrowed
SELECT m.member_id, m.name, m.email, m.created_at
FROM Member m
LEFT JOIN Loan_Record lr ON lr.borrower_id = m.member_id
WHERE lr.loan_id IS NULL
  AND m.role = 'Member';

-- SEL-7: Monthly loan volume
SELECT DATE_FORMAT(loan_date, '%Y-%m') AS month,
       COUNT(*) AS loans_created
FROM Loan_Record
GROUP BY month
ORDER BY month;

-- SEL-8: Tools grouped by physical condition
SELECT `condition`,
       COUNT(*) AS total_tools,
       ROUND(COUNT(*) * 100.0 / (SELECT COUNT(*) FROM Tool), 1) AS percentage
FROM Tool
GROUP BY `condition`;

-- ============================================================
-- NESTED AND SUBQUERY QUERIES (SUB-1 to SUB-10)
-- ============================================================

-- SUB-1: Members with above-average rating
SELECT name, avg_rating FROM Member
WHERE avg_rating > (
    SELECT AVG(avg_rating) FROM Member
    WHERE avg_rating > 0
)
ORDER BY avg_rating DESC;

-- SUB-2: Tools that have never been borrowed
SELECT tool_id, name, status FROM Tool
WHERE tool_id NOT IN (
    SELECT DISTINCT tool_id FROM Loan_Record
);

-- SUB-3: Borrower with the most loans
SELECT name, total_loans FROM Member
WHERE total_loans = (SELECT MAX(total_loans) FROM Member);

-- SUB-4: Members who filed damage reports
SELECT DISTINCT m.name, m.email FROM Member m
WHERE m.member_id IN (SELECT reported_by FROM Damage_Report);

-- SUB-5: Loans with at least one damage report
SELECT lr.loan_id, m.name AS borrower,
       t.name AS tool, lr.status
FROM Loan_Record lr
JOIN Member m ON lr.borrower_id = m.member_id
JOIN Tool   t ON lr.tool_id     = t.tool_id
WHERE lr.loan_id IN (
    SELECT DISTINCT loan_id FROM Damage_Report
);

-- SUB-6: Tools owned by highly rated members
SELECT t.name AS tool, t.status,
       m.name AS owner, m.avg_rating
FROM Tool t JOIN Member m ON t.owner_id = m.member_id
WHERE t.owner_id IN (
    SELECT member_id FROM Member WHERE avg_rating >= 4.0
);

-- SUB-7: Repeat borrowers (borrowed more than once)
SELECT m.name, COUNT(lr.loan_id) AS times_borrowed
FROM Member m
JOIN Loan_Record lr ON lr.borrower_id = m.member_id
GROUP BY m.member_id, m.name
HAVING COUNT(lr.loan_id) > 1;

-- SUB-8: Categories with at least one active loan (nested IN)
SELECT DISTINCT c.name AS category FROM Category c
WHERE c.category_id IN (
    SELECT t.category_id FROM Tool t
    WHERE t.tool_id IN (
        SELECT tool_id FROM Loan_Record
        WHERE status IN ('Active', 'Overdue')
    )
);

-- SUB-9: Most recent return entry per loan
SELECT lr.loan_id, t.name AS tool,
       m.name AS borrower,
       rl.actual_return_date, rl.condition_on_return
FROM Return_Log rl
JOIN Loan_Record lr ON rl.loan_id     = lr.loan_id
JOIN Tool        t  ON lr.tool_id     = t.tool_id
JOIN Member      m  ON lr.borrower_id = m.member_id
WHERE rl.return_id = (
    SELECT MAX(return_id) FROM Return_Log
    WHERE loan_id = rl.loan_id
);

-- SUB-10: Members who both give and receive reviews
SELECT DISTINCT m.name FROM Member m
WHERE m.member_id IN (SELECT reviewer_id FROM Review)
  AND m.member_id IN (SELECT reviewee_id FROM Review);

-- ============================================================
-- TRIGGER TEST QUERIES (T1, T6, T2, T3, T4, T5, T7, T8, T9)
-- ============================================================

-- T1 Test: after_loan_created
SELECT tool_id, name, status FROM Tool WHERE tool_id = 1;
SELECT member_id, name, total_loans FROM Member WHERE member_id = 3;
INSERT INTO Loan_Record
    (tool_id, borrower_id, loan_date, expected_return, status)
VALUES (1, 3, CURDATE(), DATE_ADD(CURDATE(), INTERVAL 7 DAY), 'Active');
SELECT tool_id, name, status FROM Tool WHERE tool_id = 1;
SELECT member_id, name, total_loans FROM Member WHERE member_id = 3;

-- T6 Test: after_loan_record_insert
SELECT req_id, status, decision_at
FROM Borrow_Request
WHERE tool_id = 13 AND requester_id = 16 AND status = 'Pending';
INSERT INTO Loan_Record
    (tool_id, borrower_id, req_id, loan_date, expected_return, status)
VALUES (13, 16, 12, CURDATE(), DATE_ADD(CURDATE(), INTERVAL 5 DAY), 'Active');
SELECT req_id, status, decision_at FROM Borrow_Request WHERE req_id = 12;

-- T2 Test: after_return_log
SELECT tool_id, status FROM Tool WHERE tool_id = 1;
INSERT INTO Return_Log
    (loan_id, actual_return_date, condition_on_return)
VALUES (1, CURDATE(), 'Good');
SELECT tool_id, name, status FROM Tool WHERE tool_id = 1;
SELECT loan_id, status FROM Loan_Record WHERE loan_id = 1;

-- T3 Test: after_damage_report
SELECT tool_id, name, status FROM Tool WHERE tool_id = 1;
INSERT INTO Damage_Report
    (loan_id, reported_by, description, severity)
VALUES (1, 3, 'Handle cracked during use.', 'Minor');
SELECT tool_id, name, status FROM Tool WHERE tool_id = 1;

-- T4 Test: after_tool_condition_update
SELECT tool_id, name, `condition` FROM Tool WHERE tool_id = 2;
SELECT COUNT(*) AS log_count FROM Tool_Condition_Log WHERE tool_id = 2;
UPDATE Tool SET `condition` = 'Fair' WHERE tool_id = 2;
SELECT * FROM Tool_Condition_Log WHERE tool_id = 2 ORDER BY changed_at DESC LIMIT 1;
SELECT COUNT(*) AS log_count FROM Tool_Condition_Log WHERE tool_id = 2;
UPDATE Tool SET description = 'Test description' WHERE tool_id = 2;
SELECT COUNT(*) AS log_count FROM Tool_Condition_Log WHERE tool_id = 2;

-- T5 Test: after_review_insert
SELECT member_id, name, avg_rating FROM Member WHERE member_id = 4;
INSERT INTO Review
    (loan_id, reviewer_id, reviewee_id, rating, comment)
VALUES (2, 14, 4, 2, 'Was not responsive during handover.');
SELECT member_id, name, avg_rating FROM Member WHERE member_id = 4;
SELECT ROUND(AVG(rating), 2) AS live_avg FROM Review WHERE reviewee_id = 4;

-- T7 Test: before_loan_record_insert_set_expected_return
SELECT req_id, duration_days FROM Borrow_Request WHERE req_id = 5;
INSERT INTO Loan_Record
    (tool_id, borrower_id, req_id, loan_date, expected_return)
VALUES (5, 11, 5, CURDATE(), CURDATE());
SELECT loan_id, loan_date, expected_return
FROM Loan_Record ORDER BY loan_id DESC LIMIT 1;

-- T8 Test: before_loan_record_update_overdue
SELECT loan_id, expected_return, status FROM Loan_Record WHERE loan_id = 14;
UPDATE Loan_Record SET expected_return = expected_return WHERE loan_id = 14;
SELECT loan_id, expected_return, status FROM Loan_Record WHERE loan_id = 14;

-- T9 Test: before_review_insert_validate_rating
INSERT INTO Review
    (loan_id, reviewer_id, reviewee_id, rating, comment)
VALUES (5, 11, 6, 6, 'Testing invalid rating.');
INSERT INTO Review
    (loan_id, reviewer_id, reviewee_id, rating, comment)
VALUES (5, 11, 6, 0, 'Testing zero rating.');

-- ============================================================
-- UPDATE QUERIES (UPD-1 to UPD-8)
-- ============================================================

-- UPD-1: Admin clears tool after inspection
UPDATE Tool
SET status = 'Available', `condition` = 'Good'
WHERE tool_id = 1 AND status = 'Needs Inspection';

-- UPD-2: Admin bans a member
UPDATE Member SET is_banned = 1
WHERE member_id = 3 AND role = 'Member';

-- UPD-3: Admin marks tool as Lost
UPDATE Tool SET status = 'Lost' WHERE tool_id = 2;

-- UPD-4: Update loan to Lost status
UPDATE Loan_Record SET status = 'Lost' WHERE loan_id = 5;

-- UPD-5: Member updates their profile bio
UPDATE Member
SET profile_bio = 'Active community lender in Dhanmondi.'
WHERE member_id = 2;

-- UPD-6: Owner edits tool condition and description
UPDATE Tool
SET `condition` = 'Fair',
    description = 'Minor scratches. Fully functional.'
WHERE tool_id = 3 AND owner_id = 2;

-- UPD-7: Auto-reject other pending requests on approval
UPDATE Borrow_Request SET status = 'Rejected'
WHERE tool_id = 1
  AND status  = 'Pending'
  AND req_id != 4;

-- UPD-8: Manually recalculate member avg_rating
UPDATE Member
SET avg_rating = (
    SELECT ROUND(AVG(rating), 2)
    FROM Review WHERE reviewee_id = 2
)
WHERE member_id = 2;

-- ============================================================
-- DELETE QUERIES (DEL-1 to DEL-3)
-- ============================================================

-- DEL-1: Safe tool deletion (no loan history)
DELETE FROM Tool_Condition_Log WHERE tool_id = 8;
DELETE FROM Borrow_Request      WHERE tool_id = 8;
DELETE FROM Tool                WHERE tool_id = 8;

-- DEL-2: Member cancels a pending request
DELETE FROM Borrow_Request
WHERE req_id = 7 AND requester_id = 3
  AND status = 'Pending';

-- DEL-3: Admin removes a review
DELETE FROM Review WHERE review_id = 5;

-- ============================================================
-- GROUP BY QUERIES (GRPBY-1 to GRPBY-3)
-- ============================================================

-- GRPBY-1: Tool inventory grouped by status (now includes Overdue)
SELECT status, COUNT(*) AS total
FROM Tool
GROUP BY status;

-- GRPBY-2: Borrow requests by month and status
SELECT DATE_FORMAT(created_at, '%Y-%m') AS month,
       status, COUNT(*) AS total
FROM Borrow_Request
GROUP BY month, status
ORDER BY month;

-- GRPBY-3: Damage reports by category and severity
SELECT c.name AS category,
       dr.severity, COUNT(*) AS total
FROM Damage_Report dr
JOIN Loan_Record lr ON dr.loan_id    = lr.loan_id
JOIN Tool        t  ON lr.tool_id    = t.tool_id
JOIN Category    c  ON t.category_id = c.category_id
GROUP BY c.category_id, dr.severity
ORDER BY c.name;
