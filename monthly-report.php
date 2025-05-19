<?php
$page = 'monthly-report';
// Include utilities for role check functions
require_once 'includes/session_config.php';
require_once 'includes/utilities.php';

// Check if user has permission to access daily reports
if (!has_permission('view_daily_report') && !is_admin()) {
    $_SESSION['error'] = "You don't have permission to access Daily Reports.";
    header('Location: index.php');
    exit();
}

// Check if form is submitting (adding a new report)
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] == 'add') {
    // Check if user has permission to add daily reports
    if (!has_permission('add_daily_report') && !is_admin()) {
        $_SESSION['error'] = "You don't have permission to add new Daily Reports.";
        header('Location: daily-report.php');
        exit();
    }
}

// Include the header (handles head, body, topbar, sidebar, opens wrappers)
require_once __DIR__ . '/includes/header.php';
include 'includes/db_connection.php'; // DB connection needed after header potentially?
?>

<!-- DataTables & CSS -->
<link rel="stylesheet" href="<?php echo $home; ?>plugins/datatables-bs4/css/dataTables.bootstrap4.min.css">
<link rel="stylesheet" href="<?php echo $home; ?>plugins/datatables-responsive/css/responsive.bootstrap4.min.css">
<link rel="stylesheet" href="<?php echo $home; ?>plugins/datatables-buttons/css/buttons.bootstrap4.min.css">
<link rel="stylesheet" href="<?php echo $home; ?>plugins/tempusdominus-bootstrap-4/css/tempusdominus-bootstrap-4.min.css">
<link rel="stylesheet" href="<?php echo $home; ?>plugins/daterangepicker/daterangepicker.css">

<!-- Print-specific styles -->
<style>
    /* Print-specific styles - optimized for full width and proper positioning */
    @media print {
        /* Reset all margins and paddings */
        * {
            margin: 0 !important;
            padding: 0 !important;
        }
        
        /* Hide everything by default */
        body * {
            visibility: hidden;
        }
        
        /* Show only the tables and logo */
        .monthly-report-table, 
        .monthly-report-table *,
        .print-logo,
        .print-logo * {
            visibility: visible !important;
        }
        
        /* Make the logo div visible and position it */
        .print-logo {
            display: block !important;
            position: relative !important;
            top: 0 !important;
            right: 0 !important;
            width: auto !important;
            z-index: 9999 !important;
            margin-bottom: 0 !important;
        }
        
        /* Position the table properly for printing all cards */
        .monthly-report-table {
            margin: 0 !important;
            border-collapse: collapse !important;
            font-size: 9pt !important;
            display: table !important;
            width: 100% !important;
            box-sizing: border-box !important;
        }
        
        /* Force each employee card to start on a new page */
        .card {
            visibility: visible !important;
            margin: 0 !important;
            padding: 0 !important;
            box-shadow: none !important;
            border: none !important;
            page-break-after: always !important;
        }
        
        /* Last card should not have page break after */
        .card:last-child {
            page-break-after: avoid !important;
        }
        
        /* Last table should not have page break after */
        .card:last-child .monthly-report-table {
            page-break-after: avoid !important;
        }
        .monthly-report-table thead { display: table-header-group !important; }
        .monthly-report-table tbody { display: table-row-group !important; }
        .monthly-report-table tfoot { display: table-footer-group !important; }
        .monthly-report-table tr { display: table-row !important; }
        .monthly-report-table th, 
        .monthly-report-table td {
            display: table-cell !important;
            padding: 1px !important;
            border: 0.5px solid #000 !important;
            font-size: 9pt !important;
            white-space: nowrap !important;
            color: #000 !important;
        }
        
        /* Make sure the table rows are as compact as possible */
        .monthly-report-table tr {
            height: auto !important;
            line-height: 1 !important;
        }
        
        /* Show the card container too */
        .card-body {
            visibility: visible !important;
            padding: 0 !important;
            margin: 0 !important;
        }
        
        /* Hide all buttons */
        button, 
        .buttons-collection,
        .dt-buttons,
        .btn-group {
            display: none !important;
        }
        
        /* Set landscape orientation with minimal margins */
        @page {
            size: landscape !important;
            margin: 0.3cm !important;
        }
    }
</style>
</head>
<!-- Body tag is opened in header.php -->
<!-- Wrapper div is removed -->
<!-- Topbar include is removed (handled by header.php) -->
<!-- Sidebar include is removed (handled by header.php) -->
<!-- Content Wrapper div is opened in header.php -->
<!-- Content wrapper -->

