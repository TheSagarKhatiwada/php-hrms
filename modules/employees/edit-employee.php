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

$academicStmt = $pdo->prepare("SELECT degree_level, institution, field_of_study, graduation_year, grade, remarks
                 FROM employee_academic_records WHERE employee_id = :emp_id ORDER BY graduation_year DESC, id DESC");
$academicStmt->execute(['emp_id' => $emp_id]);
$academicRecords = $academicStmt->fetchAll(PDO::FETCH_ASSOC);

$experienceStmt = $pdo->prepare("SELECT organization, job_title, start_date, end_date, responsibilities, achievements, currently_working
                   FROM employee_experience_records WHERE employee_id = :emp_id ORDER BY start_date DESC, id DESC");
$experienceStmt->execute(['emp_id' => $emp_id]);
$experienceRecords = $experienceStmt->fetchAll(PDO::FETCH_ASSOC);

$displayGender = '';
if ($employee['gender'] === 'male') {
  $displayGender = 'M';
} elseif ($employee['gender'] === 'female') {
  $displayGender = 'F';
}

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
                  <div class="col-md-6">
                    <label for="dob" class="form-label">Date of Birth</label>
                    <input type="date" class="form-control" id="dob" name="dob" value="<?php echo htmlspecialchars($employee['date_of_birth'] ?? ''); ?>">
                  </div>
                  <div class="col-md-6">
                    <label for="gender" class="form-label">Gender <span class="text-danger">*</span></label>
                    <select class="form-select" id="gender" name="gender" required>
                      <option value="" disabled>Select a Gender</option>
                      <option value="M" <?php echo ($displayGender === 'M') ? 'selected' : ''; ?>>Male</option>
                      <option value="F" <?php echo ($displayGender === 'F') ? 'selected' : ''; ?>>Female</option>
                    </select>
                  </div>
                  <div class="col-md-6">
                    <label for="empPhone" class="form-label">Personal Phone <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="empPhone" name="empPhone" required 
                           pattern="^\+?[0-9]*$" title="Phone number can contain only numbers and the + sign" 
                           value="<?php echo htmlspecialchars($employee['phone']); ?>">
                  </div>
                  <div class="col-md-6">
                    <label for="empEmail" class="form-label">Personal Email <span class="text-danger">*</span></label>
                    <input type="email" class="form-control" id="empEmail" name="empEmail" required value="<?php echo htmlspecialchars($employee['email']); ?>">
                  </div>
                  <div class="col-12 pt-1">
                    <h6 class="text-uppercase text-muted small mb-2">Family &amp; Marital Information</h6>
                  </div>
                  <div class="col-md-4">
                    <label for="father_name" class="form-label">Father's Name</label>
                    <input type="text" class="form-control" id="father_name" name="father_name" value="<?php echo htmlspecialchars($employee['father_name'] ?? ''); ?>">
                  </div>
                  <div class="col-md-4">
                    <label for="mother_name" class="form-label">Mother's Name</label>
                    <input type="text" class="form-control" id="mother_name" name="mother_name" value="<?php echo htmlspecialchars($employee['mother_name'] ?? ''); ?>">
                  </div>
                  <div class="col-md-4">
                    <label for="marital_status" class="form-label">Marital Status</label>
                    <select class="form-select" id="marital_status" name="marital_status">
                      <option value="">Select Status</option>
                      <?php foreach ($maritalStatusOptions as $value => $label): ?>
                        <option value="<?php echo $value; ?>" <?php echo ($employee['marital_status'] ?? '') === $value ? 'selected' : ''; ?>><?php echo $label; ?></option>
                      <?php endforeach; ?>
                    </select>
                  </div>
                  <div class="col-md-6 <?php echo (($employee['marital_status'] ?? '') === 'married') ? '' : 'd-none'; ?>" id="spouseFieldWrapper">
                    <label for="spouse_name" class="form-label">Spouse Name</label>
                    <input type="text" class="form-control" id="spouse_name" name="spouse_name" value="<?php echo htmlspecialchars($employee['spouse_name'] ?? ''); ?>">
                  </div>
                  <div class="col-12 pt-1">
                    <h6 class="text-uppercase text-muted small mb-2">Emergency Contact</h6>
                  </div>
                  <div class="col-md-6">
                    <label for="emergency_contact_name" class="form-label">Contact Name</label>
                    <input type="text" class="form-control" id="emergency_contact_name" name="emergency_contact_name" value="<?php echo htmlspecialchars($employee['emergency_contact_name'] ?? ''); ?>">
                  </div>
                  <div class="col-md-6">
                    <label for="emergency_contact_relationship" class="form-label">Relationship</label>
                    <?php $selectedRelationship = $employee['emergency_contact_relationship'] ?? ''; ?>
                    <select class="form-select" id="emergency_contact_relationship" name="emergency_contact_relationship">
                      <option value="">Select Relationship</option>
                      <?php foreach ($relationshipOptions as $option): ?>
                        <option value="<?php echo htmlspecialchars($option); ?>" <?php echo ($selectedRelationship === $option) ? 'selected' : ''; ?>><?php echo $option; ?></option>
                      <?php endforeach; ?>
                      <?php if ($selectedRelationship && !in_array($selectedRelationship, $relationshipOptions, true)): ?>
                        <option value="<?php echo htmlspecialchars($selectedRelationship); ?>" selected><?php echo htmlspecialchars($selectedRelationship); ?></option>
                      <?php endif; ?>
                    </select>
                  </div>
                  <div class="col-md-6">
                    <label for="emergency_contact_phone" class="form-label">Emergency Phone</label>
                    <input type="text" class="form-control" id="emergency_contact_phone" name="emergency_contact_phone" pattern="^\+?[0-9]*$" title="Phone number can contain only numbers and the + sign" value="<?php echo htmlspecialchars($employee['emergency_contact_phone'] ?? ''); ?>">
                  </div>
                  <div class="col-md-6">
                    <label for="emergency_contact_email" class="form-label">Emergency Email</label>
                    <input type="email" class="form-control" id="emergency_contact_email" name="emergency_contact_email" value="<?php echo htmlspecialchars($employee['emergency_contact_email'] ?? ''); ?>">
                  </div>
                  <div class="col-12 pt-1">
                    <h6 class="text-uppercase text-muted small mb-2">Health &amp; Medical</h6>
                  </div>
                  <div class="col-md-4">
                    <label for="blood_group" class="form-label">Blood Group</label>
                    <select class="form-select" id="blood_group" name="blood_group">
                      <option value="">Select Blood Group</option>
                      <?php foreach ($bloodGroupOptions as $value => $label): ?>
                        <option value="<?php echo $value; ?>" <?php echo ($employee['blood_group'] ?? '') === $value ? 'selected' : ''; ?>><?php echo $label; ?></option>
                      <?php endforeach; ?>
                    </select>
                  </div>
                  <div class="col-md-4">
                    <label for="allergies" class="form-label">Allergies</label>
                    <textarea class="form-control" id="allergies" name="allergies" rows="2"><?php echo htmlspecialchars($employee['allergies'] ?? ''); ?></textarea>
                  </div>
                  <div class="col-md-4">
                    <label for="medical_conditions" class="form-label">Medical Conditions</label>
                    <textarea class="form-control" id="medical_conditions" name="medical_conditions" rows="2"><?php echo htmlspecialchars($employee['medical_conditions'] ?? ''); ?></textarea>
                  </div>
                  <div class="col-12">
                    <label for="medical_notes" class="form-label">Medical Notes / Medications</label>
                    <textarea class="form-control" id="medical_notes" name="medical_notes" rows="2"><?php echo htmlspecialchars($employee['medical_notes'] ?? ''); ?></textarea>
                  </div>
                  <div class="col-12 pt-1">
                    <h6 class="text-uppercase text-muted small mb-2">Address Information</h6>
                  </div>
                  <div class="col-md-6">
                    <div class="border rounded p-3 h-100">
                      <h6 class="fw-semibold mb-3">Permanent Address</h6>
                      <div class="mb-3">
                        <label for="permanent_address" class="form-label">Street Address</label>
                        <textarea class="form-control" id="permanent_address" name="permanent_address" rows="2"><?php echo htmlspecialchars($employee['permanent_address'] ?? ''); ?></textarea>
                      </div>
                      <div class="row g-2">
                        <div class="col-sm-6">
                          <label for="permanent_city" class="form-label">City</label>
                          <input type="text" class="form-control" id="permanent_city" name="permanent_city" value="<?php echo htmlspecialchars($employee['permanent_city'] ?? ''); ?>">
                        </div>
                        <div class="col-sm-6">
                          <label for="permanent_district" class="form-label">District</label>
                          <select class="form-select" id="permanent_district" name="permanent_district">
                            <option value="">Select District</option>
                            <?php 
                              $permanentDistrictValue = $employee['permanent_district'] ?? '';
                              $permanentDistrictFound = false;
                              foreach ($districtRecords as $districtRow):
                                $districtName = $districtRow['district_name'];
                                $provinceName = $provinceIndex[$districtRow['province_id']] ?? '';
                                $postalCode = $districtRow['postal_code'] ?? '';
                                $selected = ($permanentDistrictValue === $districtName) ? 'selected' : '';
                                if ($selected) {
                                  $permanentDistrictFound = true;
                                }
                            ?>
                              <option value="<?php echo htmlspecialchars($districtName); ?>" data-province="<?php echo htmlspecialchars($provinceName); ?>" data-postal="<?php echo htmlspecialchars($postalCode); ?>" <?php echo $selected; ?>>
                                <?php echo htmlspecialchars($districtName); ?>
                              </option>
                            <?php endforeach; ?>
                            <?php if (!$permanentDistrictFound && !empty($permanentDistrictValue)): ?>
                              <option value="<?php echo htmlspecialchars($permanentDistrictValue); ?>" data-province="<?php echo htmlspecialchars($employee['permanent_state'] ?? ''); ?>" data-postal="<?php echo htmlspecialchars($employee['permanent_postal_code'] ?? ''); ?>" selected>
                                <?php echo htmlspecialchars($permanentDistrictValue); ?>
                              </option>
                            <?php endif; ?>
                          </select>
                        </div>
                        <div class="col-sm-6">
                          <label class="form-label">Province / State</label>
                          <input type="hidden" id="permanent_state" name="permanent_state" value="<?php echo htmlspecialchars($employee['permanent_state'] ?? ''); ?>">
                          <div id="permanent_state_display" class="form-control-plaintext"><?php echo htmlspecialchars($employee['permanent_state'] ?? ''); ?></div>
                        </div>
                        <div class="col-sm-6">
                          <label class="form-label">Postal Code</label>
                          <input type="hidden" id="permanent_postal_code" name="permanent_postal_code" value="<?php echo htmlspecialchars($employee['permanent_postal_code'] ?? ''); ?>">
                          <div id="permanent_postal_code_display" class="form-control-plaintext"><?php echo htmlspecialchars($employee['permanent_postal_code'] ?? ''); ?></div>
                        </div>
                        <div class="col-sm-6">
                          <label for="permanent_country" class="form-label">Country</label>
                          <input type="text" class="form-control" id="permanent_country" name="permanent_country" value="<?php echo htmlspecialchars($employee['permanent_country'] ?? ''); ?>">
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
                        <textarea class="form-control" id="current_address" name="current_address" rows="2"><?php echo htmlspecialchars($employee['current_address'] ?? ''); ?></textarea>
                      </div>
                      <div class="row g-2">
                        <div class="col-sm-6">
                          <label for="current_city" class="form-label">City</label>
                          <input type="text" class="form-control" id="current_city" name="current_city" value="<?php echo htmlspecialchars($employee['current_city'] ?? ''); ?>">
                        </div>
                        <div class="col-sm-6">
                          <label for="current_district" class="form-label">District</label>
                          <select class="form-select" id="current_district" name="current_district">
                            <option value="">Select District</option>
                            <?php 
                              $currentDistrictValue = $employee['current_district'] ?? '';
                              $currentDistrictFound = false;
                              foreach ($districtRecords as $districtRow):
                                $districtName = $districtRow['district_name'];
                                $provinceName = $provinceIndex[$districtRow['province_id']] ?? '';
                                $postalCode = $districtRow['postal_code'] ?? '';
                                $selected = ($currentDistrictValue === $districtName) ? 'selected' : '';
                                if ($selected) {
                                  $currentDistrictFound = true;
                                }
                            ?>
                              <option value="<?php echo htmlspecialchars($districtName); ?>" data-province="<?php echo htmlspecialchars($provinceName); ?>" data-postal="<?php echo htmlspecialchars($postalCode); ?>" <?php echo $selected; ?>>
                                <?php echo htmlspecialchars($districtName); ?>
                              </option>
                            <?php endforeach; ?>
                            <?php if (!$currentDistrictFound && !empty($currentDistrictValue)): ?>
                              <option value="<?php echo htmlspecialchars($currentDistrictValue); ?>" data-province="<?php echo htmlspecialchars($employee['current_state'] ?? ''); ?>" data-postal="<?php echo htmlspecialchars($employee['current_postal_code'] ?? ''); ?>" selected>
                                <?php echo htmlspecialchars($currentDistrictValue); ?>
                              </option>
                            <?php endif; ?>
                          </select>
                        </div>
                        <div class="col-sm-6">
                          <label class="form-label">Province / State</label>
                          <input type="hidden" id="current_state" name="current_state" value="<?php echo htmlspecialchars($employee['current_state'] ?? ''); ?>">
                          <div id="current_state_display" class="form-control-plaintext"><?php echo htmlspecialchars($employee['current_state'] ?? ''); ?></div>
                        </div>
                        <div class="col-sm-6">
                          <label class="form-label">Postal Code</label>
                          <input type="hidden" id="current_postal_code" name="current_postal_code" value="<?php echo htmlspecialchars($employee['current_postal_code'] ?? ''); ?>">
                          <div id="current_postal_code_display" class="form-control-plaintext"><?php echo htmlspecialchars($employee['current_postal_code'] ?? ''); ?></div>
                        </div>
                        <div class="col-sm-6">
                          <label for="current_country" class="form-label">Country</label>
                          <input type="text" class="form-control" id="current_country" name="current_country" value="<?php echo htmlspecialchars($employee['current_country'] ?? ''); ?>">
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
                    $academicRows = !empty($academicRecords) ? $academicRecords : [[]];
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
                    $experienceRows = !empty($experienceRecords) ? $experienceRecords : [[]];
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
                        <input class="form-check-input" type="checkbox" id="machIdNotApplicable" name="mach_id_not_applicable" value="1" <?php echo !empty($employee['mach_id_not_applicable']) ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="machIdNotApplicable">Not Applicable</label>
                      </div>
                    </div>
                    <input type="text" class="form-control mt-1" id="machId" name="machId" value="<?php echo htmlspecialchars($employee['mach_id'] ?? ''); ?>">
                  </div>
                  <div class="col-md-6">
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
                  <div class="col-md-6">
                    <label for="work_start_time" class="form-label">Work Start Time</label>
                    <input type="time" class="form-control" id="work_start_time" name="work_start_time" value="<?php echo htmlspecialchars($employee['work_start_time'] ?? '09:30:00'); ?>">
                  </div>
                  <div class="col-md-6">
                    <label for="work_end_time" class="form-label">Work End Time</label>
                    <input type="time" class="form-control" id="work_end_time" name="work_end_time" value="<?php echo htmlspecialchars($employee['work_end_time'] ?? '18:00:00'); ?>">
                  </div>
                  <div class="col-md-6">
                    <label for="office_phone" class="form-label">Office Phone</label>
                    <input type="text" class="form-control" id="office_phone" name="office_phone"
                           pattern="^\+?[0-9]*$" title="Phone number can contain only numbers and the + sign"
                           value="<?php echo htmlspecialchars($employee['office_phone'] ?? ''); ?>">
                  </div>
                  <div class="col-md-6">
                    <label for="office_email" class="form-label">Office Email</label>
                    <input type="email" class="form-control" id="office_email" name="office_email" value="<?php echo htmlspecialchars($employee['office_email'] ?? ''); ?>">
                  </div>
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
                  <div class="col-md-6">
                    <label for="role" class="form-label">Role <span class="text-danger">*</span></label>
                    <select class="form-select" id="role" name="role_id" required>
                      <option value="" disabled>Select a Role</option>
                      <?php 
                        $roleQuery = "SELECT id, name FROM roles ORDER BY name";
                        $stmtRole = $pdo->query($roleQuery);
                        while ($rowRole = $stmtRole->fetch()) {
                          $selectedRole = ($rowRole['id'] == $employee['role_id']) ? 'selected' : '';
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
                        $supervisorQuery = "SELECT e.emp_id, CONCAT(e.first_name, ' ', e.last_name, ' (', COALESCE(d.title, 'No Designation'), ')') as supervisor_name 
                                           FROM employees e
                                           LEFT JOIN designations d ON e.designation_id = d.id
                                           WHERE e.emp_id != :current_emp_id 
                                           AND (e.exit_date IS NULL OR e.exit_date = '' OR e.exit_date = '0000-00-00')
                                           ORDER BY e.first_name, e.last_name";
                        $stmtSupervisor = $pdo->prepare($supervisorQuery);
                        $stmtSupervisor->execute(['current_emp_id' => $employee['emp_id']]);
                        while ($rowSupervisor = $stmtSupervisor->fetch()) {
                          $selectedSupervisor = ($rowSupervisor['emp_id'] == $employee['supervisor_id']) ? 'selected' : '';
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
                          $selectedDepartment = ($rowDepartment['id'] == $employee['department_id']) ? 'selected' : '';
                          echo "<option value='{$rowDepartment['id']}' $selectedDepartment>{$rowDepartment['name']}</option>";
                        }
                      ?>
                    </select>
                  </div>
                  <div class="col-md-6">
                    <label for="login_access" class="form-label">Login Access <span class="text-danger">*</span></label>
                    <select class="form-select" id="login_access" name="login_access" required>
                      <option disabled>Select Login Access</option>
                      <option value="1" <?php echo ($employee['login_access'] == '1') ? 'selected' : ''; ?>>Granted</option>
                      <option value="0" <?php echo ($employee['login_access'] == '0') ? 'selected' : ''; ?>>Denied</option>
                    </select>
                  </div>
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
            </div>
          </div>

          <div class="col-md-4">
            <div class="text-center mb-3">
              <div class="position-relative d-inline-block">
                <img id="photoPreview" src="<?php 
                  $imagePath = $employee['user_image'] ?: '../../resources/userimg/default-image.jpg';
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
  const pairs = [
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

  function syncFromPermanent(){
    const toggle = document.getElementById('copyPermanentAddress');
    if(!toggle){
      return;
    }

    const getElement = (id) => document.getElementById(id);

    const updateTarget = () => {
      if(!toggle.checked){
        pairs.forEach(([_, targetId]) => {
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

      pairs.forEach(([sourceId, targetId]) => {
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

    const mirrorSourceChanges = () => {
      pairs.forEach(([sourceId, targetId]) => {
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

    toggle.addEventListener('change', updateTarget);
    mirrorSourceChanges();

    const shouldAutoEnable = pairs.every(([sourceId, targetId]) => {
      const source = getElement(sourceId);
      const target = getElement(targetId);
      if(!source || !target){
        return false;
      }
      return source.value && source.value === target.value;
    });

    if(shouldAutoEnable){
      toggle.checked = true;
    }

    updateTarget();
  }

  if(document.readyState === 'loading'){
    document.addEventListener('DOMContentLoaded', syncFromPermanent);
  } else {
    syncFromPermanent();
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