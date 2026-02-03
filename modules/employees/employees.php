<?php
// Start output buffering to prevent issues with redirects
ob_start();

// Include session configuration first to ensure session is available
require_once '../../includes/session_config.php';
require_once '../../includes/utilities.php';
require_once '../../includes/csrf_protection.php';
require_once __DIR__ . '/../../includes/db_connection.php';

// Fix session role if not set but user_role exists
if (!isset($_SESSION['role']) && isset($_SESSION['user_role'])) {
    $_SESSION['role'] = $_SESSION['user_role'];
}

// Debug: Add error display for development
if (defined('ENVIRONMENT') && ENVIRONMENT === 'development') {
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
}

$page = 'employees';

// Permission gate
$canManageAllEmployees = is_admin() || has_permission('manage_employees');
$hasAllBranchAccessPermission = $canManageAllEmployees || has_permission('access_all_branch_employee');
$canViewEmployees = $hasAllBranchAccessPermission || has_permission('view_employees');
$limitToUserBranch = !$hasAllBranchAccessPermission;
$canViewBranchColumn = $hasAllBranchAccessPermission;
$canManageBranchAssignments = $canManageAllEmployees || has_permission('manage_branch_assignments');
$canViewExitedEmployees = $canManageAllEmployees || has_permission('view_exited_employees');
$viewerBranchContext = ['legacy' => null, 'numeric' => null];

if (!$canViewEmployees) {
  header('Location: ../../dashboard.php');
  exit();
}
$restrictBranchPermission = $limitToUserBranch;
if ($restrictBranchPermission && isset($_SESSION['user_id'])) {
  try {
    $branchLookup = $pdo->prepare("SELECT branch, branch_id FROM employees WHERE emp_id = :emp_id LIMIT 1");
    $branchLookup->execute([':emp_id' => $_SESSION['user_id']]);
    $branchRow = $branchLookup->fetch(PDO::FETCH_ASSOC);
    if ($branchRow) {
      $viewerBranchContext = hrms_resolve_branch_assignment($branchRow['branch'] ?? null, $branchRow['branch_id'] ?? null);
    }
  } catch (PDOException $e) {
    // Leave branch context empty to fall back to safest behavior (no access)
  }
}
$csrf_token = generate_csrf_token();

// Handle exit date and note update
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['exitDate'])) {
    $empId = $_POST['empId'];
    $exitDate = $_POST['exitDate'];
    $exitNote = $_POST['exitNote'];

    $sql = "UPDATE employees SET exit_date = :exitDate, exit_note = :exitNote, login_access = 0 WHERE emp_id = :empId";
    $stmt = $pdo->prepare($sql);
    try {
        $stmt->execute([
            ':exitDate' => $exitDate,
            ':exitNote' => $exitNote,
            ':empId' => $empId
        ]);
        $_SESSION['success'] = "Employee exit details updated successfully!";
        header('Location: employees.php?_nocache=' . time());
        exit();
    } catch (PDOException $e) {
        $_SESSION['error'] = "Error updating exit details: " . $e->getMessage();
        header('Location: employees.php?_nocache=' . time());
        exit();
    }
}

// Handle join date update
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['joinDate'])) {
    $empId = $_POST['empId'];
    $joinDate = $_POST['joinDate'];
    $joinNote = $_POST['joinNote'];

    // For now, we'll just update the join_date. The join_note can be added later if needed.
    $sql = "UPDATE employees SET join_date = :joinDate WHERE emp_id = :empId";
    $stmt = $pdo->prepare($sql);
    try {
        $stmt->execute([
            ':joinDate' => $joinDate,
            ':empId' => $empId
        ]);
        
        // Log the join note in the system for now (can be moved to database later)
        if (!empty($joinNote)) {
            error_log("Join date update note for employee $empId: $joinNote");
        }
        
        $_SESSION['success'] = "Employee join date updated successfully!";
        header('Location: employees.php?_nocache=' . time());
        exit();
    } catch (PDOException $e) {
        $_SESSION['error'] = "Error updating join date: " . $e->getMessage();
        header('Location: employees.php?_nocache=' . time());
        exit();
    }
}

