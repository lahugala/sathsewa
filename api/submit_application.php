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

if (isset($input['name'], $input['nic'], $input['address'], $input['city'], $input['contact_number'])) {
    $nic = strtoupper(preg_replace('/[\s-]+/', '', (string)$input['nic']));
    $nic = preg_replace('/[^0-9VX]/', '', $nic);
    $contactNumber = preg_replace('/\D+/', '', (string)$input['contact_number']);

    if (!preg_match('/^(\d{9}[VX]?|\d{12})$/', $nic)) {
        echo json_encode(["success" => false, "message" => "NIC must be 9 digits with optional V/X, or 12 digits"]);
        exit();
    }

    if (!preg_match('/^\d{10}$/', $contactNumber)) {
        echo json_encode(["success" => false, "message" => "Contact number must contain exactly 10 digits"]);
        exit();
    }

    $membership_number = isset($input['membership_number']) ? $input['membership_number'] : null;
    $membership_date = isset($input['membership_date']) && !empty($input['membership_date']) ? $input['membership_date'] : null;
    $allowedStatuses = ['Active', 'Inactive', 'Suspended'];
    $status = isset($input['status']) && in_array($input['status'], $allowedStatuses, true) ? $input['status'] : 'Active';
    $statusReason = isset($input['status_reason']) ? trim((string)$input['status_reason']) : '';
    if ($status === 'Active') {
        $statusReason = null;
    } elseif ($statusReason === '') {
        echo json_encode(["success" => false, "message" => "Reason is required for {$status} members"]);
        exit();
    }

    try {
        $pdo->beginTransaction();
        
        $isEdit = isset($input['id']) && !empty($input['id']);

        if ($isEdit) {
            $memberId = $input['id'];
            $currentStmt = $pdo->prepare("SELECT status, status_reason FROM members WHERE id = ? AND is_deleted = 0");
            $currentStmt->execute([$memberId]);
            $currentMember = $currentStmt->fetch(PDO::FETCH_ASSOC);

            if (!$currentMember) {
                throw new Exception('Member not found');
            }

            $oldStatus = $currentMember['status'] ?? 'Active';

            $oldReason = $currentMember['status_reason'] ?? null;

            $stmt = $pdo->prepare("UPDATE members SET name = ?, membership_number = ?, membership_date = ?, nic = ?, address = ?, city = ?, contact_number = ?, status = ?, status_reason = ? WHERE id = ?");
            $stmt->execute([$input['name'], $membership_number, $membership_date, $nic, $input['address'], $input['city'], $contactNumber, $status, $statusReason, $memberId]);

            if ($oldStatus !== $status || ($status !== 'Active' && (string)$oldReason !== (string)$statusReason)) {
                $historyStmt = $pdo->prepare("INSERT INTO member_status_history (member_id, old_status, new_status, reason) VALUES (?, ?, ?, ?)");
                $historyStmt->execute([$memberId, $oldStatus, $status, $statusReason ?: 'Member profile update']);
            }
            
            // Delete existing dependents
            $delDepStmt = $pdo->prepare("DELETE FROM dependents WHERE member_id = ?");
            $delDepStmt->execute([$memberId]);
        } else {
            $stmt = $pdo->prepare("INSERT INTO members (name, membership_number, membership_date, nic, address, city, contact_number, status, status_reason) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$input['name'], $membership_number, $membership_date, $nic, $input['address'], $input['city'], $contactNumber, $status, $statusReason]);
            $memberId = $pdo->lastInsertId();

            $historyStmt = $pdo->prepare("INSERT INTO member_status_history (member_id, old_status, new_status, reason) VALUES (?, NULL, ?, ?)");
            $historyStmt->execute([$memberId, $status, $statusReason ?: 'Member created']);
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
    } catch (\Exception $e) {
        $pdo->rollBack();
        echo json_encode(["success" => false, "message" => $e->getMessage()]);
    }
} else {
    echo json_encode(["success" => false, "message" => "Required fields missing"]);
}
?>
