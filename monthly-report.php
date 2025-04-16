<?php
$page = 'monthly-report';
$accessRole = isset($_SESSION['role']) ? $_SESSION['role'] : null;
if ($accessRole === '0') {
    header('Location: dashboard.php');
    exit();
}
include 'includes/header.php';
include 'includes/db_connection.php';
?>

<!-- DataTables & CSS -->
<link rel="stylesheet" href="<?php echo $home; ?>plugins/datatables-bs4/css/dataTables.bootstrap4.min.css">
<link rel="stylesheet" href="<?php echo $home; ?>plugins/datatables-responsive/css/responsive.bootstrap4.min.css">
<link rel="stylesheet" href="<?php echo $home; ?>plugins/datatables-buttons/css/buttons.bootstrap4.min.css">
<link rel="stylesheet" href="<?php echo $home; ?>plugins/tempusdominus-bootstrap-4/css/tempusdominus-bootstrap-4.min.css">
<link rel="stylesheet" href="<?php echo $home; ?>plugins/daterangepicker/daterangepicker.css">

</head>
<body class="hold-transition sidebar-mini layout-footer-fixed layout-navbar-fixed layout-fixed dark-mode sidebar-collapse">

<!-- Content wrapper -->
<div class="wrapper">
    <?php include 'includes/topbar.php'; include 'includes/sidebar.php'; ?>

    <!-- Content Wrapper -->
    <div class="content-wrapper">
        <div class="content-header">
            <div class="container-fluid">
                <div class="row mb-2">
                    <div class="col-sm-6">
                        <h1 class="m-0">Monthly Report</h1>
                    </div>
                    <div class="col-sm-6">
                        <ol class="breadcrumb float-sm-right">
                            <li class="breadcrumb-item"><a href="#">Home</a></li>
                            <li class="breadcrumb-item active">Monthly Report</li>
                        </ol>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main content -->
        <section class="content">
            <div class="container-fluid">
                <div class="card">
                    <div class="card-header pb-3">
                        <form action="fetch-monthly-report-data.php" method="POST" id="monthly-report-form">
                            <input type="hidden" id="hiddenReportDateRange" value="<?php echo isset($_POST['reportDateRange']) ? $_POST['reportDateRange'] : ''; ?>">
                            <div class="row">
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <div class="input-group" id="reportDateID">
                                            <input type="text" class="form-control float-right" id="reportDateRange" name="reportDateRange" required>
                                            <label for="reportDateRange" class="form-label">Select Date Range <span style="color:red;">*</span></label>
                                              <div class="input-group-append">
                                                <span class="input-group-text"><i class="fa fa-calendar" for="reportDateRange"></i></span>
                                              </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <select class="form-control select2" style="width: 100%;" id="empBranch" name="empBranch" required>
                                            <option disabled>Select a Branch</option>
                                            <option value="">All Branches</option>
                                            <?php 
                                                $branchQuery = "SELECT id, name FROM branches";
                                                $stmt = $pdo->query($branchQuery);
                                                while ($row = $stmt->fetch()) {
                                                    echo "<option value='{$row['id']}'>{$row['name']}</option>";
                                                }
                                            ?>
                                        </select>
                                        <label for="empBranch" class="form-label">Branch <span style="color:red;">*</span></label>
                                    </div>
                                </div>
                                <div class="col-md-2 text-left">
                                    <div class="form-group">
                                        <input type="submit" class="btn btn-primary" value="Search">
                                    </div>
                                </div>
                                <div class="col-md-4 text-right">
                                  <div class="form-group">
                                      <button type="button" class="btn btn-secondary <?php if (isset($_POST['jsonData'])) {echo '';}else{echo 'd-none';} ?>" id="exportPdfBtn">Export PDF</button>
                                  </div>
                                </div>
                            </div>
                        </form>
                    </div>

                    <div class="card-body">
                        <?php 
                        if (isset($_POST['jsonData'])) {
                            $jsonData = json_decode($_POST['jsonData'], true);

                            if ($jsonData) {
                                // Group data by employee
                                $groupedData = [];
                                foreach ($jsonData as $row) {
                                    $groupedData[$row['emp_id']][] = $row;
                                }

                                // Generate cards for each employee
                                foreach ($groupedData as $empId => $employeeData) {
                                    // Initialize summary counts
                                    $present = $absent = $weekend = $holiday = $paidLeave = $unpaidLeave = $missed = $manual = $misc = 0;

                                    foreach ($employeeData as $row) {
                                        switch ($row['marked_as']) {
                                            case 'Present': $present++; break;
                                            case 'Absent': $absent++; break;
                                            case 'Weekend': $weekend++; break;
                                            case 'Holiday': $holiday++; break;
                                            case 'Paid Leave': $paidLeave++; break;
                                            case 'Unpaid Leave': $unpaidLeave++; break;
                                            case 'Missed': $missed++; break;
                                            case 'Manual': $manual++; break;
                                            default: $misc++; break;
                                        }
                                    }
                                    ?>
                                    
                                    <div class="card mb-4">
                                        <div class="card-body">
                                          <!-- Button container -->
                                          <div id="monthly-report-buttons" class="btn-group"></div>
                                            <table class="table table-sm table-bordered table-striped monthly-report-table">
                                                <thead>
                                                    <tr>
                                                        <th class="text-center" style="font-size: 1.2rem;" colspan="16">Prime Express Courier & Cargo Pvt Ltd</th>
                                                    </tr>
                                                    <tr>
                                                        <th class="text-center" colspan="16">Monthly Attendance Report</th>
                                                    </tr>
                                                    <tr>
                                                        <th class="text-center" colspan="2">Emp. ID: <?php echo $empId; ?></th>
                                                        <th class="text-left" colspan="3">Name: <?php echo $employeeData[0]['employee_name']; ?></th>
                                                        <th class="text-center" colspan="4">Designation: <?php echo $employeeData[0]['designation']; ?></th>
                                                        <th class="text-center" colspan="4">Report Date: <?php echo $employeeData[0]['date_range'] ?? ''; ?></th>
                                                        <th class="text-center" colspan="3">Branch: <?php echo $employeeData[0]['branch']; ?></th>
                                                    </tr>
                                                    <tr>
                                                      <th class="align-items-center text-center"></th>
                                                      <th class="align-items-center text-center"></th>
                                                      <th class="align-items-center text-center" colspan="3">Planned Time</th>
                                                      <th class="align-items-center text-center" colspan="3">Worked Time</th>
                                                      <th class="align-items-center text-center"></th>
                                                      <th class="align-items-center text-center"></th>
                                                      <th class="align-items-center text-center"></th>
                                                      <th class="align-items-center text-center"></th>
                                                      <th class="align-items-center text-center"></th>
                                                      <th class="align-items-center text-center"></th>
                                                      <th class="align-items-center text-center"></th>
                                                      <th class="align-items-center text-center"></th>
                                                    </tr>
                                                    <tr>
                                                      <th class="align-items-center text-center">SN</th>
                                                      <th class="align-items-center text-center">Employee Name</th>
                                                      <th class="align-items-center text-center">In</th>
                                                      <th class="align-items-center text-center">Out</th>
                                                      <th class="align-items-center text-center">Work hrs</th>
                                                      <th class="align-items-center text-center">In</th>
                                                      <th class="align-items-center text-center">Out</th>
                                                      <th class="align-items-center text-center">Actual</th>
                                                      <th class="align-items-center text-center">Overtime</th>
                                                      <th class="align-items-center text-center">Late In</th>
                                                      <th class="align-items-center text-center">Early Out</th>
                                                      <th class="align-items-center text-center">Early In</th>
                                                      <th class="align-items-center text-center">Late Out</th>
                                                      <th class="align-items-center text-center">Marked As</th>
                                                      <th class="align-items-center text-center">Methods</th>
                                                      <th class="align-items-center text-center">Remarks</th>
                                                      </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($employeeData as $index => $row) { ?>
                                                        <tr>
                                                            <td class="text-center"><?php echo $index + 1; ?></td>
                                                            <td class="text-left"><?php echo date("Y-m-d, l", strtotime($row['date'])); ?></td>
                                                            <td class="text-center"><?php echo $row['scheduled_in']; ?></td>
                                                            <td class="text-center"><?php echo $row['scheduled_out']; ?></td>
                                                            <td class="text-center"><?php echo $row['working_hour']; ?></td>
                                                            <td class="text-center"><?php echo $row['in_time'] ?: ''; ?></td>
                                                            <td class="text-center"><?php echo $row['out_time'] ?: ''; ?></td>
                                                            <td class="text-center"><?php echo $row['worked_duration'] ?: ''; ?></td>
                                                            <td class="text-center"><?php echo $row['over_time'] ?: ''; ?></td>
                                                            <td class="text-center"><?php echo $row['late_in'] ?: ''; ?></td>
                                                            <td class="text-center"><?php echo $row['early_out'] ?: ''; ?></td>
                                                            <td class="text-center"><?php echo $row['early_in'] ?: ''; ?></td>
                                                            <td class="text-center"><?php echo $row['late_out'] ?: ''; ?></td>
                                                            <td class="text-center"><?php echo $row['marked_as']; ?></td>
                                                            <td class="text-center">
                                                            <?php
                                                              if (!empty($row['methods'])) {
                                                                  $methods = explode(',', $row['methods']); // Convert string to an array
                                                                  $output = [];

                                                                  foreach ($methods as $method) {
                                                                      $output[] = ($method == '1') ? 'M' : 'A';
                                                                  }

                                                                  echo implode(' | ', $output); // Convert the array back to a string
                                                              }
                                                            ?>
                                                            </td>
                                                            <td class="text-center"><?php echo $row['remarks'] ?: ''; ?></td>
                                                        </tr>
                                                    <?php } ?>
                                                </tbody>
                                                <tfoot>
                                                    <tr>
                                                       <th class="text-right"colspan="1"></th>
                                                       <th class="text-right"colspan="1">Summary</th>
                                                       <th class="text-center"colspan="2">Present: <?php echo $present; ?></th>
                                                       <th class="text-center"colspan="2">Absent: <?php echo $absent; ?></th>
                                                       <th class="text-center"colspan="2">Weekend: <?php echo $weekend; ?></th>
                                                       <th class="text-center"colspan="2">Holiday: <?php echo $holiday; ?></th>
                                                       <th class="text-center"colspan="2">Paid Leave: <?php echo $paidLeave; ?></th>
                                                       <th class="text-center"colspan="2">Unpaid Leave: <?php echo $unpaidLeave; ?></th>
                                                       <th class="text-center"colspan="1">Missed: <?php echo $missed; ?></th>
                                                       <th class="text-center"colspan="1">Manual: <?php echo $manual; ?></th>
                                                       <!-- <th class="text-center"colspan="1">Misc: <?php //echo $misc; ?></th> -->
                                                    </tr>
                                                </tfoot>
                                            </table>
                                        </div>
                                    </div>
                                <?php }
                            }
                        } ?>
                    </div>
                </div>
            </div>
        </section>
    </div>
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
<!-- Tempusdominus Bootstrap 4 -->
<script src="<?php echo $home;?>plugins/moment/moment.min.js"></script>
<script src="<?php echo $home;?>plugins/tempusdominus-bootstrap-4/js/tempusdominus-bootstrap-4.min.js"></script>
<!-- daterangepicker -->
<script src="<?php echo $home;?>plugins/moment/moment.min.js"></script>
<script src="<?php echo $home;?>plugins/daterangepicker/daterangepicker.js"></script>

