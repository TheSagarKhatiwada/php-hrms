<?php
// Test adding an employee directly to see what errors occur
require_once 'includes/db_connection.php';

// Test data
$testData = [
    'machId' => 'TEST001',
    'empBranch' => '1',
    'empFirstName' => 'Test',
    'empMiddleName' => '',
    'empLastName' => 'Employee',
    'gender' => 'M',
    'empEmail' => 'test@example.com',
    'empPhone' => '1234567890',
    'empJoinDate' => date('Y-m-d'),
    'designation' => '1',
    'loginAccess' => '1',
    'dob' => '1990-01-01',
    'role' => '4',
    'officeEmail' => '',
    'officePhone' => '',
    'supervisor_id' => null,
    'department_id' => null
];

echo "=== TESTING ADD EMPLOYEE LOGIC ===\n";

// Generate empID based on branch value and finding the next available number
$stmt = $pdo->prepare("SELECT emp_id FROM employees WHERE branch = ? ORDER BY emp_id DESC LIMIT 1");
$stmt->execute([$testData['empBranch']]);
$lastEmployee = $stmt->fetch();

if ($lastEmployee) {
    // Extract the number part and increment
    $lastId = $lastEmployee['emp_id'];
    $numberPart = (int)substr($lastId, strlen($testData['empBranch']));
    $nextNumber = $numberPart + 1;
    $empId = $testData['empBranch'] . str_pad($nextNumber, 2, '0', STR_PAD_LEFT);
} else {
    // First employee for this branch
    $empId = $testData['empBranch'] . '01';
}

echo "Generated emp_id: $empId\n";

// Test the INSERT statement
try {
    $sql = "INSERT INTO employees (emp_id, mach_id, branch, first_name, middle_name, last_name, gender, email, phone, hire_date, join_date, designation, login_access, user_image, date_of_birth, role_id, office_email, office_phone, supervisor_id, department_id)
            VALUES (:empId, :machId, :empBranch, :empFirstName, :empMiddleName, :empLastName, :gender, :empEmail, :empPhone, :hire_date, :empJoinDate, :designation, :loginAccess, :userImage, :date_of_birth, :role_id, :officeEmail, :officePhone, :supervisor_id, :department_id)";
    
    $stmt = $pdo->prepare($sql);
    $result = $stmt->execute([
        ':empId' => $empId,
        ':machId' => $testData['machId'],
        ':empBranch' => $testData['empBranch'],
        ':empFirstName' => $testData['empFirstName'],
        ':empMiddleName' => $testData['empMiddleName'],
        ':empLastName' => $testData['empLastName'],
        ':gender' => $testData['gender'],
        ':empEmail' => $testData['empEmail'],
        ':empPhone' => $testData['empPhone'],
        ':hire_date' => $testData['empJoinDate'],
        ':empJoinDate' => $testData['empJoinDate'],
        ':designation' => $testData['designation'],
        ':loginAccess' => $testData['loginAccess'],
        ':userImage' => 'resources/userimg/default-image.jpg',
        ':date_of_birth' => $testData['dob'],
        ':role_id' => $testData['role'],
        ':officeEmail' => $testData['officeEmail'],
        ':officePhone' => $testData['officePhone'],
        ':supervisor_id' => $testData['supervisor_id'],
        ':department_id' => $testData['department_id']
    ]);
    
    if ($result) {
        echo "✅ Employee added successfully with emp_id: $empId\n";
        
        // Clean up - remove the test employee
        $deleteStmt = $pdo->prepare("DELETE FROM employees WHERE emp_id = ?");
        $deleteStmt->execute([$empId]);
        echo "✅ Test employee cleaned up\n";
    } else {
        echo "❌ Failed to add employee\n";
        print_r($stmt->errorInfo());
    }
    
} catch (PDOException $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
?>
