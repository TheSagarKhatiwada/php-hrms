<?php
// Include session configuration first to ensure session is available
require_once 'includes/session_config.php';
require_once 'includes/utilities.php';

$page = 'employees';

// Check if user has admin access
if (!is_admin()) {
    header('Location: dashboard.php');
    exit();
}

include 'includes/db_connection.php';

// Handle employee creation form submission
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['machId'], $_POST['empBranch'], $_POST['empFirstName'], $_POST['empLastName'], $_POST['empEmail'], $_POST['empPhone'], $_POST['empJoinDate'])) {
    // Get form data
    $machId = $_POST['machId'];
    $empBranch = $_POST['empBranch'];
    $empFirstName = $_POST['empFirstName'];
    $empMiddleName = isset($_POST['empMiddleName']) ? $_POST['empMiddleName'] : null; // Optional field
    $empLastName = $_POST['empLastName'];
    $empEmail = $_POST['empEmail'];
    $empPhone = $_POST['empPhone'];
    $empJoinDate = $_POST['empJoinDate'];

    // Generate empID based on branch value and auto-increment
    $stmt = $pdo->prepare("SELECT COUNT(*) AS count FROM employees WHERE branch = :branch");
    $stmt->execute([':branch' => $empBranch]);
    $row = $stmt->fetch();
    $count = $row['count'] + 1;
    $empId = $empBranch . str_pad($count, 2, '0', STR_PAD_LEFT);

    // Insert data into the database using prepared statements
    $sql = "INSERT INTO employees (emp_id, mach_id, branch, first_name, middle_name, last_name, email, phone, join_date)
            VALUES (:empId, :machId, :empBranch, :empFirstName, :empMiddleName, :empLastName, :empEmail, :empPhone, :empJoinDate)";
    $stmt = $pdo->prepare($sql);
    try {
        $stmt->execute([
            ':empId' => $empId,
            ':machId' => $machId,
            ':empBranch' => $empBranch,
            ':empFirstName' => $empFirstName,
            ':empMiddleName' => $empMiddleName,
            ':empLastName' => $empLastName,
            ':empEmail' => $empEmail,
            ':empPhone' => $empPhone,
            ':empJoinDate' => $empJoinDate
        ]);
        $_SESSION['success'] = "Employee added successfully!";
        header('Location: employees.php');
        exit();
    } catch (PDOException $e) {
        $_SESSION['error'] = "Error adding employee: " . $e->getMessage();
        header('Location: employees.php');
        exit();
    }
}

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
        header('Location: employees.php');
        exit();
    } catch (PDOException $e) {
        $_SESSION['error'] = "Error updating exit details: " . $e->getMessage();
        header('Location: employees.php');
        exit();
    }
}

// Fetch data from the database
$stmt = $pdo->prepare("SELECT e.*, b.name FROM employees e JOIN branches b ON e.branch = b.id ORDER BY e.join_date DESC");
$stmt->execute();
$employees = $stmt->fetchAll();

// Include the header (which includes topbar, starts main-wrapper and content-wrapper)
require_once __DIR__ . '/includes/header.php';
?>

<!-- The main-container div is removed -->
<!-- Sidebar is included in header.php -->

<!-- Content Wrapper (already started in header.php) -->
<!-- <div class="content-wrapper"> --> <!-- This div is opened in header.php -->
    <!-- Topbar is included in header.php -->
    
    <!-- Main content -->
    <div class="container-fluid p-4">
      <!-- Page header -->
      <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
          <h1 class="fs-2 fw-bold mb-1">Manage Employees</h1>
        </div>
        <a href="add-employee.php" class="btn btn-primary">
          <i class="fas fa-user-plus me-2"></i> Add Employee
        </a>
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
                <?php foreach ($employees as $employee): ?>
                <tr>
                  <td class="text-center align-middle"><?php echo htmlspecialchars($employee['emp_id']); ?></td>
                  <td>
                    <div class="d-flex align-items-center">
                      <img src="<?php echo htmlspecialchars($employee['user_image'] ?: 'resources/images/default-user.png'); ?>" 
                           alt="Employee" 
                           class="rounded-circle me-3" 
                           style="width: 40px; height: 40px; object-fit: cover;">
                      <div>
                        <div class="fw-bold"><?php echo htmlspecialchars($employee['first_name'] . ' ' . $employee['middle_name'] . ' ' . $employee['last_name']); ?></div>
                        <small class="text-muted"><?php echo htmlspecialchars($employee['designation'] ?: 'Not Assigned'); ?></small>
                      </div>
                    </div>
                  </td>
                  <td>
                    <div><i class="fas fa-envelope me-1 text-muted small"></i> <?php echo htmlspecialchars($employee['email']); ?></div>
                    <div><i class="fas fa-phone me-1 text-muted small"></i> <?php echo htmlspecialchars($employee['phone']); ?></div>
                  </td>
                  <td><?php echo htmlspecialchars($employee['name']); ?></td>
                  <td class="text-center align-middle"><?php echo date('M d, Y', strtotime($employee['join_date'])); ?></td>
                  <td class="text-center align-middle">
                    <?php if ($employee['exit_date']): ?>
                      <span class="badge bg-danger">Exited</span>
                    <?php else: ?>
                      <span class="badge bg-success">Active</span>
                    <?php endif; ?>
                  </td>
                  <td class="text-center align-middle">
                    <div class="dropdown">
                      <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" id="dropdownMenuButton" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="fas fa-ellipsis-v"></i>
                      </button>
                      <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="dropdownMenuButton">
                        <li><a class="dropdown-item" href="employee-viewer.php?empId=<?php echo $employee['emp_id']; ?>"><i class="fas fa-eye me-2"></i> View</a></li>
                        <li><a class="dropdown-item" href="edit-employee.php?id=<?php echo $employee['emp_id']; ?>"><i class="fas fa-edit me-2"></i> Edit</a></li>
                        <?php if (!$employee['exit_date']): ?>
                        <li><a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#markExitModal" data-emp-id="<?php echo $employee['emp_id']; ?>"><i class="fas fa-sign-out-alt me-2"></i> Mark Exit</a></li>
                        <?php endif; ?>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item text-danger" href="#" data-bs-toggle="modal" data-bs-target="#deleteEmployeeModal" data-emp-id="<?php echo $employee['emp_id']; ?>"><i class="fas fa-trash me-2"></i> Delete</a></li>
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
<?php require_once __DIR__ . '/includes/footer.php'; ?>

<!-- Page specific script -->
<script>
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