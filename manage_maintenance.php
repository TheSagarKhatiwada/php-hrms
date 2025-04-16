<?php
ob_start(); // Start output buffering
$page = 'Maintenance Records';
include 'includes/header.php';
include 'includes/db_connection.php';

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
try {
    $stmt = $pdo->prepare("SELECT 
        am.RecordID,
        am.AssetID,
        am.MaintenanceDate,
        am.MaintenanceType,
        am.MaintenancePerformBy,
        am.Description,
        am.Cost,
        am.MaintenanceStatus,
        fa.AssetName,
        fa.AssetSerial,
        fa.Status AS AssetStatus
    FROM 
        AssetMaintenance am
    LEFT JOIN 
        FixedAssets fa 
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
    $stmt = $pdo->prepare("SELECT AssetID, AssetName, AssetSerial FROM FixedAssets WHERE Status = 'Available' ORDER BY AssetName");
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

            try {
                $stmt = $pdo->prepare("INSERT INTO AssetMaintenance (AssetID, MaintenanceDate, MaintenanceType, Description, Cost, MaintenancePerformBy, MaintenanceStatus) 
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
                $stmt = $pdo->prepare("UPDATE FixedAssets SET Status = 'Maintenance' WHERE AssetID = :assetId");
                $stmt->execute([':assetId' => $assetId]);

                $_SESSION['success'] = "Asset added for maintainence successfully.";
            } catch (PDOException $e) {
                // Log the error and display a user-friendly message
                error_log("Database error: " . $e->getMessage(), 3, 'error_log.txt');
                $_SESSION['error'] = "An error occurred while adding the maintenance record. Please try again later.";
            }
        }

        // ...existing code for other actions...
    }

    header("Location: manage_maintenance.php");
    exit();
}

// Debugging: Log submitted data and errors for the 'Not Required' action
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] === 'not_required') {
    $maintenanceId = $_POST['maintenanceId'] ?? null;
    $reason = $_POST['reason'] ?? null;

    // Log submitted data
    error_log("Not Required Action: maintenanceId=" . $maintenanceId . ", reason=" . $reason, 3, 'debug_log.txt');

    try {
        // Update maintenance table
        $stmt = $pdo->prepare("UPDATE AssetMaintenance SET MaintenanceStatus = 'Not Required', Description = :reason WHERE RecordID = :maintenanceId");
        $stmt->execute([
            ':reason' => $reason,
            ':maintenanceId' => $maintenanceId
        ]);

        // Update asset table
        $stmt = $pdo->prepare("SELECT AssetID FROM AssetMaintenance WHERE RecordID = :maintenanceId");
        $stmt->execute([':maintenanceId' => $maintenanceId]);
        $assetId = $stmt->fetchColumn();

        if ($assetId) {
            $stmt = $pdo->prepare("UPDATE FixedAssets SET Status = 'Available' WHERE AssetID = :assetId");
            $stmt->execute([':assetId' => $assetId]);
        }

        $_SESSION['success'] = "Maintenance marked as not required and asset status updated to 'Available'.";
    } catch (PDOException $e) {
        // Log errors
        error_log("Database error: " . $e->getMessage(), 3, 'debug_log.txt');
        $_SESSION['error'] = "An error occurred while updating the maintenance record. Please try again later.";
    }

    header("Location: manage_maintenance.php");
    exit();
}
?>
  <!-- DataTables -->
  <link rel="stylesheet" href="<?php echo $home;?>plugins/datatables-bs4/css/dataTables.bootstrap4.min.css">
  <link rel="stylesheet" href="<?php echo $home;?>plugins/datatables-responsive/css/responsive.bootstrap4.min.css">
  <link rel="stylesheet" href="<?php echo $home;?>plugins/datatables-buttons/css/buttons.bootstrap4.min.css">
  <!-- DataTables Buttons JS -->
  <script src="<?php echo $home;?>plugins/datatables-buttons/js/dataTables.buttons.min.js"></script>
  <script src="<?php echo $home;?>plugins/datatables-buttons/js/buttons.bootstrap4.min.js"></script>
  <script src="<?php echo $home;?>plugins/jszip/jszip.min.js"></script>
  <script src="<?php echo $home;?>plugins/pdfmake/pdfmake.min.js"></script>
  <script src="<?php echo $home;?>plugins/pdfmake/vfs_fonts.js"></script>
  <script src="<?php echo $home;?>plugins/datatables-buttons/js/buttons.html5.min.js"></script>
  <script src="<?php echo $home;?>plugins/datatables-buttons/js/buttons.print.min.js"></script>
  <!-- daterange picker -->
  <link rel="stylesheet" href="<?php echo $home;?>plugins/daterangepicker/daterangepicker.css">
  <!-- Tempusdominus Bootstrap 4 -->
  <link rel="stylesheet" href="<?php echo $home;?>plugins/tempusdominus-bootstrap-4/css/tempusdominus-bootstrap-4.min.css">
  <!-- Select2 -->
  <link rel="stylesheet" href="<?php echo $home;?>plugins/select2/css/select2.min.css">
  <link rel="stylesheet" href="<?php echo $home;?>plugins/select2-bootstrap4-theme/select2-bootstrap4.min.css">