// Fetch data from the database
try {
  // NOTE: Using e.branch and e.designation (legacy column names) instead of branch_id/designation_id
  // Other modules reference these columns, so align here to actually fetch employees.
  // Use LEFT JOIN for branches too so employees without a branch still appear.
      $query = "SELECT e.*, b.name, d.title AS designation_title 
          FROM employees e 
          LEFT JOIN branches b ON e.branch = b.id 
          LEFT JOIN designations d ON e.designation_id = d.id";
      $params = [];
      $whereClauses = [];
      if ($limitToUserBranch) {
        $branchFilterSql = hrms_build_branch_filter_sql($viewerBranchContext, $params, 'e.branch', 'e.branch_id');
        if ($branchFilterSql === '') {
          $whereClauses[] = '1 = 0';
        } else {
          $whereClauses[] = $branchFilterSql;
        }
      }
      if (!$canViewExitedEmployees) {
        $whereClauses[] = "(e.exit_date IS NULL OR e.exit_date = '')";
      }
      if (!empty($whereClauses)) {
        $query .= ' WHERE ' . implode(' AND ', $whereClauses);
      }
    $query .= " ORDER BY e.join_date DESC";
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $employees = $stmt->fetchAll();

    $branchesStmt = $pdo->query("SELECT id, name FROM branches ORDER BY name ASC");
    $branches = $branchesStmt->fetchAll(PDO::FETCH_ASSOC);

    $supervisorsStmt = $pdo->prepare("SELECT emp_id, CONCAT(first_name, ' ', last_name) AS name FROM employees WHERE exit_date IS NULL OR exit_date = '' ORDER BY first_name, last_name");
    $supervisorsStmt->execute();
    $supervisors = $supervisorsStmt->fetchAll(PDO::FETCH_ASSOC);
    
  // (Removed verbose debug logging)
    
} catch (PDOException $e) {
  // (Removed verbose debug logging)
    $employees = []; // Set empty array to prevent errors in the view
    $branches = [];
    $supervisors = [];
    $_SESSION['error'] = "Error loading employees: " . $e->getMessage();
}

// Force fresh load by checking for cache-busting parameter
// Only redirect if not already redirected and no POST data
if (!isset($_GET['_nocache']) && $_SERVER['REQUEST_METHOD'] !== 'POST' && !isset($_POST['exitDate'])) {
    // Ensure no output has been sent before redirect
    if (!headers_sent()) {
        // Clean any output buffer before redirect
        ob_clean();
        header("Location: employees.php?_nocache=" . time());
        exit();
    }
}

// Clean the output buffer and start fresh for HTML output
ob_end_clean();
ob_start();

// Include the header (which includes topbar, starts main-wrapper and content-wrapper)
require_once __DIR__ . '/../../includes/header.php';
?>

<!-- The main-container div is removed -->
<!-- Sidebar is included in header.php -->

