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

if (!isset($input['installment_id'], $input['paid_date'], $input['amount_paid'])) {
    echo json_encode(['success' => false, 'message' => 'Required fields missing']);
    exit();
}

try {
    $installmentId = (int)$input['installment_id'];
    $amountPaid = money_round($input['amount_paid']);
    $paidDate = new DateTime($input['paid_date']);

    if ($amountPaid <= 0) {
        echo json_encode(['success' => false, 'message' => 'Recovered amount must be greater than zero']);
        exit();
    }

    $stmtInstallment = $pdo->prepare("
        SELECT i.id, i.loan_id, i.amount_due, l.status AS loan_status
        FROM loan_installments i
        JOIN member_loans l ON i.loan_id = l.id
        WHERE i.id = ?
    ");
    $stmtInstallment->execute([$installmentId]);
    $installment = $stmtInstallment->fetch(PDO::FETCH_ASSOC);

    if (!$installment) {
        echo json_encode(['success' => false, 'message' => 'Installment not found']);
        exit();
    }

    if ($installment['loan_status'] === 'Cancelled') {
        echo json_encode(['success' => false, 'message' => 'Cannot recover installment for a cancelled loan']);
        exit();
    }

    $amountDue = money_round($installment['amount_due']);
    $status = $amountPaid >= $amountDue ? 'Paid' : 'Partially Paid';

    $stmt = $pdo->prepare("
        UPDATE loan_installments
        SET paid_date = ?, amount_paid = ?, status = ?
        WHERE id = ?
    ");
    $stmt->execute([
        $paidDate->format('Y-m-d'),
        min($amountPaid, $amountDue),
        $status,
        $installmentId
    ]);

    refresh_loan_status($pdo, (int)$installment['loan_id']);

    echo json_encode(['success' => true, 'message' => 'Installment recovery saved successfully']);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Installment save failed: ' . $e->getMessage()]);
}
?>
