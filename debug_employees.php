<?php
// Define include check to allow config.php inclusion
if (!defined('INCLUDE_CHECK')) {
    define('INCLUDE_CHECK', true);
}

require_once 'includes/db_connection.php';

echo "=== Employee Data Investigation ===\n";

try {
    // Check all employees
    echo "1. All employees in database:\n";
    $stmt = $pdo->prepare("SELECT id, emp_id, user_id, CONCAT(first_name, ' ', last_name) as name, status FROM employees ORDER BY id");
    $stmt->execute();
    $employees = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($employees as $emp) {
        echo "   ID: {$emp['id']}, emp_id: '{$emp['emp_id']}', user_id: " . ($emp['user_id'] ?? 'NULL') . ", name: '{$emp['name']}', status: {$emp['status']}\n";
    }
    
    // Check what forms are sending
    echo "\n2. Checking attendance.php form to see what emp_id values are being sent:\n";
    
    // Check for any attendance forms or dropdowns
    if (file_exists('attendance.php')) {
        echo "   attendance.php exists - need to check form fields\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

echo "\n=== Investigation Complete ===\n";
?>
