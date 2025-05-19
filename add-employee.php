<?php
// Include session configuration and utilities
require_once 'includes/session_config.php';
require_once 'includes/utilities.php';

$page = 'Add Employee';

// Use the standardized role check function
if (!is_admin() && get_user_role() === '0') {
    header('Location: dashboard.php');
    exit();
}

include 'includes/db_connection.php'; // Include the database connection file

// Get query parameters for repopulating form after errors
$machId = $_GET['machId'] ?? '';
$empBranch = $_GET['empBranch'] ?? '';
$empFirstName = $_GET['empFirstName'] ?? '';
$empMiddleName = $_GET['empMiddleName'] ?? '';
$empLastName = $_GET['empLastName'] ?? '';
$gender = $_GET['gender'] ?? '';
$empEmail = $_GET['empEmail'] ?? '';
$empPhone = $_GET['empPhone'] ?? '';
$empJoinDate = $_GET['empJoinDate'] ?? '';
$designation = $_GET['designation'] ?? '';
$loginAccess = $_GET['login_access'] ?? '';
$dob = $_GET['dob'] ?? '';
$role = $_GET['role'] ?? '';
$officeEmail = $_GET['office_email'] ?? '';
$officePhone = $_GET['office_phone'] ?? '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
  // Get form data
  $machId = $_POST['machId'];
  $empBranch = $_POST['empBranch'];
  $empFirstName = $_POST['empFirstName'];
  $empMiddleName = $_POST['empMiddleName'];
  $empLastName = $_POST['empLastName'];
  $gender = $_POST['gender'];
  $empEmail = $_POST['empEmail'];
  $empPhone = $_POST['empPhone'];
  $empJoinDate = $_POST['empJoinDate'];
  $designation = $_POST['designation']; 
  $loginAccess = $_POST['login_access']; 
  $croppedImage = $_POST['croppedImage'];
  $dob = $_POST['dob'];
  $role = $_POST['role'];
  $officeEmail = $_POST['office_email'];
  $officePhone = $_POST['office_phone'];

  // Handle file upload
  if ($croppedImage) {
      $targetDir = "resources/userimg/uploads/";
      $imageData = base64_decode(preg_replace('#^data:image/\w+;base64,#i', '', $croppedImage));
      $imageName = uniqid() . '.png';
      $targetFile = $targetDir . $imageName;
      file_put_contents($targetFile, $imageData);
  } else {
      $targetFile = "resources/userimg/default-image.jpg";
  }

  // Generate empID based on branch value and auto-increment
  $stmt = $pdo->prepare("SELECT COUNT(*) AS count FROM employees WHERE branch = :branch");
  $stmt->execute([':branch' => $empBranch]);
  $row = $stmt->fetch();
  $count = $row['count'] + 1;
  $empId = $empBranch . str_pad($count, 2, '0', STR_PAD_LEFT);

  try {
    // Insert data into the database using prepared statements
    $sql = "INSERT INTO employees (emp_id, mach_id, branch, first_name, middle_name, last_name, gender, email, phone, join_date, designation, login_access, user_image, dob, role_id, office_email, office_phone)
            VALUES (:empId, :machId, :empBranch, :empFirstName, :empMiddleName, :empLastName, :gender, :empEmail, :empPhone, :empJoinDate, :designation, :loginAccess, :userImage, :dob, :role_id, :officeEmail, :officePhone)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':empId' => $empId,
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
        ':dob' => $dob,
        ':role_id' => $role, // Changed from :role to :role_id to match the column name
        ':officeEmail' => $officeEmail,
        ':officePhone' => $officePhone
    ]);

    // Get the ID of the newly inserted employee
    $newEmployeeId = $pdo->lastInsertId();

    // Add detailed error logging
    if (!$newEmployeeId) {
        $errorInfo = $stmt->errorInfo();
        $_SESSION['error'] = "Database error: " . $errorInfo[2];
        error_log("Employee add error: " . print_r($errorInfo, true));
    } else {
        // Update attendance_logs with emp_Id from employees based on machine_id
        $sql = "UPDATE attendance_logs a JOIN employees e ON a.mach_id = e.mach_id SET a.emp_Id = e.emp_id;";
        $stmt = $pdo->prepare($sql);
        $stmt->execute();

        // Send welcome notification to the new employee if login access is granted
        if ($loginAccess == '1' && $newEmployeeId) {
            notify_employee($newEmployeeId, 'joined');
            
            // Also notify HR team or admins about the new employee
            $fullName = $empFirstName . ' ' . ($empMiddleName ? $empMiddleName . ' ' : '') . $empLastName;
            notify_system(
                'New Employee Added', 
                "A new employee ($fullName) has been added to the system with Employee ID: $empId",
                'success',
                true
            );
            
            // Get admins/HR personnel to notify
            $stmt = $pdo->prepare("SELECT id FROM employees WHERE role = 1 OR role = 2"); // Assuming role 2 is HR
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
    }

    $_SESSION['success'] = "Employee added successfully!";
  } catch (PDOException $e) {
    $_SESSION['error'] = "Error adding employee: " . $e->getMessage();
  }

  // Redirect to the employees page
  header("Location: employees.php?_nocache=" . time());
  exit();
}

