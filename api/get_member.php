<?php
require 'db.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

if (isset($_GET['id'])) {
    try {
        $stmt = $pdo->prepare("SELECT id, name, nic, city, contact_number, address FROM members WHERE id = ? AND is_deleted = 0");
        $stmt->execute([$_GET['id']]);
        $member = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($member) {
            $depStmt = $pdo->prepare("SELECT name, relationship, birth_year FROM dependents WHERE member_id = ?");
            $depStmt->execute([$member['id']]);
            $member['dependents'] = $depStmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode(["success" => true, "member" => $member]);
        } else {
            echo json_encode(["success" => false, "message" => "Member not found"]);
        }
    } catch (\PDOException $e) {
        echo json_encode(["success" => false, "message" => "Database error: " . $e->getMessage()]);
    }
} else {
    echo json_encode(["success" => false, "message" => "Member ID missing"]);
}
?>
