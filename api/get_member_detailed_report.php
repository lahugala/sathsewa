<?php
require 'db.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

$memberId = isset($_GET['member_id']) ? (int)$_GET['member_id'] : 0;

if ($memberId <= 0) {
    echo json_encode([
        'success' => false,
        'message' => 'Valid member ID is required'
    ]);
    exit();
}

try {
    $stmtMember = $pdo->prepare("SELECT id, name, membership_number, membership_date, nic, city, contact_number, address, DATE_FORMAT(created_at, '%Y-%m-%d') as date_added FROM members WHERE id = ? AND is_deleted = 0 LIMIT 1");
    $stmtMember->execute([$memberId]);
    $member = $stmtMember->fetch(PDO::FETCH_ASSOC);

    if (!$member) {
        echo json_encode([
            'success' => false,
            'message' => 'Member not found'
        ]);
        exit();
    }

    $stmtDependents = $pdo->prepare("SELECT name, relationship, birth_year FROM dependents WHERE member_id = ? ORDER BY name ASC");
    $stmtDependents->execute([$memberId]);
    $dependents = $stmtDependents->fetchAll(PDO::FETCH_ASSOC);

    $stmtPayments = $pdo->prepare("SELECT payment_year, payment_month, paid_date, member_fee, share_capital, special_charges, remarks,
            (COALESCE(member_fee,0) + COALESCE(share_capital,0) + COALESCE(special_charges,0)) AS total_amount
        FROM payments
        WHERE member_id = ?
        ORDER BY payment_year DESC, payment_month DESC, paid_date DESC");
    $stmtPayments->execute([$memberId]);
    $payments = $stmtPayments->fetchAll(PDO::FETCH_ASSOC);

    $stmtBenefits = $pdo->prepare("SELECT paid_date, benefit_type, dependent_name, relationship, aid_nature, amount
        FROM member_benefits
        WHERE member_id = ?
        ORDER BY paid_date DESC, created_at DESC");
    $stmtBenefits->execute([$memberId]);
    $benefits = $stmtBenefits->fetchAll(PDO::FETCH_ASSOC);

    $totalPaid = 0.0;
    foreach ($payments as $payment) {
        $totalPaid += (float)$payment['total_amount'];
    }

    $totalBenefits = 0.0;
    foreach ($benefits as $benefit) {
        $totalBenefits += (float)$benefit['amount'];
    }

    echo json_encode([
        'success' => true,
        'member' => $member,
        'dependents' => $dependents,
        'payments' => $payments,
        'benefits' => $benefits,
        'summary' => [
            'dependents_count' => count($dependents),
            'payments_count' => count($payments),
            'benefits_count' => count($benefits),
            'total_paid' => $totalPaid,
            'total_benefits' => $totalBenefits
        ]
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
?>