<?php
// Include session configuration and utilities
require_once '../../includes/session_config.php';
require_once '../../includes/utilities.php';
require_once '../../includes/employee_profile_helpers.php';

$page = 'Add Employee';
$page = 'employees';

// Use the standardized role check function
if (!is_admin() && get_user_role() === '0') {
    header('Location: ../../dashboard.php');
    exit();
}

include '../../includes/db_connection.php'; // Include the database connection file

// Get query parameters for repopulating form after errors
$machId = $_GET['machId'] ?? '';
$machIdNotApplicablePrefill = isset($_GET['mach_id_not_applicable']) ? (int)$_GET['mach_id_not_applicable'] : 0;
$empBranchId = $_GET['empBranchId'] ?? '';
$empFirstName = $_GET['empFirstName'] ?? '';
$empMiddleName = $_GET['empMiddleName'] ?? '';
$empLastName = $_GET['empLastName'] ?? '';
$gender = $_GET['gender'] ?? '';
$empEmail = $_GET['empEmail'] ?? '';
$empPhone = $_GET['empPhone'] ?? '';
$empHireDate = $_GET['empHireDate'] ?? '';
$empJoinDate = $_GET['empJoinDate'] ?? '';
$designationId = $_GET['designationId'] ?? '';
$loginAccess = $_GET['login_access'] ?? '';
$dob = $_GET['dob'] ?? '';
$role = $_GET['role'] ?? '';
$officeEmail = $_GET['office_email'] ?? '';
$officePhone = $_GET['office_phone'] ?? '';
$supervisor_id = $_GET['supervisor_id'] ?? '';
$department_id = $_GET['department_id'] ?? '';
$allowWebAttendancePrefill = isset($_GET['allow_web_attendance']) ? (int)$_GET['allow_web_attendance'] : 0;
$fatherName = $_GET['father_name'] ?? '';
$motherName = $_GET['mother_name'] ?? '';
$spouseName = $_GET['spouse_name'] ?? '';
$maritalStatus = $_GET['marital_status'] ?? '';
$emergencyContactName = $_GET['emergency_contact_name'] ?? '';
$emergencyContactRelationship = $_GET['emergency_contact_relationship'] ?? '';
$emergencyContactPhone = $_GET['emergency_contact_phone'] ?? '';
$emergencyContactEmail = $_GET['emergency_contact_email'] ?? '';
$bloodGroup = $_GET['blood_group'] ?? '';
$allergies = $_GET['allergies'] ?? '';
$medicalConditions = $_GET['medical_conditions'] ?? '';
$medicalNotes = $_GET['medical_notes'] ?? '';
$currentAddress = $_GET['current_address'] ?? '';
$currentCity = $_GET['current_city'] ?? '';
$currentDistrict = $_GET['current_district'] ?? '';
$currentState = $_GET['current_state'] ?? '';
$currentPostalCode = $_GET['current_postal_code'] ?? '';
$currentCountry = $_GET['current_country'] ?? 'Nepal';
$permanentAddress = $_GET['permanent_address'] ?? '';
$permanentCity = $_GET['permanent_city'] ?? '';
$permanentDistrict = $_GET['permanent_district'] ?? '';
$permanentState = $_GET['permanent_state'] ?? '';
$permanentPostalCode = $_GET['permanent_postal_code'] ?? '';
$permanentCountry = $_GET['permanent_country'] ?? 'Nepal';

$maritalStatusOptions = [
  'single' => 'Single',
  'married' => 'Married',
  'divorced' => 'Divorced',
  'widowed' => 'Widowed',
  'other' => 'Other'
];

$bloodGroupOptions = [
  'A+' => 'A+',
  'A-' => 'A-',
  'B+' => 'B+',
  'B-' => 'B-',
  'O+' => 'O+',
  'O-' => 'O-',
  'AB+' => 'AB+',
  'AB-' => 'AB-',
  'Unknown' => 'Unknown'
];

$relationshipOptions = [
  'Father',
  'Mother',
  'Spouse',
  'Sibling',
  'Child',
  'Relative',
  'Friend',
  'Guardian',
  'Other'
];

