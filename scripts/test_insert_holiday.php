<?php
// Test inserting a holiday using current holidays.php logic (without HTTP POST)
define('INCLUDE_CHECK', true);
require_once __DIR__ . '/../includes/db_connection.php';

$name = 'Test Holiday ' . date('YmdHis');
$startDate = date('Y-m-d');
$endDate = date('Y-m-d');
$type = 'company';
$branchId = null;
$description = 'Inserted by test script';
$recurringType = 'none';
$recurringDow = null; // simulate no weekly DOW selected
$isRec = 0;

try {
    $stmt = $pdo->prepare("INSERT INTO holidays (name, start_date, end_date, type, branch_id, description, is_recurring, recurring_type, recurring_day_of_week, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $ok = $stmt->execute([
        $name,
        $startDate,
        $endDate,
        $type,
        $branchId,
        $description,
        $isRec,
        $recurringType,
        $recurringDow,
        'active'
    ]);
    echo "Insert OK: " . ($ok ? 'yes' : 'no') . "\n";
    if ($ok) {
        echo "Inserted ID: " . $pdo->lastInsertId() . "\n";
    }
} catch (Exception $e) {
    echo "Insert failed: " . $e->getMessage() . "\n";
}

// Also test with empty string for recurring DOW (should be converted to NULL in handler)
try {
    $stmt = $pdo->prepare("INSERT INTO holidays (name, start_date, end_date, type, branch_id, description, is_recurring, recurring_type, recurring_day_of_week, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $ok2 = $stmt->execute([
        $name . ' emptydow',
        $startDate,
        $endDate,
        $type,
        $branchId,
        $description,
        $isRec,
        $recurringType,
        '', // intentionally empty string to simulate bad input
        'active'
    ]);
    echo "Insert (empty string dow) OK: " . ($ok2 ? 'yes' : 'no') . "\n";
} catch (Exception $e) {
    echo "Insert (empty string dow) failed as expected: " . $e->getMessage() . "\n";
}
