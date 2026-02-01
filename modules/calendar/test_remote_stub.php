<?php
// Simple stub to simulate remote Nepali calendar API for testing
// Usage: test_remote_stub.php?mode=bs&year=2025&month=12
header('Content-Type: application/json');

$mode = strtolower($_GET['mode'] ?? 'ad');
$year = (int)($_GET['year'] ?? date('Y'));
$month = (int)($_GET['month'] ?? date('n'));
if ($month < 1 || $month > 12) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid month']);
    exit;
}

// Build a simple month of AD dates
$start = DateTime::createFromFormat('Y-n-j', "$year-$month-1");
$end = clone $start;
$end->modify('last day of this month');

$days = [];
$period = new DatePeriod($start, new DateInterval('P1D'), $end->modify('+1 day'));
foreach ($period as $dt) {
    $ad = $dt->format('Y-m-d');
    // For simplicity in this stub, make BS date mirror AD date (real API would provide proper BS)
    $bs = $ad;
    $days[] = [
        'ad_date' => $ad,
        'bs_date' => $bs,
        'bs_year' => (int)$dt->format('Y'),
        'bs_month' => (int)$dt->format('n'),
        'bs_day' => (int)$dt->format('j'),
        'bs_month_name' => 'StubMonth',
    ];
}

// Determine leading/trailing to fill full weeks (Sunday-first)
$firstWeekday = (int)date('w', strtotime($days[0]['ad_date']));
$leading = [];
if ($firstWeekday > 0) {
    $prevEnd = (new DateTime($days[0]['ad_date']))->modify('-1 day');
    for ($i = 0; $i < $firstWeekday; $i++) {
        $d = clone $prevEnd;
        $d->modify('-' . ($firstWeekday - 1 - $i) . ' days');
        $ad = $d->format('Y-m-d');
        $bs = $ad;
        $leading[] = [
            'ad_date' => $ad,
            'bs_date' => $bs,
            'bs_year' => (int)$d->format('Y'),
            'bs_month' => (int)$d->format('n'),
            'bs_day' => (int)$d->format('j'),
            'bs_month_name' => 'StubMonth',
        ];
    }
}

$filledCells = count($leading) + count($days);
$trailingNeeded = $filledCells % 7 === 0 ? 0 : 7 - ($filledCells % 7);
$trailing = [];
if ($trailingNeeded > 0) {
    $last = new DateTime(end($days)['ad_date']);
    for ($i = 1; $i <= $trailingNeeded; $i++) {
        $d = clone $last;
        $d->modify('+' . $i . ' days');
        $ad = $d->format('Y-m-d');
        $bs = $ad;
        $trailing[] = [
            'ad_date' => $ad,
            'bs_date' => $bs,
            'bs_year' => (int)$d->format('Y'),
            'bs_month' => (int)$d->format('n'),
            'bs_day' => (int)$d->format('j'),
            'bs_month_name' => 'StubMonth',
        ];
    }
}

$meta = [
    'mode' => $mode,
    'requested_year' => $year,
    'requested_month' => $month,
    'english_month_name' => $start->format('F'),
    'english_year' => (int)$start->format('Y'),
    'bs_month_name' => 'StubMonth',
    'bs_year' => (int)$start->format('Y'),
];

$response = [
    'status' => 'success',
    'meta' => $meta,
    'days' => $days,
    'leading_days' => $leading,
    'trailing_days' => $trailing,
];

echo json_encode($response);
