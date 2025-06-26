<?php
require_once '../../includes/session_config.php';
require_once '../../includes/db_connection.php';

header('Content-Type: text/plain');

try {
    echo "=== DATABASE CONNECTIVITY TEST ===\n\n";
    
    // Test basic connection
    $stmt = $pdo->query("SELECT VERSION() as version");
    $version = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "✅ MySQL Version: " . $version['version'] . "\n";
    
    // Check if tasks table exists
    $stmt = $pdo->query("SHOW TABLES LIKE 'tasks'");
    if ($stmt->rowCount() > 0) {
        echo "✅ Tasks table: EXISTS\n";
        
        // Show table structure
        echo "\n--- TASKS TABLE STRUCTURE ---\n";
        $stmt = $pdo->query("DESCRIBE tasks");
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            echo sprintf("%-20s %-15s %s\n", $row['Field'], $row['Type'], $row['Null'] === 'NO' ? 'NOT NULL' : 'NULL');
        }
    } else {
        echo "❌ Tasks table: NOT FOUND\n";
        echo "Creating tasks table...\n";
        
        $createTable = "
        CREATE TABLE IF NOT EXISTS tasks (
            id INT AUTO_INCREMENT PRIMARY KEY,
            title VARCHAR(255) NOT NULL,
            description TEXT,
            assigned_by INT NOT NULL,
            assigned_to INT NOT NULL,
            priority ENUM('low', 'medium', 'high', 'urgent') DEFAULT 'medium',
            status ENUM('pending', 'in_progress', 'completed', 'cancelled') DEFAULT 'pending',
            progress TINYINT DEFAULT 0,
            due_date DATE NULL,
            category VARCHAR(100) NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (assigned_by) REFERENCES employees(emp_id) ON DELETE CASCADE,
            FOREIGN KEY (assigned_to) REFERENCES employees(emp_id) ON DELETE CASCADE
        )";
        
        $pdo->exec($createTable);
        echo "✅ Tasks table created successfully\n";
    }
    
    // Check employees table
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM employees LIMIT 1");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "\n✅ Employees table accessible: " . $result['count'] . " records\n";
    
    // Test a sample employee
    $stmt = $pdo->query("SELECT emp_id, first_name, last_name FROM employees LIMIT 1");
    $employee = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($employee) {
        echo "✅ Sample employee: " . $employee['first_name'] . " " . $employee['last_name'] . " (ID: " . $employee['emp_id'] . ")\n";
    }
    
    echo "\n=== SESSION TEST ===\n";
    echo "Session status: " . (session_status() === PHP_SESSION_ACTIVE ? 'ACTIVE' : 'INACTIVE') . "\n";
    echo "Session ID: " . (session_id() ?: 'NOT SET') . "\n";
    
    // Mock session for testing
    if (!isset($_SESSION['user_id'])) {
        $_SESSION['user_id'] = $employee['emp_id'] ?? 1;
        echo "Mock user session set: " . $_SESSION['user_id'] . "\n";
    } else {
        echo "User session exists: " . $_SESSION['user_id'] . "\n";
    }
    
} catch (Exception $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}
?>
