<?php
require 'db.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

$inputJSON = file_get_contents('php://input');
$input = json_decode($inputJSON, TRUE);

if (isset($input['name'], $input['nic'], $input['address'], $input['city'], $input['contact_number'])) {
    $membership_number = isset($input['membership_number']) ? $input['membership_number'] : null;
    $membership_date = isset($input['membership_date']) && !empty($input['membership_date']) ? $input['membership_date'] : null;
    try {
        $pdo->beginTransaction();
        
        $isEdit = isset($input['id']) && !empty($input['id']);

        if ($isEdit) {
            $memberId = $input['id'];
            $stmt = $pdo->prepare("UPDATE members SET name = ?, membership_number = ?, membership_date = ?, nic = ?, address = ?, city = ?, contact_number = ? WHERE id = ?");
            $stmt->execute([$input['name'], $membership_number, $membership_date, $input['nic'], $input['address'], $input['city'], $input['contact_number'], $memberId]);
            
            // Delete existing dependents
            $delDepStmt = $pdo->prepare("DELETE FROM dependents WHERE member_id = ?");
            $delDepStmt->execute([$memberId]);
        } else {
            $stmt = $pdo->prepare("INSERT INTO members (name, membership_number, membership_date, nic, address, city, contact_number) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$input['name'], $membership_number, $membership_date, $input['nic'], $input['address'], $input['city'], $input['contact_number']]);
            $memberId = $pdo->lastInsertId();
        }

        if (isset($input['dependents']) && is_array($input['dependents'])) {
            $depStmt = $pdo->prepare("INSERT INTO dependents (member_id, name, relationship, birth_year) VALUES (?, ?, ?, ?)");
            foreach ($input['dependents'] as $dep) {
                if (!empty($dep['name'])) {
                    $depStmt->execute([$memberId, $dep['name'], $dep['relationship'], $dep['birth_year']]);
                }
            }
        }

        $pdo->commit();
        $message = $isEdit ? "Member updated successfully" : "Application submitted successfully";
        echo json_encode(["success" => true, "message" => $message]);
    } catch (\PDOException $e) {
        $pdo->rollBack();
        if ($e->getCode() == 23000) {
            echo json_encode(["success" => false, "message" => "NIC already exists"]);
        } else {
            echo json_encode(["success" => false, "message" => "Database error: " . $e->getMessage()]);
        }
    }
} else {
    echo json_encode(["success" => false, "message" => "Required fields missing"]);
}
?>
