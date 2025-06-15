<?php
define('INCLUDE_CHECK', true);
require_once 'includes/db_connection.php';

try {
    echo "Checking leave_requests table structure:\n";
    $stmt = $pdo->query("DESCRIBE leave_requests");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($columns as $column) {
        echo "- " . $column['Field'] . " (" . $column['Type'] . ")\n";
    }
    
    echo "\nSample data from leave_requests:\n";
    $stmt = $pdo->query("SELECT * FROM leave_requests LIMIT 2");
    $requests = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if (empty($requests)) {
        echo "No data in leave_requests table\n";
    } else {
        foreach ($requests as $request) {
            print_r($request);
        }
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
