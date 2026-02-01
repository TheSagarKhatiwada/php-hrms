<?php
require_once 'includes/db_connection.php';

echo "Checking activity_log table...\n";
try {
    $pdo->query("SELECT 1 FROM activity_log LIMIT 1");
    echo "activity_log table exists.\n";
} catch (PDOException $e) {
    echo "activity_log table MISSING: " . $e->getMessage() . "\n";
}

echo "\nChecking departments query...\n";
try {
    $sql = "SELECT d.*, CONCAT(e.first_name, ' ', COALESCE(e.middle_name, ''), ' ', e.last_name) AS manager_name 
        FROM departments d 
        LEFT JOIN employees e ON d.manager_id = e.emp_id 
        ORDER BY d.name ASC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $departments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "Departments query successful. Count: " . count($departments) . "\n";
} catch (PDOException $e) {
    echo "Departments query FAILED: " . $e->getMessage() . "\n";
}

echo "\nChecking holiday check...\n";
require_once 'includes/utilities.php';
try {
    $res = is_holiday('2025-12-25');
    echo "Holiday check (no branch) result: " . json_encode($res) . "\n";
    
    $res2 = is_holiday('2025-12-25', 1);
    echo "Holiday check (branch 1) result: " . json_encode($res2) . "\n";
} catch (Throwable $e) {
    echo "Holiday check FAILED: " . $e->getMessage() . "\n";
}
