<?php
// (Removed verbose debug logging previously writing POST data to debug_log.txt)
require_once '../../includes/session_config.php';
require_once '../../includes/db_connection.php';
require_once '../../includes/utilities.php';
require_once '../../includes/hierarchy_helpers.php';
require_once '../../includes/employee_profile_helpers.php';

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    // Collect and sanitize input
    $emp_id         = $_POST["emp_id"] ?? '';
    $machId         = trim($_POST['machId'] ?? '');
    $machIdNotApplicable = isset($_POST['mach_id_not_applicable']) ? 1 : 0;
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
    $work_start_time = trim($_POST['work_start_time'] ?? '');
    $work_end_time = trim($_POST['work_end_time'] ?? '');
    $allowWebAttendance = isset($_POST['allow_web_attendance']) ? 1 : 0;
    $fatherName = trim($_POST['father_name'] ?? '');
    $motherName = trim($_POST['mother_name'] ?? '');
    $spouseName = trim($_POST['spouse_name'] ?? '');
    $maritalStatus = trim($_POST['marital_status'] ?? '');
    $emergencyContactName = trim($_POST['emergency_contact_name'] ?? '');
    $emergencyContactRelationship = trim($_POST['emergency_contact_relationship'] ?? '');
    $emergencyContactPhone = trim($_POST['emergency_contact_phone'] ?? '');
    $emergencyContactEmail = trim($_POST['emergency_contact_email'] ?? '');
    $bloodGroup = trim($_POST['blood_group'] ?? '');
    $allergies = trim($_POST['allergies'] ?? '');
    $medicalConditions = trim($_POST['medical_conditions'] ?? '');
    $medicalNotes = trim($_POST['medical_notes'] ?? '');
    $currentAddress = trim($_POST['current_address'] ?? '');
    $currentCity = trim($_POST['current_city'] ?? '');
    $currentDistrict = trim($_POST['current_district'] ?? '');
    $currentState = trim($_POST['current_state'] ?? '');
    $currentPostalCode = trim($_POST['current_postal_code'] ?? '');
    $currentCountry = trim($_POST['current_country'] ?? '');
    $permanentAddress = trim($_POST['permanent_address'] ?? '');
    $permanentCity = trim($_POST['permanent_city'] ?? '');
    $permanentDistrict = trim($_POST['permanent_district'] ?? '');
    $permanentState = trim($_POST['permanent_state'] ?? '');
    $permanentPostalCode = trim($_POST['permanent_postal_code'] ?? '');
    $permanentCountry = trim($_POST['permanent_country'] ?? '');
    $academicRecords = collect_academic_records_from_request($_POST);
    $experienceRecords = collect_experience_records_from_request($_POST);
    if ($machIdNotApplicable) {
        $machId = '';
        $work_start_time = '';
        $work_end_time = '';
    }

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

    // Handle image upload - validate data URL mime & size and save with correct extension
    if ($croppedImage) {
        if (!preg_match('#^data:(image/[a-zA-Z0-9.+-]+);base64,#', $croppedImage, $m)) {
            redirect_with_message("edit-employee.php?id={$emp_id}", 'error', 'Invalid image data.');
        }

        $mime = strtolower($m[1]);
        $allowed = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp'
        ];

        if (!isset($allowed[$mime])) {
            redirect_with_message("edit-employee.php?id={$emp_id}", 'error', 'Unsupported image format. Allowed: JPG, PNG, WEBP.');
        }

        $imageData = base64_decode(preg_replace('#^data:image/\w+;base64,#i', '', $croppedImage));
        if ($imageData === false) {
            redirect_with_message("edit-employee.php?id={$emp_id}", 'error', 'Unable to decode uploaded image.');
        }

        $maxBytes = 5 * 1024 * 1024; // 5MB
        if (strlen($imageData) > $maxBytes) {
            redirect_with_message("edit-employee.php?id={$emp_id}", 'error', 'Cropped image exceeds 5MB maximum. Please upload a smaller image.');
        }

        $targetDir = "../../resources/userimg/uploads/";
        if (!is_dir($targetDir)) mkdir($targetDir, 0777, true);

        $ext = $allowed[$mime];
        $imageName = uniqid() . '.' . $ext;
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
        mach_id_not_applicable = :mach_id_not_applicable,
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
        work_start_time = :work_start_time,
        work_end_time = :work_end_time,
        supervisor_id = :supervisor_id,
        department_id = :department_id,
        allow_web_attendance = :allow_web_attendance,
        father_name = :father_name,
        mother_name = :mother_name,
        spouse_name = :spouse_name,
        marital_status = :marital_status,
        emergency_contact_name = :emergency_contact_name,
        emergency_contact_relationship = :emergency_contact_relationship,
        emergency_contact_phone = :emergency_contact_phone,
        emergency_contact_email = :emergency_contact_email,
        blood_group = :blood_group,
        allergies = :allergies,
        medical_conditions = :medical_conditions,
        medical_notes = :medical_notes,
        current_address = :current_address,
        current_city = :current_city,
        current_district = :current_district,
        current_state = :current_state,
        current_postal_code = :current_postal_code,
        current_country = :current_country,
        permanent_address = :permanent_address,
        permanent_city = :permanent_city,
        permanent_district = :permanent_district,
        permanent_state = :permanent_state,
        permanent_postal_code = :permanent_postal_code,
        permanent_country = :permanent_country
        WHERE emp_id = :emp_id";
    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
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
            ,':work_start_time' => ($machIdNotApplicable ? null : ($work_start_time !== '' ? $work_start_time : '09:30:00'))
            ,':work_end_time' => ($machIdNotApplicable ? null : ($work_end_time !== '' ? $work_end_time : '18:00:00'))
            ,':mach_id_not_applicable' => $machIdNotApplicable
            ,':allow_web_attendance' => $allowWebAttendance
            ,':father_name' => $fatherName ?: null
            ,':mother_name' => $motherName ?: null
            ,':spouse_name' => $spouseName ?: null
            ,':marital_status' => $maritalStatus ?: null
            ,':emergency_contact_name' => $emergencyContactName ?: null
            ,':emergency_contact_relationship' => $emergencyContactRelationship ?: null
            ,':emergency_contact_phone' => $emergencyContactPhone ?: null
            ,':emergency_contact_email' => $emergencyContactEmail ?: null
            ,':blood_group' => $bloodGroup ?: null
            ,':allergies' => $allergies ?: null
            ,':medical_conditions' => $medicalConditions ?: null
            ,':medical_notes' => $medicalNotes ?: null
            ,':current_address' => $currentAddress ?: null
            ,':current_city' => $currentCity ?: null
            ,':current_district' => $currentDistrict ?: null
            ,':current_state' => $currentState ?: null
            ,':current_postal_code' => $currentPostalCode ?: null
            ,':current_country' => $currentCountry ?: null
            ,':permanent_address' => $permanentAddress ?: null
            ,':permanent_city' => $permanentCity ?: null
            ,':permanent_district' => $permanentDistrict ?: null
            ,':permanent_state' => $permanentState ?: null
            ,':permanent_postal_code' => $permanentPostalCode ?: null
            ,':permanent_country' => $permanentCountry ?: null
        ]);

        sync_employee_academic_records($pdo, $emp_id, $academicRecords);
        sync_employee_experience_records($pdo, $emp_id, $experienceRecords);

            // Process per-user permission overrides (only for admins or users with override permissions)
            if ((is_admin() || has_permission('manage_permission_overrides') || has_permission('manage_user_permissions')) && isset($_POST['save_user_permissions'])) {
                $permOverrides = $_POST['perm_override'] ?? [];
                if (!is_array($permOverrides)) $permOverrides = [];

                $permissionIdLookup = [];
                try {
                    $permStmt = $pdo->query('SELECT id, name FROM permissions');
                    foreach ($permStmt->fetchAll(PDO::FETCH_ASSOC) as $permRow) {
                        $code = $permRow['name'] ?? null;
                        if ($code) {
                            $permissionIdLookup[$code] = (int)$permRow['id'];
                        }
                    }
                } catch (PDOException $e) {
                    $permissionIdLookup = [];
                }

                foreach ($permOverrides as $permKey => $value) {
                    $permId = null;
                    if (ctype_digit((string)$permKey)) {
                        $permId = (int)$permKey;
                    } elseif (isset($permissionIdLookup[$permKey])) {
                        $permId = $permissionIdLookup[$permKey];
                    }

                    if (!$permId) {
                        continue;
                    }

                    $normalized = is_string($value) ? strtolower($value) : $value;
                    if ($normalized === '' || $normalized === 'inherit') {
                        $del = $pdo->prepare('DELETE FROM user_permissions WHERE user_id = ? AND permission_id = ?');
                        $del->execute([$emp_id, $permId]);
                        continue;
                    }

                    if ($normalized === 'allow') {
                        $allowed = 1;
                    } elseif ($normalized === 'deny') {
                        $allowed = 0;
                    } else {
                        $allowed = ((string)$value === '1') ? 1 : 0;
                    }

                    $up = $pdo->prepare('INSERT INTO user_permissions (user_id, permission_id, allowed) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE allowed = VALUES(allowed)');
                    $up->execute([$emp_id, $permId, $allowed]);
                }

                // If we changed permissions for the logged-in user, clear cached permissions
                if (isset($_SESSION['user_id']) && (int)$_SESSION['user_id'] === (int)$emp_id) {
                    unset($_SESSION['permission_cache']);
                    if (isset($_SESSION['user_permission_cache']) && isset($_SESSION['user_permission_cache'][$emp_id])) {
                        unset($_SESSION['user_permission_cache'][$emp_id]);
                    }
                }

                // Touch the employee's permissions_updated_at so other sessions will refresh their cache
                try {
                    $touch = $pdo->prepare('UPDATE employees SET permissions_updated_at = NOW() WHERE emp_id = ?');
                    $touch->execute([$emp_id]);
                } catch (PDOException $e) {
                    // ignore; no blocking behavior
                }
            }

        $pdo->commit();
        $_SESSION['success'] = "Employee record updated successfully.";
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
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
