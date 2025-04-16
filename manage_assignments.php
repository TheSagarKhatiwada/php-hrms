<?php
ob_start(); // Start output buffering
$page = 'Asset Assignments';
include 'includes/header.php';
include 'includes/db_connection.php';

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
            $expectedReturnDate = $_POST['expectedReturnDate'];
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
                $stmt = $pdo->prepare("INSERT INTO AssetAssignments (AssetID, EmployeeID, AssignmentDate, ExpectedReturnDate, Notes) 
                                     VALUES (:assetId, :employeeId, :assignDate, :expectedReturnDate, :notes)");
                $stmt->execute([
                    ':assetId' => $assetId,
                    ':employeeId' => $employeeId,
                    ':assignDate' => $assignDate,
                    ':expectedReturnDate' => $expectedReturnDate,
                    ':notes' => $notes
                ]);
                
                // Update asset status to 'Assigned'
                $stmt = $pdo->prepare("UPDATE FixedAssets SET Status = 'Assigned' WHERE AssetID = :assetId");
                $stmt->execute([':assetId' => $assetId]);
                
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
                $stmt = $pdo->prepare("SELECT AssetID FROM AssetAssignments WHERE AssignmentID = :assignmentId");
                $stmt->execute([':assignmentId' => $assignmentId]);
                $assignment = $stmt->fetch();
                $assetId = $assignment['AssetID'];

                // Append maintenance note if needed
                if ($needsMaintenance) {
                    $returnNotes = "Need Maintenance (" . $returnNotes . ")";
                }

                

                // Update assignment record
                $stmt = $pdo->prepare("UPDATE AssetAssignments SET ReturnDate = :returnDate, ReturnNotes = :returnNotes 
                                     WHERE AssignmentID = :assignmentId");
                $stmt->execute([
                    ':returnDate' => $returnDate,
                    ':returnNotes' => $returnNotes,
                    ':assignmentId' => $assignmentId
                ]);
              
                // Update asset status
                $newStatus = $needsMaintenance ? 'Maintenance' : 'Available';
                $stmt = $pdo->prepare("UPDATE FixedAssets SET Status = :newStatus WHERE AssetID = :assetId");
                $stmt->execute([
                    ':newStatus' => $newStatus,
                    ':assetId' => $assetId
                ]);
                
                // Fetch the assigned user's name
                $stmt = $pdo->prepare("SELECT e.First_Name, e.Middle_Name, e.Last_Name FROM employees e JOIN AssetAssignments aa ON e.ID = aa.EmployeeID WHERE aa.AssignmentID = :assignmentId");
                $stmt->execute([':assignmentId' => $assignmentId]);
                $assignedUser = $stmt->fetch();
                $assignedUserName = $assignedUser ? $assignedUser['First_Name'] . ' ' . $assignedUser['Middle_Name'] . ' ' . $assignedUser['Last_Name'] : 'Unknown User';

                // Update the return notes
                $returnNotes = "Returned from $assignedUserName with note: " . $returnNotes;
                
                // Insert into AssetMaintenance table if maintenance is needed
                if ($needsMaintenance) {
                    $stmt = $pdo->prepare("INSERT INTO AssetMaintenance (AssetID, MaintenanceDate, Description) 
                                         VALUES (:assetId, :maintenanceDate, :notes)");
                    $stmt->execute([
                        ':assetId' => $assetId,
                        ':maintenanceDate' => $returnDate,
                        ':notes' => $returnNotes
                    ]);
                }
                $_SESSION['success'] = "Asset returned successfully!";
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

// Fetch all assignments
try {
    $stmt = $pdo->query("SELECT 
        aa.*,
        fa.AssetName,
        fa.AssetSerial,
        ac.CategoryShortCode,
        e.First_Name,
        e.Last_Name,
        e.Designation
    FROM AssetAssignments aa
    LEFT JOIN fixedassets fa ON aa.AssetID = fa.AssetID
    LEFT JOIN assetcategories ac ON fa.CategoryID = ac.CategoryID
    LEFT JOIN employees e ON aa.EmployeeID = e.ID
    ORDER BY aa.AssignmentDate DESC");
    $assignments = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $_SESSION['error'] = "Error fetching data: " . $e->getMessage();
    $assignments = [];
}
?>
  <!-- DataTables -->
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
            <h1 class="m-0">Asset Assignments</h1>
          </div>
          <div class="col-sm-6">
            <ol class="breadcrumb float-sm-right">
              <li class="breadcrumb-item"><a href="assets.php">Assets</a></li>
              <li class="breadcrumb-item active">Assignments</li>
            </ol>
          </div>
        </div>
      </div>
    </div>
    
    <!-- Main content -->
    <section class="content">
      <div class="container-fluid">
        <div class="row">
          <div class="col-12">
            <div class="card">
              <div class="card-body">
                <?php if (isset($_SESSION['success'])): ?>
                  <script>
                    $(document).ready(function() {
                      showSuccessToast('<?php echo $_SESSION['success']; ?>');
                    });
                  </script>
                  <?php unset($_SESSION['success']); ?>
                <?php endif; ?>
                
                <?php if (isset($_SESSION['error'])): ?>
                  <script>
                    $(document).ready(function() {
                      showErrorToast('<?php echo $_SESSION['error']; ?>');
                    });
                  </script>
                  <?php unset($_SESSION['error']); ?>
                <?php endif; ?>
                  <table id="assignmentsTable" class="table table-bordered table-striped table-sm">
                    <thead>
                      <tr>
                        <th>SN</th>
                        <th>Asset Name</th>
                        <th>Serial No</th>
                        <th>Employee</th>
                        <th>Assigned Date</th>
                        <th>Issuing Note</th>
                        <th>Expected Return on</th>
                        <th>Return Date</th>
                        <th>Return Note</th>
                        <th>Actions</th>
                      </tr>
                    </thead>
                    <tbody>
                      <?php 
                      $sn = 1;
                      foreach ($assignments as $assignment): ?>
                        <tr>
                          <td><?php echo $sn++; ?></td>
                          <td><?php echo $assignment['AssetName']; ?></td>
                          <td><?php echo $assignment['AssetSerial']; ?></td>
                          <td><?php echo ($assignment['First_Name'] . ' ' . $assignment['Last_Name'] . ' (' . $assignment['Designation'] . ')') ?? $assignment['EmployeeID']; ?></td>
                          <td><?php echo $assignment['AssignmentDate']; ?></td>
                          <td><?php echo $assignment['Notes']; ?></td>
                          <td><?php echo $assignment['ExpectedReturnDate']; ?></td>
                          <td><?php echo $assignment['ReturnDate'] ? $assignment['ReturnDate'] : '-'; ?></td>
                          <td><?php echo $assignment['ReturnNotes'] ? $assignment['ReturnNotes'] : '-'; ?></td>
                          <td>
                            <?php if (empty($assignment['ReturnDate'])): ?>
                              <button type="button" class="btn btn-warning btn-sm return-asset" 
                                      data-id="<?php echo $assignment['AssignmentID']; ?>"
                                      data-name="<?php echo $assignment['AssetName'] . ' (' . $assignment['AssetSerial'] . ')'; ?>">
                                <i class="fas fa-undo"></i> Return
                              </button>
                            <?php else: ?>
                              <span class="text-muted">Returned</span>
                            <?php endif; ?>
                          </td>
                        </tr>
                      <?php endforeach; ?>
                    </tbody>
                  </table>
                </div>
              </div>
              <!-- /.card-body -->
            </div>
            <!-- /.card -->
          </div>
          <!-- /.col -->
        </div>
        <!-- /.row -->
      </div><!-- /.container-fluid -->
    </section><!-- /.content -->
  </div><!-- /.content-wrapper -->

  <!-- Add Assignment Modal -->
  <div class="modal fade" id="addAssignmentModal" tabindex="-1" role="dialog" aria-labelledby="addAssignmentModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="addAssignmentModalLabel">New Asset Assignment</h5>
          <button type="button" class="close" data-dismiss="modal" aria-label="Close">
            <span aria-hidden="true">&times;</span>
          </button>
        </div>
        <form method="POST" action="manage_assignments.php">
          <div class="modal-body">
            <input type="hidden" name="action" value="add">
            <div class="form-group">
              <label for="assetId">Asset</label>
              <select class="form-control" id="assetId" name="assetId" required>
                <option value="">Select Asset</option>
                <?php
                // Fetch available assets (not assigned)
                $stmt = $pdo->query("SELECT fa.AssetID, fa.AssetName, fa.AssetSerial, ac.CategoryShortCode 
                                   FROM fixedassets fa 
                                   LEFT JOIN assetcategories ac ON fa.CategoryID = ac.CategoryID
                                   LEFT JOIN AssetAssignments aa ON fa.AssetID = aa.AssetID 
                                   WHERE fa.Status = 'Available' 
                                   ORDER BY fa.AssetName");
                while ($asset = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    echo "<option value='{$asset['AssetID']}'>{$asset['AssetName']} ({$asset['AssetSerial']})</option>";
                }
                ?>
              </select>
            </div>
            <div class="form-group">
              <label for="employeeId">Employee</label>
              <select class="form-control" id="employeeId" name="employeeId" required>
                <option value="">Select Employee</option>
                <?php
                // Fetch all employees to whom assignment goes
                $stmt = $pdo->query("SELECT ID, CONCAT(First_Name, ' ', Last_Name, ' (', Designation, ')') AS EmployeeName 
                                     FROM employees");
                while ($employee = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    echo "<option value='{$employee['ID']}'>{$employee['EmployeeName']}</option>";
                }
                ?>
              </select>
            </div>
            <div class="form-group">
              <label for="assignDate">Assign Date</label>
              <input type="date" class="form-control" id="assignDate" name="assignDate" required>
            </div>
            <div class="form-group">
              <label for="expectedReturnDate">Expected Return Date</label>
              <input type="date" class="form-control" id="expectedReturnDate" name="expectedReturnDate" min="<?php echo date('Y-m-d'); ?>">
            </div>
            <div class="form-group">
              <label for="notes">Notes</label>
              <textarea class="form-control" id="notes" name="notes" rows="3"></textarea>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-primary">Assign Asset</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- Return Asset Modal -->
  <div class="modal fade" id="returnAssetModal" tabindex="-1" role="dialog" aria-labelledby="returnAssetModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="returnAssetModalLabel">Return Asset</h5>
          <button type="button" class="close" data-dismiss="modal" aria-label="Close">
            <span aria-hidden="true">&times;</span>
          </button>
        </div>
        <form method="POST" action="manage_assignments.php">
          <div class="modal-body">
            <input type="hidden" name="action" value="return">
            <input type="hidden" name="assignmentId" id="returnAssignmentId">
            <p>Are you sure you want to return the asset: <strong id="returnAssetName"></strong>?</p>
            <div class="form-group">
              <label for="returnDate">Return Date</label>
              <input type="date" class="form-control" id="returnDate" name="returnDate" max="<?php echo date('Y-m-d'); ?>" required>
            </div>
            <div class="form-group">
              <label for="returnNotes">Return Notes</label>
              <textarea class="form-control" id="returnNotes" name="returnNotes" rows="3"></textarea>
            </div>
            <div class="form-group">
              <div class="form-check">
                <input class="form-check-input" type="checkbox" id="needsMaintenance" name="needsMaintenance">
                <label class="form-check-label" for="needsMaintenance">
                  Needs Maintenance
                </label>
              </div>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-success">Confirm Return</button>
          </div>
        </form>
      </div>
    </div>
  </div>
  <?php include 'includes/footer.php'; ?>
</div>
<!-- REQUIRED SCRIPTS -->
<!-- jQuery -->
<script src="<?php echo $home;?>plugins/jquery/jquery.min.js"></script>
<!-- Bootstrap -->
<script src="<?php echo $home;?>plugins/bootstrap/js/bootstrap.bundle.min.js"></script>
<!-- DataTables  & Plugins -->
<script src="<?php echo $home;?>plugins/datatables/jquery.dataTables.min.js"></script>
<script src="<?php echo $home;?>plugins/datatables-bs4/js/dataTables.bootstrap4.min.js"></script>
<script src="<?php echo $home;?>plugins/datatables-responsive/js/dataTables.responsive.min.js"></script>
<script src="<?php echo $home;?>plugins/datatables-responsive/js/responsive.bootstrap4.min.js"></script>
<script src="<?php echo $home;?>plugins/datatables-buttons/js/dataTables.buttons.min.js"></script>
<script src="<?php echo $home;?>plugins/datatables-buttons/js/buttons.bootstrap4.min.js"></script>
<script src="<?php echo $home;?>plugins/jszip/jszip.min.js"></script>
<script src="<?php echo $home;?>plugins/pdfmake/pdfmake.min.js"></script>
<script src="<?php echo $home;?>plugins/pdfmake/vfs_fonts.js"></script>
<script src="<?php echo $home;?>plugins/datatables-buttons/js/buttons.html5.min.js"></script>
<script src="<?php echo $home;?>plugins/datatables-buttons/js/buttons.print.min.js"></script>
<script src="<?php echo $home;?>plugins/datatables-buttons/js/buttons.colVis.min.js"></script>
<!-- AdminLTE -->
<script src="<?php echo $home;?>dist/js/adminlte.js"></script>
<!-- Page Specific Scripts -->
<script>
  $(function () {
    $("#assignmentsTable").DataTable({
      "responsive": true,
      "lengthChange": true,
      "autoWidth": false,
      "paging": true,
      "searching": true,
      "ordering": true,
      "order": [[0, 'asc']], // [columnIndex, 'asc' or 'desc']
      "info": true,
      "pageLength": 10, // Set the default number of rows to display
      "lengthMenu": [ [10, 25, 50, -1], [10, 25, 50, "All"] ], // Define the options in the page length dropdown menu
      "pagingType": "full_numbers", // Controls the pagination controls' appearance (options: 'simple', 'simple_numbers', 'full', 'full_numbers', 'first_last_numbers')
      "buttons": ["colvis"], //copy, csv, excel, pdf, print, colvis
      "language": {
        "emptyTable": "No data available in table",
        "info": "Showing _START_ to _END_ of _TOTAL_ entries",
        "infoEmpty": "Showing 0 to 0 of 0 entries",
        "infoFiltered": "(filtered from _MAX_ total entries)",
        "lengthMenu": "Show _MENU_ entries",
        "loadingRecords": "Loading...",
        "processing": "Processing...",
        "search": "Search:",
        "zeroRecords": "No matching records found",
        "paginate": {
          "first": '<i class="fas fa-angle-double-left"></i>',
          "previous": '<i class="fas fa-angle-left"></i>',
          "next": '<i class="fas fa-angle-right"></i>',
          "last": '<i class="fas fa-angle-double-right"></i>'
        }
      }
    }).buttons().container().appendTo('#assignmentsTable_wrapper .col-md-6:eq(0)');

    // Add custom button below the filter
    $('#assignmentsTable_filter').after('<div class="custom-filter-button" style="display: flex; justify-content: flex-end;"><button class="btn btn-primary" id="custom-filter-btn"><i class="fas fa-user-check"></i> New Assignment</button></div>');

    // Custom button action
    $('#custom-filter-btn').on('click', function() {
      $('#addAssignmentModal').modal({
        backdrop: 'static',
        keyboard: false
      });
    });

    // Return asset button click
    $('.return-asset').click(function() {
      var id = $(this).data('id');
      var name = $(this).data('name');
      $('#returnAssignmentId').val(id);
      $('#returnAssetName').text(name);
      $('#returnDate').val(new Date().toISOString().split('T')[0]);
      $('#returnAssetModal').modal('show');
    });

    // Set today's date as default for assign date
    $('#assignDate').val(new Date().toISOString().split('T')[0]);
  });
</script>
</body>
</html>