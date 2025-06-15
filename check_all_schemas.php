<?php
define('INCLUDE_CHECK', true);
require_once 'includes/db_connection.php';

try {
    echo "Checking all table schemas for missing columns...\n\n";
    
    // Check employees table
    echo "=== EMPLOYEES TABLE ===\n";
    $stmt = $pdo->query("DESCRIBE employees");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($columns as $column) {
        echo "- " . $column['Field'] . " (" . $column['Type'] . ")\n";
    }
    
    // Check leave_types table
    echo "\n=== LEAVE_TYPES TABLE ===\n";
    $stmt = $pdo->query("DESCRIBE leave_types");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($columns as $column) {
        echo "- " . $column['Field'] . " (" . $column['Type'] . ")\n";
    }
    
    // Check leave_requests table
    echo "\n=== LEAVE_REQUESTS TABLE ===\n";
    $stmt = $pdo->query("DESCRIBE leave_requests");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($columns as $column) {
        echo "- " . $column['Field'] . " (" . $column['Type'] . ")\n";
    }
    
    // Check branches table
    echo "\n=== BRANCHES TABLE ===\n";
    $stmt = $pdo->query("DESCRIBE branches");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($columns as $column) {
        echo "- " . $column['Field'] . " (" . $column['Type'] . ")\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
