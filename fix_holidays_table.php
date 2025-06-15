<?php
define('INCLUDE_CHECK', true);
require_once 'includes/db_connection.php';

try {
    echo "Adding missing columns to holidays table...\n";
    
    // Check if columns already exist
    $stmt = $pdo->query("DESCRIBE holidays");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $existingColumns = array_column($columns, 'Field');
    
    // Add is_recurring column if it doesn't exist
    if (!in_array('is_recurring', $existingColumns)) {
        $pdo->exec("ALTER TABLE holidays ADD COLUMN is_recurring TINYINT(1) DEFAULT 0 AFTER description");
        echo "Added is_recurring column\n";
    } else {
        echo "is_recurring column already exists\n";
    }
    
    // Add branch_id column if it doesn't exist
    if (!in_array('branch_id', $existingColumns)) {
        $pdo->exec("ALTER TABLE holidays ADD COLUMN branch_id BIGINT(20) UNSIGNED DEFAULT NULL AFTER is_recurring");
        echo "Added branch_id column\n";
        
        // Add index for branch_id
        $pdo->exec("ALTER TABLE holidays ADD INDEX idx_branch_id (branch_id)");
        echo "Added index for branch_id\n";
        
        // Add foreign key constraint
        $pdo->exec("ALTER TABLE holidays ADD CONSTRAINT holidays_ibfk_1 FOREIGN KEY (branch_id) REFERENCES branches (id) ON DELETE SET NULL");
        echo "Added foreign key constraint\n";
    } else {
        echo "branch_id column already exists\n";
    }
    
    echo "Successfully updated holidays table structure!\n";
    
    // Verify the new structure
    echo "\nUpdated holidays table columns:\n";
    $stmt = $pdo->query("DESCRIBE holidays");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($columns as $column) {
        echo "- " . $column['Field'] . " (" . $column['Type'] . ")\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
