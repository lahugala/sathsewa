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
$chargeId = isset($input['id']) ? (int)$input['id'] : 0;
$chargeScope = ($input['charge_scope'] ?? 'all') === 'targeted' ? 'targeted' : 'all';
$memberIds = array_values(array_unique(array_filter(array_map('intval', $input['member_ids'] ?? []))));
$description = trim($input['description'] ?? '');

if ($year < 2000 || $year > 2100 || $month < 1 || $month > 12 || $amount <= 0) {
    echo json_encode(['success' => false, 'message' => 'Special charge amount must be greater than 0']);
    exit();
}

if ($description === '') {
    echo json_encode(['success' => false, 'message' => 'Description is required for special charges']);
    exit();
}

if ($chargeScope === 'targeted' && empty($memberIds)) {
    echo json_encode(['success' => false, 'message' => 'Select at least one target member']);
    exit();
}

try {
    $pdo->beginTransaction();

    if ($chargeId > 0) {
        $stmt = $pdo->prepare("UPDATE monthly_special_charges
            SET charge_year = ?, charge_month = ?, amount = ?, charge_scope = ?, description = ?
            WHERE id = ?");
        $stmt->execute([$year, $month, $amount, $chargeScope, $description, $chargeId]);
    } else {
        $stmt = $pdo->prepare("INSERT INTO monthly_special_charges (charge_year, charge_month, amount, charge_scope, description)
            VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$year, $month, $amount, $chargeScope, $description]);
        $chargeId = (int)$pdo->lastInsertId();
    }

    $deleteTargets = $pdo->prepare("DELETE FROM special_charge_targets WHERE charge_id = ?");
    $deleteTargets->execute([$chargeId]);

    if ($chargeScope === 'targeted') {
        $memberPlaceholders = implode(',', array_fill(0, count($memberIds), '?'));
        $stmtMembers = $pdo->prepare("SELECT id FROM members WHERE id IN ($memberPlaceholders) AND is_deleted = 0");
        $stmtMembers->execute($memberIds);
        $validMemberIds = array_map('intval', array_column($stmtMembers->fetchAll(PDO::FETCH_ASSOC), 'id'));

        if (count($validMemberIds) !== count($memberIds)) {
            $pdo->rollBack();
            echo json_encode(['success' => false, 'message' => 'One or more selected members could not be found']);
            exit();
        }

        $stmtTarget = $pdo->prepare("INSERT INTO special_charge_targets (charge_id, member_id) VALUES (?, ?)");
        foreach ($validMemberIds as $memberId) {
            $stmtTarget->execute([$chargeId, $memberId]);
        }
    }

    $pdo->commit();

    echo json_encode(['success' => true, 'message' => 'Special charge saved successfully']);
} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>
