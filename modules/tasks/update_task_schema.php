<?php
require_once '../../includes/session_config.php';
require_once '../../includes/db_connection.php';

header('Content-Type: text/plain');

try {
    echo "=== MODIFYING TASK SYSTEM FOR SELF-ASSIGNMENT ===\n\n";
    
    // Add new columns to tasks table
    $alterQueries = [
        "ALTER TABLE tasks ADD COLUMN IF NOT EXISTS task_type ENUM('assigned', 'open', 'department') DEFAULT 'assigned'",
        "ALTER TABLE tasks ADD COLUMN IF NOT EXISTS target_department_id INT NULL",
        "ALTER TABLE tasks ADD COLUMN IF NOT EXISTS target_role_id INT NULL",
        "ALTER TABLE tasks MODIFY COLUMN assigned_to INT NULL",
        "ALTER TABLE tasks ADD COLUMN IF NOT EXISTS self_assigned_at TIMESTAMP NULL"
    ];
    
    foreach ($alterQueries as $query) {
        try {
            $pdo->exec($query);
            echo "✅ Executed: " . substr($query, 0, 50) . "...\n";
        } catch (Exception $e) {
            if (strpos($e->getMessage(), 'Duplicate column') === false) {
                echo "⚠️  Warning: " . $e->getMessage() . "\n";
            }
        }
    }
    
    // Add indexes for better performance
    $indexQueries = [
        "CREATE INDEX IF NOT EXISTS idx_tasks_type ON tasks(task_type)",
        "CREATE INDEX IF NOT EXISTS idx_tasks_department ON tasks(target_department_id)",
        "CREATE INDEX IF NOT EXISTS idx_tasks_role ON tasks(target_role_id)",
        "CREATE INDEX IF NOT EXISTS idx_tasks_creator ON tasks(assigned_by)"
    ];
    
    foreach ($indexQueries as $query) {
        try {
            $pdo->exec($query);
            echo "✅ Index created\n";
        } catch (Exception $e) {
            echo "⚠️  Index warning: " . $e->getMessage() . "\n";
        }
    }
    
    echo "\n=== TASK TABLE UPDATED SUCCESSFULLY ===\n";
    echo "New columns added:\n";
    echo "- task_type: 'assigned' (direct assignment), 'open' (anyone can assign), 'department' (department specific)\n";
    echo "- target_department_id: For department-specific tasks\n";
    echo "- target_role_id: For role-specific tasks\n";
    echo "- assigned_to: Now nullable for open/department tasks\n";
    echo "- self_assigned_at: Timestamp when user assigned task to themselves\n";
    
} catch (Exception $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n";
}
?>
