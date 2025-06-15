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

echo "=== CHECKING PERMISSIONS SYSTEM ===\n\n";

// Check if permissions table exists
$tables = $pdo->query("SHOW TABLES LIKE 'permissions'")->fetchAll();
if (empty($tables)) {
    echo "❌ No permissions table found\n";
} else {
    echo "✅ Permissions table exists\n";
    
    // Check what permissions exist
    $permissions = $pdo->query("SELECT name FROM permissions WHERE name LIKE '%report%'")->fetchAll();
    if (empty($permissions)) {
        echo "❌ No report-related permissions found\n";
        echo "Available permissions:\n";
        $allPermissions = $pdo->query("SELECT name FROM permissions LIMIT 10")->fetchAll();
        foreach ($allPermissions as $perm) {
            echo "  - {$perm['name']}\n";
        }
    } else {
        echo "✅ Report permissions found:\n";
        foreach ($permissions as $perm) {
            echo "  - {$perm['name']}\n";
        }
    }
}

// Check current user's role and permissions
echo "\nChecking current session:\n";
session_start();
if (isset($_SESSION['user_id'])) {
    echo "✅ User ID: {$_SESSION['user_id']}\n";
    if (isset($_SESSION['role'])) {
        echo "✅ Role: {$_SESSION['role']}\n";
    }
} else {
    echo "❌ No active session\n";
}

echo "\n=== RECOMMENDATION ===\n";
echo "Reports should be accessible to all logged-in users or admins.\n";
echo "We should either:\n";
echo "1. Create the missing permissions in database\n";
echo "2. Remove permission checks and only check if user is logged in\n";
echo "3. Only check if user is admin\n";
?>
