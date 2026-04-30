<?php
function fetch_special_charges($pdo, $year = null) {
    if ($year !== null) {
        $stmt = $pdo->prepare("SELECT id, charge_year, charge_month, amount, charge_scope, description FROM monthly_special_charges WHERE charge_year = ? ORDER BY charge_year ASC, charge_month ASC, id ASC");
        $stmt->execute([(int)$year]);
        return attach_special_charge_targets($pdo, $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    $stmt = $pdo->query("SELECT id, charge_year, charge_month, amount, charge_scope, description FROM monthly_special_charges ORDER BY charge_year ASC, charge_month ASC, id ASC");
    return attach_special_charge_targets($pdo, $stmt->fetchAll(PDO::FETCH_ASSOC));
}

function attach_special_charge_targets($pdo, $charges) {
    if (empty($charges)) {
        return [];
    }

    $chargeIds = array_map(function($charge) {
        return (int)$charge['id'];
    }, $charges);
    $placeholders = implode(',', array_fill(0, count($chargeIds), '?'));

    $stmt = $pdo->prepare("
        SELECT t.charge_id, t.member_id, m.name, m.membership_number
        FROM special_charge_targets t
        JOIN members m ON t.member_id = m.id
        WHERE t.charge_id IN ($placeholders) AND m.is_deleted = 0
        ORDER BY m.name ASC
    ");
    $stmt->execute($chargeIds);
    $targets = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $targetMap = [];
    foreach ($targets as $target) {
        $chargeId = (int)$target['charge_id'];
        if (!isset($targetMap[$chargeId])) {
            $targetMap[$chargeId] = [];
        }
        $targetMap[$chargeId][] = [
            'member_id' => (int)$target['member_id'],
            'name' => $target['name'],
            'membership_number' => $target['membership_number']
        ];
    }

    foreach ($charges as &$charge) {
        $chargeId = (int)$charge['id'];
        $charge['charge_scope'] = $charge['charge_scope'] ?? 'all';
        $charge['targets'] = $targetMap[$chargeId] ?? [];
    }

    return $charges;
}

function build_special_charge_lookup($specialCharges, $memberId = null) {
    $lookup = [];
    foreach ($specialCharges as $charge) {
        if (!isset($charge['charge_year'], $charge['charge_month'])) {
            continue;
        }

        $scope = $charge['charge_scope'] ?? 'all';
        if ($scope === 'targeted') {
            if ($memberId === null) {
                continue;
            }

            $targetIds = array_map(function($target) {
                if (is_array($target)) {
                    return (int)($target['member_id'] ?? 0);
                }
                return (int)$target;
            }, $charge['targets'] ?? []);

            if (!in_array((int)$memberId, $targetIds, true)) {
                continue;
            }
        }

        $key = sprintf('%04d-%02d', (int)$charge['charge_year'], (int)$charge['charge_month']);
        if (!isset($lookup[$key])) {
            $lookup[$key] = [
                'amount' => 0.0,
                'description' => '',
                'charges' => []
            ];
        }

        $amount = (float)($charge['amount'] ?? 0);
        $description = trim((string)($charge['description'] ?? ''));
        $lookup[$key]['amount'] += $amount;
        if ($description !== '') {
            $lookup[$key]['charges'][] = [
                'amount' => $amount,
                'description' => $description,
                'charge_scope' => $scope
            ];
        }
        $lookup[$key]['description'] = implode('; ', array_map(function($item) {
            return $item['description'];
        }, $lookup[$key]['charges']));
    }

    return $lookup;
}
?>
