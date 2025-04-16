<?php
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
        $_SESSION['error'] = "Invalid email or phone number";
        header('Location: edit-employee.php?id=' . $emp_id);
        exit();
    }

    // Fetch employee details
    $stmt = $pdo->prepare("SELECT * FROM employees WHERE emp_id = :emp_id");
    $stmt->execute(['emp_id' => $emp_id]);
    $employee = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$employee) {
        $_SESSION['error'] = "Employee not found";
        header('Location: employees.php');
        exit();
    }

    // Handle file upload
    if (isset($_FILES['empPhoto']) && $_FILES['empPhoto']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = 'resources/userimg/uploads/';
        if (!file_exists($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }
        
        $fileName = time() . '_' . basename($_FILES['empPhoto']['name']);
        $targetPath = $uploadDir . $fileName;
        
        if (move_uploaded_file($_FILES['empPhoto']['tmp_name'], $targetPath)) {
            $user_image = $targetPath;
            $_SESSION['success'] = "Image uploaded successfully";
        } else {
            $_SESSION['error'] = "Failed to upload image.";
            header("Location: edit-employee.php?emp_id=" . $emp_id);
            exit();
        }
    } else if (isset($_POST['croppedImage']) && !empty($_POST['croppedImage'])) {
        // Handle cropped image
        $uploadDir = 'resources/userimg/uploads/';
        if (!file_exists($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }
        
        $croppedImage = $_POST['croppedImage'];
        $imageData = base64_decode(preg_replace('#^data:image/\w+;base64,#i', '', $croppedImage));
        
        $fileName = time() . '_cropped.png';
        $targetPath = $uploadDir . $fileName;
        
        if (file_put_contents($targetPath, $imageData)) {
            $user_image = $targetPath;
            $_SESSION['success'] = "Image cropped and saved successfully";
        } else {
            $_SESSION['error'] = "Failed to save cropped image.";
            header("Location: edit-employee.php?emp_id=" . $emp_id);
            exit();
        }
    } else {
        // Keep existing image if no new image is uploaded
        $user_image = $employee['user_image'];
    }

    // Generate a random password if login access is granted
    if ($loginAccess == '1') {
        $randomPassword = bin2hex(random_bytes(4)); // Generate a random 8-character password
        $hashedPassword = password_hash($randomPassword, PASSWORD_BCRYPT); // Hash the password
    } else {
        $hashedPassword = $employee['password']; // Keep existing password
    }

    try {
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
            ':userImage' => $user_image,
            ':password' => $hashedPassword
        ]);

        $_SESSION['success'] = "Employee updated successfully";

        // Send email if login access is granted
        if ($loginAccess == '1') {
            $to = $empEmail;
            $subject = "Login Access Granted";
            $message = "Dear $empFirstName $empLastName,\n\nYour login access has been granted. You can now log in to the system using the following credentials:\n\nLogin ID: $empEmail\nPassword: $randomPassword\n\nPlease change your password after logging in for the first time.\n\nBest regards,\nHRMS Team";
            $headers = "From: no-reply@yourdomain.com";

            if (mail($to, $subject, $message, $headers)) {
                $_SESSION['success'] .= " and login credentials sent via email";
            } else {
                $_SESSION['warning'] = "Employee updated successfully but failed to send email";
            }
        }
    } catch (PDOException $e) {
        $_SESSION['error'] = "Error updating employee: " . $e->getMessage();
    }

    // Redirect to the employees page to prevent form resubmission
    header("Location: employees.php");
    exit();
}
