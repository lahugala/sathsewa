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
    $stmtMembers = $pdo->query("SELECT id, name, membership_number, membership_date, nic, city, contact_number, address, status, status_reason, created_at, DATE_FORMAT(created_at, '%Y-%m-%d') as date_added FROM members WHERE is_deleted = 0 ORDER BY created_at DESC");
    $members = $stmtMembers->fetchAll(PDO::FETCH_ASSOC);

    if (empty($members)) {
        echo json_encode([
            'success' => true,
            'members' => [],
            'meta' => [
                'cities' => []
            ]
        ]);
        exit();
    }

    $memberIds = array_column($members, 'id');
    $placeholders = implode(',', array_fill(0, count($memberIds), '?'));

    $stmtDependents = $pdo->prepare("SELECT member_id, name, relationship, birth_year FROM dependents WHERE member_id IN ($placeholders) ORDER BY member_id ASC, name ASC");
    $stmtDependents->execute($memberIds);
    $dependentsRows = $stmtDependents->fetchAll(PDO::FETCH_ASSOC);

    $dependentsMap = [];
    foreach ($dependentsRows as $row) {
        $memberId = (int)$row['member_id'];
        if (!isset($dependentsMap[$memberId])) {
            $dependentsMap[$memberId] = [];
        }
        $dependentsMap[$memberId][] = [
            'name' => $row['name'],
            'relationship' => $row['relationship'],
            'birth_year' => $row['birth_year']
        ];
    }

    $stmtPayments = $pdo->prepare("SELECT member_id,
            SUM(COALESCE(member_fee,0) + COALESCE(share_capital,0) + COALESCE(special_charges,0)) AS total_paid,
            SUM(COALESCE(member_fee,0)) AS total_member_fee,
            SUM(COALESCE(share_capital,0)) AS total_share_capital,
            SUM(COALESCE(special_charges,0)) AS total_special_charges,
            MAX(paid_date) AS last_payment_date,
            COUNT(*) AS payments_count
        FROM payments
        WHERE member_id IN ($placeholders)
        GROUP BY member_id");
    $stmtPayments->execute($memberIds);
    $paymentsRows = $stmtPayments->fetchAll(PDO::FETCH_ASSOC);

    $paymentsMap = [];
    foreach ($paymentsRows as $row) {
        $paymentsMap[(int)$row['member_id']] = [
            'total_paid' => (float)$row['total_paid'],
            'total_member_fee' => (float)$row['total_member_fee'],
            'total_share_capital' => (float)$row['total_share_capital'],
            'total_special_charges' => (float)$row['total_special_charges'],
            'last_payment_date' => $row['last_payment_date'],
            'payments_count' => (int)$row['payments_count']
        ];
    }

    $stmtPaymentMonths = $pdo->prepare("SELECT member_id, payment_year, payment_month, member_fee, share_capital, special_charges,
            (COALESCE(member_fee,0) + COALESCE(share_capital,0) + COALESCE(special_charges,0)) AS total_amount
        FROM payments
        WHERE member_id IN ($placeholders)");
    $stmtPaymentMonths->execute($memberIds);
    $paymentMonthRows = $stmtPaymentMonths->fetchAll(PDO::FETCH_ASSOC);

    $paymentMonthsMap = [];
    foreach ($paymentMonthRows as $row) {
        $memberId = (int)$row['member_id'];
        if (!isset($paymentMonthsMap[$memberId])) {
            $paymentMonthsMap[$memberId] = [];
        }
        $paymentMonthsMap[$memberId][] = [
            'payment_year' => $row['payment_year'],
            'payment_month' => $row['payment_month'],
            'member_fee' => $row['member_fee'],
            'share_capital' => $row['share_capital'],
            'special_charges' => $row['special_charges'],
            'total_amount' => $row['total_amount']
        ];
    }

    $stmtBenefits = $pdo->prepare("SELECT member_id,
            SUM(COALESCE(amount,0)) AS total_benefits,
            MAX(paid_date) AS last_benefit_date,
            COUNT(*) AS benefits_count
        FROM member_benefits
        WHERE member_id IN ($placeholders)
        GROUP BY member_id");
    $stmtBenefits->execute($memberIds);
    $benefitsRows = $stmtBenefits->fetchAll(PDO::FETCH_ASSOC);

    $benefitsMap = [];
    foreach ($benefitsRows as $row) {
        $benefitsMap[(int)$row['member_id']] = [
            'total_benefits' => (float)$row['total_benefits'],
            'last_benefit_date' => $row['last_benefit_date'],
            'benefits_count' => (int)$row['benefits_count']
        ];
    }

    $fullMembers = [];
    $cities = [];

    foreach ($members as $member) {
        $id = (int)$member['id'];
        $memberDependents = $dependentsMap[$id] ?? [];
        $paymentAgg = $paymentsMap[$id] ?? [
            'total_paid' => 0,
            'total_member_fee' => 0,
            'total_share_capital' => 0,
            'total_special_charges' => 0,
            'last_payment_date' => null,
            'payments_count' => 0
        ];
        $benefitAgg = $benefitsMap[$id] ?? [
            'total_benefits' => 0,
            'last_benefit_date' => null,
            'benefits_count' => 0
        ];

        $city = trim((string)$member['city']);
        if ($city !== '' && !in_array($city, $cities, true)) {
            $cities[] = $city;
        }

        $fullMembers[] = [
            'id' => $id,
            'name' => $member['name'],
            'membership_number' => $member['membership_number'],
            'membership_date' => $member['membership_date'],
            'nic' => $member['nic'],
            'city' => $member['city'],
            'contact_number' => $member['contact_number'],
            'address' => $member['address'],
            'status' => $member['status'],
            'status_reason' => $member['status_reason'],
            'date_added' => $member['date_added'],
            'dependents_count' => count($memberDependents),
            'dependents' => $memberDependents,
            'payments' => $paymentAgg,
            'benefits' => $benefitAgg,
            'outstanding' => calculate_outstanding_payments($member, $paymentMonthsMap[$id] ?? [])
        ];
    }

    sort($cities, SORT_NATURAL | SORT_FLAG_CASE);

    echo json_encode([
        'success' => true,
        'members' => $fullMembers,
        'meta' => [
            'cities' => $cities
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