<!-- Page Specific Scripts -->
<script>
$(function () {
    $(".monthly-report-table").each(function () {
        $(this).DataTable({
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
                    autoPrint: true,
                    title: 'Periodic Attendance Report',
                    messageTop: '',
                    messageBottom: 'Prime Express Courier & Cargo Pvt Ltd',
                    footer: true,
                    header: true,
                    customize: function (win) {
                        $(win.document.body)
                            .css('font-size', '10pt')
                            .prepend(
                                ''
                            );

                        $(win.document.body).find('table')
                            .addClass('compact')
                            .css('font-size', 'inherit');

                        // Set paper size and orientation
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
            ], //copy, csv, excel, pdf, print, colvis
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
        }).buttons().container().appendTo($(this).closest('.card-body').find('.btn-group')); // Append buttons to respective card-body
    });
});

$('#reportDateRange').daterangepicker({
    locale: {
      format: 'DD/MM/YYYY' // Date format for start and end dates
    },
    opens: 'auto',
    alwaysShowCalendars: false, // Initially hide the calendar
    startDate: moment().startOf('month'),
    endDate: moment().endOf('month'),
    maxDate: moment(),
    autoApply: false, // Ensure Apply button is available
    ranges: {
      'This Month': [moment().startOf('month'), moment().endOf('month')],
      'Last Month': [moment().subtract(1, 'months').startOf('month'), moment().subtract(1, 'months').endOf('month')],
      'Last 30 Days': [moment().subtract(29, 'days'), moment()]
    }
  });

</script>
<!-- AdminLTE for demo purposes -->
<script src="<?php echo $home;?>dist/js/demo.js"></script>

</body>
</html>
