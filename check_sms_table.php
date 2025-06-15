<?php
define('INCLUDE_CHECK', true);
require_once 'includes/db_connection.php';

try {
    echo "Checking SMS logs table structure:\n";
    $stmt = $pdo->query("DESCRIBE sms_logs");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($columns as $column) {
        echo "- " . $column['Field'] . " (" . $column['Type'] . ")\n";
    }
    
    echo "\nSample data from sms_logs:\n";
    $stmt = $pdo->query("SELECT * FROM sms_logs LIMIT 3");
    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if (empty($logs)) {
        echo "No data in sms_logs table\n";
    } else {
        foreach ($logs as $log) {
            print_r($log);
        }
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
