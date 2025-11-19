<?php
require_once __DIR__ . '/../includes/db_connection.php';
try {
    $stmt = $pdo->prepare('SELECT id, emp_id, start_date, end_date, recurring_yearly, work_start_time, work_end_time, priority, reason, created_at FROM employee_schedule_overrides WHERE emp_id = ?');
    $stmt->execute(['101']);
    while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo implode(' | ', array_map(function($v) { return $v ?? 'NULL'; }, $r)) . PHP_EOL;
    }
} catch (Throwable $e) {
    echo "ERROR: " . $e->getMessage() . PHP_EOL;
}