<?php
function fetch_special_charges($pdo, $year = null) {
    if ($year !== null) {
        $stmt = $pdo->prepare("SELECT charge_year, charge_month, amount, description FROM monthly_special_charges WHERE charge_year = ? ORDER BY charge_year ASC, charge_month ASC");
        $stmt->execute([(int)$year]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    $stmt = $pdo->query("SELECT charge_year, charge_month, amount, description FROM monthly_special_charges ORDER BY charge_year ASC, charge_month ASC");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function build_special_charge_lookup($specialCharges) {
    $lookup = [];
    foreach ($specialCharges as $charge) {
        if (!isset($charge['charge_year'], $charge['charge_month'])) {
            continue;
        }

        $key = sprintf('%04d-%02d', (int)$charge['charge_year'], (int)$charge['charge_month']);
        $lookup[$key] = [
            'amount' => (float)($charge['amount'] ?? 0),
            'description' => $charge['description'] ?? ''
        ];
    }

    return $lookup;
}
?>
