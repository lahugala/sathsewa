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

$input = json_decode(file_get_contents('php://input'), true);

$year = isset($input['charge_year']) ? (int)$input['charge_year'] : 0;
$month = isset($input['charge_month']) ? (int)$input['charge_month'] : 0;
$amount = isset($input['amount']) ? (float)$input['amount'] : -1;
$description = trim($input['description'] ?? '');

if ($year < 2000 || $year > 2100 || $month < 1 || $month > 12 || $amount < 0) {
    echo json_encode(['success' => false, 'message' => 'Valid year, month, and amount are required']);
    exit();
}

try {
    $stmt = $pdo->prepare("INSERT INTO monthly_special_charges (charge_year, charge_month, amount, description)
        VALUES (?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            amount = VALUES(amount),
            description = VALUES(description)");
    $stmt->execute([$year, $month, $amount, $description]);

    echo json_encode(['success' => true, 'message' => 'Special charge saved successfully']);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>
