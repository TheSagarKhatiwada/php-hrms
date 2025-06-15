<?php
ob_start(); // Start output buffering
$page = 'Maintenance Records';
require_once __DIR__ . '/../../includes/header.php';
include '../../includes/db_connection.php';

// CSRF Token Generation
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Check if user is logged in and has admin role
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] != '1') {
    header('Location: index.php');
    exit();
}

// Add error handling for database operations
try {    $stmt = $pdo->prepare("SELECT 
        am.RecordID,
        am.AssetID,
        am.MaintenanceDate,
        am.MaintenanceType,
        am.MaintenancePerformBy,
        am.Description,
        am.Cost,
        am.MaintenanceStatus,
        am.CompletionDate,
        am.CompletionNotes,
        fa.AssetName,
        fa.AssetSerial,
        fa.Status AS AssetStatus
    FROM 
        assetmaintenance am
    LEFT JOIN 
        fixedassets fa
    ON 
        am.AssetID = fa.AssetID
    ORDER BY 
        am.MaintenanceDate DESC");
    $stmt->execute();
    $records = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Log the error and display a user-friendly message
    error_log("Database error: " . $e->getMessage(), 3, 'error_log.txt');
    $_SESSION['error'] = "An error occurred while fetching maintenance records. Please try again later.";
    $records = [];
}

// Fetch assets with status 'Available'
try {
    $stmt = $pdo->prepare("SELECT AssetID, AssetName, AssetSerial FROM fixedassets WHERE Status = 'Available' ORDER BY AssetName");
    $stmt->execute();
    $assets = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Log the error and display a user-friendly message
    error_log("Database error: " . $e->getMessage(), 3, 'error_log.txt');
    $_SESSION['error'] = "An error occurred while fetching assets. Please try again later.";
    $assets = [];
}

// Handle form submissions
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $_SESSION['error'] = "Invalid CSRF token.";
        header('Location: manage_maintenance.php');
        exit();
    }

    if (isset($_POST['action'])) {
        $action = $_POST['action'];

        if ($action == 'add') {
            $assetId = $_POST['assetId'];
            $maintenanceDate = $_POST['maintenanceDate'];
            $maintenanceType = $_POST['maintenanceType'];
            $description = $_POST['description'];
            $cost = $_POST['cost'];
            $performedBy = $_POST['performedBy'];

            try {                $stmt = $pdo->prepare("INSERT INTO assetmaintenance (AssetID, MaintenanceDate, MaintenanceType, Description, Cost, MaintenancePerformBy, MaintenanceStatus) 
                                     VALUES (:assetId, :maintenanceDate, :maintenanceType, :description, :cost, :performedBy, 'Scheduled')");
                $stmt->execute([
                    ':assetId' => $assetId,
                    ':maintenanceDate' => $maintenanceDate,
                    ':maintenanceType' => $maintenanceType,
                    ':description' => $description,
                    ':cost' => $cost,
                    ':performedBy' => $performedBy
                ]);

                // Update asset status to 'Maintenance'
                $stmt = $pdo->prepare("UPDATE fixedassets SET Status = 'Maintenance' WHERE AssetID = :assetId");
                $stmt->execute([':assetId' => $assetId]);

                $_SESSION['success'] = "Asset added for maintainence successfully.";
            } catch (PDOException $e) {
                // Log the error and display a user-friendly message
                error_log("Database error: " . $e->getMessage(), 3, 'error_log.txt');
                $_SESSION['error'] = "An error occurred while adding the maintenance record. Please try again later.";
            }
        } elseif ($action == 'in_progress') {
            $maintenanceId = $_POST['maintenanceId'];
            $notes = $_POST['notes'];

            try {                // Update maintenance status to In Progress and save notes
                $stmt = $pdo->prepare("UPDATE assetmaintenance 
                                     SET MaintenanceStatus = 'In Progress',
                                         Description = CONCAT(Description, '\n\nProgress Notes: ', :notes)
                                     WHERE RecordID = :maintenanceId");
                $stmt->execute([
                    ':notes' => $notes,
                    ':maintenanceId' => $maintenanceId
                ]);

                $_SESSION['success'] = "Maintenance status updated to In Progress.";
            } catch (PDOException $e) {
                error_log("Database error: " . $e->getMessage(), 3, 'error_log.txt');
                $_SESSION['error'] = "An error occurred while updating the maintenance status.";
            }        } elseif ($action == 'completed') {
            $maintenanceId = $_POST['maintenanceId'];
            $notes = $_POST['notes'];
            $actualCost = $_POST['actualCost'];

            try {
                // Start transaction
                $pdo->beginTransaction();

                // Update maintenance record
                $stmt = $pdo->prepare("UPDATE assetmaintenance 
                                     SET MaintenanceStatus = 'Completed',
                                         CompletionDate = CURDATE(),
                                         CompletionNotes = :notes,
                                         Cost = :actualCost
                                     WHERE RecordID = :maintenanceId");
                $stmt->execute([
                    ':notes' => $notes,
                    ':actualCost' => $actualCost,
                    ':maintenanceId' => $maintenanceId
                ]);                // Get asset ID and update its status to Available
                $stmt = $pdo->prepare("SELECT AssetID FROM assetmaintenance WHERE RecordID = :maintenanceId");
                $stmt->execute([':maintenanceId' => $maintenanceId]);
                $assetId = $stmt->fetchColumn();

                if ($assetId) {
                    $stmt = $pdo->prepare("UPDATE fixedassets SET Status = 'Available' WHERE AssetID = :assetId");
                    $stmt->execute([':assetId' => $assetId]);
                }

                // Commit transaction
                $pdo->commit();

                $_SESSION['success'] = "Maintenance marked as completed and asset status updated to Available.";
            } catch (PDOException $e) {
                // Rollback transaction on error
                $pdo->rollBack();
                error_log("Database error: " . $e->getMessage(), 3, 'error_log.txt');
                $_SESSION['error'] = "An error occurred while completing the maintenance.";
            }        } elseif ($action == 'not_required') {
            $maintenanceId = $_POST['maintenanceId'];
            $reason = $_POST['reason'];

            try {
                // Start transaction
                $pdo->beginTransaction();
                  // Update maintenance status
                $stmt = $pdo->prepare("UPDATE assetmaintenance 
                                     SET MaintenanceStatus = 'Cancelled', 
                                         Description = CONCAT(Description, '\n\nNot Required Reason: ', :reason) 
                                     WHERE RecordID = :maintenanceId");
                $stmt->execute([
                    ':reason' => $reason,
                    ':maintenanceId' => $maintenanceId
                ]);

                // Get and update asset status
                $stmt = $pdo->prepare("SELECT AssetID FROM assetmaintenance WHERE RecordID = :maintenanceId");
                $stmt->execute([':maintenanceId' => $maintenanceId]);
                $assetId = $stmt->fetchColumn();

                if ($assetId) {
                    $stmt = $pdo->prepare("UPDATE fixedassets SET Status = 'Available' WHERE AssetID = :assetId");
                    $stmt->execute([':assetId' => $assetId]);
                }

                // Commit transaction
                $pdo->commit();
                $_SESSION['success'] = "Maintenance marked as not required (cancelled) and asset status updated to Available.";
            } catch (PDOException $e) {
                // Rollback transaction on error
                $pdo->rollBack();
                error_log("Database error: " . $e->getMessage(), 3, 'error_log.txt');
                $_SESSION['error'] = "An error occurred while updating the maintenance record.";
            }
        }

        header("Location: manage_maintenance.php");
        exit();
    }
}
?>
<!-- Page-specific CSS for DataTables -->
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
      <h1 class="fs-2 fw-bold mb-1">Asset Maintenance</h1>
    </div>
    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addMaintenanceModal">
      <i class="fas fa-plus me-2"></i> Add Maintenance
    </button>
  </div>
  
  <!-- Maintenance Records Card -->
  <div class="card border-0 shadow-sm">
    <div class="card-body">
      <div class="table-responsive">
        <table id="maintenanceTable" class="table table-hover">
          <thead>
            <tr>
              <th class="text-center">SN</th>
              <th>Submit Date</th>
              <th>Asset</th>
              <th>Type</th>
              <th>Description</th>
              <th>Cost</th>
              <th class="text-center">Status</th>
              <th class="text-center">Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php 
            $sn = 1;
            foreach ($records as $record): ?>
              <tr>
                <td class="text-center align-middle"><?php echo $sn++; ?></td>
                <td class="align-middle"><?php echo date('M d, Y', strtotime($record['MaintenanceDate'])); ?></td>
                <td class="align-middle">
                  <div class="d-flex flex-column">
                    <span class="fw-bold"><?php echo htmlspecialchars($record['AssetName']); ?></span>
                    <small class="text-muted"><?php echo htmlspecialchars($record['AssetSerial']); ?></small>
                  </div>
                </td>
                <td class="align-middle"><?php echo htmlspecialchars($record['MaintenanceType']); ?></td>
                <td class="align-middle"><?php 
                  // Split the description by the note markers
                  $description = $record['Description'];
                  $parts = preg_split('/\n\n(Progress Notes:|Completion Notes:|Not Required Reason:)/', $description, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
                  
                  // Output initial description
                  echo "• " . htmlspecialchars($parts[0]);
                  
                  // Output subsequent notes with bullet points
                  for ($i = 1; $i < count($parts); $i += 2) {
                      if (isset($parts[$i]) && isset($parts[$i + 1])) {
                          echo "<br>• " . htmlspecialchars(trim($parts[$i])) . ": " . htmlspecialchars(trim($parts[$i + 1]));
                      }
                  }
                ?></td>                <td class="align-middle">
                  <div>
                    <span class="fw-medium">Est: </span><?php echo $record['Cost'] ? 'Rs. ' . number_format($record['Cost'], 2) : 'N/A'; ?>
                  </div>
                  <?php if ($record['MaintenanceStatus'] == 'Completed' && $record['Cost']): ?>
                    <div>
                      <span class="fw-medium">Final: </span>Rs. <?php echo number_format($record['Cost'], 2); ?>
                    </div>
                  <?php endif; ?>
                </td>
                <td class="text-center align-middle">
                  <?php if ($record['MaintenanceStatus'] == 'Completed'): ?>
                    <span class="badge bg-success">Completed</span>                  <?php elseif ($record['MaintenanceStatus'] == 'Cancelled'): ?>
                    <span class="badge bg-secondary">Not Required</span>
                  <?php elseif ($record['MaintenanceStatus'] == 'In Progress'): ?>
                    <span class="badge bg-info">In Progress</span>
                  <?php else: ?>
                    <span class="badge bg-warning text-dark">Scheduled</span>
                  <?php endif; ?>
                </td>
                <td class="text-center align-middle">
                  <?php if ($record['MaintenanceStatus'] == 'Completed' || $record['MaintenanceStatus'] == 'Cancelled'): ?>
                    <span class="text-muted">-</span>
                  <?php else: ?>
                  <div class="dropdown">
                    <a href="#" class="text-secondary" role="button" id="dropdownMenuButton<?php echo $record['RecordID']; ?>" data-bs-toggle="dropdown" aria-expanded="false">
                      <i class="fas fa-ellipsis-v"></i>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="dropdownMenuButton<?php echo $record['RecordID']; ?>">
                      <li>
                        <a class="dropdown-item action-button" href="#" data-action="Maintenance Not Required" data-id="<?php echo $record['RecordID']; ?>">
                          <i class="fas fa-times me-2"></i> Not Required
                        </a>
                      </li>
                      <li>
                        <a class="dropdown-item action-button" href="#" data-action="Maintenance on Progress" data-id="<?php echo $record['RecordID']; ?>">
                          <i class="fas fa-spinner me-2"></i> On Progress
                        </a>
                      </li>
                      <li>
                        <a class="dropdown-item action-button" href="#" data-action="Maintenance Completed" data-id="<?php echo $record['RecordID']; ?>" data-additional-field="Completion Notes">
                          <i class="fas fa-check me-2"></i> Completed
                        </a>
                      </li>
                    </ul>
                  </div>
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

<!-- Add Maintenance Modal -->
<div class="modal fade" id="addMaintenanceModal" tabindex="-1" aria-labelledby="addMaintenanceModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="addMaintenanceModalLabel">New Maintenance Record</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form method="POST" action="manage_maintenance.php">
        <div class="modal-body">
          <input type="hidden" name="action" value="add">
          <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
          <div class="mb-3">
            <label for="assetId" class="form-label">Asset <span class="text-danger">*</span></label>
            <select class="form-select select2" id="assetId" name="assetId" required>
              <option value="">Select an Asset</option>
              <?php foreach ($assets as $asset): ?>
                <option value="<?php echo $asset['AssetID']; ?>">
                  <?php echo htmlspecialchars($asset['AssetName'] . ' (' . $asset['AssetSerial'] . ')'); ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="mb-3">
            <label for="maintenanceDate" class="form-label">Maintenance Date <span class="text-danger">*</span></label>
            <input type="date" class="form-control" id="maintenanceDate" name="maintenanceDate" value="<?php echo date('Y-m-d'); ?>" required>
          </div>
          <div class="mb-3">
            <label for="maintenanceType" class="form-label">Maintenance Type <span class="text-danger">*</span></label>
            <select class="form-select" id="maintenanceType" name="maintenanceType" required>
              <option value="Preventive">Preventive</option>
              <option value="Corrective">Corrective</option>
              <option value="Warranty">Warranty</option>
              <option value="Upgrade">Upgrade</option>
            </select>
          </div>
          <div class="mb-3">
            <label for="description" class="form-label">Reason for Maintenance <span class="text-danger">*</span></label>
            <textarea class="form-control" id="description" name="description" rows="3" placeholder="Enter detailed reason for maintenance" required></textarea>
          </div>
          <div class="mb-3">
            <label for="cost" class="form-label">Estimated Cost <span class="text-danger">*</span></label>
            <div class="input-group">
              <span class="input-group-text">Rs.</span>
              <input type="number" step="0.01" class="form-control" id="cost" name="cost" placeholder="0.00" required>
            </div>
          </div>
          <div class="mb-3">
            <label for="performedBy" class="form-label">Performed By <span class="text-danger">*</span></label>
            <input type="text" class="form-control" id="performedBy" name="performedBy" placeholder="Name of person or company performing maintenance" required>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary">Save Record</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Not Required Modal -->
<div class="modal fade" id="notRequiredModal" tabindex="-1" aria-labelledby="notRequiredModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="notRequiredModalLabel">Mark Maintenance as Not Required</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form method="POST" action="manage_maintenance.php">
        <div class="modal-body">
          <input type="hidden" name="action" value="not_required">
          <input type="hidden" name="maintenanceId" id="notRequiredMaintenanceId">
          <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
          <p class="text-muted mb-3">This will mark the maintenance record as not required and the asset will be set as Available.</p>
          <div class="mb-3">
            <label for="notRequiredReason" class="form-label">Reason <span class="text-danger">*</span></label>
            <textarea class="form-control" id="notRequiredReason" name="reason" rows="3" placeholder="Enter reason for marking as not required" required></textarea>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary">Confirm</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- On Progress Modal -->
<div class="modal fade" id="onProgressModal" tabindex="-1" aria-labelledby="onProgressModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="onProgressModalLabel">Mark Maintenance as In Progress</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form method="POST" action="manage_maintenance.php">
        <div class="modal-body">
          <input type="hidden" name="action" value="in_progress">
          <input type="hidden" name="maintenanceId" id="onProgressMaintenanceId">
          <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
          <p class="text-muted mb-3">Provide details about the current progress of this maintenance task.</p>
          <div class="mb-3">
            <label for="progressNotes" class="form-label">Progress Notes <span class="text-danger">*</span></label>
            <textarea class="form-control" id="progressNotes" name="notes" rows="3" placeholder="Enter progress notes, current status, and any relevant details" required></textarea>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary">Update Status</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Completed Modal -->
<div class="modal fade" id="completedModal" tabindex="-1" aria-labelledby="completedModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="completedModalLabel">Mark Maintenance as Completed</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form method="POST" action="manage_maintenance.php">
        <div class="modal-body">
          <input type="hidden" name="action" value="completed">
          <input type="hidden" name="maintenanceId" id="completedMaintenanceId">
          <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
          <p class="text-muted mb-3">This will mark the maintenance as completed and set the asset status to Available.</p>
          <div class="mb-3">
            <label for="completionNotes" class="form-label">Completion Notes <span class="text-danger">*</span></label>
            <textarea class="form-control" id="completionNotes" name="notes" rows="3" placeholder="Enter details about the completed maintenance work" required></textarea>
          </div>
          <div class="mb-3">
            <label for="actualCost" class="form-label">Actual Cost <span class="text-danger">*</span></label>
            <div class="input-group">
              <span class="input-group-text">Rs.</span>
              <input type="number" step="0.01" class="form-control" id="actualCost" name="actualCost" placeholder="0.00" required>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-success">Complete Maintenance</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- REQUIRED SCRIPTS -->
<!-- jQuery -->
<script src="<?php echo $home;?>plugins/jquery/jquery.min.js"></script>
<!-- Bootstrap -->
<script src="<?php echo $home;?>plugins/bootstrap/js/bootstrap.bundle.min.js"></script>
<!-- DataTables & Plugins -->
<script src="<?php echo $home;?>plugins/datatables/jquery.dataTables.min.js"></script>
<script src="<?php echo $home;?>plugins/datatables-bs4/js/dataTables.bootstrap4.min.js"></script>
<script src="<?php echo $home;?>plugins/datatables-responsive/js/dataTables.responsive.min.js"></script>
<script src="<?php echo $home;?>plugins/datatables-responsive/js/responsive.bootstrap4.min.js"></script>
<!-- AdminLTE -->
<script src="<?php echo $home;?>dist/js/adminlte.js"></script>
<!-- Page specific script -->
<script>
document.addEventListener('DOMContentLoaded', function() {
  // Initialize DataTable
  const maintenanceTable = new DataTable('#maintenanceTable', {
    responsive: true,
    lengthChange: true,
    autoWidth: false,
    order: [[1, 'desc']], // Sort by maintenance date (column 1)
    pageLength: 10,
    language: {
      paginate: {
        previous: '<i class="fas fa-chevron-left"></i>',
        next: '<i class="fas fa-chevron-right"></i>'
      }
    },
    // Draw callback to reinitialize click handlers after table redraw
    drawCallback: function() {
      initializeActionButtons();
    }
  });
  
  // Initialize Select2 for asset dropdown
  $('.select2').select2({
    theme: 'bootstrap4',
    dropdownParent: $('#addMaintenanceModal .modal-body')
  });

  // Function to initialize action buttons using event delegation
  function initializeActionButtons() {
    // Use event delegation for action buttons
    $(document).off('click', '.action-button').on('click', '.action-button', function(e) {
      e.preventDefault();
      const action = $(this).data('action');
      const maintenanceId = $(this).data('id');
      
      if (action === 'Maintenance Not Required') {
        document.getElementById('notRequiredMaintenanceId').value = maintenanceId;
        const notRequiredModal = new bootstrap.Modal(document.getElementById('notRequiredModal'));
        notRequiredModal.show();
      } else if (action === 'Maintenance on Progress') {
        document.getElementById('onProgressMaintenanceId').value = maintenanceId;
        const onProgressModal = new bootstrap.Modal(document.getElementById('onProgressModal'));
        onProgressModal.show();
      } else if (action === 'Maintenance Completed') {
        document.getElementById('completedMaintenanceId').value = maintenanceId;
        const completedModal = new bootstrap.Modal(document.getElementById('completedModal'));
        completedModal.show();
      }
    });
  }
  
  // Initialize action buttons on page load
  initializeActionButtons();
  
  // Set today's date as default for date inputs
  document.querySelectorAll('input[type="date"]').forEach(dateInput => {
    if (!dateInput.value) {
      dateInput.valueAsDate = new Date();
    }
  });
});
</script>
</body>
</html>