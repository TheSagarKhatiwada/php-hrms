<?php
require_once __DIR__ . '/../includes/db_connection.php';
$tables = ['shift_templates','employee_shift_assignments','employee_schedule_overrides'];
foreach ($tables as $t) {
    try {
        $stmt = $pdo->query("SHOW TABLES LIKE '" . $t . "'");
        $found = $stmt && $stmt->rowCount() > 0;
        echo $t . ' => ' . ($found ? 'EXISTS' : 'MISSING') . PHP_EOL;
    } catch (Throwable $e) {
        echo 'ERROR checking ' . $t . ': ' . $e->getMessage() . PHP_EOL;
    }
}
