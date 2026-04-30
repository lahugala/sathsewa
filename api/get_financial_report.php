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

$year = isset($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');
if ($year < 2000 || $year > 2100) {
    echo json_encode(['success' => false, 'message' => 'Valid report year is required']);
    exit();
}

try {
    $stmtYears = $pdo->query("
        SELECT DISTINCT YEAR(issued_date) AS report_year
        FROM member_loans
        UNION
        SELECT DISTINCT YEAR(paid_date) AS report_year
        FROM loan_installments
        WHERE paid_date IS NOT NULL
        ORDER BY report_year DESC
    ");
    $availableYears = array_values(array_filter(array_map('intval', array_column($stmtYears->fetchAll(PDO::FETCH_ASSOC), 'report_year'))));
    if (!in_array($year, $availableYears, true)) {
        $availableYears[] = $year;
        rsort($availableYears);
    }

    $stmtIssued = $pdo->prepare("
        SELECT
            COUNT(*) AS loans_issued,
            COALESCE(SUM(principal_amount), 0) AS principal_issued,
            COALESCE(SUM(total_interest), 0) AS interest_expected,
            COALESCE(SUM(total_payable), 0) AS total_payable
        FROM member_loans
        WHERE status <> 'Cancelled' AND YEAR(issued_date) = ?
    ");
    $stmtIssued->execute([$year]);
    $issuedRaw = $stmtIssued->fetch(PDO::FETCH_ASSOC);

    $stmtRecoveries = $pdo->prepare("
        SELECT
            COUNT(*) AS recoveries_count,
            COALESCE(SUM(amount_paid), 0) AS recovered_total
        FROM loan_installments
        WHERE paid_date IS NOT NULL AND YEAR(paid_date) = ?
    ");
    $stmtRecoveries->execute([$year]);
    $recoveryRaw = $stmtRecoveries->fetch(PDO::FETCH_ASSOC);

    $stmtPortfolio = $pdo->query("
        SELECT
            COUNT(*) AS loans_count,
            SUM(CASE WHEN l.status <> 'Cancelled'
                AND GREATEST(COALESCE(a.total_due, l.total_payable) - COALESCE(a.total_paid, 0), 0) > 0
                THEN 1 ELSE 0 END) AS active_loans,
            SUM(CASE WHEN l.status <> 'Cancelled'
                AND GREATEST(COALESCE(a.total_due, l.total_payable) - COALESCE(a.total_paid, 0), 0) <= 0
                THEN 1 ELSE 0 END) AS settled_loans,
            SUM(CASE WHEN l.status <> 'Cancelled'
                AND COALESCE(a.overdue_count, 0) > 0
                AND GREATEST(COALESCE(a.total_due, l.total_payable) - COALESCE(a.total_paid, 0), 0) > 0
                THEN 1 ELSE 0 END) AS overdue_loans,
            COALESCE(SUM(CASE WHEN l.status <> 'Cancelled'
                THEN GREATEST(COALESCE(a.total_due, l.total_payable) - COALESCE(a.total_paid, 0), 0)
                ELSE 0 END), 0) AS outstanding_balance,
            COALESCE(SUM(CASE WHEN l.status <> 'Cancelled'
                THEN COALESCE(a.total_paid, 0)
                ELSE 0 END), 0) AS lifetime_recovered
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
    ");
    $portfolioRaw = $stmtPortfolio->fetch(PDO::FETCH_ASSOC);

    $stmtOverdue = $pdo->query("
        SELECT
            COALESCE(SUM(GREATEST(i.amount_due - i.amount_paid, 0)), 0) AS overdue_balance,
            COUNT(*) AS overdue_installments
        FROM loan_installments i
        JOIN member_loans l ON i.loan_id = l.id
        WHERE l.status <> 'Cancelled'
          AND i.status <> 'Paid'
          AND i.due_date < CURDATE()
    ");
    $overdueRaw = $stmtOverdue->fetch(PDO::FETCH_ASSOC);

    $stmtMonthlyRecovery = $pdo->prepare("
        SELECT MONTH(paid_date) AS recovery_month,
               COUNT(*) AS recoveries_count,
               COALESCE(SUM(amount_paid), 0) AS recovered_total
        FROM loan_installments
        WHERE paid_date IS NOT NULL AND YEAR(paid_date) = ?
        GROUP BY MONTH(paid_date)
        ORDER BY recovery_month ASC
    ");
    $stmtMonthlyRecovery->execute([$year]);
    $monthlyRecovery = $stmtMonthlyRecovery->fetchAll(PDO::FETCH_ASSOC);

    $stmtLoans = $pdo->prepare("
        SELECT l.id, l.issued_date, l.principal_amount, l.interest_rate, l.term_months,
               l.total_interest, l.total_payable, l.installment_amount, l.status,
               m.id AS member_id, m.name, m.membership_number,
               COALESCE(a.total_paid, 0) AS paid_total,
               GREATEST(COALESCE(a.total_due, l.total_payable) - COALESCE(a.total_paid, 0), 0) AS balance,
               COALESCE(a.paid_installments, 0) AS paid_installments,
               COALESCE(a.installments_count, 0) AS installments_count,
               COALESCE(a.overdue_count, 0) AS overdue_installments
        FROM member_loans l
        JOIN members m ON l.member_id = m.id
        LEFT JOIN (
            SELECT
                loan_id,
                COALESCE(SUM(amount_due), 0) AS total_due,
                COALESCE(SUM(amount_paid), 0) AS total_paid,
                SUM(CASE WHEN status = 'Paid' THEN 1 ELSE 0 END) AS paid_installments,
                COUNT(id) AS installments_count,
                SUM(CASE WHEN status <> 'Paid' AND due_date < CURDATE() THEN 1 ELSE 0 END) AS overdue_count
            FROM loan_installments
            GROUP BY loan_id
        ) a ON a.loan_id = l.id
        WHERE l.status <> 'Cancelled'
          AND m.is_deleted = 0
          AND YEAR(l.issued_date) = ?
        ORDER BY l.issued_date DESC, l.id DESC
    ");
    $stmtLoans->execute([$year]);
    $loanRows = $stmtLoans->fetchAll(PDO::FETCH_ASSOC);

    $stmtMemberSummary = $pdo->query("
        SELECT m.id AS member_id, m.name, m.membership_number,
               COUNT(l.id) AS loans_count,
               COALESCE(SUM(l.principal_amount), 0) AS principal_total,
               COALESCE(SUM(l.total_interest), 0) AS interest_total,
               COALESCE(SUM(a.total_paid), 0) AS recovered_total,
               COALESCE(SUM(GREATEST(COALESCE(a.total_due, l.total_payable) - COALESCE(a.total_paid, 0), 0)), 0) AS balance,
               COALESCE(SUM(a.overdue_count), 0) AS overdue_installments
        FROM members m
        JOIN member_loans l ON l.member_id = m.id
        LEFT JOIN (
            SELECT
                loan_id,
                COALESCE(SUM(amount_due), 0) AS total_due,
                COALESCE(SUM(amount_paid), 0) AS total_paid,
                SUM(CASE WHEN status <> 'Paid' AND due_date < CURDATE() THEN 1 ELSE 0 END) AS overdue_count
            FROM loan_installments
            GROUP BY loan_id
        ) a ON a.loan_id = l.id
        WHERE m.is_deleted = 0 AND l.status <> 'Cancelled'
        GROUP BY m.id, m.name, m.membership_number
        ORDER BY balance DESC, m.name ASC
        LIMIT 50
    ");
    $memberSummary = $stmtMemberSummary->fetchAll(PDO::FETCH_ASSOC);

    $stmtOverdueRows = $pdo->query("
        SELECT i.id, i.installment_no, i.due_date, i.amount_due, i.amount_paid,
               GREATEST(i.amount_due - i.amount_paid, 0) AS balance,
               l.id AS loan_id, l.issued_date, m.id AS member_id, m.name, m.membership_number
        FROM loan_installments i
        JOIN member_loans l ON i.loan_id = l.id
        JOIN members m ON l.member_id = m.id
        WHERE l.status <> 'Cancelled'
          AND m.is_deleted = 0
          AND i.status <> 'Paid'
          AND i.due_date < CURDATE()
        ORDER BY i.due_date ASC, m.name ASC
        LIMIT 50
    ");
    $overdueRows = $stmtOverdueRows->fetchAll(PDO::FETCH_ASSOC);

    $toFloat = function($value) {
        return round((float)($value ?? 0), 2);
    };

    echo json_encode([
        'success' => true,
        'year' => $year,
        'availableYears' => $availableYears,
        'summary' => [
            'loansIssued' => (int)($issuedRaw['loans_issued'] ?? 0),
            'principalIssued' => $toFloat($issuedRaw['principal_issued'] ?? 0),
            'interestExpected' => $toFloat($issuedRaw['interest_expected'] ?? 0),
            'totalPayableIssued' => $toFloat($issuedRaw['total_payable'] ?? 0),
            'recoveriesCount' => (int)($recoveryRaw['recoveries_count'] ?? 0),
            'recoveredThisYear' => $toFloat($recoveryRaw['recovered_total'] ?? 0),
            'portfolioLoans' => (int)($portfolioRaw['loans_count'] ?? 0),
            'activeLoans' => (int)($portfolioRaw['active_loans'] ?? 0),
            'settledLoans' => (int)($portfolioRaw['settled_loans'] ?? 0),
            'overdueLoans' => (int)($portfolioRaw['overdue_loans'] ?? 0),
            'outstandingBalance' => $toFloat($portfolioRaw['outstanding_balance'] ?? 0),
            'lifetimeRecovered' => $toFloat($portfolioRaw['lifetime_recovered'] ?? 0),
            'overdueBalance' => $toFloat($overdueRaw['overdue_balance'] ?? 0),
            'overdueInstallments' => (int)($overdueRaw['overdue_installments'] ?? 0)
        ],
        'monthlyRecovery' => array_map(function($row) use ($toFloat) {
            return [
                'month' => (int)$row['recovery_month'],
                'recoveries_count' => (int)$row['recoveries_count'],
                'recovered_total' => $toFloat($row['recovered_total'])
            ];
        }, $monthlyRecovery),
        'loans' => array_map(function($row) use ($toFloat) {
            return [
                'id' => (int)$row['id'],
                'member_id' => (int)$row['member_id'],
                'name' => $row['name'],
                'membership_number' => $row['membership_number'],
                'issued_date' => $row['issued_date'],
                'principal_amount' => $toFloat($row['principal_amount']),
                'interest_rate' => $toFloat($row['interest_rate']),
                'term_months' => (int)$row['term_months'],
                'total_interest' => $toFloat($row['total_interest']),
                'total_payable' => $toFloat($row['total_payable']),
                'installment_amount' => $toFloat($row['installment_amount']),
                'paid_total' => $toFloat($row['paid_total']),
                'balance' => $toFloat($row['balance']),
                'paid_installments' => (int)$row['paid_installments'],
                'installments_count' => (int)$row['installments_count'],
                'overdue_installments' => (int)$row['overdue_installments'],
                'status' => ((int)$row['overdue_installments'] > 0 && (float)$row['balance'] > 0) ? 'Overdue' : (((float)$row['balance'] > 0) ? 'Active' : 'Settled')
            ];
        }, $loanRows),
        'memberSummary' => array_map(function($row) use ($toFloat) {
            return [
                'member_id' => (int)$row['member_id'],
                'name' => $row['name'],
                'membership_number' => $row['membership_number'],
                'loans_count' => (int)$row['loans_count'],
                'principal_total' => $toFloat($row['principal_total']),
                'interest_total' => $toFloat($row['interest_total']),
                'recovered_total' => $toFloat($row['recovered_total']),
                'balance' => $toFloat($row['balance']),
                'overdue_installments' => (int)$row['overdue_installments']
            ];
        }, $memberSummary),
        'overdueInstallments' => array_map(function($row) use ($toFloat) {
            return [
                'id' => (int)$row['id'],
                'loan_id' => (int)$row['loan_id'],
                'member_id' => (int)$row['member_id'],
                'name' => $row['name'],
                'membership_number' => $row['membership_number'],
                'installment_no' => (int)$row['installment_no'],
                'due_date' => $row['due_date'],
                'issued_date' => $row['issued_date'],
                'amount_due' => $toFloat($row['amount_due']),
                'amount_paid' => $toFloat($row['amount_paid']),
                'balance' => $toFloat($row['balance'])
            ];
        }, $overdueRows)
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>
