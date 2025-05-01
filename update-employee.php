<?php
// Include session configuration before starting any session
require_once 'includes/session_config.php';

session_start();
require 'includes/db_connection.php'; // Include database connection

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Get form data
    $emp_id = $_POST["emp_id"];
    $machId = $_POST['machId'];
    $empBranch = $_POST['empBranch'];
    $empFirstName = $_POST['empFirstName'];
    $empMiddleName = $_POST['empMiddleName'];
    $empLastName = $_POST['empLastName'];
    $gender = $_POST['gender'];
    $empEmail = filter_var($_POST['empEmail'], FILTER_VALIDATE_EMAIL) ? $_POST['empEmail'] : null;
    $empPhone = preg_match('/^\d+$/', $_POST['empPhone']) ? $_POST['empPhone'] : null;
    $empJoinDate = $_POST['empJoinDate'];
    $designation = $_POST['designation']; 
    $loginAccess = $_POST['login_access']; 
    $croppedImage = $_POST['croppedImage'];

    if (!$empEmail || !$empPhone) {
        die("Invalid email or phone number");
    }

    // Fetch employee details
    $stmt = $pdo->prepare("SELECT * FROM employees WHERE emp_id = :emp_id");
    $stmt->execute(['emp_id' => $emp_id]);
    $employee = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$employee) {
        die("Employee not found");
    }

    // Handle file upload
    if ($croppedImage) {
        $targetDir = "resources/userimg/uploads/";

        // Ensure directory exists
        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0777, true);
        }

        $imageData = base64_decode(preg_replace('#^data:image/\w+;base64,#i', '', $croppedImage));
        $imageName = uniqid() . '.png';
        $targetFile = $targetDir . $imageName;
        file_put_contents($targetFile, $imageData);
    } else {
        $targetFile = $employee['user_image']; // Keep existing image
    }

    // Generate a random password if login access is granted
    if ($loginAccess == '1') {
        $randomPassword = bin2hex(random_bytes(4)); // Generate a random 8-character password
        $hashedPassword = password_hash($randomPassword, PASSWORD_BCRYPT); // Hash the password
    } else {
        $hashedPassword = $employee['password']; // Keep existing password
    }

    // Update data in the database using prepared statements
    $sql = "UPDATE employees SET 
            mach_id = :machId, 
            branch = :empBranch, 
            first_name = :empFirstName, 
            middle_name = :empMiddleName, 
            last_name = :empLastName, 
            gender = :gender, 
            email = :empEmail, 
            phone = :empPhone, 
            join_date = :empJoinDate, 
            designation = :designation,
            login_access = :loginAccess,
            user_image = :userImage,
            password = :password
            WHERE emp_id = :emp_id";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':emp_id' => $emp_id,
        ':machId' => $machId,
        ':empBranch' => $empBranch,
        ':empFirstName' => $empFirstName,
        ':empMiddleName' => $empMiddleName,
        ':empLastName' => $empLastName,
        ':gender' => $gender,
        ':empEmail' => $empEmail,
        ':empPhone' => $empPhone,
        ':empJoinDate' => $empJoinDate,
        ':designation' => $designation,
        ':loginAccess' => $loginAccess,
        ':userImage' => $targetFile,
        ':password' => $hashedPassword
    ]);

    $_SESSION['success'] = "Employee record updated successfully";

    // Send email if login access is granted
    if ($loginAccess == '1') {
        $to = $empEmail;
        $subject = "Login Access Granted";
        $message = "Dear $empFirstName $empLastName,\n\nYour login access has been granted. You can now log in to the system using the following credentials:\n\nLogin ID: $empEmail\nPassword: $randomPassword\n\nPlease change your password after logging in for the first time.\n\nBest regards,\nHRMS Team";
        $headers = "From: no-reply@yourdomain.com";

        if (mail($to, $subject, $message, $headers)) {
            $_SESSION['success'] .= "\nLogin credentials have been sent to the employee's email.";
        } else {
            $_SESSION['error'] = "Failed to send login credentials email.";
        }
    }

    // Redirect to the employees page to prevent form resubmission
    header("Location: employees.php");
    exit();
}