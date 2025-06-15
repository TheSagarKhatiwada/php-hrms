<?php
define('INCLUDE_CHECK', true);
require_once 'includes/db_connection.php';

try {
    echo "Fixing branch_id column type to match branches.id...\n";
    
    // Drop the branch_id column and recreate it with the correct type
    $pdo->exec("ALTER TABLE holidays DROP COLUMN branch_id");
    echo "Dropped branch_id column\n";
    
    // Add branch_id with correct type (int(11) to match branches.id)
    $pdo->exec("ALTER TABLE holidays ADD COLUMN branch_id INT(11) DEFAULT NULL AFTER is_recurring");
    echo "Added branch_id column with correct type\n";
    
    // Add index for branch_id
    $pdo->exec("ALTER TABLE holidays ADD INDEX idx_branch_id (branch_id)");
    echo "Added index for branch_id\n";
    
    // Add foreign key constraint
    $pdo->exec("ALTER TABLE holidays ADD CONSTRAINT holidays_ibfk_1 FOREIGN KEY (branch_id) REFERENCES branches (id) ON DELETE SET NULL");
    echo "Added foreign key constraint\n";
    
    echo "Successfully fixed holidays table structure!\n";
    
    // Verify the final structure
    echo "\nFinal holidays table columns:\n";
    $stmt = $pdo->query("DESCRIBE holidays");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($columns as $column) {
        echo "- " . $column['Field'] . " (" . $column['Type'] . ")\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
