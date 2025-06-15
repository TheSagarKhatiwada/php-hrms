<?php
define('INCLUDE_CHECK', true);
require_once 'includes/db_connection.php';

try {
    echo "=== CHECKING EXISTING EMPLOYEES AND ID GENERATION ===\n\n";
    
    // Check existing employees
    echo "1. Current employees:\n";
    $stmt = $pdo->query("SELECT emp_id, first_name, last_name, branch FROM employees ORDER BY emp_id");
    $employees = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($employees as $employee) {
        echo "- {$employee['emp_id']}: {$employee['first_name']} {$employee['last_name']} (Branch: {$employee['branch']})\n";
    }
    
    // Check ID generation for each branch
    echo "\n2. Testing ID generation for each branch:\n";
    $stmt = $pdo->query("SELECT id, name FROM branches");
    $branches = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($branches as $branch) {
        $branchId = $branch['id'];
        $branchName = $branch['name'];
        
        // Current logic from add-employee.php
        $stmt = $pdo->prepare("SELECT COUNT(*) AS count FROM employees WHERE branch = :branch");
        $stmt->execute([':branch' => $branchId]);
        $row = $stmt->fetch();
        $count = $row['count'] + 1;
        $empId = $branchId . str_pad($count, 2, '0', STR_PAD_LEFT);
        
        echo "- Branch $branchId ($branchName): Count = {$row['count']}, Next ID would be = $empId\n";
        
        // Check if this ID already exists
        $stmt = $pdo->prepare("SELECT COUNT(*) as exists_count FROM employees WHERE emp_id = ?");
        $stmt->execute([$empId]);
        $existsResult = $stmt->fetch();
        if ($existsResult['exists_count'] > 0) {
            echo "  ⚠ WARNING: Employee ID $empId already exists!\n";
        }
    }
    
    // Show a better ID generation approach
    echo "\n3. Better ID generation approach:\n";
    foreach ($branches as $branch) {
        $branchId = $branch['id'];
        $branchName = $branch['name'];
        
        // Find the highest existing employee number for this branch
        $stmt = $pdo->prepare("SELECT emp_id FROM employees WHERE branch = ? ORDER BY emp_id DESC LIMIT 1");
        $stmt->execute([$branchId]);
        $lastEmployee = $stmt->fetch();
        
        if ($lastEmployee) {
            // Extract the number part and increment
            $lastId = $lastEmployee['emp_id'];
            $numberPart = (int)substr($lastId, strlen($branchId));
            $nextNumber = $numberPart + 1;
            $nextEmpId = $branchId . str_pad($nextNumber, 2, '0', STR_PAD_LEFT);
        } else {
            // First employee for this branch
            $nextEmpId = $branchId . '01';
        }
        
        echo "- Branch $branchId ($branchName): Last ID = " . ($lastEmployee['emp_id'] ?? 'none') . ", Next ID should be = $nextEmpId\n";
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
?>
