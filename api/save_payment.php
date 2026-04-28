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

$inputJSON = file_get_contents('php://input');
$input = json_decode($inputJSON, TRUE);

if (isset($input['member_id'], $input['payment_year'], $input['payment_month'], $input['paid_date'])) {
    try {
        $member_fee = $input['member_fee'] ?? 0;
        $share_capital = $input['share_capital'] ?? 0;
        $special_charges = $input['special_charges'] ?? 0;
        $remarks = $input['remarks'] ?? '';
        $memberId = (int)$input['member_id'];
        $paymentYear = (int)$input['payment_year'];
        $paymentMonth = (int)$input['payment_month'];

        $stmtMember = $pdo->prepare("SELECT membership_date FROM members WHERE id = ? AND is_deleted = 0");
        $stmtMember->execute([$memberId]);
        $member = $stmtMember->fetch(PDO::FETCH_ASSOC);

        if (!$member) {
            echo json_encode(['success' => false, 'message' => 'Member not found']);
            exit();
        }

        if (!empty($member['membership_date'])) {
            $membershipDate = new DateTime($member['membership_date']);
            $membershipMonth = clone $membershipDate;
            $membershipMonth->modify('first day of this month');
            $membershipMonth->setTime(0, 0, 0);

            $paymentPeriod = new DateTime(sprintf('%04d-%02d-01', $paymentYear, $paymentMonth));
            $paymentPeriod->setTime(0, 0, 0);

            $paidDate = new DateTime($input['paid_date']);
            $paidDate->setTime(0, 0, 0);

            if ($paymentPeriod < $membershipMonth) {
                echo json_encode(['success' => false, 'message' => 'Cannot save payment for a month before the member date']);
                exit();
            }

            if ($paidDate < $membershipDate) {
                echo json_encode(['success' => false, 'message' => 'Paid date cannot be before the member date']);
                exit();
            }
        }

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
            $memberId,
            $paymentYear,
            $paymentMonth,
            $input['paid_date'],
            $member_fee,
            $share_capital,
            $special_charges,
            $remarks
        ]);

        echo json_encode(['success' => true, 'message' => 'Payment saved successfully']);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Invalid payment data: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Required fields missing']);
}
?>
