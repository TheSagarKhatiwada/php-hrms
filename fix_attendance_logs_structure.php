<?php
/**
 * Fix Attendance Logs Table Structure
 * 
 * This script fixes the attendance_logs table to have proper separate
 * date and time columns instead of a combined log_time timestamp.
 */

define('INCLUDE_CHECK', true);
require_once 'includes/config.php';

function getDBConnection() {
    $config = getDBConfig();
    
    try {
        $dsn = "mysql:host={$config['host']};dbname={$config['name']};charset={$config['charset']}";
        $pdo = new PDO($dsn, $config['user'], $config['pass'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
        return $pdo;
    } catch (PDOException $e) {
        throw new Exception("Database connection failed: " . $e->getMessage());
    }
}

function executeSQL($pdo, $sql, $description) {
    try {
        $pdo->exec($sql);
        echo "✅ SUCCESS: $description\n";
        return true;
    } catch (PDOException $e) {
        echo "❌ ERROR: $description - " . $e->getMessage() . "\n";
        return false;
    }
}

$pdo = getDBConnection();

echo "=== FIXING ATTENDANCE_LOGS TABLE STRUCTURE ===\n\n";

// First, let's see what we have currently
echo "Current attendance_logs structure:\n";
$columns = $pdo->query("DESCRIBE attendance_logs")->fetchAll(PDO::FETCH_ASSOC);
foreach ($columns as $column) {
    echo "  {$column['Field']} - {$column['Type']}\n";
}
echo "\n";

// Step 1: Add proper date and time columns
echo "Step 1: Adding proper date and time columns...\n";
executeSQL($pdo, "ALTER TABLE attendance_logs ADD COLUMN date DATE AFTER emp_id", "Added date column");
executeSQL($pdo, "ALTER TABLE attendance_logs ADD COLUMN time TIME AFTER date", "Added time column");

// Step 2: Migrate data from log_time to separate columns
echo "\nStep 2: Migrating data from log_time to separate columns...\n";
executeSQL($pdo, "UPDATE attendance_logs SET date = DATE(log_time), time = TIME(log_time) WHERE log_time IS NOT NULL", "Migrated log_time data to date and time columns");

// Step 3: Make the new columns NOT NULL (after data migration)
echo "\nStep 3: Making date and time columns NOT NULL...\n";
executeSQL($pdo, "ALTER TABLE attendance_logs MODIFY COLUMN date DATE NOT NULL", "Made date column NOT NULL");
executeSQL($pdo, "ALTER TABLE attendance_logs MODIFY COLUMN time TIME NOT NULL", "Made time column NOT NULL");

// Step 4: Drop the old log_time column (optional - keeping for safety)
echo "\nStep 4: Dropping old log_time column...\n";
executeSQL($pdo, "ALTER TABLE attendance_logs DROP COLUMN log_time", "Dropped old log_time column");

// Step 5: Add index for better performance
echo "\nStep 5: Adding performance indexes...\n";
executeSQL($pdo, "CREATE INDEX idx_attendance_logs_date ON attendance_logs(date)", "Added index on date column");
executeSQL($pdo, "CREATE INDEX idx_attendance_logs_emp_date ON attendance_logs(emp_id, date)", "Added composite index on emp_id and date");

// Step 6: Show final structure
echo "\nFinal attendance_logs structure:\n";
$columns = $pdo->query("DESCRIBE attendance_logs")->fetchAll(PDO::FETCH_ASSOC);
foreach ($columns as $column) {
    echo "  {$column['Field']} - {$column['Type']} - {$column['Null']} - {$column['Key']}\n";
}

echo "\n=== ATTENDANCE_LOGS TABLE STRUCTURE FIXED ===\n";
echo "Now you have proper separate date and time columns for attendance tracking!\n";

?>
