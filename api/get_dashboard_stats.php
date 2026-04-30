<?php
require 'db.php';
require 'auth.php';
require_auth();
require 'schema.php';
require 'outstanding_helpers.php';
require 'special_charge_helpers.php';
ensure_app_schema($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

try {
    $trendCalc = function($current, $previous) {
        $current = (int)$current;
        $previous = (int)$previous;
        $change = $current - $previous;
        if ($previous > 0) {
            $percent = round(($change / $previous) * 100, 1);
        } else {
            $percent = $current > 0 ? 100.0 : 0.0;
        }

        $direction = 'flat';
        if ($change > 0) {
            $direction = 'up';
        } elseif ($change < 0) {
            $direction = 'down';
        }

        return [
            'currentMonth' => $current,
            'previousMonth' => $previous,
            'change' => $change,
            'percent' => $percent,
            'direction' => $direction
        ];
    };

    $stmtMembers = $pdo->query("SELECT id, name, membership_number, membership_date, nic, city, contact_number, status, created_at, DATE_FORMAT(created_at, '%Y-%m-%d') as date_added FROM members WHERE is_deleted = 0 ORDER BY created_at DESC");
    $members = $stmtMembers->fetchAll(PDO::FETCH_ASSOC);
    $totalMembers = count($members);

    // Get total dependents
    $stmtDependents = $pdo->query("SELECT COUNT(*) as total_dependents FROM dependents d JOIN members m ON d.member_id = m.id WHERE m.is_deleted = 0");
    $totalDependents = $stmtDependents->fetch(PDO::FETCH_ASSOC)['total_dependents'];

    // Get 5 recent members
    $stmtRecent = $pdo->query("SELECT id, name, membership_number, nic, city, contact_number, status, DATE_FORMAT(created_at, '%Y-%m-%d') as date_added FROM members WHERE is_deleted = 0 ORDER BY created_at DESC LIMIT 5");
    $recentMembers = $stmtRecent->fetchAll(PDO::FETCH_ASSOC);

    $statusCounts = ['Active' => 0, 'Inactive' => 0, 'Suspended' => 0];
    $cityCounts = [];
    foreach ($members as $member) {
        $status = $member['status'] ?: 'Active';
        if (!isset($statusCounts[$status])) {
            $statusCounts[$status] = 0;
        }
        $statusCounts[$status]++;

        $city = trim((string)($member['city'] ?? ''));
        if ($city !== '') {
            $cityCounts[$city] = ($cityCounts[$city] ?? 0) + 1;
        }
    }

    arsort($cityCounts);
    $topCities = [];
    foreach (array_slice($cityCounts, 0, 5, true) as $city => $count) {
        $topCities[] = ['city' => $city, 'members' => (int)$count];
    }

    // Member additions this month vs previous month
    $stmtMemberTrend = $pdo->query("
        SELECT
            SUM(CASE WHEN created_at >= DATE_FORMAT(CURDATE(), '%Y-%m-01')
                      AND created_at < DATE_ADD(DATE_FORMAT(CURDATE(), '%Y-%m-01'), INTERVAL 1 MONTH)
                     THEN 1 ELSE 0 END) AS current_month,
            SUM(CASE WHEN created_at >= DATE_SUB(DATE_FORMAT(CURDATE(), '%Y-%m-01'), INTERVAL 1 MONTH)
                      AND created_at < DATE_FORMAT(CURDATE(), '%Y-%m-01')
                     THEN 1 ELSE 0 END) AS previous_month
        FROM members
        WHERE is_deleted = 0
    ");
    $memberTrendRaw = $stmtMemberTrend->fetch(PDO::FETCH_ASSOC);
    $memberTrend = $trendCalc($memberTrendRaw['current_month'] ?? 0, $memberTrendRaw['previous_month'] ?? 0);

    // Dependents tied to members created this month vs previous month
    $stmtDependentTrend = $pdo->query("
        SELECT
            SUM(CASE WHEN m.created_at >= DATE_FORMAT(CURDATE(), '%Y-%m-01')
                      AND m.created_at < DATE_ADD(DATE_FORMAT(CURDATE(), '%Y-%m-01'), INTERVAL 1 MONTH)
                     THEN 1 ELSE 0 END) AS current_month,
            SUM(CASE WHEN m.created_at >= DATE_SUB(DATE_FORMAT(CURDATE(), '%Y-%m-01'), INTERVAL 1 MONTH)
                      AND m.created_at < DATE_FORMAT(CURDATE(), '%Y-%m-01')
                     THEN 1 ELSE 0 END) AS previous_month
        FROM dependents d
        JOIN members m ON d.member_id = m.id
        WHERE m.is_deleted = 0
    ");
    $dependentTrendRaw = $stmtDependentTrend->fetch(PDO::FETCH_ASSOC);
    $dependentTrend = $trendCalc($dependentTrendRaw['current_month'] ?? 0, $dependentTrendRaw['previous_month'] ?? 0);

    $stmtFinance = $pdo->query("
        SELECT
            COALESCE(SUM(member_fee), 0) AS total_member_fee,
            COALESCE(SUM(share_capital), 0) AS total_share_capital,
            COALESCE(SUM(special_charges), 0) AS total_special_charges,
            COALESCE(SUM(member_fee + share_capital + special_charges), 0) AS total_income,
            COUNT(*) AS payment_records,
            MAX(paid_date) AS last_payment_date,
            COALESCE(SUM(CASE WHEN paid_date >= DATE_FORMAT(CURDATE(), '%Y-%m-01')
                               AND paid_date < DATE_ADD(DATE_FORMAT(CURDATE(), '%Y-%m-01'), INTERVAL 1 MONTH)
                              THEN COALESCE(member_fee,0) + COALESCE(share_capital,0) + COALESCE(special_charges,0) ELSE 0 END), 0) AS month_income
        FROM payments
    ");
    $financeRaw = $stmtFinance->fetch(PDO::FETCH_ASSOC);

    $stmtBenefits = $pdo->query("
        SELECT
            COALESCE(SUM(amount), 0) AS total_benefits,
            COUNT(*) AS benefits_count,
            MAX(paid_date) AS last_benefit_date,
            COALESCE(SUM(CASE WHEN paid_date >= DATE_FORMAT(CURDATE(), '%Y-%m-01')
                               AND paid_date < DATE_ADD(DATE_FORMAT(CURDATE(), '%Y-%m-01'), INTERVAL 1 MONTH)
                              THEN amount ELSE 0 END), 0) AS month_benefits
        FROM member_benefits
    ");
    $benefitsRaw = $stmtBenefits->fetch(PDO::FETCH_ASSOC);

    $stmtCurrentCharge = $pdo->query("
        SELECT COALESCE(SUM(amount), 0) AS amount,
               GROUP_CONCAT(description ORDER BY id SEPARATOR '; ') AS description
        FROM monthly_special_charges
        WHERE charge_year = YEAR(CURDATE())
          AND charge_month = MONTH(CURDATE())
          AND charge_scope = 'all'
    ");
    $currentCharge = $stmtCurrentCharge->fetch(PDO::FETCH_ASSOC) ?: null;

    $stmtPaymentRows = $pdo->query("SELECT member_id, payment_year, payment_month, member_fee, share_capital, special_charges FROM payments");
    $paymentRows = $stmtPaymentRows->fetchAll(PDO::FETCH_ASSOC);
    $paymentsMap = [];
    foreach ($paymentRows as $row) {
        $memberId = (int)$row['member_id'];
        if (!isset($paymentsMap[$memberId])) {
            $paymentsMap[$memberId] = [];
        }
        $paymentsMap[$memberId][] = $row;
    }

    $specialCharges = fetch_special_charges($pdo);
    $outstandingMembers = [];
    $totalOutstandingMonths = 0;
    $totalOutstandingAmount = 0.0;
    $outstandingAsOf = null;

    foreach ($members as $member) {
        $outstanding = calculate_outstanding_payments($member, $paymentsMap[(int)$member['id']] ?? [], null, 100.00, $specialCharges);
        $outstandingAsOf = $outstandingAsOf ?: $outstanding['as_of_date'];
        if ((int)$outstanding['outstanding_months'] > 0) {
            $totalOutstandingMonths += (int)$outstanding['outstanding_months'];
            $totalOutstandingAmount += (float)$outstanding['outstanding_amount'];
            $outstandingMembers[] = [
                'id' => (int)$member['id'],
                'name' => $member['name'],
                'membership_number' => $member['membership_number'],
                'city' => $member['city'],
                'status' => $member['status'],
                'outstanding_months' => (int)$outstanding['outstanding_months'],
                'outstanding_amount' => (float)$outstanding['outstanding_amount'],
                'last_paid_month' => $outstanding['last_paid_month']
            ];
        }
    }

    usort($outstandingMembers, function($a, $b) {
        return $b['outstanding_amount'] <=> $a['outstanding_amount'];
    });

    $stmtRecentPayments = $pdo->query("
        SELECT p.id, p.paid_date, p.payment_year, p.payment_month,
               (COALESCE(p.member_fee,0) + COALESCE(p.share_capital,0) + COALESCE(p.special_charges,0)) AS total_amount,
               m.name, m.membership_number
        FROM payments p
        JOIN members m ON p.member_id = m.id
        WHERE m.is_deleted = 0
        ORDER BY p.paid_date DESC, p.created_at DESC
        LIMIT 5
    ");
    $recentPayments = $stmtRecentPayments->fetchAll(PDO::FETCH_ASSOC);

    $stmtRecentBenefits = $pdo->query("
        SELECT b.id, b.benefit_type, b.paid_date, b.amount, b.dependent_name, b.aid_nature,
               m.name, m.membership_number
        FROM member_benefits b
        JOIN members m ON b.member_id = m.id
        WHERE m.is_deleted = 0
        ORDER BY b.paid_date DESC, b.created_at DESC
        LIMIT 5
    ");
    $recentBenefits = $stmtRecentBenefits->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'stats' => [
            'totalMembers' => (int)$totalMembers,
            'totalDependents' => (int)$totalDependents,
            'activeMembers' => (int)($statusCounts['Active'] ?? 0),
            'inactiveMembers' => (int)($statusCounts['Inactive'] ?? 0),
            'suspendedMembers' => (int)($statusCounts['Suspended'] ?? 0),
            'statusCounts' => $statusCounts,
            'memberTrend' => $memberTrend,
            'dependentTrend' => $dependentTrend,
            'finance' => [
                'totalIncome' => (float)$financeRaw['total_income'],
                'monthIncome' => (float)$financeRaw['month_income'],
                'totalMemberFee' => (float)$financeRaw['total_member_fee'],
                'totalShareCapital' => (float)$financeRaw['total_share_capital'],
                'totalSpecialCharges' => (float)$financeRaw['total_special_charges'],
                'paymentRecords' => (int)$financeRaw['payment_records'],
                'lastPaymentDate' => $financeRaw['last_payment_date'],
                'totalBenefits' => (float)$benefitsRaw['total_benefits'],
                'monthBenefits' => (float)$benefitsRaw['month_benefits'],
                'benefitsCount' => (int)$benefitsRaw['benefits_count'],
                'lastBenefitDate' => $benefitsRaw['last_benefit_date'],
                'netBalance' => (float)$financeRaw['total_income'] - (float)$benefitsRaw['total_benefits']
            ],
            'outstanding' => [
                'membersCount' => count($outstandingMembers),
                'totalMonths' => $totalOutstandingMonths,
                'totalAmount' => round($totalOutstandingAmount, 2),
                'asOfDate' => $outstandingAsOf
            ],
            'currentSpecialCharge' => $currentCharge && (float)$currentCharge['amount'] > 0 ? [
                'amount' => (float)$currentCharge['amount'],
                'description' => $currentCharge['description']
            ] : null
        ],
        'topCities' => $topCities,
        'topOutstandingMembers' => array_slice($outstandingMembers, 0, 5),
        'recentMembers' => $recentMembers,
        'recentPayments' => $recentPayments,
        'recentBenefits' => $recentBenefits
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
?>
