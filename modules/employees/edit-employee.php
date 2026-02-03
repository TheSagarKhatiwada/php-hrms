<?php
$page = 'Edit Employee';
$page = 'employees';
// Include utilities for role check functions
require_once __DIR__ . '/../../includes/session_config.php';
require_once __DIR__ . '/../../includes/utilities.php';
require_once __DIR__ . '/../../includes/db_connection.php';

// Use the standardized role check function
if (!is_admin() && get_user_role() === '0') {
    header('Location: ../../dashboard.php');
    exit();
}

// Ensure database connection is available before proceeding
if (!isset($pdo) || !($pdo instanceof PDO)) {
  $_SESSION['error'] = 'Database connection is unavailable.';
  header('Location: employees.php');
  exit();
}

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

$permissionCatalog = hrms_menu_permissions_catalog();
hrms_sync_permissions_from_catalog();
$rolePermissionCodes = hrms_get_permissions_for_role($employee['role_id'] ?? 0);
$userPermissionOverrides = hrms_get_user_permission_overrides($emp_id, true);
$permissionIdMap = [];
$missingPermissionCodes = [];

try {
  $permIdStmt = $pdo->query('SELECT id, name FROM permissions');
  foreach ($permIdStmt->fetchAll(PDO::FETCH_ASSOC) as $permRow) {
    $code = $permRow['name'] ?? null;
    if ($code) {
      $permissionIdMap[$code] = (int)$permRow['id'];
    }
  }
} catch (PDOException $e) {
  $permissionIdMap = [];
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
$countryRecords = [];
  try {
    $provinceStmt = $pdo->query("SELECT province_id, province_name FROM provinces ORDER BY province_name");
  $provinceRecords = $provinceStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
  foreach ($provinceRecords as $provinceRow) {
    $provinceIndex[$provinceRow['province_id']] = $provinceRow['province_name'];
  }

  $districtStmt = $pdo->query("SELECT district_id, district_name, province_id, postal_code FROM districts ORDER BY district_name");
  $districtRecords = $districtStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
  // Try to load countries list if available in the DB
  try {
    // read all available fields from countries so ISO2/code can be used if present
    $countryStmt = $pdo->query("SELECT * FROM countries ORDER BY name");
    $countryRecords = $countryStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
  } catch (Throwable $e) {
    $countryRecords = [];
  }
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
  
        <div class="card border-0 shadow-sm">
          <div class="card-body">
            <form id="editEmployeeForm" method="POST" action="update-employee.php" enctype="multipart/form-data">
              <input type="hidden" name="emp_id" value="<?php echo htmlspecialchars($employee['emp_id']); ?>">

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
              <?php if (is_admin() || has_permission('manage_user_permissions')): ?>
              <li class="nav-item" role="presentation">
                <button class="nav-link" id="permissions-tab" data-bs-toggle="tab" data-bs-target="#tab-permissions" type="button" role="tab" aria-controls="tab-permissions" aria-selected="false">
                  <i class="fas fa-user-shield me-1"></i>Permissions
                </button>
              </li>
              <?php endif; ?>
            </ul>

            <div class="tab-content border border-top-0 rounded-bottom p-4">
              <div class="tab-pane fade show active" id="tab-personal" role="tabpanel" aria-labelledby="personal-tab">
                <h5 class="fw-semibold mb-3">Personal Information</h5>
                <div class="row gy-3 gx-2">
                  <div class="col-md-4">
                    <label for="empFirstName" class="form-label">First Name <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="empFirstName" name="empFirstName" required value="<?php echo htmlspecialchars($employee['first_name'] ?? ''); ?>">
                  </div>
                  <div class="col-md-4">
                    <label for="empMiddleName" class="form-label">Middle Name</label>
                    <input type="text" class="form-control" id="empMiddleName" name="empMiddleName" value="<?php echo htmlspecialchars($employee['middle_name'] ?? ''); ?>">
                  </div>
                  <div class="col-md-4">
                    <label for="empLastName" class="form-label">Last Name <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="empLastName" name="empLastName" required value="<?php echo htmlspecialchars($employee['last_name'] ?? ''); ?>">
                  </div>
                  <div class="col-md-6">
                    <label for="dob" class="form-label">Date of Birth</label>
                    <input type="date" class="form-control" id="dob" name="dob" value="<?php echo htmlspecialchars($employee['date_of_birth'] ?? ''); ?>">
                  </div>
                  <div class="col-md-6">
                    <label for="gender" class="form-label">Gender <span class="text-danger">*</span></label>
                    <select class="form-select" id="gender" name="gender" required>
                      <option value="" disabled <?php echo empty($displayGender) ? 'selected' : ''; ?>>Select a Gender</option>
                      <option value="M" <?php echo ($displayGender === 'M') ? 'selected' : ''; ?>>Male</option>
                      <option value="F" <?php echo ($displayGender === 'F') ? 'selected' : ''; ?>>Female</option>
                    </select>
                  </div>
                  <div class="col-md-6">
                    <label for="empPhone" class="form-label">Personal Phone <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="empPhone" name="empPhone" required 
                           pattern="^\+?[0-9]*$" title="Phone number can contain only numbers and the + sign" 
                           value="<?php echo htmlspecialchars($employee['phone'] ?? ''); ?>">
                  </div>
                  <div class="col-md-6">
                    <label for="empEmail" class="form-label">Personal Email <span class="text-danger">*</span></label>
                    <input type="email" class="form-control" id="empEmail" name="empEmail" required value="<?php echo htmlspecialchars($employee['email'] ?? ''); ?>">
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
                        <option value="<?php echo $value; ?>" <?php echo (!empty($employee['marital_status']) && $employee['marital_status'] === $value) ? 'selected' : ''; ?>><?php echo $label; ?></option>
                      <?php endforeach; ?>
                    </select>
                  </div>
                  <div class="col-md-6 <?php echo (!empty($employee['marital_status']) && $employee['marital_status'] === 'married') ? '' : 'd-none'; ?>" id="spouseFieldWrapper">
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
                    <select class="form-select" id="emergency_contact_relationship" name="emergency_contact_relationship">
                      <option value="">Select Relationship</option>
                      <?php foreach ($relationshipOptions as $option): ?>
                        <option value="<?php echo htmlspecialchars($option); ?>" <?php echo (!empty($employee['emergency_contact_relationship']) && $employee['emergency_contact_relationship'] === $option) ? 'selected' : ''; ?>><?php echo $option; ?></option>
                      <?php endforeach; ?>
                      <?php if (!empty($employee['emergency_contact_relationship']) && !in_array($employee['emergency_contact_relationship'], $relationshipOptions, true)): ?>
                        <option value="<?php echo htmlspecialchars($employee['emergency_contact_relationship']); ?>" selected><?php echo htmlspecialchars($employee['emergency_contact_relationship']); ?></option>
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
                        <option value="<?php echo $value; ?>" <?php echo (!empty($employee['blood_group']) && $employee['blood_group'] === $value) ? 'selected' : ''; ?>><?php echo $label; ?></option>
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
                              $permanentDistrictFound = false;
                              foreach ($districtRecords as $districtRow):
                                $districtName = $districtRow['district_name'];
                                $provinceName = $provinceIndex[$districtRow['province_id']] ?? '';
                                $postalCode = $districtRow['postal_code'] ?? '';
                                $selected = (!empty($employee['permanent_district']) && $employee['permanent_district'] === $districtName) ? 'selected' : '';
                                if ($selected) {
                                  $permanentDistrictFound = true;
                                }
                            ?>
                              <option value="<?php echo htmlspecialchars($districtName); ?>" data-province="<?php echo htmlspecialchars($provinceName); ?>" data-postal="<?php echo htmlspecialchars($postalCode); ?>" <?php echo $selected; ?>>
                                <?php echo htmlspecialchars($districtName); ?>
                              </option>
                            <?php endforeach; ?>
                            <?php if (!$permanentDistrictFound && !empty($employee['permanent_district'])): ?>
                              <option value="<?php echo htmlspecialchars($employee['permanent_district']); ?>" data-province="<?php echo htmlspecialchars($employee['permanent_state'] ?? ''); ?>" data-postal="<?php echo htmlspecialchars($employee['permanent_postal_code'] ?? ''); ?>" selected>
                                <?php echo htmlspecialchars($employee['permanent_district']); ?>
                              </option>
                            <?php endif; ?>
                          </select>
                        </div>
                        <div class="col-sm-6">
                          <label class="form-label">Province / State</label>
                          <input type="text" class="form-control" id="permanent_state" name="permanent_state" placeholder="Select District" value="<?php echo htmlspecialchars($employee['permanent_state'] ?? ''); ?>">
                        </div>
                        <div class="col-sm-6">
                          <label class="form-label">Postal Code</label>
                          <input type="text" class="form-control" id="permanent_postal_code" name="permanent_postal_code" placeholder="Postal Code" value="<?php echo htmlspecialchars($employee['permanent_postal_code'] ?? ''); ?>">
                        </div>
                        <div class="col-sm-6">
                          <label class="form-label">Country</label>
                          <?php if (!empty($countryRecords)): ?>
                          <select class="form-select" id="permanent_country" name="permanent_country">
                            <?php foreach ($countryRecords as $c):
                              $cName = $c['name'] ?? $c['country_name'] ?? $c['country'] ?? '';
                              if (preg_match('/^([A-Za-z]{2})[\s:.-]+(.+)$/u', trim($cName), $m)) {
                                $displayName = $m[2];
                              } else {
                                $displayName = $cName;
                              }
                              $selected = (!empty($employee['permanent_country']) && $employee['permanent_country'] === $cName) || (empty($employee['permanent_country']) && strtolower($cName) === 'nepal') ? 'selected' : '';
                            ?>
                              <option value="<?php echo htmlspecialchars($cName); ?>" <?php echo $selected; ?>><?php echo htmlspecialchars($displayName); ?></option>
                            <?php endforeach; ?>
                          </select>
                          <?php else:
                            $fallbackCountries = ['Nepal','India','United States','United Kingdom','Pakistan','Bangladesh','China','Japan','Australia','Canada'];
                          ?>
                          <select class="form-select" id="permanent_country" name="permanent_country">
                            <?php foreach ($fallbackCountries as $cName):
                              $selected = (!empty($employee['permanent_country']) && $employee['permanent_country'] === $cName) || (empty($employee['permanent_country']) && strtolower($cName) === 'nepal') ? 'selected' : '';
                              $displayName = $cName;
                            ?>
                              <option value="<?php echo htmlspecialchars($cName); ?>" <?php echo $selected; ?>><?php echo htmlspecialchars($displayName); ?></option>
                            <?php endforeach; ?>
                          </select>
                          <?php endif; ?>
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
                              $currentDistrictFound = false;
                              foreach ($districtRecords as $districtRow):
                                $districtName = $districtRow['district_name'];
                                $provinceName = $provinceIndex[$districtRow['province_id']] ?? '';
                                $postalCode = $districtRow['postal_code'] ?? '';
                                $selected = (!empty($employee['current_district']) && $employee['current_district'] === $districtName) ? 'selected' : '';
                                if ($selected) { $currentDistrictFound = true; }
                            ?>
                              <option value="<?php echo htmlspecialchars($districtName); ?>" data-province="<?php echo htmlspecialchars($provinceName); ?>" data-postal="<?php echo htmlspecialchars($postalCode); ?>" <?php echo $selected; ?>>
                                <?php echo htmlspecialchars($districtName); ?>
                              </option>
                            <?php endforeach; ?>
                            <?php if (!$currentDistrictFound && !empty($employee['current_district'])): ?>
                              <option value="<?php echo htmlspecialchars($employee['current_district']); ?>" data-province="<?php echo htmlspecialchars($employee['current_state'] ?? ''); ?>" data-postal="<?php echo htmlspecialchars($employee['current_postal_code'] ?? ''); ?>" selected>
                                <?php echo htmlspecialchars($employee['current_district']); ?>
                              </option>
                            <?php endif; ?>
                          </select>
                        </div>
                        <div class="col-sm-6">
                          <label class="form-label">Province / State</label>
                          <input type="text" class="form-control" id="current_state" name="current_state" placeholder="Select District" value="<?php echo htmlspecialchars($employee['current_state'] ?? ''); ?>">
                        </div>
                        <div class="col-sm-6">
                          <label class="form-label">Postal Code</label>
                          <input type="text" class="form-control" id="current_postal_code" name="current_postal_code" placeholder="Postal Code" value="<?php echo htmlspecialchars($employee['current_postal_code'] ?? ''); ?>">
                        </div>
                        <div class="col-sm-6">
                          <label class="form-label">Country</label>
                          <?php if (!empty($countryRecords)): ?>
                          <select class="form-select" id="current_country" name="current_country">
                            <?php foreach ($countryRecords as $c):
                              $cName = $c['name'] ?? $c['country_name'] ?? $c['country'] ?? '';
                              if (preg_match('/^([A-Za-z]{2})[\s:.-]+(.+)$/u', trim($cName), $m)) {
                                $displayName = $m[2];
                              } else {
                                $displayName = $cName;
                              }
                              $selected = (!empty($employee['current_country']) && $employee['current_country'] === $cName) || (empty($employee['current_country']) && strtolower($cName) === 'nepal') ? 'selected' : '';
                            ?>
                              <option value="<?php echo htmlspecialchars($cName); ?>" <?php echo $selected; ?>><?php echo htmlspecialchars($displayName); ?></option>
                            <?php endforeach; ?>
                          </select>
                          <?php else:
                            $fallbackCountries = ['Nepal','India','United States','United Kingdom','Pakistan','Bangladesh','China','Japan','Australia','Canada'];
                          ?>
                          <select class="form-select" id="current_country" name="current_country">
                            <?php foreach ($fallbackCountries as $cName):
                              $selected = (!empty($employee['current_country']) && $employee['current_country'] === $cName) || (empty($employee['current_country']) && strtolower($cName) === 'nepal') ? 'selected' : '';
                            ?>
                              <option value="<?php echo htmlspecialchars($cName); ?>" <?php echo $selected; ?>><?php echo htmlspecialchars($cName); ?></option>
                            <?php endforeach; ?>
                          </select>
                          <?php endif; ?>
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
                    <div class="d-flex align-items-center justify-content-between">
                      <label class="form-label mb-0">Web Check-In/Checkout</label>
                      <div class="form-check form-switch m-0">
                        <input class="form-check-input" type="checkbox" id="allow_web_attendance" name="allow_web_attendance" value="1" <?php echo !empty($employee['allow_web_attendance']) ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="allow_web_attendance">Allow</label>
                      </div>
                    </div>
                    <small class="text-muted d-block mt-1">Leave disabled for employees who must rely on biometric devices only.</small>
                  </div>
                </div>
              </div>
              <?php if (is_admin() || has_permission('manage_user_permissions')): ?>
              <div class="tab-pane fade" id="tab-permissions" role="tabpanel" aria-labelledby="permissions-tab">
                <h5 class="fw-semibold mb-3">Permission Overrides</h5>
                <p class="text-muted small">This view mirrors the Roles &amp; Permissions &rarr; Permission Overrides page. Leave a permission on Inherit to rely on the employee&rsquo;s role; switch to Allow or Deny for explicit overrides.</p>
                <?php if (!empty($permissionCatalog['sections'])): ?>
                  <div class="accordion" id="employeePermissionAccordion">
                    <?php foreach ($permissionCatalog['sections'] as $sectionKey => $section): ?>
                      <?php
                        $sectionChildren = $section['children'] ?? [];
                        if (empty($sectionChildren)) {
                          continue;
                        }
                        $accordionId = 'emp-perm-' . preg_replace('/[^a-zA-Z0-9_-]/', '-', (string)$sectionKey);
                      ?>
                      <div class="accordion-item mb-3">
                        <h2 class="accordion-header" id="heading-<?php echo htmlspecialchars($accordionId); ?>">
                          <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse-<?php echo htmlspecialchars($accordionId); ?>">
                            <i class="<?php echo htmlspecialchars($section['icon'] ?? 'fas fa-layer-group'); ?> me-2"></i>
                            <?php echo htmlspecialchars($section['label'] ?? ucfirst((string)$sectionKey)); ?>
                          </button>
                        </h2>
                        <div id="collapse-<?php echo htmlspecialchars($accordionId); ?>" class="accordion-collapse collapse" data-bs-parent="#employeePermissionAccordion">
                          <div class="accordion-body">
                            <?php foreach ($sectionChildren as $child): ?>
                              <?php $childPermissions = $child['permissions'] ?? []; ?>
                              <?php if (empty($childPermissions)) { continue; } ?>
                              <div class="mb-4">
                                <h6 class="fw-semibold mb-2"><?php echo htmlspecialchars($child['label'] ?? 'Permissions'); ?></h6>
                                <div class="table-responsive">
                                  <table class="table table-sm align-middle">
                                    <thead>
                                      <tr>
                                        <th>Permission</th>
                                        <th>Description</th>
                                        <th>Role default</th>
                                        <th class="text-center">Allow</th>
                                        <th class="text-center">Deny</th>
                                        <th class="text-center">Inherit</th>
                                      </tr>
                                    </thead>
                                    <tbody>
                                      <?php foreach ($childPermissions as $permission): ?>
                                        <?php
                                          $code = $permission['code'] ?? null;
                                          if (!$code) {
                                              continue;
                                          }
                                          $roleHas = in_array($code, $rolePermissionCodes, true);
                                          $overrideValue = $userPermissionOverrides[$code] ?? null;
                                          $allowChecked = ($overrideValue === 1);
                                          $denyChecked = ($overrideValue === 0);
                                          $inheritChecked = (!$allowChecked && !$denyChecked);
                                          $missing = !isset($permissionIdMap[$code]);
                                          if ($missing && !in_array($code, $missingPermissionCodes, true)) {
                                              $missingPermissionCodes[] = $code;
                                          }
                                        ?>
                                        <tr>
                                          <td class="fw-medium">
                                            <?php echo htmlspecialchars($permission['label'] ?? $code); ?>
                                            <div class="text-muted small">Code: <?php echo htmlspecialchars($code); ?></div>
                                          </td>
                                          <td><?php echo htmlspecialchars($permission['description'] ?? ''); ?></td>
                                          <td>
                                            <?php if ($roleHas): ?>
                                              <span class="badge bg-success">Granted</span>
                                            <?php else: ?>
                                              <span class="badge bg-secondary">Not granted</span>
                                            <?php endif; ?>
                                          </td>
                                          <td class="text-center">
                                            <input type="radio" class="form-check-input" name="perm_override[<?php echo htmlspecialchars($code); ?>]" value="allow" <?php echo $allowChecked ? 'checked' : ''; ?> <?php echo $missing ? 'disabled' : ''; ?>>
                                          </td>
                                          <td class="text-center">
                                            <input type="radio" class="form-check-input" name="perm_override[<?php echo htmlspecialchars($code); ?>]" value="deny" <?php echo $denyChecked ? 'checked' : ''; ?> <?php echo $missing ? 'disabled' : ''; ?>>
                                          </td>
                                          <td class="text-center">
                                            <input type="radio" class="form-check-input" name="perm_override[<?php echo htmlspecialchars($code); ?>]" value="inherit" <?php echo $inheritChecked ? 'checked' : ''; ?> <?php echo $missing ? 'disabled' : ''; ?>>
                                          </td>
                                        </tr>
                                      <?php endforeach; ?>
                                    </tbody>
                                  </table>
                                </div>
                              </div>
                            <?php endforeach; ?>
                          </div>
                        </div>
                      </div>
                    <?php endforeach; ?>
                  </div>
                <?php else: ?>
                  <p class="text-muted">No permissions are defined in the catalog.</p>
                <?php endif; ?>
                <div class="mt-3 d-flex flex-wrap justify-content-between align-items-center gap-2">
                  <div>
                    <?php if (!empty($missingPermissionCodes)): ?>
                      <span class="text-danger small">Missing permission codes: <?php echo htmlspecialchars(implode(', ', $missingPermissionCodes)); ?></span>
                    <?php endif; ?>
                  </div>
                  <button type="submit" name="save_user_permissions" value="1" class="btn btn-primary">Save Permission Overrides</button>
                </div>
              </div>
              <?php endif; ?>
            </div>
          </div>

          <div class="col-md-4">
            <div class="text-center mb-3">
              <div class="position-relative d-inline-block">
                <img id="photoPreview" src="<?php 
                  // Ensure we use the same base path as other pages ($home) so images resolve correctly
                  $imagePath = '';
                  if (!empty($employee['user_image'])) {
                    // If value looks like an absolute URL, use it. Otherwise prefix with $home
                    if (str_starts_with($employee['user_image'], 'http') || str_starts_with($employee['user_image'], '/')) {
                      $imagePath = $employee['user_image'];
                    } else {
                      $imagePath = (isset($home) ? $home : '../../') . ltrim($employee['user_image'], '/');
                    }
                  } else {
                    $imagePath = (isset($home) ? $home : '../../') . 'resources/userimg/default-image.jpg';
                  }
                  echo htmlspecialchars($imagePath);
                ?>" 
                     alt="Employee Photo" class="rounded-circle img-thumbnail" 
                     style="width: 200px; height: 200px; object-fit: cover;"
                     onerror="this.src='<?php echo htmlspecialchars((isset($home) ? $home : '../../') . 'resources/userimg/default-image.jpg'); ?>'">
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
<style>
#cropModal .modal-dialog {
  max-width: min(1024px, calc(100vw - 2rem));
}
#cropModal .modal-content {
  border-radius: 18px;
  border: 1px solid rgba(255,255,255,0.06);
  background: #1b1d25;
  color: #f2f2f2;
}
#cropModal .modal-header,
#cropModal .modal-footer {
  border-color: rgba(255,255,255,0.08);
}
#cropModal .img-container {
  width: 100%;
  min-height: 460px;
  max-height: 70vh;
  display: flex;
  align-items: center;
  justify-content: center;
  background: #0d0f16;
  border-radius: 16px;
  overflow: hidden;
  box-shadow: inset 0 0 0 1px rgba(255,255,255,0.04);
}
#cropModal .img-container img {
  max-width: 100%;
  max-height: 100%;
}
#cropModal .img-controls {
  display: flex;
  flex-direction: column;
  gap: 0.85rem;
  margin-top: 1.25rem;
}
#cropModal .control-row {
  width: 100%;
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 0.45rem;
  padding: 0.45rem 0.85rem;
  border-radius: 999px;
  background: linear-gradient(135deg, rgba(15,17,25,0.92), rgba(13,15,22,0.85));
  border: 1px solid rgba(255,255,255,0.08);
  box-shadow: 0 14px 30px rgba(0,0,0,0.35);
}
#cropModal .control-row.control-row--primary {
  justify-content: space-between;
  align-items: stretch;
  gap: 0.75rem;
  flex-wrap: wrap;
}
#cropModal .control-row--primary .control-cluster {
  display: flex;
  flex-direction: column;
  gap: 0.35rem;
  flex: 1 1 180px;
}
#cropModal .control-row--primary .control-cluster .control-pair {
  display: flex;
  gap: 0.35rem;
  justify-content: center;
}
#cropModal .control-row--primary .control-cluster--left .control-pair {
  justify-content: flex-start;
}
#cropModal .control-row--primary .control-cluster--right .control-pair {
  justify-content: flex-end;
}
#cropModal .control-row--primary .control-btn.control-btn-primary {
  min-width: 120px;
  align-self: center;
  margin: 0 0.5rem;
}
@media (max-width: 768px) {
  #cropModal .control-row.control-row--primary {
    flex-direction: column;
  }
  #cropModal .control-row--primary .control-cluster,
  #cropModal .control-row--primary .control-pair {
    align-items: center;
    justify-content: center !important;
  }
  #cropModal .control-row--primary .control-btn.control-btn-primary {
    width: 100%;
    margin: 0;
  }
}
#cropModal .control-row.control-row--secondary {
  background: linear-gradient(135deg, rgba(15,17,25,0.75), rgba(13,15,22,0.65));
  border-style: dashed;
  border-color: rgba(255,255,255,0.12);
}
#cropModal .control-row.control-row--slider {
  border-radius: 22px;
  padding: 0.75rem 1rem;
  background: rgba(7,8,12,0.85);
  border: 1px solid rgba(255,255,255,0.06);
  gap: 0.85rem;
}
#cropModal .control-row--slider .control-btn {
  min-width: 72px;
  height: 46px;
}
#cropModal .control-btn {
  border: 1px solid rgba(255,255,255,0.12);
  border-radius: 999px;
  background: rgba(255,255,255,0.02);
  color: #f7f7f7;
  padding: 0.35rem 0.9rem;
  font-size: 0.9rem;
  font-weight: 500;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 0.35rem;
  transition: border-color 0.15s ease, background 0.15s ease;
}
#cropModal .control-btn.control-btn-icon {
  width: 38px;
  height: 38px;
  padding: 0;
}
#cropModal .control-btn.control-btn-primary {
  background: linear-gradient(135deg, #3a3f52, #252839);
  border-color: rgba(138,92,246,0.45);
  box-shadow: 0 8px 15px rgba(138,92,246,0.3);
}
#cropModal .control-btn:hover {
  border-color: rgba(138,92,246,0.65);
  background: rgba(138,92,246,0.08);
}
#cropModal .control-label {
  text-transform: uppercase;
  font-size: 0.75rem;
  letter-spacing: 0.1em;
  color: rgba(255,255,255,0.6);
  margin-right: 0.35rem;
}
#cropModal .rotation-scale {
  position: relative;
  flex: 1;
  min-width: 220px;
  --rotation-shift: 0px;
  --tick-unit: 14px;
  --sequence-width: calc(var(--tick-unit) * 10);
  --half-sequence: calc(var(--tick-unit) * 5);
  --long-height: 24px;
  --mid-height: 18px;
  --short-height: 11px;
}
#cropModal .rotation-scale .rotation-ruler {
  position: relative;
  width: 100%;
  height: 34px;
}
#cropModal .rotation-scale .rotation-ruler::before {
  content: '';
  position: absolute;
  inset: 6px 4%;
  border-radius: 999px;
  background: linear-gradient(90deg, rgba(255,255,255,0) 0%, rgba(255,255,255,0.25) 50%, rgba(255,255,255,0) 100%);
  opacity: 0.6;
  pointer-events: none;
}
#cropModal .rotation-scale .tick-track {
  position: absolute;
  left: 4%;
  right: 4%;
  top: 50%;
  height: var(--long-height);
  transform: translateY(-50%);
  pointer-events: none;
  background-repeat: repeat-x, repeat-x, repeat-x;
  background-image:
    linear-gradient(90deg, rgba(255,255,255,0.85) 0 2px, transparent 2px),
    linear-gradient(90deg, rgba(255,255,255,0.65) 0 2px, transparent 2px),
    linear-gradient(90deg, rgba(255,255,255,0.45) 0 2px, transparent 2px);
  background-size:
    var(--sequence-width) var(--long-height),
    var(--sequence-width) var(--mid-height),
    var(--tick-unit) var(--short-height);
  background-position:
    calc(50% + var(--rotation-shift)) center,
    calc(50% + var(--rotation-shift) + var(--half-sequence)) center,
    calc(50% + var(--rotation-shift)) center;
  opacity: 0.85;
}
#cropModal .rotation-scale .rotation-ruler .ruler-line {
  position: absolute;
  left: 6%;
  right: 6%;
  top: 50%;
  height: 2px;
  background: rgba(255,255,255,0.4);
  transform: translateY(-50%);
  z-index: 0;
  box-shadow: 0 0 8px rgba(255,255,255,0.2);
}
#cropModal .rotation-scale .rotation-ruler .ruler-mid {
  position: absolute;
  top: 6px;
  bottom: 6px;
  left: 50%;
  width: 2px;
  background: rgba(255,255,255,0.95);
  transform: translateX(-50%);
  box-shadow: 0 0 14px rgba(255,255,255,0.35);
  z-index: 1;
}
#cropModal .rotation-scale .rotation-ruler .ruler-base {
  position: absolute;
  left: 6%;
  right: 6%;
  bottom: 6px;
  height: 1px;
  background: linear-gradient(90deg, rgba(255,255,255,0), rgba(255,255,255,0.35), rgba(255,255,255,0));
  opacity: 0.7;
  z-index: 0;
}
#cropModal .rotation-scale input[type="range"] {
  position: absolute;
  inset: 0;
  width: 100%;
  height: 100%;
  appearance: none;
  background: transparent;
  z-index: 2;
}
#cropModal .rotation-scale input[type="range"]:focus {
  outline: none;
}
#cropModal .rotation-scale input[type="range"]::-webkit-slider-thumb {
  appearance: none;
  width: 12px;
  height: 32px;
  border-radius: 8px;
  background: linear-gradient(180deg, #ffffff, #c5c5c5);
  border: 1px solid rgba(0,0,0,0.35);
  box-shadow: 0 6px 14px rgba(0,0,0,0.45);
}
#cropModal .rotation-scale input[type="range"]::-moz-range-thumb {
  width: 12px;
  height: 32px;
  border-radius: 8px;
  background: linear-gradient(180deg, #ffffff, #c5c5c5);
  border: 1px solid rgba(0,0,0,0.35);
  box-shadow: 0 6px 14px rgba(0,0,0,0.45);
}
#cropModal .rotation-scale input[type="range"]::-webkit-slider-runnable-track,
#cropModal .rotation-scale input[type="range"]::-moz-range-track {
  height: 2px;
  background: transparent;
}
#cropModal .rotation-value {
  font-variant-numeric: tabular-nums;
  font-size: 0.95rem;
  color: rgba(255,255,255,0.85);
  min-width: 54px;
  text-align: center;
}
#cropModal .rotation-value {
  font-variant-numeric: tabular-nums;
  font-size: 0.95rem;
  color: rgba(255,255,255,0.85);
  min-width: 54px;
  text-align: center;
  position: absolute;
  top: -1.35rem;
  left: 50%;
  transform: translateX(-50%);
  pointer-events: none;
}
#cropModal #cropWarning {
  flex: 1;
}
</style>
<div class="modal fade" id="cropModal" tabindex="-1" aria-labelledby="cropModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="cropModalLabel">Crop Image</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div class="img-container">
          <img id="imageToCrop" src="" alt="Image to Crop" style="max-width: 100%;">
        </div>
        <div class="img-controls">
          <div class="control-panel" role="group" aria-label="Crop controls">
            <div class="control-row control-row--slider" role="group" aria-label="Rotation fine control">
              <button type="button" class="control-btn" id="nudgeRotateLeft" title="Rotate left 5°">↺ 5°</button>
              <div class="rotation-scale" aria-hidden="false">
                <span class="rotation-value" id="rotationValue">0°</span>
                <div class="rotation-ruler" aria-hidden="true">
                  <span class="tick-track" aria-hidden="true"></span>
                  <span class="ruler-line" aria-hidden="true"></span>
                  <span class="ruler-mid" aria-hidden="true"></span>
                  <span class="ruler-base" aria-hidden="true"></span>
                  <input type="range" id="rotateSlider" min="-180" max="180" step="1" value="0" aria-label="Rotation" />
                </div>
              </div>
              <button type="button" class="control-btn" id="nudgeRotateRight" title="Rotate right 5°">↻ 5°</button>
            </div>
            <div class="control-row control-row--primary" role="toolbar" aria-label="Primary crop toolbar">
                <div class="control-pair" role="group" aria-label="90 degree rotation">
                  <button type="button" class="control-btn control-btn-icon" id="rotateLeft" title="Rotate left 90°">⟲</button>
                  <button type="button" class="control-btn control-btn-icon" id="rotateRight" title="Rotate right 90°">⟳</button>
                </div>
                <div class="control-pair" role="group" aria-label="Zoom controls">
                  <button type="button" class="control-btn control-btn-icon" id="zoomOut" title="Zoom out">🔍−</button>
                  <button type="button" class="control-btn control-btn-icon" id="zoomIn" title="Zoom in">🔍+</button>
                </div>
              <button type="button" class="control-btn control-btn-primary" id="resetCrop" title="Reset crop">Reset</button>
                <div class="control-pair" role="group" aria-label="Fit and aspect">
                  <button type="button" class="control-btn" id="fitCrop" title="Fit to frame">⤢</button>
                  <button type="button" class="control-btn" id="oneToOne" title="1:1 Aspect Ratio">1:1</button>
                </div>
                <div class="control-pair" role="group" aria-label="Flip controls">
                  <button type="button" class="control-btn" id="flipHorizontal" title="Flip horizontally">⇋</button>
                  <button type="button" class="control-btn" id="flipVertical" title="Flip vertically">⇅</button>
                </div>
            </div>
          </div>
            <div id="cropWarning" class="text-danger small" style="display:none;">Invalid image (allowed: png/jpeg/webp, max 5MB)</div>
          </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-primary" id="cropButton" disabled>Crop & Save</button>
      </div>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>

