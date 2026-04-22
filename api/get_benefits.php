<?php
require 'db.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

$member_id = $_GET['member_id'] ?? null;

if ($member_id) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM member_benefits WHERE member_id = ? ORDER BY paid_date DESC, created_at DESC");
        $stmt->execute([$member_id]);
        $benefits = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['success' => true, 'benefits' => $benefits]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Member ID is required']);
}
?>
