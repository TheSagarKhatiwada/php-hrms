<?php
require_once '../../includes/session_config.php';
require_once '../../includes/utilities.php';
require_once '../../includes/configuration.php';
require_once '../../includes/db_connection.php';
require_once '../../includes/hierarchy_helpers.php';

$page = 'employee';

$canManageEmployees = is_admin() || has_permission('manage_employees');
$hasAllBranchAccessPermission = $canManageEmployees || has_permission('access_all_branch_employee');
$canViewEmployees = $hasAllBranchAccessPermission || has_permission('view_employees');

if (!$canViewEmployees) {
  header('Location: ../../dashboard.php');
  exit();
}

$limitToUserBranch = !$hasAllBranchAccessPermission;
$viewerBranchContext = ['legacy' => null, 'numeric' => null];

if ($limitToUserBranch && isset($_SESSION['user_id'])) {
  try {
    $branchLookup = $pdo->prepare("SELECT branch, branch_id FROM employees WHERE emp_id = :emp_id LIMIT 1");
    $branchLookup->execute([':emp_id' => $_SESSION['user_id']]);
    $branchRow = $branchLookup->fetch(PDO::FETCH_ASSOC);
    if ($branchRow) {
      $viewerBranchContext = hrms_resolve_branch_assignment($branchRow['branch'] ?? null, $branchRow['branch_id'] ?? null);
    }
  } catch (PDOException $e) {
    // Leave branch context empty so access remains restricted if lookup fails
  }
}

$empId = $_GET['empId'] ?? '';

if (empty($empId)) {
  header("Location: employees.php");
  exit();
}

// Handle password reset request
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['new_password']) && isset($_POST['confirm_password']) && isset($_POST['emp_id'])) {
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    $emp_id = $_POST['emp_id'];
    
    // Validate that passwords match
    if ($new_password === $confirm_password) {
        $hashed_password = password_hash($new_password, PASSWORD_BCRYPT);
        $stmt = $pdo->prepare("UPDATE employees SET password = :password WHERE emp_id = :emp_id");
        $result = $stmt->execute([':password' => $hashed_password, ':emp_id' => $emp_id]);
        
        if ($result) {
            $success_message = "Password has been reset successfully!";
        } else {
            $error_message = "Failed to reset password. Please try again.";
        }
    } else {
        $error_message = "Passwords do not match. Please try again.";
    }
}

// Reuse $empId from query parameter
$transferHistory = [];
$academicRecords = [];
$experienceRecords = [];

