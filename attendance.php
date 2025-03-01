<?php
$page = 'attendance';
include 'includes/header.php';
include 'includes/db_connection.php';  // Add your database connection file here

// Fetching attendance data
try {
    $stmt = $pdo->prepare("SELECT a.*, e.first_name, e.last_name, e.middle_name, e.branch, e.emp_id, e.user_image, e.designation, a.date, a.time, a.method, a.id, b.name FROM attendance_logs a INNER JOIN employees e ON a.emp_Id = e.emp_id INNER JOIN branches b ON e.branch = b.id WHERE a.method = 1 ORDER BY a.date DESC");
    $stmt->execute();
    $attendanceRecords = $stmt->fetchAll();

} catch (PDOException $e) {
    echo "Error fetching attendance data: " . $e->getMessage();
    exit;
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
            <h1 class="m-0">Attendance Records</h1>
          </div><!-- /.col -->
          <div class="col-sm-6">
            <ol class="breadcrumb float-sm-right">
              <li class="breadcrumb-item"><a href="#">Home</a></li>
              <li class="breadcrumb-item active">Attendances</li>
            </ol>
          </div><!-- /.col -->
        </div><!-- /.row -->
      </div><!-- /.container-fluid -->
    </div>
    <!-- /.content-header -->
    <!-- Main content -->
    <section class="content">
     <div class="container-fluid">
      <div class="card">
        <?php
          if (isset($_GET['status']) && $_GET['status'] == 'deleted') {
              echo "<div class='alert alert-success'>Attendance record deleted successfully.</div>";
          }elseif (isset($_GET["status"]) && $_GET["status"] == "manual-sucess") {
              echo "<div class='alert alert-success'>Attendance record added successfully.</div>";
          }
        ?>
        <span id="status"></span>
        <!-- <div class="card-header">
          <h3 class="card-title">Users</h3>
        </div> -->
        <!-- /.card-header -->
        <div class="card-body">
          <table id="attendance-table" class="table table-sm table-bordered table-striped" width="100%">
            <thead>
              <tr>
                <th class="align-items-center text-center">Emp. ID</th>
                <th class="align-items-center text-left">Employee Details</th>
                <th class="align-items-center text-center">Branch</th>
                <th class="align-items-center text-center">Attendance Date</th>
                <th class="align-items-center text-center">Time</th>
                <th class="align-items-center text-center">Method</th>
                <th class="align-items-center text-center">Reason</th>
                <th class="align-items-center text-center">Action</th>
              </tr>
            </thead>
            <tbody>
              <?php 
                foreach ($attendanceRecords as $record) {
                  echo "<tr>";
                  echo "<td class='align-items-center text-center'>{$record['emp_id']}</td>";
                  echo "<td class='align-items-center text-left'>
                        <div style='display: flex; align-items: center;'>
                          <div class='empImage' style='margin-right: 1rem;'>
                            <img src='{$home}resources/userimg/default-image.jpg' alt='Employee Image' class='employee-image' style='width: 50px; height: 50px; border-radius: 50%; object-fit: cover;'>
                          </div>
                          <div class='empNameDegi'>
                            <strong>{$record['first_name']} {$record['middle_name']} {$record['last_name']}</strong><br>{$record['designation']}
                          </div>
                        </div>
                        </td>";
                  echo "<td class='align-items-center text-center'>{$record['name']}</td>";
                  echo "<td class='align-items-center text-center'>" . date('d M, Y', strtotime($record['date'])) . "</td>";
                  echo "<td class='align-items-center text-center'>{$record['time']}</td>";
                  echo "<td class='align-items-center text-center'>";
                  if($record['method'] == 0){
                    echo'Auto';
                  }else{
                    echo 'Manual'; 
                  }
                   echo "</td>";
                  echo "<td class='align-items-center text-center'>{$record['manual_reason']}</td>";
                  echo "<td class='align-items-center text-center'>
                          <!--<a href='edit-attendance.php?id={$record['id']}' class='btn btn-primary btn-sm'>Edit</a> -->
                          <a href='delete-attendance.php?id={$record['id']}' class='btn btn-danger btn-sm'>Delete</a>
                        </td>";
                  echo "</tr>";
                }
              ?>
            </tbody>

          </table>
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

  <?php 
  include 'includes/footer.php';
  ?>

</div>
<!-- ./wrapper -->

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
<!-- AdminLTE for demo purposes -->
<script src="<?php echo $home;?>dist/js/demo.js"></script>
<!-- Include required scripts -->
<script src="<?php echo $home;?>plugins/moment/moment.min.js"></script>
<script src="<?php echo $home;?>plugins/daterangepicker/daterangepicker.js"></script>
<!-- Tempusdominus Bootstrap 4 -->
<script src="<?php echo $home;?>plugins/tempusdominus-bootstrap-4/js/tempusdominus-bootstrap-4.min.js"></script>
<!-- Select2 -->
<script src="<?php echo $home;?>plugins/select2/js/select2.full.min.js"></script>

<!-- Page Specific Scripts -->
<script>
  $(function () {
    $("#attendance-table").DataTable({
      "responsive": true,
      "lengthChange": true,
      "autoWidth": true,
      "paging": true,
      "searching": true,
      "ordering": true,
      "order": [[3, 'desc']], // [columnIndex, 'asc' or 'desc']
      "info": true,
      "pageLength": 10, // Set the default number of rows to display
      "lengthMenu": [ [10, 25, 50, -1], [10, 25, 50, "All"] ], // Define the options in the page length dropdown menu
      "pagingType": "full_numbers", // Controls the pagination controls' appearance (options: 'simple', 'simple_numbers', 'full', 'full_numbers', 'first_last_numbers')
      "buttons": ["colvis"], //copy, csv, excel, pdf, print, colvis
      "language": {
        "paginate": {
          "first": '<i class="fas fa-angle-double-left"></i>',
          "previous": '<i class="fas fa-angle-left"></i>',
          "next": '<i class="fas fa-angle-right"></i>',
          "last": '<i class="fas fa-angle-double-right"></i>'
        },
        "emptyTable": "No data available in table",
        "info": "Showing _START_ to _END_ of _TOTAL_ entries",
        "infoEmpty": "Showing 0 to 0 of 0 entries",
        "infoFiltered": "(filtered from _MAX_ total entries)",
        "lengthMenu": "Show _MENU_ entries",
        "loadingRecords": "Loading...",
        "processing": "Processing...",
        "search": "Search:",
        "zeroRecords": "No matching records found"
      }
    }).buttons().container().appendTo('#attendance-table_wrapper .col-md-6:eq(0)');

    // Add custom button below the filter
    $('#attendance-table_filter').after('<div class="custom-filter-button" style="display: flex; justify-content: flex-end; margin-bottom: 10px;"><button class="btn btn-primary" id="custom-filter-btn">Add Attendance</button></div>');
    
    // Add custom button below the filter
    $('#custom-filter-btn').before('<div class="custom-filter-button" style="display: flex; justify-content: flex-end; margin-bottom: 0px; margin-right: 5px;"><button class="btn btn-primary" id="update-log">Refresh</button></div>');

    // Refresh the attendance log
    $(document).ready(function() {
            $("#update-log").click(function() {
                $.ajax({
                    url: "update-log.php", // PHP file to execute
                    type: "POST",
                    success: function(response) {
                        $("#status").html(response); // Display response message
                    },
                    error: function() {
                        $("#status").html('<div class="alert alert-danger">Error processing request.</div>');
                    }
                });
            });
        });


    $('#custom-filter-btn').on('click', function() {
      $('#addAttendanceModal').modal({
        backdrop: 'static',
        keyboard: false
      });
    });

    // Initialize the time picker
    $('#attendanceTime').datetimepicker({
      format: 'LT',
      defaultDate: moment(),
      format: 'HH:mm:ss'
    });

    // Initialize Select2 with custom template for employee select
    function formatState (state) {
      if (!state.id) {
        return state.text;
      }
      var baseUrl = "<?php echo $home;?>resources/userimg/";
      var imageUrl = baseUrl + state.element.value.toLowerCase() + '.jpg';
      var defaultImageUrl = baseUrl + 'default-image.jpg';
      var $state = $(
        '<span><img src="' + imageUrl + '" onerror="this.onerror=null;this.src=\'' + defaultImageUrl + '\';" class="img-flag" style="width: 20px; height: 20px; border-radius: 80%; margin-right: 10px;" /> ' + state.text + '</span>'
      );
      return $state;
    };

    function formatStateSelection (state) {
      return state.text;
    };

    $('.select2-employee').select2({
      templateResult: formatState,
      templateSelection: formatStateSelection
    });
  });
</script>

<!-- Add Attendance Modal -->
<div class="modal fade" id="addAttendanceModal" tabindex="-1" role="dialog" aria-labelledby="addAttendanceModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="addAttendanceModalLabel">Add Attendance</h5>
        <!-- <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button> -->
      </div>
      <div class="modal-body">
        <div class="col-12 col-sm-12">
            <div class="card card-primary card-outline card-outline-tabs">
              <div class="card-header p-0 border-bottom-0">
                <ul class="nav nav-tabs" id="custom-tabs-four-tab" role="tablist">
                  <li class="nav-item">
                    <a class="nav-link active" id="custom-tabs-four-home-tab" data-toggle="pill" href="#custom-tabs-four-home" role="tab" aria-controls="custom-tabs-four-home" aria-selected="true">Upload</a>
                  </li>
                  <li class="nav-item">
                    <a class="nav-link" id="custom-tabs-four-profile-tab" data-toggle="pill" href="#custom-tabs-four-profile" role="tab" aria-controls="custom-tabs-four-profile" aria-selected="false">Manual</a>
                  </li>
                </ul>
              </div>
              <div class="card-body">
                <div class="tab-content" id="custom-tabs-four-tabContent">
                  <div class="tab-pane fade" id="custom-tabs-four-profile" role="tabpanel" aria-labelledby="custom-tabs-four-profile-tab">
                    <div class="row">
                      <div class="col-md-6">
                        <form id="manualAttendance" method="POST" action="record_manual_attendance.php">
                          <div class="form-group">
                          <select class="form-control select2" style="width: 100%;" id="empBranch" name="empBranch" required>
                            <option selected disabled>Select a Branch</option>
                              <!-- Optionally populate this statically or dynamically from the database -->
                              <?php 
                                  $branchQuery = "SELECT DISTINCT id, name FROM branches";
                                  $stmt = $pdo->query($branchQuery);
                                  while ($row = $stmt->fetch()) {
                                      echo "<option value='{$row['id']}'>{$row['name']}</option>";
                                  }
                              ?>
                          </select>
                            <label for="empBranch" class="form-label">Branch <span style="color:red;">*</span></label>
                          </div>
                        </div>
                        <div class="col-md-6">
                          <div class="form-group">
                            <!-- User Dropdown will be populated after branch selection -->
                            <select class="form-control select2" style="width: 100%;" id="emp_id" name="empId" required>
                                <option value="">Select Employee</option>
                            </select>
                            <label for="emp_id" class="form-label">Employee <span style="color:red;">*</span></label>
                          </div>
                        </div>
                        <div class="col-md-12">
                          <div class="row">
                            <div class="col-md-6">
                              <div class="form-group">
                                <input type="date" class="form-control" id="Attendance" name="attendanceDate" placeholder=" " value="<?php echo date('Y-m-d'); ?>" min="<?php echo date('Y-m-d', strtotime('-30 days')); ?>" max="<?php echo date('Y-m-d'); ?>" required>
                                <label for="Attendance" class="form-label">Attendance Date <span style="color:red;">*</span></label>
                              </div>
                            </div>
                            <div class="col-md-6">
                              <div class="form-group">
                                <input type="text" class="form-control datetimepicker-input" id="attendanceTime" name="attendanceTime" data-toggle="datetimepicker" data-target="#attendancetime" value="" required>
                                <label for="attendancetime" class="form-label">Attendance Time <span style="color:red;">*</span></label>
                              </div>
                            </div>
                          </div>
                          <div class="row">
                            <div class="col-md-6">
                              <div class="form-group">
                                <select class="form-control select2" style="width: 100%;" name="reason" id="reason" required>
                                  <option selected disabled>Select a Reason</option>
                                  <option value="1">Card Forgot</option>
                                  <option value="2">Card Lost</option>
                                  <option value="3">Forgot to Punch</option>
                                  <option value="4">Office Work Delay</option>
                                  <option value="5">Feild Visit</option>
                                </select>
                                <label for="reason" class="form-label">Reason <span style="color:red;">*</span></label>
                              </div>
                            </div>
                              <div class="col-md-6">
                                <button type="submit" form="manualAttendance" class="btn btn-primary float-right">Save Attendance</button>
                              </div>
                          </div>
                          
                        </div>
                      </div>
                  </form>
                </div>
                  <div class="tab-pane fade active show" id="custom-tabs-four-home" role="tabpanel" aria-labelledby="custom-tabs-four-home-tab">
                    <div class="row">
                      <div class="col-md-9">
                        <form action="upload-attendance.php" method="post" enctype="multipart/form-data">
                          <div class="form-group">
                            <input type="file" class="form-control" id="attendanceFile" name="attendanceFile" type="file" accept=".txt" required>
                            <label for="attendanceFile" class="form-label">Upload Attendance File <span class="text-red"> *</span></label>
                          </div>
                      </div>
                      <div class="col-md-3">
                      <button class="btn btn-warning" type="submit">Upload & Process</button>
                      </div>
                        </form>
                    </div>
                    <div class="check-result text-warning text-center text-bold">*** 514 Logs can be saved ***</div>
                  </div>
                </div>
              </div>
              <!-- /.card -->
            </div>
          </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-danger" data-dismiss="modal" aria-label="Close">Cancel</button>
          </div>
      </div>
    </div>
  </div>
</div>
<!-- JavaScript to handle the AJAX request and populate the employee dropdown based on the selected branch: -->
<script>
  $(document).ready(function () {
    $('#empBranch').change(function () {
      var branch = $(this).val(); // Get the selected branch

      if (branch) {
        $.ajax({
          url: 'fetch_users.php',
          type: 'POST',
          data: { branch: branch },
          success: function (response) {
            $('#emp_id').html(response); // Populate the user dropdown
          }
        });
      } else {
        $('#emp_id').html('<option value="">Select Employee</option>');
      }
    });
  });
</script>

</body>
</html>