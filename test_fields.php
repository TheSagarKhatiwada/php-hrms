<?php
// Define include check to allow config.php inclusion
if (!defined('INCLUDE_CHECK')) {
    define('INCLUDE_CHECK', true);
}

require_once 'includes/db_connection.php';

echo "=== Employee Table Field Verification ===\n";

try {
    // Check table structure using PDO
    $query = "DESCRIBE employees";
    $stmt = $pdo->prepare($query);
    $stmt->execute();
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if ($results) {
        echo "Current employees table structure:\n";
        $fields = [];
        foreach ($results as $row) {
            $fields[] = $row['Field'];
            echo "  • {$row['Field']} - {$row['Type']}" . ($row['Null'] == 'YES' ? ' (nullable)' : ' (not null)') . "\n";
        }
        
        // Check for required fields
        $required_fields = ['office_email', 'office_phone', 'gender', 'date_of_birth'];
        echo "\nRequired field check:\n";
        foreach ($required_fields as $field) {
            if (in_array($field, $fields)) {
                echo "  ✓ $field - EXISTS\n";
            } else {
                echo "  ✗ $field - MISSING\n";
            }
        }
        
        // Test data retrieval
        echo "\nTesting employee data retrieval:\n";
        $test_query = "SELECT id, first_name, last_name, office_email, office_phone, gender, date_of_birth FROM employees LIMIT 1";
        $test_stmt = $pdo->prepare($test_query);
        $test_stmt->execute();
        $employee = $test_stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($employee) {
            echo "  Sample employee data:\n";
            foreach ($employee as $key => $value) {
                $display_value = $value ? $value : '(empty/null)';
                echo "    $key: $display_value\n";
            }
        } else {
            echo "  No employee data found\n";
        }
        
    } else {
        echo "Error describing table\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

echo "\n=== Test Completed ===\n";
?>
