<?php
/**
 * Leave Module Database Migration Script
 * This script adds any missing fields or tables required for the Leave Module
 */

require_once '../../includes/session_config.php';
require_once '../../includes/db_connection.php';

// Function to check if column exists
function columnExists($table, $column, $pdo) {
    try {
        $stmt = $pdo->query("SHOW COLUMNS FROM `$table` LIKE '$column'");
        return $stmt->rowCount() > 0;
    } catch (PDOException $e) {
        return false;
    }
}

// Function to check if table exists
function tableExists($table, $pdo) {
    try {
        $stmt = $pdo->query("SHOW TABLES LIKE '$table'");
        return $stmt->rowCount() > 0;
    } catch (PDOException $e) {
        return false;
    }
}

// Start migration
echo "<h2>Leave Module Database Migration</h2>";
echo "<div style='background: #f8f9fa; padding: 20px; border-radius: 5px; font-family: monospace;'>";

$migrations_applied = 0;
$errors = [];

try {
    // Migration 1: Ensure leave_requests table has all required fields
    if (tableExists('leave_requests', $pdo)) {
        echo "✓ Table 'leave_requests' exists<br>";
        
        // Check for additional fields that might be missing
        $required_fields = [
            'is_half_day' => "ALTER TABLE leave_requests ADD COLUMN is_half_day TINYINT(1) DEFAULT 0",
            'half_day_period' => "ALTER TABLE leave_requests ADD COLUMN half_day_period ENUM('morning', 'afternoon') NULL",
            'attachment_path' => "ALTER TABLE leave_requests ADD COLUMN attachment_path VARCHAR(255) NULL",
            'reviewed_by' => "ALTER TABLE leave_requests ADD COLUMN reviewed_by INT NULL",
            'reviewed_date' => "ALTER TABLE leave_requests ADD COLUMN reviewed_date DATETIME NULL",
            'approval_comments' => "ALTER TABLE leave_requests ADD COLUMN approval_comments TEXT NULL",
            'rejection_reason' => "ALTER TABLE leave_requests ADD COLUMN rejection_reason TEXT NULL",
            'applied_date' => "ALTER TABLE leave_requests ADD COLUMN applied_date DATETIME DEFAULT CURRENT_TIMESTAMP",
            'deleted_at' => "ALTER TABLE leave_requests ADD COLUMN deleted_at DATETIME NULL"
        ];
        
        foreach ($required_fields as $field => $query) {
            if (!columnExists('leave_requests', $field, $pdo)) {
                try {
                    $pdo->exec($query);
                    echo "✓ Added column '$field' to leave_requests<br>";
                    $migrations_applied++;
                } catch (PDOException $e) {
                    $errors[] = "Failed to add column '$field': " . $e->getMessage();
                }
            } else {
                echo "- Column '$field' already exists<br>";
            }
        }    } else {
        // Create leave_requests table if it doesn't exist
        $create_table_query = "
            CREATE TABLE leave_requests (
                id INT AUTO_INCREMENT PRIMARY KEY,
                employee_id INT NOT NULL,
                leave_type_id INT NOT NULL,
                start_date DATE NOT NULL,
                end_date DATE NOT NULL,
                days_requested DECIMAL(4,1) NOT NULL,
                reason TEXT NOT NULL,
                status ENUM('pending', 'approved', 'rejected', 'cancelled') DEFAULT 'pending',
                is_half_day TINYINT(1) DEFAULT 0,
                half_day_period ENUM('morning', 'afternoon') NULL,
                attachment_path VARCHAR(255) NULL,
                reviewed_by INT NULL,
                reviewed_date DATETIME NULL,
                approval_comments TEXT NULL,
                rejection_reason TEXT NULL,
                applied_date DATETIME DEFAULT CURRENT_TIMESTAMP,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                deleted_at DATETIME NULL,
                FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
                FOREIGN KEY (leave_type_id) REFERENCES leave_types(id) ON DELETE CASCADE,
                FOREIGN KEY (reviewed_by) REFERENCES employees(id) ON DELETE SET NULL,
                INDEX idx_employee_date (employee_id, start_date),
                INDEX idx_status (status),
                INDEX idx_leave_type (leave_type_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ";
        
        try {
            $pdo->exec($create_table_query);
            echo "✓ Created table 'leave_requests'<br>";
            $migrations_applied++;
        } catch (PDOException $e) {
            $errors[] = "Failed to create table 'leave_requests': " . $e->getMessage();
        }
    }

    // Migration 2: Ensure leave_types table has all required fields
    if (tableExists('leave_types', $pdo)) {
        echo "✓ Table 'leave_types' exists<br>";
        
        $required_fields = [
            'color' => "ALTER TABLE leave_types ADD COLUMN color VARCHAR(7) DEFAULT '#007bff'",
            'requires_approval' => "ALTER TABLE leave_types ADD COLUMN requires_approval TINYINT(1) DEFAULT 1",
            'can_carry_forward' => "ALTER TABLE leave_types ADD COLUMN can_carry_forward TINYINT(1) DEFAULT 0",
            'max_carry_forward' => "ALTER TABLE leave_types ADD COLUMN max_carry_forward INT DEFAULT 0",
            'allow_half_day' => "ALTER TABLE leave_types ADD COLUMN allow_half_day TINYINT(1) DEFAULT 1",
            'max_consecutive_days' => "ALTER TABLE leave_types ADD COLUMN max_consecutive_days INT DEFAULT 30",
            'status' => "ALTER TABLE leave_types ADD COLUMN status ENUM('active', 'inactive') DEFAULT 'active'",
            'created_at' => "ALTER TABLE leave_types ADD COLUMN created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP",
            'updated_at' => "ALTER TABLE leave_types ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP"        ];
        
        foreach ($required_fields as $field => $query) {
            if (!columnExists('leave_types', $field, $pdo)) {
                try {
                    $pdo->exec($query);
                    echo "✓ Added column '$field' to leave_types<br>";
                    $migrations_applied++;
                } catch (PDOException $e) {
                    $errors[] = "Failed to add column '$field': " . $e->getMessage();
                }
            } else {
                echo "- Column '$field' already exists<br>";
            }
        }
    } else {
        // Create leave_types table if it doesn't exist
        $create_table_query = "
            CREATE TABLE leave_types (
                id INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(100) NOT NULL,
                code VARCHAR(20) UNIQUE NOT NULL,
                description TEXT NULL,
                days_allowed INT NOT NULL DEFAULT 0,
                color VARCHAR(7) DEFAULT '#007bff',
                requires_approval TINYINT(1) DEFAULT 1,
                can_carry_forward TINYINT(1) DEFAULT 0,
                max_carry_forward INT DEFAULT 0,
                allow_half_day TINYINT(1) DEFAULT 1,
                max_consecutive_days INT DEFAULT 30,
                status ENUM('active', 'inactive') DEFAULT 'active',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_status (status),
                INDEX idx_code (code)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ";
        
        try {
            $pdo->exec($create_table_query);
            echo "✓ Created table 'leave_types'<br>";
            $migrations_applied++;
        } catch (PDOException $e) {
            $errors[] = "Failed to create table 'leave_types': " . $e->getMessage();
        }
    }

    // Migration 3: Create leave_balances table if it doesn't exist
    if (!tableExists('leave_balances', $pdo)) {
        $create_table_query = "
            CREATE TABLE leave_balances (
                id INT AUTO_INCREMENT PRIMARY KEY,
                employee_id INT NOT NULL,
                leave_type_id INT NOT NULL,
                year YEAR NOT NULL,
                total_days DECIMAL(5,1) NOT NULL DEFAULT 0,
                used_days DECIMAL(5,1) NOT NULL DEFAULT 0,
                remaining_days DECIMAL(5,1) NOT NULL DEFAULT 0,
                carried_forward DECIMAL(5,1) NOT NULL DEFAULT 0,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
                FOREIGN KEY (leave_type_id) REFERENCES leave_types(id) ON DELETE CASCADE,
                UNIQUE KEY unique_employee_type_year (employee_id, leave_type_id, year),
                INDEX idx_employee_year (employee_id, year),
                INDEX idx_leave_type (leave_type_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ";
        
        try {
            $pdo->exec($create_table_query);
            echo "✓ Created table 'leave_balances'<br>";
            $migrations_applied++;        } catch (PDOException $e) {
            $errors[] = "Failed to create table 'leave_balances': " . $e->getMessage();
        }
    } else {
        echo "✓ Table 'leave_balances' exists<br>";
    }

    // Migration 4: Insert default leave types if table is empty
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM leave_types");
    $count = $stmt->fetch()['count'];
    
    if ($count == 0) {
        require_once 'config.php';
        
        foreach ($default_leave_types as $leave_type) {
            $stmt = $pdo->prepare("
                INSERT INTO leave_types 
                (name, code, days_allowed, color, requires_approval, can_carry_forward, max_carry_forward) 
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            
            if ($stmt->execute([
                $leave_type['name'],
                $leave_type['code'],
                $leave_type['days_allowed'],
                $leave_type['color'],
                $leave_type['requires_approval'],
                $leave_type['can_carry_forward'],
                $leave_type['max_carry_forward']
            ])) {
                echo "✓ Added default leave type: {$leave_type['name']}<br>";
                $migrations_applied++;
            } else {
                $errors[] = "Failed to add leave type '{$leave_type['name']}'";
            }
        }
    } else {
        echo "- Leave types already exist ($count types)<br>";
    }

    // Migration 5: Create indexes for better performance
    $indexes = [
        "CREATE INDEX idx_leave_requests_employee_status ON leave_requests(employee_id, status)",
        "CREATE INDEX idx_leave_requests_dates ON leave_requests(start_date, end_date)",
        "CREATE INDEX idx_leave_requests_created ON leave_requests(created_at)",
    ];
    
    foreach ($indexes as $index_query) {
        // Extract index name for checking
        preg_match('/CREATE INDEX (\w+)/', $index_query, $matches);
        $index_name = $matches[1] ?? '';
        
        // Check if index exists
        try {
            $stmt = $pdo->query("SHOW INDEX FROM leave_requests WHERE Key_name = '$index_name'");
            
            if ($stmt->rowCount() == 0) {
                $pdo->exec($index_query);
                echo "✓ Created index: $index_name<br>";
                $migrations_applied++;
            } else {
                echo "- Index '$index_name' already exists<br>";
            }
        } catch (PDOException $e) {
            // Index creation might fail if it already exists with different name
            echo "- Index creation skipped (may already exist)<br>";
        }
    }

} catch (PDOException $e) {
    $errors[] = "Migration error: " . $e->getMessage();
}

// Display results
echo "<br><hr><br>";
echo "<strong>Migration Summary:</strong><br>";
echo "✓ Migrations applied: $migrations_applied<br>";

if (!empty($errors)) {
    echo "❌ Errors encountered: " . count($errors) . "<br>";
    echo "<br><strong>Error Details:</strong><br>";
    foreach ($errors as $error) {
        echo "❌ $error<br>";
    }
} else {
    echo "✅ All migrations completed successfully!<br>";
}

echo "<br><strong>Next Steps:</strong><br>";
echo "1. Verify that all leave module files are in place<br>";
echo "2. Configure email settings in notifications.php<br>";
echo "3. Integrate navigation menus in your main HRMS header<br>";
echo "4. Test the leave module functionality<br>";
echo "5. Set up initial leave balances for employees<br>";

echo "</div>";

// Create logs directory if it doesn't exist
if (!is_dir('../../logs')) {
    mkdir('../../logs', 0755, true);
    echo "<br>✓ Created logs directory<br>";
}

// Create upload directory for leave attachments
if (!is_dir('../../uploads/leave')) {
    mkdir('../../uploads/leave', 0755, true);
    echo "✓ Created upload directory for leave attachments<br>";
}

echo "<br><p><strong>Migration completed at: " . date('Y-m-d H:i:s') . "</strong></p>";
?>
