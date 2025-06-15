<?php
require_once 'includes/db_connection.php';

echo "=== CHECKING FOREIGN KEY CONSTRAINTS ===\n";

// Check foreign key constraints on notifications table
$stmt = $pdo->query("SELECT CONSTRAINT_NAME, COLUMN_NAME, REFERENCED_TABLE_NAME, REFERENCED_COLUMN_NAME 
                     FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE 
                     WHERE TABLE_SCHEMA = DATABASE() 
                     AND TABLE_NAME = 'notifications' 
                     AND REFERENCED_TABLE_NAME IS NOT NULL");

$constraints = $stmt->fetchAll();

if (empty($constraints)) {
    echo "No foreign key constraints found on notifications table\n";
} else {
    echo "Found foreign key constraints:\n";
    foreach ($constraints as $constraint) {
        echo "- {$constraint['CONSTRAINT_NAME']}: {$constraint['COLUMN_NAME']} -> {$constraint['REFERENCED_TABLE_NAME']}.{$constraint['REFERENCED_COLUMN_NAME']}\n";
    }
}
?>
