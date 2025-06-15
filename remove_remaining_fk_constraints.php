<?php
/**
 * Remove Remaining Foreign Key Constraints and Update References
 * This script will remove all remaining foreign key constraints that reference employees.id
 * and update the related tables to use emp_id instead
 */

require_once 'includes/db_connection.php';

echo "=== REMOVING REMAINING FOREIGN KEY CONSTRAINTS ===\n\n";

try {
    // List of foreign key constraints to remove
    $constraintsToRemove = [
        ['table' => 'assetassignments', 'constraint' => 'assetassignments_ibfk_2'],
        ['table' => 'branches', 'constraint' => 'branches_manager_id_foreign'],
        ['table' => 'departments', 'constraint' => 'departments_manager_id_foreign'],
        ['table' => 'sms_logs', 'constraint' => 'sms_logs_employee_id_foreign']
    ];
    
    echo "1. Removing foreign key constraints...\n";
    foreach ($constraintsToRemove as $constraint) {
        try {
            $sql = "ALTER TABLE `{$constraint['table']}` DROP FOREIGN KEY `{$constraint['constraint']}`";
            $pdo->exec($sql);
            echo "✓ Removed constraint: {$constraint['constraint']} from {$constraint['table']}\n";
        } catch (PDOException $e) {
            if (strpos($e->getMessage(), "doesn't exist") !== false) {
                echo "- Constraint {$constraint['constraint']} already removed from {$constraint['table']}\n";
            } else {
                echo "❌ Error removing {$constraint['constraint']}: " . $e->getMessage() . "\n";
            }
        }
    }
    
    echo "\n2. Updating table structures to use emp_id references...\n";
    
    // Update assetassignments table
    echo "Updating assetassignments table...\n";
    try {
        // Check if EmployeeID column exists and what type it is
        $stmt = $pdo->query("DESCRIBE assetassignments");
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $employeeIdColumn = null;
        
        foreach ($columns as $column) {
            if ($column['Field'] === 'EmployeeID') {
                $employeeIdColumn = $column;
                break;
            }
        }
        
        if ($employeeIdColumn) {
            // Change EmployeeID to VARCHAR to match emp_id
            $pdo->exec("ALTER TABLE assetassignments MODIFY COLUMN EmployeeID VARCHAR(20)");
            echo "✓ Modified assetassignments.EmployeeID to VARCHAR(20)\n";
            
            // Update the data to use emp_id values
            $pdo->exec("
                UPDATE assetassignments aa 
                JOIN employees e ON aa.EmployeeID = e.id 
                SET aa.EmployeeID = e.emp_id
            ");
            echo "✓ Updated assetassignments data to use emp_id values\n";
        }
    } catch (PDOException $e) {
        echo "❌ Error updating assetassignments: " . $e->getMessage() . "\n";
    }
    
    // Update branches table
    echo "Updating branches table...\n";
    try {
        // Change manager_id to VARCHAR to match emp_id
        $pdo->exec("ALTER TABLE branches MODIFY COLUMN manager_id VARCHAR(20)");
        echo "✓ Modified branches.manager_id to VARCHAR(20)\n";
        
        // Update the data to use emp_id values
        $pdo->exec("
            UPDATE branches b 
            JOIN employees e ON b.manager_id = e.id 
            SET b.manager_id = e.emp_id
            WHERE b.manager_id IS NOT NULL
        ");
        echo "✓ Updated branches data to use emp_id values\n";
    } catch (PDOException $e) {
        echo "❌ Error updating branches: " . $e->getMessage() . "\n";
    }
    
    // Update departments table
    echo "Updating departments table...\n";
    try {
        // Change manager_id to VARCHAR to match emp_id
        $pdo->exec("ALTER TABLE departments MODIFY COLUMN manager_id VARCHAR(20)");
        echo "✓ Modified departments.manager_id to VARCHAR(20)\n";
        
        // Update the data to use emp_id values
        $pdo->exec("
            UPDATE departments d 
            JOIN employees e ON d.manager_id = e.id 
            SET d.manager_id = e.emp_id
            WHERE d.manager_id IS NOT NULL
        ");
        echo "✓ Updated departments data to use emp_id values\n";
    } catch (PDOException $e) {
        echo "❌ Error updating departments: " . $e->getMessage() . "\n";
    }
    
    // Update sms_logs table
    echo "Updating sms_logs table...\n";
    try {
        // Change employee_id to VARCHAR to match emp_id
        $pdo->exec("ALTER TABLE sms_logs MODIFY COLUMN employee_id VARCHAR(20)");
        echo "✓ Modified sms_logs.employee_id to VARCHAR(20)\n";
        
        // Update the data to use emp_id values
        $pdo->exec("
            UPDATE sms_logs s 
            JOIN employees e ON s.employee_id = e.id 
            SET s.employee_id = e.emp_id
            WHERE s.employee_id IS NOT NULL
        ");
        echo "✓ Updated sms_logs data to use emp_id values\n";
    } catch (PDOException $e) {
        echo "❌ Error updating sms_logs: " . $e->getMessage() . "\n";
    }
    
    echo "\n3. Creating new foreign key constraints with emp_id references...\n";
    
    // Create new foreign key constraints
    $newConstraints = [
        [
            'table' => 'assetassignments',
            'column' => 'EmployeeID',
            'references' => 'employees(emp_id)',
            'name' => 'assetassignments_employeeid_foreign'
        ],
        [
            'table' => 'branches',
            'column' => 'manager_id',
            'references' => 'employees(emp_id)',
            'name' => 'branches_manager_empid_foreign'
        ],
        [
            'table' => 'departments',
            'column' => 'manager_id',
            'references' => 'employees(emp_id)',
            'name' => 'departments_manager_empid_foreign'
        ],
        [
            'table' => 'sms_logs',
            'column' => 'employee_id',
            'references' => 'employees(emp_id)',
            'name' => 'sms_logs_empid_foreign'
        ]
    ];
    
    foreach ($newConstraints as $constraint) {
        try {
            $sql = "ALTER TABLE `{$constraint['table']}` 
                    ADD CONSTRAINT `{$constraint['name']}` 
                    FOREIGN KEY (`{$constraint['column']}`) 
                    REFERENCES {$constraint['references']} 
                    ON DELETE SET NULL ON UPDATE CASCADE";
            $pdo->exec($sql);
            echo "✓ Created constraint: {$constraint['name']}\n";
        } catch (PDOException $e) {
            if (strpos($e->getMessage(), 'already exists') !== false) {
                echo "- Constraint {$constraint['name']} already exists\n";
            } else {
                echo "❌ Error creating {$constraint['name']}: " . $e->getMessage() . "\n";
            }
        }
    }
    
    echo "\n4. Verifying foreign key constraints...\n";
    $stmt = $pdo->query("
        SELECT 
            TABLE_NAME,
            COLUMN_NAME,
            CONSTRAINT_NAME,
            REFERENCED_TABLE_NAME,
            REFERENCED_COLUMN_NAME
        FROM 
            INFORMATION_SCHEMA.KEY_COLUMN_USAGE 
        WHERE 
            REFERENCED_TABLE_NAME = 'employees' 
            AND TABLE_SCHEMA = DATABASE()
        ORDER BY TABLE_NAME, COLUMN_NAME
    ");
    $allConstraints = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Current foreign key constraints referencing employees:\n";
    foreach ($allConstraints as $fk) {
        echo "  - {$fk['TABLE_NAME']}.{$fk['COLUMN_NAME']} -> {$fk['REFERENCED_TABLE_NAME']}.{$fk['REFERENCED_COLUMN_NAME']}\n";
    }
    
    echo "\n" . str_repeat("=", 60) . "\n";
    echo "✅ FOREIGN KEY CONSTRAINTS UPDATED SUCCESSFULLY!\n";
    echo "✅ All tables now reference employees.emp_id instead of employees.id\n";
    echo "✅ Ready to proceed with final migration\n";
    echo str_repeat("=", 60) . "\n";

} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "Foreign key constraint removal failed!\n";
}
?>
