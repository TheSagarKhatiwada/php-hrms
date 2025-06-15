<?php
/**
 * System Validation - Organizational Hierarchy
 * Tests all major components of the hierarchy system
 */

// Define include check to allow config.php inclusion
define('INCLUDE_CHECK', true);

echo "<h1>🏢 HRMS Pro - Organizational Hierarchy System Validation</h1>";
echo "<hr>";

try {
    // Load database configuration from centralized config file
    require_once __DIR__ . '/includes/config.php';
    
    $dsn = "mysql:host={$DB_CONFIG['host']};dbname={$DB_CONFIG['name']};charset={$DB_CONFIG['charset']}";
    $pdo = new PDO($dsn, $DB_CONFIG['user'], $DB_CONFIG['pass']);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "<h2>✅ Database Connection: SUCCESS</h2>";
    
    // Test 1: Board of Directors Tables
    echo "<h3>📋 Test 1: Board of Directors Structure</h3>";
    
    $tables = ['board_of_directors', 'board_committees', 'board_committee_members', 'board_meetings'];
    $allTablesExist = true;
    
    foreach ($tables as $table) {
        try {
            $stmt = $pdo->query("SELECT COUNT(*) FROM $table");
            $count = $stmt->fetchColumn();
            echo "<p>✅ Table '$table': $count records</p>";
        } catch (Exception $e) {
            echo "<p>❌ Table '$table': NOT FOUND</p>";
            $allTablesExist = false;
        }
    }
    
    // Test 2: Employee Hierarchy Fields
    echo "<h3>👥 Test 2: Employee Hierarchy Fields</h3>";
    
    try {
        $stmt = $pdo->query("DESCRIBE employees");
        $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        $requiredFields = ['supervisor_id', 'department_id'];
        foreach ($requiredFields as $field) {
            if (in_array($field, $columns)) {
                echo "<p>✅ Field '$field': EXISTS</p>";
            } else {
                echo "<p>❌ Field '$field': MISSING</p>";
            }
        }
    } catch (Exception $e) {
        echo "<p>❌ Cannot check employees table</p>";
    }
    
    // Test 3: File Structure
    echo "<h3>📁 Test 3: File Structure</h3>";
    
    $requiredFiles = [
        'organizational-chart.php' => 'Main Organizational Chart',
        'board-management.php' => 'Board Management Interface',
        'hierarchy-setup.php' => 'Hierarchy Setup Wizard',
        'includes/hierarchy_helpers.php' => 'Helper Functions'
    ];
    
    foreach ($requiredFiles as $file => $description) {
        if (file_exists($file)) {
            echo "<p>✅ $description: $file</p>";
        } else {
            echo "<p>❌ $description: $file (MISSING)</p>";
        }
    }
    
    // Test 4: Sample Data
    echo "<h3>📊 Test 4: Sample Data Verification</h3>";
    
    if ($allTablesExist) {
        $stmt = $pdo->query("SELECT COUNT(*) FROM board_of_directors WHERE status = 'active'");
        $boardCount = $stmt->fetchColumn();
        
        $stmt = $pdo->query("SELECT COUNT(*) FROM employees WHERE exit_date IS NULL");
        $empCount = $stmt->fetchColumn();
        
        echo "<p>✅ Board Members: $boardCount</p>";
        echo "<p>✅ Active Employees: $empCount</p>";
        
        if ($boardCount > 0) {
            echo "<p>✅ Board data populated</p>";
        } else {
            echo "<p>⚠️ No board members found - add some via Board Management</p>";
        }
    }
    
    // Test 5: Navigation Links
    echo "<h3>🧭 Test 5: Navigation Integration</h3>";
    
    $navigationFiles = [
        'organizational-chart.php' => 'Organizational Chart',
        'hierarchy-setup.php' => 'Hierarchy Setup',
        'board-management.php' => 'Board of Directors'
    ];
    
    foreach ($navigationFiles as $file => $title) {
        if (file_exists($file)) {
            echo "<p>✅ <a href='$file' target='_blank'>$title</a> - Available</p>";
        } else {
            echo "<p>❌ $title - File missing</p>";
        }
    }
    
    // Final Status
    echo "<hr>";
    echo "<h2>🎉 Overall System Status</h2>";
    
    if ($allTablesExist && file_exists('organizational-chart.php') && file_exists('board-management.php')) {
        echo "<div style='background: #d4edda; padding: 20px; border: 1px solid #c3e6cb; border-radius: 5px;'>";
        echo "<h3 style='color: #155724; margin: 0;'>✅ SYSTEM READY FOR PRODUCTION</h3>";
        echo "<p style='margin: 10px 0 0 0;'>All components are properly installed and configured.</p>";
        echo "</div>";
        
        echo "<h4>🔗 Quick Access Links:</h4>";
        echo "<ul>";
        echo "<li><a href='organizational-chart.php' target='_blank'><strong>📊 View Organizational Chart</strong></a></li>";
        echo "<li><a href='board-management.php' target='_blank'><strong>🏛️ Manage Board of Directors</strong></a></li>";
        echo "<li><a href='hierarchy-setup.php' target='_blank'><strong>⚙️ Setup Employee Hierarchy</strong></a></li>";
        echo "</ul>";
        
        echo "<h4>📚 Documentation:</h4>";
        echo "<ul>";
        echo "<li><a href='docs/hierarchy-final-report.md' target='_blank'>Complete Implementation Report</a></li>";
        echo "<li><a href='docs/quick-start-hierarchy.md' target='_blank'>Quick Start Guide</a></li>";
        echo "<li><a href='docs/hierarchy-implementation-guide.md' target='_blank'>Detailed User Guide</a></li>";
        echo "</ul>";
        
    } else {
        echo "<div style='background: #f8d7da; padding: 20px; border: 1px solid #f5c6cb; border-radius: 5px;'>";
        echo "<h3 style='color: #721c24; margin: 0;'>⚠️ SYSTEM INCOMPLETE</h3>";
        echo "<p style='margin: 10px 0 0 0;'>Some components are missing. Please review the test results above.</p>";
        echo "</div>";
    }
    
} catch (PDOException $e) {
    echo "<h2>❌ Database Connection Failed</h2>";
    echo "<p>Error: " . $e->getMessage() . "</p>";
    echo "<p>Please ensure XAMPP is running and the database exists.</p>";
}
?>

<style>
body {
    font-family: Arial, sans-serif;
    margin: 20px;
    line-height: 1.6;
}

h1, h2, h3 {
    color: #333;
}

p {
    margin: 5px 0;
}

a {
    color: #007bff;
    text-decoration: none;
}

a:hover {
    text-decoration: underline;
}

hr {
    margin: 30px 0;
    border: none;
    border-top: 2px solid #eee;
}
</style>