<!-- Content Header (Page header) -->
        <div class="content-header">
            <div class="container-fluid">
                <div class="row mb-2">
                    <div class="col-sm-6">
                        <h1 class="m-0">Periodic Report</h1>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main content -->
        <section class="content">
            <div class="container-fluid">
                <div class="card">
                    <div class="card-header" style="padding: 10px;">
                        <form action="fetch-monthly-report-data.php" method="POST" id="monthly-report-form" class="mt-3">
                            <input type="hidden" id="hiddenReportDateRange" value="<?php echo isset($_POST['reportDateRange']) ? $_POST['reportDateRange'] : ''; ?>">
                            <div class="row">
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label for="reportDateRange">Date Range <span class="text-danger">*</span></label>
                                        <div class="input-field" style="border:1px solid #ddd; width: 100%; border-radius: 5px; padding: 2px; display: flex; align-items: center;">
                                          <i class="fas fa-calendar-alt mr-2" style="font-size: 1.5rem;"></i>
                                          <input type="text" class="form-control border-0" id="reportDateRange" name="reportDateRange" required>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label for="empBranch">Branch <span class="text-danger">*</span></label>
                                        <div class="input-field" style="border:1px solid #ddd; width:100%; border-radius: 5px; padding: 2px; display: flex; align-items: center;">
                                          <i class="fas fa-building mr-2" style="font-size: 1.5rem;"></i>
                                          <select class="form-control border-0" id="empBranch" name="empBranch" required>
                                            <option disabled>Select a Branch</option>
                                            <option value="">All Branches</option>
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
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-3 d-flex align-items-end text-center">
                                    <div class="form-group mb-0">
                                        <button type="submit" class="btn btn-primary btn-md px-4">
                                          <i class="fas fa-filter mr-1"></i> Generate Report
                                        </button>
                                    </div>
                                </div>
                                <div class="col-md-3 d-flex align-items-end text-left">
                                    <div class="form-group mb-0">
                                        <?php if (isset($_POST['jsonData'])): ?>
                                        <button type="button" id="print-report-btn" class="btn btn-success btn-md px-4">
                                          <i class="fas fa-print mr-1"></i> Print
                                        </button>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </form>
                    </div>

                    <div class="card-body"> 
                        <!-- Hidden logo for printing -->
                        <!-- <div class="d-none print-logo">
                            <img src="<?php echo $home;?>resources/logo.png" alt="Company Logo" style="height: 80px; float: right; margin-bottom: 10px;">
                        </div>                        -->
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
                                    
                                    <div class="card employee-card mb-4">
                                        <div class="card-body">
                                            <!-- Hidden button container for DataTables buttons -->
                                            <div class="btn-group" style="display: none;"></div>
                                            <table class="table table-sm table-bordered table-striped monthly-report-table">
                                                <thead>
                                                    <tr>
                                                        <th class="text-center" colspan="16">Periodic Attendance Report: <?php echo $employeeData[0]['date_range'] ?? ''; ?></th>
                                                    </tr>
                                                    <tr>
                                                        <th class="text-center" colspan="2">Emp. ID: <?php echo $empId; ?></th>
                                                        <th class="text-left" colspan="3">Name: <?php echo $employeeData[0]['employee_name']; ?></th>
                                                        <th class="text-center" colspan="4">Designation: <?php echo $employeeData[0]['designation']; ?></th>
                                                        <th class="text-center" colspan="4">Branch: <?php echo $employeeData[0]['branch']; ?></th>
                                                        <th class="text-center" colspan="3">Department: <?php echo $employeeData[0]['department'] ?? ''; ?></th>
                                                    </tr>
                                                    <tr>
                                                      <th class="align-items-center text-center" rowspan="2">SN</th>
                                                      <th class="align-items-center text-center" rowspan="2">Date</th>
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
                                                                    // Parse the methods from format "In: X, Out: Y"
                                                                    preg_match('/In: (.*?)(?:, Out: (.*))?$/', $row['methods'], $matches);
                                                                    
                                                                    $inMethod = isset($matches[1]) ? trim($matches[1]) : '';
                                                                    $outMethod = isset($matches[2]) ? trim($matches[2]) : '';
                                                                    
                                                                    $methodLabels = [
                                                                        '0' => 'A', // Automatic
                                                                        '1' => 'M', // Manual
                                                                        '2' => 'W'  // Web
                                                                    ];
                                                                    
                                                                    $inMethodLabel = isset($methodLabels[$inMethod]) ? $methodLabels[$inMethod] : $inMethod;
                                                                    $outMethodLabel = isset($methodLabels[$outMethod]) ? $methodLabels[$outMethod] : $outMethod;
                                                                    
                                                                    if ($outMethod) {
                                                                        echo "{$inMethodLabel} | {$outMethodLabel}";
                                                                    } else {
                                                                        echo "{$inMethodLabel}";
                                                                    }
                                                                }
                                                            ?>
                                                            </td>
                                                            <td class="text-center"><?php echo $row['remarks'] ?: ''; ?></td>
                                                        </tr>
                                                    <?php } ?>
                                                </tbody>
                                                <tfoot>
                                                    <tr>
                                                       <th class="text-right" colspan="1">Summary:</th>
                                                       <th class="text-center" colspan="2">Present: <?php echo $present; ?></th>
                                                       <th class="text-center" colspan="2">Absent: <?php echo $absent; ?></th>
                                                       <th class="text-center" colspan="2">Weekend: <?php echo $weekend; ?></th>
                                                       <th class="text-center" colspan="2">Holiday: <?php echo $holiday; ?></th>
                                                       <th class="text-center" colspan="2">Paid Leave: <?php echo $paidLeave; ?></th>
                                                       <th class="text-center" colspan="2">Unpaid Leave: <?php echo $unpaidLeave; ?></th>
                                                       <th class="text-center" colspan="1">Missed: <?php echo $missed; ?></th>
                                                       <th class="text-center" colspan="2">Manual: <?php echo $manual; ?></th>
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
                    exportOptions: {
                        modifier: {
                            page: 'all',  // Ensure it applies to all pages
                        },
                        header: true  // Include the headers in the print
                    },
                    autoPrint: false, // Don't auto print - let the user click the button
                    title: 'Periodic Attendance Report',
                    messageTop: '',
                    messageBottom: '',
                    customize: function (win) {
                        // Remove any default margins
                        $(win.document.body).css({
                            'font-size': '10pt',
                            'margin': 0,
                            'padding': 0
                        });
                        
                        $(win.document.body).find('table')
                            .addClass('compact')
                            .css({
                                'font-size': 'inherit',
                                'margin': 0,
                                'padding': 0,
                                'border-spacing': 0
                            });

                        // Add page break after each employee card for better printing
                        $(win.document.body).find('.employee-card').css({
                            'page-break-after': 'always',
                            'margin': 0,
                            'padding': 0
                        });
                        
                        $(win.document.body).find('.employee-card:last-child').css('page-break-after', 'avoid');

                        // Set paper size and orientation with minimal margins
                        var css = '@page { size: A4 landscape; margin: 0.3cm; }';
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
            ], 
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
        }).buttons().container().appendTo($(this).closest('.card-body').find('.btn-group:first-child')); 
    });
    
    // Print button functionality
    $('#print-report-btn').on('click', function() {
        window.print();
    });
});

$('#reportDateRange').daterangepicker({
    locale: {
      format: 'DD/MM/YYYY' // Date format for start and end dates
    },
    opens: 'auto',
    alwaysShowCalendars: false,
    startDate: moment().subtract(1, 'months').startOf('month'),
    endDate: moment().subtract(1, 'months').endOf('month'),
    maxDate: moment(),
    autoApply: false,
    ranges: {
      'This Month': [moment().startOf('month'), moment().endOf('month')],
      'Last Month': [moment().subtract(1, 'months').startOf('month'), moment().subtract(1, 'months').endOf('month')],
      'Last 30 Days': [moment().subtract(29, 'days'), moment()]
    }
});

// Ensure the reportDateRange input is populated correctly
$(document).ready(function() {
    $('#reportDateRange').on('change', function() {
        const selectedDateRange = $(this).val();
        $('#hiddenReportDateRange').val(selectedDateRange);
    });

    // Set the initial value of hiddenReportDateRange
    const initialDateRange = $('#reportDateRange').val();
    $('#hiddenReportDateRange').val(initialDateRange);
});
</script>
<!-- AdminLTE for demo purposes -->
<script src="<?php echo $home;?>dist/js/demo.js"></script>

</body>
</html>