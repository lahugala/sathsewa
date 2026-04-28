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

    // Get total members
    $stmtMembers = $pdo->query("SELECT COUNT(*) as total_members FROM members WHERE is_deleted = 0");
    $totalMembers = $stmtMembers->fetch(PDO::FETCH_ASSOC)['total_members'];

    // Get total dependents
    $stmtDependents = $pdo->query("SELECT COUNT(*) as total_dependents FROM dependents d JOIN members m ON d.member_id = m.id WHERE m.is_deleted = 0");
    $totalDependents = $stmtDependents->fetch(PDO::FETCH_ASSOC)['total_dependents'];

    // Get 5 recent members
    $stmtRecent = $pdo->query("SELECT id, name, nic, city, DATE_FORMAT(created_at, '%Y-%m-%d') as date_added FROM members WHERE is_deleted = 0 ORDER BY created_at DESC LIMIT 5");
    $recentMembers = $stmtRecent->fetchAll(PDO::FETCH_ASSOC);

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

    echo json_encode([
        'success' => true,
        'stats' => [
            'totalMembers' => (int)$totalMembers,
            'totalDependents' => (int)$totalDependents,
            'memberTrend' => $memberTrend,
            'dependentTrend' => $dependentTrend
        ],
        'recentMembers' => $recentMembers
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
?>
