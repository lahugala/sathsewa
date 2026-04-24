<?php
require 'db.php';
require 'schema.php';
ensure_app_schema($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

$inputJSON = file_get_contents('php://input');
$input = json_decode($inputJSON, TRUE);

if (isset($input['member_id'], $input['benefit_type'], $input['paid_date'], $input['amount'])) {
    try {
        $dependent_name = $input['dependent_name'] ?? null;
        $relationship = $input['relationship'] ?? null;
        $aid_nature = $input['aid_nature'] ?? null;

        $stmt = $pdo->prepare("INSERT INTO member_benefits (member_id, benefit_type, paid_date, dependent_name, relationship, aid_nature, amount) 
            VALUES (?, ?, ?, ?, ?, ?, ?)");
            
        $stmt->execute([
            $input['member_id'], 
            $input['benefit_type'], 
            $input['paid_date'], 
            $dependent_name,
            $relationship,
            $aid_nature,
            $input['amount']
        ]);

        echo json_encode(['success' => true, 'message' => 'Benefit record added successfully']);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Required fields missing']);
}
?>