</head>
<body class="hold-transition sidebar-mini layout-footer-fixed layout-navbar-fixed layout-fixed dark-mode">
<div class="wrapper">
  <?php
    include 'includes/topbar.php';
    include 'includes/sidebar.php';
  ?>
  <!-- Content Wrapper. Contains page content -->
  <div class="content-wrapper">
    <!-- Content Header (Page header) -->
    <div class="content-header">
      <div class="container-fluid">
        <div class="row mb-2">
          <div class="col-sm-6">
            <h1 class="m-0">Maintenance Records</h1>
          </div>
          <div class="col-sm-6">
            <ol class="breadcrumb float-sm-right">
              <li class="breadcrumb-item"><a href="assets.php">Assets</a></li>
              <li class="breadcrumb-item active">Maintenance</li>
            </ol>
          </div>
        </div>
      </div>
    </div>
    <!-- Main content -->
    <section class="content">
      <div class="container-fluid">
        <div class="card">
          <!-- <div class="card-header">
            <h3 class="card-title">Manage Maintenance Records</h3>
          </div> -->
          <div class="card-body">
            <table id="maintenanceTable" class="table table-bordered table-striped table-sm">
              <thead>
                <tr>
                  <th>SN</th>
                  <th>Submit Date</th>
                  <th>Asset </th>
                  <th>Type</th>
                  <th>Reason for Maintenance</th>
                  <th>Estimated Cost<br>Actual Cost</th>
                  <th>Status</th>
                  <th>Actions</th>
                </tr>
              </thead>
              <tbody>
                <?php 
                $sn = 1;
                foreach ($records as $record): ?>
                  <tr>
                    <td><?php echo $sn++; ?></td>
                    <td><?php echo date('Y-M-d', strtotime($record['MaintenanceDate'])); ?></td>
                    <td><?php echo htmlspecialchars($record['AssetName']); ?> </br> ( <?php echo htmlspecialchars($record['AssetSerial']);?>)</td>
                    <td><?php echo htmlspecialchars($record['MaintenanceType']); ?></td>
                    <td><?php echo htmlspecialchars($record['Description']); ?></td>
                    <td><?php echo $record['Cost']? number_format($record['Cost'], 2) : 'N/A'; ?></td>
                    <td>
                      <?php if ($record['MaintenanceStatus'] == 'Completed'): ?>
                        <span class="badge badge-success">Completed</span>
                      <?php elseif ($record['MaintenanceStatus'] == 'In Progress'): ?>
                        <span class="badge badge-info">In Progress</span>
                      <?php else: ?>
                        <span class="badge badge-warning">Scheduled</span>
                      <?php endif; ?>
                    </td>
                    <td>
                      <div class="dropdown">
                        <a class="btn btn-link text-secondary p-0" type="button" id="actionsMenu" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                          <i class="fas fa-ellipsis-v"></i>
                        </a>
                        <div class="dropdown-menu dropdown-menu-right" aria-labelledby="dropdownMenuButton">
                          <a class="dropdown-item action-button" data-action="Maintenance Not Required" data-id="<?php echo $record['RecordID']; ?>">
                            <i class="fas fa-times"></i> Not Required
                          </a>
                          <a class="dropdown-item action-button" data-action="Maintenance on Progress" data-id="<?php echo $record['RecordID']; ?>">
                            <i class="fas fa-spinner"></i> On Progress
                          </a>
                          <a class="dropdown-item action-button" data-action="Maintenance Completed" data-id="<?php echo $record['RecordID']; ?>" data-additional-field="Completion Notes">
                            <i class="fas fa-check"></i>Completed
                          </a>
                        </div>
                      </div>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </section>
  </div>
  <?php include 'includes/footer.php'; ?>