// Fetch employee details from the database
if ($empId) {
  $stmt = $pdo->prepare("SELECT e.*, b.name AS branch_name, d.title AS designation_title, r.name AS role_name, dept.name AS department_name
                         FROM employees e 
                         INNER JOIN branches b ON e.branch_id = b.id 
                         LEFT JOIN designations d ON e.designation_id = d.id 
                         LEFT JOIN roles r ON e.role_id = r.id 
                         LEFT JOIN departments dept ON e.department_id = dept.id
                         WHERE e.emp_id = :empId");
    $stmt->execute([':empId' => $empId]);
    $employee = $stmt->fetch();

    if (!$employee) {
        echo "<p>Employee not found.</p>";
        exit();
    }

    $employeeBranchContext = hrms_resolve_branch_assignment($employee['branch'] ?? null, $employee['branch_id'] ?? null);
    $isViewingSelf = isset($_SESSION['user_id']) && $_SESSION['user_id'] === $employee['emp_id'];
    if ($limitToUserBranch && !$isViewingSelf) {
      $hasAccess = hrms_employee_matches_branch($viewerBranchContext, $employeeBranchContext);
      if (!$hasAccess) {
        $_SESSION['error'] = "You don't have permission to view that employee.";
        header('Location: employees.php');
        exit();
      }
    }

    // Fetch assigned assets for the employee
    $assigned_assets_stmt = $pdo->prepare("SELECT 
                                        fa.AssetName, 
                                        fa.AssetSerial, 
                                        aa.AssignmentDate,
                                        fa.Status AS AssetStatus
                                    FROM assetassignments aa
                                    JOIN fixedassets fa ON aa.AssetID = fa.AssetID
                                    WHERE aa.EmployeeID = :employee_id AND aa.ReturnDate IS NULL
                                    ORDER BY aa.AssignmentDate DESC");
    // Use $employee['id'] which is the primary key for the employees table and likely the foreign key in AssetAssignments
    $assigned_assets_stmt->execute(['employee_id' => $employee['emp_id']]); 
    $assigned_assets = $assigned_assets_stmt->fetchAll(PDO::FETCH_ASSOC);

    // Location logs for selected date
    $locationDate = $_GET['loc_date'] ?? date('Y-m-d');
    $locationDate = preg_match('/^\d{4}-\d{2}-\d{2}$/', $locationDate) ? $locationDate : date('Y-m-d');
    $todayDate = date('Y-m-d');
    if ($locationDate > $todayDate) {
      $locationDate = $todayDate;
    }
    $location_logs = [];
    try {
      $locStmt = $pdo->prepare("SELECT latitude, longitude, accuracy_meters, created_at
                    FROM location_logs
                    WHERE employee_id = :emp
                    AND DATE(created_at) = :log_date
                    ORDER BY created_at ASC
                    LIMIT 500");
      $locStmt->execute([
        ':emp' => $employee['emp_id'],
        ':log_date' => $locationDate
      ]);
      $location_logs = $locStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (PDOException $e) {
      $location_logs = [];
    }

    $location_log_points = array_map(function ($row) {
      return [
        'lat' => (float)($row['latitude'] ?? 0),
        'lon' => (float)($row['longitude'] ?? 0),
        'time' => $row['created_at'] ?? null,
        'accuracy' => $row['accuracy_meters'] ?? null
      ];
    }, $location_logs);

    try {
      $transfer_stmt = $pdo->prepare("SELECT t.*, 
          fb.name AS from_branch_name,
          tb.name AS to_branch_name,
          CONCAT(fs.first_name, ' ', fs.last_name) AS from_supervisor_name,
          CONCAT(ts.first_name, ' ', ts.last_name) AS to_supervisor_name
        FROM employee_branch_transfers t
        LEFT JOIN branches fb ON t.from_branch_id = fb.id
        LEFT JOIN branches tb ON t.to_branch_id = tb.id
        LEFT JOIN employees fs ON t.from_supervisor_id = fs.emp_id
        LEFT JOIN employees ts ON t.to_supervisor_id = ts.emp_id
        WHERE t.employee_id = :emp_id
        ORDER BY t.effective_date DESC, t.created_at DESC");
      $transfer_stmt->execute([':emp_id' => $employee['emp_id']]);
      $transferHistory = $transfer_stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
      error_log('Transfer history fetch failed: ' . $e->getMessage());
      $transferHistory = [];
    }

    try {
      $academicStmt = $pdo->prepare("SELECT degree_level, institution, field_of_study, graduation_year, grade, remarks
        FROM employee_academic_records
        WHERE employee_id = :emp_id
        ORDER BY graduation_year DESC, id DESC");
      $academicStmt->execute([':emp_id' => $employee['emp_id']]);
      $academicRecords = $academicStmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
      error_log('Academic history fetch failed: ' . $e->getMessage());
      $academicRecords = [];
    }

    try {
      $experienceStmt = $pdo->prepare("SELECT organization, job_title, start_date, end_date, responsibilities, achievements, currently_working
        FROM employee_experience_records
        WHERE employee_id = :emp_id
        ORDER BY start_date DESC, id DESC");
      $experienceStmt->execute([':emp_id' => $employee['emp_id']]);
      $experienceRecords = $experienceStmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
      error_log('Experience history fetch failed: ' . $e->getMessage());
      $experienceRecords = [];
    }

    // Check if employee image is empty and set default image
    if (empty($employee['user_image'])) {
        $employee['user_image'] = $home . 'resources/userimg/default-image.jpg';
    } else {
        // Make sure image path is absolute if it's not already
        if (strpos($employee['user_image'], 'http') !== 0 && strpos($employee['user_image'], $home) !== 0) {
            $employee['user_image'] = $home . $employee['user_image'];
        }
    }
} else {
    // Redirect back to the Employees table
    header("Location: employees.php");
    exit();  // Make sure to stop the script after redirection
}

include '../../includes/header.php';

// Calculate birthday countdown
$dob_date = new DateTime($employee['date_of_birth']);
$current_date = new DateTime();
$dob_date_this_year = (new DateTime())->setDate($current_date->format('Y'), $dob_date->format('m'), $dob_date->format('d'));

if ($dob_date_this_year < $current_date) {
    // Birthday has passed this year, next birthday is next year
    $dob_date_this_year->modify('+1 year');
}

$interval = $dob_date_this_year->diff($current_date);
$birthdayCountdown = $interval->days . ' days left for next birthday';

// Calculate employment duration
$join_date = new DateTime($employee['join_date']);
$employment_interval = $join_date->diff($current_date);
$years = $employment_interval->y;
$months = $employment_interval->m;
$employmentDuration = "";
if ($years > 0) {
    $employmentDuration = $years . " year" . ($years > 1 ? "s" : "");
    if ($months > 0) {
        $employmentDuration .= ", " . $months . " month" . ($months > 1 ? "s" : "");
    }
} else {
    $employmentDuration = $months . " month" . ($months > 1 ? "s" : "");
    if ($employment_interval->d > 0) {
        $employmentDuration .= ", " . $employment_interval->d . " day" . ($employment_interval->d > 1 ? "s" : "");
    }
}

// Determine status class
$statusClass = $employee['exit_date'] ? 'danger' : 'success';
$statusBg = $employee['exit_date'] ? '#ffe5e5' : '#e5fff2';
$statusIcon = $employee['exit_date'] ? 'fa-times-circle' : 'fa-check-circle';

// Determine login access status
$lAccessIcon = $employee['login_access'] == '1' ? 'fa-check-circle' : 'fa-times-circle';

// Calculate experience level (for skill bar)
$experience_years = $years + ($months / 12);
$experience_level = min(round(($experience_years / 5) * 100), 100); // Assuming 5 years is 100% experience

// Get hierarchy information
$hierarchyPath = getHierarchyPath($pdo, $employee['emp_id']);
$teamMembers = getTeamMembers($pdo, $employee['emp_id'], false); // Direct reports only
$allSubordinates = getSubordinates($pdo, $employee['emp_id']); // All subordinates
?>

<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
<link rel="stylesheet" href="https://unpkg.com/leaflet.fullscreen@1.6.0/Control.FullScreen.css" />

<style>
    /* Essential styles from profile.php */
    .profile-picture-container {
        position: relative;
        width: 200px;
        height: 200px;
        border-radius: 50%;
        margin: 0 auto;
        overflow: hidden;
        border: 5px solid rgba(var(--bs-primary-rgb), 0.1);
        box-shadow: var(--card-shadow);
    }
    .profile-picture-container img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }
    
    .profile-info-item {
        padding: 15px;
        border-bottom: 1px solid var(--border-color);
    }
    
    body.dark-mode .profile-info-item {
        border-bottom-color: rgba(255, 255, 255, 0.05);
    }
    
    .profile-info-item:last-child {
        border-bottom: none;
    }
    
    .profile-info-item strong {
        display: block;
        margin-bottom: 5px;
    }
    
    .profile-info-item p {
        margin-bottom: 0;
    }

    .employee-location-map {
      height: 600px;
      width: 100%;
      border-radius: 8px;
      border: 1px solid var(--border-color);
    }

    .leaflet-user-avatar {
      border-radius: 50%;
      border: 2px solid #0d6efd;
      box-shadow: 0 2px 6px rgba(0,0,0,0.35);
      background-color: #ffffff;
    }

    .location-date-controls {
      display: flex;
      align-items: center;
      gap: 6px;
    }
    .location-date-picker {
      position: relative;
    }
    .location-date-picker input[type="date"] {
      position: absolute;
      opacity: 0;
      width: 1px;
      height: 1px;
      pointer-events: none;
    }
    .location-date-button {
      width: 34px;
      height: 34px;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      padding: 0;
    }
    
    /* Fix for active tab background in dark mode */
    body.dark-mode .nav-tabs .nav-link.active {
        background-color: #343a40;
        color: #f8f9fa;
        border-color: #495057;
        border-bottom-color: #343a40;
    }
    
    body.dark-mode .card-header {
        background-color: #2c3136;
        border-color: #495057;
    }
    
    body.dark-mode .card {
        background-color: #343a40;
        border-color: #495057;
    }
    
    /* Timeline enhancements */
    .timeline-item {
        border-radius: 8px;
        box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
        transition: transform 0.2s;
    }
    
    .timeline-item:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 8px rgba(0, 0, 0, 0.15);
    }
    
    body.dark-mode .timeline-item {
        background-color: #3a3f48;
        box-shadow: 0 2px 5px rgba(0, 0, 0, 0.2);
    }
    
    /* Improve table appearance */
    .table-hover tbody tr:hover {
        background-color: rgba(var(--bs-primary-rgb), 0.05);
    }
    
    body.dark-mode .table-hover tbody tr:hover {
        background-color: rgba(255, 255, 255, 0.05);
    }
    
    /* Enhance badge styling */
    .badge {
        font-weight: 500;
        letter-spacing: 0.5px;
        padding: 0.4em 0.7em;
        border-radius: 4px;
    }
    
    /* Make list items more readable in dark mode */
    body.dark-mode .list-group-item {
        background-color: #343a40;
        border-color: #495057;
        color: #f8f9fa;
    }
    
    /* Special badge styles */
    .special-badge {
        display: inline-block;
        padding: 4px 10px;
        border-radius: 20px;
        font-size: 13px;
        font-weight: 500;
        margin-left: 10px;
        transition: background-color 0.3s ease, color 0.3s ease;
    }
    
    .birthday-badge {
        background-color: #fff0e6;
        color: #ff6b00;
        padding: 2px 10px 0 10px;
        margin-left: 0;
    }
    
    body.dark-mode .birthday-badge {
        background-color: rgba(255, 107, 0, 0.2);
        color: #ff9c4f;
    }
    
    .employment-badge {
        background-color: #e6f3ff;
        color: #0070d1;
    }
    
    body.dark-mode .employment-badge {
        background-color: rgba(0, 112, 209, 0.2);
        color: #4da3ff;
    }

    /* Hierarchy Styles */
    .hierarchy-path {
        position: relative;
    }

    .hierarchy-item {
        position: relative;
        padding-left: 20px;
    }

    .hierarchy-item:not(:last-child)::after {
        content: '';
        position: absolute;
        left: 20px;
        top: 100%;
        height: 20px;
        width: 2px;
        background: #ddd;
    }

    .hierarchy-level {
        background: #007bff;
        color: white;
        border-radius: 50%;
        width: 30px;
        height: 30px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 12px;
        font-weight: bold;
        flex-shrink: 0;
    }

    .team-member {
        border: 1px solid #eee;
        border-radius: 8px;
        padding: 10px;
        transition: all 0.3s ease;
    }

    .team-member:hover {
        border-color: #007bff;
        box-shadow: 0 2px 4px rgba(0,123,255,0.1);
    }

    .stat-box {
        padding: 15px;
        border-left: 4px solid #007bff;
        background: #f8f9fa;
        border-radius: 4px;
    }

    .stat-box h3 {
        margin-bottom: 5px;
        font-weight: bold;
    }

    body.dark-mode .team-member {
        border-color: #555;
        background: #2a2a2a;
    }

    body.dark-mode .team-member:hover {
        border-color: #4da3ff;
        box-shadow: 0 2px 4px rgba(77,163,255,0.1);
    }

    body.dark-mode .stat-box {
        background: #2a2a2a;
        border-left-color: #4da3ff;
    }
    
    /* Hierarchy styles */
    .hierarchy-path {
        padding-left: 20px;
        border-left: 2px solid #007bff;
    }
    
    .hierarchy-item {
        position: relative;
        padding-left: 20px;
    }
    
    .hierarchy-item::before {
        content: '';
        position: absolute;
        left: -10px;
        top: 8px;
        width: 10px;
        height: 10px;
        border-radius: 50%;
        background-color: #007bff;
    }
    
    .stat-box {
        padding: 15px;
        border: 1px solid var(--border-color);
        border-radius: 8px;
        background-color: rgba(255, 255, 255, 0.1);
        transition: transform 0.3s;
    }
    
    .stat-box:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
    }
</style>

<!-- Page Content - The content area begins here -->
<div class="container-fluid">
  <!-- Page header -->
  <div class="d-flex justify-content-between align-items-center mb-4">
    <div>
      <h1 class="fs-2 fw-bold mb-1">Employee Profile</h1>
    </div>
    <div>
      <a href="employees.php" class="btn btn-secondary me-2">
        <i class="fas fa-arrow-left me-1"></i> Back to Employees
      </a>
      <a href="edit-employee.php?id=<?php echo htmlspecialchars($employee['emp_id']); ?>" class="btn btn-primary me-2">
        <i class="fas fa-edit me-2"></i> Edit Employee
      </a>
      <?php if (!empty($employee['login_access'])): ?>
      <button type="button" class="btn btn-info" data-bs-toggle="modal" data-bs-target="#changePasswordModal">
        <i class="fas fa-key me-2"></i> Reset Password
      </button>
      <?php endif; ?>
    </div>
  </div>

  <div class="row">
    <!-- Left column -->
    <div class="col-md-4">
      <!-- Profile Image -->
      <div class="card border-0 shadow-sm mb-4">
        <div class="card-body box-profile text-center">
          <div class="profile-picture-container mb-3">
            <img src="<?php echo htmlspecialchars($employee['user_image']); ?>" alt="Employee Photo" class="img-fluid">
          </div>

          <h3 class="profile-username mb-1">
            <?php echo ucwords(htmlspecialchars($employee['first_name']) . ' ' . htmlspecialchars($employee['middle_name']) . ' ' . htmlspecialchars($employee['last_name'])); ?>
          </h3>
          <p class="text-muted mb-3">
            <?php echo ucwords(htmlspecialchars($employee['designation_title'] ?: 'Not Assigned')); ?>
          </p>

          <ul class="list-group list-group-flush mb-3">
            <li class="list-group-item d-flex justify-content-between align-items-center px-0">
              <b>Employee ID</b> <span><?php echo htmlspecialchars($employee['emp_id']); ?></span>
            </li>
            <li class="list-group-item d-flex justify-content-between align-items-center px-0">
              <b>Branch</b> <span><?php echo htmlspecialchars($employee['branch_name']); ?></span>
            </li>
            <li class="list-group-item d-flex justify-content-between align-items-center px-0">
              <b>Joined Date</b> <span><?php echo hrms_format_preferred_date($employee['join_date'], 'd M Y'); ?></span>
            </li>
            <?php if (isset($employee['login_access'])): ?>
            <li class="list-group-item d-flex justify-content-between align-items-center px-0">
              <b>Login Access</b> 
              <span class="badge <?php echo $employee['login_access'] == '1' ? 'bg-success' : 'bg-danger'; ?>">
                <?php echo $employee['login_access'] == '1' ? 'Granted' : 'Denied'; ?>
              </span>
            </li>
            <?php endif; ?>
          </ul>
        </div>
        <!-- /.card-body -->
      </div>
      <!-- /.card -->

      <!-- About Me Box -->
      <div class="card border-0 shadow-sm">
        <div class="card-header">
          <h3 class="card-title">Contact Information</h3>
        </div>
        <!-- /.card-header -->
        <div class="card-body">
          <h5><strong><i class="fas fa-user-circle me-2"></i> Personal</strong></h5>
          <p class="text-muted">
            <i class="fas fa-envelope me-2"></i> <?php echo htmlspecialchars($employee['email']); ?><br>
            <i class="fas fa-phone-alt me-2"></i> <?php echo htmlspecialchars($employee['phone']); ?>
          </p>

          <?php if (!empty($employee['office_email']) || !empty($employee['office_phone'])): ?>
          <hr>
          <h5><strong><i class="fas fa-briefcase me-2"></i> Official</strong></h5>
          <p class="text-muted">
            <?php if (!empty($employee['office_email'])): ?>
                <i class="fas fa-at me-2"></i> <?php echo htmlspecialchars($employee['office_email']); ?><br>
            <?php endif; ?>
            <?php if (!empty($employee['office_phone'])): ?>
                <i class="fas fa-phone-square-alt me-2"></i> <?php echo htmlspecialchars($employee['office_phone']); ?>
            <?php endif; ?>
          </p>
          <?php endif; ?>

          <?php if (!empty($employee['address'])): ?>
          <hr>
          <strong><i class="fas fa-map-marker-alt me-2"></i> Address</strong>
          <p class="text-muted"><?php echo htmlspecialchars($employee['address']); ?></p>
          <?php endif; ?>
        </div>
        <!-- /.card-body -->
      </div>
      <!-- /.card -->
    </div>
    <!-- /.col -->

    <!-- Right column -->
    <div class="col-md-8">
      <div class="card border-0 shadow-sm">
        <div class="card-header p-2">
          <ul class="nav nav-tabs card-header-tabs">
            <li class="nav-item">
              <a class="nav-link active" href="#details" data-bs-toggle="tab">
                <i class="fas fa-user me-1"></i> Profile Details
              </a>
            </li>
            <li class="nav-item">
              <a class="nav-link" href="#hierarchy" data-bs-toggle="tab">
                <i class="fas fa-sitemap me-1"></i> Hierarchy
              </a>
            </li>
            <li class="nav-item">
              <a class="nav-link" href="#assets" data-bs-toggle="tab">
                <i class="fas fa-laptop me-1"></i> Assigned Assets
              </a>
            </li>
            <li class="nav-item">
              <a class="nav-link" href="#location" data-bs-toggle="tab">
                <i class="fas fa-map-marker-alt me-1"></i> Location
              </a>
            </li>
            <li class="nav-item">
              <a class="nav-link" href="#activity" data-bs-toggle="tab">
                <i class="fas fa-history me-1"></i> Activity Log
              </a>
            </li>
          </ul>
        </div><!-- /.card-header -->
        <div class="card-body">
          <div class="tab-content">
            <div class="active tab-pane" id="details">
              <div class="row">
                <div class="col-md-6">
                  <div class="card mb-4">
                    <div class="card-header">
                      <h5 class="card-title m-0">Personal Information</h5>
                    </div>
                    <div class="card-body p-0">
                      <div class="profile-info-item">
                        <strong>Full Name:</strong>
                        <p class="mb-0"><?php echo htmlspecialchars($employee['first_name']) . ' ' . htmlspecialchars($employee['middle_name']) . ' ' . htmlspecialchars($employee['last_name']); ?></p>
                      </div>
                      <div class="profile-info-item">
                        <strong>Gender:</strong>
                        <p class="mb-0"><?php 
                          $gender = strtolower($employee['gender'] ?? '');
                          if (in_array($gender, ['m', 'male'], true)) {
                            echo 'Male';
                          } elseif (in_array($gender, ['f', 'female'], true)) {
                            echo 'Female';
                          } else {
                            echo 'Not specified';
                          }
                        ?></p>
                      </div>
                      <div class="profile-info-item">
                        <strong>Date of Birth:</strong>
                        <p class="mb-0">
                          <?php 
                          if ($employee['date_of_birth']) {
                            echo hrms_format_preferred_date($employee['date_of_birth'], 'd F Y'); 
                          ?>
                          <span class="special-badge birthday-badge">
                            <i class="fas fa-hourglass-half me-1"></i> <?php echo $birthdayCountdown; ?>
                          </span>
                          <?php 
                          } else {
                            echo 'Not specified';
                          }
                          ?>
                        </p>
                      </div>
                    </div>
                  </div>
                </div>
                <div class="col-md-6">
                  <div class="card mb-4">
                    <div class="card-header">
                      <h5 class="card-title m-0">Employment Information</h5>
                    </div>
                    <div class="card-body p-0">
                      <div class="profile-info-item">
                        <strong>Designation:</strong>
                        <p class="mb-0"><?php echo htmlspecialchars($employee['designation_title'] ?: 'Not Assigned'); ?></p>
                      </div>
                      <div class="profile-info-item">
                        <strong>Role:</strong>
                        <p class="mb-0"><?php echo htmlspecialchars($employee['role_name'] ?: 'Not Assigned'); ?></p>
                      </div>
                      <div class="profile-info-item">
                        <strong>Department:</strong>
                        <p class="mb-0"><?php echo htmlspecialchars($employee['department_name'] ?: 'Not Assigned'); ?></p>
                      </div>
                      <div class="profile-info-item">
                        <strong>Status:</strong>
                        <p class="mb-0">
                          <?php if (empty($employee['exit_date'])): ?>
                            <span class="badge bg-success">Active</span>
                          <?php else: ?>
                            <span class="badge bg-danger">Exit on <?php echo htmlspecialchars($employee['exit_date']); ?></span>
                          <?php endif; ?>
                        </p>
                      </div>
                      <div class="profile-info-item">
                        <strong>Employment Duration:</strong>
                        <p class="mb-0">
                          <span class="special-badge employment-badge">
                            <i class="fas fa-clock me-1"></i> <?php echo $employmentDuration; ?>
                          </span>
                        </p>
                      </div>
                    </div>
                  </div>
                </div>
              </div>

              <div class="timeline timeline-inverse mt-4">
                <div class="time-label">
                  <span class="bg-danger">Employment Timeline</span>
                </div>
                <div>
                  <i class="fas fa-user bg-primary"></i>
                  <div class="timeline-item">
                    <span class="time"><i class="far fa-clock"></i> <?php echo hrms_format_preferred_date($employee['join_date'], 'd F Y'); ?></span>
                    <h3 class="timeline-header">Joined as <?php echo htmlspecialchars($employee['designation_title'] ?: 'Not Assigned'); ?></h3>
                    <div class="timeline-body">
                      Joined <?php echo htmlspecialchars($employee['branch_name']); ?> branch.
                    </div>
                  </div>
                </div>
                <div>
                  <i class="fas <?php echo empty($employee['exit_date']) ? 'fa-check bg-success' : 'fa-times-circle bg-danger'; ?>"></i>
                  <div class="timeline-item">
                    <span class="time"><i class="far fa-clock"></i> <?php echo empty($employee['exit_date']) ? 'Now' : hrms_format_preferred_date($employee['exit_date'], 'd F Y'); ?></span>
                    <h3 class="timeline-header">Current Status</h3>
                    <div class="timeline-body">
                      <?php echo empty($employee['exit_date']) ? 'Currently active employee' : 'Exited the company on ' . hrms_format_preferred_date($employee['exit_date'], 'd F Y'); ?>
                    </div>
                  </div>
                </div>
                <div>
                  <i class="far fa-clock bg-gray"></i>
                </div>
              </div>

              <div class="row mt-4 g-4">
                <div class="col-md-6">
                  <div class="card h-100">
                    <div class="card-header d-flex justify-content-between align-items-center">
                      <h5 class="card-title m-0"><i class="fas fa-graduation-cap me-2"></i>Academic History</h5>
                      <span class="badge <?php echo !empty($academicRecords) ? 'bg-primary' : 'bg-secondary'; ?>">
                        <?php echo !empty($academicRecords) ? count($academicRecords) . ' record(s)' : 'Not provided'; ?>
                      </span>
                    </div>
                    <div class="card-body">
                      <?php if (!empty($academicRecords)): ?>
                        <div class="list-group list-group-flush">
                          <?php foreach ($academicRecords as $record): ?>
                            <div class="list-group-item px-0">
                              <div class="d-flex justify-content-between align-items-start">
                                <div>
                                  <h6 class="mb-1"><?php echo htmlspecialchars($record['degree_level'] ?: 'Degree'); ?></h6>
                                  <p class="mb-1 text-muted"><?php echo htmlspecialchars($record['institution'] ?: 'Institution'); ?></p>
                                </div>
                                <?php if (!empty($record['graduation_year'])): ?>
                                  <span class="badge bg-light text-dark">Class of <?php echo htmlspecialchars($record['graduation_year']); ?></span>
                                <?php endif; ?>
                              </div>
                              <?php if (!empty($record['field_of_study'])): ?>
                                <p class="mb-1"><strong>Field:</strong> <?php echo htmlspecialchars($record['field_of_study']); ?></p>
                              <?php endif; ?>
                              <?php if (!empty($record['grade'])): ?>
                                <p class="mb-1"><strong>Grade:</strong> <?php echo htmlspecialchars($record['grade']); ?></p>
                              <?php endif; ?>
                              <?php if (!empty($record['remarks'])): ?>
                                <p class="mb-0 text-muted"><em><?php echo htmlspecialchars($record['remarks']); ?></em></p>
                              <?php endif; ?>
                            </div>
                          <?php endforeach; ?>
                        </div>
                      <?php else: ?>
                        <p class="text-muted mb-0">No academic records have been captured for this employee yet.</p>
                      <?php endif; ?>
                    </div>
                  </div>
                </div>
                <div class="col-md-6">
                  <div class="card h-100">
                    <div class="card-header d-flex justify-content-between align-items-center">
                      <h5 class="card-title m-0"><i class="fas fa-briefcase me-2"></i>Experience History</h5>
                      <span class="badge <?php echo !empty($experienceRecords) ? 'bg-primary' : 'bg-secondary'; ?>">
                        <?php echo !empty($experienceRecords) ? count($experienceRecords) . ' assignment(s)' : 'Not provided'; ?>
                      </span>
                    </div>
                    <div class="card-body">
                      <?php if (!empty($experienceRecords)): ?>
                        <div class="list-group list-group-flush">
                          <?php foreach ($experienceRecords as $record): ?>
                            <div class="list-group-item px-0">
                              <div class="d-flex justify-content-between align-items-start">
                                <div>
                                  <h6 class="mb-1"><?php echo htmlspecialchars($record['job_title'] ?: 'Role'); ?></h6>
                                  <p class="mb-1 text-muted"><?php echo htmlspecialchars($record['organization'] ?: 'Organization'); ?></p>
                                </div>
                                <span class="badge <?php echo !empty($record['currently_working']) ? 'bg-success' : 'bg-light text-dark'; ?>">
                                  <?php echo !empty($record['currently_working']) ? 'Current' : 'Past'; ?>
                                </span>
                              </div>
                              <p class="mb-1 small text-muted">
                                <?php
                                  $start = !empty($record['start_date']) ? date('d M Y', strtotime($record['start_date'])) : 'Unknown';
                                  $end = (!empty($record['currently_working']) || empty($record['end_date'])) ? 'Present' : date('d M Y', strtotime($record['end_date']));
                                  echo $start . ' - ' . $end;
                                ?>
                              </p>
                              <?php if (!empty($record['responsibilities'])): ?>
                                <p class="mb-1"><strong>Responsibilities:</strong> <?php echo nl2br(htmlspecialchars($record['responsibilities'])); ?></p>
                              <?php endif; ?>
                              <?php if (!empty($record['achievements'])): ?>
                                <p class="mb-0"><strong>Achievements:</strong> <?php echo nl2br(htmlspecialchars($record['achievements'])); ?></p>
                              <?php endif; ?>
                            </div>
                          <?php endforeach; ?>
                        </div>
                      <?php else: ?>
                        <p class="text-muted mb-0">No prior experience entries have been provided.</p>
                      <?php endif; ?>
                    </div>
                  </div>
                </div>
              </div>

              <div class="card mt-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                  <h5 class="card-title m-0">
                    <i class="fas fa-random me-2"></i>Branch Transfer History
                  </h5>
                  <span class="badge <?php echo !empty($transferHistory) ? 'bg-primary' : 'bg-secondary'; ?>">
                    <?php echo !empty($transferHistory) ? count($transferHistory) . ' record(s)' : 'No transfers yet'; ?>
                  </span>
                </div>
                <div class="card-body p-0">
                  <?php if (!empty($transferHistory)): ?>
                  <div class="table-responsive">
                    <table class="table table-striped table-hover table-sm mb-0">
                      <thead class="table-light">
                        <tr>
                          <th style="width: 15%;">Effective</th>
                          <th style="width: 25%;">Branch Move</th>
                          <th style="width: 20%;">Supervisor</th>
                          <th style="width: 20%;">Schedule</th>
                          <th>Notes</th>
                        </tr>
                      </thead>
                      <tbody>
                        <?php foreach ($transferHistory as $transfer): 
                          $effectiveDisplay = $transfer['effective_date'] ? date('d M Y', strtotime($transfer['effective_date'])) : '-';
                          $lastDayDisplay = $transfer['last_day_in_previous_branch'] ? date('d M Y', strtotime($transfer['last_day_in_previous_branch'])) : null;
                          $fromBranch = $transfer['from_branch_name'] ?: 'Unassigned';
                          $toBranch = $transfer['to_branch_name'] ?: 'Unassigned';
                          $fromSupervisor = $transfer['from_supervisor_name'] ?: ($transfer['from_supervisor_id'] ?: 'Not set');
                          $toSupervisor = $transfer['to_supervisor_name'] ?: ($transfer['to_supervisor_id'] ?: 'Not set');
                          $supervisorChange = ($transfer['to_supervisor_id'] && $transfer['to_supervisor_id'] !== $transfer['from_supervisor_id']);
                          $prevStart = !empty($transfer['previous_work_start_time']) ? date('H:i', strtotime($transfer['previous_work_start_time'])) : null;
                          $prevEnd = !empty($transfer['previous_work_end_time']) ? date('H:i', strtotime($transfer['previous_work_end_time'])) : null;
                          $newStart = !empty($transfer['new_work_start_time']) ? date('H:i', strtotime($transfer['new_work_start_time'])) : null;
                          $newEnd = !empty($transfer['new_work_end_time']) ? date('H:i', strtotime($transfer['new_work_end_time'])) : null;
                          $scheduleChanged = $newStart || $newEnd;
                        ?>
                        <tr>
                          <td>
                            <div class="fw-semibold"><?php echo $effectiveDisplay; ?></div>
                            <?php if ($lastDayDisplay): ?>
                              <small class="text-muted">Last day: <?php echo $lastDayDisplay; ?></small>
                            <?php endif; ?>
                          </td>
                          <td>
                            <div class="fw-semibold"><?php echo htmlspecialchars($fromBranch); ?> <i class="fas fa-long-arrow-alt-right mx-1"></i> <?php echo htmlspecialchars($toBranch); ?></div>
                            <small class="text-muted">Logged by <?php echo htmlspecialchars($transfer['processed_by'] ?: 'System'); ?></small>
                          </td>
                          <td>
                            <?php if ($supervisorChange): ?>
                              <div><?php echo htmlspecialchars($fromSupervisor); ?> <i class="fas fa-arrow-right mx-1"></i> <?php echo htmlspecialchars($toSupervisor); ?></div>
                            <?php else: ?>
                              <span class="text-muted">Unchanged</span>
                            <?php endif; ?>
                          </td>
                          <td>
                            <?php if ($scheduleChanged): ?>
                              <div class="small">
                                <span class="text-muted">Prev:</span> <?php echo htmlspecialchars(($prevStart ?: '--') . ' - ' . ($prevEnd ?: '--')); ?><br>
                                <span class="text-muted">New:</span> <?php echo htmlspecialchars(($newStart ?: '--') . ' - ' . ($newEnd ?: '--')); ?>
                              </div>
                            <?php else: ?>
                              <span class="text-muted">No change</span>
                            <?php endif; ?>
                          </td>
                          <td>
                            <div class="small"><?php echo nl2br(htmlspecialchars($transfer['reason'] ?: '--')); ?></div>
                            <?php if (!empty($transfer['notify_stakeholders'])): ?>
                              <span class="badge bg-info text-dark mt-1"><i class="fas fa-bell me-1"></i>Notified</span>
                            <?php endif; ?>
                          </td>
                        </tr>
                        <?php endforeach; ?>
                      </tbody>
                    </table>
                  </div>
                  <?php else: ?>
                  <div class="text-center text-muted py-4">
                    <i class="fas fa-route fa-2x mb-2"></i>
                    <p class="mb-0">No branch movements have been recorded for this employee.</p>
                  </div>
                  <?php endif; ?>
                </div>
              </div>
            </div>
            <!-- /.tab-pane -->

            <!-- Hierarchy Tab -->
            <div class="tab-pane" id="hierarchy">
              <div class="row">
                <!-- Reporting Structure -->
                <div class="col-md-6">
                  <div class="card">
                    <div class="card-header">
                      <h5 class="card-title mb-0">
                        <i class="fas fa-level-up-alt me-2"></i>Reports To
                      </h5>
                    </div>
                    <div class="card-body">
                      <?php if (!empty($hierarchyPath) && count($hierarchyPath) > 1): ?>
                        <div class="hierarchy-path">
                          <?php 
                          $reversedPath = array_reverse(array_slice($hierarchyPath, 1)); // Remove self and reverse
                          foreach ($reversedPath as $index => $pathEmployee): 
                          ?>
                            <div class="hierarchy-item d-flex align-items-center mb-2">
                              <div class="hierarchy-level">L<?php echo $index + 1; ?></div>
                              <div class="ms-3">
                                <strong><?php echo htmlspecialchars($pathEmployee['full_name']); ?></strong><br>
                                <small class="text-muted"><?php echo htmlspecialchars($pathEmployee['designation_id'] ?: 'No Designation'); ?></small>
                              </div>
                            </div>
                          <?php endforeach; ?>
                        </div>
                      <?php else: ?>
                        <div class="text-center text-muted py-4">
                          <i class="fas fa-crown fa-3x mb-3"></i>
                          <p>This employee is at the top level<br><small>No supervisor assigned</small></p>
                        </div>
                      <?php endif; ?>
                    </div>
                  </div>
                </div>

                <!-- Team Members -->
                <div class="col-md-6">
                  <div class="card">
                    <div class="card-header">
                      <h5 class="card-title mb-0">
                        <i class="fas fa-users me-2"></i>Direct Reports
                        <span class="badge bg-primary ms-2"><?php echo count($teamMembers); ?></span>
                      </h5>
                    </div>
                    <div class="card-body">
                      <?php if (!empty($teamMembers)): ?>
                        <div class="team-members">
                          <?php foreach ($teamMembers as $member): ?>
                            <div class="team-member d-flex align-items-center mb-3">
                              <img src="<?php echo $member['user_image'] ?: 'resources/userimg/default-image.jpg'; ?>" 
                                   alt="Profile" class="rounded-circle me-3" width="40" height="40">
                              <div class="flex-grow-1">
                                <a href="employee-viewer.php?empId=<?php echo $member['emp_id']; ?>" class="text-decoration-none">
                                  <strong><?php echo htmlspecialchars($member['first_name'] . ' ' . $member['last_name']); ?></strong>
                                </a><br>
                                <small class="text-muted">
                                  <?php echo htmlspecialchars($member['designation_title'] ?: 'No Designation'); ?>
                                  <?php if ($member['department_name']): ?>
                                    • <?php echo htmlspecialchars($member['department_name']); ?>
                                  <?php endif; ?>
                                </small>
                              </div>
                            </div>
                          <?php endforeach; ?>
                        </div>
                      <?php else: ?>
                        <div class="text-center text-muted py-4">
                          <i class="fas fa-user-friends fa-3x mb-3"></i>
                          <p>No direct reports<br><small>This employee doesn't supervise anyone</small></p>
                        </div>
                      <?php endif; ?>
                    </div>
                  </div>
                </div>
              </div>

              <!-- Hierarchy Statistics -->
              <div class="row mt-4">
                <div class="col-12">
                  <div class="card">
                    <div class="card-header">
                      <h5 class="card-title mb-0">
                        <i class="fas fa-chart-line me-2"></i>Hierarchy Statistics
                      </h5>
                    </div>
                    <div class="card-body">
                      <div class="row text-center">
                        <div class="col-md-3">
                          <div class="stat-box">
                            <h3 class="text-primary"><?php echo count($hierarchyPath) - 1; ?></h3>
                            <p class="text-muted mb-0">Levels Above</p>
                          </div>
                        </div>
                        <div class="col-md-3">
                          <div class="stat-box">
                            <h3 class="text-success"><?php echo count($teamMembers); ?></h3>
                            <p class="text-muted mb-0">Direct Reports</p>
                          </div>
                        </div>
                        <div class="col-md-3">
                          <div class="stat-box">
                            <h3 class="text-info"><?php echo count($allSubordinates); ?></h3>
                            <p class="text-muted mb-0">Total Subordinates</p>
                          </div>
                        </div>
                        <div class="col-md-3">
                          <div class="stat-box">
                            <h3 class="text-warning"><?php echo max(0, count($allSubordinates) - count($teamMembers)); ?></h3>
                            <p class="text-muted mb-0">Indirect Reports</p>
                          </div>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
            <!-- /.tab-pane -->

            <div class="tab-pane" id="assets">
              <div class="table-responsive">
                <table class="table table-striped table-hover">
                  <thead>
                    <tr>
                      <th style="width: 10px">#</th>
                      <th>Asset</th>
                      <th>Serial Number</th>
                      <th>Assigned Date</th>
                      <th>Status</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php if (!empty($assigned_assets)):
                        $asset_count = 1;
                        foreach ($assigned_assets as $asset):
                    ?>
                        <tr>
                            <td><?php echo $asset_count++; ?>.</td>
                            <td><?php echo htmlspecialchars($asset['AssetName']); ?></td>
                            <td><?php echo htmlspecialchars($asset['AssetSerial']); ?></td>
                            <td><?php echo htmlspecialchars(date('M d, Y', strtotime($asset['AssignmentDate']))); ?></td>
                            <td>
                                <span class="badge bg-<?php 
                                    echo $asset['AssetStatus'] == 'Assigned' ? 'success' : 
                                        ($asset['AssetStatus'] == 'Maintenance' ? 'warning' : 'danger'); 
                                ?>">
                                    <?php echo htmlspecialchars($asset['AssetStatus']); ?>
                                </span>
                            </td>
                        </tr>
                    <?php 
                        endforeach;
                    else:
                    ?>
                        <tr>
                            <td colspan="5" class="text-center">No assets currently assigned.</td>
                        </tr>
                    <?php endif; ?>
                  </tbody>
                </table>
              </div>
            </div>
            <!-- /.tab-pane -->

            <div class="tab-pane" id="location">
              <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                  <h5 class="card-title m-0"><i class="fas fa-map-marker-alt me-2"></i>Location Map - <span id="locationDateLabel"><?php echo htmlspecialchars(date('jS M, Y', strtotime($locationDate))); ?></span></h5>
                  <form method="get" class="d-flex align-items-center">
                    <input type="hidden" name="empId" value="<?php echo htmlspecialchars($empId); ?>">
                    <div class="location-date-controls">
                      <button type="button" class="btn btn-outline-secondary btn-sm location-date-button" id="locPrev" aria-label="Previous day">
                        <i class="fas fa-chevron-left"></i>
                      </button>
                      <div class="location-date-picker">
                        <button type="button" class="btn btn-outline-secondary btn-sm location-date-button" id="locCalendar" aria-label="Pick a date">
                          <i class="fas fa-calendar-alt"></i>
                        </button>
                        <input type="date" id="locationDate" name="loc_date" value="<?php echo htmlspecialchars($locationDate); ?>" max="<?php echo htmlspecialchars($todayDate); ?>">
                      </div>
                      <button type="button" class="btn btn-outline-secondary btn-sm location-date-button" id="locNext" aria-label="Next day" <?php echo $locationDate >= $todayDate ? 'disabled' : ''; ?>>
                        <i class="fas fa-chevron-right"></i>
                      </button>
                    </div>
                  </form>
                </div>
                <div class="card-body">
                  <div id="employeeLocationMapWrapper">
                    <?php if (!empty($location_logs)): ?>
                      <div id="employeeLocationMap" class="employee-location-map"></div>
                    <?php else: ?>
                      <div class="text-muted">No location logs available for this date.</div>
                    <?php endif; ?>
                  </div>
                </div>
              </div>
            </div>
            <!-- /.tab-pane -->
            
            <div class="tab-pane" id="activity">
              <div class="timeline timeline-inverse">
                <div class="time-label">
                  <span class="bg-info">Activity History</span>
                </div>
                <?php 
                // Fetch activity logs
                try {
                    $activityStmt = $pdo->prepare("SELECT * FROM activity_log WHERE user_id = :emp_id ORDER BY created_at DESC LIMIT 20");
                    $activityStmt->execute([':emp_id' => $empId]);
                    $activities = $activityStmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    if (!empty($activities)) {
                        foreach ($activities as $activity) {
                            $activityTime = strtotime($activity['created_at']);
                            $activityType = strtolower($activity['action']);
                            
                            // Determine icon and color based on activity type
                            if ($activityType === 'login') {
                                $icon = 'fas fa-sign-in-alt';
                                $bgColor = 'bg-success';
                                $header = 'User Login';
                            } elseif ($activityType === 'logout') {
                                $icon = 'fas fa-sign-out-alt';
                                $bgColor = 'bg-warning';
                                $header = 'User Logout';
                            } else {
                                $icon = 'fas fa-info-circle';
                                $bgColor = 'bg-info';
                                $header = ucfirst($activityType);
                            }
                            ?>
                            <div>
                              <i class="<?php echo $icon; ?> <?php echo $bgColor; ?>"></i>
                              <div class="timeline-item">
                                <span class="time"><i class="far fa-clock"></i> <?php echo date('d F Y H:i:s', $activityTime); ?></span>
                                <h3 class="timeline-header"><?php echo htmlspecialchars($header); ?></h3>
                                <div class="timeline-body">
                                  <div class="small">
                                    <strong>IP Address:</strong> <?php echo htmlspecialchars($activity['ip_address'] ?? 'Unknown'); ?><br>
                                    <?php if (!empty($activity['details'])): ?>
                                      <strong>Details:</strong> <?php 
                                        $details = $activity['details'];
                                        // Parse browser info from details
                                        if (strpos($details, 'Browser:') !== false) {
                                          $ua = str_replace('Browser: ', '', $details);
                                          echo '<br><strong>Device:</strong> ';
                                          if (strpos($ua, 'Chrome') !== false) echo 'Chrome';
                                          elseif (strpos($ua, 'Firefox') !== false) echo 'Firefox';
                                          elseif (strpos($ua, 'Safari') !== false) echo 'Safari';
                                          elseif (strpos($ua, 'Edge') !== false) echo 'Edge';
                                          else echo 'Unknown Browser';
                                          
                                          if (strpos($ua, 'Mobile') !== false || strpos($ua, 'Android') !== false) echo ' (Mobile)';
                                          elseif (strpos($ua, 'Windows') !== false) echo ' (Windows)';
                                          elseif (strpos($ua, 'Mac') !== false) echo ' (Mac)';
                                          elseif (strpos($ua, 'Linux') !== false) echo ' (Linux)';
                                        } else {
                                          echo htmlspecialchars($details);
                                        }
                                      ?>
                                    <?php endif; ?>
                                  </div>
                                </div>
                              </div>
                            </div>
                            <?php
                        }
                    } else {
                        // No activity logs found, show default timeline
                        ?>
                        <div>
                          <i class="fas fa-user-plus bg-primary"></i>
                          <div class="timeline-item">
                            <span class="time"><i class="far fa-clock"></i> <?php echo date('d F Y', strtotime($employee['join_date'])); ?></span>
                            <h3 class="timeline-header">Account Created</h3>
                            <div class="timeline-body">
                              <p>Employee record created and added to the system.</p>
                              <div class="small text-muted mt-2">
                                <strong>Branch:</strong> <?php echo htmlspecialchars($employee['branch_name'] ?? 'N/A'); ?><br>
                                <strong>Department:</strong> <?php echo htmlspecialchars($employee['department_name'] ?? 'N/A'); ?><br>
                                <strong>Designation:</strong> <?php echo htmlspecialchars($employee['designation_title'] ?? 'N/A'); ?>
                              </div>
                            </div>
                          </div>
                        </div>
                        <?php
                        if (!empty($employee['updated_at'])) {
                            $updatedTime = strtotime($employee['updated_at']);
                            ?>
                            <div>
                              <i class="fas fa-edit bg-warning"></i>
                              <div class="timeline-item">
                                <span class="time"><i class="far fa-clock"></i> <?php echo date('d F Y H:i:s', $updatedTime); ?></span>
                                <h3 class="timeline-header">Last Update</h3>
                                <div class="timeline-body">
                                  Employee record was last modified.
                                </div>
                              </div>
                            </div>
                            <?php
                        }
                    }
                } catch (PDOException $e) {
                    // Table doesn't exist, show default timeline
                    ?>
                    <div class="alert alert-info">
                      <i class="fas fa-info-circle me-2"></i>
                      Activity logging table not found. Creating table is required to track user activities.
                    </div>
                    <div>
                      <i class="fas fa-user-plus bg-primary"></i>
                      <div class="timeline-item">
                        <span class="time"><i class="far fa-clock"></i> <?php echo date('d F Y', strtotime($employee['join_date'])); ?></span>
                        <h3 class="timeline-header">Account Created</h3>
                        <div class="timeline-body">
                          <p>Employee record created and added to the system.</p>
                          <div class="small text-muted mt-2">
                            <strong>Branch:</strong> <?php echo htmlspecialchars($employee['branch_name'] ?? 'N/A'); ?><br>
                            <strong>Department:</strong> <?php echo htmlspecialchars($employee['department_name'] ?? 'N/A'); ?><br>
                            <strong>Designation:</strong> <?php echo htmlspecialchars($employee['designation_title'] ?? 'N/A'); ?>
                          </div>
                        </div>
                      </div>
                    </div>
                    <?php
                }
                ?>
                <?php if ($employee['exit_date']): ?>
                <div>
                  <i class="fas fa-user-times bg-danger"></i>
                  <div class="timeline-item">
                    <span class="time"><i class="far fa-clock"></i> <?php echo date('d F Y', strtotime($employee['exit_date'])); ?></span>
                    <h3 class="timeline-header">Employment Ended</h3>
                    <div class="timeline-body">
                      Employee exited the company.
                      <?php if ($employee['exit_note']): ?>
                        <p class="mt-2"><strong>Exit Note:</strong> <?php echo htmlspecialchars($employee['exit_note']); ?></p>
                      <?php endif; ?>
                    </div>
                  </div>
                </div>
                <?php endif; ?>
                <div>
                  <i class="far fa-clock bg-gray"></i>
                </div>
              </div>
            </div>
            <!-- /.tab-pane -->
            
          </div>
          <!-- /.tab-content -->
        </div><!-- /.card-body -->
      </div>
      <!-- /.card -->
    </div>
    <!-- /.col -->
  </div>
  <!-- /.row -->
  
</div><!-- /.container-fluid -->

<?php 
include '../../includes/footer.php';
?>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="https://unpkg.com/leaflet.fullscreen@1.6.0/Control.FullScreen.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
  let locationLogPoints = <?php echo json_encode($location_log_points, JSON_UNESCAPED_SLASHES); ?>;
  const userImageUrl = <?php echo json_encode(!empty($employee['user_image']) ? $employee['user_image'] : 'dist/img/default-avatar.png'); ?>;
  const mapWrapper = document.getElementById('employeeLocationMapWrapper');
  const mapLabel = document.getElementById('locationDateLabel');
  const empId = <?php echo json_encode($empId); ?>;
  let locationMap = null;
  let mapLayerGroup = null;
  let avatarMarker = null;
  let lastSignature = null;
  let hasInitialView = false;
  let userLocateControl = null;
  let latestUserPoint = null;

  function ensureMapContainer() {
    if (!mapWrapper) return null;
    let mapEl = document.getElementById('employeeLocationMap');
    if (!mapEl) {
      mapWrapper.innerHTML = '<div id="employeeLocationMap" class="employee-location-map"></div>';
      mapEl = document.getElementById('employeeLocationMap');
    }
    return mapEl;
  }

  function clearMap() {
    if (mapLayerGroup) {
      mapLayerGroup.clearLayers();
    }
    if (avatarMarker) {
      avatarMarker.remove();
      avatarMarker = null;
    }
  }

  function updateUserMarker(point) {
    if (!point || !point.lat || !point.lon) return;
    const mapEl = ensureMapContainer();
    if (!mapEl || typeof L === 'undefined') return;

    if (!locationMap) {
      locationMap = L.map('employeeLocationMap');
      L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '&copy; OpenStreetMap contributors'
      }).addTo(locationMap);
      mapLayerGroup = L.layerGroup().addTo(locationMap);
      if (L.control && L.control.fullscreen) {
        L.control.fullscreen({ position: 'topleft' }).addTo(locationMap);
      }
      if (!userLocateControl && L.control) {
        userLocateControl = L.control({ position: 'topleft' });
        userLocateControl.onAdd = function () {
          const container = L.DomUtil.create('div', 'leaflet-control');
          const button = L.DomUtil.create('a', 'leaflet-control-userloc', container);
          button.href = '#';
          button.title = 'Locate user';
          button.setAttribute('aria-label', 'Locate user');
          button.style.width = '32px';
          button.style.height = '32px';
          button.style.display = 'flex';
          button.style.alignItems = 'center';
          button.style.justifyContent = 'center';
          button.style.background = 'transparent';
          button.style.border = 'none';
          button.style.marginTop = '6px';
          button.style.boxShadow = 'none';

          const img = L.DomUtil.create('img', 'leaflet-user-avatar-control', button);
          img.src = userImageUrl;
          img.alt = 'User location';
          img.style.width = '30px';
          img.style.height = '30px';
          img.style.borderRadius = '50%';
          img.style.border = '2px solid #0d6efd';
          img.style.objectFit = 'cover';

          L.DomEvent.on(button, 'click', function (e) {
            L.DomEvent.stop(e);
            if (avatarMarker) {
              locationMap.setView(avatarMarker.getLatLng(), 16, { animate: true });
            }
          });

          return container;
        };
        userLocateControl.addTo(locationMap);
      }
    }

    const avatarIcon = L.icon({
      iconUrl: userImageUrl,
      iconSize: [36, 36],
      iconAnchor: [18, 36],
      popupAnchor: [0, -36],
      className: 'leaflet-user-avatar'
    });

    if (avatarMarker) {
      avatarMarker.setLatLng([point.lat, point.lon]);
    } else {
      avatarMarker = L.marker([point.lat, point.lon], { icon: avatarIcon })
        .addTo(locationMap)
        .bindPopup('Current location');
    }

    if (!hasInitialView) {
      locationMap.setView([point.lat, point.lon], 16);
      hasInitialView = true;
    }
  }

  function renderLocationMap(points) {
    const mapEl = ensureMapContainer();
    if (!mapEl || !Array.isArray(points) || points.length === 0 || typeof L === 'undefined') {
      if (mapWrapper) {
        mapWrapper.innerHTML = '<div class="text-muted">No location logs available for this date.</div>';
      }
      if (latestUserPoint) {
        updateUserMarker(latestUserPoint);
      }
      return;
    }

    const validPoints = points.filter(p => p && p.lat && p.lon);
    if (validPoints.length === 0) {
      if (mapWrapper) {
        mapWrapper.innerHTML = '<div class="text-muted">No location logs available for this date.</div>';
      }
      return;
    }

    if (!locationMap) {
      locationMap = L.map('employeeLocationMap');
      L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '&copy; OpenStreetMap contributors'
      }).addTo(locationMap);
      mapLayerGroup = L.layerGroup().addTo(locationMap);
      if (L.control && L.control.fullscreen) {
        L.control.fullscreen({ position: 'topleft' }).addTo(locationMap);
      }
      if (!userLocateControl && L.control) {
        userLocateControl = L.control({ position: 'topleft' });
        userLocateControl.onAdd = function () {
          const container = L.DomUtil.create('div', 'leaflet-control');
          const button = L.DomUtil.create('a', 'leaflet-control-userloc', container);
          button.href = '#';
          button.title = 'Locate user';
          button.setAttribute('aria-label', 'Locate user');
          button.style.width = '32px';
          button.style.height = '32px';
          button.style.display = 'flex';
          button.style.alignItems = 'center';
          button.style.justifyContent = 'center';
          button.style.background = 'transparent';
          button.style.border = 'none';
          button.style.marginTop = '6px';
          button.style.boxShadow = 'none';

          const img = L.DomUtil.create('img', 'leaflet-user-avatar-control', button);
          img.src = userImageUrl;
          img.alt = 'User location';
          img.style.width = '30px';
          img.style.height = '30px';
          img.style.borderRadius = '50%';
          img.style.border = '2px solid #0d6efd';
          img.style.objectFit = 'cover';

          L.DomEvent.on(button, 'click', function (e) {
            L.DomEvent.stop(e);
            if (avatarMarker) {
              locationMap.setView(avatarMarker.getLatLng(), 16, { animate: true });
            }
          });

          return container;
        };
        userLocateControl.addTo(locationMap);
      }
    }

    clearMap();

    const latlngs = validPoints.map(p => [p.lat, p.lon]);
    const lastPoint = validPoints[validPoints.length - 1];

    function haversineMeters(a, b) {
      const toRad = d => (d * Math.PI) / 180;
      const R = 6371000;
      const dLat = toRad(b[0] - a[0]);
      const dLon = toRad(b[1] - a[1]);
      const lat1 = toRad(a[0]);
      const lat2 = toRad(b[0]);
      const h = Math.sin(dLat / 2) ** 2 + Math.cos(lat1) * Math.cos(lat2) * Math.sin(dLon / 2) ** 2;
      return 2 * R * Math.asin(Math.sqrt(h));
    }

    const maxSegmentMeters = 2000;
    let segments = [];
    let current = [];

    latlngs.forEach((point, index) => {
      if (index === 0) {
        current = [point];
        return;
      }
      const prev = latlngs[index - 1];
      const distance = haversineMeters(prev, point);
      if (distance <= maxSegmentMeters) {
        current.push(point);
      } else {
        if (current.length > 1) segments.push(current);
        current = [point];
      }
    });
    if (current.length > 1) segments.push(current);

    segments.forEach(segment => {
      const polyline = L.polyline(segment, {
        color: '#0d6efd',
        weight: 4,
        opacity: 0.9,
        lineCap: 'round',
        lineJoin: 'round'
      }).addTo(mapLayerGroup);
      polyline.bringToBack();
    });
    if (!hasInitialView) {
      if (lastPoint && lastPoint.lat && lastPoint.lon) {
        locationMap.setView([lastPoint.lat, lastPoint.lon], 16);
      } else if (latlngs.length === 1) {
        locationMap.setView(latlngs[0], 16);
      } else {
        if (segments.length > 0) {
          const bounds = L.latLngBounds(segments.flat());
          locationMap.fitBounds(bounds, { padding: [20, 20] });
        }
      }
      hasInitialView = true;
    }

    validPoints.forEach((p, idx) => {
      const isLast = idx === validPoints.length - 1;
      const marker = L.circleMarker([p.lat, p.lon], {
        radius: 4,
        color: '#0d6efd',
        fillColor: '#0d6efd',
        fillOpacity: 0.8
      }).addTo(mapLayerGroup);

      if (!isLast && p.time) {
        marker.bindTooltip(p.time, { direction: 'top', opacity: 0.9 });
      }
    });

    if (lastPoint && lastPoint.lat && lastPoint.lon) {
      const avatarIcon = L.icon({
        iconUrl: userImageUrl,
        iconSize: [36, 36],
        iconAnchor: [18, 36],
        popupAnchor: [0, -36],
        className: 'leaflet-user-avatar'
      });
      avatarMarker = L.marker([lastPoint.lat, lastPoint.lon], { icon: avatarIcon })
        .addTo(locationMap)
        .bindPopup('Current location');
    }
    if (latestUserPoint && latestUserPoint.lat && latestUserPoint.lon) {
      updateUserMarker(latestUserPoint);
    }

    setTimeout(() => locationMap.invalidateSize(), 300);
  }

  function updateMapForDate(dateValue) {
    if (!dateValue) return;
    const params = new URLSearchParams({ empId: empId, loc_date: dateValue, t: Date.now().toString() });
    fetch('../../api/employee-location-logs.php?' + params.toString(), { credentials: 'same-origin', cache: 'no-store' })
      .then(r => r.json())
      .then(data => {
        if (!data || !data.success) return;
        if (mapLabel && data.dateLabel) {
          mapLabel.textContent = data.dateLabel;
        }
        const points = Array.isArray(data.points) ? data.points : [];
        latestUserPoint = data.latestPoint || null;
        const lastPoint = points.length ? points[points.length - 1] : null;
        const livePoint = latestUserPoint || lastPoint;
        const signature = `${points.length}|${lastPoint && lastPoint.time ? lastPoint.time : ''}|${lastPoint && typeof lastPoint.lat !== 'undefined' ? lastPoint.lat : ''}|${lastPoint && typeof lastPoint.lon !== 'undefined' ? lastPoint.lon : ''}|${livePoint && livePoint.time ? livePoint.time : ''}|${livePoint && typeof livePoint.lat !== 'undefined' ? livePoint.lat : ''}|${livePoint && typeof livePoint.lon !== 'undefined' ? livePoint.lon : ''}`;
        if (signature === lastSignature) {
          if (latestUserPoint) {
            updateUserMarker(latestUserPoint);
          }
          return;
        }
        lastSignature = signature;
        locationLogPoints = points;
        renderLocationMap(locationLogPoints);
      })
      .catch(() => {
        // Ignore errors silently
      });
  }

  if (Array.isArray(locationLogPoints)) {
    const lastPoint = locationLogPoints.length ? locationLogPoints[locationLogPoints.length - 1] : null;
    const livePoint = latestUserPoint || lastPoint;
    lastSignature = `${locationLogPoints.length}|${lastPoint && lastPoint.time ? lastPoint.time : ''}|${lastPoint && typeof lastPoint.lat !== 'undefined' ? lastPoint.lat : ''}|${lastPoint && typeof lastPoint.lon !== 'undefined' ? lastPoint.lon : ''}|${livePoint && livePoint.time ? livePoint.time : ''}|${livePoint && typeof livePoint.lat !== 'undefined' ? livePoint.lat : ''}|${livePoint && typeof livePoint.lon !== 'undefined' ? livePoint.lon : ''}`;
  }
  renderLocationMap(locationLogPoints);

  // Recalculate map when switching to Location tab
  document.querySelectorAll('.nav-tabs .nav-link').forEach(function(tab) {
    tab.addEventListener('click', function() {
      if (this.getAttribute('href') === '#location') {
        setTimeout(() => renderLocationMap(locationLogPoints), 200);
      }
    });
  });

  // Date controls
  const locationDate = document.getElementById('locationDate');
  const locPrev = document.getElementById('locPrev');
  const locNext = document.getElementById('locNext');
  const locCalendar = document.getElementById('locCalendar');
  const locationTabLink = document.querySelector('.nav-tabs .nav-link[href="#location"]');
  let autoRefreshTimer = null;

  function updateNextButton() {
    if (!locationDate || !locNext) return;
    const maxDate = locationDate.getAttribute('max');
    if (maxDate && locationDate.value >= maxDate) {
      locNext.setAttribute('disabled', 'disabled');
    } else {
      locNext.removeAttribute('disabled');
    }
  }

  function shiftDateBy(days) {
    if (!locationDate) return;
    const current = locationDate.value ? new Date(locationDate.value) : new Date();
    current.setDate(current.getDate() + days);
    const maxDate = locationDate.getAttribute('max');
    if (maxDate) {
      const max = new Date(maxDate);
      if (current > max) return;
    }
    const yyyy = current.getFullYear();
    const mm = String(current.getMonth() + 1).padStart(2, '0');
    const dd = String(current.getDate()).padStart(2, '0');
    locationDate.value = `${yyyy}-${mm}-${dd}`;
    updateNextButton();
    updateMapForDate(locationDate.value);
  }

  function isLocationTabActive() {
    const tabPane = document.getElementById('location');
    return tabPane ? tabPane.classList.contains('active') : false;
  }

  function startAutoRefresh() {
    if (!locationDate) return;
    stopAutoRefresh();
    autoRefreshTimer = setInterval(function() {
      if (!isLocationTabActive()) return;
      updateMapForDate(locationDate.value);
    }, 100);
  }

  function stopAutoRefresh() {
    if (autoRefreshTimer) {
      clearInterval(autoRefreshTimer);
      autoRefreshTimer = null;
    }
  }

  if (locationDate) {
    locationDate.addEventListener('change', function() {
      updateNextButton();
      updateMapForDate(this.value);
      startAutoRefresh();
    });
    updateNextButton();
    startAutoRefresh();
  }

  if (locPrev) {
    locPrev.addEventListener('click', function() {
      shiftDateBy(-1);
      startAutoRefresh();
    });
  }

  if (locNext) {
    locNext.addEventListener('click', function() {
      shiftDateBy(1);
      startAutoRefresh();
    });
  }

  if (locCalendar && locationDate) {
    locCalendar.addEventListener('click', function() {
      if (typeof locationDate.showPicker === 'function') {
        locationDate.showPicker();
      } else {
        locationDate.click();
      }
    });
  }

  if (locationTabLink) {
    locationTabLink.addEventListener('click', function() {
      startAutoRefresh();
    });
  }
});
</script>

