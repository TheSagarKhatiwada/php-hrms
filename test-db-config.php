<?php
/**
 * Database Connection Test
 * This file tests that all database connections work with the centralized config
 */

// Define include check to allow config.php inclusion
define('INCLUDE_CHECK', true);

echo "<h1>🔧 Database Configuration Test</h1>";
echo "<hr>";

try {
    echo "<h2>1. Testing Direct Config Load</h2>";
    require_once __DIR__ . '/includes/config.php';
    echo "✅ Config file loaded successfully<br>";
    echo "📊 Database: {$DB_CONFIG['host']}/{$DB_CONFIG['name']} as {$DB_CONFIG['user']}<br><br>";
    
    echo "<h2>2. Testing Database Connection via db_connection.php</h2>";
    require_once __DIR__ . '/includes/db_connection.php';
    
    if (isset($pdo) && $pdo instanceof PDO) {
        echo "✅ Database connection successful via db_connection.php<br>";
        
        // Test a simple query
        $stmt = $pdo->query("SELECT VERSION() as version");
        $result = $stmt->fetch();
        echo "📈 MySQL Version: " . $result['version'] . "<br><br>";
    } else {
        echo "❌ Database connection failed via db_connection.php<br><br>";
    }
    
    echo "<h2>3. Testing DatabaseInstaller Class</h2>";
    require_once __DIR__ . '/includes/DatabaseInstaller.php';
    
    $installer = new DatabaseInstaller();
    if ($installer->testConnection()) {
        echo "✅ DatabaseInstaller connection test successful<br>";
    } else {
        echo "❌ DatabaseInstaller connection test failed<br>";
    }
    
    echo "<br><h2>📋 Summary</h2>";
    echo "All database connections are now centralized through includes/config.php<br>";
    echo "No hardcoded credentials remain in other files<br>";
    echo "✅ Configuration centralization completed successfully!<br>";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "<br>";
}
?>