<script src="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.12/cropper.min.js"></script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.12/cropper.min.css">

<script>
// Disable Web Check-In unless login access is granted
(() => {
  const loginSelect = document.getElementById('login_access');
  const webToggle = document.getElementById('allow_web_attendance');
  if (!loginSelect || !webToggle) return;
  const sync = () => {
    const granted = loginSelect.value === '1';
    webToggle.disabled = !granted;
    if (!granted) {
      webToggle.checked = false;
    }
  };
  loginSelect.addEventListener('change', sync);
  sync();
})();

let cropper;
let cropperResizeTimeout;
let flipX = 1;
let flipY = 1;
let rotationAngle = 0;

const rotationSlider = document.getElementById('rotateSlider');
const rotationValue = document.getElementById('rotationValue');
const rotationScale = document.querySelector('#cropModal .rotation-scale');

const setRotationShift = (angle) => {
  if (!rotationScale) {
    return;
  }
  // Recompute tick sizing so 3 long-ticks map to one side (180°) -> 6 long ticks across full track
  const trackPct = 0.92; // left:4% right:4% earlier in CSS
  const trackWidth = rotationScale.clientWidth * trackPct;
  // We want 6 long ticks across full width -> each long spacing is trackWidth / 6
  const longSpacing = Math.max(28, trackWidth / 6); // enforce a minimum so ticks stay visible
  const tickUnit = longSpacing / 10; // pattern consists of 10 units: long + 4 short + mid + 4 short
  rotationScale.style.setProperty('--tick-unit', `${tickUnit}px`);
  rotationScale.style.setProperty('--sequence-width', `${longSpacing}px`);
  rotationScale.style.setProperty('--half-sequence', `${longSpacing / 2}px`);

  // Compute travel limited by sequence and container for a stable visual range
  const maxBySeq = longSpacing / 2;
  const maxByContainer = rotationScale.clientWidth * 0.4;
  const travel = Math.min(maxBySeq, maxByContainer);
  let clamped = Math.max(-180, Math.min(180, angle));
  let shift = (clamped / 180) * travel;
  // Ensure we never exceed visual travel bounds
  shift = Math.max(-travel, Math.min(travel, shift));
  rotationScale.style.setProperty('--rotation-shift', `${shift}px`);
};

