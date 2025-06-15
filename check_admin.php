<?php
try {
    require_once 'includes/config.php';
    require_once 'includes/db_connection.php';
    
    echo "=== Checking Admin Login ===\n";
    
    // Get the admin user
    $stmt = $pdo->prepare('SELECT emp_id, email, first_name, last_name, role_id, login_access, password FROM employees WHERE emp_id = ? OR email = ?');
    $stmt->execute(['EMP001', 'admin@hrms.local']);
    $admin = $stmt->fetch();
    
    if ($admin) {
        echo "Admin user found:\n";
        echo "EMP ID: " . $admin['emp_id'] . "\n";
        echo "Email: " . $admin['email'] . "\n";
        echo "Name: " . $admin['first_name'] . ' ' . $admin['last_name'] . "\n";
        echo "Role ID: " . $admin['role_id'] . "\n";
        echo "Login Access: " . $admin['login_access'] . "\n";
        echo "Password Hash: " . substr($admin['password'], 0, 30) . "...\n";
        
        // Test password verification
        $test_password = 'admin123';
        $verify_result = password_verify($test_password, $admin['password']);
        echo "Password verification for 'admin123': " . ($verify_result ? 'SUCCESS' : 'FAILED') . "\n";
        
        if (!$verify_result) {
            // Try some common passwords
            $common_passwords = ['admin', 'password', 'admin123', '123456', 'Admin123'];
            foreach ($common_passwords as $pwd) {
                if (password_verify($pwd, $admin['password'])) {
                    echo "Password verified with: '$pwd'\n";
                    break;
                }
            }
            
            // Create a new password hash for admin123
            echo "\nCreating new password hash for 'admin123'...\n";
            $new_hash = password_hash($test_password, PASSWORD_DEFAULT);
            echo "New hash: " . $new_hash . "\n";
            
            // Update the password in the database
            $update_stmt = $pdo->prepare('UPDATE employees SET password = ? WHERE emp_id = ?');
            $update_result = $update_stmt->execute([$new_hash, 'EMP001']);
            echo "Password update: " . ($update_result ? 'SUCCESS' : 'FAILED') . "\n";
            
            // Verify the new password
            $verify_new = password_verify($test_password, $new_hash);
            echo "New password verification: " . ($verify_new ? 'SUCCESS' : 'FAILED') . "\n";
        }
    } else {
        echo "Admin user not found!\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
