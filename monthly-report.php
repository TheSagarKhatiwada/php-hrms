<?php
$page = 'monthly-report';
// Include utilities for role check functions
require_once 'includes/utilities.php';

require_once __DIR__ . '/includes/header.php'; // Assumes header.php includes Bootstrap 5 CSS
include 'includes/db_connection.php';
?>

<!-- DataTables Bootstrap 5 CSS -->
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
<link rel="stylesheet" href="https://cdn.datatables.net/responsive/2.5.0/css/responsive.bootstrap5.min.css">
<link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.1/css/buttons.bootstrap5.min.css">
<!-- DateRangePicker CSS -->
<link rel="stylesheet" type="text/css" href="https://cdn.jsdelivr.net/npm/daterangepicker/daterangepicker.css" />

<!-- Removed AdminLTE/BS4 specific CSS -->

</head>
<body> <!-- Removed AdminLTE classes -->

<!-- App container likely starts in header.php -->
<!-- Main Content Area -->
<div class="container-fluid mt-4">
    <!-- Page Title and Breadcrumbs -->
    <div class="row mb-3">
        <div class="col-md-6">
            <h1 class="page-title">Monthly Report</h1>
        </div>
        <div class="col-md-6">
        </div>
    </div>

    <!-- Filter Form -->
    <div class="card mb-4">
        <div class="card-header">
            Report Filters
        </div>
        <div class="card-body">
            <form action="monthly-report.php" method="POST" id="monthly-report-form">
                <input type="hidden" name="jsonData" value='<?php echo isset($_POST["jsonData"]) ? htmlspecialchars($_POST["jsonData"]) : ""; ?>'>
                <div class="row g-3 align-items-end">
                    <div class="col-md-4">
                        <label for="reportDateRange" class="form-label">Date Range <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <input type="text" class="form-control" id="reportDateRange" name="reportDateRange" required value="<?php echo isset($_POST['reportDateRange']) ? htmlspecialchars($_POST['reportDateRange']) : ''; ?>">
                            <span class="input-group-text"><i class="fas fa-calendar"></i></span>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <label for="empBranch" class="form-label">Branch <span class="text-danger">*</span></label>
                        <select class="form-select" id="empBranch" name="empBranch">
                            <option value="">All Branches</option>
                            <?php 
                                $selectedBranch = isset($_POST['empBranch']) ? $_POST['empBranch'] : '';
                                $branchQuery = "SELECT id, name FROM branches ORDER BY name";
                                $stmt = $pdo->query($branchQuery);
                                while ($row = $stmt->fetch()) {
                                    $selected = ($row['id'] == $selectedBranch) ? 'selected' : '';
                                    echo "<option value='{$row['id']}' {$selected}>{$row['name']}</option>";
                                }
                            ?>
                        </select>
                    </div>
                    <div class="col-md-auto">
                        <button type="submit" class="btn btn-primary">Search</button>
                        <button type="submit" formaction="export-to-pdf.php" formtarget="_blank" class="btn btn-secondary" id="exportPdfBtn">Export PDF</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Report Display Area -->
    <div class="card">
        <div class="card-header">
            Report Results
        </div>
        <div class="card-body">
            <?php 
            if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reportDateRange'])) {
                // Include the logic to fetch data based on POST
                // This assumes fetch-monthly-report-data.php returns JSON
                ob_start();
                $_POST['isAjax'] = false; // Indicate it's not an AJAX request for the fetch script
                include 'fetch-monthly-report-data.php';
                $jsonData = ob_get_clean();
                $reportData = json_decode($jsonData, true);

                if ($reportData && !empty($reportData['data'])) {
                    // Group data by employee
                    $groupedData = [];
                    foreach ($reportData['data'] as $row) {
                        $groupedData[$row['emp_id']][] = $row;
                    }

                    // Generate cards for each employee
                    foreach ($groupedData as $empId => $employeeData) {
                        // Initialize summary counts
                        $present = $absent = $weekend = $holiday = $paidLeave = $unpaidLeave = $missed = $manual = $misc = 0;
                        $totalOvertimeSeconds = 0;
                        $totalWorkedSeconds = 0;
                        $totalScheduledSeconds = 0;

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
                            // Sum durations (assuming HH:MM:SS format)
                            if (!empty($row['over_time'])) $totalOvertimeSeconds += timeToSeconds($row['over_time']);
                            if (!empty($row['worked_duration'])) $totalWorkedSeconds += timeToSeconds($row['worked_duration']);
                            if (!empty($row['working_hour'])) $totalScheduledSeconds += timeToSeconds($row['working_hour']);
                        }
                        
                        // Helper function to convert HH:MM:SS to seconds
                        function timeToSeconds($time) {
                            if (empty($time) || !str_contains($time, ':')) return 0;
                            list($h, $m, $s) = explode(':', $time);
                            return ($h * 3600) + ($m * 60) + $s;
                        }

                        // Helper function to convert seconds to HH:MM:SS
                        function secondsToTime($seconds) {
                            if ($seconds <= 0) return '00:00:00';
                            $h = floor($seconds / 3600);
                            $m = floor(($seconds % 3600) / 60);
                            $s = $seconds % 60;
                            return sprintf('%02d:%02d:%02d', $h, $m, $s);
                        }
                        ?>
                        
                        <div class="card mb-4 shadow-sm">
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-sm table-bordered table-striped monthly-report-table display nowrap" style="width:100%">
                                        <thead class="table-light">
                                            <tr>
                                                <th class="text-center fs-5" colspan="16">Prime Express Courier & Cargo Pvt Ltd</th>
                                            </tr>
                                            <tr>
                                                <th class="text-center fs-6" colspan="16">Monthly Attendance Report</th>
                                            </tr>
                                            <tr>
                                                <th class="text-center small" colspan="2">Emp. ID: <?php echo htmlspecialchars($empId); ?></th>
                                                <th class="text-start small" colspan="3">Name: <?php echo htmlspecialchars($employeeData[0]['employee_name']); ?></th>
                                                <th class="text-start small" colspan="4">Designation: <?php echo htmlspecialchars($employeeData[0]['designation']); ?></th>
                                                <th class="text-start small" colspan="4">Report Period: <?php echo htmlspecialchars($reportData['date_range'] ?? ''); ?></th>
                                                <th class="text-start small" colspan="3">Branch: <?php echo htmlspecialchars($employeeData[0]['branch']); ?></th>
                                            </tr>
                                            <tr>
                                                <th class="text-center small align-middle" rowspan="2">SN</th>
                                                <th class="text-center small align-middle" rowspan="2">Date</th>
                                                <th class="text-center small" colspan="3">Planned Time</th>
                                                <th class="text-center small" colspan="3">Worked Time</th>
                                                <th class="text-center small align-middle" rowspan="2">Overtime</th>
                                                <th class="text-center small align-middle" rowspan="2">Late In</th>
                                                <th class="text-center small align-middle" rowspan="2">Early Out</th>
                                                <th class="text-center small align-middle" rowspan="2">Early In</th>
                                                <th class="text-center small align-middle" rowspan="2">Late Out</th>
                                                <th class="text-center small align-middle" rowspan="2">Marked As</th>
                                                <th class="text-center small align-middle" rowspan="2">Methods</th>
                                                <th class="text-center small align-middle" rowspan="2">Remarks</th>
                                            </tr>
                                            <tr>
                                                <th class="text-center small">In</th>
                                                <th class="text-center small">Out</th>
                                                <th class="text-center small">Work Hrs</th>
                                                <th class="text-center small">In</th>
                                                <th class="text-center small">Out</th>
                                                <th class="text-center small">Actual</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($employeeData as $index => $row) { ?>
                                                <tr>
                                                    <td class="text-center small"><?php echo $index + 1; ?></td>
                                                    <td class="text-start small"><?php echo date("d-M-Y, D", strtotime($row['date'])); ?></td>
                                                    <td class="text-center small"><?php echo htmlspecialchars($row['scheduled_in']); ?></td>
                                                    <td class="text-center small"><?php echo htmlspecialchars($row['scheduled_out']); ?></td>
                                                    <td class="text-center small"><?php echo htmlspecialchars($row['working_hour']); ?></td>
                                                    <td class="text-center small"><?php echo htmlspecialchars($row['in_time'] ?: '--'); ?></td>
                                                    <td class="text-center small"><?php echo htmlspecialchars($row['out_time'] ?: '--'); ?></td>
                                                    <td class="text-center small"><?php echo htmlspecialchars($row['worked_duration'] ?: '--'); ?></td>
                                                    <td class="text-center small"><?php echo htmlspecialchars($row['over_time'] ?: '--'); ?></td>
                                                    <td class="text-center small"><?php echo htmlspecialchars($row['late_in'] ?: '--'); ?></td>
                                                    <td class="text-center small"><?php echo htmlspecialchars($row['early_out'] ?: '--'); ?></td>
                                                    <td class="text-center small"><?php echo htmlspecialchars($row['early_in'] ?: '--'); ?></td>
                                                    <td class="text-center small"><?php echo htmlspecialchars($row['late_out'] ?: '--'); ?></td>
                                                    <td class="text-center small"><?php echo htmlspecialchars($row['marked_as']); ?></td>
                                                    <td class="text-center small">
                                                    <?php
                                                      if (!empty($row['methods'])) {
                                                          $methods = explode(',', $row['methods']);
                                                          $output = [];
                                                          foreach ($methods as $method) {
                                                              $output[] = ($method == '1') ? 'M' : 'A'; // Manual vs Auto
                                                          }
                                                          echo implode(' | ', $output);
                                                      }
                                                    ?>
                                                    </td>
                                                    <td class="text-start small"><?php echo htmlspecialchars($row['remarks'] ?: ''); ?></td>
                                                </tr>
                                            <?php } ?>
                                        </tbody>
                                        <tfoot class="table-light">
                                            <tr>
                                               <th class="text-end small" colspan="2">Summary:</th>
                                               <th class="text-center small" colspan="2">Scheduled: <?php echo secondsToTime($totalScheduledSeconds); ?></th>
                                               <th class="text-center small" colspan="2">Worked: <?php echo secondsToTime($totalWorkedSeconds); ?></th>
                                               <th class="text-center small" colspan="2">Overtime: <?php echo secondsToTime($totalOvertimeSeconds); ?></th>
                                               <th class="text-center small">Present: <?php echo $present; ?></th>
                                               <th class="text-center small">Absent: <?php echo $absent; ?></th>
                                               <th class="text-center small">Weekend: <?php echo $weekend; ?></th>
                                               <th class="text-center small">Holiday: <?php echo $holiday; ?></th>
                                               <th class="text-center small">P.Leave: <?php echo $paidLeave; ?></th>
                                               <th class="text-center small">U.Leave: <?php echo $unpaidLeave; ?></th>
                                               <th class="text-center small">Missed: <?php echo $missed; ?></th>
                                               <th class="text-center small">Manual: <?php echo $manual; ?></th>
                                            </tr>
                                        </tfoot>
                                    </table>
                                </div>
                                <!-- Button container for DataTables -->
                                <div class="dt-buttons btn-group mt-2"></div> 
                            </div>
                        </div>
                    <?php 
                    }
                } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
                    echo '<div class="alert alert-warning">No data found for the selected criteria.</div>';
                } else {
                    echo '<div class="alert alert-info">Please select a date range and branch, then click Search to generate the report.</div>';
                }
            } else {
                 echo '<div class="alert alert-info">Please select a date range and branch, then click Search to generate the report.</div>';
            }
            ?>
        </div> <!-- /.card-body -->
    </div> <!-- /.card -->
