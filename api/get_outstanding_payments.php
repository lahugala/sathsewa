<?php
require 'db.php';
require 'schema.php';
require 'outstanding_helpers.php';
ensure_app_schema($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

try {
    $stmtMembers = $pdo->query("SELECT id, name, membership_number, membership_date, nic, city, contact_number, status, status_reason, created_at, DATE_FORMAT(created_at, '%Y-%m-%d') as date_added
        FROM members
        WHERE is_deleted = 0
        ORDER BY membership_number ASC, name ASC");
    $members = $stmtMembers->fetchAll(PDO::FETCH_ASSOC);

    $stmtPayments = $pdo->query("SELECT member_id, payment_year, payment_month, member_fee, share_capital, special_charges,
            (COALESCE(member_fee,0) + COALESCE(share_capital,0) + COALESCE(special_charges,0)) AS total_amount
        FROM payments");
    $paymentRows = $stmtPayments->fetchAll(PDO::FETCH_ASSOC);

    $paymentsMap = [];
    foreach ($paymentRows as $row) {
        $memberId = (int)$row['member_id'];
        if (!isset($paymentsMap[$memberId])) {
            $paymentsMap[$memberId] = [];
        }
        $paymentsMap[$memberId][] = [
            'payment_year' => $row['payment_year'],
            'payment_month' => $row['payment_month'],
            'member_fee' => $row['member_fee'],
            'share_capital' => $row['share_capital'],
            'special_charges' => $row['special_charges'],
            'total_amount' => $row['total_amount']
        ];
    }

    $outstandingMembers = [];
    foreach ($members as $member) {
        $outstanding = calculate_outstanding_payments($member, $paymentsMap[(int)$member['id']] ?? []);
        if ((int)$outstanding['outstanding_months'] > 0) {
            $outstandingMembers[] = [
                'id' => (int)$member['id'],
                'name' => $member['name'],
                'membership_number' => $member['membership_number'],
                'membership_date' => $member['membership_date'],
                'nic' => $member['nic'],
                'city' => $member['city'],
                'contact_number' => $member['contact_number'],
                'status' => $member['status'],
                'status_reason' => $member['status_reason'],
                'date_added' => $member['date_added'],
                'outstanding' => $outstanding
            ];
        }
    }

    echo json_encode([
        'success' => true,
        'members' => $outstandingMembers,
        'summary' => [
            'as_of_date' => (new DateTime())->format('Y-m-d'),
            'members_count' => count($outstandingMembers),
            'total_outstanding_months' => array_sum(array_map(function($member) {
                return (int)$member['outstanding']['outstanding_months'];
            }, $outstandingMembers)),
            'total_outstanding_amount' => array_sum(array_map(function($member) {
                return (float)$member['outstanding']['outstanding_amount'];
            }, $outstandingMembers))
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
