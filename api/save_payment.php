<?php
require 'db.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

$inputJSON = file_get_contents('php://input');
$input = json_decode($inputJSON, TRUE);

if (isset($input['member_id'], $input['payment_year'], $input['payment_month'], $input['paid_date'])) {
    try {
        $member_fee = $input['member_fee'] ?? 0;
        $share_capital = $input['share_capital'] ?? 0;
        $special_charges = $input['special_charges'] ?? 0;
        $remarks = $input['remarks'] ?? '';

        // Upsert logic
        $stmt = $pdo->prepare("INSERT INTO payments (member_id, payment_year, payment_month, paid_date, member_fee, share_capital, special_charges, remarks) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?) 
            ON DUPLICATE KEY UPDATE 
            paid_date = VALUES(paid_date),
            member_fee = VALUES(member_fee),
            share_capital = VALUES(share_capital),
            special_charges = VALUES(special_charges),
            remarks = VALUES(remarks)");
            
        $stmt->execute([
            $input['member_id'], 
            $input['payment_year'], 
            $input['payment_month'], 
            $input['paid_date'],
            $member_fee,
            $share_capital,
            $special_charges,
            $remarks
        ]);

        echo json_encode(['success' => true, 'message' => 'Payment saved successfully']);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Required fields missing']);
}
?>