<!-- Content Wrapper (already started in header.php) -->
  <!-- This div is opened in header.php -->
    <!-- Topbar is included in header.php -->
    
    <!-- Main content -->
    <div class="container-fluid p-4">
      <!-- Page header -->
      <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
          <h1 class="fs-2 fw-bold mb-1">Manage Employees</h1>
        </div>
        <div class="d-flex gap-2">
          <button type="button" id="refreshEmployees" class="btn btn-outline-primary" onclick="forceRefresh()">
            <i class="fas fa-sync-alt"></i> Refresh
          </button>
          <?php if ($canManageAllEmployees): ?>
          <a href="add-employee.php" class="btn btn-primary">
            <i class="fas fa-user-plus me-2"></i> Add Employee
          </a>
          <?php endif; ?>
        </div>
      </div>
      
      <!-- Employees Table Card -->
      <div class="card border-0 shadow-sm">
        <div class="card-body">
          <div class="table-responsive">
            <!-- Unified controls: entries length, show exited toggle, search -->
              <div id="employee-controls" class="d-flex align-items-center flex-wrap p-2 rounded small gap-2">
              <div id="dt-length-holder" class="d-flex align-items-center"></div>
              <div class="ms-auto d-flex align-items-center gap-2" id="right-control-cluster">
                <?php if ($canViewExitedEmployees): ?>
                <button id="toggleExited" type="button" class="btn btn-outline-secondary btn-sm" data-show="0">
                  <i class="fas fa-user-slash me-1"></i> Show Exited
                </button>
                <?php endif; ?>
                <div id="dt-search-holder"></div>
              </div>
            </div>
            <table id="employees-table" class="table table-hover">
              <thead>
                <tr>
                  <th class="text-center">ID</th>
                  <th>Employee</th>
                  <th>Contact Info</th>
                  <?php if ($canViewBranchColumn): ?>
                  <th>Branch</th>
                  <?php endif; ?>
                  <th class="text-center">Joining Date</th>
                  <th class="text-center">Status</th>
                  <th class="text-center">Actions</th>
                </tr>
              </thead>
              <tbody>
                <?php 
                // Debug information (only show in development)
                if (defined('ENVIRONMENT') && ENVIRONMENT === 'development') {
                    echo "<!-- DEBUG: Found " . count($employees) . " employees -->";
                }
                
                foreach ($employees as $employee):
                  if (!$canViewExitedEmployees && !empty($employee['exit_date'])) {
                    continue;
                  }
                ?>
                <tr class="<?php echo $employee['exit_date'] ? 'exited-row' : ''; ?>">
                  <td class="text-center align-middle"><?php echo htmlspecialchars($employee['emp_id']); ?></td>
                  <td>
                    <div class="d-flex align-items-center">
                      <img src="<?php 
                        $imagePath = $employee['user_image'] ?: '../../resources/userimg/default-image.jpg';
                        // If the image path doesn't start with ../ or http, it's stored without the relative path
                        if (!empty($employee['user_image']) && strpos($employee['user_image'], '../') !== 0 && strpos($employee['user_image'], 'http') !== 0) {
                          $imagePath = '../../' . $employee['user_image'];
                        }
                        echo htmlspecialchars($imagePath);
                      ?>" 
                           alt="Employee" 
                           class="rounded-circle me-3" 
                           style="width: 40px; height: 40px; object-fit: cover;"
                           onerror="this.src='../../resources/userimg/default-image.jpg'">
                      <div>
                        <div class="fw-bold"><?php 
                          $fullName = $employee['first_name'];
                          if (!empty($employee['middle_name'])) {
                              $fullName .= ' ' . $employee['middle_name'];
                          }
                          $fullName .= ' ' . $employee['last_name'];
                          echo htmlspecialchars($fullName);
                        ?></div>
                        <small class="text-muted"><?php echo htmlspecialchars($employee['designation_title'] ?: 'Not Assigned'); ?></small>
                      </div>
                    </div>
                  </td>
                  <td>
                    <div><i class="fas fa-envelope me-1 text-muted small"></i> <?php echo htmlspecialchars($employee['office_email'] ? $employee['office_email'] : 'N/A'); ?></div>
                    <div><i class="fas fa-phone me-1 text-muted small"></i> <?php echo htmlspecialchars($employee['office_phone'] ? $employee['office_phone'] : 'N/A'); ?></div>
                  </td>
                  <?php if ($canViewBranchColumn): ?>
                  <td><?php echo htmlspecialchars($employee['name']); ?></td>
                  <?php endif; ?>
                  <td class="text-center align-middle"><?php echo hrms_format_preferred_date($employee['join_date'], 'Y-m-d'); ?></td>
                  <td class="text-center align-middle">
                    <?php if ($employee['exit_date']): ?>
                      <span class="badge bg-danger">Exited on</span></br><?php echo hrms_format_preferred_date($employee['exit_date'], 'M d, Y'); ?>
                    <?php else: ?>
                      <span class="badge bg-success">Active</span>
                    <?php endif; ?>
                  </td>
                  <td class="text-center align-middle">
                    <?php if ($canManageAllEmployees): ?>
                    <div class="dropdown">
                      <a href="#" class="text-secondary" role="button" id="dropdownMenuButton<?php echo $employee['emp_id']; ?>" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="fas fa-ellipsis-v"></i>
                      </a>
                      <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="dropdownMenuButton<?php echo $employee['emp_id']; ?>">
                        <li>
                          <a class="dropdown-item" href="employee-viewer.php?empId=<?php echo $employee['emp_id']; ?>">
                            <i class="fas fa-eye me-2"></i> View
                          </a>
                        </li>
                        <li>
                          <a class="dropdown-item" href="edit-employee.php?id=<?php echo $employee['emp_id']; ?>">
                            <i class="fas fa-edit me-2"></i> Edit
                          </a>
                        </li>
                        <?php if (!$employee['exit_date'] && $canManageBranchAssignments): ?>
                        <li>
                          <a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#transferEmployeeModal"
                             data-emp-id="<?php echo htmlspecialchars($employee['emp_id']); ?>"
                             data-emp-name="<?php echo htmlspecialchars($fullName); ?>"
                             data-current-branch="<?php echo htmlspecialchars($employee['name'] ?? 'Unassigned'); ?>"
                             data-current-branch-id="<?php echo htmlspecialchars((string)($employee['branch'] ?: ($employee['branch_id'] ?? ''))); ?>"
                             data-current-supervisor="<?php echo htmlspecialchars($employee['supervisor_id'] ?? ''); ?>"
                             data-work-start="<?php echo htmlspecialchars($employee['work_start_time'] ? substr($employee['work_start_time'], 0, 5) : ''); ?>"
                             data-work-end="<?php echo htmlspecialchars($employee['work_end_time'] ? substr($employee['work_end_time'], 0, 5) : ''); ?>">
                            <i class="fas fa-random me-2"></i> Transfer
                          </a>
                        </li>
                        <li>
                          <a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#markExitModal" data-emp-id="<?php echo $employee['emp_id']; ?>">
                            <i class="fas fa-sign-out-alt me-2"></i> Mark Exit
                          </a>
                        </li>
                        <?php endif; ?>
                        <li>
                          <a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#markJoinModal" data-emp-id="<?php echo $employee['emp_id']; ?>" data-current-join-date="<?php echo $employee['join_date']; ?>">
                            <i class="fas fa-calendar-plus me-2"></i> Update Join Date
                          </a>
                        </li>
                        <li><hr class="dropdown-divider"></li>
                        <li>
                          <a class="dropdown-item text-danger" href="#" data-bs-toggle="modal" data-bs-target="#deleteEmployeeModal" data-emp-id="<?php echo $employee['emp_id']; ?>">
                            <i class="fas fa-trash me-2"></i> Delete
                          </a>
                        </li>
                      </ul>
                    </div>
                    <?php else: ?>
                    <a class="btn btn-outline-primary btn-sm" href="employee-viewer.php?empId=<?php echo $employee['emp_id']; ?>">
                      <i class="fas fa-eye me-1"></i> View
                    </a>
                    <?php endif; ?>
                  </td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div> <!-- /.container-fluid -->
    
    <!-- Footer include is removed from here -->
