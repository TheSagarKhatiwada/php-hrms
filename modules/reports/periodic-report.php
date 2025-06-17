<?php
$page = 'periodic-report'; // Updated to match the new filename
$home = '../../'; // Fixed: Should point to project root
// Include utilities for role check functions
require_once '../../includes/session_config.php';
require_once '../../includes/utilities.php';

// Check if user has permission to access daily reports
if (!has_permission('view_daily_report') && !is_admin()) {
    $_SESSION['error'] = "You don't have permission to access Daily Reports.";
    header('Location: ../../dashboard.php');
    exit();
}

// Check if form is submitting (adding a new report)
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] == 'add') {
    // Check if user has permission to add daily reports
    if (!has_permission('add_daily_report') && !is_admin()) {
        $_SESSION['error'] = "You don't have permission to add new Daily Reports.";
        header('Location: ../../dashboard.php');
        exit();
    }
}

// Include the header (handles head, body, topbar, sidebar, opens wrappers)
require_once __DIR__ . '/../../includes/header.php';
include '../../includes/db_connection.php'; // DB connection needed after header potentially?
?>

<!-- Page-specific CSS (DataTables) - Bootstrap 4 compatible -->
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap4.min.css">
<link rel="stylesheet" href="https://cdn.datatables.net/responsive/2.5.0/css/responsive.bootstrap4.min.css">
<link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.2/css/buttons.bootstrap4.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/daterangepicker/daterangepicker.css">

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
        .periodic-report-table, 
        .periodic-report-table *,
        .print-logo,
        .print-logo * {
            visibility: visible !important;
        }
        
        /* Make the logo div visible and position it */
        .print-logo {
            display: hidden !important;
            position: relative !important;
            top: 0 !important;
            right: 0 !important;
            width: auto !important;
            z-index: 9999 !important;
            margin-bottom: 0 !important;
        }
        
        /* Position the table properly for printing all cards */
        .periodic-report-table {
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
        .card:last-child .periodic-report-table {
            page-break-after: avoid !important;
        }
        .periodic-report-table thead { display: table-header-group !important; }
        .periodic-report-table tbody { display: table-row-group !important; }
        .periodic-report-table tfoot { display: table-footer-group !important; }
        .periodic-report-table tr { display: table-row !important; }
        .periodic-report-table th, 
        .periodic-report-table td {
            display: table-cell !important;
            padding: 1px !important;
            border: 0.5px solid #ddd !important;
            font-size: 9pt !important;
            white-space: nowrap !important;
            color: #000 !important;
        }
        
        /* Make sure the table rows are as compact as possible */
        .periodic-report-table tr {
            height: auto !important;
            line-height: 1.5 !important;
        }
        
        /* Show the card container too */
        .card-body {
            visibility: visible !important;
            padding: 0 !important;
            margin: 0 !important;
        }
        
        /* Hide all buttons except print content */
        button:not(.print-visible), 
        .buttons-collection,
        .dt-buttons,
        .btn-group:not(.print-visible) {
            display: none !important;
        }
        
        /* Show specific print elements */
        .print-visible {
            display: block !important;
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
                <form action="api/fetch-periodic-report-data.php" method="POST" id="periodic-report-form" class="mt-3">
                    <?php $savedDateRange = isset($_POST['reportDateRange']) ? $_POST['reportDateRange'] : ''; ?>
                    <input type="hidden" name="reportDateRange" id="hiddenReportDateRange" value="<?php echo $savedDateRange; ?>">
                    <div class="row">
                        <div class="col-md-3">
                            <div class="form-group">
                                <label for="reportDateRange">Date Range <span class="text-danger">*</span></label>
                                <div class="input-field" style="border:1px solid #ddd; width: 100%; border-radius: 5px; padding: 2px; display: flex; align-items: center;">
                                  <i class="fas fa-calendar-alt mr-2" style="font-size: 1.5rem;"></i>
                                  <input type="text" class="form-control border-0" id="reportDateRange" name="reportDateRange" 
                                         value="<?php echo $savedDateRange; ?>" required>
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
                                        $selectedBranch = isset($_POST['empBranch']) ? $_POST['empBranch'] : '';
                                        try {
                                            $branchQuery = "SELECT id, name FROM branches";
                                            $stmt = $pdo->query($branchQuery);
                                            while ($row = $stmt->fetch()) {
                                                $selected = ($selectedBranch == $row['id']) ? 'selected' : '';
                                                echo "<option value='{$row['id']}' $selected>{$row['name']}</option>";
                                            }
                                        } catch (Exception $e) {
                                            echo "<option disabled>Error loading branches</option>";
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
                                <button type="button" id="print-table-btn" class="btn btn-success btn-md px-4">
                                  <i class="fas fa-print mr-1"></i> Print
                                </button>
                                <?php else: ?>
                                <button type="button" id="print-table-btn" class="btn btn-secondary btn-md px-4" title="Generate report first to enable printing">
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
                <div class="d-none print-logo">
                    <img src="<?php echo $home;?>resources/logo.png" alt="Company Logo" style="height: 80px; float: right; margin-bottom: 10px;">
                </div>
                
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
                                // Count all present variations (Present, Present (Holiday), Present (Weekend))
                                if (strpos($row['marked_as'], 'Present') !== false) {
                                    $present++;
                                } else {
                                    switch ($row['marked_as']) {
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
                            }
                            ?>
                            
                            <div class="card employee-card mb-4">
                                <div class="card-body">
                                    <!-- Hidden button container for DataTables buttons -->
                                    <div class="btn-group" style="display: none;"></div>
                                    <table class="table table-sm table-bordered table-striped periodic-report-table">
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
                                              <th class="text-center" rowspan="2">SN</th>
                                              <th class="text-center" rowspan="2">Date</th>
                                              <th class="text-center" colspan="3">Planned Time</th>
                                              <th class="text-center" colspan="3">Worked Time</th>
                                              <th class="text-center" rowspan="2">Overtime</th>
                                              <th class="text-center" rowspan="2">Late In</th>
                                              <th class="text-center" rowspan="2">Early Out</th>
                                              <th class="text-center" rowspan="2">Early In</th>
                                              <th class="text-center" rowspan="2">Late Out</th>
                                              <th class="text-center" rowspan="2">Marked As</th>
                                              <th class="text-center" rowspan="2">Methods</th>
                                              <th class="text-center" rowspan="2">Remarks</th>
                                            </tr>
                                            <tr>
                                              <th class="text-center">In</th>
                                              <th class="text-center">Out</th>
                                              <th class="text-center">Work hrs</th>
                                              <th class="text-center">In</th>
                                              <th class="text-center">Out</th>
                                              <th class="text-center">Actual</th>
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
                                                    <td class="text-center"><?php echo $row['marked_as']; ?></td>                                    <td class="text-center"><?php echo $row['methods']; ?></td>
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
                } else {
                    echo '<div class="alert alert-info"><i class="fas fa-info-circle mr-2"></i>Please select a date range and branch to generate the periodic report.</div>';
                }
                ?>
            </div>
        </div>
    </div>
</section>
    </div>
</div>


<?php require_once '../../includes/footer.php'; ?>

<!-- Date picker libraries - Load after jQuery is available -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/moment.js/2.29.4/moment.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/daterangepicker/daterangepicker.min.js"></script>

<!-- Page Specific Scripts - Must come after footer.php loads jQuery -->
<script>
// Wait for jQuery to be available and DOM to be ready
$(document).ready(function() {
    console.log('Periodic report JavaScript initializing...');
    
    // Check if jQuery is available
    if (typeof $ === 'undefined') {
        console.error('jQuery not available!');
        return;
    }
    
    // Initialize DataTables for periodic reports
    $(".periodic-report-table").each(function () {
        $(this).DataTable({
            "responsive": true,
            "lengthChange": false,
            "autoWidth": false,
            "paging": false,
            "searching": true,
            "ordering": false,
            "info": false,
            "dom": 't',  // Only show the table, no other controls
            "columnDefs": [
                { "orderable": false, "targets": '_all' }
            ],
            "language": {
                "emptyTable": "No data available in table",
                "search": "Search:",
                "zeroRecords": "No matching records found"
            }
        });
    });
    
    console.log('DataTables initialized for periodic reports');
});

// Print button functionality
$(document).ready(function() {
    // Check if jQuery is available
    if (typeof $ === 'undefined') {
        console.error('jQuery not available for print functionality!');
        return;
    }
    
    console.log('Initializing periodic report print functionality...');
    
    // Handle print button click using event delegation
    $(document).on('click', '#print-table-btn', function(e) {
        e.preventDefault();
        console.log('Periodic report print button clicked');
        
        // Check if button is disabled (no data generated)
        if ($(this).hasClass('btn-secondary')) {
            alert('Please generate a report first before printing.');
            return;
        }
        
        window.print();
    });
    
    console.log('Print functionality initialized successfully');
});

// Initialize daterangepicker
$(document).ready(function() {
    console.log('Initializing periodic report daterangepicker...');
    
    // Check if jQuery is available
    if (typeof $ === 'undefined') {
        console.error('jQuery not available for daterangepicker!');
        return;
    }
    
    // Check if required libraries are loaded
    if (typeof moment === 'undefined') {
        console.error('Moment.js not loaded!');
        return;
    }
    
    if (typeof $.fn.daterangepicker === 'undefined') {
        console.error('Daterangepicker not loaded!');
        return;
    }
    
    console.log('Required libraries loaded successfully');
    
    // Initialize daterangepicker
    $('#reportDateRange').daterangepicker({
        locale: {
          format: 'DD/MM/YYYY'
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
          'Last 30 Days': [moment().subtract(29, 'days'), moment()],
          'This Quarter': [moment().startOf('quarter'), moment().endOf('quarter')],
          'Last Quarter': [moment().subtract(1, 'quarter').startOf('quarter'), moment().subtract(1, 'quarter').endOf('quarter')]
        }
    });
    
    console.log('Daterangepicker initialized successfully');
    
    // Set existing date range if available
    if($('#hiddenReportDateRange').val()) {
        $('#reportDateRange').val($('#hiddenReportDateRange').val());
        console.log('Set saved date range:', $('#hiddenReportDateRange').val());
    }

    // Handle date range changes
    $('#reportDateRange').on('change', function() {
        const selectedDateRange = $(this).val();
        $('#hiddenReportDateRange').val(selectedDateRange);
    });

    // Handle the apply event from daterangepicker to submit form
    $('#reportDateRange').on('apply.daterangepicker', function(ev, picker) {
        console.log('Daterangepicker apply event triggered');
        const selectedDateRange = picker.startDate.format('DD/MM/YYYY') + ' - ' + picker.endDate.format('DD/MM/YYYY');
        $(this).val(selectedDateRange);
        $('#hiddenReportDateRange').val(selectedDateRange);
        console.log('Applied date range:', selectedDateRange);
        
        // Auto-submit the form when date range is applied
        setTimeout(function() {
            console.log('Submitting form with date range:', selectedDateRange);
            $('#periodic-report-form').submit();
        }, 100);
    });

    // Handle cancel event
    $('#reportDateRange').on('cancel.daterangepicker', function(ev, picker) {
        console.log('Daterangepicker cancelled');
        // Don't clear the field, keep the previous value
    });

    // Set the initial value of hiddenReportDateRange
    const initialDateRange = $('#reportDateRange').val();
    $('#hiddenReportDateRange').val(initialDateRange);
    
    // Make sure the date picker has a value if there's a saved one
    if($('#hiddenReportDateRange').val() && $('#reportDateRange').val() === '') {
        $('#reportDateRange').val($('#hiddenReportDateRange').val());
    }
    
    console.log('Daterangepicker initialization complete');
});

// Form auto-submission for branch changes
$(document).ready(function() {
    // Check if jQuery is available
    if (typeof $ === 'undefined') {
        console.error('jQuery not available for form auto-submission!');
        return;
    }
    
    console.log('Initializing form auto-submission...');
    
    // Auto submit the form when branch selection changes
    const form = $("#periodic-report-form");
    const branchSelect = form.find("select[name='empBranch']");

    if (branchSelect.length > 0) {
        branchSelect.on("change", function() {
            console.log('Branch selection changed, submitting form...');
            form.submit();
        });
        console.log('Form auto-submission initialized successfully');
    } else {
        console.log('Branch select not found');
    }
});

// Prevent the data being auto-loaded on page refresh
if (window.history.replaceState) {
    window.history.replaceState(null, null, window.location.href);
}
</script>
