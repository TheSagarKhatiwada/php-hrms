<?php
// Test SMS tables and admin user installation
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Define include check
define('INCLUDE_CHECK', true);

// Include the database installer
require_once __DIR__ . '/includes/DatabaseInstaller.php';

try {
    echo "Testing Updated Database Installation with SMS Tables...\n\n";
    
    $installer = new DatabaseInstaller();
    
    echo "1. Creating database if not exists...\n";
    $installer->createDatabase();
    echo "   ✓ Database creation completed\n\n";
    
    echo "2. Connecting to database...\n";
    $installer->connect();
    echo "   ✓ Database connection established\n\n";
    
    echo "3. Installing updated schema with SMS tables...\n";
    $result = $installer->installSchema();
    echo "   ✓ Schema installation completed\n\n";
    
    echo "4. Checking SMS tables exist...\n";
    
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
    
    $sms_tables = ['sms_config', 'sms_logs', 'sms_templates', 'sms_campaigns', 'sms_sender_identities'];
    
    foreach ($sms_tables as $table) {
        $stmt = $pdo->query("SHOW TABLES LIKE '$table'");
        $exists = $stmt->rowCount() > 0;
        echo "   - $table: " . ($exists ? "EXISTS" : "MISSING") . "\n";
    }
    
    echo "\n5. Checking admin user exists...\n";
    $stmt = $pdo->query("SELECT id, username, email, first_name, last_name, role FROM users WHERE username = 'admin'");
    $admin = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($admin) {
        echo "   ✓ Admin user found:\n";
        echo "     - ID: {$admin['id']}\n";
        echo "     - Username: {$admin['username']}\n";
        echo "     - Email: {$admin['email']}\n";
        echo "     - Name: {$admin['first_name']} {$admin['last_name']}\n";
        echo "     - Role: {$admin['role']}\n";
    } else {
        echo "   ✗ Admin user not found\n";
    }
    
    echo "\n6. Checking employee record for admin...\n";
    $stmt = $pdo->query("SELECT emp_id, first_name, last_name, email, department_id FROM employees WHERE emp_id = 'EMP001'");
    $employee = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($employee) {
        echo "   ✓ Employee record found:\n";
        echo "     - Employee ID: {$employee['emp_id']}\n";
        echo "     - Name: {$employee['first_name']} {$employee['last_name']}\n";
        echo "     - Email: {$employee['email']}\n";
        echo "     - Department ID: {$employee['department_id']}\n";
    } else {
        echo "   ✗ Employee record not found\n";
    }
    
    echo "\n7. Checking SMS templates...\n";
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM sms_templates");
    $count = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "   SMS templates installed: {$count['count']}\n";
    
    if ($count['count'] > 0) {
        $stmt = $pdo->query("SELECT name, category FROM sms_templates LIMIT 3");
        $templates = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo "   Sample templates:\n";
        foreach ($templates as $template) {
            echo "     - {$template['name']} ({$template['category']})\n";
        }
    }
    
    echo "\n✓ Updated database installation test completed successfully!\n";
    echo "\nLogin credentials:\n";
    echo "Username: admin\n";
    echo "Password: admin123\n";
    echo "Email: admin@hrms.local\n";
    
} catch (Exception $e) {
    echo "✗ Error during installation test: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}
?>
