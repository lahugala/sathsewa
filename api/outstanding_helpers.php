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

function calculate_outstanding_payments($member, $paidMonths, $asOfDate = null, $monthlyFee = 100.00) {
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

    $paidLookup = [];
    foreach ($paidMonths as $payment) {
        if (!isset($payment['payment_year'], $payment['payment_month'])) {
            continue;
        }

        $memberFee = (float)($payment['member_fee'] ?? 0);
        $shareCapital = (float)($payment['share_capital'] ?? 0);
        $specialCharges = (float)($payment['special_charges'] ?? 0);
        $totalAmount = array_key_exists('total_amount', $payment)
            ? (float)$payment['total_amount']
            : ($memberFee + $shareCapital + $specialCharges);

        if ($totalAmount <= 0) {
            continue;
        }

        $period = normalize_month_start(sprintf('%04d-%02d-01', (int)$payment['payment_year'], (int)$payment['payment_month']));
        if (!$period || $period < $start || $period > $asOf) {
            continue;
        }

        $key = $period->format('Y-m');
        $paidLookup[$key] = true;
    }

    $missing = [];
    $expectedMonths = 0;
    $cursor = clone $start;
    while ($cursor <= $asOf) {
        $expectedMonths++;
        $key = $cursor->format('Y-m');
        if (!isset($paidLookup[$key])) {
            $missing[] = [
                'year' => (int)$cursor->format('Y'),
                'month' => (int)$cursor->format('n'),
                'label' => $cursor->format('F Y')
            ];
        }
        $cursor->modify('+1 month');
    }

    $paidKeys = array_keys($paidLookup);
    sort($paidKeys);
    $lastPaid = !empty($paidKeys) ? end($paidKeys) : null;

    return [
        'as_of_date' => $asOfDateLabel,
        'start_month' => $start->format('Y-m'),
        'expected_months' => $expectedMonths,
        'paid_months' => count($paidLookup),
        'outstanding_months' => count($missing),
        'outstanding_amount' => round(count($missing) * (float)$monthlyFee, 2),
        'missing_periods' => $missing,
        'last_paid_month' => $lastPaid
    ];
}
?>