<!-- </div> --> <!-- This div (content-wrapper) is closed in footer.php -->
<!-- </div> --> <!-- This div (main-container) is removed -->

<!-- Modals remain outside the main content flow, before the final footer include -->
<!-- Mark Exit Modal -->
<div class="modal fade" id="markExitModal" tabindex="-1" aria-labelledby="markExitModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="markExitModalLabel">Mark Employee Exit</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form id="markExitForm" method="POST" action="employees.php">
        <div class="modal-body">
          <input type="hidden" id="exitEmpId" name="empId">
          <div class="mb-3">
            <label for="exitDate" class="form-label">Exit Date <span class="text-danger">*</span></label>
            <input type="date" class="form-control" id="exitDate" name="exitDate" max="<?php echo date('Y-m-d'); ?>" required>
          </div>
          <div class="mb-3">
            <label for="exitNote" class="form-label">Remarks</label>
            <textarea class="form-control" id="exitNote" name="exitNote" rows="3" placeholder="Enter exit reason or other remarks"></textarea>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary">Update Exit</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Mark Join Date Modal -->
<div class="modal fade" id="markJoinModal" tabindex="-1" aria-labelledby="markJoinModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="markJoinModalLabel">Update Employee Join Date</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form id="markJoinForm" method="POST" action="employees.php">
        <div class="modal-body">
          <input type="hidden" id="joinEmpId" name="empId">
          <div class="mb-3">
            <label for="joinDate" class="form-label">Join Date (Start Working) <span class="text-danger">*</span></label>
            <input type="date" class="form-control" id="joinDate" name="joinDate" max="<?php echo date('Y-m-d'); ?>" required>
          </div>
          <div class="mb-3">
            <label for="joinNote" class="form-label">Remarks</label>
            <textarea class="form-control" id="joinNote" name="joinNote" rows="3" placeholder="Enter reason for join date update or other remarks"></textarea>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-success">Update Join Date</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Delete Employee Modal -->
