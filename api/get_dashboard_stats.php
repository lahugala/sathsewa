<?php
require 'db.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

try {
    // Get total members
    $stmtMembers = $pdo->query("SELECT COUNT(*) as total_members FROM members WHERE is_deleted = 0");
    $totalMembers = $stmtMembers->fetch(PDO::FETCH_ASSOC)['total_members'];

    // Get total dependents
    $stmtDependents = $pdo->query("SELECT COUNT(*) as total_dependents FROM dependents d JOIN members m ON d.member_id = m.id WHERE m.is_deleted = 0");
    $totalDependents = $stmtDependents->fetch(PDO::FETCH_ASSOC)['total_dependents'];

    // Get 5 recent members
    $stmtRecent = $pdo->query("SELECT id, name, nic, city, DATE_FORMAT(created_at, '%Y-%m-%d') as date_added FROM members WHERE is_deleted = 0 ORDER BY created_at DESC LIMIT 5");
    $recentMembers = $stmtRecent->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'stats' => [
            'totalMembers' => $totalMembers,
            'totalDependents' => $totalDependents
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
