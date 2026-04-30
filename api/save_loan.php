<?php
require 'db.php';
require 'auth.php';
require_auth();
require 'schema.php';
require 'loan_helpers.php';
ensure_app_schema($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

$inputJSON = file_get_contents('php://input');
$input = json_decode($inputJSON, TRUE);

if (!isset($input['member_id'], $input['issued_date'], $input['first_due_date'], $input['principal_amount'], $input['interest_rate'], $input['term_months'])) {
    echo json_encode(['success' => false, 'message' => 'Required fields missing']);
    exit();
}

try {
    $memberId = (int)$input['member_id'];
    $principal = money_round($input['principal_amount']);
    $interestRate = money_round($input['interest_rate']);
    $termMonths = (int)$input['term_months'];
    $remarks = $input['remarks'] ?? '';

    if (!in_array($termMonths, [3, 6, 12], true)) {
        echo json_encode(['success' => false, 'message' => 'Loan term must be 3, 6, or 12 months']);
        exit();
    }

    if ($principal <= 0) {
        echo json_encode(['success' => false, 'message' => 'Loan amount must be greater than zero']);
        exit();
    }

    if ($interestRate < 0) {
        echo json_encode(['success' => false, 'message' => 'Interest rate cannot be negative']);
        exit();
    }

    $issuedDate = new DateTime($input['issued_date']);
    $firstDueDate = new DateTime($input['first_due_date']);

    if ($firstDueDate < $issuedDate) {
        echo json_encode(['success' => false, 'message' => 'First due date cannot be before issued date']);
        exit();
    }

    $stmtMember = $pdo->prepare("SELECT id FROM members WHERE id = ? AND is_deleted = 0");
    $stmtMember->execute([$memberId]);
    if (!$stmtMember->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Member not found']);
        exit();
    }

    $totalInterest = money_round($principal * ($interestRate / 100));
    $totalPayable = money_round($principal + $totalInterest);
    $installmentAmount = money_round($totalPayable / $termMonths);
    $principalInstallment = money_round($principal / $termMonths);
    $interestInstallment = money_round($totalInterest / $termMonths);

    $pdo->beginTransaction();

    $stmtLoan = $pdo->prepare("
        INSERT INTO member_loans
            (member_id, issued_date, principal_amount, interest_rate, term_months, total_interest, total_payable, installment_amount, status, remarks)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'Active', ?)
    ");
    $stmtLoan->execute([
        $memberId,
        $issuedDate->format('Y-m-d'),
        $principal,
        $interestRate,
        $termMonths,
        $totalInterest,
        $totalPayable,
        $installmentAmount,
        $remarks
    ]);

    $loanId = (int)$pdo->lastInsertId();
    $stmtInstallment = $pdo->prepare("
        INSERT INTO loan_installments
            (loan_id, installment_no, due_date, principal_component, interest_component, amount_due)
        VALUES (?, ?, ?, ?, ?, ?)
    ");

    $principalScheduled = 0.0;
    $interestScheduled = 0.0;
    $amountScheduled = 0.0;

    for ($i = 1; $i <= $termMonths; $i++) {
        $dueDate = clone $firstDueDate;
        if ($i > 1) {
            $dueDate->modify('+' . ($i - 1) . ' month');
        }

        if ($i === $termMonths) {
            $principalPart = money_round($principal - $principalScheduled);
            $interestPart = money_round($totalInterest - $interestScheduled);
            $amountDue = money_round($totalPayable - $amountScheduled);
        } else {
            $principalPart = $principalInstallment;
            $interestPart = $interestInstallment;
            $amountDue = $installmentAmount;
            $principalScheduled += $principalPart;
            $interestScheduled += $interestPart;
            $amountScheduled += $amountDue;
        }

        $stmtInstallment->execute([
            $loanId,
            $i,
            $dueDate->format('Y-m-d'),
            $principalPart,
            $interestPart,
            $amountDue
        ]);
    }

    $pdo->commit();

    echo json_encode(['success' => true, 'message' => 'Loan issued successfully', 'loan_id' => $loanId]);
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo json_encode(['success' => false, 'message' => 'Loan save failed: ' . $e->getMessage()]);
}
?>
