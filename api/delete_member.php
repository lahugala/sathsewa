<?php
require 'db.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

$inputJSON = file_get_contents('php://input');
$input = json_decode($inputJSON, TRUE);

if (isset($input['id'])) {
    try {
        $stmt = $pdo->prepare("UPDATE members SET is_deleted = 1 WHERE id = ?");
        $stmt->execute([$input['id']]);
        echo json_encode(["success" => true, "message" => "Member deleted successfully"]);
    } catch (\PDOException $e) {
        echo json_encode(["success" => false, "message" => "Database error: " . $e->getMessage()]);
    }
} else {
    echo json_encode(["success" => false, "message" => "Member ID missing"]);
}
?>
