<?php
define('INCLUDE_CHECK', true);
require_once 'includes/db_connection.php';

try {
    echo "=== FIXING LEAVE_TYPES TABLE SCHEMA ===\n\n";
    
    // Check current structure
    echo "Current leave_types table columns:\n";
    $stmt = $pdo->query("DESCRIBE leave_types");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $existingColumns = array_column($columns, 'Field');
    
    foreach ($columns as $column) {
        echo "- " . $column['Field'] . " (" . $column['Type'] . ")\n";
    }
    
    echo "\nAdding missing columns...\n";
    
    // Add is_paid column
    if (!in_array('is_paid', $existingColumns)) {
        $pdo->exec("ALTER TABLE leave_types ADD COLUMN is_paid TINYINT(1) DEFAULT 1 AFTER days_allowed");
        echo "✓ Added is_paid column\n";
    } else {
        echo "✓ is_paid column already exists\n";
    }
    
    // Add requires_approval column
    if (!in_array('requires_approval', $existingColumns)) {
        $pdo->exec("ALTER TABLE leave_types ADD COLUMN requires_approval TINYINT(1) DEFAULT 1 AFTER is_paid");
        echo "✓ Added requires_approval column\n";
    } else {
        echo "✓ requires_approval column already exists\n";
    }
    
    // Add max_consecutive_days column
    if (!in_array('max_consecutive_days', $existingColumns)) {
        $pdo->exec("ALTER TABLE leave_types ADD COLUMN max_consecutive_days INT(11) DEFAULT NULL AFTER requires_approval");
        echo "✓ Added max_consecutive_days column\n";
    } else {
        echo "✓ max_consecutive_days column already exists\n";
    }
    
    // Add min_notice_days column
    if (!in_array('min_notice_days', $existingColumns)) {
        $pdo->exec("ALTER TABLE leave_types ADD COLUMN min_notice_days INT(11) DEFAULT 0 AFTER max_consecutive_days");
        echo "✓ Added min_notice_days column\n";
    } else {
        echo "✓ min_notice_days column already exists\n";
    }
    
    // Check if we need to rename days_allowed to days_allowed_per_year (some code expects this)
    if (in_array('days_allowed', $existingColumns) && !in_array('days_allowed_per_year', $existingColumns)) {
        // Add days_allowed_per_year as alias/copy of days_allowed
        $pdo->exec("ALTER TABLE leave_types ADD COLUMN days_allowed_per_year INT(11) DEFAULT 0 AFTER description");
        $pdo->exec("UPDATE leave_types SET days_allowed_per_year = days_allowed");
        echo "✓ Added days_allowed_per_year column and copied data from days_allowed\n";
    }
    
    echo "\n=== VERIFICATION ===\n";
    echo "Updated leave_types table columns:\n";
    $stmt = $pdo->query("DESCRIBE leave_types");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($columns as $column) {
        echo "- " . $column['Field'] . " (" . $column['Type'] . ")\n";
    }
    
    // Set some default values for existing leave types
    echo "\nSetting default values for existing leave types...\n";
    $pdo->exec("UPDATE leave_types SET is_paid = 1 WHERE is_paid IS NULL");
    $pdo->exec("UPDATE leave_types SET requires_approval = 1 WHERE requires_approval IS NULL");
    $pdo->exec("UPDATE leave_types SET min_notice_days = 1 WHERE min_notice_days IS NULL");
    echo "✓ Set default values for new columns\n";
    
    echo "\n✅ Leave_types table schema updated successfully!\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
?>
