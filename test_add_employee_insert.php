<?php
define('INCLUDE_CHECK', true);
require_once 'includes/db_connection.php';

try {
    echo "Testing add employee INSERT statement...\n";
    
    // Test data
    $empId = "TEST99";
    $machId = "TEST999";
    $empBranch = 1;
    $empFirstName = "Test";
    $empMiddleName = "User";
    $empLastName = "Employee";
    $gender = "M";
    $empEmail = "test@test.com";
    $empPhone = "1234567890";
    $empJoinDate = "2025-06-15";
    $designation = 1;
    $loginAccess = 0;
    $targetFile = "resources/userimg/default-image.jpg";
    $dob = "1990-01-01";
    $role = 3;
    $officeEmail = "test.office@test.com";
    $officePhone = "0987654321";
    $supervisor_id = null;
    $department_id = 1;
    
    // Test the INSERT statement
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
        echo "✅ INSERT statement works correctly!\n";
        echo "Test employee added with emp_id: $empId\n";
        
        // Clean up - remove the test employee
        $pdo->prepare("DELETE FROM employees WHERE emp_id = ?")->execute([$empId]);
        echo "✅ Test employee cleaned up\n";
    } else {
        echo "❌ INSERT failed\n";
        print_r($stmt->errorInfo());
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
?>
