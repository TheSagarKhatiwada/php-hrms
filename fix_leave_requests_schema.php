<?php
define('INCLUDE_CHECK', true);
require_once 'includes/db_connection.php';

try {
    echo "=== FIXING LEAVE_REQUESTS TABLE SCHEMA ===\n\n";
    
    // Check current structure
    echo "Current leave_requests table columns:\n";
    $stmt = $pdo->query("DESCRIBE leave_requests");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $existingColumns = array_column($columns, 'Field');
    
    foreach ($columns as $column) {
        echo "- " . $column['Field'] . " (" . $column['Type'] . ")\n";
    }
    
    echo "\nAdding missing columns...\n";
    
    // Add days_requested column (seems to be the same as total_days)
    if (!in_array('days_requested', $existingColumns)) {
        $pdo->exec("ALTER TABLE leave_requests ADD COLUMN days_requested INT(11) DEFAULT NULL AFTER total_days");
        echo "✓ Added days_requested column\n";
        
        // Copy data from total_days to days_requested for existing records
        $pdo->exec("UPDATE leave_requests SET days_requested = total_days WHERE days_requested IS NULL");
        echo "✓ Copied total_days to days_requested for existing records\n";
    } else {
        echo "✓ days_requested column already exists\n";
    }
    
    // Add half_day_period column
    if (!in_array('half_day_period', $existingColumns)) {
        $pdo->exec("ALTER TABLE leave_requests ADD COLUMN half_day_period ENUM('morning','afternoon') DEFAULT NULL AFTER is_half_day");
        echo "✓ Added half_day_period column\n";
    } else {
        echo "✓ half_day_period column already exists\n";
    }
    
    // Add applied_date column
    if (!in_array('applied_date', $existingColumns)) {
        $pdo->exec("ALTER TABLE leave_requests ADD COLUMN applied_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP AFTER half_day_period");
        echo "✓ Added applied_date column\n";
        
        // Set applied_date to created_at for existing records
        $pdo->exec("UPDATE leave_requests SET applied_date = created_at WHERE applied_date IS NULL");
        echo "✓ Set applied_date from created_at for existing records\n";
    } else {
        echo "✓ applied_date column already exists\n";
    }
    
    echo "\n=== VERIFICATION ===\n";
    echo "Updated leave_requests table columns:\n";
    $stmt = $pdo->query("DESCRIBE leave_requests");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($columns as $column) {
        echo "- " . $column['Field'] . " (" . $column['Type'] . ")\n";
    }
    
    echo "\n✅ Leave_requests table schema updated successfully!\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
?>