<!-- Change Password Modal -->
<div class="modal fade" id="changePasswordModal" tabindex="-1" aria-labelledby="changePasswordModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="changePasswordModalLabel">Reset Password</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <?php if (isset($success_message)): ?>
                    <div class="alert alert-success"><?php echo $success_message; ?></div>
                <?php endif; ?>
                <?php if (isset($error_message)): ?>
                    <div class="alert alert-danger"><?php echo $error_message; ?></div>
                <?php endif; ?>
                <form id="passwordResetForm" action="" method="post" class="form">
                    <input type="hidden" name="emp_id" value="<?php echo $employee['emp_id']; ?>">
                    <div class="mb-3">
                        <label for="new_password_modal" class="form-label">New Password</label>
                        <div class="input-group">
                            <input type="password" class="form-control" id="new_password_modal" name="new_password" placeholder="New Password" required>
                            <button type="button" class="btn btn-secondary" id="generatePassword">Generate</button>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="confirm_password_modal" class="form-label">Confirm Password</label>
                        <input type="password" class="form-control" id="confirm_password_modal" name="confirm_password" placeholder="Confirm Password" required>
                    </div>
                    <button type="submit" class="btn btn-primary w-100">Update Password</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
$(function() {
    // Generate a random password
    function generateRandomPassword(length = 12) {
      const charset = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*()_+~`|}{[]\:;?><,./-=";
      let password = "";
      for (let i = 0; i < length; i++) {
          const randomIndex = Math.floor(Math.random() * charset.length);
          password += charset.charAt(randomIndex);
      }
      return password;
    }
    
    // Handle generate password button
    $('#generatePassword').on('click', function() {
        const newPass = generateRandomPassword();
        const newPassInput = $('#new_password_modal');
        const confirmPassInput = $('#confirm_password_modal');
        newPassInput.val(newPass);
        confirmPassInput.val(newPass);

        // Change input types so they show the generated password as plain text
        newPassInput.attr('type', 'text');
        confirmPassInput.attr('type', 'text');
    });
});
</script>
</body>
</html>