<div class="modal fade" id="deleteEmployeeModal" tabindex="-1" aria-labelledby="deleteEmployeeModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="deleteEmployeeModalLabel">Confirm Employee Deletion</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <p>Are you sure you want to delete this employee? This action cannot be undone.</p>
        <p class="text-danger"><strong>Warning:</strong> All associated data including attendance records will also be deleted.</p>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
        <a href="#" id="confirmDeleteBtn" class="btn btn-danger">Delete Employee</a>
      </div>
    </div>
  </div>
</div>

<?php if ($canManageBranchAssignments): ?>
<!-- Transfer Employee Modal -->
<div class="modal fade" id="transferEmployeeModal" tabindex="-1" aria-labelledby="transferEmployeeModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <div>
          <h5 class="modal-title" id="transferEmployeeModalLabel">Transfer Employee</h5>
          <p class="text-muted small mb-0">Capture branch moves, supervisor changes, and scheduling updates.</p>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form id="transferEmployeeForm" autocomplete="off" novalidate>
        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
        <input type="hidden" name="emp_id" id="transferEmpId">
        <div class="modal-body">
          <div id="transferAlert" class="alert d-none" role="alert"></div>
          <div class="border rounded p-3 mb-3 bg-light-subtle">
            <h6 class="fw-semibold mb-2" id="transferEmployeeName">Employee</h6>
            <dl class="row mb-0 small">
              <dt class="col-sm-4">Current Branch</dt>
              <dd class="col-sm-8" id="currentBranchValue">–</dd>
              <dt class="col-sm-4">Supervisor</dt>
              <dd class="col-sm-8" id="currentSupervisorValue">–</dd>
              <dt class="col-sm-4">Schedule</dt>
              <dd class="col-sm-8" id="currentScheduleValue">–</dd>
            </dl>
          </div>
          <div class="row g-3">
            <div class="col-md-6">
              <label for="transferNewBranch" class="form-label">New Branch <span class="text-danger">*</span></label>
              <select class="form-select" id="transferNewBranch" name="new_branch_id" required>
                <option value="">Select branch</option>
                <?php foreach ($branches as $branch): ?>
                  <option value="<?php echo (int)$branch['id']; ?>"><?php echo htmlspecialchars($branch['name']); ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-3">
              <label for="lastDayCurrent" class="form-label">Last Day (Current Branch)</label>
              <input type="date" class="form-control" id="lastDayCurrent" name="last_day_current">
              <div class="form-text">Optional; defaults to effective date - 1.</div>
            </div>
            <div class="col-md-3">
              <label for="effectiveDate" class="form-label">Effective Date <span class="text-danger">*</span></label>
              <input type="date" class="form-control" id="effectiveDate" name="effective_date" required>
            </div>
            <div class="col-md-6">
              <label for="transferSupervisor" class="form-label">New Supervisor</label>
              <select class="form-select" id="transferSupervisor" name="new_supervisor_id">
                <option value="">Keep current supervisor</option>
                <?php foreach ($supervisors as $supervisor): ?>
                  <option value="<?php echo htmlspecialchars($supervisor['emp_id']); ?>"><?php echo htmlspecialchars($supervisor['name']); ?></option>
                <?php endforeach; ?>
              </select>
              <div class="form-text">Leave blank to retain the employee's existing supervisor.</div>
            </div>
            <div class="col-md-3">
              <label for="newWorkStart" class="form-label">New Start Time</label>
              <input type="time" class="form-control" id="newWorkStart" name="new_work_start_time" step="60">
            </div>
            <div class="col-md-3">
              <label for="newWorkEnd" class="form-label">New End Time</label>
              <input type="time" class="form-control" id="newWorkEnd" name="new_work_end_time" step="60">
            </div>
            <div class="col-12">
              <label for="transferReason" class="form-label">Reason / Notes <span class="text-danger">*</span></label>
              <textarea class="form-control" id="transferReason" name="reason" rows="3" placeholder="Provide context for the transfer" required></textarea>
            </div>
            <div class="col-12">
              <div class="form-check">
                <input class="form-check-input" type="checkbox" value="1" id="notifyStakeholders" name="notify_stakeholders">
                <label class="form-check-label" for="notifyStakeholders">
                  Notify employee, new supervisor, and admins about this transfer
                </label>
              </div>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary" id="submitTransferBtn">
            <span class="me-2"><i class="fas fa-save"></i></span>Save Transfer
          </button>
        </div>
      </form>
    </div>
  </div>
