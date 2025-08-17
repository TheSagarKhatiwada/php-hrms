<?php
require_once 'includes/db_connection.php';

try {
    echo "=== FIXING TASKS TABLE SCHEMA ===\n\n";
    
    // Check current schema
    $stmt = $pdo->query("DESCRIBE tasks");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Current schema:\n";
    foreach ($columns as $column) {
        if (in_array($column['Field'], ['assigned_by', 'assigned_to'])) {
            echo "  {$column['Field']} - {$column['Type']} - {$column['Null']}\n";
        }
    }
    
    echo "\nFixing data types to match employees.emp_id (VARCHAR(20))...\n";
    
    // First, let's check if there are any existing tasks
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM tasks");
    $taskCount = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    echo "Current tasks in database: $taskCount\n";
    
    if ($taskCount > 0) {
        echo "WARNING: There are existing tasks. This fix will clear them to avoid data corruption.\n";
        echo "Backing up existing tasks...\n";
        
        // Create backup table
        $pdo->exec("DROP TABLE IF EXISTS tasks_backup");
        $pdo->exec("CREATE TABLE tasks_backup AS SELECT * FROM tasks");
        echo "✅ Backup created in tasks_backup table\n";
        
        // Clear existing tasks
        $pdo->exec("DELETE FROM tasks");
        echo "✅ Existing tasks cleared\n";
    }
    
    // Modify the columns
    $alterQueries = [
        "ALTER TABLE tasks MODIFY COLUMN assigned_by VARCHAR(20) NOT NULL",
        "ALTER TABLE tasks MODIFY COLUMN assigned_to VARCHAR(20) NULL"
    ];
    
    foreach ($alterQueries as $query) {
        echo "Executing: $query\n";
        $pdo->exec($query);
        echo "✅ Success\n";
    }
    
    // Verify the changes
    echo "\nVerifying changes...\n";
    $stmt = $pdo->query("DESCRIBE tasks");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($columns as $column) {
        if (in_array($column['Field'], ['assigned_by', 'assigned_to'])) {
            echo "  {$column['Field']} - {$column['Type']} - {$column['Null']}\n";
        }
    }
    
    echo "\n✅ Tasks table schema fixed successfully!\n";
    echo "You can now create tasks without the 500 error.\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "Stack trace: " . $e->getTraceAsString() . "\n";
}
?>
