<?php
// Simple test harness for includes/schedule_helpers.php
require_once __DIR__ . '/../../includes/session_config.php';
require_once __DIR__ . '/../../includes/db_connection.php';
require_once __DIR__ . '/../../includes/schedule_helpers.php';

function assert_eq($a, $b, $msg=''){
    if ($a === $b) { echo "PASS: $msg\n"; } else { echo "FAIL: $msg\nExpected:".var_export($b,true)."\nGot:".var_export($a,true)."\n"; }
}

// Basic smoke tests
echo "Running schedule_helpers basic tests...\n";
$emp = ['emp_id' => 1, 'work_start_time' => '09:00', 'work_end_time' => '17:00'];
$date = date('Y-m-d');
$overrides = [];
$sched = resolve_schedule_for_emp_date($emp, $date, $overrides, '09:00', '17:00');
assert_eq(is_array($sched), true, 'resolve_schedule returns array');
assert_eq(isset($sched['start']), true, 'schedule has start');
assert_eq(isset($sched['end']), true, 'schedule has end');

// Test prefetch (should not error)
try {
    $ids = [$emp['emp_id']];
    $map = prefetch_schedule_overrides($pdo, $ids, $date, $date);
    assert_eq(is_array($map), true, 'prefetch returns array');
    echo "schedule_helpers tests completed.\n";
} catch (Exception $e) {
    echo "ERROR running prefetch: " . $e->getMessage() . "\n";
}

?>
