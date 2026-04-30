<?php
require 'db.php';
require 'auth.php';
require_auth();
require 'schema.php';
ensure_app_schema($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

try {
    $stmt = $pdo->query("SELECT id, name, membership_number, membership_date, nic, city, contact_number, address, status, status_reason, DATE_FORMAT(created_at, '%Y-%m-%d') as date_added FROM members WHERE is_deleted = 0 ORDER BY created_at DESC");
    $members = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $loanRows = $pdo->query("
        SELECT
            l.member_id,
            COUNT(*) AS loans_count,
            SUM(CASE
                WHEN l.status <> 'Cancelled'
                 AND GREATEST(COALESCE(a.total_due, l.total_payable) - COALESCE(a.total_paid, 0), 0) > 0
                THEN 1 ELSE 0 END) AS active_loans_count,
            SUM(CASE
                WHEN l.status <> 'Cancelled'
                 AND COALESCE(a.overdue_count, 0) > 0
                 AND GREATEST(COALESCE(a.total_due, l.total_payable) - COALESCE(a.total_paid, 0), 0) > 0
                THEN 1 ELSE 0 END) AS overdue_loans_count,
            COALESCE(SUM(CASE
                WHEN l.status <> 'Cancelled'
                THEN GREATEST(COALESCE(a.total_due, l.total_payable) - COALESCE(a.total_paid, 0), 0)
                ELSE 0 END), 0) AS total_balance,
            MAX(l.issued_date) AS last_loan_date
        FROM member_loans l
        LEFT JOIN (
            SELECT
                loan_id,
                COALESCE(SUM(amount_due), 0) AS total_due,
                COALESCE(SUM(amount_paid), 0) AS total_paid,
                SUM(CASE WHEN status <> 'Paid' AND due_date < CURDATE() THEN 1 ELSE 0 END) AS overdue_count
            FROM loan_installments
            GROUP BY loan_id
        ) a ON a.loan_id = l.id
        GROUP BY l.member_id
    ")->fetchAll(PDO::FETCH_ASSOC);

    $loanMap = [];
    foreach ($loanRows as $row) {
        $loanMap[(int)$row['member_id']] = [
            'loans_count' => (int)$row['loans_count'],
            'active_loans_count' => (int)$row['active_loans_count'],
            'overdue_loans_count' => (int)$row['overdue_loans_count'],
            'total_balance' => round((float)$row['total_balance'], 2),
            'last_loan_date' => $row['last_loan_date']
        ];
    }

    foreach ($members as &$member) {
        $memberId = (int)$member['id'];
        $member['loans'] = $loanMap[$memberId] ?? [
            'loans_count' => 0,
            'active_loans_count' => 0,
            'overdue_loans_count' => 0,
            'total_balance' => 0.00,
            'last_loan_date' => null
        ];
    }

    echo json_encode([
        'success' => true,
        'members' => $members
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
?>
