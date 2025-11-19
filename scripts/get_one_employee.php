<?php
require_once __DIR__ . '/../includes/db_connection.php';
try {
    $stmt = $pdo->query("SELECT emp_id, first_name, last_name FROM employees LIMIT 1");
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row) {
        echo "Found: " . $row['emp_id'] . ' - ' . ($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? '') . PHP_EOL;
    } else {
        echo "No employees found\n";
    }
} catch (Throwable $e) {
    echo "ERROR: " . $e->getMessage() . PHP_EOL;
}