$districtRecords = [];
$provinceRecords = [];
$provinceIndex = [];
try {
  $provinceStmt = $pdo->query("SELECT province_id, province_name FROM provinces ORDER BY province_name");
  $provinceRecords = $provinceStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
  foreach ($provinceRecords as $provinceRow) {
    $provinceIndex[$provinceRow['province_id']] = $provinceRow['province_name'];
  }

  $districtStmt = $pdo->query("SELECT district_id, district_name, province_id, postal_code FROM districts ORDER BY district_name");
  $districtRecords = $districtStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch (Throwable $e) {
  $districtRecords = [];
  $provinceRecords = [];
  $provinceIndex = [];
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
  // Get form data and trim/validate
  $machId = trim($_POST['machId'] ?? '');
  $machIdNotApplicable = isset($_POST['mach_id_not_applicable']) ? 1 : 0;
  $empBranchId = trim($_POST['empBranchId'] ?? '');
  $empFirstName = trim($_POST['empFirstName'] ?? '');
  $empMiddleName = trim($_POST['empMiddleName'] ?? '');
  $empLastName = trim($_POST['empLastName'] ?? '');
  $gender = trim($_POST['gender'] ?? '');
  $empEmail = trim($_POST['empEmail'] ?? '');
  $empPhone = trim($_POST['empPhone'] ?? '');
  $empHireDate = trim($_POST['empHireDate'] ?? '');
  $empJoinDate = trim($_POST['empJoinDate'] ?? '');
  $workStartTime = trim($_POST['work_start_time'] ?? '');
  $workEndTime = trim($_POST['work_end_time'] ?? '');
  if ($machIdNotApplicable) {
      $machId = '';
      $workStartTime = '';
      $workEndTime = '';
  }
  $designationId = trim($_POST['designationId'] ?? '');
  $loginAccess = trim($_POST['login_access'] ?? '');
  $croppedImage = $_POST['croppedImage'] ?? '';
  $dob = trim($_POST['dob'] ?? '');
  $role = trim($_POST['role'] ?? '');
  $officeEmail = trim($_POST['office_email'] ?? '');
  $officePhone = trim($_POST['office_phone'] ?? '');
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
  
  // Hierarchy fields
  $supervisor_id = !empty($_POST['supervisor_id']) ? trim($_POST['supervisor_id']) : null;
  $department_id = !empty($_POST['department_id']) ? trim($_POST['department_id']) : null;
  $academicRecords = collect_academic_records_from_request($_POST);
  $experienceRecords = collect_experience_records_from_request($_POST);

  // Validate and clean gender field
  $gender = strtoupper(trim($gender)); // Ensure uppercase and no whitespace
  if (!in_array($gender, ['M', 'F'])) {
      $gender = 'M'; // Default to M if invalid
  }
  
  // Convert gender to database format (enum expects 'male', 'female', 'other')
  $genderForDb = '';
  switch($gender) {
      case 'M':
          $genderForDb = 'male';
          break;
      case 'F':
          $genderForDb = 'female';
          break;
      default:
          $genderForDb = 'male';
  }

    // Handle file upload
    if ($croppedImage) {
      $targetDir = "../../resources/userimg/uploads/";
      if (!is_dir($targetDir)) {
        mkdir($targetDir, 0777, true);
      }
      $imageData = base64_decode(preg_replace('#^data:image/\w+;base64,#i', '', $croppedImage));
      $imageName = uniqid() . '.png';
      $targetFile = $targetDir . $imageName;
      // Store relative path for database
      $dbPath = "resources/userimg/uploads/" . $imageName;
      file_put_contents($targetFile, $imageData);
    } else {
      $dbPath = "resources/userimg/default-image.jpg";
    }

  // Generate empID based on branch_id value and finding the next available number
  $stmt = $pdo->prepare("SELECT emp_id FROM employees WHERE branch_id = ? ORDER BY emp_id DESC LIMIT 1");
  $stmt->execute([$empBranchId]);
  $lastEmployee = $stmt->fetch();
  
  if ($lastEmployee) {
      $lastId = $lastEmployee['emp_id'];
      $numberPart = (int)substr($lastId, strlen($empBranchId));
      $nextNumber = $numberPart + 1;
      $empId = $empBranchId . str_pad($nextNumber, 2, '0', STR_PAD_LEFT);
  } else {
      $empId = $empBranchId . '01';
  }

  try {
    $pdo->beginTransaction();

    $sql = "INSERT INTO employees (emp_id, mach_id, branch_id, first_name, middle_name, last_name, gender, email, phone, hire_date, join_date, designation_id, login_access, allow_web_attendance, user_image, date_of_birth, role_id, office_email, office_phone, supervisor_id, department_id, work_start_time, work_end_time, mach_id_not_applicable, father_name, mother_name, spouse_name, marital_status, emergency_contact_name, emergency_contact_relationship, emergency_contact_phone, emergency_contact_email, blood_group, allergies, medical_conditions, medical_notes, current_address, current_city, current_district, current_state, current_postal_code, current_country, permanent_address, permanent_city, permanent_district, permanent_state, permanent_postal_code, permanent_country)
      VALUES (:empId, :machId, :empBranchId, :empFirstName, :empMiddleName, :empLastName, :gender, :empEmail, :empPhone, :hire_date, :empJoinDate, :designationId, :loginAccess, :allowWebAttendance, :userImage, :date_of_birth, :role_id, :officeEmail, :officePhone, :supervisor_id, :department_id, :workStartTime, :workEndTime, :machIdNotApplicable, :fatherName, :motherName, :spouseName, :maritalStatus, :emergencyContactName, :emergencyContactRelationship, :emergencyContactPhone, :emergencyContactEmail, :bloodGroup, :allergies, :medicalConditions, :medicalNotes, :currentAddress, :currentCity, :currentDistrict, :currentState, :currentPostalCode, :currentCountry, :permanentAddress, :permanentCity, :permanentDistrict, :permanentState, :permanentPostalCode, :permanentCountry)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':empId' => $empId,
        ':machId' => ($machId !== '' ? (int)$machId : null),
        ':empBranchId' => $empBranchId,
        ':empFirstName' => $empFirstName,
        ':empMiddleName' => $empMiddleName,
        ':empLastName' => $empLastName,
        ':gender' => $genderForDb,
        ':empEmail' => $empEmail,
        ':empPhone' => $empPhone,
        ':hire_date' => $empHireDate ?: null,
        ':empJoinDate' => $empJoinDate ?: null,
        ':designationId' => ($designationId !== '' ? (int)$designationId : null),
        ':loginAccess' => ($loginAccess !== '' ? (int)$loginAccess : 0),
        ':allowWebAttendance' => $allowWebAttendance,
        ':userImage' => $dbPath,
        ':date_of_birth' => $dob ?: null,
        ':role_id' => ($role !== '' ? (int)$role : null),
        ':officeEmail' => $officeEmail ?: null,
        ':officePhone' => $officePhone ?: null,
        ':supervisor_id' => $supervisor_id,
        ':department_id' => $department_id
      ,':workStartTime' => ($machIdNotApplicable ? null : ($workStartTime !== '' ? $workStartTime : '09:30:00'))
      ,':workEndTime' => ($machIdNotApplicable ? null : ($workEndTime !== '' ? $workEndTime : '18:00:00'))
      ,':machIdNotApplicable' => $machIdNotApplicable
      ,':fatherName' => $fatherName ?: null
      ,':motherName' => $motherName ?: null
      ,':spouseName' => $spouseName ?: null
      ,':maritalStatus' => $maritalStatus ?: null
      ,':emergencyContactName' => $emergencyContactName ?: null
      ,':emergencyContactRelationship' => $emergencyContactRelationship ?: null
      ,':emergencyContactPhone' => $emergencyContactPhone ?: null
      ,':emergencyContactEmail' => $emergencyContactEmail ?: null
      ,':bloodGroup' => $bloodGroup ?: null
      ,':allergies' => $allergies ?: null
      ,':medicalConditions' => $medicalConditions ?: null
      ,':medicalNotes' => $medicalNotes ?: null
      ,':currentAddress' => $currentAddress ?: null
      ,':currentCity' => $currentCity ?: null
      ,':currentDistrict' => $currentDistrict ?: null
      ,':currentState' => $currentState ?: null
      ,':currentPostalCode' => $currentPostalCode ?: null
      ,':currentCountry' => $currentCountry ?: null
      ,':permanentAddress' => $permanentAddress ?: null
      ,':permanentCity' => $permanentCity ?: null
      ,':permanentDistrict' => $permanentDistrict ?: null
      ,':permanentState' => $permanentState ?: null
      ,':permanentPostalCode' => $permanentPostalCode ?: null
      ,':permanentCountry' => $permanentCountry ?: null
    ]);

    sync_employee_academic_records($pdo, $empId, $academicRecords);
    sync_employee_experience_records($pdo, $empId, $experienceRecords);

    $pdo->commit();

    $newEmployeeId = $empId;

    try {
        $sql = "UPDATE attendance_logs a 
                JOIN employees e ON a.mach_id = e.mach_id 
                SET a.emp_Id = e.emp_id 
                WHERE a.mach_id IS NOT NULL 
                AND e.mach_id IS NOT NULL 
                AND a.mach_id > 0 
                AND e.mach_id > 0";
        $stmt = $pdo->prepare($sql);
        $stmt->execute();
    } catch (PDOException $e) {
        error_log("Warning: Could not update attendance_logs: " . $e->getMessage());
    }

    if ($loginAccess == '1' && $newEmployeeId) {
        notify_employee($newEmployeeId, 'joined');
        
        $fullName = $empFirstName . ' ' . ($empMiddleName ? $empMiddleName . ' ' : '') . $empLastName;
        notify_system(
            'New Employee Added', 
            "A new employee ($fullName) has been added to the system with Employee ID: $empId",
            'success',
            true
        );
        
        $stmt = $pdo->prepare("SELECT emp_id FROM employees WHERE role_id = 1 OR role_id = 2");
        $stmt->execute();
        $adminIds = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        if (!empty($adminIds)) {
            notify_users(
                $adminIds,
                'New Employee Added',
                "Employee $fullName ($empId) has been added to the system",
                'info',
                'employees.php'
            );
        }
    }

    $_SESSION['success'] = "Employee added successfully!";
  } catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $errorMessage = "Error adding employee: " . $e->getMessage();
    $_SESSION['error'] = $errorMessage;
    error_log("PDO Exception in add-employee.php: " . $e->getMessage());
    error_log("PDO Error Code: " . $e->getCode());
    error_log("Stack trace: " . $e->getTraceAsString());
  } catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $errorMessage = "General error adding employee: " . $e->getMessage();
    $_SESSION['error'] = $errorMessage;
    error_log("General Exception in add-employee.php: " . $e->getMessage());
  }

  // Redirect to the employees page
  header("Location: employees.php?_nocache=" . time());
  exit();
}

