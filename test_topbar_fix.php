<?php
define('INCLUDE_CHECK', true);
require_once 'includes/db_connection.php';

// Simulate session with emp_id
session_start();
$_SESSION['user_id'] = '101'; // Using a known emp_id

try {
    echo "Testing topbar.php user lookup fix...\n";
    
    // Test the fixed query
    $user_id = $_SESSION['user_id'];
    $stmt = $pdo->prepare("SELECT * FROM employees WHERE emp_id = :emp_id");
    $stmt->execute(['emp_id' => $user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    $stmt->closeCursor();
    
    if ($user) {
        echo "✅ User lookup successful!\n";
        echo "Employee ID: " . $user['emp_id'] . "\n";
        echo "Name: " . $user['first_name'] . " " . $user['last_name'] . "\n";
    } else {
        echo "❌ No user found with emp_id: $user_id\n";
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
?>
