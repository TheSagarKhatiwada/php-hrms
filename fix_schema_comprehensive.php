<?php
define('INCLUDE_CHECK', true);
require_once 'includes/db_connection.php';

try {
    echo "=== COMPREHENSIVE SCHEMA FIXES ===\n\n";
    
    // Fix 1: Add color column to leave_types table
    echo "1. Checking leave_types table for color column...\n";
    $stmt = $pdo->query("DESCRIBE leave_types");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $existingColumns = array_column($columns, 'Field');
    
    if (!in_array('color', $existingColumns)) {
        $pdo->exec("ALTER TABLE leave_types ADD COLUMN color VARCHAR(7) DEFAULT '#007bff' AFTER description");
        echo "✓ Added color column to leave_types table\n";
    } else {
        echo "✓ Color column already exists in leave_types table\n";
    }
    
    // Fix 2: Check all foreign key references to employees
    echo "\n2. Checking and fixing employee references...\n";
    
    // Check leave_requests table
    $stmt = $pdo->query("DESCRIBE leave_requests");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($columns as $column) {
        if ($column['Field'] == 'employee_id') {
            echo "✓ leave_requests.employee_id exists (" . $column['Type'] . ")\n";
        }
        if ($column['Field'] == 'approved_by') {
            echo "✓ leave_requests.approved_by exists (" . $column['Type'] . ")\n";
        }
    }
    
    // Check branches table manager_id
    $stmt = $pdo->query("DESCRIBE branches");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($columns as $column) {
        if ($column['Field'] == 'manager_id') {
            echo "✓ branches.manager_id exists (" . $column['Type'] . ")\n";
        }
    }
    
    // Check employees table for any remaining id references
    echo "\n3. Employees table structure:\n";
    $stmt = $pdo->query("DESCRIBE employees");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($columns as $column) {
        if ($column['Key'] == 'PRI') {
            echo "✓ Primary key: " . $column['Field'] . " (" . $column['Type'] . ")\n";
        }
    }
    
    // Fix 3: Update leave_types with some default colors if there are any existing types
    echo "\n4. Setting default colors for existing leave types...\n";
    $colors = ['#007bff', '#28a745', '#ffc107', '#dc3545', '#6f42c1', '#fd7e14', '#20c997'];
    $stmt = $pdo->query("SELECT id, name FROM leave_types ORDER BY id");
    $leaveTypes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $colorIndex = 0;
    foreach ($leaveTypes as $type) {
        $color = $colors[$colorIndex % count($colors)];
        $updateStmt = $pdo->prepare("UPDATE leave_types SET color = ? WHERE id = ?");
        $updateStmt->execute([$color, $type['id']]);
        echo "✓ Set color {$color} for leave type: {$type['name']}\n";
        $colorIndex++;
    }
    
    echo "\n=== VERIFICATION ===\n";
    
    // Verify final leave_types structure
    echo "\nFinal leave_types table columns:\n";
    $stmt = $pdo->query("DESCRIBE leave_types");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($columns as $column) {
        echo "- " . $column['Field'] . " (" . $column['Type'] . ")\n";
    }
    
    echo "\n✅ All schema fixes completed successfully!\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
?>
