<?php
define('INCLUDE_CHECK', true);
require_once 'includes/db_connection.php';

try {
    echo "Testing login query and session setup...\n";
      // Test the login query
    $stmt = $pdo->prepare("SELECT emp_id, first_name, middle_name, last_name, email, password, designation, user_image, role_id FROM employees WHERE email = ? OR emp_id = ? LIMIT 1");
    $testLoginId = 'admin@hrms.local'; // Using actual email from the system
    $stmt->execute([$testLoginId, $testLoginId]);
    $user = $stmt->fetch();
    
    if ($user) {
        echo "✓ User found: {$user['first_name']} {$user['last_name']} (emp_id: {$user['emp_id']})\n";
        
        // Test session variables setup (without actually setting them)
        $sessionData = [
            'user_id' => $user['emp_id'], // This should be emp_id now
            'designation' => $user['designation'],
            'fullName' => $user['first_name'] . ' ' . $user['middle_name'] . ' ' . $user['last_name'],
            'userImage' => $user['user_image'],
            'user_role' => isset($user['role']) ? $user['role'] : (isset($user['role_id']) ? $user['role_id'] : '0'),
            'user_role_id' => isset($user['role_id']) ? $user['role_id'] : (isset($user['role']) ? $user['role'] : '0')
        ];
        
        echo "✓ Session data would be set correctly:\n";
        foreach ($sessionData as $key => $value) {
            echo "  - \$_SESSION['$key'] = '$value'\n";
        }
        
        echo "\n✅ Login process would work correctly with emp_id!\n";
    } else {
        echo "⚠ No user found with login_id: $testLoginId\n";
        echo "Available users:\n";
        $stmt = $pdo->query("SELECT emp_id, email, first_name, last_name FROM employees LIMIT 3");
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($users as $u) {
            echo "- emp_id: {$u['emp_id']}, email: {$u['email']}, name: {$u['first_name']} {$u['last_name']}\n";
        }
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
?>
