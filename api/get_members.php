<?php
require 'db.php';
require 'schema.php';
ensure_app_schema($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

try {
    $stmt = $pdo->query("SELECT id, name, membership_number, membership_date, nic, city, contact_number, address, status, status_reason, DATE_FORMAT(created_at, '%Y-%m-%d') as date_added FROM members WHERE is_deleted = 0 ORDER BY created_at DESC");
    $members = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'members' => $members
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
?>
