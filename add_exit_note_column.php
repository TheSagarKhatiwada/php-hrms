<?php
require_once 'includes/db_connection.php';

echo "=== ADDING EXIT_NOTE COLUMN TO EMPLOYEES TABLE ===\n";

try {
    // Add the exit_note column
    $pdo->query("ALTER TABLE employees ADD COLUMN exit_note TEXT AFTER exit_date");
    echo "✅ Added exit_note column to employees table\n";
    
    echo "\n=== UPDATED TABLE STRUCTURE ===\n";
    $stmt = $pdo->query("DESCRIBE employees");
    while ($row = $stmt->fetch()) {
        if (strpos($row['Field'], 'exit') !== false) {
            echo "Field: {$row['Field']}, Type: {$row['Type']}, Null: {$row['Null']}\n";
        }
    }
    
} catch (PDOException $e) {
    if (strpos($e->getMessage(), 'Duplicate column name') !== false) {
        echo "ℹ️  exit_note column already exists\n";
    } else {
        echo "❌ Error: " . $e->getMessage() . "\n";
    }
}
?>
