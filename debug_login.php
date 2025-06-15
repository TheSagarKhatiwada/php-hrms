<?php
session_start();

// Add debug mode
$debug = true;

if ($debug) {
    echo "<pre>DEBUG: Login attempt started\n";
}

try {
    require_once 'includes/config.php';
    require_once 'includes/db_connection.php';
    
    // Test the exact login flow
    $login_id = 'EMP001';  // or 'admin@hrms.local'
    $password = 'admin123';
    
    if ($debug) {
        echo "Login ID: $login_id\n";
        echo "Password: $password\n";
    }
    
    // Fetch user from the database (same as index.php)
    $stmt = $pdo->prepare("SELECT * FROM employees WHERE email = ? OR emp_id = ?");
    $stmt->execute([$login_id, $login_id]);
    $user = $stmt->fetch();
    
    if ($debug) {
        echo "User found: " . ($user ? 'YES' : 'NO') . "\n";
        if ($user) {
            echo "User ID: " . $user['id'] . "\n";
            echo "EMP ID: " . $user['emp_id'] . "\n";
            echo "Email: " . $user['email'] . "\n";
            echo "Login Access: " . $user['login_access'] . "\n";
            echo "Password hash starts with: " . substr($user['password'], 0, 10) . "\n";
        }
    }

    if ($user && password_verify($password, $user['password'])) {
        if ($debug) {
            echo "Password verification: SUCCESS\n";
            echo "Login access check: " . ($user['login_access'] == '1' ? 'ALLOWED' : 'DENIED') . "\n";
        }
        
        if ($user['login_access'] == '0') {
            echo "ERROR: Account is disabled\n";
        } else {
            echo "SUCCESS: Login should work!\n";
            echo "Role ID: " . $user['role_id'] . "\n";
            echo "Dashboard redirect: " . ($user['role_id'] == '1' ? 'admin-dashboard.php' : 'dashboard.php') . "\n";
        }
    } else {
        if ($debug) {
            echo "Password verification: FAILED\n";
            if ($user) {
                $manual_verify = password_verify($password, $user['password']);
                echo "Manual verification: " . ($manual_verify ? 'SUCCESS' : 'FAILED') . "\n";
            }
        }
        echo "ERROR: Invalid credentials\n";
    }
    
} catch (Exception $e) {
    echo "EXCEPTION: " . $e->getMessage() . "\n";
}

echo "</pre>";
?>
