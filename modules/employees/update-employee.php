<?php
// (Removed verbose debug logging previously writing POST data to debug_log.txt)
require_once '../../includes/session_config.php';
require_once '../../includes/db_connection.php';
require_once '../../includes/utilities.php';
require_once '../../includes/hierarchy_helpers.php';

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    // Collect and sanitize input
    $emp_id         = $_POST["emp_id"] ?? '';
    $machId         = trim($_POST['machId'] ?? '');
    $empBranchId    = trim($_POST['empBranchId'] ?? '');
    $empFirstName   = trim($_POST['empFirstName'] ?? '');
    $empMiddleName  = trim($_POST['empMiddleName'] ?? '');
    $empLastName    = trim($_POST['empLastName'] ?? '');
    $gender         = trim($_POST['gender'] ?? '');
    $empEmail       = trim($_POST['empEmail'] ?? '');
    $empPhone       = trim($_POST['empPhone'] ?? '');
    $empHireDate    = trim($_POST['empHireDate'] ?? '');
    $empJoinDate    = trim($_POST['empJoinDate'] ?? '');
    $designationId  = trim($_POST['designationId'] ?? '');
    $loginAccess    = trim($_POST['login_access'] ?? '');
    $croppedImage   = $_POST['croppedImage'] ?? '';
    $dob            = trim($_POST['dob'] ?? '');
    $role_id        = trim($_POST['role_id'] ?? '');
    $office_email   = trim($_POST['office_email'] ?? '');
    $office_phone   = trim($_POST['office_phone'] ?? '');
    $supervisor_id  = !empty($_POST['supervisor_id']) ? trim($_POST['supervisor_id']) : null;
    $department_id  = !empty($_POST['department_id']) ? trim($_POST['department_id']) : null;

    // Normalize and map gender like add-employee (form uses M/F, DB expects male/female)
    $gender = strtoupper($gender);
    if (!in_array($gender, ['M', 'F'], true)) {
        $gender = 'M';
    }
    $genderForDb = ($gender === 'F') ? 'female' : 'male';

    // Validate required fields
    if (!$emp_id || !$empFirstName || !$empLastName || !$empEmail || !$empPhone || !$empBranchId || !$designationId) {
        $_SESSION['error'] = "Missing required fields.";
        header("Location: edit-employee.php?id=$emp_id&_nocache=" . time());
        exit();
    }

    // Validate email and phone
    if (!filter_var($empEmail, FILTER_VALIDATE_EMAIL) || !preg_match('/^\+?[0-9]*$/', $empPhone)) {
        $_SESSION['error'] = "Invalid email or phone number.";
        header("Location: edit-employee.php?id=$emp_id&_nocache=" . time());
        exit();
    }

    // Validate hierarchy (no circular reference)
    if ($supervisor_id) {
        $stmt = $pdo->prepare("SELECT emp_id FROM employees WHERE emp_id = :emp_id");
        $stmt->execute(['emp_id' => $emp_id]);
        $current_employee = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($current_employee && !canSupervise($pdo, $supervisor_id, $current_employee['emp_id'])) {
            $_SESSION['error'] = "Cannot assign supervisor: This would create a circular hierarchy.";
            header("Location: edit-employee.php?id=$emp_id&_nocache=" . time());
            exit();
        }
    }

    // Fetch current employee data
    $stmt = $pdo->prepare("SELECT * FROM employees WHERE emp_id = :emp_id");
    $stmt->execute(['emp_id' => $emp_id]);
    $employee = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$employee) {
        $_SESSION['error'] = "Employee not found.";
        header("Location: employees.php");
        exit();
    }

    // Handle image upload
    if ($croppedImage) {
        $targetDir = "../../resources/userimg/uploads/";
        if (!is_dir($targetDir)) mkdir($targetDir, 0777, true);
        $imageData = base64_decode(preg_replace('#^data:image/\w+;base64,#i', '', $croppedImage));
        $imageName = uniqid() . '.png';
        $targetFile = $targetDir . $imageName;
        file_put_contents($targetFile, $imageData);
        $dbPath = "resources/userimg/uploads/" . $imageName;
    } else {
        $dbPath = $employee['user_image'];
    }

    // Password logic
    if ($loginAccess == '1' && $employee['login_access'] != '1') {
        $randomPassword = bin2hex(random_bytes(4));
        $hashedPassword = password_hash($randomPassword, PASSWORD_BCRYPT);
    } else {
        $hashedPassword = $employee['password'];
    }

    // Update employee
    $sql = "UPDATE employees SET
        mach_id = :machId,
        branch_id = :empBranchId,
        first_name = :empFirstName,
        middle_name = :empMiddleName,
        last_name = :empLastName,
        gender = :gender,
        email = :empEmail,
        phone = :empPhone,
        hire_date = :empHireDate,
        join_date = :empJoinDate,
        designation_id = :designationId,
        login_access = :loginAccess,
        user_image = :userImage,
        password = :password,
        date_of_birth = :date_of_birth,
        role_id = :role_id,
        office_email = :office_email,
        office_phone = :office_phone,
        supervisor_id = :supervisor_id,
        department_id = :department_id
        WHERE emp_id = :emp_id";
    try {
        $stmt = $pdo->prepare($sql);
        $result = $stmt->execute([
            ':emp_id' => $emp_id,
            ':machId' => ($machId !== '' ? (int)$machId : null),
            ':empBranchId' => $empBranchId,
            ':empFirstName' => $empFirstName,
            ':empMiddleName' => $empMiddleName,
            ':empLastName' => $empLastName,
            ':gender' => $genderForDb,
            ':empEmail' => $empEmail,
            ':empPhone' => $empPhone,
            ':empHireDate' => $empHireDate ?: null,
            ':empJoinDate' => $empJoinDate ?: null,
            ':designationId' => ($designationId !== '' ? (int)$designationId : null),
            ':loginAccess' => ($loginAccess !== '' ? (int)$loginAccess : 0),
            ':userImage' => $dbPath,
            ':password' => $hashedPassword,
            ':date_of_birth' => $dob ?: null,
            ':role_id' => ($role_id !== '' ? (int)$role_id : null),
            ':office_email' => ($office_email !== '' ? $office_email : null),
            ':office_phone' => ($office_phone !== '' ? $office_phone : null),
            ':supervisor_id' => $supervisor_id,
            ':department_id' => $department_id
        ]);
        if ($result) {
            $_SESSION['success'] = "Employee record updated successfully.";
        } else {
            $_SESSION['error'] = "No changes were made to the employee record.";
        }
    } catch (PDOException $e) {
        $_SESSION['error'] = "Error updating employee: " . $e->getMessage();
    }
    header("Location: employees.php?_nocache=" . time());
    exit();
} else {
    $_SESSION['error'] = "Invalid request method.";
    header("Location: employees.php");
    exit();
}
?>
