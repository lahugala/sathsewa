<?php
require 'db.php';
require 'schema.php';
require 'special_charge_helpers.php';
ensure_app_schema($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

$year = isset($_GET['year']) && $_GET['year'] !== '' ? (int)$_GET['year'] : null;

try {
    echo json_encode([
        'success' => true,
        'charges' => fetch_special_charges($pdo, $year)
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>