require_once __DIR__ . '/includes/header.php';
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
        <div class="row">
          <div class="col-md-8">
            <div class="row mb-3">
              <div class="col-md-4">
                <label for="machId" class="form-label">Machine ID</label>
                <input type="text" class="form-control" id="machId" name="machId" value="<?php echo htmlspecialchars($machId); ?>" autofocus>
              </div>
              <div class="col-md-8">
                <label for="empBranch" class="form-label">Branch <span class="text-danger">*</span></label>
                <select class="form-select" id="empBranch" name="empBranch" required>
                  <option value="" disabled <?php echo empty($empBranch) ? 'selected' : ''; ?>>Select a Branch</option>
                  <?php 
                    $branchQuery = "SELECT DISTINCT id, name FROM branches";
                    $stmt = $pdo->query($branchQuery);
                    while ($row = $stmt->fetch()) {
                      $selected = ($row['id'] == $empBranch) ? 'selected' : ''; 
                      echo "<option value='{$row['id']}' $selected>{$row['name']}</option>";
                    }
                  ?>
                </select>
              </div>
            </div>
            
            <div class="row mb-3">
              <div class="col-md-4">
                <label for="empFirstName" class="form-label">First Name <span class="text-danger">*</span></label>
                <input type="text" class="form-control text-capitalize" id="empFirstName" name="empFirstName" required value="<?php echo htmlspecialchars($empFirstName); ?>">
              </div>
              <div class="col-md-4">
                <label for="empMiddleName" class="form-label">Middle Name</label>
                <input type="text" class="form-control text-capitalize" id="empMiddleName" name="empMiddleName" value="<?php echo htmlspecialchars($empMiddleName); ?>">
              </div>
              <div class="col-md-4">
                <label for="empLastName" class="form-label">Last Name <span class="text-danger">*</span></label>
                <input type="text" class="form-control text-capitalize" id="empLastName" name="empLastName" required value="<?php echo htmlspecialchars($empLastName); ?>">
              </div>
            </div>

            <div class="row mb-3">
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
            </div>
            
            <div class="row mb-3">
              <div class="col-md-6">
                <label for="empPhone" class="form-label">Personal Phone <span class="text-danger">*</span></label>
                <input type="text" class="form-control" id="empPhone" name="empPhone" required 
                       pattern="^\+?[0-9]*$" title="Phone number can contain only numbers and the + sign" 
                       value="<?php echo htmlspecialchars($empPhone); ?>">
              </div>
              <div class="col-md-6">
                <label for="office_phone" class="form-label">Office Phone</label>
                <input type="text" class="form-control" id="office_phone" name="office_phone" 
                       pattern="^\+?[0-9]*$" title="Phone number can contain only numbers and the + sign" 
                       value="<?php echo htmlspecialchars($officePhone); ?>">
              </div>
            </div>
            
            <div class="row mb-3">
              <div class="col-md-6">
                <label for="empEmail" class="form-label">Personal Email <span class="text-danger">*</span></label>
                <input type="email" class="form-control" id="empEmail" name="empEmail" required value="<?php echo htmlspecialchars($empEmail); ?>">
              </div>
              <div class="col-md-6">
                <label for="office_email" class="form-label">Office Email</label>
                <input type="email" class="form-control" id="office_email" name="office_email" value="<?php echo htmlspecialchars($officeEmail); ?>">
              </div>
            </div>
            
            <div class="row mb-3">
              <div class="col-md-6">
                <label for="empJoinDate" class="form-label">Joining Date <span class="text-danger">*</span></label>
                <input type="date" class="form-control" id="empJoinDate" name="empJoinDate" required 
                       value="<?php echo $empJoinDate ?: date('Y-m-d'); ?>" 
                       min="<?php echo date('Y-m-d', strtotime('-30 days')); ?>" 
                       max="<?php echo date('Y-m-d', strtotime('15 days')); ?>">
              </div>
              <div class="col-md-6">
                <label for="designation" class="form-label">Designation <span class="text-danger">*</span></label>
                <select class="form-select" id="designation" name="designation" required>
                  <option value="" disabled <?php echo empty($designation) ? 'selected' : ''; ?>>Select a Designation</option>
                  <?php 
                    $designationQuery = "SELECT id, title FROM designations ORDER BY title";
                    $stmt = $pdo->query($designationQuery);
                    while ($row = $stmt->fetch()) {
                      $selected = ($row['id'] == $designation) ? 'selected' : ''; 
                      echo "<option value='{$row['id']}' $selected>{$row['title']}</option>";
                    }
                  ?>
                </select>
              </div>
            </div>

            <div class="row mb-3">
              <div class="col-md-6">
                <label for="role" class="form-label">Role <span class="text-danger">*</span></label>
                <select class="form-select" id="role" name="role" required>
                  <option value="" disabled <?php echo empty($role) ? 'selected' : ''; ?>>Select a Role</option>
                  <?php 
                    $roleQuery = "SELECT id, name FROM roles ORDER BY name"; // Assuming you have a 'roles' table
                    $stmtRole = $pdo->query($roleQuery);
                    while ($rowRole = $stmtRole->fetch()) {
                      $selectedRole = ($rowRole['id'] == $role) ? 'selected' : ''; 
                      echo "<option value='{$rowRole['id']}' $selectedRole>{$rowRole['name']}</option>";
                    }
                  ?>
                </select>
              </div>
              <div class="col-md-6">
                <label for="login_access" class="form-label">Login Access <span class="text-danger">*</span></label>
                <select class="form-select" id="login_access" name="login_access" required>
                  <option value="" disabled <?php echo empty($loginAccess) ? 'selected' : ''; ?>>Select Login Access</option>
                  <option value="1" <?php echo ($loginAccess === '1') ? 'selected' : ''; ?>>Granted</option>
                  <option value="0" <?php echo ($loginAccess === '0') ? 'selected' : ''; ?>>Denied</option>
                </select>
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

<?php require_once __DIR__ . '/includes/footer.php'; ?>

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