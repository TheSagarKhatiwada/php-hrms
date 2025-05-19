<?php
$page = 'employee';
include 'includes/header.php';
include 'includes/db_connection.php';

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

// Get empId from the query parameter
$empId = $_GET['empId'] ?? '';

// Fetch employee details from the database
if ($empId) {
    $stmt = $pdo->prepare("SELECT e.*, b.name AS branch_name, d.title AS designation_title, r.name AS role_name 
                         FROM employees e 
                         INNER JOIN branches b ON e.branch = b.id 
                         LEFT JOIN designations d ON e.designation = d.id 
                         LEFT JOIN roles r ON e.role_id = r.id 
                         WHERE e.emp_id = :empId");
    $stmt->execute([':empId' => $empId]);
    $employee = $stmt->fetch();

    if (!$employee) {
        echo "<p>Employee not found.</p>";
        exit();
    }

    // Fetch assigned assets for the employee
    $assigned_assets_stmt = $pdo->prepare("SELECT 
                                        fa.AssetName, 
                                        fa.AssetSerial, 
                                        aa.AssignmentDate,
                                        fa.Status AS AssetStatus
                                    FROM AssetAssignments aa
                                    JOIN FixedAssets fa ON aa.AssetID = fa.AssetID
                                    WHERE aa.EmployeeID = :employee_id AND aa.ReturnDate IS NULL
                                    ORDER BY aa.AssignmentDate DESC");
    // Use $employee['id'] which is the primary key for the employees table and likely the foreign key in AssetAssignments
    $assigned_assets_stmt->execute(['employee_id' => $employee['id']]); 
    $assigned_assets = $assigned_assets_stmt->fetchAll(PDO::FETCH_ASSOC);

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

// Calculate birthday countdown
$dob_date = new DateTime($employee['dob']);
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
?>

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
      <button type="button" class="btn btn-info" data-bs-toggle="modal" data-bs-target="#changePasswordModal">
        <i class="fas fa-key me-2"></i> Reset Password
      </button>
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
              <b>Joined Date</b> <span><?php echo date('d M Y', strtotime($employee['join_date'])); ?></span>
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
              <a class="nav-link" href="#assets" data-bs-toggle="tab">
                <i class="fas fa-laptop me-1"></i> Assigned Assets
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
                        <p class="mb-0"><?php echo ($employee['gender'] == 'M') ? 'Male' : 'Female'; ?></p>
                      </div>
                      <div class="profile-info-item">
                        <strong>Date of Birth:</strong>
                        <p class="mb-0">
                          <?php echo date('d F Y', strtotime($employee['dob'])); ?>
                          <span class="special-badge birthday-badge">
                            <i class="fas fa-hourglass-half me-1"></i> <?php echo $birthdayCountdown; ?>
                          </span>
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
                    <span class="time"><i class="far fa-clock"></i> <?php echo date('d F Y', strtotime($employee['join_date'])); ?></span>
                    <h3 class="timeline-header">Joined as <?php echo htmlspecialchars($employee['designation_title'] ?: 'Not Assigned'); ?></h3>
                    <div class="timeline-body">
                      Joined <?php echo htmlspecialchars($employee['branch_name']); ?> branch.
                    </div>
                  </div>
                </div>
                <div>
                  <i class="fas <?php echo empty($employee['exit_date']) ? 'fa-check bg-success' : 'fa-times-circle bg-danger'; ?>"></i>
                  <div class="timeline-item">
                    <span class="time"><i class="far fa-clock"></i> <?php echo empty($employee['exit_date']) ? 'Now' : date('d F Y', strtotime($employee['exit_date'])); ?></span>
                    <h3 class="timeline-header">Current Status</h3>
                    <div class="timeline-body">
                      <?php echo empty($employee['exit_date']) ? 'Currently active employee' : 'Exited the company on ' . date('d F Y', strtotime($employee['exit_date'])); ?>
                    </div>
                  </div>
                </div>
                <div>
                  <i class="far fa-clock bg-gray"></i>
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
            
            <div class="tab-pane" id="activity">
              <div class="timeline timeline-inverse">
                <div class="time-label">
                  <span class="bg-info">Activity History</span>
                </div>
                <div>
                  <i class="fas fa-user-plus bg-primary"></i>
                  <div class="timeline-item">
                    <span class="time"><i class="far fa-clock"></i> <?php echo date('d F Y', strtotime($employee['join_date'])); ?></span>
                    <h3 class="timeline-header">Account Created</h3>
                    <div class="timeline-body">
                      Employee added to the system.
                    </div>
                  </div>
                </div>
                <div>
                  <i class="fas fa-sign-in-alt bg-success"></i>
                  <div class="timeline-item">
                    <span class="time"><i class="far fa-clock"></i> <?php echo date('d F Y', strtotime($employee['join_date'])); ?></span>
                    <h3 class="timeline-header">First Login</h3>
                    <div class="timeline-body">
                      Employee logged into the system for the first time.
                    </div>
                  </div>
                </div>
                <?php if ($employee['exit_date']): ?>
                <div>
                  <i class="fas fa-sign-out-alt bg-danger"></i>
                  <div class="timeline-item">
                    <span class="time"><i class="far fa-clock"></i> <?php echo date('d F Y', strtotime($employee['exit_date'])); ?></span>
                    <h3 class="timeline-header">Employment Ended</h3>
                    <div class="timeline-body">
                      Employee exited the company.
                      <?php if ($employee['exit_note']): ?>
                        <p><strong>Note:</strong> <?php echo htmlspecialchars($employee['exit_note']); ?></p>
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
include 'includes/footer.php';
?>

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