</div>
<?php endif; ?>

<!-- Include the main footer (which closes content-wrapper, main-wrapper, etc.) -->
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>

<!-- Page specific script -->
<script>
const joinDateSortIndex = <?php echo $canViewBranchColumn ? 4 : 3; ?>;
// Force refresh function to reload page with cache-busting parameter
const forceRefresh = () => {
  const timestamp = new Date().getTime();
  const currentUrl = new URL(window.location.href);
  currentUrl.searchParams.delete('_nocache');
  currentUrl.searchParams.set('_nocache', timestamp);
  window.location.href = currentUrl.toString();
};

document.addEventListener('DOMContentLoaded', function() {
  const employeesTableEl = document.getElementById('employees-table');
  let employeesTable = null;
  if(employeesTableEl && typeof DataTable !== 'undefined'){
    employeesTable = new DataTable(employeesTableEl, {
    responsive: true,
    lengthChange: true,
    autoWidth: false,
    order: [[joinDateSortIndex, 'desc']], // Sort by join date
    pageLength: 10,
    language: {
      paginate: {
        previous: '<i class="fas fa-chevron-left"></i>',
        next: '<i class="fas fa-chevron-right"></i>'
      }
    }
    });
  }

  // Move built-in DataTables controls (length + search) into unified control box
  if (employeesTable) {
    const wrapper = document.getElementById('employee-controls');
    const dtWrapper = document.getElementById('employees-table_wrapper');
    if (wrapper && dtWrapper) {
      const lengthDiv = dtWrapper.querySelector('.dataTables_length');
      const filterDiv = dtWrapper.querySelector('.dataTables_filter');
      const lengthHolder = document.getElementById('dt-length-holder');
      const searchHolder = document.getElementById('dt-search-holder');
      if (lengthDiv && lengthHolder) {
        // Simplify label text
        const label = lengthDiv.querySelector('label');
        if (label) {
          const select = label.querySelector('select');
          if (select) {
            select.classList.add('form-select', 'form-select-sm', 'w-auto');
            lengthHolder.appendChild(select);
            const span = document.createElement('span');
            span.className = 'ms-2 text-muted';
            span.textContent = 'entries';
            lengthHolder.appendChild(span);
          }
        }
        lengthDiv.remove();
      }
      if (filterDiv && searchHolder) {
        const label = filterDiv.querySelector('label');
        if (label) {
          const input = label.querySelector('input');
          if (input) {
            input.classList.add('form-control', 'form-control-sm');
            input.placeholder = 'Search...';
            searchHolder.appendChild(input);
          }
        }
        filterDiv.remove();
      }
    }
  }


  const toggleBtn = document.getElementById('toggleExited');
  const exitedRowsSelector = '#employees-table tbody tr.exited-row';
  let showExitedRows = toggleBtn ? toggleBtn.getAttribute('data-show') === '1' : false;
  const updateToggleAppearance = () => {
    if(!toggleBtn) return;
    if(showExitedRows){
      toggleBtn.classList.remove('btn-outline-secondary');
      toggleBtn.classList.add('btn-secondary');
      toggleBtn.innerHTML = '<i class="fas fa-users me-1"></i> Hide Exited';
    } else {
      toggleBtn.classList.remove('btn-secondary');
      toggleBtn.classList.add('btn-outline-secondary');
      toggleBtn.innerHTML = '<i class="fas fa-user-slash me-1"></i> Show Exited';
    }
  };
  const hasDtFilterSupport = !!(employeesTable && DataTable && DataTable.ext && Array.isArray(DataTable.ext.search));
  const filterExitedRows = function(settings, data, dataIndex){
    if(settings.nTable !== employeesTableEl) return true;
    if(showExitedRows) return true;
    const api = new DataTable.Api(settings);
    const node = api.row(dataIndex).node();
    return !(node && node.classList && node.classList.contains('exited-row'));
  };
  if(hasDtFilterSupport){
    DataTable.ext.search.push(filterExitedRows);
    employeesTable.draw();
  } else {
    if(!showExitedRows){
      document.querySelectorAll(exitedRowsSelector).forEach(tr => tr.classList.add('d-none'));
    }
  }
  updateToggleAppearance();
  if (toggleBtn) {
    toggleBtn.addEventListener('click', () => {
      showExitedRows = !showExitedRows;
      toggleBtn.setAttribute('data-show', showExitedRows ? '1' : '0');
      updateToggleAppearance();
      if(hasDtFilterSupport){
        employeesTable.draw();
      } else {
        document.querySelectorAll(exitedRowsSelector).forEach(tr => {
          tr.classList.toggle('d-none', !showExitedRows);
        });
      }
    });
  }

  // Mark Exit Modal Handler
  const markExitModal = document.getElementById('markExitModal');
  if (markExitModal) {
    markExitModal.addEventListener('show.bs.modal', function(event) {
      const button = event.relatedTarget;
      const empId = button.getAttribute('data-emp-id');
      document.getElementById('exitEmpId').value = empId;
    });
  }

  // Mark Join Date Modal Handler
  const markJoinModal = document.getElementById('markJoinModal');
  if (markJoinModal) {
    markJoinModal.addEventListener('show.bs.modal', function(event) {
      const button = event.relatedTarget;
      const empId = button.getAttribute('data-emp-id');
      const currentJoinDate = button.getAttribute('data-current-join-date');
      document.getElementById('joinEmpId').value = empId;
      document.getElementById('joinDate').value = currentJoinDate || '';
    });
  }
  
  // Delete Employee Modal Handler
  const deleteEmployeeModal = document.getElementById('deleteEmployeeModal');
  if (deleteEmployeeModal) {
    deleteEmployeeModal.addEventListener('show.bs.modal', function(event) {
      const button = event.relatedTarget;
      const empId = button.getAttribute('data-emp-id');
      const confirmDeleteBtn = document.getElementById('confirmDeleteBtn');
      confirmDeleteBtn.href = 'delete-employee.php?id=' + empId;
    });
  }

  // Transfer Employee Modal Handlers
  const transferModal = document.getElementById('transferEmployeeModal');
  const transferForm = document.getElementById('transferEmployeeForm');
  const transferAlert = document.getElementById('transferAlert');
  const employeeNameEl = document.getElementById('transferEmployeeName');
  const currentBranchValue = document.getElementById('currentBranchValue');
  const currentSupervisorValue = document.getElementById('currentSupervisorValue');
  const currentScheduleValue = document.getElementById('currentScheduleValue');
  const supervisorSelect = document.getElementById('transferSupervisor');
  const newBranchSelect = document.getElementById('transferNewBranch');
  const workStartInput = document.getElementById('newWorkStart');
  const workEndInput = document.getElementById('newWorkEnd');
  const notifyCheckbox = document.getElementById('notifyStakeholders');
  const submitTransferBtn = document.getElementById('submitTransferBtn');

  const clearTransferAlert = () => {
    if (transferAlert) {
      transferAlert.classList.add('d-none');
      transferAlert.textContent = '';
      transferAlert.classList.remove('alert-success', 'alert-danger');
    }
  };

  const setTransferAlert = (type, message) => {
    if (!transferAlert) return;
    transferAlert.classList.remove('d-none');
    transferAlert.classList.remove('alert-success', 'alert-danger');
    transferAlert.classList.add(`alert-${type}`);
    transferAlert.textContent = message;
  };

  if (transferModal && transferForm) {
    transferModal.addEventListener('show.bs.modal', event => {
      transferForm.reset();
      transferForm.classList.remove('was-validated');
      clearTransferAlert();

      const trigger = event.relatedTarget;
      if (!trigger) return;

      const empId = trigger.getAttribute('data-emp-id') || '';
      const empName = trigger.getAttribute('data-emp-name') || 'Employee';
      const branchName = trigger.getAttribute('data-current-branch') || 'Unassigned';
      const branchId = trigger.getAttribute('data-current-branch-id') || '';
      const supervisorId = trigger.getAttribute('data-current-supervisor') || '';
      const workStart = trigger.getAttribute('data-work-start') || '';
      const workEnd = trigger.getAttribute('data-work-end') || '';

      document.getElementById('transferEmpId').value = empId;
      employeeNameEl.textContent = empName;
      currentBranchValue.textContent = branchName;
      currentBranchValue.dataset.branchId = branchId;
      currentSupervisorValue.textContent = supervisorId ? `ID: ${supervisorId}` : 'Not assigned';
      currentScheduleValue.textContent = workStart && workEnd
        ? `${workStart} - ${workEnd}`
        : (workStart || workEnd ? `${workStart || workEnd}` : 'Not set');

      if (supervisorSelect) {
        supervisorSelect.value = '';
      }
      if (newBranchSelect) {
        newBranchSelect.value = '';
        const options = Array.from(newBranchSelect.options);
        options.forEach(option => option.disabled = false);
        if (branchId) {
          options.forEach(option => {
            if (option.value === branchId) {
              option.disabled = true;
            }
          });
        }
      }

      if (workStartInput) {
        workStartInput.value = '';
        workStartInput.placeholder = workStart ? `Current: ${workStart}` : '';
      }
      if (workEndInput) {
        workEndInput.value = '';
        workEndInput.placeholder = workEnd ? `Current: ${workEnd}` : '';
      }
      if (notifyCheckbox) {
        notifyCheckbox.checked = false;
      }
    });

    transferForm.addEventListener('submit', async event => {
      event.preventDefault();
      clearTransferAlert();

      if (!transferForm.checkValidity()) {
        transferForm.classList.add('was-validated');
        return;
      }

      if (!submitTransferBtn) return;
      const originalBtnHtml = submitTransferBtn.innerHTML;
      submitTransferBtn.disabled = true;
      submitTransferBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>Saving...';

      try {
        const formData = new FormData(transferForm);
        const response = await fetch('../../api/transfer-employee.php', {
          method: 'POST',
          body: formData
        });

        let result;
        try {
          result = await response.json();
        } catch (jsonError) {
          throw new Error('Server returned an unexpected response.');
        }

        if (!response.ok || result.status !== 'success') {
          throw new Error(result.message || 'Unable to save transfer.');
        }

        setTransferAlert('success', result.message || 'Transfer recorded successfully.');
        setTimeout(() => {
          const modalInstance = bootstrap.Modal.getInstance(transferModal);
          if (modalInstance) {
            modalInstance.hide();
          }
          forceRefresh();
        }, 900);
      } catch (error) {
        setTransferAlert('danger', error.message || 'An unexpected error occurred.');
      } finally {
        submitTransferBtn.disabled = false;
        submitTransferBtn.innerHTML = originalBtnHtml;
      }
    });
  }
});
</script>