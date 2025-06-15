<?php
require_once 'includes/db_connection.php';

echo "=== FIXING NOTIFICATIONS TABLE TO USE EMP_ID ===\n";

try {
    // Drop the specific foreign key constraint
    $pdo->query("ALTER TABLE notifications DROP FOREIGN KEY notifications_user_id_foreign");
    echo "✅ Dropped foreign key constraint notifications_user_id_foreign\n";
    
    // Add the new emp_id column (if it doesn't exist)
    try {
        $pdo->query("ALTER TABLE notifications ADD COLUMN emp_id VARCHAR(20) AFTER user_id");
        echo "✅ Added emp_id column\n";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate column name') !== false) {
            echo "ℹ️  emp_id column already exists\n";
        } else {
            throw $e;
        }
    }
    
    // Drop the old user_id column
    $pdo->query("ALTER TABLE notifications DROP COLUMN user_id");
    echo "✅ Dropped user_id column\n";
    
    // Add foreign key constraint for emp_id
    $pdo->query("ALTER TABLE notifications ADD CONSTRAINT fk_notifications_emp_id FOREIGN KEY (emp_id) REFERENCES employees(emp_id) ON DELETE CASCADE");
    echo "✅ Added foreign key constraint for emp_id\n";
    
    echo "\n=== NOTIFICATIONS TABLE UPDATED SUCCESSFULLY ===\n";
    
    // Verify the table structure
    echo "\n=== NEW TABLE STRUCTURE ===\n";
    $stmt = $pdo->query('DESCRIBE notifications');
    while ($row = $stmt->fetch()) {
        echo "Field: {$row['Field']}, Type: {$row['Type']}, Null: {$row['Null']}, Key: {$row['Key']}\n";
    }
    
} catch (PDOException $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
?>