const setRotationDisplay = (angle) => {
  // Always work with a normalized/clamped value for display and visuals
  const clamped = normalizeAngle(Number(angle));
  if (rotationSlider) {
    rotationSlider.value = clamped;
  }
  if (rotationValue) {
    rotationValue.textContent = `${clamped}°`;
  }
  setRotationShift(clamped);
  // Disable/enable nudge buttons at limits
  const leftBtn = document.getElementById('nudgeRotateLeft');
  const rightBtn = document.getElementById('nudgeRotateRight');
  if (leftBtn) leftBtn.disabled = clamped <= -180;
  if (rightBtn) rightBtn.disabled = clamped >= 180;
};

const normalizeAngle = (angle) => {
  if (Number.isNaN(angle)) {
    return 0;
  }
  return Math.max(-180, Math.min(180, angle));
};

const updateRotation = (angle) => {
  rotationAngle = normalizeAngle(angle);
  setRotationDisplay(rotationAngle);
  if (!cropper) {
    return;
  }
  cropper.rotateTo(rotationAngle);
};

const enforceAspectRatio = () => {
  if (!cropper) {
    return;
  }
  cropper.setAspectRatio(1);
};

setRotationDisplay(rotationAngle);

const bindCropperControls = () => {
  const bind = (id, handler) => {
    const el = document.getElementById(id);
    if (el) {
      el.onclick = handler;
    }
  };

  const safe = (fn) => () => {
    if (!cropper) {
      return;
    }
    fn();
  };

  bind('rotateLeft', safe(() => updateRotation(rotationAngle - 90)));
  bind('rotateRight', safe(() => updateRotation(rotationAngle + 90)));
  bind('zoomIn', safe(() => cropper.zoom(0.12)));
  bind('zoomOut', safe(() => cropper.zoom(-0.12)));
  bind('flipHorizontal', safe(() => {
    flipX = -flipX;
    cropper.scaleX(flipX);
  }));
  bind('flipVertical', safe(() => {
    flipY = -flipY;
    cropper.scaleY(flipY);
  }));
  bind('fitCrop', safe(() => {
    const canvasData = cropper.getCanvasData();
    if (!canvasData.width || !canvasData.height) {
      return;
    }
    const size = Math.min(canvasData.width, canvasData.height);
    cropper.setCropBoxData({
      left: canvasData.left + (canvasData.width - size) / 2,
      top: canvasData.top + (canvasData.height - size) / 2,
      width: size,
      height: size
    });
  }));
  bind('resetCrop', safe(() => {
    cropper.reset();
    flipX = 1;
    flipY = 1;
    rotationAngle = 0;
    updateRotation(0);
    enforceAspectRatio();
  }));
  bind('nudgeRotateLeft', safe(() => updateRotation(rotationAngle - 5)));
  bind('nudgeRotateRight', safe(() => updateRotation(rotationAngle + 5)));
  if (rotationSlider) {
    rotationSlider.addEventListener('input', (e) => {
      if (!cropper) {
        return;
      }
      const val = parseInt(e.target.value, 10);
      updateRotation(Number.isNaN(val) ? 0 : val);
    });
  }
};

