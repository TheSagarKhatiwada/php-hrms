<?php
require_once 'includes/db_connection.php';

$stmt = $pdo->query('DESCRIBE employees');
$columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "Employees table structure:\n";
foreach ($columns as $column) {
    if (in_array($column['Field'], ['emp_id', 'id'])) {
        echo "  {$column['Field']} - {$column['Type']} - {$column['Key']}\n";
    }
}

// Check the actual data type mismatch
echo "\nData type comparison:\n";
$stmt = $pdo->query("SHOW COLUMNS FROM tasks WHERE Field IN ('assigned_by', 'assigned_to')");
$task_columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach ($task_columns as $col) {
    echo "  tasks.{$col['Field']} = {$col['Type']}\n";
}

$stmt = $pdo->query("SHOW COLUMNS FROM employees WHERE Field = 'emp_id'");
$emp_column = $stmt->fetch(PDO::FETCH_ASSOC);
echo "  employees.emp_id = {$emp_column['Type']}\n";
?>