require_once __DIR__ . '/../../includes/header.php';
?>

<!-- Main content -->
<div class="container-fluid p-4">
  <!-- Page header -->
  <div class="d-flex justify-content-between align-items-center mb-4">
    <div>
      <h1 class="fs-2 fw-bold mb-1">Add New Employee</h1>
    </div>
    <a href="employees.php" class="btn btn-outline-secondary">
      <i class="fas fa-arrow-left me-2"></i> Back to Employees
    </a>
  </div>
  
  <!-- Employee Add Card -->
  <div class="card border-0 shadow-sm">
    <div class="card-body">
      <form id="addEmployeeForm" method="POST" action="add-employee.php" enctype="multipart/form-data">
        <div class="row g-4">
          <div class="col-md-8">
            <ul class="nav nav-tabs nav-fill" id="employeeFormTabs" role="tablist">
              <li class="nav-item" role="presentation">
                <button class="nav-link active" id="personal-tab" data-bs-toggle="tab" data-bs-target="#tab-personal" type="button" role="tab" aria-controls="tab-personal" aria-selected="true">
                  <i class="fas fa-user me-1"></i>Personal Info
                </button>
              </li>
              <li class="nav-item" role="presentation">
                <button class="nav-link" id="academic-tab" data-bs-toggle="tab" data-bs-target="#tab-academic" type="button" role="tab" aria-controls="tab-academic" aria-selected="false">
                  <i class="fas fa-graduation-cap me-1"></i>Academic
                </button>
              </li>
              <li class="nav-item" role="presentation">
                <button class="nav-link" id="experience-tab" data-bs-toggle="tab" data-bs-target="#tab-experience" type="button" role="tab" aria-controls="tab-experience" aria-selected="false">
                  <i class="fas fa-briefcase me-1"></i>Experience
                </button>
              </li>
              <li class="nav-item" role="presentation">
                <button class="nav-link" id="assignment-tab" data-bs-toggle="tab" data-bs-target="#tab-assignment" type="button" role="tab" aria-controls="tab-assignment" aria-selected="false">
                  <i class="fas fa-tasks me-1"></i>Assigned Details
                </button>
              </li>
            </ul>

            <div class="tab-content border border-top-0 rounded-bottom p-4">
              <div class="tab-pane fade show active" id="tab-personal" role="tabpanel" aria-labelledby="personal-tab">
                <h5 class="fw-semibold mb-3">Personal Information</h5>
                <div class="row gy-3 gx-2">
                  <div class="col-md-4">
                    <label for="empFirstName" class="form-label">First Name <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="empFirstName" name="empFirstName" required value="<?php echo htmlspecialchars($empFirstName); ?>">
                  </div>
                  <div class="col-md-4">
                    <label for="empMiddleName" class="form-label">Middle Name</label>
                    <input type="text" class="form-control" id="empMiddleName" name="empMiddleName" value="<?php echo htmlspecialchars($empMiddleName); ?>">
                  </div>
                  <div class="col-md-4">
                    <label for="empLastName" class="form-label">Last Name <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="empLastName" name="empLastName" required value="<?php echo htmlspecialchars($empLastName); ?>">
                  </div>
                  <div class="col-md-6">
                    <label for="dob" class="form-label">Date of Birth</label>
                    <input type="date" class="form-control" id="dob" name="dob" value="<?php echo htmlspecialchars($dob); ?>">
                  </div>
                  <div class="col-md-6">
                    <label for="gender" class="form-label">Gender <span class="text-danger">*</span></label>
                    <select class="form-select" id="gender" name="gender" required>
                      <option value="" disabled <?php echo empty($gender) ? 'selected' : ''; ?>>Select a Gender</option>
                      <option value="M" <?php echo ($gender === 'M') ? 'selected' : ''; ?>>Male</option>
                      <option value="F" <?php echo ($gender === 'F') ? 'selected' : ''; ?>>Female</option>
                    </select>
                  </div>
                  <div class="col-md-6">
                    <label for="empPhone" class="form-label">Personal Phone <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="empPhone" name="empPhone" required 
                           pattern="^\+?[0-9]*$" title="Phone number can contain only numbers and the + sign" 
                           value="<?php echo htmlspecialchars($empPhone); ?>">
                  </div>
                  <div class="col-md-6">
                    <label for="empEmail" class="form-label">Personal Email <span class="text-danger">*</span></label>
                    <input type="email" class="form-control" id="empEmail" name="empEmail" required value="<?php echo htmlspecialchars($empEmail); ?>">
                  </div>
                  <div class="col-12 pt-1">
                    <h6 class="text-uppercase text-muted small mb-2">Family &amp; Marital Information</h6>
                  </div>
                  <div class="col-md-4">
                    <label for="father_name" class="form-label">Father's Name</label>
                    <input type="text" class="form-control" id="father_name" name="father_name" value="<?php echo htmlspecialchars($fatherName); ?>">
                  </div>
                  <div class="col-md-4">
                    <label for="mother_name" class="form-label">Mother's Name</label>
                    <input type="text" class="form-control" id="mother_name" name="mother_name" value="<?php echo htmlspecialchars($motherName); ?>">
                  </div>
                  <div class="col-md-4">
                    <label for="marital_status" class="form-label">Marital Status</label>
                    <select class="form-select" id="marital_status" name="marital_status">
                      <option value="">Select Status</option>
                      <?php foreach ($maritalStatusOptions as $value => $label): ?>
                        <option value="<?php echo $value; ?>" <?php echo ($maritalStatus === $value) ? 'selected' : ''; ?>><?php echo $label; ?></option>
                      <?php endforeach; ?>
                    </select>
                  </div>
                  <div class="col-md-6 <?php echo ($maritalStatus === 'married') ? '' : 'd-none'; ?>" id="spouseFieldWrapper">
                    <label for="spouse_name" class="form-label">Spouse Name</label>
                    <input type="text" class="form-control" id="spouse_name" name="spouse_name" value="<?php echo htmlspecialchars($spouseName); ?>">
                  </div>
                  <div class="col-12 pt-1">
                    <h6 class="text-uppercase text-muted small mb-2">Emergency Contact</h6>
                  </div>
                  <div class="col-md-6">
                    <label for="emergency_contact_name" class="form-label">Contact Name</label>
                    <input type="text" class="form-control" id="emergency_contact_name" name="emergency_contact_name" value="<?php echo htmlspecialchars($emergencyContactName); ?>">
                  </div>
                  <div class="col-md-6">
                    <label for="emergency_contact_relationship" class="form-label">Relationship</label>
                    <select class="form-select" id="emergency_contact_relationship" name="emergency_contact_relationship">
                      <option value="">Select Relationship</option>
                      <?php foreach ($relationshipOptions as $option): ?>
                        <option value="<?php echo htmlspecialchars($option); ?>" <?php echo ($emergencyContactRelationship === $option) ? 'selected' : ''; ?>><?php echo $option; ?></option>
                      <?php endforeach; ?>
                      <?php if ($emergencyContactRelationship && !in_array($emergencyContactRelationship, $relationshipOptions, true)): ?>
                        <option value="<?php echo htmlspecialchars($emergencyContactRelationship); ?>" selected><?php echo htmlspecialchars($emergencyContactRelationship); ?></option>
                      <?php endif; ?>
                    </select>
                  </div>
                  <div class="col-md-6">
                    <label for="emergency_contact_phone" class="form-label">Emergency Phone</label>
                    <input type="text" class="form-control" id="emergency_contact_phone" name="emergency_contact_phone" pattern="^\+?[0-9]*$" title="Phone number can contain only numbers and the + sign" value="<?php echo htmlspecialchars($emergencyContactPhone); ?>">
                  </div>
                  <div class="col-md-6">
                    <label for="emergency_contact_email" class="form-label">Emergency Email</label>
                    <input type="email" class="form-control" id="emergency_contact_email" name="emergency_contact_email" value="<?php echo htmlspecialchars($emergencyContactEmail); ?>">
                  </div>
                  <div class="col-12 pt-1">
                    <h6 class="text-uppercase text-muted small mb-2">Health &amp; Medical</h6>
                  </div>
                  <div class="col-md-4">
                    <label for="blood_group" class="form-label">Blood Group</label>
                    <select class="form-select" id="blood_group" name="blood_group">
                      <option value="">Select Blood Group</option>
                      <?php foreach ($bloodGroupOptions as $value => $label): ?>
                        <option value="<?php echo $value; ?>" <?php echo ($bloodGroup === $value) ? 'selected' : ''; ?>><?php echo $label; ?></option>
                      <?php endforeach; ?>
                    </select>
                  </div>
                  <div class="col-md-4">
                    <label for="allergies" class="form-label">Allergies</label>
                    <textarea class="form-control" id="allergies" name="allergies" rows="2"><?php echo htmlspecialchars($allergies); ?></textarea>
                  </div>
                  <div class="col-md-4">
                    <label for="medical_conditions" class="form-label">Medical Conditions</label>
                    <textarea class="form-control" id="medical_conditions" name="medical_conditions" rows="2"><?php echo htmlspecialchars($medicalConditions); ?></textarea>
                  </div>
                  <div class="col-12">
                    <label for="medical_notes" class="form-label">Medical Notes / Medications</label>
                    <textarea class="form-control" id="medical_notes" name="medical_notes" rows="2"><?php echo htmlspecialchars($medicalNotes); ?></textarea>
                  </div>
                  <div class="col-12 pt-1">
                    <h6 class="text-uppercase text-muted small mb-2">Address Information</h6>
                  </div>
                  <div class="col-md-6">
                    <div class="border rounded p-3 h-100">
                      <h6 class="fw-semibold mb-3">Permanent Address</h6>
                      <div class="mb-3">
                        <label for="permanent_address" class="form-label">Street Address</label>
                        <textarea class="form-control" id="permanent_address" name="permanent_address" rows="2"><?php echo htmlspecialchars($permanentAddress); ?></textarea>
                      </div>
                      <div class="row g-2">
                        <div class="col-sm-6">
                          <label for="permanent_city" class="form-label">City</label>
                          <input type="text" class="form-control" id="permanent_city" name="permanent_city" value="<?php echo htmlspecialchars($permanentCity); ?>">
                        </div>
                        <div class="col-sm-6">
                          <label for="permanent_district" class="form-label">District</label>
                          <select class="form-select" id="permanent_district" name="permanent_district">
                            <option value="">Select District</option>
                            <?php 
                              $permanentDistrictFound = false;
                              foreach ($districtRecords as $districtRow):
                                $districtName = $districtRow['district_name'];
                                $provinceName = $provinceIndex[$districtRow['province_id']] ?? '';
                                $postalCode = $districtRow['postal_code'] ?? '';
                                $selected = ($permanentDistrict === $districtName) ? 'selected' : '';
                                if ($selected) {
                                  $permanentDistrictFound = true;
                                }
                            ?>
                              <option value="<?php echo htmlspecialchars($districtName); ?>" data-province="<?php echo htmlspecialchars($provinceName); ?>" data-postal="<?php echo htmlspecialchars($postalCode); ?>" <?php echo $selected; ?>>
                                <?php echo htmlspecialchars($districtName); ?>
                              </option>
                            <?php endforeach; ?>
                            <?php if (!$permanentDistrictFound && !empty($permanentDistrict)): ?>
                              <option value="<?php echo htmlspecialchars($permanentDistrict); ?>" data-province="<?php echo htmlspecialchars($permanentState); ?>" data-postal="<?php echo htmlspecialchars($permanentPostalCode); ?>" selected>
                                <?php echo htmlspecialchars($permanentDistrict); ?>
                              </option>
                            <?php endif; ?>
                          </select>
                        </div>
                        <div class="col-sm-6">
                          <label class="form-label">Province / State</label>
                          <input type="hidden" id="permanent_state" name="permanent_state" value="<?php echo htmlspecialchars($permanentState); ?>">
                          <div id="permanent_state_display" class="py-1"><?php echo htmlspecialchars($permanentState); ?></div>
                        </div>
                        <div class="col-sm-6">
                          <label class="form-label">Postal Code</label>
                          <input type="hidden" id="permanent_postal_code" name="permanent_postal_code" value="<?php echo htmlspecialchars($permanentPostalCode); ?>">
                          <div id="permanent_postal_code_display" class="py-1"><?php echo htmlspecialchars($permanentPostalCode); ?></div>
                        </div>
                        <div class="col-sm-6">
                          <label class="form-label">Country</label>
                          <input type="hidden" id="permanent_country" name="permanent_country" value="<?php echo htmlspecialchars($permanentCountry); ?>">
                          <div id="permanent_country_display" class="py-1"><?php echo htmlspecialchars($permanentCountry); ?></div>
                        </div>
                      </div>
                    </div>
                  </div>
                  <div class="col-md-6">
                    <div class="border rounded p-3 h-100">
                      <div class="d-flex justify-content-between align-items-center mb-2 flex-wrap gap-2">
                        <h6 class="fw-semibold mb-0">Current Address</h6>
                        <div class="form-check form-switch m-0">
                          <input class="form-check-input" type="checkbox" id="copyPermanentAddress">
                          <label class="form-check-label small" for="copyPermanentAddress">Same as Permanent</label>
                        </div>
                      </div>
                      <div class="mb-3">
                        <label for="current_address" class="form-label">Street Address</label>
                        <textarea class="form-control" id="current_address" name="current_address" rows="2"><?php echo htmlspecialchars($currentAddress); ?></textarea>
                      </div>
                      <div class="row g-2">
                        <div class="col-sm-6">
                          <label for="current_city" class="form-label">City</label>
                          <input type="text" class="form-control" id="current_city" name="current_city" value="<?php echo htmlspecialchars($currentCity); ?>">
                        </div>
                        <div class="col-sm-6">
                          <label for="current_district" class="form-label">District</label>
                          <select class="form-select" id="current_district" name="current_district">
                            <option value="">Select District</option>
                            <?php 
                              $currentDistrictFound = false;
                              foreach ($districtRecords as $districtRow):
                                $districtName = $districtRow['district_name'];
                                $provinceName = $provinceIndex[$districtRow['province_id']] ?? '';
                                $postalCode = $districtRow['postal_code'] ?? '';
                                $selected = ($currentDistrict === $districtName) ? 'selected' : '';
                                if ($selected) {
                                  $currentDistrictFound = true;
                                }
                            ?>
                              <option value="<?php echo htmlspecialchars($districtName); ?>" data-province="<?php echo htmlspecialchars($provinceName); ?>" data-postal="<?php echo htmlspecialchars($postalCode); ?>" <?php echo $selected; ?>>
                                <?php echo htmlspecialchars($districtName); ?>
                              </option>
                            <?php endforeach; ?>
                            <?php if (!$currentDistrictFound && !empty($currentDistrict)): ?>
                              <option value="<?php echo htmlspecialchars($currentDistrict); ?>" data-province="<?php echo htmlspecialchars($currentState); ?>" data-postal="<?php echo htmlspecialchars($currentPostalCode); ?>" selected>
                                <?php echo htmlspecialchars($currentDistrict); ?>
                              </option>
                            <?php endif; ?>
                          </select>
                        </div>
                        <div class="col-sm-6">
                          <label class="form-label">Province / State</label>
                          <input type="hidden" id="current_state" name="current_state" value="<?php echo htmlspecialchars($currentState); ?>">
                          <div id="current_state_display" class="py-1"><?php echo htmlspecialchars($currentState); ?></div>
                        </div>
                        <div class="col-sm-6">
                          <label class="form-label">Postal Code</label>
                          <input type="hidden" id="current_postal_code" name="current_postal_code" value="<?php echo htmlspecialchars($currentPostalCode); ?>">
                          <div id="current_postal_code_display" class="py-1"><?php echo htmlspecialchars($currentPostalCode); ?></div>
                        </div>
                        <div class="col-sm-6">
                          <label class="form-label">Country</label>
                          <input type="hidden" id="current_country" name="current_country" value="<?php echo htmlspecialchars($currentCountry); ?>">
                          <div id="current_country_display" class="py-1"><?php echo htmlspecialchars($currentCountry); ?></div>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>

              <div class="tab-pane fade" id="tab-academic" role="tabpanel" aria-labelledby="academic-tab">
                <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-3">
                  <div>
                    <h5 class="fw-semibold mb-1">Academic Information</h5>
                    <p class="text-muted small mb-0">Maintain a history of the employee's education.</p>
                  </div>
                  <button type="button" class="btn btn-sm btn-outline-primary" data-action="add-academic">
                    <i class="fas fa-plus me-1"></i>Add Record
                  </button>
                </div>
                <div id="academicEntries" class="d-flex flex-column gap-3">
                  <?php
                    $academicRows = [[]];
                    foreach ($academicRows as $record) {
                      include __DIR__ . '/partials/academic-row.php';
                    }
                  ?>
                </div>
                <template id="academicRowTemplate">
                  <?php $record = []; include __DIR__ . '/partials/academic-row.php'; ?>
                </template>
              </div>

              <div class="tab-pane fade" id="tab-experience" role="tabpanel" aria-labelledby="experience-tab">
                <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-3">
                  <div>
                    <h5 class="fw-semibold mb-1">Experience History</h5>
                    <p class="text-muted small mb-0">Track prior assignments for better onboarding.</p>
                  </div>
                  <button type="button" class="btn btn-sm btn-outline-primary" data-action="add-experience">
                    <i class="fas fa-plus me-1"></i>Add Experience
                  </button>
                </div>
                <div id="experienceEntries" class="d-flex flex-column gap-3">
                  <?php
                    $experienceRows = [[]];
                    foreach ($experienceRows as $record) {
                      include __DIR__ . '/partials/experience-row.php';
                    }
                  ?>
                </div>
                <template id="experienceRowTemplate">
                  <?php $record = []; include __DIR__ . '/partials/experience-row.php'; ?>
                </template>
              </div>

              <div class="tab-pane fade" id="tab-assignment" role="tabpanel" aria-labelledby="assignment-tab">
                <h5 class="fw-semibold mb-3">Assigned Details</h5>
                <div class="row g-3">
                  <div class="col-md-6">
                    <div class="d-flex justify-content-between align-items-center">
                      <label for="machId" class="form-label mb-0">Machine ID</label>
                      <div class="form-check form-check-inline m-0 small">
                        <input class="form-check-input" type="checkbox" id="machIdNotApplicable" name="mach_id_not_applicable" value="1" <?php echo $machIdNotApplicablePrefill ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="machIdNotApplicable">Not Applicable</label>
                      </div>
                    </div>
                    <input type="text" class="form-control mt-1" id="machId" name="machId" value="<?php echo htmlspecialchars($machId); ?>" autofocus>
                  </div>
                  <div class="col-md-6">
                    <label for="empBranchId" class="form-label">Branch <span class="text-danger">*</span></label>
                    <select class="form-select" id="empBranchId" name="empBranchId" required>
                      <option value="" disabled <?php echo empty($empBranchId) ? 'selected' : ''; ?>>Select a Branch</option>
                      <?php 
                        $branchQuery = "SELECT DISTINCT id, name FROM branches";
                        $stmt = $pdo->query($branchQuery);
                        while ($row = $stmt->fetch()) {
                          $selected = ($row['id'] == $empBranchId) ? 'selected' : '';
                          echo "<option value='{$row['id']}' $selected>{$row['name']}</option>";
                        }
                      ?>
                    </select>
                  </div>
                  <div class="col-md-6">
                    <label for="empHireDate" class="form-label">Hire Date <span class="text-danger">*</span></label>
                    <input type="date" class="form-control" id="empHireDate" name="empHireDate" required 
                           value="<?php echo htmlspecialchars($empHireDate ?: date('Y-m-d')); ?>" readonly
                           title="Hire date is automatically set to today's date">
                  </div>
                  <div class="col-md-6">
                    <label for="empJoinDate" class="form-label">Join Date (Start Working)</label>
                    <input type="date" class="form-control" id="empJoinDate" name="empJoinDate" 
                           value="<?php echo htmlspecialchars($empJoinDate); ?>" 
                           min="<?php echo date('Y-m-d', strtotime('-30 days')); ?>" 
                           max="<?php echo date('Y-m-d', strtotime('15 days')); ?>"
                           title="Date when employee actually started working (can be updated later)">
                  </div>
                  <div class="col-md-6">
                    <label for="work_start_time" class="form-label">Work Start Time</label>
                    <input type="time" class="form-control" id="work_start_time" name="work_start_time" value="<?php echo htmlspecialchars($_GET['work_start_time'] ?? '09:00'); ?>">
                  </div>
                  <div class="col-md-6">
                    <label for="work_end_time" class="form-label">Work End Time</label>
                    <input type="time" class="form-control" id="work_end_time" name="work_end_time" value="<?php echo htmlspecialchars($_GET['work_end_time'] ?? '18:00'); ?>">
                  </div>
                  <div class="col-md-6">
                    <label for="office_phone" class="form-label">Office Phone</label>
                    <input type="text" class="form-control" id="office_phone" name="office_phone"
                           pattern="^\+?[0-9]*$" title="Phone number can contain only numbers and the + sign"
                           value="<?php echo htmlspecialchars($officePhone); ?>">
                  </div>
                  <div class="col-md-6">
                    <label for="office_email" class="form-label">Office Email</label>
                    <input type="email" class="form-control" id="office_email" name="office_email" value="<?php echo htmlspecialchars($officeEmail); ?>">
                  </div>
                  <div class="col-md-6">
                    <label for="designationId" class="form-label">Designation <span class="text-danger">*</span></label>
                    <select class="form-select" id="designationId" name="designationId" required>
                      <option value="" disabled <?php echo empty($designationId) ? 'selected' : ''; ?>>Select a Designation</option>
                      <?php 
                        $designationQuery = "SELECT id, title FROM designations ORDER BY title";
                        $stmt = $pdo->query($designationQuery);
                        while ($row = $stmt->fetch()) {
                          $selected = ($row['id'] == $designationId) ? 'selected' : '';
                          echo "<option value='{$row['id']}' $selected>{$row['title']}</option>";
                        }
                      ?>
                    </select>
                  </div>
                  <div class="col-md-6">
                    <label for="role" class="form-label">Role <span class="text-danger">*</span></label>
                    <select class="form-select" id="role" name="role" required>
                      <option value="" disabled <?php echo empty($role) ? 'selected' : ''; ?>>Select a Role</option>
                      <?php 
                        $roleQuery = "SELECT id, name FROM roles ORDER BY name";
                        $stmtRole = $pdo->query($roleQuery);
                        while ($rowRole = $stmtRole->fetch()) {
                          $selectedRole = ($rowRole['id'] == $role) ? 'selected' : '';
                          echo "<option value='{$rowRole['id']}' $selectedRole>{$rowRole['name']}</option>";
                        }
                      ?>
                    </select>
                  </div>
                  <div class="col-md-6">
                    <label for="supervisor" class="form-label">Direct Supervisor</label>
                    <select class="form-select" id="supervisor" name="supervisor_id">
                      <option value="">-- No Supervisor --</option>
                      <?php 
                        $supervisorQuery = "SELECT emp_id, CONCAT(first_name, ' ', last_name, ' (', emp_id, ')') as supervisor_name 
                                           FROM employees 
                                           WHERE exit_date IS NULL AND login_access = 1 
                                           ORDER BY first_name, last_name";
                        $stmtSupervisor = $pdo->query($supervisorQuery);
                        while ($rowSupervisor = $stmtSupervisor->fetch()) {
                          $selectedSupervisor = ($rowSupervisor['emp_id'] == $supervisor_id) ? 'selected' : '';
                          echo "<option value='{$rowSupervisor['emp_id']}' $selectedSupervisor>{$rowSupervisor['supervisor_name']}</option>";
                        }
                      ?>
                    </select>
                  </div>
                  <div class="col-md-6">
                    <label for="department" class="form-label">Department</label>
                    <select class="form-select" id="department" name="department_id">
                      <option value="">-- Select Department --</option>
                      <?php 
                        $departmentQuery = "SELECT id, name FROM departments ORDER BY name";
                        $stmtDepartment = $pdo->query($departmentQuery);
                        while ($rowDepartment = $stmtDepartment->fetch()) {
                          $selectedDepartment = ($rowDepartment['id'] == $department_id) ? 'selected' : '';
                          echo "<option value='{$rowDepartment['id']}' $selectedDepartment>{$rowDepartment['name']}</option>";
                        }
                      ?>
                    </select>
                  </div>
                  <div class="col-md-6">
                    <label for="login_access" class="form-label">Login Access <span class="text-danger">*</span></label>
                    <select class="form-select" id="login_access" name="login_access" required>
                      <option value="" disabled <?php echo $loginAccess === '' ? 'selected' : ''; ?>>Select Login Access</option>
                      <option value="1" <?php echo ($loginAccess === '1') ? 'selected' : ''; ?>>Granted</option>
                      <option value="0" <?php echo ($loginAccess === '0') ? 'selected' : ''; ?>>Denied</option>
                    </select>
                  </div>
                  <div class="col-md-6">
                    <label class="form-label">Web Check-In/Checkout</label>
                    <div class="form-check form-switch mt-2">
                      <input class="form-check-input" type="checkbox" id="allow_web_attendance" name="allow_web_attendance" value="1" <?php echo $allowWebAttendancePrefill ? 'checked' : ''; ?>>
                      <label class="form-check-label" for="allow_web_attendance">Allow</label>
                    </div>
                    <small class="text-muted">Leave disabled for employees who must rely on biometric devices only.</small>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <div class="col-md-4">
            <div class="text-center mb-3">
              <div class="position-relative d-inline-block">
                <img id="photoPreview" src="<?php echo $home; ?>resources/userimg/default-image.jpg" 
                     alt="Employee Photo" class="rounded-circle img-thumbnail" 
                     style="width: 200px; height: 200px; object-fit: cover;">
                <button type="button" class="btn btn-sm btn-primary position-absolute bottom-0 end-0 rounded-circle"
                        onclick="document.getElementById('empPhoto').click();">
                  <i class="fas fa-camera"></i>
                </button>
              </div>
              <input type="file" class="form-control d-none" id="empPhoto" name="empPhoto" accept="image/*" onchange="previewImage(event)">
              <input type="hidden" id="croppedImage" name="croppedImage">
            </div>
            <p class="text-muted text-center small">Click on the camera icon to upload photo</p>
          </div>
        </div>
        
        <div class="d-flex justify-content-end mt-4">
          <a href="employees.php" class="btn btn-outline-secondary me-2">Cancel</a>
          <button type="submit" class="btn btn-primary">Add Employee</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Image Crop Modal -->
