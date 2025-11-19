<?php
$page = 'Edit Employee';
$page = 'employees';
// Include utilities for role check functions
require_once '../../includes/session_config.php';
require_once '../../includes/utilities.php';

// Use the standardized role check function
if (!is_admin() && get_user_role() === '0') {
    header('Location: ../../dashboard.php');
    exit();
}

// Fetch employee details
include '../../includes/db_connection.php'; // Include database connection

if (!isset($_GET['id'])) {
  header('Location: employees.php');
  exit();
}

$emp_id = $_GET['id'];

// Fetch employee details
$stmt = $pdo->prepare("SELECT e.*, b.name as branch_name FROM employees e LEFT JOIN branches b ON e.branch_id = b.id WHERE e.emp_id = :emp_id");
$stmt->execute(['emp_id' => $emp_id]);
$employee = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$employee) {
    $_SESSION['error'] = "Employee not found";
    header('Location: employees.php');
    exit();
}

// Include the header (which includes topbar, starts main-wrapper and content-wrapper)
require_once __DIR__ . '/../../includes/header.php';
?>

<!-- Main content -->
<div class="container-fluid p-4">
  <!-- Page header -->
  <div class="d-flex justify-content-between align-items-center mb-4">
    <div>
      <h1 class="fs-2 fw-bold mb-1">Edit Employee</h1>
    </div>
    <a href="employees.php" class="btn btn-outline-secondary">
      <i class="fas fa-arrow-left me-2"></i> Back to Employees
    </a>
  </div>
  
  <!-- Employee Edit Card -->
  <div class="card border-0 shadow-sm">
    <div class="card-body">
      <form id="editEmployeeForm" method="POST" action="update-employee.php" enctype="multipart/form-data">
        <div class="row">
          <div class="col-md-8">
            <input type="hidden" name="emp_id" value="<?php echo htmlspecialchars($emp_id); ?>">
            
            <div class="row mb-3">
              <div class="col-md-4">
                <div class="d-flex justify-content-between align-items-center">
                  <label for="machId" class="form-label mb-0">Machine ID</label>
                  <div class="form-check form-check-inline m-0 small">
                    <input class="form-check-input" type="checkbox" id="machIdNotApplicable" name="mach_id_not_applicable" value="1" <?php echo !empty($employee['mach_id_not_applicable']) ? 'checked' : ''; ?>>
                    <label class="form-check-label" for="machIdNotApplicable">Not Applicable</label>
                  </div>
                </div>
                <input type="text" class="form-control mt-1" id="machId" name="machId" value="<?php echo htmlspecialchars($employee['mach_id'] ?? ''); ?>">
              </div>
              <div class="col-md-8">
                <label for="empBranchId" class="form-label">Branch <span class="text-danger">*</span></label>
                <select class="form-select" id="empBranchId" name="empBranchId" required>
                  <option disabled>Select a Branch</option>
                  <?php 
                    $branchQuery = "SELECT id, name FROM branches";
                    $stmt = $pdo->query($branchQuery);
                    while ($row = $stmt->fetch()) {
                      $selected = ($row['id'] == $employee['branch_id']) ? 'selected' : ''; 
                      echo "<option value='{$row['id']}' $selected>{$row['name']}</option>";
                    }
                  ?>
                </select>
              </div>
            </div>
            
            <div class="row mb-3">
              <div class="col-md-4">
                <label for="empFirstName" class="form-label">First Name <span class="text-danger">*</span></label>
                <input type="text" class="form-control" id="empFirstName" name="empFirstName" required value="<?php echo htmlspecialchars($employee['first_name']); ?>">
              </div>
              <div class="col-md-4">
                <label for="empMiddleName" class="form-label">Middle Name</label>
                <input type="text" class="form-control" id="empMiddleName" name="empMiddleName" value="<?php echo htmlspecialchars($employee['middle_name'] ?? ''); ?>">
              </div>
              <div class="col-md-4">
                <label for="empLastName" class="form-label">Last Name <span class="text-danger">*</span></label>
                <input type="text" class="form-control" id="empLastName" name="empLastName" required value="<?php echo htmlspecialchars($employee['last_name']); ?>">
              </div>
            </div>

            <div class="row mb-3">
              <div class="col-md-6">
                <label for="dob" class="form-label">Date of Birth</label>
                <input type="date" class="form-control" id="dob" name="dob" value="<?php echo htmlspecialchars($employee['date_of_birth'] ?? ''); ?>">
              </div>
              <div class="col-md-6">
                <label for="gender" class="form-label">Gender <span class="text-danger">*</span></label>
                <select class="form-select" id="gender" name="gender" required>
                  <option disabled>Select a Gender</option>
                  <?php 
                    // Convert database values back to display format
                    $displayGender = '';
                    if ($employee['gender'] == 'male') $displayGender = 'M';
                    elseif ($employee['gender'] == 'female') $displayGender = 'F';
                    
                    $genders = ['M' => 'Male', 'F' => 'Female'];
                    foreach ($genders as $key => $value) {
                      $selected = ($displayGender == $key) ? 'selected' : '';
                      echo "<option value='$key' $selected>$value</option>";
                    }
                  ?>
                </select>
              </div>
            </div>
            
            <div class="row mb-3">
              <div class="col-md-6">
                <label for="empPhone" class="form-label">Personal Phone <span class="text-danger">*</span></label>
                <input type="text" class="form-control" id="empPhone" name="empPhone" required 
                       pattern="^\+?[0-9]*$" title="Phone number can contain only numbers and the + sign" 
                       value="<?php echo htmlspecialchars($employee['phone']); ?>">
              </div>
              <div class="col-md-6">
                <label for="office_phone" class="form-label">Office Phone</label>
                <input type="text" class="form-control" id="office_phone" name="office_phone" 
                       pattern="^\+?[0-9]*$" title="Phone number can contain only numbers and the + sign" 
                       value="<?php echo htmlspecialchars($employee['office_phone'] ?? ''); ?>">
              </div>
            </div>
            
            <div class="row mb-3">
              <div class="col-md-6">
                <label for="empEmail" class="form-label">Personal Email <span class="text-danger">*</span></label>
                <input type="email" class="form-control" id="empEmail" name="empEmail" required value="<?php echo htmlspecialchars($employee['email']); ?>">
              </div>
              <div class="col-md-6">
                <label for="office_email" class="form-label">Office Email</label>
                <input type="email" class="form-control" id="office_email" name="office_email" value="<?php echo htmlspecialchars($employee['office_email'] ?? ''); ?>">
              </div>
            </div>
            
            <div class="row mb-3">
              <div class="col-md-6">
                <label for="empHireDate" class="form-label">Hire Date</label>
                <input type="date" class="form-control" id="empHireDate" name="empHireDate" 
                       value="<?php echo htmlspecialchars($employee['hire_date']); ?>" readonly
                       title="Hire date cannot be changed">
              </div>
              <div class="col-md-6">
                <label for="empJoinDate" class="form-label">Join Date (Start Working)</label>
                <input type="date" class="form-control" id="empJoinDate" name="empJoinDate" 
                       value="<?php echo htmlspecialchars($employee['join_date']); ?>" 
                       max="<?php echo date('Y-m-d', strtotime('15 days')); ?>"
                       title="Date when employee actually started working">
              </div>
            </div>

            <div class="row mb-3">
              <div class="col-md-6">
                <label for="work_start_time" class="form-label">Work Start Time</label>
                <input type="time" class="form-control" id="work_start_time" name="work_start_time" value="<?php echo htmlspecialchars($employee['work_start_time'] ?? '09:30:00'); ?>">
              </div>
              <div class="col-md-6">
                <label for="work_end_time" class="form-label">Work End Time</label>
                <input type="time" class="form-control" id="work_end_time" name="work_end_time" value="<?php echo htmlspecialchars($employee['work_end_time'] ?? '18:00:00'); ?>">
              </div>
            </div>
            
            <div class="row mb-3">
              <div class="col-md-6">
                <label for="designationId" class="form-label">Designation <span class="text-danger">*</span></label>
                <select class="form-select" id="designationId" name="designationId" required>
                  <option value="" disabled>Select a Designation</option>
                  <?php 
                    $designationQuery = "SELECT id, title FROM designations ORDER BY title";
                    $stmt = $pdo->query($designationQuery);
                    while ($row = $stmt->fetch()) {
                      $selected = ($row['id'] == $employee['designation_id']) ? 'selected' : ''; 
                      echo "<option value='{$row['id']}' $selected>{$row['title']}</option>";
                    }
                  ?>
                </select>
              </div>
            </div>

            <div class="row mb-3">
              <div class="col-md-6">
                <label for="role" class="form-label">Role <span class="text-danger">*</span></label>
                <select class="form-select" id="role" name="role_id" required> <!-- Changed name to role_id -->
                  <option value="" disabled>Select a Role</option>
                  <?php 
                    $roleQuery = "SELECT id, name FROM roles ORDER BY name"; // Assuming you have a 'roles' table
                    $stmtRole = $pdo->query($roleQuery);
                    while ($rowRole = $stmtRole->fetch()) {
                      $selectedRole = ($rowRole['id'] == $employee['role_id']) ? 'selected' : ''; // Use role_id for comparison
                      echo "<option value='{$rowRole['id']}' $selectedRole>{$rowRole['name']}</option>";
                    }
                  ?>
                </select>
              </div>
              <div class="col-md-6">
                <label for="login_access" class="form-label">Login Access <span class="text-danger">*</span></label>
                <select class="form-select" id="login_access" name="login_access" required>
                  <option disabled>Select Login Access</option>
                  <?php 
                    $LoginAccess = ['1' => 'Granted', '0' => 'Denied'];
                    foreach ($LoginAccess as $key => $value) {
                      $selected = ($employee['login_access'] == $key) ? 'selected' : '';
                      echo "<option value='$key' $selected>$value</option>";
                    }
                  ?>
                </select>
              </div>
            </div>

            <!-- Hierarchy Fields -->
            <div class="row mb-3">
              <div class="col-md-6">
                <label for="supervisor" class="form-label">Direct Supervisor</label>
                <select class="form-select" id="supervisor" name="supervisor_id">
                  <option value="">-- No Supervisor --</option>
                  <?php 
                    // DEBUG: Check total employees first
                    $totalQuery = "SELECT COUNT(*) as total FROM employees";
                    $totalStmt = $pdo->prepare($totalQuery);
                    $totalStmt->execute();
                    $totalCount = $totalStmt->fetch()['total'];
                    echo "<!-- DEBUG: Total employees in database: $totalCount -->";
                    
                    // DEBUG: Check active employees (no exit date)
                    $activeQuery = "SELECT COUNT(*) as active FROM employees WHERE (exit_date IS NULL OR exit_date = '' OR exit_date = '0000-00-00')";
                    $activeStmt = $pdo->prepare($activeQuery);
                    $activeStmt->execute();
                    $activeCount = $activeStmt->fetch()['active'];
                    echo "<!-- DEBUG: Active employees (no exit date): $activeCount -->";
                    
                    // DEBUG: Current employee being edited
                    echo "<!-- DEBUG: Current employee ID being edited: {$employee['emp_id']} -->";
                    
                    // Modified query to exclude employees with exit dates and show designation
                    $supervisorQuery = "SELECT e.emp_id, CONCAT(e.first_name, ' ', e.last_name, ' (', COALESCE(d.title, 'No Designation'), ')') as supervisor_name 
                                       FROM employees e
                                       LEFT JOIN designations d ON e.designation_id = d.id
                                       WHERE e.emp_id != :current_emp_id 
                                       AND (e.exit_date IS NULL OR e.exit_date = '' OR e.exit_date = '0000-00-00')
                                       ORDER BY e.first_name, e.last_name";
                    $stmtSupervisor = $pdo->prepare($supervisorQuery);
                    $stmtSupervisor->execute(['current_emp_id' => $employee['emp_id']]);
                    
                    $supervisorCount = 0;
                    while ($rowSupervisor = $stmtSupervisor->fetch()) {
                      $supervisorCount++;
                      $selectedSupervisor = ($rowSupervisor['emp_id'] == $employee['supervisor_id']) ? 'selected' : '';
                      echo "<option value='{$rowSupervisor['emp_id']}' $selectedSupervisor>{$rowSupervisor['supervisor_name']}</option>";
                    }
                    echo "<!-- DEBUG: Total supervisors shown in dropdown: $supervisorCount -->";
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
                      $selectedDepartment = ($rowDepartment['id'] == $employee['department_id']) ? 'selected' : '';
                      echo "<option value='{$rowDepartment['id']}' $selectedDepartment>{$rowDepartment['name']}</option>";
                    }
                  ?>
                </select>
              </div>
            </div>


            

            <div class="row mb-3">
              <div class="col-md-6">
                <label class="form-label">Web Check-In/Checkout</label>
                <div class="form-check form-switch mt-2">
                  <input class="form-check-input" type="checkbox" id="allow_web_attendance" name="allow_web_attendance" value="1" <?php echo !empty($employee['allow_web_attendance']) ? 'checked' : ''; ?>>
                  <label class="form-check-label" for="allow_web_attendance">Allow</label>
                </div>
                <small class="text-muted">Leave disabled for employees who must rely on biometric devices only.</small>
              </div>
            </div>
          </div>
          
          <div class="col-md-4">
            <div class="text-center mb-3">
              <div class="position-relative d-inline-block">
                <img id="photoPreview" src="<?php 
                  $imagePath = $employee['user_image'] ?: '../../resources/userimg/default-image.jpg';
                  // If the image path doesn't start with ../ or http, it's stored without the relative path
                  if (!empty($employee['user_image']) && !str_starts_with($employee['user_image'], '../') && !str_starts_with($employee['user_image'], 'http')) {
                    $imagePath = '../../' . $employee['user_image'];
                  }
                  echo htmlspecialchars($imagePath);
                ?>" 
                     alt="Employee Photo" class="rounded-circle img-thumbnail" 
                     style="width: 200px; height: 200px; object-fit: cover;"
                     onerror="this.src='../../resources/userimg/default-image.jpg'">
                <button type="button" class="btn btn-sm btn-primary position-absolute bottom-0 end-0 rounded-circle"
                        onclick="document.getElementById('empPhoto').click();">
                  <i class="fas fa-camera"></i>
                </button>
              </div>
              <input type="file" class="form-control d-none" id="empPhoto" name="empPhoto" accept="image/*" onchange="previewImage(event)">
              <input type="hidden" id="croppedImage" name="croppedImage">
            </div>
            <p class="text-muted text-center small">Click on the camera icon to change photo</p>
          </div>
        </div>
        
        <div class="d-flex justify-content-end mt-4">
          <a href="employees.php" class="btn btn-outline-secondary me-2">Cancel</a>
          <button type="submit" class="btn btn-primary">Save Changes</button>
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