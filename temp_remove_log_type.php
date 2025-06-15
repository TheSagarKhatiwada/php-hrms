<?php
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

$pdo = getDBConnection();

echo "=== REMOVING UNNECESSARY log_type COLUMN ===\n\n";

try {
    // Remove the log_type column as it's not needed for simple attendance tracking
    $sql = "ALTER TABLE attendance_logs DROP COLUMN log_type";
    $pdo->exec($sql);
    echo "✅ Removed log_type column from attendance_logs\n";
    
    // Show the updated table structure
    echo "\nUpdated attendance_logs table structure:\n";
    $columns = $pdo->query("DESCRIBE attendance_logs")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($columns as $column) {
        printf("  %-20s %-30s %-5s %-10s %s\n", 
            $column['Field'], 
            $column['Type'], 
            $column['Null'], 
            $column['Key'], 
            $column['Default'] ?: 'NULL'
        );
    }
    
} catch (PDOException $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}

echo "\n=== CLEANUP COMPLETED ===\n";
?>
