<?php
require_once 'includes/db_connection.php';

echo "=== UPDATING NOTIFICATIONS TABLE TO USE EMP_ID ===\n";

try {
    // First, drop the foreign key constraint if it exists
    $pdo->query("ALTER TABLE notifications DROP FOREIGN KEY IF EXISTS fk_notifications_user_id");
    echo "✅ Dropped foreign key constraint (if existed)\n";
    
    // Add the new emp_id column
    $pdo->query("ALTER TABLE notifications ADD COLUMN emp_id VARCHAR(20) AFTER user_id");
    echo "✅ Added emp_id column\n";
    
    // Update existing records - this will set emp_id to NULL since we don't have a mapping
    // In a real scenario, you'd need to map user_id to emp_id if there was data
    echo "⚠️  Note: Existing notification records will have NULL emp_id (no data mapping available)\n";
    
    // Drop the old user_id column
    $pdo->query("ALTER TABLE notifications DROP COLUMN user_id");
    echo "✅ Dropped user_id column\n";
    
    // Add foreign key constraint for emp_id
    $pdo->query("ALTER TABLE notifications ADD CONSTRAINT fk_notifications_emp_id FOREIGN KEY (emp_id) REFERENCES employees(emp_id) ON DELETE CASCADE");
    echo "✅ Added foreign key constraint for emp_id\n";
    
    echo "\n=== NOTIFICATIONS TABLE UPDATED SUCCESSFULLY ===\n";
    
} catch (PDOException $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
?>
