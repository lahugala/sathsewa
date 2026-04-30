<?php
require 'db.php';
require 'auth.php';
require_auth();
require 'schema.php';
require 'special_charge_helpers.php';
ensure_app_schema($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

$member_id = $_GET['member_id'] ?? null;
$year = $_GET['year'] ?? date('Y');

if ($member_id) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM payments WHERE member_id = ? AND payment_year = ? ORDER BY payment_month ASC");
        $stmt->execute([$member_id, $year]);
        $payments = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $specialCharges = fetch_special_charges($pdo, $year);
        $specialChargeLookup = build_special_charge_lookup($specialCharges, (int)$member_id);
        $memberSpecialCharges = [];
        foreach ($specialChargeLookup as $key => $charge) {
            [$chargeYear, $chargeMonth] = array_map('intval', explode('-', $key));
            if ((int)$chargeYear !== (int)$year) {
                continue;
            }
            $memberSpecialCharges[] = [
                'charge_year' => $chargeYear,
                'charge_month' => $chargeMonth,
                'amount' => round((float)$charge['amount'], 2),
                'description' => $charge['description'] ?? '',
                'charges' => $charge['charges'] ?? []
            ];
        }
        echo json_encode([
            'success' => true,
            'payments' => $payments,
            'predefined_special_charges' => $memberSpecialCharges
        ]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Member ID is required']);
}
?>
