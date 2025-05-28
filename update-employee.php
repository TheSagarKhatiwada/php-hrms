<?php
// Include session configuration before starting any session
require_once 'includes/session_config.php';

session_start();
require 'includes/db_connection.php'; // Include database connection
require_once 'includes/utilities.php'; // Include utilities

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
    $dob = $_POST['dob'];
    $role_id = $_POST['role_id']; // Changed from role to role_id
    $office_email = filter_var($_POST['office_email'], FILTER_VALIDATE_EMAIL) ? $_POST['office_email'] : null;
    $office_phone = preg_match('/^\+?[0-9]*$/', $_POST['office_phone']) ? $_POST['office_phone'] : null;

    if (!$empEmail || !$empPhone) {
        $_SESSION['error'] = "Invalid email or phone number";
        header("Location: edit-employee.php?id=$emp_id&_nocache=" . time());
        exit();
    }

    // Fetch employee details
    $stmt = $pdo->prepare("SELECT * FROM employees WHERE emp_id = :emp_id");
    $stmt->execute(['emp_id' => $emp_id]);
    $employee = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$employee) {
        $_SESSION['error'] = "Employee not found";
        header("Location: employees.php");
        exit();
    }

    // Handle file upload
    if ($croppedImage) {
        $targetDir = "resources/userimg/uploads/";

        // Ensure directory exists
        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0777, true);
        }

        $imageData = base64_decode(preg_split('#^data:image/\w+;base64,#i', $croppedImage)[1]);
        $imageName = uniqid() . '.png';
        $targetFile = $targetDir . $imageName;
        file_put_contents($targetFile, $imageData);
    } else {
        $targetFile = $employee['user_image']; // Keep existing image
    }

    // Generate a random password if login access is granted and was not previously granted
    if ($loginAccess == '1' && $employee['login_access'] != '1') {
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
            password = :password,
            dob = :dob,
            role_id = :role_id,
            office_email = :office_email,
            office_phone = :office_phone
            WHERE emp_id = :emp_id";

    $stmt = $pdo->prepare($sql);
    
    try {
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
            ':password' => $hashedPassword,
            ':dob' => $dob,
            ':role_id' => $role_id, // Changed from role to role_id
            ':office_email' => $office_email,
            ':office_phone' => $office_phone
        ]);

        // Send notification to the employee about the profile update
        notify_employee($employee['id'], 'updated');
        
        // Track and notify for significant changes
        $changes = [];
        
        // Check for significant changes to track
        if ($employee['branch'] != $empBranch) {
            $changes[] = 'branch';
            
            // Get branch name
            $branchStmt = $pdo->prepare("SELECT name FROM branches WHERE id = :id");
            $branchStmt->execute([':id' => $empBranch]);
            $branchName = $branchStmt->fetchColumn() ?: 'Unknown Branch';
            
            notify_system(
                'Employee Branch Change', 
                "Employee $empFirstName $empLastName's branch has been updated to $branchName",
                'info'
            );
        }
        
        if ($employee['designation'] != $designation) {
            $changes[] = 'designation';
            
            // Get designation title
            $designationStmt = $pdo->prepare("SELECT title FROM designations WHERE id = :id");
            $designationStmt->execute([':id' => $designation]);
            $designationTitle = $designationStmt->fetchColumn() ?: 'Unknown Designation';
            
            // This is a significant change - notify HR/Management
            $adminStmt = $pdo->prepare("SELECT id FROM employees WHERE role_id = 1 OR role_id = 2"); // Changed from role to role_id
            $adminStmt->execute();
            $adminIds = $adminStmt->fetchAll(PDO::FETCH_COLUMN);
            
            if (!empty($adminIds)) {
                notify_users(
                    $adminIds,
                    'Employee Designation Change',
                    "Employee $empFirstName $empLastName's designation has been updated to $designationTitle",
                    'info',
                    'employees.php'
                );
            }
        }
        
        // If login access was granted, send notification
        if ($loginAccess == '1' && $employee['login_access'] != '1') {
            notify_employee($employee['id'], 'access_granted', [
                'access_type' => 'login'
            ]);
            
            // Notify admins
            notify_system(
                'Login Access Granted', 
                "Login access has been granted to employee $empFirstName $empLastName",
                'success'
            );
        }
        
        // If login access was revoked, send notification
        if ($loginAccess == '0' && $employee['login_access'] == '1') {
            notify_system(
                'Login Access Revoked', 
                "Login access has been revoked for employee $empFirstName $empLastName",
                'warning'
            );
        }

        $_SESSION['success'] = "Employee record updated successfully";

        // Send email if login access is granted and was not previously granted
        if ($loginAccess == '1' && $employee['login_access'] != '1') {
            // Include the mail helper file
            require_once 'includes/mail_helper.php';
            
            $to = $empEmail;
            $subject = "Login Access Granted";
            $message = "Dear $empFirstName $empLastName,\n\nYour login access has been granted. You can now log in to the system using the following credentials:\n\nLogin ID: $empEmail\nPassword: $randomPassword\n\nPlease change your password after logging in for the first time.\n\nBest regards,\nHRMS Team";
            
            if (send_email($to, $subject, $message, 'HRMS System')) {
                $_SESSION['success'] .= "\nLogin credentials have been sent to the employee's email.";
            } else {
                $_SESSION['error'] = "Failed to send login credentials email.";
            }
        }
    } catch (PDOException $e) {
        $_SESSION['error'] = "Error updating employee: " . $e->getMessage();
    }

    // Redirect to the employees page
    header("Location: employees.php?_nocache=" . time());
    exit();
}