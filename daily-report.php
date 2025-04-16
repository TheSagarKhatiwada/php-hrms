<?php
$page = 'daily-report';
$accessRole = isset($_SESSION['role']) ? $_SESSION['role'] : null;
if ($accessRole === '0') {
    header('Location: dashboard.php');
    exit();
}
include 'includes/header.php';
include 'includes/db_connection.php';
?>
  <!-- DataTables -->
  <link rel="stylesheet" href="<?php echo $home;?>plugins/datatables-bs4/css/dataTables.bootstrap4.min.css">
  <link rel="stylesheet" href="<?php echo $home;?>plugins/datatables-responsive/css/responsive.bootstrap4.min.css">
  <link rel="stylesheet" href="<?php echo $home;?>plugins/datatables-buttons/css/buttons.bootstrap4.min.css">
  
</head>
<body class="hold-transition sidebar-mini layout-footer-fixed layout-navbar-fixed layout-fixed dark-mode sidebar-collapse">
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
            <h1 class="m-0">Daily Report</h1>
          </div><!-- /.col -->
          <div class="col-sm-6">
            <ol class="breadcrumb float-sm-right">
              <li class="breadcrumb-item"><a href="#">Home</a></li>
              <li class="breadcrumb-item active">Daily Report</li>
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
              <div class="card-header pb-0">
                <form action="fetch-daily-report-data.php" method="POST" id="daily-report-form">
                  <div class="reportDate mt-3">
                    <div class="row">
                      <div class="col-md-2">
                        <div class="form-group">
                          <input type="date" class="form-control" id="reportdate" name="reportdate" 
                          value="<?= isset($_POST['reportdate']) ? $_POST['reportdate'] : date('Y-m-d'); ?>" 
                          max="<?= date('Y-m-d'); ?>" required>
                          <label for="reportdate" class="form-label">Report Date <span style="color:red;">*</span></label>
                        </div>
                      </div>
                      <div class="col-md-2">
                        <div class="form-group">
                          <select class="form-control select2" style="width: 100%;" id="empBranch" name="empBranch" required>
                            <option disabled>Select a Branch</option>
                            <option value="" <?= (isset($_POST['empBranch']) && $_POST['empBranch'] === '') ? 'selected' : ''; ?>>All Branches</option>
                            <?php 
                                $selectedBranch = isset($_POST['empBranch']) ? $_POST['empBranch'] : ''; // Store selected value
                                $branchQuery = "SELECT id, name FROM branches";
                                $stmt = $pdo->query($branchQuery);
                                while ($row = $stmt->fetch()) {
                                    $selected = ($selectedBranch == $row['id']) ? 'selected' : ''; // Compare values correctly
                                    echo "<option value='{$row['id']}' $selected>{$row['name']}</option>";
                                }
                            ?>
                          </select>

                          <label for="empBranch" class="form-label">Branch <span style="color:red;">*</span></label>
                        </div>
                      </div>
                      <div class="col-md-2">
                        <input type="submit" class="btn btn-primary"></input>
                      </div>
                    </div>
                  </div>
                </form>
              </div>
              <!-- /.card-header -->
               
              <div class="card-body">
                <table id="daily-report-table" class="table table-sm table-bordered table-striped" width="100%">
                  <thead>
                    <!-- <tr>
                      <th class="align-items-center text-center" style="font-size: 1.8rem;" colspan="18">Prime Express Courier & Cargo Pvt. Ltd.</th>
                    </tr> -->
                    <tr>
                      <th class="align-items-center text-center" colspan="18">Daily Attendance Report: <?php if (isset($_POST['reportdate'])) { echo $_POST['reportdate']; } ?></th>
                    </tr>
                    <tr>
                      <th class="align-items-center text-center" rowspan="2">SN</th>
                      <th class="align-items-center text-center" rowspan="2">Employee Name</th>
                      <th class="align-items-center text-center" rowspan="2">Designation</th>
                      <th class="align-items-center text-center" rowspan="2">Branch</th>
                      <th class="align-items-center text-center" colspan="3">Planned Time</th>
                      <th class="align-items-center text-center" colspan="3">Worked Time</th>
                      <th class="align-items-center text-center" rowspan="2">Overtime</th>
                      <th class="align-items-center text-center" rowspan="2">Late In</th>
                      <th class="align-items-center text-center" rowspan="2">Early Out</th>
                      <th class="align-items-center text-center" rowspan="2">Early In</th>
                      <th class="align-items-center text-center" rowspan="2">Late Out</th>
                      <th class="align-items-center text-center" rowspan="2">Marked As</th>
                      <th class="align-items-center text-center" rowspan="2">Methods</th>
                      <th class="align-items-center text-center" rowspan="2">Remarks</th>
                    </tr>
                    <tr>
                      <th class="align-items-center text-center">In</th>
                      <th class="align-items-center text-center">Out</th>
                      <th class="align-items-center text-center">Work hrs</th>
                      <th class="align-items-center text-center">In</th>
                      <th class="align-items-center text-center">Out</th>
                      <th class="align-items-center text-center">Actual</th>
                    </tr>
                  </thead>
                  <tbody>
                  <?php 
                
                if (isset($_POST['jsonData'])) {
                  $jsonData = json_decode($_POST['jsonData'], true); // Convert JSON to array
                  
                  if ($jsonData) {
                    foreach ($jsonData as $index => $row) {
               ?>
                    <tr>
                        <td class="align-items-center text-center"><?php echo $index + 1; ?></td>
                        <td class="align-items-center text-left"><b><?php echo $row['emp_id'] . " - " .$row['employee_name'] ?></b></td>
                        <td class="align-items-center text-left"><?php echo $row['designation'] ?></td>
                        <td class="align-items-center text-center"><?php echo $row['branch'] ?></td>
                        <td class="align-items-center text-center"><?php echo $row['scheduled_in'] ?></td>
                        <td class="align-items-center text-center"><?php echo $row['scheduled_out'] ?></td>
                        <td class="align-items-center text-center"><?php echo $row['working_hour'] ?></td>
                        <td class="align-items-center text-center"><?php echo $row['in_time'] ?></td>
                        <td class="align-items-center text-center"><?php echo $row['out_time'] ?></td>
                        <td class="align-items-center text-center"><?php echo $row['worked_duration'] ?></td> 
                        <td class="align-items-center text-center"><?php echo $row['over_time'] ?></td>
                        <td class="align-items-center text-center"><?php echo $row['late_in'] ?></td>
                        <td class="align-items-center text-center"><?php echo $row['early_out'] ?></td>
                        <td class="align-items-center text-center"><?php echo $row['early_in'] ?></td>
                        <td class="align-items-center text-center"><?php echo $row['late_out'] ?></td>
                        <td class="align-items-center text-center"><?php echo $row['marked_as'] ?></td>
                        <td class="align-items-center text-center">
                          <?php
                            if (!empty($row['methods']) && (!empty($row['in_time']))) {
                                $methods = explode(',', $row['methods']); // Convert string to an array
                                $output = [];

                                foreach ($methods as $method) {
                                    $output[] = ($method == '1') ? 'M' : 'A';
                                }

                                echo implode(' | ', $output); // Convert the array back to a string
                            }
                          ?>

                        </td>
                        <td class="align-items-center text-center"><?php echo $row['remarks'] ?></td>
                        <?php 
                      }
                    } else {
                        echo "<td class='align-items-center text-center' colspan='18'>There is no employees for Selected Branch.</td></tr>";
                    }
                } else {
                    echo "<tr>
                      <td class='align-items-center text-center' colspan='18'>No data fetched. Submit above to view report.</td>";
                }
                    ?>
                      </tr>
              <?php
                if (isset($jsonData)){
                  $totalEmployees = count($jsonData);
                }
                $presentCount = 0;
                $absentCount = 0;
                $leaveCount = 0;

                if (isset($jsonData) && is_array($jsonData)) {
                  foreach ($jsonData as $row) {
                      if ($row['marked_as'] == 'Present') {
                          $presentCount++;
                      } elseif ($row['marked_as'] == 'Absent') {
                          $absentCount++;
                      } elseif ($row['marked_as'] == 'Leave') {
                          $leaveCount++;
                      }
                  }
                }
              ?>
                <tfoot>
                  <th class="align-items-center text-right" colspan="2">Daily Summary: </th>
                  <th class="align-items-center text-center" colspan="2">Total Employees: <?php if (isset($jsonData)){ echo $totalEmployees;}else{echo 0;}?></th>
                  <th class="align-items-center text-center" colspan="5">Total Present: <?php echo $presentCount;?></th>
                  <th class="align-items-center text-center" colspan="4">Total Absent: <?php echo $absentCount;?></th>
                  <th class="align-items-center text-center" colspan="5">Total on Leave: 0</th>
                </tfoot>
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

