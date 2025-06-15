<?php
// Define include check to allow config.php inclusion
if (!defined('INCLUDE_CHECK')) {
    define('INCLUDE_CHECK', true);
}

require_once 'includes/db_connection.php';

echo "=== Notifications Table Constraint Investigation ===\n";

try {
    // Check notifications table structure
    echo "1. Notifications table structure:\n";
    $stmt = $pdo->prepare("DESCRIBE notifications");
    $stmt->execute();
    $fields = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($fields as $field) {
        echo "   {$field['Field']} - {$field['Type']} - {$field['Key']}\n";
    }
    
    // Check foreign key constraints
    echo "\n2. Foreign key constraints on notifications:\n";
    $stmt = $pdo->prepare("
        SELECT 
            CONSTRAINT_NAME,
            COLUMN_NAME,
            REFERENCED_TABLE_NAME,
            REFERENCED_COLUMN_NAME
        FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE 
        WHERE TABLE_SCHEMA = DATABASE() 
        AND TABLE_NAME = 'notifications'
        AND REFERENCED_TABLE_NAME IS NOT NULL
    ");
    $stmt->execute();
    $constraints = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($constraints as $constraint) {
        echo "   {$constraint['CONSTRAINT_NAME']}: {$constraint['COLUMN_NAME']} -> {$constraint['REFERENCED_TABLE_NAME']}.{$constraint['REFERENCED_COLUMN_NAME']}\n";
    }
    
    // Check if users table exists and its structure
    echo "\n3. Users table check:\n";
    $stmt = $pdo->prepare("SHOW TABLES LIKE 'users'");
    $stmt->execute();
    if ($stmt->rowCount() > 0) {
        echo "   Users table exists\n";
        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM users");
        $stmt->execute();
        $count = $stmt->fetch()['count'];
        echo "   Users count: $count\n";
        
        // Check if employees have corresponding user records
        $stmt = $pdo->prepare("
            SELECT 
                e.id as emp_id, 
                e.user_id, 
                u.id as user_table_id
            FROM employees e 
            LEFT JOIN users u ON e.user_id = u.id 
            WHERE e.role_id = 1 
            LIMIT 5
        ");
        $stmt->execute();
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo "   Admin employees and their user mappings:\n";
        foreach ($results as $row) {
            echo "     Employee ID: {$row['emp_id']}, User ID: " . ($row['user_id'] ?? 'NULL') . ", User Table ID: " . ($row['user_table_id'] ?? 'NULL') . "\n";
        }
    } else {
        echo "   Users table does not exist\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

echo "\n=== Investigation Complete ===\n";
?>