<div class="modal fade" id="cropModal" tabindex="-1" aria-labelledby="cropModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="cropModalLabel">Crop Image</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div class="img-container">
          <img id="imageToCrop" src="" alt="Image to Crop" style="max-width: 100%;">
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-primary" id="cropButton">Crop & Save</button>
      </div>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>

<script src="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.12/cropper.min.js"></script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.12/cropper.min.css">

<script>
let cropper;

function previewImage(event) {
  const file = event.target.files[0];
  if (file) {
    const reader = new FileReader();
    reader.onload = function(e) {
      document.getElementById('imageToCrop').src = e.target.result;
      const cropModal = new bootstrap.Modal(document.getElementById('cropModal'));
      cropModal.show();
      
      // Destroy previous cropper if exists
      if (cropper) {
        cropper.destroy();
      }
      
      // Initialize cropper
      cropper = new Cropper(document.getElementById('imageToCrop'), {
        aspectRatio: 1,
        viewMode: 1,
        autoCropArea: 1,
        responsive: true,
        guides: true,
        highlight: true,
        cropBoxMovable: true,
        cropBoxResizable: true
      });
    }
    reader.readAsDataURL(file);
  }
}

document.getElementById('cropButton').addEventListener('click', function() {
  if (cropper) {
    const canvas = cropper.getCroppedCanvas({
      width: 400,
      height: 400
    });
    
    canvas.toBlob(function(blob) {
      const reader = new FileReader();
      reader.onload = function(e) {
        document.getElementById('croppedImage').value = e.target.result;
        document.getElementById('photoPreview').src = e.target.result;
        const cropModal = bootstrap.Modal.getInstance(document.getElementById('cropModal'));
        cropModal.hide();
        
        // Destroy cropper
        cropper.destroy();
        cropper = null;
      }
      reader.readAsDataURL(blob);
    });
  }
});
</script>
<script>
(function () {
  const academicContainer = document.getElementById('academicEntries');
  const academicTemplate = document.getElementById('academicRowTemplate');
  const experienceContainer = document.getElementById('experienceEntries');
  const experienceTemplate = document.getElementById('experienceRowTemplate');

  const addAcademicButton = document.querySelector('[data-action="add-academic"]');
  const addExperienceButton = document.querySelector('[data-action="add-experience"]');

  const cloneTemplate = (template) => {
    if (!template || !template.content.firstElementChild) {
      return null;
    }
    return template.content.firstElementChild.cloneNode(true);
  };

  const appendRepeatable = (container, template) => {
    if (!container) {
      return null;
    }
    const node = cloneTemplate(template);
    if (node) {
      container.appendChild(node);
    }
    return node;
  };

  const getExperienceRepeatables = (scope) => {
    if (!scope) {
      return [];
    }
    if (scope.classList && scope.classList.contains('profile-repeatable') && scope.dataset.repeatable === 'experience') {
      return [scope];
    }
    return scope.querySelectorAll ? scope.querySelectorAll('.profile-repeatable[data-repeatable="experience"]') : [];
  };

  const bindExperienceSwitches = (scope) => {
    const repeatables = getExperienceRepeatables(scope);
    repeatables.forEach((entry) => {
      const checkbox = entry.querySelector('.experience-current-checkbox');
      const hiddenField = entry.querySelector('.experience-current-hidden');
      const endDateField = entry.querySelector('.experience-end-date');
      if (!checkbox || !hiddenField) {
        return;
      }

      const syncState = () => {
        hiddenField.value = checkbox.checked ? '1' : '0';
        if (endDateField) {
          if (checkbox.checked) {
            endDateField.value = '';
            endDateField.setAttribute('disabled', 'disabled');
          } else {
            endDateField.removeAttribute('disabled');
          }
        }
      };

      checkbox.removeEventListener('change', checkbox._experienceStateHandler || (() => {}));
      checkbox._experienceStateHandler = syncState;
      checkbox.addEventListener('change', syncState);
      syncState();
    });
  };

  const removeRepeatable = (trigger) => {
    const wrapper = trigger.closest('.profile-repeatable');
    if (wrapper) {
      wrapper.remove();
    }
  };

  addAcademicButton?.addEventListener('click', () => {
    appendRepeatable(academicContainer, academicTemplate);
  });

  addExperienceButton?.addEventListener('click', () => {
    const node = appendRepeatable(experienceContainer, experienceTemplate);
    if (node) {
      bindExperienceSwitches(node);
    }
  });

  document.addEventListener('click', (event) => {
    const trigger = event.target.closest('[data-action="remove-repeatable"]');
    if (trigger) {
      removeRepeatable(trigger);
    }
  });

  bindExperienceSwitches(document);
})();
</script>
<script>
(function(){
  function initMachIdToggle(){
    const checkbox = document.getElementById('machIdNotApplicable');
    if(!checkbox) return;
    const fields = ['machId','work_start_time','work_end_time']
      .map(id => document.getElementById(id))
      .filter(Boolean);
    const rememberValue = (field) => { field.dataset.prevValue = field.value; };
    fields.forEach(field => {
      rememberValue(field);
      field.addEventListener('input', function(){
        if(!checkbox.checked){ rememberValue(field); }
      });
    });
    const toggleMachineFields = () => {
      const disableFields = checkbox.checked;
      fields.forEach(field => {
        if(disableFields){
          rememberValue(field);
          field.value = '';
          field.setAttribute('disabled','disabled');
        } else {
          field.removeAttribute('disabled');
          if(typeof field.dataset.prevValue !== 'undefined'){
            field.value = field.dataset.prevValue;
          }
        }
      });
    };
    checkbox.addEventListener('change', toggleMachineFields);
    toggleMachineFields();
  }
  if(document.readyState === 'loading'){
    document.addEventListener('DOMContentLoaded', initMachIdToggle);
  } else {
    initMachIdToggle();
  }
})();
</script>
<script>
(function(){
  const fieldPairs = [
    ['permanent_address', 'current_address'],
    ['permanent_city', 'current_city'],
    ['permanent_district', 'current_district'],
    ['permanent_state', 'current_state'],
    ['permanent_postal_code', 'current_postal_code'],
    ['permanent_country', 'current_country']
  ];

  const lockField = (field) => {
    if(!field || field.type === 'hidden'){
      return;
    }
    if(field.tagName === 'SELECT'){
      field.setAttribute('disabled', 'disabled');
    } else {
      field.setAttribute('readonly', 'readonly');
    }
  };

  const unlockField = (field) => {
    if(!field || field.type === 'hidden'){
      return;
    }
    if(field.tagName === 'SELECT'){
      field.removeAttribute('disabled');
    } else {
      field.removeAttribute('readonly');
    }
  };

  const triggerUpdate = (field) => {
    if(!field){
      return;
    }
    const eventName = field.tagName === 'SELECT' ? 'change' : 'input';
    field.dispatchEvent(new Event(eventName, { bubbles: true }));
  };

  function initAddressCopy(){
    const toggle = document.getElementById('copyPermanentAddress');
    if(!toggle){
      return;
    }

    const getElement = (id) => document.getElementById(id);

    const syncTargets = () => {
      if(!toggle.checked){
        fieldPairs.forEach(([_, targetId]) => {
          const target = getElement(targetId);
          if(!target){
            return;
          }
          if(Object.prototype.hasOwnProperty.call(target.dataset, 'permanentCache')){
            target.value = target.dataset.permanentCache;
            delete target.dataset.permanentCache;
          }
          unlockField(target);
          triggerUpdate(target);
        });
        return;
      }

      fieldPairs.forEach(([sourceId, targetId]) => {
        const source = getElement(sourceId);
        const target = getElement(targetId);
        if(!source || !target){
          return;
        }
        if(!Object.prototype.hasOwnProperty.call(target.dataset, 'permanentCache')){
          target.dataset.permanentCache = target.value;
        }
        target.value = source.value;
        lockField(target);
        triggerUpdate(target);
      });
    };

    const bindSourceListeners = () => {
      fieldPairs.forEach(([sourceId, targetId]) => {
        const source = getElement(sourceId);
        const target = getElement(targetId);
        if(!source || !target){
          return;
        }
        const handler = () => {
          if(toggle.checked){
            target.value = source.value;
            triggerUpdate(target);
          }
        };
        const eventName = source.tagName === 'SELECT' ? 'change' : 'input';
        source.addEventListener(eventName, handler);
      });
    };

    toggle.addEventListener('change', syncTargets);
    bindSourceListeners();

    const autoEnable = fieldPairs.every(([sourceId, targetId]) => {
      const source = getElement(sourceId);
      const target = getElement(targetId);
      if(!source || !target){
        return false;
      }
      return source.value && source.value === target.value;
    });

    if(autoEnable){
      toggle.checked = true;
    }

    syncTargets();
  }

  if(document.readyState === 'loading'){
    document.addEventListener('DOMContentLoaded', initAddressCopy);
  } else {
    initAddressCopy();
  }
})();
</script>
<script>
(function(){
  function initDistrictProvinceAutoFill(){
    const configs = [
      { selectId: 'permanent_district', hiddenId: 'permanent_state', displayId: 'permanent_state_display', postalHiddenId: 'permanent_postal_code', postalDisplayId: 'permanent_postal_code_display' },
      { selectId: 'current_district', hiddenId: 'current_state', displayId: 'current_state_display', postalHiddenId: 'current_postal_code', postalDisplayId: 'current_postal_code_display' }
    ];

    const setDisplay = (el, val) => {
      if(!el) return;
      if('value' in el){
        el.value = val;
      } else {
        el.textContent = val || '';
      }
    };

    const sync = (config, forceClear = false) => {
      const select = document.getElementById(config.selectId);
      const hidden = document.getElementById(config.hiddenId);
      const display = document.getElementById(config.displayId);
      const postalHidden = config.postalHiddenId ? document.getElementById(config.postalHiddenId) : null;
      const postalDisplay = config.postalDisplayId ? document.getElementById(config.postalDisplayId) : null;
      if(!select || !hidden || !display){
        return;
      }

      const option = select.options[select.selectedIndex];
      if(option && option.value){
        const province = option.getAttribute('data-province') || '';
        hidden.value = province;
        setDisplay(display, province);
        const postalCode = option.getAttribute('data-postal') || '';
        if(postalHidden){ postalHidden.value = postalCode; }
        if(postalDisplay){ setDisplay(postalDisplay, postalCode); }
      } else if(forceClear) {
        hidden.value = '';
        setDisplay(display, '');
        if(postalHidden){ postalHidden.value = ''; }
        if(postalDisplay){ setDisplay(postalDisplay, ''); }
      } else {
        setDisplay(display, hidden.value || '');
        if(postalDisplay && postalHidden){ setDisplay(postalDisplay, postalHidden.value || ''); }
      }
    };

    configs.forEach((config) => {
      const select = document.getElementById(config.selectId);
      if(!select){
        return;
      }
      select.addEventListener('change', () => sync(config, true));
      sync(config, false);
    });
  }

  if(document.readyState === 'loading'){
    document.addEventListener('DOMContentLoaded', initDistrictProvinceAutoFill);
  } else {
    initDistrictProvinceAutoFill();
  }
})();
</script>
<script>
(function(){
  function initSpouseVisibility(){
    const maritalSelect = document.getElementById('marital_status');
    const spouseRow = document.getElementById('spouseFieldWrapper');
    if(!maritalSelect || !spouseRow){
      return;
    }

    const toggleVisibility = () => {
      if(maritalSelect.value === 'married'){
        spouseRow.classList.remove('d-none');
      } else {
        spouseRow.classList.add('d-none');
      }
    };

    maritalSelect.addEventListener('change', toggleVisibility);
    toggleVisibility();
  }

  if(document.readyState === 'loading'){
    document.addEventListener('DOMContentLoaded', initSpouseVisibility);
  } else {
    initSpouseVisibility();
  }
})();
</script>