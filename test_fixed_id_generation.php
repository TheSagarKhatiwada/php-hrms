<?php
define('INCLUDE_CHECK', true);
require_once 'includes/db_connection.php';

try {
    echo "=== TESTING FIXED ID GENERATION ===\n\n";
    
    // Test for branch 1 (should generate 104)
    $empBranch = 1;
    
    $stmt = $pdo->prepare("SELECT emp_id FROM employees WHERE branch = ? ORDER BY emp_id DESC LIMIT 1");
    $stmt->execute([$empBranch]);
    $lastEmployee = $stmt->fetch();
    
    if ($lastEmployee) {
        $lastId = $lastEmployee['emp_id'];
        $numberPart = (int)substr($lastId, strlen($empBranch));
        $nextNumber = $numberPart + 1;
        $empId = $empBranch . str_pad($nextNumber, 2, '0', STR_PAD_LEFT);
    } else {
        $empId = $empBranch . '01';
    }
    
    echo "Branch: $empBranch\n";
    echo "Last employee ID: " . ($lastEmployee['emp_id'] ?? 'none') . "\n";
    echo "Generated new ID: $empId\n";
    
    // Check if this ID already exists
    $stmt = $pdo->prepare("SELECT COUNT(*) as exists_count FROM employees WHERE emp_id = ?");
    $stmt->execute([$empId]);
    $existsResult = $stmt->fetch();
    
    if ($existsResult['exists_count'] > 0) {
        echo "❌ ERROR: Generated ID $empId already exists!\n";
    } else {
        echo "✅ Generated ID $empId is unique!\n";
    }
    
    // Now test the full add employee process with the fixed ID
    echo "\n=== TESTING FULL ADD EMPLOYEE WITH FIXED ID ===\n";
    
    $machId = 'TEST001';
    $empFirstName = 'Test';
    $empMiddleName = 'Middle';
    $empLastName = 'Employee';
    $gender = 'M';
    $empEmail = 'test@example.com';
    $empPhone = '1234567890';
    $empJoinDate = date('Y-m-d');
    $designation = 1;
    $loginAccess = 1;
    $targetFile = 'resources/userimg/default-image.jpg';
    $dob = '1990-01-01';
    $role = 1;
    $officeEmail = 'test.office@example.com';
    $officePhone = '0987654321';
    $supervisor_id = null;
    $department_id = 1;
    
    $sql = "INSERT INTO employees (emp_id, mach_id, branch, first_name, middle_name, last_name, gender, email, phone, hire_date, join_date, designation, login_access, user_image, date_of_birth, role_id, office_email, office_phone, supervisor_id, department_id)
            VALUES (:empId, :machId, :empBranch, :empFirstName, :empMiddleName, :empLastName, :gender, :empEmail, :empPhone, :hire_date, :empJoinDate, :designation, :loginAccess, :userImage, :date_of_birth, :role_id, :officeEmail, :officePhone, :supervisor_id, :department_id)";
    
    $stmt = $pdo->prepare($sql);
    $result = $stmt->execute([
        ':empId' => $empId,
        ':machId' => $machId,
        ':empBranch' => $empBranch,
        ':empFirstName' => $empFirstName,
        ':empMiddleName' => $empMiddleName,
        ':empLastName' => $empLastName,
        ':gender' => $gender,
        ':empEmail' => $empEmail,
        ':empPhone' => $empPhone,
        ':hire_date' => $empJoinDate,
        ':empJoinDate' => $empJoinDate,
        ':designation' => $designation,
        ':loginAccess' => $loginAccess,
        ':userImage' => $targetFile,
        ':date_of_birth' => $dob,
        ':role_id' => $role,
        ':officeEmail' => $officeEmail,
        ':officePhone' => $officePhone,
        ':supervisor_id' => $supervisor_id,
        ':department_id' => $department_id
    ]);
    
    if ($result) {
        echo "✅ Employee INSERT successful with ID: $empId\n";
        
        // Clean up
        $stmt = $pdo->prepare("DELETE FROM employees WHERE emp_id = ?");
        $stmt->execute([$empId]);
        echo "✅ Test employee cleaned up\n";
    } else {
        echo "❌ Employee INSERT failed\n";
        $errorInfo = $stmt->errorInfo();
        echo "Error: " . $errorInfo[2] . "\n";
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
?>
