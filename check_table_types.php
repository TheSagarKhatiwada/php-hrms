<?php
define('INCLUDE_CHECK', true);
require_once 'includes/db_connection.php';

try {
    echo "Checking branches table structure:\n";
    $stmt = $pdo->query("DESCRIBE branches");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($columns as $column) {
        echo "- " . $column['Field'] . " (" . $column['Type'] . ")\n";
    }
    
    echo "\nChecking holidays table structure:\n";
    $stmt = $pdo->query("DESCRIBE holidays");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($columns as $column) {
        echo "- " . $column['Field'] . " (" . $column['Type'] . ")\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
