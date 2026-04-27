<?php
require 'db.php';
require 'schema.php';
ensure_app_schema($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

$input = json_decode(file_get_contents('php://input'), true);
$year = isset($input['charge_year']) ? (int)$input['charge_year'] : 0;
$month = isset($input['charge_month']) ? (int)$input['charge_month'] : 0;

if ($year < 2000 || $year > 2100 || $month < 1 || $month > 12) {
    echo json_encode(['success' => false, 'message' => 'Valid year and month are required']);
    exit();
}

try {
    $stmt = $pdo->prepare("DELETE FROM monthly_special_charges WHERE charge_year = ? AND charge_month = ?");
    $stmt->execute([$year, $month]);

    echo json_encode(['success' => true, 'message' => 'Special charge deleted successfully']);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>
