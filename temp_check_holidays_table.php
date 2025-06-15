<?php
define('INCLUDE_CHECK', true);
require_once 'includes/db_connection.php';

try {
    $stmt = $pdo->query('DESCRIBE holidays');
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "Holidays table columns:\n";
    foreach ($columns as $column) {
        echo "- " . $column['Field'] . " (" . $column['Type'] . ")\n";
    }
    
    // Also check if there are any existing holidays to understand the data structure
    echo "\nSample holidays data:\n";
    $stmt = $pdo->query('SELECT * FROM holidays LIMIT 3');
    $holidays = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($holidays as $holiday) {
        print_r($holiday);
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