bindCropperControls();

function previewImage(event) {
  const file = event.target.files[0];
  if (file) {
    const maxSize = 5 * 1024 * 1024; // 5MB
    const allowed = ['image/jpeg','image/png','image/webp'];
    const warningEl = document.getElementById('cropWarning');
    if (!allowed.includes(file.type) || file.size > maxSize) {
      if (warningEl) { warningEl.style.display = 'inline'; }
      alert('Image must be PNG/JPEG/WEBP and <= 5MB');
      return;
    }
    if (warningEl) { warningEl.style.display = 'none'; }

    const reader = new FileReader();
    reader.onload = function(e) {
      document.getElementById('imageToCrop').src = e.target.result;
      const cropModal = new bootstrap.Modal(document.getElementById('cropModal'));
      cropModal.show();
      
      // Destroy previous cropper if exists
      if (cropper) {
        cropper.destroy();
      }
      
      // Initialize cropper with larger container and smoother behavior
      cropper = new Cropper(document.getElementById('imageToCrop'), {
        aspectRatio: 1,
        viewMode: 2,
        autoCropArea: 0.85,
        responsive: true,
        guides: true,
        highlight: false,
        background: false,
        cropBoxMovable: true,
        cropBoxResizable: true,
        minCropBoxWidth: 220,
        minCropBoxHeight: 220,
        minContainerWidth: 600,
        minContainerHeight: 430
      });
      flipX = 1;
      flipY = 1;
      rotationAngle = 0;
      cropper.scaleX(flipX);
      cropper.scaleY(flipY);
      updateRotation(0);
      enforceAspectRatio();

      // Enable controls
      document.getElementById('cropButton').disabled = false;
    }
    reader.readAsDataURL(file);
  }
}