</div> <!-- /.container-fluid -->

<!-- Include Footer (Assumes footer.php includes Bootstrap 5 JS, jQuery, Popper) -->
<?php require_once __DIR__ . '/includes/footer.php'; ?>

<!-- DataTables & Plugins JS -->
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdn.datatables.net/responsive/2.5.0/js/dataTables.responsive.min.js"></script>
<script src="https://cdn.datatables.net/responsive/2.5.0/js/responsive.bootstrap5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.1/js/dataTables.buttons.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.bootstrap5.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/pdfmake.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/vfs_fonts.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.html5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.print.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.colVis.min.js"></script>

<!-- Moment.js (Required for DateRangePicker) -->
<script type="text/javascript" src="https://cdn.jsdelivr.net/momentjs/latest/moment.min.js"></script>
<!-- DateRangePicker JS -->
<script type="text/javascript" src="https://cdn.jsdelivr.net/npm/daterangepicker/daterangepicker.min.js"></script>

<!-- Page Specific Scripts -->
<script>
$(function () {
    // Initialize DataTables for each report table
    $(".monthly-report-table").each(function () {
        var table = $(this).DataTable({
            "responsive": true,
            "lengthChange": false,
            "autoWidth": false,
            "paging": false,
            "searching": false, // Disable native search, use filters
            "ordering": false,
            "info": false,
            "buttons": [
                'colvis', // Column visibility
                {
                    extend: 'copyHtml5',
                    text: 'Copy',
                    exportOptions: {
                        columns: ':visible' // Only copy visible columns
                    }
                },
                {
                    extend: 'excelHtml5',
                    text: 'Excel',
                    title: 'Monthly Attendance Report', // File name
                    exportOptions: {
                        columns: ':visible'
                    }
                },
                {
                    extend: 'pdfHtml5',
                    text: 'PDF',
                    title: 'Monthly Attendance Report',
                    orientation: 'landscape', // Landscape orientation for wide tables
                    pageSize: 'A4',
                    exportOptions: {
                        columns: ':visible'
                    },
                    customize: function (doc) {
                        // Adjust font size and margins
                        doc.defaultStyle.fontSize = 8;
                        doc.styles.tableHeader.fontSize = 9;
                        doc.pageMargins = [ 20, 30, 20, 30 ]; // [left, top, right, bottom]
                    }
                },
                {
                    extend: 'print',
                    text: 'Print',
                    title: 'Monthly Attendance Report',
                    exportOptions: {
                        columns: ':visible'
                    },
                    customize: function (win) {
                        $(win.document.body)
                            .css('font-size', '10pt');
 
                        $(win.document.body).find('table')
                            .addClass('compact')
                            .css('font-size', 'inherit');
                        
                        // Add landscape printing for browser print dialog
                        var css = '@page { size: landscape; }';
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
                // Standard Bootstrap 5 pagination icons if paging were enabled
                "paginate": {
                    "previous": "&laquo;",
                    "next": "&raquo;"
                },
                "emptyTable": "No data available for this employee in the selected period."
                // Add other language options if needed
            }
        });

        // Move buttons to the container div in the card body
        table.buttons().container().appendTo($(this).closest('.card-body').find('.dt-buttons'));
    });

    // Initialize DateRangePicker
    const dateRangeInput = $('#reportDateRange');
    const initialDateRange = dateRangeInput.val(); // Get value set by PHP if POST occurred
    let startDate = moment().startOf('month');
    let endDate = moment().endOf('month');

    // If there's an initial value from POST, parse it
    if (initialDateRange) {
        const dates = initialDateRange.split(' - ');
        if (dates.length === 2) {
            startDate = moment(dates[0], 'DD/MM/YYYY');
            endDate = moment(dates[1], 'DD/MM/YYYY');
        }
    }

    dateRangeInput.daterangepicker({
        locale: {
          format: 'DD/MM/YYYY' // Display format
        },
        opens: 'right',
        startDate: startDate,
        endDate: endDate,
        maxDate: moment(), // Prevent selecting future dates
        autoApply: false, // Show Apply/Cancel buttons
        ranges: {
           'Today': [moment(), moment()],
           'Yesterday': [moment().subtract(1, 'days'), moment().subtract(1, 'days')],
           'Last 7 Days': [moment().subtract(6, 'days'), moment()],
           'Last 30 Days': [moment().subtract(29, 'days'), moment()],
           'This Month': [moment().startOf('month'), moment().endOf('month')],
           'Last Month': [moment().subtract(1, 'month').startOf('month'), moment().subtract(1, 'month').endOf('month')]
        }
    });

    // Clear the input if no date is selected initially (on first load)
    if (!initialDateRange) {
        dateRangeInput.val('');
    }
    
    dateRangeInput.on('cancel.daterangepicker', function(ev, picker) {
      // Clear the input when cancelled
      $(this).val('');
    });

});
</script>

<!-- Removed AdminLTE demo.js -->

</body>
</html>
