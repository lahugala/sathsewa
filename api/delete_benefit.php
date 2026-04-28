<?php
require 'db.php';
require 'auth.php';
require_auth();

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

$inputJSON = file_get_contents('php://input');
$input = json_decode($inputJSON, TRUE);

if (isset($input['id'])) {
    try {
        $stmt = $pdo->prepare("DELETE FROM member_benefits WHERE id = ?");
        $stmt->execute([$input['id']]);

        echo json_encode(['success' => true, 'message' => 'Benefit record deleted successfully']);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Benefit ID missing']);
}
?>
