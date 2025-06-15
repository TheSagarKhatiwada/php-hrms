<?php
// Test script to check database installation
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Define include check
define('INCLUDE_CHECK', true);

// Include the database installer
require_once __DIR__ . '/includes/DatabaseInstaller.php';

try {
    echo "Testing Database Installation...\n\n";
    
    $installer = new DatabaseInstaller();
      echo "1. Creating database if not exists...\n";
    $installer->createDatabase();
    echo "   ✓ Database creation completed\n\n";
    
    echo "2. Connecting to database...\n";
    $installer->connect();
    echo "   ✓ Database connection established\n\n";
    
    echo "3. Installing schema...\n";
    $result = $installer->installSchema();
    echo "   ✓ Schema installation completed\n\n";
      echo "4. Checking if settings table exists...\n";
    
    // Get database config
    require_once __DIR__ . '/includes/config.php';
    global $DB_CONFIG;
    $config = $DB_CONFIG;
    
    $pdo = new PDO(
        "mysql:host={$config['host']};dbname={$config['name']};charset={$config['charset']}",
        $config['user'],
        $config['pass'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    
    $stmt = $pdo->query("SHOW TABLES LIKE 'settings'");
    $exists = $stmt->rowCount() > 0;
    echo "   Settings table exists: " . ($exists ? "YES" : "NO") . "\n";
    
    if ($exists) {
        echo "\n5. Checking settings table structure...\n";
        $stmt = $pdo->query("DESCRIBE settings");
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($columns as $column) {
            echo "   - {$column['Field']} ({$column['Type']})\n";
        }
        
        echo "\n6. Testing settings table with sample data...\n";
        $stmt = $pdo->prepare("SELECT setting_key, value FROM settings LIMIT 5");
        $stmt->execute();
        $settings = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (count($settings) > 0) {
            echo "   Sample settings:\n";
            foreach ($settings as $setting) {
                echo "   - {$setting['setting_key']}: {$setting['value']}\n";
            }
        } else {
            echo "   No settings found in table\n";
        }
    }
    
    echo "\n✓ Database installation test completed successfully!\n";
    
} catch (Exception $e) {
    echo "✗ Error during installation test: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}
?>