<!-- Page Specific Scripts -->
<script>
  $(function () {
    var table = $("#daily-report-table").DataTable({
        "responsive": true,
        "lengthChange": false,
        "autoWidth": true,
        "paging": false,
        "searching": true,
        "ordering": false,
        "info": false,
        "buttons": [
            'colvis', // Add column visibility button
            {
                extend: 'print',
                text: 'Print',
                exportOptions: {
                    modifier: {
                        page: 'all',  // Ensure it applies to all pages
                    },
                    header: true  // Include the headers in the print
                },
                autoPrint: true,
                title: 'HRMS | Reports', // Custom title for print dialog
                title: 'Daily Attendance Report of <?php echo  $_POST['reportdate'] ?>',
                messageTop: '', // Subtitle text for the printed page
                customize: function (win) {
                    $(win.document.body)
                        .css('font-size', '10pt') // Adjust font size for print
                        .prepend('<img src="<?php echo $home;?>resources/logo.png" style="position:absolute; top:0; right:5px; width:200px;" />');
                    $(win.document.body).find('table')
                        .addClass('compact') // Adjust table layout
                        .css('font-size', 'inherit');
                    
                    // Customize the print page size
                    var css = '@page { size: A4 landscape; }'; 
                    var head = win.document.head || win.document.getElementsByTagName('head')[0];
                    var style = win.document.createElement('style');
                    style.type = 'text/css';
                    style.media = 'print';
                    if (style.styleSheet) {
                        style.styleSheet.cssText = css;
                    } else {
                        style.appendChild(win.document.createTextNode(css));
                    }
                    head.appendChild(style);
                }
            }
        ], // Buttons: copy, csv, excel, pdf, print, colvis
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
    });

    // Enable buttons and append them to the container
    table.buttons().container().appendTo('#daily-report-table_wrapper .col-md-6:eq(0)');
});

// Prevent the data being autoload on page refresh
if (window.history.replaceState) {
    window.history.replaceState(null, null, window.location.href);
}

//auto submit the filers
document.addEventListener("DOMContentLoaded", function() {
    const form = document.getElementById("daily-report-form");
    const inputs = form.querySelectorAll("input, select");

    inputs.forEach(input => {
        input.addEventListener("change", function() {
            form.submit(); // Auto-submit when input changes
        });
    });
});

</script>
<!-- AdminLTE for demo purposes -->
<script src="<?php echo $home;?>dist/js/demo.js"></script>

</body>
</html>