<?php
ob_start(); // Start output buffering
$page = 'Asset Assignments';

// Include notification_helpers.php before header to enable notify functions
require_once __DIR__ . '/../../includes/notification_helpers.php';
require_once __DIR__ . '/../../includes/header.php';
include '../../includes/db_connection.php';

// Check if user is logged in and has admin role
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] != '1') {
    header('Location: index.php');
    exit();
}

// Handle form submissions
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['action'])) {
        $action = $_POST['action'];
        
        if ($action == 'add') {
            $assetId = $_POST['assetId'];
            $employeeId = $_POST['employeeId'];
            $assignDate = $_POST['assignDate'];
            $expectedReturnDate = !empty($_POST['expectedReturnDate']) ? $_POST['expectedReturnDate'] : null;
            $notes = $_POST['notes'];
            
            if (strtotime($assignDate) < strtotime(date('Y-m-d'))) {
                $_SESSION['error'] = "Assign date cannot be in the past.";
                header('Location: manage_assignments.php');
                exit();
            }

            // Allow expected return date to be blank or a future date
            if (!empty($expectedReturnDate) && strtotime($expectedReturnDate) < strtotime(date('Y-m-d'))) {
                $_SESSION['error'] = "Expected return date must be a future date or left blank.";
                header('Location: manage_assignments.php');
                exit();
            }

            try {
                // Prepare the SQL with NULL handling for expected return date
                if ($expectedReturnDate === null) {                    $stmt = $pdo->prepare("INSERT INTO assetassignments (AssetID, EmployeeID, AssignmentDate, ExpectedReturnDate, Notes) 
                                         VALUES (:assetId, :employeeId, :assignDate, NULL, :notes)");
                    $stmt->execute([
                        ':assetId' => $assetId,
                        ':employeeId' => $employeeId,
                        ':assignDate' => $assignDate,
                        ':notes' => $notes
                    ]);
                } else {                    $stmt = $pdo->prepare("INSERT INTO assetassignments (AssetID, EmployeeID, AssignmentDate, ExpectedReturnDate, Notes) 
                                         VALUES (:assetId, :employeeId, :assignDate, :expectedReturnDate, :notes)");
                    $stmt->execute([
                        ':assetId' => $assetId,
                        ':employeeId' => $employeeId,
                        ':assignDate' => $assignDate,
                        ':expectedReturnDate' => $expectedReturnDate,
                        ':notes' => $notes
                    ]);
                }
                
                // Update asset status to 'Assigned'
                $stmt = $pdo->prepare("UPDATE fixedassets SET Status = 'Assigned' WHERE AssetID = :assetId");
                $stmt->execute([':assetId' => $assetId]);
                
                // Get asset name for notification
                $stmt = $pdo->prepare("SELECT AssetName FROM fixedassets WHERE AssetID = :assetId");
                $stmt->execute([':assetId' => $assetId]);
                $asset = $stmt->fetch();
                $assetName = $asset ? $asset['AssetName'] : "Asset #$assetId";
                
                // Send notification to the employee about the asset assignment
                notify_asset($employeeId, 'assigned', $assetName);
                
                // If admins/managers should be notified of all asset assignments
                notify_system(
                    'Asset Assigned', 
                    "$assetName has been assigned to employee ID: $employeeId", 
                    'info'
                );
                
                $_SESSION['success'] = "Asset assigned successfully!";
                header('Location: manage_assignments.php');
                exit();
            } catch (PDOException $e) {
                $_SESSION['error'] = "Error assigning asset: " . $e->getMessage();
                header('Location: manage_assignments.php');
                exit();
            }
        } 
        elseif ($action == 'return') {
            $assignmentId = $_POST['assignmentId'];
            $returnDate = $_POST['returnDate'];
            $returnNotes = $_POST['returnNotes'];
            $needsMaintenance = isset($_POST['needsMaintenance']) ? true : false;

            if (strtotime($returnDate) > strtotime(date('Y-m-d'))) {
                $_SESSION['error'] = "Return date cannot be in the future.";
                header('Location: manage_assignments.php');
                exit();
            }

            try {
                // Get the asset ID from the assignment
                $stmt = $pdo->prepare("SELECT AssetID FROM assetassignments WHERE AssignmentID = :assignmentId");
                $stmt->execute([':assignmentId' => $assignmentId]);
                $assignment = $stmt->fetch();
                $assetId = $assignment['AssetID'];

                // Append maintenance note if needed
                if ($needsMaintenance) {
                    $returnNotes = "Need Maintenance (" . $returnNotes . ")";
                }                // Update assignment record
                $stmt = $pdo->prepare("UPDATE assetassignments SET ReturnDate = :returnDate, ReturnNotes = :returnNotes 
                                     WHERE AssignmentID = :assignmentId");
                $stmt->execute([
                    ':returnDate' => $returnDate,
                    ':returnNotes' => $returnNotes,
                    ':assignmentId' => $assignmentId
                ]);
              
                // Update asset status
                $newStatus = $needsMaintenance ? 'Maintenance' : 'Available';
                $stmt = $pdo->prepare("UPDATE fixedassets SET Status = :newStatus WHERE AssetID = :assetId");
                $stmt->execute([
                    ':newStatus' => $newStatus,
                    ':assetId' => $assetId
                ]);
                  // Fetch the assigned user's name
                $stmt = $pdo->prepare("SELECT e.first_name, e.middle_name, e.last_name FROM employees e JOIN assetassignments aa ON e.emp_id = aa.EmployeeID WHERE aa.AssignmentID = :assignmentId");
                $stmt->execute([':assignmentId' => $assignmentId]);
                $assignedUser = $stmt->fetch();
                $assignedUserName = $assignedUser ? $assignedUser['first_name'] . ' ' . $assignedUser['middle_name'] . ' ' . $assignedUser['last_name'] : 'Unknown User';

                // Update the return notes
                $returnNotes = "Returned from $assignedUserName with note: " . $returnNotes;
                  // Get the employee ID and asset name for notification
                $stmt = $pdo->prepare("SELECT aa.EmployeeID, fa.AssetName 
                                      FROM assetassignments aa 
                                      JOIN fixedassets fa ON aa.AssetID = fa.AssetID 
                                      WHERE aa.AssignmentID = :assignmentId");
                $stmt->execute([':assignmentId' => $assignmentId]);
                $assetInfo = $stmt->fetch();
                
                if ($assetInfo) {
                    $employeeId = $assetInfo['EmployeeID'];
                    $assetName = $assetInfo['AssetName'];
                    
                    // Insert into AssetMaintenance table if maintenance is needed
                    if ($needsMaintenance) {
                        $stmt = $pdo->prepare("INSERT INTO AssetMaintenance (AssetID, MaintenanceDate, MaintenanceType, Description, Cost, MaintenancePerformBy, MaintenanceStatus) 
                                             VALUES (:assetId, :maintenanceDate, :maintenanceType, :notes, :cost, :performedBy, 'Scheduled')");
                        $stmt->execute([
                            ':assetId' => $assetId,
                            ':maintenanceDate' => $returnDate,
                            ':maintenanceType' => 'Corrective',
                            ':notes' => $returnNotes,
                            ':cost' => 0.00, // Default zero cost for returned items
                            ':performedBy' => 'Returned by ' . $assignedUserName
                        ]);
                        
                        // Send maintenance notification
                        notify_system(
                            'Asset Needs Maintenance', 
                            "$assetName has been returned and needs maintenance", 
                            'warning',
                            false // Changed to false to store notification in DB without redirecting
                        );
                    }
                    
                    // Send notification to the employee about the asset return
                    notify_asset($employeeId, 'returned', $assetName);
                    
                    // Notify system/admins about the asset return
                    notify_system(
                        'Asset Returned', 
                        "$assetName has been returned by Employee #$employeeId", 
                        'info',
                        false // Set to false to prevent immediate notification redirect
                    );
                }
                
                $_SESSION['success'] = "Asset returned successfully!";
                
                // Make sure all output is flushed before redirect
                ob_end_clean();
                
                // Explicitly set the Content-Type header to help with redirection
                header('Content-Type: text/html; charset=utf-8');
                header('Location: manage_assignments.php');
                exit();
            } catch (PDOException $e) {
                $_SESSION['error'] = "Error returning asset: " . $e->getMessage();
                header('Location: manage_assignments.php');
                exit();
            }
        }
    }
}

// Set display flags for modals based on POST data
$showAddModal = false;
$showReturnModal = false;
$returnAssetData = [];

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['show_add_modal'])) {
        $showAddModal = true;
    } elseif (isset($_POST['show_return_modal'])) {
        $showReturnModal = true;
        $assignmentId = $_POST['assignment_id'];
        
        // Fetch asset details for return modal
        $stmt = $pdo->prepare("SELECT 
            aa.AssignmentID,
            fa.AssetName,
            fa.AssetSerial
            FROM AssetAssignments aa
            LEFT JOIN fixedassets fa ON aa.AssetID = fa.AssetID
            WHERE aa.AssignmentID = :assignmentId");
        $stmt->execute([':assignmentId' => $assignmentId]);
        $returnAssetData = $stmt->fetch(PDO::FETCH_ASSOC);
    }
}

// Fetch all assignments
try {    $stmt = $pdo->query("SELECT 
        aa.*,
        fa.AssetName,
        fa.AssetSerial,
        ac.CategoryShortCode,
        e.first_name,
        e.last_name,
        d.title AS Designation
    FROM assetassignments aa
    LEFT JOIN fixedassets fa ON aa.AssetID = fa.AssetID
    LEFT JOIN assetcategories ac ON fa.CategoryID = ac.CategoryID
    LEFT JOIN employees e ON aa.EmployeeID = e.emp_id
    LEFT JOIN designations d ON e.designation = d.id
    ORDER BY aa.AssignmentDate DESC");
    $assignments = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $_SESSION['error'] = "Error fetching data: " . $e->getMessage();
    $assignments = [];
}
?>
<!-- Page-specific CSS -->
<link rel="stylesheet" href="<?php echo $home;?>plugins/datatables-bs4/css/dataTables.bootstrap4.min.css">
<link rel="stylesheet" href="<?php echo $home;?>plugins/datatables-responsive/css/responsive.bootstrap4.min.css">
<link rel="stylesheet" href="<?php echo $home;?>plugins/datatables-buttons/css/buttons.bootstrap4.min.css">
<!-- daterange picker -->
<link rel="stylesheet" href="<?php echo $home;?>plugins/daterangepicker/daterangepicker.css">
<!-- Tempusdominus Bootstrap 4 -->
<link rel="stylesheet" href="<?php echo $home;?>plugins/tempusdominus-bootstrap-4/css/tempusdominus-bootstrap-4.min.css">
<!-- Select2 -->
<link rel="stylesheet" href="<?php echo $home;?>plugins/select2/css/select2.min.css">
<link rel="stylesheet" href="<?php echo $home;?>plugins/select2-bootstrap4-theme/select2-bootstrap4.min.css">

<!-- Content Header (Page header) -->
<div class="container-fluid p-4">
  <!-- Page header -->
  <div class="d-flex justify-content-between align-items-center mb-4">
    <div>
      <h1 class="fs-2 fw-bold mb-1">Asset Assignments</h1>
    </div>
    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addAssignmentModal">
      <i class="fas fa-plus me-2"></i> New Assignment
    </button>
  </div>

  <!-- Assignments Table Card -->
  <div class="card border-0 shadow-sm">
    <div class="card-body">
      <div class="table-responsive">
        <table id="assignmentsTable" class="table table-hover">
          <thead>
            <tr>
              <th class="text-center">SN</th>
              <th>Asset Name</th>
              <th>Serial No</th>
              <th>Employee</th>
              <th class="text-center">Assigned Date</th>
              <th>Issuing Note</th>
              <th class="text-center">Expected Return on</th>
              <th class="text-center">Return Date</th>
              <th>Return Note</th>
              <th class="text-center">Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php 
            $sn = 1;
            foreach ($assignments as $assignment): ?>
              <tr>
                <td class="text-center align-middle"><?php echo $sn++; ?></td>
                <td class="align-middle"><?php echo $assignment['AssetName']; ?></td>
                <td class="align-middle"><?php echo $assignment['AssetSerial']; ?></td>
                <td class="align-middle"><?php echo ($assignment['first_name'] . ' ' . $assignment['last_name'] . ' (' . $assignment['Designation'] . ')') ?? $assignment['EmployeeID']; ?></td>
                <td class="text-center align-middle"><?php echo date('M d, Y', strtotime($assignment['AssignmentDate'])); ?></td>
                <td class="align-middle"><?php echo $assignment['Notes']; ?></td>
                <td class="text-center align-middle">
                  <?php 
                  if (!is_null($assignment['ExpectedReturnDate']) && $assignment['ExpectedReturnDate'] != '0000-00-00') {
                    echo date('M d, Y', strtotime($assignment['ExpectedReturnDate']));
                  } else {
                    echo '-';
                  }
                  ?>
                </td>
                <td class="text-center align-middle">
                  <?php 
                  if (!is_null($assignment['ReturnDate']) && $assignment['ReturnDate'] != '0000-00-00') {
                    echo date('M d, Y', strtotime($assignment['ReturnDate']));
                  } else {
                    echo '-';
                  }
                  ?>
                </td>
                <td class="align-middle"><?php echo $assignment['ReturnNotes'] ? $assignment['ReturnNotes'] : '-'; ?></td>
                <td class="text-center align-middle">
                  <?php if (empty($assignment['ReturnDate'])): ?>
                    <button type="button" class="btn btn-sm btn-outline-warning return-asset-btn" data-bs-toggle="modal" data-bs-target="#returnAssetModal" data-assignment-id="<?php echo $assignment['AssignmentID']; ?>" data-asset-name="<?php echo htmlspecialchars($assignment['AssetName']); ?>" data-asset-serial="<?php echo htmlspecialchars($assignment['AssetSerial']); ?>">
                      <i class="fas fa-undo-alt me-1"></i> Return
                    </button>
                  <?php else: ?>
                    <span class="badge bg-success">Returned</span>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<?php include '../../includes/footer.php'; ?>

<!-- Add Assignment Modal -->
<div class="modal fade" id="addAssignmentModal" tabindex="-1" aria-labelledby="addAssignmentModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="addAssignmentModalLabel">New Asset Assignment</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form method="POST" action="manage_assignments.php">
        <div class="modal-body">
          <input type="hidden" name="action" value="add">
            <div class="mb-3">
              <label for="assetId" class="form-label">Asset <span class="text-danger">*</span></label>
              <select class="form-select select2" style="width: 100%;" id="assetId" name="assetId" required>
                <option value="">Select Asset</option>
                <?php
                // Fetch available assets (not assigned)
                $stmt = $pdo->query("SELECT fa.AssetID, fa.AssetName, fa.AssetSerial, ac.CategoryShortCode 
                                   FROM fixedassets fa 
                                   LEFT JOIN assetcategories ac ON fa.CategoryID = ac.CategoryID                                   LEFT JOIN assetassignments aa ON fa.AssetID = aa.AssetID 
                                   WHERE fa.Status = 'Available' 
                                   ORDER BY fa.AssetName");
                while ($asset = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    echo "<option value='{$asset['AssetID']}'>{$asset['AssetName']} ({$asset['AssetSerial']})</option>";
                }
                ?>
              </select>
            </div>
            <div class="mb-3">
              <label for="employeeId" class="form-label">Employee <span class="text-danger">*</span></label>
              <select class="form-select select2" style="width: 100%;" id="employeeId" name="employeeId" required>
                <option value="">Select Employee</option>
                <?php                // Fetch all employees to whom assignment goes
                $stmt = $pdo->query("SELECT emp_id, CONCAT(first_name, ' ', COALESCE(middle_name, ''), ' ', last_name, ' (', COALESCE(d.title, 'Unknown'), ')') AS EmployeeName 
                                     FROM employees e
                                     LEFT JOIN designations d ON e.designation = d.id
                                     WHERE exit_date IS NULL
                                     ORDER BY first_name");
                while ($employee = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    echo "<option value='{$employee['emp_id']}'>{$employee['EmployeeName']}</option>";
                }
                ?>
              </select>
            </div>
            <div class="mb-3">
              <label for="assignDate" class="form-label">Assign Date <span class="text-danger">*</span></label>
              <input type="date" class="form-control" id="assignDate" name="assignDate" required>
            </div>
            <div class="mb-3">
              <label for="expectedReturnDate" class="form-label">Expected Return Date</label>
              <input type="date" class="form-control" id="expectedReturnDate" name="expectedReturnDate" min="<?php echo date('Y-m-d'); ?>">
            </div>
            <div class="mb-3">
              <label for="notes" class="form-label">Notes</label>
              <textarea class="form-control" id="notes" name="notes" rows="3" placeholder="Enter assignment notes or remarks"></textarea>
            </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary">Assign Asset</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Return Asset Modal -->
<div class="modal fade" id="returnAssetModal" tabindex="-1" aria-labelledby="returnAssetModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="returnAssetModalLabel">Return Asset</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form method="POST" action="manage_assignments.php">
        <div class="modal-body">
          <input type="hidden" name="action" value="return">
          <input type="hidden" name="assignmentId" id="returnAssignmentId">
          <p>Are you sure you want to return the asset: <strong><span id="returnAssetDetails"></span></strong>?</p>
          <div class="mb-3">
            <label for="returnDate" class="form-label">Return Date <span class="text-danger">*</span></label>
            <input type="date" class="form-control" id="returnDate" name="returnDate" value="<?php echo date('Y-m-d'); ?>" max="<?php echo date('Y-m-d'); ?>" required>
          </div>
          <div class="mb-3">
            <label for="returnNotes" class="form-label">Return Notes</label>
            <textarea class="form-control" id="returnNotes" name="returnNotes" rows="3" placeholder="Enter return reason or other remarks"></textarea>
          </div>
          <div class="mb-3">
            <div class="form-check">
              <input class="form-check-input" type="checkbox" id="needsMaintenance" name="needsMaintenance">
              <label for="needsMaintenance" class="form-check-label">Needs Maintenance</label>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-success">Confirm Return</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Page specific scripts -->
<script>
  document.addEventListener('DOMContentLoaded', function() {
    // Initialize DataTable
    var table = $("#assignmentsTable").DataTable({
      "responsive": true,
      "lengthChange": true,
      "autoWidth": false,
      "order": [[4, 'desc']], // Sort by assigned date
      "language": {
        "paginate": {
          "previous": '<i class="fas fa-chevron-left"></i>',
          "next": '<i class="fas fa-chevron-right"></i>'
        }
      }
    });
        
    // Manual modal handling to avoid bootstrap issues
    function openAddAssignmentModal() {
      $('#addAssignmentModal').addClass('show').css('display', 'block').attr('aria-modal', 'true').removeAttr('aria-hidden');
      $('body').addClass('modal-open').append('<div class="modal-backdrop fade show"></div>');
      
      // Initialize Select2 inside modal
      try {
        $('#assetId, #employeeId').select2({
          dropdownParent: $('#addAssignmentModal .modal-body'),
          width: '100%'
        });
      } catch(e) {
        console.error("Error initializing Select2:", e);
      }
      
      // Set today's date
      var today = new Date();
      var dd = String(today.getDate()).padStart(2, '0');
      var mm = String(today.getMonth() + 1).padStart(2, '0');
      var yyyy = today.getFullYear();
      today = yyyy + '-' + mm + '-' + dd;
      $('#assignDate').val(today);
    }
    
    function closeAddAssignmentModal() {
      $('#addAssignmentModal').removeClass('show').css('display', 'none').attr('aria-hidden', 'true').removeAttr('aria-modal');
      $('body').removeClass('modal-open');
      $('.modal-backdrop').remove();
    }
    
    function openReturnAssetModal(assignmentId, assetName, assetSerial) {
      $('#returnAssignmentId').val(assignmentId);
      $('#returnAssetDetails').text(assetName + ' - ' + assetSerial);
      
      $('#returnAssetModal').addClass('show').css('display', 'block').attr('aria-modal', 'true').removeAttr('aria-hidden');
      $('body').addClass('modal-open').append('<div class="modal-backdrop fade show"></div>');
      
      // Set today's date
      var today = new Date();
      var dd = String(today.getDate()).padStart(2, '0');
      var mm = String(today.getMonth() + 1).padStart(2, '0');
      var yyyy = today.getFullYear();
      today = yyyy + '-' + mm + '-' + dd;
      $('#returnDate').val(today);
    }
    
    function closeReturnAssetModal() {
      $('#returnAssetModal').removeClass('show').css('display', 'none').attr('aria-hidden', 'true').removeAttr('aria-modal');
      $('body').removeClass('modal-open');
      $('.modal-backdrop').remove();
    }
    
    // Add Assignment button in header
    $('#add-assignment-btn').on('click', function(e) {
      e.preventDefault();
      openAddAssignmentModal();
    });
    
    // Custom DataTable button
    $('#dt-add-assignment-btn').on('click', function(e) {
      e.preventDefault();
      openAddAssignmentModal();
    });
    
    // Return button clicks
    $(document).on('click', '.return-asset-btn', function(e) {
      e.preventDefault();
      var assetName = $(this).data('asset-name');
      var assetSerial = $(this).data('asset-serial');
      var assignmentId = $(this).data('assignment-id');
      
      openReturnAssetModal(assignmentId, assetName, assetSerial);
    });
    
    // Close modal buttons
    $(document).on('click', '.modal .btn-close, .modal .btn-outline-secondary', function() {
      if ($(this).closest('.modal').attr('id') === 'addAssignmentModal') {
        closeAddAssignmentModal();
      } else if ($(this).closest('.modal').attr('id') === 'returnAssetModal') {
        closeReturnAssetModal();
      }
    });
    
    // Close modal when clicking on backdrop
    $(document).on('click', '.modal-backdrop', function() {
      closeAddAssignmentModal();
      closeReturnAssetModal();
    });
    
    // Setup the Select2 initially for outside elements
    try {
      $('.select2:not(#assetId):not(#employeeId)').select2();
    } catch(e) {
      console.error("Error initializing Select2 outside modals:", e);
    }
  });
</script>

<!-- Load Select2 JS explicitly to ensure it's available -->
<script src="<?php echo $home; ?>plugins/select2/js/select2.full.min.js"></script>
</body>
</html>