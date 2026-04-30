<?php
function normalize_month_start($dateValue) {
    if (empty($dateValue)) {
        return null;
    }

    try {
        $date = new DateTime($dateValue);
        $date->modify('first day of this month');
        $date->setTime(0, 0, 0);
        return $date;
    } catch (Exception $e) {
        return null;
    }
}

function calculate_outstanding_payments($member, $paidMonths, $asOfDate = null, $monthlyFee = 100.00, $specialCharges = []) {
    $today = $asOfDate ? new DateTime($asOfDate) : new DateTime();
    $asOf = clone $today;
    $asOf->modify('last day of previous month');
    $asOfDateLabel = $asOf->format('Y-m-d');
    $asOf->modify('first day of this month');
    $asOf->setTime(0, 0, 0);

    $start = normalize_month_start($member['membership_date'] ?? null);
    if (!$start) {
        $start = normalize_month_start($member['created_at'] ?? $member['date_added'] ?? null);
    }

    if (!$start || $start > $asOf) {
        return [
            'as_of_date' => $asOfDateLabel,
            'start_month' => null,
            'expected_months' => 0,
            'paid_months' => 0,
            'outstanding_months' => 0,
            'outstanding_amount' => 0.00,
            'missing_periods' => [],
            'last_paid_month' => null
        ];
    }

    $specialChargeLookup = function_exists('build_special_charge_lookup')
        ? build_special_charge_lookup($specialCharges, $member['id'] ?? null)
        : [];

    $paidLookup = [];
    foreach ($paidMonths as $payment) {
        if (!isset($payment['payment_year'], $payment['payment_month'])) {
            continue;
        }

        $period = normalize_month_start(sprintf('%04d-%02d-01', (int)$payment['payment_year'], (int)$payment['payment_month']));
        if (!$period || $period < $start || $period > $asOf) {
            continue;
        }

        $key = $period->format('Y-m');
        $paidLookup[$key] = [
            'member_fee' => (float)($payment['member_fee'] ?? 0),
            'special_charges' => (float)($payment['special_charges'] ?? 0),
            'share_capital' => (float)($payment['share_capital'] ?? 0)
        ];
    }

    $missing = [];
    $expectedMonths = 0;
    $paidMonthsCount = 0;
    $outstandingAmount = 0.0;
    $cursor = clone $start;
    while ($cursor <= $asOf) {
        $expectedMonths++;
        $key = $cursor->format('Y-m');
        $expectedSpecial = (float)($specialChargeLookup[$key]['amount'] ?? 0);
        $expectedMemberFee = (float)$monthlyFee;
        $paid = $paidLookup[$key] ?? [
            'member_fee' => 0,
            'special_charges' => 0,
            'share_capital' => 0
        ];
        $memberFeeBalance = max(0, $expectedMemberFee - (float)$paid['member_fee']);
        $specialBalance = max(0, $expectedSpecial - (float)$paid['special_charges']);
        $periodOutstanding = round($memberFeeBalance + $specialBalance, 2);

        if ($periodOutstanding > 0) {
            $missing[] = [
                'year' => (int)$cursor->format('Y'),
                'month' => (int)$cursor->format('n'),
                'label' => $cursor->format('F Y'),
                'expected_amount' => round($expectedMemberFee + $expectedSpecial, 2),
                'expected_special_charges' => round($expectedSpecial, 2),
                'paid_amount' => round((float)$paid['member_fee'] + (float)$paid['special_charges'], 2),
                'outstanding_amount' => $periodOutstanding
            ];
            $outstandingAmount += $periodOutstanding;
        } else {
            $paidMonthsCount++;
        }
        $cursor->modify('+1 month');
    }

    $paidKeys = [];
    foreach ($paidLookup as $key => $payment) {
        $period = normalize_month_start($key . '-01');
        if (!$period) {
            continue;
        }

        $expectedSpecial = (float)($specialChargeLookup[$key]['amount'] ?? 0);
        $isPaid = (float)$payment['member_fee'] >= (float)$monthlyFee
            && (float)$payment['special_charges'] >= $expectedSpecial;
        if ($isPaid) {
            $paidKeys[] = $key;
        }
    }
    sort($paidKeys);
    $lastPaid = !empty($paidKeys) ? end($paidKeys) : null;

    return [
        'as_of_date' => $asOfDateLabel,
        'start_month' => $start->format('Y-m'),
        'expected_months' => $expectedMonths,
        'paid_months' => $paidMonthsCount,
        'outstanding_months' => count($missing),
        'outstanding_amount' => round($outstandingAmount, 2),
        'missing_periods' => $missing,
        'last_paid_month' => $lastPaid
    ];
}
?>
