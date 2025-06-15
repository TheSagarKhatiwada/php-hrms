<?php
define('INCLUDE_CHECK', true);
require_once 'includes/db_connection.php';

try {
    echo "=== TESTING FULL ADD EMPLOYEE PROCESS ===\n\n";
    
    // Simulate form data like it would come from the actual form
    $machId = 'TEST001';
    $empBranch = 1; // Use a valid branch ID
    $empFirstName = 'Test';
    $empMiddleName = 'Middle';
    $empLastName = 'Employee';
    $gender = 'M';
    $empEmail = 'test@example.com';
    $empPhone = '1234567890';
    $empJoinDate = date('Y-m-d'); // Today's date
    $designation = 1; // Use a valid designation ID
    $loginAccess = 1;
    $targetFile = 'resources/userimg/default-image.jpg';
    $dob = '1990-01-01';
    $role = 1; // Use a valid role ID
    $officeEmail = 'test.office@example.com';
    $officePhone = '0987654321';
    $supervisor_id = null;
    $department_id = 1; // Use a valid department ID
    
    // Generate empID exactly like the add-employee.php does
    $stmt = $pdo->prepare("SELECT COUNT(*) AS count FROM employees WHERE branch = :branch");
    $stmt->execute([':branch' => $empBranch]);
    $row = $stmt->fetch();
    $count = $row['count'] + 1;
    $empId = $empBranch . str_pad($count, 2, '0', STR_PAD_LEFT);
    
    echo "Generated Employee ID: $empId\n";
    echo "Join Date: $empJoinDate\n";
    echo "Branch: $empBranch\n";
    echo "Designation: $designation\n";
    echo "Role: $role\n\n";
    
    // Test the exact INSERT statement from add-employee.php
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
        echo "✅ Employee INSERT successful!\n";
        echo "Employee ID: $empId\n";
        
        // Verify the inserted data
        $stmt = $pdo->prepare("SELECT * FROM employees WHERE emp_id = ?");
        $stmt->execute([$empId]);
        $employee = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($employee) {
            echo "\n✅ Employee data verified:\n";
            echo "- Name: {$employee['first_name']} {$employee['middle_name']} {$employee['last_name']}\n";
            echo "- Email: {$employee['email']}\n";
            echo "- Join Date: {$employee['join_date']}\n";
            echo "- Hire Date: {$employee['hire_date']}\n";
        }
        
        // Clean up test data
        $stmt = $pdo->prepare("DELETE FROM employees WHERE emp_id = ?");
        $stmt->execute([$empId]);
        echo "\n✅ Test employee cleaned up\n";
        
    } else {
        echo "❌ Employee INSERT failed\n";
        $errorInfo = $stmt->errorInfo();
        echo "Error: " . $errorInfo[2] . "\n";
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . " Line: " . $e->getLine() . "\n";
}
?>
