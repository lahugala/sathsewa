<?php
function refresh_loan_status($pdo, $loanId) {
    $stmt = $pdo->prepare("
        SELECT
            COALESCE(SUM(amount_due), 0) AS total_due,
            COALESCE(SUM(amount_paid), 0) AS total_paid,
            SUM(CASE WHEN status <> 'Paid' AND due_date < CURDATE() THEN 1 ELSE 0 END) AS overdue_count
        FROM loan_installments
        WHERE loan_id = ?
    ");
    $stmt->execute([$loanId]);
    $summary = $stmt->fetch(PDO::FETCH_ASSOC);

    $totalDue = round((float)($summary['total_due'] ?? 0), 2);
    $totalPaid = round((float)($summary['total_paid'] ?? 0), 2);
    $overdueCount = (int)($summary['overdue_count'] ?? 0);

    if ($totalDue > 0 && $totalPaid >= $totalDue) {
        $status = 'Settled';
    } elseif ($overdueCount > 0) {
        $status = 'Overdue';
    } else {
        $status = 'Active';
    }

    $update = $pdo->prepare("UPDATE member_loans SET status = ? WHERE id = ? AND status <> 'Cancelled'");
    $update->execute([$status, $loanId]);

    return $status;
}

function money_round($value) {
    return round((float)$value, 2);
}

function fetch_member_loans($pdo, $memberId) {
    $stmtLoans = $pdo->prepare("
        SELECT l.*,
            COALESCE(a.scheduled_total, 0) AS scheduled_total,
            COALESCE(a.paid_total, 0) AS paid_total,
            COALESCE(a.paid_installments, 0) AS paid_installments,
            COALESCE(a.installments_count, 0) AS installments_count
        FROM member_loans l
        LEFT JOIN (
            SELECT loan_id,
                COALESCE(SUM(amount_due), 0) AS scheduled_total,
                COALESCE(SUM(amount_paid), 0) AS paid_total,
                SUM(CASE WHEN status = 'Paid' THEN 1 ELSE 0 END) AS paid_installments,
                COUNT(id) AS installments_count
            FROM loan_installments
            GROUP BY loan_id
        ) a ON a.loan_id = l.id
        WHERE l.member_id = ?
        ORDER BY l.issued_date DESC, l.created_at DESC
    ");
    $stmtLoans->execute([(int)$memberId]);
    $loanRows = $stmtLoans->fetchAll(PDO::FETCH_ASSOC);

    $loans = [];
    $stmtInstallments = $pdo->prepare("SELECT * FROM loan_installments WHERE loan_id = ? ORDER BY installment_no ASC");

    foreach ($loanRows as $loan) {
        $loanStatus = refresh_loan_status($pdo, (int)$loan['id']);

        $stmtInstallments->execute([(int)$loan['id']]);
        $installments = $stmtInstallments->fetchAll(PDO::FETCH_ASSOC);

        $paidTotal = (float)$loan['paid_total'];
        $totalPayable = (float)$loan['total_payable'];
        $loans[] = [
            'id' => (int)$loan['id'],
            'member_id' => (int)$loan['member_id'],
            'issued_date' => $loan['issued_date'],
            'principal_amount' => (float)$loan['principal_amount'],
            'interest_rate' => (float)$loan['interest_rate'],
            'term_months' => (int)$loan['term_months'],
            'total_interest' => (float)$loan['total_interest'],
            'total_payable' => $totalPayable,
            'installment_amount' => (float)$loan['installment_amount'],
            'status' => $loanStatus,
            'remarks' => $loan['remarks'],
            'paid_total' => $paidTotal,
            'balance' => max(0, round($totalPayable - $paidTotal, 2)),
            'paid_installments' => (int)$loan['paid_installments'],
            'installments_count' => (int)$loan['installments_count'],
            'installments' => array_map(function($item) {
                return [
                    'id' => (int)$item['id'],
                    'loan_id' => (int)$item['loan_id'],
                    'installment_no' => (int)$item['installment_no'],
                    'due_date' => $item['due_date'],
                    'principal_component' => (float)$item['principal_component'],
                    'interest_component' => (float)$item['interest_component'],
                    'amount_due' => (float)$item['amount_due'],
                    'paid_date' => $item['paid_date'],
                    'amount_paid' => (float)$item['amount_paid'],
                    'status' => $item['status']
                ];
            }, $installments)
        ];
    }

    return $loans;
}
?>