document.getElementById('cropButton').addEventListener('click', function() {
  if (cropper) {
    // Disable crop button while processing
    const cb = document.getElementById('cropButton');
    if (cb) cb.disabled = true;
    const canvas = cropper.getCroppedCanvas({ width: 400, height: 400 });

    // Export JPEG for smaller size (quality 0.85)
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
        rotationAngle = 0;
        setRotationDisplay(0);
        if (cb) cb.disabled = false;
      }
      reader.readAsDataURL(blob);
    }, 'image/jpeg', 0.85);
  }
});

window.addEventListener('resize', () => {
  if (!cropper) {
    return;
  }
  clearTimeout(cropperResizeTimeout);
  cropperResizeTimeout = setTimeout(() => {
    if (!cropper) {
      return;
    }
    const currentData = cropper.getData();
    cropper.reset();
    cropper.setData(currentData);
  }, 150);
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
      { selectId: 'permanent_district', hiddenId: 'permanent_state', displayId: 'permanent_state', postalHiddenId: 'permanent_postal_code', postalDisplayId: 'permanent_postal_code' },
      { selectId: 'current_district', hiddenId: 'current_state', displayId: 'current_state', postalHiddenId: 'current_postal_code', postalDisplayId: 'current_postal_code' }
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

    // Disable district auto-fill if the selected country is not Nepal
    const watchCountry = (countryId, config) => {
      const countryEl = document.getElementById(countryId);
      let districtEl = document.getElementById(config.selectId);
      const stateEl = document.getElementById(config.displayId);
      const postalEl = document.getElementById(config.postalDisplayId);
      if(!countryEl || !districtEl) return;

      const apply = () => {
        const val = (countryEl.value || '').toString().trim().toLowerCase();
        const isNepal = val === 'nepal' || val === '';
        if(!isNepal){
          districtEl.setAttribute('disabled','disabled');
          if(stateEl){
            stateEl.removeAttribute('readonly');
            stateEl.value = '';
            if(stateEl.placeholder === 'Select District') stateEl.placeholder = '';
          }
          if(postalEl){
            postalEl.removeAttribute('readonly');
            postalEl.value = '';
            if(postalEl.placeholder === 'Postal Code') postalEl.placeholder = '';
          }
          if (districtEl.tagName && districtEl.tagName.toLowerCase() === 'select') {
            const optionsHtml = districtEl.innerHTML || '';
            // capture currently-selected value and data attributes so we can restore them later
            const selectedValue = districtEl.value || '';
            const selectedOption = districtEl.options[districtEl.selectedIndex] || null;
            const selectedProvince = selectedOption ? (selectedOption.getAttribute('data-province') || '') : '';
            const selectedPostal = selectedOption ? (selectedOption.getAttribute('data-postal') || '') : '';
            const wrapper = districtEl.parentNode;
            const input = document.createElement('input');
            input.type = 'text';
            input.className = 'form-control';
            input.id = districtEl.id;
            input.name = districtEl.name;
            input.placeholder = 'District';
            input.value = '';
            if (optionsHtml) input.dataset.options = optionsHtml;
            // preserve selected value and its metadata so we can restore when switching back to Nepal
            if (selectedValue) input.dataset.selected = selectedValue;
            if (selectedProvince) input.dataset.selectedProvince = selectedProvince;
            if (selectedPostal) input.dataset.selectedPostal = selectedPostal;
            wrapper.replaceChild(input, districtEl);
            districtEl = document.getElementById(config.selectId);
          } else {
            districtEl.value = '';
          }
        } else {
          if (districtEl.tagName && districtEl.tagName.toLowerCase() !== 'select') {
            const wrapper = districtEl.parentNode;
            const select = document.createElement('select');
            select.className = 'form-select';
            select.id = districtEl.id;
            select.name = districtEl.name;
            const optionsHtml = districtEl.dataset.options || '';
            const savedSelected = districtEl.dataset.selected || '';
            const savedProvince = districtEl.dataset.selectedProvince || '';
            const savedPostal = districtEl.dataset.selectedPostal || '';
            if (optionsHtml) {
              select.innerHTML = optionsHtml;
              if (savedSelected) {
                try { select.value = savedSelected; } catch(e) { /* ignore */ }
                if (select.selectedIndex === -1) {
                  const missing = document.createElement('option');
                  missing.value = savedSelected;
                  missing.textContent = savedSelected;
                  if (savedProvince) missing.setAttribute('data-province', savedProvince);
                  if (savedPostal) missing.setAttribute('data-postal', savedPostal);
                  missing.selected = true;
                  select.appendChild(missing);
                }
              }
            } else {
              select.innerHTML = '<option value="">Select District</option>';
            }
            wrapper.replaceChild(select, districtEl);
            districtEl = document.getElementById(config.selectId);
            districtEl.addEventListener('change', () => sync(config, true));
          }
            districtEl.removeAttribute('disabled');
            // when returning to Nepal, make state/postal readonly again and ensure they sync
            if (stateEl) stateEl.setAttribute('readonly', 'readonly');
            if (postalEl) postalEl.setAttribute('readonly', 'readonly');
          // ensure restored selection updates province/postal immediately
          sync(config, true);
        }
      };

      countryEl.addEventListener('change', apply);
      apply();
    };

    watchCountry('permanent_country', configs[0]);
    watchCountry('current_country', configs[1]);
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