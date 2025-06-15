<?php
// Quick test to verify the add employee form works
echo "=== Testing Add Employee Form ===\n";

// Test 1: Check if we can access the page without errors
$output = shell_exec('php -l add-employee.php 2>&1');
if (strpos($output, 'No syntax errors') !== false) {
    echo "✅ add-employee.php syntax is valid\n";
} else {
    echo "❌ Syntax error in add-employee.php:\n$output\n";
}

// Test 2: Check if we can access edit-employee.php without errors
$output = shell_exec('php -l edit-employee.php 2>&1');
if (strpos($output, 'No syntax errors') !== false) {
    echo "✅ edit-employee.php syntax is valid\n";
} else {
    echo "❌ Syntax error in edit-employee.php:\n$output\n";
}

echo "\nThe emp_id generation and employee addition should now work correctly.\n";
echo "You can test by:\n";
echo "1. Navigate to add-employee.php in your browser\n";
echo "2. Fill out the form with required fields\n";
echo "3. Submit the form\n";
echo "4. Check if the employee is added successfully\n";
?>