</div>
<!-- Add Maintenance Modal -->
<div class="modal fade" id="addMaintenanceModal" tabindex="-1" role="dialog" aria-labelledby="addMaintenanceModalLabel" aria-hidden="true">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="addMaintenanceModalLabel">New Maintenance Record</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <form method="POST" action="manage_maintenance.php">
        <div class="modal-body">
          <input type="hidden" name="action" value="add">
          <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
          <div class="form-group">
            <label for="assetId">Asset</label>
            <select class="form-control" id="assetId" name="assetId" required>
              <option value="">Select an Asset</option>
              <?php foreach ($assets as $asset): ?>
                <option value="<?php echo $asset['AssetID']; ?>">
                  <?php echo htmlspecialchars($asset['AssetName'] . ' (' . $asset['AssetSerial'] . ')'); ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label for="maintenanceDate">Maintenance Date</label>
            <input type="date" class="form-control" id="maintenanceDate" name="maintenanceDate" required>
          </div>
          <div class="form-group">
            <label for="maintenanceType">Maintenance Type</label>
            <select class="form-control" id="maintenanceType" name="maintenanceType" required>
              <option value="Preventive">Preventive</option>
              <option value="Corrective">Corrective</option>
            </select>
          </div>
          <div class="form-group">
            <label for="description">Reason for Maintenance</label>
            <textarea class="form-control" id="description" name="description" rows="3" required></textarea>
          </div>
          <div class="form-group">
            <label for="cost">Cost</label>
            <input type="number" step="0.01" class="form-control" id="cost" name="cost" required>
          </div>
          <div class="form-group">
            <label for="performedBy">Performed By</label>
            <input type="text" class="form-control" id="performedBy" name="performedBy" required>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary">Save Record</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Not Required Modal -->
<div class="modal fade" id="notRequiredModal" tabindex="-1" role="dialog" aria-labelledby="notRequiredModalLabel" aria-hidden="true">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="notRequiredModalLabel">Mark Maintenance as Not Required</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <form method="POST" action="manage_maintenance.php">
        <div class="modal-body">
          <input type="hidden" name="action" value="not_required">
          <input type="hidden" name="maintenanceId" id="notRequiredMaintenanceId">
          <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
          <div class="form-group">
            <label for="notRequiredReason">Reason</label>
            <textarea class="form-control" id="notRequiredReason" name="reason" rows="3" placeholder="Enter reason for marking as not required" required></textarea>
          </div>
        </div>
        <div class="modal-footer">
          <button type="submit" class="btn btn-primary">Submit</button>
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
<script>
  $(document).ready(function() {
    // Initialize DataTable
    $('#maintenanceTable').DataTable({
      "responsive": true,
      "lengthChange": true,
      "autoWidth": false,
      "pageLength": 10,
      "lengthMenu": [[10, 25, 50, 100, -1], [10, 25, 50, 100, "All"]],
      "buttons": ["copy", "csv", "excel", "pdf", "print"]
    });
    // Add custom button below the filter
    $('#maintenanceTable_filter').after('<div class="custom-filter-button" style="display: flex; justify-content: flex-end;"><button class="btn btn-primary" id="custom-filter-btn"><i class="fas fa-tools"></i> Add Maintenance</button></div>');
    // Custom button action
    $('#custom-filter-btn').on('click', function() {
      $('#addMaintenanceModal').modal({
        backdrop: 'static',
        keyboard: false
      });
    });
  });

  document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.action-button[data-action="Maintenance Not Required"]').forEach(function(button) {
      button.addEventListener('click', function() {
        const maintenanceId = this.dataset.id;
        document.getElementById('notRequiredMaintenanceId').value = maintenanceId;
        $('#notRequiredModal').modal('show');
      });
    });
  });
</script>
</body>
</html>