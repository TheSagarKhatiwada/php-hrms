<?php
// Start output buffering to prevent issues with redirects
ob_start();

// Include session configuration first to ensure session is available
require_once '../../includes/session_config.php';
require_once '../../includes/utilities.php';

// Debug: Add error display for development
if (defined('ENVIRONMENT') && ENVIRONMENT === 'development') {
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
}

$page = 'employees';

// Check if user has admin access
if (!is_admin()) {
    header('Location: ../../dashboard.php');
    exit();
}

include '../../includes/db_connection.php';

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

// Fetch data from the database
try {
    $stmt = $pdo->prepare("SELECT e.*, b.name, d.title AS designation_title 
                    FROM employees e 
                    JOIN branches b ON e.branch = b.id 
                    LEFT JOIN designations d ON e.designation = d.id 
                    ORDER BY e.join_date DESC");
    $stmt->execute();
    $employees = $stmt->fetchAll();
    
    // Debug: Log the number of employees fetched
    error_log("Fetched " . count($employees) . " employees from database", 3, dirname(__DIR__) . '/../../debug_log.txt');
    
} catch (PDOException $e) {
    error_log("Error fetching employees: " . $e->getMessage(), 3, dirname(__DIR__) . '/../../debug_log.txt');
    $employees = []; // Set empty array to prevent errors in the view
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
          <a href="add-employee.php" class="btn btn-primary">
            <i class="fas fa-user-plus me-2"></i> Add Employee
          </a>
        </div>
      </div>
      
      <!-- Employees Table Card -->
      <div class="card border-0 shadow-sm">
        <div class="card-body">
          <div class="table-responsive">
            <table id="employees-table" class="table table-hover">
              <thead>
                <tr>
                  <th class="text-center">ID</th>
                  <th>Employee</th>
                  <th>Contact Info</th>
                  <th>Branch</th>
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
                
                foreach ($employees as $employee): ?>
                <tr>
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
                  <td><?php echo htmlspecialchars($employee['name']); ?></td>
                  <td class="text-center align-middle"><?php echo date('M d, Y', strtotime($employee['join_date'])); ?></td>
                  <td class="text-center align-middle">
                    <?php if ($employee['exit_date']): ?>
                      <span class="badge bg-danger">Exited on</span></br><?php echo date('M d, Y', strtotime($employee['exit_date'])); ?>
                    <?php else: ?>
                      <span class="badge bg-success">Active</span>
                    <?php endif; ?>
                  </td>
                  <td class="text-center align-middle">
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
                        <?php if (!$employee['exit_date']): ?>
                        <li>
                          <a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#markExitModal" data-emp-id="<?php echo $employee['emp_id']; ?>">
                            <i class="fas fa-sign-out-alt me-2"></i> Mark Exit
                          </a>
                        </li>
                        <?php endif; ?>
                        <li><hr class="dropdown-divider"></li>
                        <li>
                          <a class="dropdown-item text-danger" href="#" data-bs-toggle="modal" data-bs-target="#deleteEmployeeModal" data-emp-id="<?php echo $employee['emp_id']; ?>">
                            <i class="fas fa-trash me-2"></i> Delete
                          </a>
                        </li>
                      </ul>
                    </div>
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

<!-- Include the main footer (which closes content-wrapper, main-wrapper, etc.) -->
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>

<!-- Page specific script -->
<script>
// Force refresh function to reload page with cache-busting parameter
const forceRefresh = () => {
  const timestamp = new Date().getTime();
  const currentUrl = new URL(window.location.href);
  currentUrl.searchParams.delete('_nocache');
  currentUrl.searchParams.set('_nocache', timestamp);
  window.location.href = currentUrl.toString();
};

document.addEventListener('DOMContentLoaded', function() {
  // Initialize DataTable
  const employeesTable = new DataTable('#employees-table', {
    responsive: true,
    lengthChange: true,
    autoWidth: false,
    order: [[4, 'desc']], // Sort by join date
    pageLength: 10,
    language: {
      paginate: {
        previous: '<i class="fas fa-chevron-left"></i>',
        next: '<i class="fas fa-chevron-right"></i>'
      }
    }
  });

  // Mark Exit Modal Handler
  const markExitModal = document.getElementById('markExitModal');
  if (markExitModal) {
    markExitModal.addEventListener('show.bs.modal', function(event) {
      const button = event.relatedTarget;
      const empId = button.getAttribute('data-emp-id');
      document.getElementById('exitEmpId').value = empId;
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
});
</script>