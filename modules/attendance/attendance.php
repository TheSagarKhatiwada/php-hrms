<?php
ob_start(); // Start output buffering
$page = 'attendance';

include '../../includes/session_config.php';
include '../../includes/db_connection.php';
include '../../includes/utilities.php';

// Check if we need to open the modal in manual mode
$openManualModal = isset($_GET['action']) && $_GET['action'] === 'manual';

// Fetching attendance data
try {
    $stmt = $pdo->prepare("SELECT a.*, e.first_name, e.last_name, e.middle_name, e.branch, e.emp_id, e.user_image, e.designation, b.name , d.title AS designation
                           FROM attendance_logs a 
                           INNER JOIN employees e ON a.emp_Id = e.emp_id 
                           INNER JOIN branches b ON e.branch = b.id 
                           LEFT JOIN designations d ON e.designation = d.id
                           WHERE a.date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
                           ORDER BY a.date DESC, a.time DESC 
                           LIMIT 200");
    $stmt->execute();
    $attendanceRecords = $stmt->fetchAll();
} catch (PDOException $e) {
    $_SESSION['error'] = "Error fetching attendance data: " . $e->getMessage();
}

// Include the header after data loading (includes topbar, starts main-wrapper and content-wrapper)
require_once __DIR__ . '/../../includes/header.php';
?>

<!-- Content Wrapper (already started in header.php) -->
<!-- <div class="content-wrapper"> --> <!-- This div is opened in header.php -->
    <!-- Topbar is included in header.php -->
    
    <!-- Main content -->
    <div class="container-fluid p-4">
      <!-- Page header -->
      <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
          <h1 class="fs-2 fw-bold mb-1">Attendance Records</h1>
        </div>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addAttendanceModal">
          <i class="fas fa-plus me-2"></i> Add Attendance
        </button>
      </div>
      
      <!-- Attendance Table Card -->
      <div class="card border-0 shadow-sm">
        <div class="card-body">
          <div class="table-responsive">
            <table id="attendance-table" class="table table-hover">
              <thead>
                <tr>
                  <th class="text-center">ID</th>
                  <th>Employee</th>
                  <th class="text-center">Branch</th>
                  <th class="text-center">Date</th>
                  <th class="text-center">Time</th>
                  <th class="text-center">Method</th>
                  <th>Reason</th>
                  <th class="text-center">Actions</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($attendanceRecords as $record): ?>
                <tr>
                  <td class="text-center align-middle"><?php echo htmlspecialchars($record['emp_id']); ?></td>
                  <td>
                    <div class="d-flex align-items-center">
                      <img src="<?php echo htmlspecialchars($record['user_image'] ?: 'resources/images/default-user.png'); ?>" 
                           alt="Employee" 
                           class="rounded-circle me-3" 
                           style="width: 40px; height: 40px; object-fit: cover;">
                      <div>
                        <div class="fw-bold"><?php echo htmlspecialchars($record['first_name'] . ' ' . $record['middle_name'] . ' ' . $record['last_name']); ?></div>
                        <small class="text-muted"><?php echo htmlspecialchars($record['designation'] ?: 'Not Assigned'); ?></small>
                      </div>
                    </div>
                  </td>
                  <td class="text-center align-middle"><?php echo htmlspecialchars($record['name']); ?></td>
                  <td class="text-center align-middle"><?php echo date('M d, Y', strtotime($record['date'])); ?></td>
                  <td class="text-center align-middle"><?php echo htmlspecialchars($record['time']); ?></td>
                  <td class="text-center align-middle">
                    <?php 
                    $methodText = '<span class="badge bg-secondary">Unknown</span>'; // Default
                    if (isset($record['method'])) {
                        switch ($record['method']) {
                            case 0:
                                $methodText = '<span class="badge bg-primary">Auto</span>';
                                break;
                            case 1:
                                $methodText = '<span class="badge bg-warning" style="color: #000;">Manual</span>';
                                break;
                            case 2:
                                $methodText = '<span class="badge bg-info">Web</span>';
                                break;
                        }
                    }
                    echo $methodText;
                    ?>
                  </td>
                  <td>
                    <?php 
                    if ($record['manual_reason']) {
                      $parts = explode(' || ', $record['manual_reason']);
                      $reasonId = trim($parts[0]);
                      $remarks = isset($parts[1]) ? trim($parts[1]) : '';
                      
                      switch($reasonId) {
                        case '1': echo 'Card Forgot'; break;
                        case '2': echo 'Card Lost'; break;
                        case '3': echo 'Forgot to Punch'; break;
                        case '4': echo 'Office Work Delay'; break;
                        case '5': echo 'Field Visit'; break;
                        default: echo "-" . $reasonId;
                      }
                      
                      if (!empty($remarks)) {
                        echo "<br><small class='text-muted'>-" . htmlspecialchars($remarks) . "</small>";
                      }
                    } else {
                      echo '<span class="text-muted">-</span>';
                    }
                    ?>
                  </td>
                  <td class="text-center align-middle">
                    <?php if ($record['method'] == 1): ?>
                    <div class="dropdown">
                      <a href="#" class="text-secondary" role="button" id="dropdownMenuButton<?php echo $record['id']; ?>" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="fas fa-ellipsis-v"></i>
                      </a>
                      <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="dropdownMenuButton<?php echo $record['id']; ?>">
                        <li>
                          <a class="dropdown-item edit-attendance" href="#"
                              data-bs-toggle="modal" 
                              data-bs-target="#editAttendanceModal" 
                              data-id="<?php echo $record['id']; ?>"
                              data-date="<?php echo $record['date']; ?>"
                              data-time="<?php echo $record['time']; ?>"
                              data-reason="<?php echo !empty($record['manual_reason']) ? explode(' || ', $record['manual_reason'])[0] : ''; ?>"
                              data-remarks="<?php echo !empty($record['manual_reason']) && strpos($record['manual_reason'], ' || ') !== false ? explode(' || ', $record['manual_reason'])[1] : ''; ?>"
                              data-emp-name="<?php echo $record['first_name'] . ' ' . $record['last_name']; ?>"
                              data-emp-id="<?php echo $record['emp_id']; ?>"
                              data-emp-image="<?php echo $record['user_image']; ?>"
                              data-designation="<?php echo $record['designation']; ?>"
                              data-branch="<?php echo $record['name']; ?>">
                            <i class="fas fa-edit me-2"></i> Edit
                          </a>
                        </li>
                        <li><hr class="dropdown-divider"></li>
                        <li>
                          <a class="dropdown-item text-danger delete-attendance" href="#"
                             data-bs-toggle="modal" 
                             data-bs-target="#deleteAttendanceModal"
                             data-id="<?php echo $record['id']; ?>">
                            <i class="fas fa-trash me-2"></i> Delete
                          </a>
                        </li>
                      </ul>
                    </div>
                    <?php else: ?>
                    <span class="text-muted">-</span>
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
    
<!-- Modals remain outside the main content flow, before the final footer include -->
<!-- Add Attendance Modal -->
<div class="modal fade" id="addAttendanceModal" tabindex="-1" aria-labelledby="addAttendanceModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="addAttendanceModalLabel">Add Attendance</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <!-- Tabs -->
        <ul class="nav nav-tabs" id="attendanceTabs" role="tablist">
          <li class="nav-item" role="presentation">
            <button class="nav-link active" id="upload-tab" data-bs-toggle="tab" data-bs-target="#upload-tab-pane" type="button" role="tab" aria-controls="upload-tab-pane" aria-selected="true">Upload</button>
          </li>
          <li class="nav-item" role="presentation">
            <button class="nav-link" id="manual-tab" data-bs-toggle="tab" data-bs-target="#manual-tab-pane" type="button" role="tab" aria-controls="manual-tab-pane" aria-selected="false">Manual</button>
          </li>
        </ul>
        
        <!-- Tab Content -->
        <div class="tab-content mt-3" id="attendanceTabsContent">
          <!-- Upload Tab -->
          <div class="tab-pane fade show active" id="upload-tab-pane" role="tabpanel" aria-labelledby="upload-tab" tabindex="0">
            <form action="upload-attendance.php" method="post" enctype="multipart/form-data">
              <div class="row">
                <div class="col-md-9 mb-3">
                  <label for="attendanceFile" class="form-label">Upload Attendance File <span class="text-danger">*</span></label>
                  <input type="file" class="form-control" id="attendanceFile" name="attendanceFile" accept=".txt" required>
                  <div class="form-text text-primary">Upload a text file with attendance records</div>
                </div>
                <div class="col-md-3 d-flex align-items-end">
                  <button type="submit" class="btn btn-primary w-100">Upload & Process</button>
                </div>
              </div>
            </form>
          </div>
          
          <!-- Manual Tab -->
          <div class="tab-pane fade" id="manual-tab-pane" role="tabpanel" aria-labelledby="manual-tab" tabindex="0">
            <form id="manualAttendance" method="POST" action="record_manual_attendance.php">
              <div class="row g-3">
                <div class="col-md-6 mb-3">
                  <label for="empBranch" class="form-label">Branch <span class="text-danger">*</span></label>
                  <select class="form-select" id="empBranch" name="empBranch" required>
                    <option value="" selected disabled>Select Branch</option>
                    <?php 
                    $branchQuery = "SELECT DISTINCT id, name FROM branches";
                    $stmt = $pdo->query($branchQuery);
                    while ($row = $stmt->fetch()) {
                        echo "<option value='{$row['id']}'>{$row['name']}</option>";
                    }
                    ?>
                  </select>
                </div>
                <div class="col-md-6 mb-3">
                  <label for="emp_id" class="form-label">Employee <span class="text-danger">*</span></label>
                  <select class="form-select" id="emp_id" name="empId" required>
                    <option value="">Select Employee</option>
                  </select>
                </div>
                <div class="col-md-6 mb-3">
                  <label for="attendanceDate" class="form-label">Attendance Date <span class="text-danger">*</span></label>
                  <input type="date" class="form-control" id="attendanceDate" name="attendanceDate" 
                         value="<?php echo date('Y-m-d'); ?>" 
                         min="<?php echo date('Y-m-d', strtotime('-30 days')); ?>" 
                         max="<?php echo date('Y-m-d'); ?>" 
                         required>
                </div>
                <div class="col-md-6 mb-3">
                  <label for="attendanceTime" class="form-label">Attendance Time <span class="text-danger">*</span></label>
                  <input type="time" class="form-control" id="attendanceTime" name="attendanceTime" step="1" required>
                </div>
                <div class="col-md-6 mb-3">
                  <label for="reason" class="form-label">Reason <span class="text-danger">*</span></label>
                  <select class="form-select" id="reason" name="reason" required>
                    <option value="" selected disabled>Select Reason</option>
                    <option value="1">Card Forgot</option>
                    <option value="2">Card Lost</option>
                    <option value="3">Forgot to Punch</option>
                    <option value="4">Office Work Delay</option>
                    <option value="5">Field Visit</option>
                  </select>
                </div>
                <div class="col-md-6 mb-3">
                  <label for="remarks" class="form-label">Remarks</label>
                  <input type="text" class="form-control" id="remarks" name="remarks" placeholder="Optional">
                </div>
                <div class="col-12 text-end">
                  <button type="submit" class="btn btn-primary">Save Attendance</button>
                </div>
              </div>
            </form>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>

<!-- Edit Attendance Modal -->
<div class="modal fade" id="editAttendanceModal" tabindex="-1" aria-labelledby="editAttendanceModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="editAttendanceModalLabel">Edit Attendance Record</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form id="editAttendanceForm" method="POST" action="update_attendance.php">
        <div class="modal-body">
          <input type="hidden" id="edit_attendance_id" name="attendanceId">
          
          <!-- Employee Details Section -->
          <div class="p-3 mb-3 rounded">
            <div class="d-flex align-items-center">
              <img id="edit_emp_image" src="" alt="Employee Image" class="rounded-circle me-3" style="width: 60px; height: 60px; object-fit: cover;">
              <div>
                <h5 id="edit_emp_name" class="mb-1"></h5>
                <p class="mb-0 text-muted">
                  <span id="edit_emp_designation"></span> • 
                  <span id="edit_emp_branch"></span>
                </p>
              </div>
            </div>
          </div>
          
          <!-- Attendance Details Section -->
          <div class="row g-3">
            <div class="col-md-6 mb-3">
              <label for="edit_attendance_date" class="form-label">Attendance Date</label>
              <input type="date" class="form-control" id="edit_attendance_date" name="attendanceDate" required>
            </div>
            <div class="col-md-6 mb-3">
              <label for="edit_attendance_time" class="form-label">Attendance Time</label>
              <input type="time" class="form-control" id="edit_attendance_time" name="attendanceTime" step="1" required>
            </div>
            <div class="col-md-6 mb-3">
              <label for="edit_reason" class="form-label">Reason</label>
              <select class="form-select" id="edit_reason" name="reason" required>
                <option value="1">Card Forgot</option>
                <option value="2">Card Lost</option>
                <option value="3">Forgot to Punch</option>
                <option value="4">Office Work Delay</option>
                <option value="5">Field Visit</option>
              </select>
            </div>
            <div class="col-md-6 mb-3">
              <label for="edit_remarks" class="form-label">Remarks</label>
              <input type="text" class="form-control" id="edit_remarks" name="remarks" placeholder="Optional remarks">
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary">Update Attendance</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Delete Attendance Modal -->
<div class="modal fade" id="deleteAttendanceModal" tabindex="-1" aria-labelledby="deleteAttendanceModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="deleteAttendanceModalLabel">Confirm Deletion</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <p>Are you sure you want to delete this attendance record? This action cannot be undone.</p>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
        <a href="#" id="confirmDeleteBtn" class="btn btn-danger">Delete</a>
      </div>
    </div>
  </div>
</div>

<!-- Include the main footer (which closes content-wrapper, main-wrapper, etc.) -->
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>

<!-- Page specific script -->
<script>
document.addEventListener('DOMContentLoaded', function() {
  // Initialize DataTable
  const attendanceTable = new DataTable('#attendance-table', {
    responsive: true,
    lengthChange: true,
    autoWidth: false,
    order: [[3, 'desc'], [4, 'desc']], // Sort by date and time
    pageLength: 10,
    language: {
      paginate: {
        previous: '<i class="fas fa-chevron-left"></i>',
        next: '<i class="fas fa-chevron-right"></i>'
      }
    }
  });

  // Branch change - fetch employees
  document.getElementById('empBranch').addEventListener('change', function() {
    const branch = this.value;
    if (branch) {
      fetch('fetch_users.php', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'branch=' + branch
      })
      .then(response => response.text())
      .then(data => {
        document.getElementById('emp_id').innerHTML = data;
      })
      .catch(error => {
        console.error('Error fetching employees:', error);
      });
    } else {
      document.getElementById('emp_id').innerHTML = '<option value="">Select Employee</option>';
    }
  });

  // Edit Attendance Modal Handler
  const editAttendanceModal = document.getElementById('editAttendanceModal');
  if (editAttendanceModal) {
    editAttendanceModal.addEventListener('show.bs.modal', function(event) {
      const button = event.relatedTarget;
      
      // Extract data
      const id = button.getAttribute('data-id');
      const date = button.getAttribute('data-date');
      const time = button.getAttribute('data-time');
      const reason = button.getAttribute('data-reason');
      const remarks = button.getAttribute('data-remarks');
      const empName = button.getAttribute('data-emp-name');
      const empId = button.getAttribute('data-emp-id');
      const empImage = button.getAttribute('data-emp-image');
      const designation = button.getAttribute('data-designation');
      const branch = button.getAttribute('data-branch');
      
      // Set form values
      document.getElementById('edit_attendance_id').value = id;
      document.getElementById('edit_attendance_date').value = date;
      document.getElementById('edit_attendance_time').value = time;
      document.getElementById('edit_reason').value = reason || "1";
      document.getElementById('edit_remarks').value = remarks || "";
      
      // Set employee details
      document.getElementById('edit_emp_name').textContent = empName;
      document.getElementById('edit_emp_designation').textContent = designation || "Not Assigned";
      document.getElementById('edit_emp_branch').textContent = branch;
      
      // Set employee image
      const imageElem = document.getElementById('edit_emp_image');
      imageElem.src = empImage || 'resources/images/default-user.png';
    });
  }
  
  // Delete Attendance Modal Handler
  const deleteAttendanceModal = document.getElementById('deleteAttendanceModal');
  if (deleteAttendanceModal) {
    deleteAttendanceModal.addEventListener('show.bs.modal', function(event) {
      const button = event.relatedTarget;
      const id = button.getAttribute('data-id');
      document.getElementById('confirmDeleteBtn').href = 'delete-attendance.php?id=' + id;
    });
  }
  
  // Check for edit parameter in URL and auto-open edit modal
  const urlParams = new URLSearchParams(window.location.search);
  const editId = urlParams.get('edit');
  const action = urlParams.get('action');
  
  if (editId) {
    // Find the edit button for this attendance record and trigger it
    const editButton = document.querySelector(`[data-id="${editId}"].edit-attendance`);
    if (editButton) {
      // Simulate a click on the edit button to open the modal with data
      editButton.click();
      
      // Clean up the URL by removing the edit parameter
      const newUrl = window.location.protocol + "//" + window.location.host + window.location.pathname;
      window.history.replaceState(null, null, newUrl);
    }
  }
  
  // Check for manual parameter and auto-open add attendance modal
  if (action === 'manual') {
    // Clean up the URL immediately to prevent re-opening on refresh
    const newUrl = window.location.protocol + "//" + window.location.host + window.location.pathname;
    window.history.replaceState(null, null, newUrl);
    
    // Open the add attendance modal and switch to manual tab after a short delay
    setTimeout(() => {
      const addAttendanceModal = new bootstrap.Modal(document.getElementById('addAttendanceModal'));
      addAttendanceModal.show();
      
      // Switch to manual tab after modal is shown
      addAttendanceModal._element.addEventListener('shown.bs.modal', function() {
        const manualTab = new bootstrap.Tab(document.getElementById('manual-tab'));
        manualTab.show();
      }, { once: true });
    }, 100);
  }
});
</script>