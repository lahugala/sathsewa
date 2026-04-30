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

$inputJSON = file_get_contents('php://input');
$input = json_decode($inputJSON, TRUE);

if (!isset($input['id'])) {
    echo json_encode(['success' => false, 'message' => 'Loan ID missing']);
    exit();
}

try {
    $stmt = $pdo->prepare("DELETE FROM member_loans WHERE id = ?");
    $stmt->execute([(int)$input['id']]);

    echo json_encode(['success' => true, 'message' => 'Loan deleted successfully']);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>
