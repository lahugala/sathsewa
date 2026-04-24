<?php
require 'db.php';
require 'schema.php';
ensure_app_schema($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

$member_id = $_GET['member_id'] ?? null;
$year = $_GET['year'] ?? date('Y');

if ($member_id) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM payments WHERE member_id = ? AND payment_year = ? ORDER BY payment_month ASC");
        $stmt->execute([$member_id, $year]);
        $payments = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['success' => true, 'payments' => $payments]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Member ID is required']);
}
?>
