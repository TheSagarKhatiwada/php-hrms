<?php
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
            user_image = :userImage 
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
        ':userImage' => $targetFile
    ]);

    echo "Record updated successfully";

    // Redirect to the employees page to prevent form resubmission
    header("Location: employees.php");
    exit();
}
