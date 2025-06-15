<?php
define('INCLUDE_CHECK', true);
require_once 'includes/db_connection.php';

try {
    echo "Checking leave_types table structure for missing columns:\n";
    $stmt = $pdo->query("DESCRIBE leave_types");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $existingColumns = array_column($columns, 'Field');
    
    echo "Current leave_types columns:\n";
    foreach ($columns as $column) {
        echo "- " . $column['Field'] . " (" . $column['Type'] . ")\n";
    }
    
    // Check for missing columns that are referenced in code
    $expectedColumns = ['is_paid', 'requires_approval', 'max_consecutive_days', 'min_notice_days'];
    $missingColumns = [];
    
    foreach ($expectedColumns as $expected) {
        if (!in_array($expected, $existingColumns)) {
            $missingColumns[] = $expected;
        }
    }
    
    if (!empty($missingColumns)) {
        echo "\nMissing columns found: " . implode(', ', $missingColumns) . "\n";
    } else {
        echo "\nAll expected columns are present.\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
