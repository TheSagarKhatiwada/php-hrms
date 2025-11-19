<?php
require_once __DIR__ . '/../includes/db_connection.php';
$emp = '101';
$start = '2025-10-25';
$end = '2025-11-05';
$startTime = '09:30:00';
$endTime = '18:00:00';
$priority = 10;
try {
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM employee_schedule_overrides WHERE emp_id = ? AND start_date = ? AND end_date = ?');
    $stmt->execute([$emp, $start, $end]);
    $c = $stmt->fetchColumn();
    if ($c > 0) {
        echo "Override already exists\n";
        exit(0);
    }
    $ins = $pdo->prepare('INSERT INTO employee_schedule_overrides (emp_id, start_date, end_date, recurring_yearly, work_start_time, work_end_time, priority, reason, created_by) VALUES (?, ?, ?, 0, ?, ?, ?, ?, ?)');
    $ins->execute([$emp, $start, $end, $startTime, $endTime, $priority, 'Test override for smoke test', 'script']);
    echo "Inserted override id: " . $pdo->lastInsertId() . PHP_EOL;
} catch (Throwable $e) {
    echo "ERROR: " . $e->getMessage() . PHP_EOL;
}
