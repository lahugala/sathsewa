<?php
require 'db.php';
require 'auth.php';
require_auth();
require 'schema.php';
require 'loan_helpers.php';
ensure_app_schema($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

$memberId = isset($_GET['member_id']) ? (int)$_GET['member_id'] : 0;

if ($memberId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Member ID is required']);
    exit();
}

try {
    echo json_encode(['success' => true, 'loans' => fetch_member_loans($pdo, $memberId)]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>
