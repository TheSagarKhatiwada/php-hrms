<?php
define('INCLUDE_CHECK', true);
require_once 'includes/db_connection.php';

// Simulate a basic session (required for holidays.php)
session_start();
$_SESSION['user_id'] = 'EMP001'; // emp_id format
$_SESSION['user_type'] = 'admin';

// Capture any output and errors
ob_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

try {
    echo "Testing holidays.php inclusion...\n";
    
    // Test the main query that was failing
    $currentYear = date('Y');
    $stmt = $pdo->prepare("SELECT h.*, b.name as branch_name 
                           FROM holidays h 
                           LEFT JOIN branches b ON h.branch_id = b.id 
                           WHERE YEAR(h.date) = ? OR h.is_recurring = 1
                           ORDER BY h.date ASC");
    $stmt->execute([$currentYear]);
    $holidays = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "✓ Main holidays query works - found " . count($holidays) . " holidays\n";
    
    // Test branches query
    $branchStmt = $pdo->query("SELECT id, name FROM branches ORDER BY name");
    $branches = $branchStmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "✓ Branches query works - found " . count($branches) . " branches\n";
    
    echo "✓ All holidays.php queries work correctly!\n";
    
} catch (Exception $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
}

$output = ob_get_clean();
echo $output;
?>
