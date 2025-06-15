<?php
$page = 'periodic-time-report';
// Include utilities for role check functions
require_once '../../includes/session_config.php';
require_once '../../includes/utilities.php';

// Check if user has permission to access reports
if (!has_permission('view_daily_report') && !is_admin()) {
    $_SESSION['error'] = "You don't have permission to access Reports.";
    header('Location: index.php');
    exit();
}

// Include the header (handles head, body, topbar, sidebar, opens wrappers)
require_once __DIR__ . '/../../includes/header.php';
include '../../includes/db_connection.php'; // DB connection needed after header potentially?
?>

<!-- DataTables & CSS -->
<link rel="stylesheet" href="<?php echo $home; ?>plugins/datatables-bs4/css/dataTables.bootstrap4.min.css">
<link rel="stylesheet" href="<?php echo $home; ?>plugins/datatables-responsive/css/responsive.bootstrap4.min.css">
<link rel="stylesheet" href="<?php echo $home; ?>plugins/datatables-buttons/css/buttons.bootstrap4.min.css">
<link rel="stylesheet" href="<?php echo $home; ?>plugins/tempusdominus-bootstrap-4/css/tempusdominus-bootstrap-4.min.css">
<link rel="stylesheet" href="<?php echo $home; ?>plugins/daterangepicker/daterangepicker.css">

<!-- Print-specific styles -->
<style>    /* Regular styling for the time report table */
    .time-report-table th:not(:first-child):not(:nth-child(2)):not(:last-child):not(:nth-last-child(2)):not(:nth-last-child(3)):not(:nth-last-child(4)):not(:nth-last-child(5)) {
        min-width: 30px; /* Ensure date columns have some minimum width */
        max-width: 50px;
        width: 40px;
    }
    
    /* Status column styling */
    .badge-success, .badge-danger {
        font-weight: bold;
    }
    
    /* Print-specific styles - optimized for full width and proper positioning */    /* Helper class for print-visible elements */
    .print-visible {
        display: none;
    }
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
        
        /* Remove unwanted margins and set white background */
        body {
            margin: 0 !important;
            padding: 0 !important;
            background-color: #ffffff !important;
            background: #ffffff !important;
        }
        
        /* Ensure white background for printing area */
        .content-wrapper,
        .card,
        .card-body {
            background-color: #ffffff !important;
            background: #ffffff !important;
        }/* Show only the tables, rules section, and logo */
        .time-report-table, 
        .time-report-table *,
        .print-logo,
        .print-logo *,
        .rules-section,
        .rules-section *,
        .table-responsive {
            visibility: visible !important;
            opacity: 1 !important;
            display: block !important;
        }
          /* Force table display properties */
        .time-report-table {
            display: table !important;
            background-color: #ffffff !important;
            color: #000000 !important;
        }
          /* Default white background for table cells, but not status cells */
        .time-report-table tr,
        .time-report-table th,
        .time-report-table td:not(.bg-success):not(.bg-danger):not(.bg-secondary):not(.bg-warning) {
            background-color: #ffffff !important;
        }        /* Force display of rules section during print */
        .rules-section,
        .rules-section.d-none,
        .print-visible {
            display: block !important;
            margin-top: 20px !important;
            padding-top: 10px !important;
            color: #000 !important;
        }
        
        /* Ensure the table is visible during print */
        .table-responsive {
            display: block !important;
            overflow: visible !important;
        }
          /* Make the logo div visible and position it */
        .print-logo {
            display: block !important;
            position: absolute !important;
            top: 0.5cm !important;
            right: 0.5cm !important;
            width: auto !important;
            height: auto !important;
            z-index: 9999 !important;
            margin: 0 !important;
            padding: 0 !important;
        }
          /* Position the table properly for printing */
        .time-report-table {
            margin: 0 !important;
            padding: 0 !important;
            border-collapse: collapse !important;
            font-size: 9pt !important;
            display: table !important;
            width: 100% !important;
            box-sizing: border-box !important;
            page-break-after: auto !important;
        }
          /* Remove extra margin in parent containers */
        .table-responsive, 
        .card-body,
        .print-container {
            margin: 0 !important;
            padding: 0 !important;
            display: block !important;
            visibility: visible !important;
        }
        
        .time-report-table thead { display: table-header-group !important; }
        .time-report-table tbody { display: table-row-group !important; }
        .time-report-table tfoot { display: table-footer-group !important; }
        .time-report-table tr { display: table-row !important; }        .time-report-table th, 
        .time-report-table td {
            display: table-cell !important;
            padding: 1px !important;
            border: 0.5px solid #ddd !important;
            font-size: 9pt !important;
            white-space: nowrap !important;
            color: #000 !important;
        }
        
        /* Preserve status color backgrounds in print */
        .time-report-table td.bg-success {
            background-color: #28a745 !important;
            color: #ffffff !important;
        }
        
        .time-report-table td.bg-danger {
            background-color: #dc3545 !important;
            color: #ffffff !important;
        }
        
        .time-report-table td.bg-secondary {
            background-color: #6c757d !important;
            color: #ffffff !important;
        }
        
        .time-report-table td.bg-warning {
            background-color: #ffc107 !important;
            color: #000000 !important;
        }
          /* Make sure the table rows are as compact as possible */
        .time-report-table tr {
            height: auto !important;
            line-height: 1.5 !important;
        }
        .summary{
            line-height: 1 !important
        }
          /* Ensure footer column totals are visible in print */
        .time-report-table tfoot .badge {
            color: #ffffff !important;
            font-size: 7pt !important;
            margin: 0 !important;
            padding: 1px 3px !important;
            line-height: 1 !important;
            display: block !important;
        }
          /* Summary badges colors in footer */
        .time-report-table tfoot .badge-success { background-color: #28a745 !important; }
        .time-report-table tfoot .badge-danger { background-color: #dc3545 !important; }
        .time-report-table tfoot .badge-secondary { background-color: #6c757d !important; }
        .time-report-table tfoot .badge-warning { background-color: #ffc107 !important; color: #000000 !important; }
        .time-report-table tfoot .badge-info { background-color: #17a2b8 !important; }
        /* Hide all buttons */
        button, 
        .buttons-collection,
        .dt-buttons,
        .btn-group {
            display: none !important;
        }        /* Set landscape orientation with minimal margins */
        @page {
            size: landscape !important;
            margin: 0.5cm !important;
        }
        
        /* Ensure page has white background for printing */
        body, html {
            background-color: #ffffff !important;
            background: #ffffff !important;
            color: #000000 !important;
        }
          /* Ensure colors print properly */
        * {
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
            color-adjust: exact !important;
        }
        
        /* Force color printing for specific status cells */
        .bg-success, .bg-danger, .bg-secondary, .bg-warning {
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
            color-adjust: exact !important;
            print-color: exact !important;
        }
          /* Custom styling for Nepali rules in print */
        .rules-section h4 {
            font-size: 16pt !important;
            margin-bottom: 10px !important;
            padding: 0 !important;
        }
        
        .rules-section ol {
            padding-left: 5px !important;
            margin: 10px 0 !important;
            list-style-type: none !important;
        }
        
        .rules-section li {
            font-size: 12pt !important;
            line-height: 1.5 !important;
            margin-bottom: 4px !important;
            padding-left: 5px !important;
            display: block !important;
        }
    }
</style>

<!-- Content Header (Page header) -->
<div class="content-header">
    <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-6">
                <h1 class="m-0">Periodic Entry Time Report</h1>
            </div>
        </div>
    </div>
</div>

<!-- Main content -->
<section class="content">
    <div class="container-fluid">
        <div class="card">
            <div class="card-header" style="padding: 10px;">
                <form action="api/fetch-periodic-time-report-data.php" method="POST" id="periodic-time-report-form" class="mt-3">                    <?php $savedDateRange = isset($_POST['reportDateRange']) ? $_POST['reportDateRange'] : ''; ?>
                    <input type="hidden" id="hiddenReportDateRange" value="<?php echo $savedDateRange; ?>">
                    <div class="row">
                        <div class="col-md-3">
                            <div class="form-group">
                                <label for="reportDateRange">Date Range <span class="text-danger">*</span></label>
                                <div class="input-field" style="border:1px solid #ddd; width: 100%; border-radius: 5px; padding: 2px; display: flex; align-items: center;">
                                    <i class="fas fa-calendar-alt mr-2" style="font-size: 1.5rem;"></i>
                                    <input type="text" class="form-control border-0" id="reportDateRange" name="reportDateRange" value="<?php echo $savedDateRange; ?>" required>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label for="empBranch">Branch <span class="text-danger">*</span></label>
                                <div class="input-field" style="border:1px solid #ddd; width:100%; border-radius: 5px; padding: 2px; display: flex; align-items: center;">
                                    <i class="fas fa-building mr-2" style="font-size: 1.5rem;"></i>                                
                                    <select class="form-control border-0" id="empBranch" name="empBranch" required>
                                        <?php
                                            $selectedBranch = isset($_POST['empBranch']) ? $_POST['empBranch'] : ''; // Store selected value
                                            $branchQuery = "SELECT id, name FROM branches";
                                            $stmt = $pdo->query($branchQuery);
                                            $branches = $stmt->fetchAll();
                                            $firstBranch = true;
                                            
                                            foreach ($branches as $row) {
                                                // If no branch is selected, select the first one by default
                                                if (empty($selectedBranch) && $firstBranch) {
                                                    $selected = 'selected';
                                                    $firstBranch = false;
                                                } else {
                                                    $selected = ($selectedBranch == $row['id']) ? 'selected' : '';
                                                }
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
            </div>            <div class="card-body p-0">                <!-- Hidden logo for printing -->
                <div class="d-none print-logo">
                    <img src="<?php echo $home;?>resources/logo.png" alt="Company Logo" style="height: 60px; width: auto;">
                </div>

                <?php 
                if (isset($_POST['jsonData'])) {
                    $jsonData = json_decode($_POST['jsonData'], true);
                    $reportDateRange = isset($_POST['reportDateRange']) ? $_POST['reportDateRange'] : '';

                    if ($jsonData && is_array($jsonData)) {
                        // Extract unique dates for columns
                        $dates = [];
                        foreach ($jsonData as $record) {
                            if (isset($record['dates']) && is_array($record['dates'])) {
                                foreach ($record['dates'] as $dateData) {
                                    $dates[$dateData['date']] = $dateData['date'];
                                }
                            }
                        }
                        ksort($dates); // Sort dates chronologically
                ?>                <div class="table-responsive print-container p-0">
                    <table id="time-report-table" class="table table-sm table-bordered table-striped time-report-table">
                        <thead>
                            <tr>
                                <th class="text-center" colspan="<?php echo 2 + count($dates) + 5; ?>">Periodic Entry Time Report: <?php echo $reportDateRange; ?></th>
                            </tr>
                            <tr>
                                <th class="text-center" colspan="<?php echo 2 + count($dates) + 5; ?>">
                                    Branch: <?php echo isset($jsonData[0]['branch']) ? $jsonData[0]['branch'] : ''; ?>
                                    &nbsp;&nbsp;|&nbsp;&nbsp;
                                    Working Days: <?php echo isset($jsonData[0]['summary']['working_days']) ? $jsonData[0]['summary']['working_days'] : 0; ?>
                                    &nbsp;&nbsp;|&nbsp;&nbsp;
                                    Holidays: <?php echo isset($jsonData[0]['summary']['holidays']) ? $jsonData[0]['summary']['holidays'] : 0; ?>
                                </th>
                            </tr>
                            <tr>
                                <th class="text-center">SN</th>
                                <th class="text-center">Employee</th>
                                <?php                                foreach ($dates as $date): 
                                    $dayOfWeek = date('N', strtotime($date)); // 1 (Mon) to 7 (Sun)
                                    $isSaturday = ($dayOfWeek == 6);
                                    $headerClass = $isSaturday ? 'bg-warning' : '';
                                    $headerText = date('d', strtotime($date));
                                ?>
                                    <th class="text-center <?php echo $headerClass; ?>"><?php echo $headerText; ?></th>
                                <?php endforeach; ?>                                <th class="text-center text-success">Present</th>
                                <th class="text-center bg-danger">Absent</th>
                                <th class="text-center bg-secondary">Leave</th>
                                <th class="text-center bg-danger">Amount</th>
                                <th class="text-center bg-secondary">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($jsonData as $index => $employee): ?>
                            <tr>                                
                                <td class="text-center"><?php echo $index + 1; ?></td>
                                <td class="text-left"><?php echo $employee['emp_id'] . ' - ' . $employee['employee_name']; ?></td><?php 
                                foreach ($dates as $date): 
                                    $status = '-';
                                    $statusClass = '';
                                      if (isset($employee['dates'][$date])) {
                                        $status = $employee['dates'][$date]['status'];
                                        
                                        // Add appropriate status classes for styling with background colors
                                        if ($status === 'P') $statusClass = 'bg-success text-white';
                                        else if ($status === 'A') $statusClass = 'bg-danger text-white';
                                        else if ($status === 'L') $statusClass = 'bg-secondary text-white'; // Gray for L
                                        else if ($status === 'H') $statusClass = 'bg-warning text-black'; // Yellow for H
                                    }
                                    
                                    // Check if it's a Saturday to highlight
                                    $dayOfWeek = date('N', strtotime($date)); // 1 (Mon) to 7 (Sun)
                                    $isSaturday = ($dayOfWeek == 6);
                                    $headerClass = $isSaturday ? 'bg-warning text-black' : '';
                                ?>                                    <td class="text-center font-weight-bold <?php echo $statusClass . ' ' . $headerClass; ?>">
                                        <?php echo $status; ?>
                                    </td>
                                <?php endforeach; ?>                                <!-- Add individual employee summary counts -->                                <td class="text-center font-weight-bold text-white bg-success">
                                    <?php echo isset($employee['summary']['present']) ? $employee['summary']['present'] : 0; ?>
                                </td>
                                <td class="text-center font-weight-bold bg-danger">
                                    <?php $absentCount = isset($employee['summary']['absent']) ? $employee['summary']['absent'] : 0; 
                                    echo $absentCount;
                                    ?>
                                </td>
                                <td class="text-center font-weight-bold bg-secondary">
                                    <?php echo isset($employee['summary']['leave']) ? $employee['summary']['leave'] : 0; ?>
                                </td>
                                <td class="text-center font-weight-bold bg-danger">
                                    <?php 
                                    // Calculate amount: If A ≤ 3, amount = 0; If A > 3, amount = (A-3) × 50
                                    $absentCount = isset($employee['summary']['absent']) ? $employee['summary']['absent'] : 0;
                                    $amount = ($absentCount <= 3) ? 0 : (($absentCount - 3) * 50);
                                    echo number_format($amount, 0);
                                    ?>
                                </td>
                                <td class="text-center font-weight-bold">
                                    <?php 
                                    // Calculate status:
                                    // "Selected" if P ≥ 60% of working days AND A ≤ 2, "Not Selected" otherwise
                                    $presentCount = isset($employee['summary']['present']) ? $employee['summary']['present'] : 0;
                                    $workingDays = isset($employee['summary']['working_days']) ? $employee['summary']['working_days'] : 0;
                                    $absentCount = isset($employee['summary']['absent']) ? $employee['summary']['absent'] : 0;
                                    
                                    $sixtyPercentOfWorkingDays = floor($workingDays * 0.6); // Round down to whole number (60%)
                                    
                                    $isSelected = ($presentCount >= $sixtyPercentOfWorkingDays && $absentCount <= 2);
                                    $status = $isSelected
                                        ? '<span class="bg-success text-white">Selected</span>' 
                                        : '<span class="bg-danger text-white">Not Selected</span>';

                                    echo $status;
                                    ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>                        
                        <tfoot>
                            <tr>
                                <th class="text-right" colspan="2">Summary:</th>
                                <?php 
                                // For each date, count the totals of P/A/L/H
                                foreach ($dates as $date): 
                                    $pCount = 0;
                                    $aCount = 0;
                                    $lCount = 0;
                                    $hCount = 0;
                                    
                                    foreach ($jsonData as $employee) {
                                        if (isset($employee['dates'][$date])) {
                                            $status = $employee['dates'][$date]['status'];
                                            if ($status === 'P') $pCount++;
                                            elseif ($status === 'A') $aCount++;
                                            elseif ($status === 'L') $lCount++;
                                            elseif ($status === 'H') $hCount++;
                                        }
                                    }
                                    
                                    $dayOfWeek = date('N', strtotime($date)); // 1 (Mon) to 7 (Sun)
                                    $isSaturday = ($dayOfWeek == 6);
                                    $headerClass = $isSaturday ? 'bg-warning text-black' : '';                                    // Display total summary for each date column
                                    $totalDisplay = '';
                                    if ($pCount > 0) $totalDisplay .= "<span class='summary badge badge-success' style='line-height:1!important;display:block;margin-bottom:1px;'>P:{$pCount}</span>";
                                    if ($aCount > 0) $totalDisplay .= "<span class='summary badge badge-danger' style='line-height:1!important;display:block;margin-bottom:1px;'>A:{$aCount}</span>";
                                    if ($lCount > 0) $totalDisplay .= "<span class='summary badge badge-secondary' style='line-height:1!important;display:block;margin-bottom:1px;'>L:{$lCount}</span>";
                                    if ($hCount > 0) $totalDisplay .= "<span class='summary badge badge-warning text-black' style='line-height:1!important;display:block;margin-bottom:1px;'>H:{$hCount}</span>";
                                    
                                    // If Saturday, highlight with background class
                                    $fullDateStr = date('Y-m-d', strtotime($date));
                                ?>
                                <th class="text-center <?php echo $headerClass; ?>" title="<?php echo strip_tags($totalDisplay); ?>"><?php echo $totalDisplay; ?></th>
                                <?php endforeach; ?>                                
                                <th class="text-center text-success"></th>
                                <th class="text-center text-danger"></th>
                                <th class="text-center text-warning"></th>
                                <th class="text-center text-info">
                                    <?php
                                    // Calculate total amount for all employees
                                    $totalAmount = 0;
                                    foreach ($jsonData as $employee) {
                                        $absentCount = isset($employee['summary']['absent']) ? $employee['summary']['absent'] : 0;
                                        $amount = ($absentCount <= 3) ? 0 : (($absentCount - 3) * 50);
                                        $totalAmount += $amount;
                                    }
                                    echo number_format($totalAmount, 0);
                                    ?>
                                </th>
                                <?php
                                // Count total "Selected" employees
                                $selectedCount = 0;
                                foreach ($jsonData as $employee) {
                                    $presentCount = isset($employee['summary']['present']) ? $employee['summary']['present'] : 0;
                                    $workingDays = isset($employee['summary']['working_days']) ? $employee['summary']['working_days'] : 0;
                                    $absentCount = isset($employee['summary']['absent']) ? $employee['summary']['absent'] : 0;
                                    $sixtyPercentOfWorkingDays = floor($workingDays * 0.6);
                                    $isSelected = ($presentCount >= $sixtyPercentOfWorkingDays && $absentCount <= 2);
                                    if ($isSelected) $selectedCount++;
                                }
                                ?>
                                <th class="text-center text-success"><?php echo $selectedCount; ?></th>
                            </tr>
                        </tfoot>
                    </table>                
                </div>                <!-- Rules section - visible only when printing -->
                <div class="d-none rules-section">
                    <div class="mt-4 p-3 border-top">
                        <h4 class="font-weight-bold">नियमहरू:</h4>
                        <ol style="font-size: 14px; margin-top: 10px;">
                            <li style="margin-bottom: 5px;">1. नयाँ कर्मचारीले पहिलो पटक गोलाप्रथामा सहभागी हुन पाउने छैन।</li> 
                            <li style="margin-bottom: 5px;">2. गोलाप्रथामा योग्य हुन कम्तीमा १५ दिन अनिवार्य उपस्थित हुनुपर्नेछ।</li> 
                            <li style="margin-bottom: 5px;">3. २ दिनसम्मको अनुपस्थिति भए पनि गोलाप्रथामा योग्य मानिनेछ।</li> 
                            <li style="margin-bottom: 5px;">4. ३ दिनसम्म अनुपस्थित हुँदा कुनै जरिवाना लाग्ने छैन।</li> 
                            <li style="margin-bottom: 5px;">5. ४ वा बढी दिन अनुपस्थित भएमा पहिलो दिनदेखि नै जरिवाना गणना हुनेछ।</li> 
                            <li style="margin-bottom: 5px;">6. नामावली प्रकाशित भएपछि आउँदो आइतबार गोलाप्रथा सञ्चालन हुनेछ।</li> 
                            <li style="margin-bottom: 5px;">7. प्रकाशित नामावली पुनः जाँच गर्न परेमा, अथवा कुनै गुनासो वा सुझाव भएमा श्री सागर खतिवडासँग सम्पर्क गर्नुहोस्।</li>
                        </ol>
                    </div>
                </div>

                <?php 
                    } else {
                        echo "<p class='text-center'>No data available for the selected date range.</p>";
                    }
                }
                ?>
            </div>
        </div>
    </div>
</section>

<!-- Footer include (handles closing wrappers, common JS) -->
<?php 
include '../../includes/footer.php';
?>

<!-- Page Specific Scripts -->
<script src="<?php echo $home;?>plugins/moment/moment.min.js"></script>
<script src="<?php echo $home;?>plugins/daterangepicker/daterangepicker.js"></script>
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

<script>
$(function() {
    // Initialize DataTables
    var table = $("#time-report-table").DataTable({
        "responsive": true,
        "lengthChange": false,
        "autoWidth": true,
        "paging": false,
        "searching": true,
        "ordering": false,
        "info": false,
        // "buttons": [
        //     'colvis',
        //     {
        //         extend: 'print',
        //         text: 'Print',
        //         exportOptions: {
        //             modifier: {
        //                 page: 'all',
        //             },
        //             header: true
        //         },
        //         customize: function (win) {
        //             $(win.document.body).css('font-size', '10pt');
        //             $(win.document.body).find('table')
        //                 .addClass('compact')
        //                 .css('font-size', 'inherit');
                    
        //             // Set paper size and orientation 
        //             var css = '@page { size: landscape; margin: 0.3cm; }';
        //             var head = win.document.head || win.document.getElementsByTagName('head')[0];
        //             var style = win.document.createElement('style');
        //             style.type = 'text/css';
        //             style.media = 'print';
        //             if (style.styleSheet) {
        //                 style.styleSheet.cssText = css;
        //             } else {
        //                 style.appendChild(win.document.createTextNode(css));
        //             }
        //             head.appendChild(style);
        //         }
        //     }
        // ],
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
    table.buttons().container().appendTo('#time-report-table_wrapper .col-md-6:eq(0)');    // Initialize DateRangePicker
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
    
    // Set existing date range if available
    if($('#hiddenReportDateRange').val()) {
        $('#reportDateRange').val($('#hiddenReportDateRange').val());
    }    // Print button functionality - improved to ensure table display with proper colors
    $(document).on('click', '#print-report-btn', function() {
        // Force rules section to be visible during print
        $('.rules-section').addClass('print-visible');
        
        // Remove unwanted margins for better printing
        $('body').css('margin', '0');
        $('.card-body').css('padding', '0');
        
        // Set white background for print
        $('body, html').css('background-color', '#ffffff');
        $('.content-wrapper, .card, .card-body').css('background-color', '#ffffff');
        
        // Ensure table visibility
        $('.time-report-table').css('display', 'table').css('visibility', 'visible');
        $('.time-report-table *').css('visibility', 'visible');
        
        // Set background colors only for non-status cells
        $('.time-report-table td:not(.bg-success):not(.bg-danger):not(.bg-secondary):not(.bg-warning)').css('background-color', '#ffffff');
          // Ensure status cells have proper colors
        $('.time-report-table td.bg-success').css('background-color', '#28a745').css('color', '#ffffff');
        $('.time-report-table td.bg-danger').css('background-color', '#dc3545').css('color', '#ffffff');
        $('.time-report-table td.bg-secondary').css('background-color', '#6c757d').css('color', '#ffffff');
        $('.time-report-table td.bg-warning').css('background-color', '#ffc107').css('color', '#ffffff');
        
        // Make sure the rules section appears correctly
        $('.rules-section').css('display', 'block').css('visibility', 'visible');
        
        setTimeout(function() {
            window.print();
        }, 500); // Increased delay for better rendering
    });// Ensure the reportDateRange input is populated correctly
    $(document).ready(function() {
        // Handle date range changes
        $('#reportDateRange').on('change', function() {
            const selectedDateRange = $(this).val();
            $('#hiddenReportDateRange').val(selectedDateRange);
        });

        // Set the initial value of hiddenReportDateRange
        const initialDateRange = $('#reportDateRange').val();
        $('#hiddenReportDateRange').val(initialDateRange);
        
        // Make sure the date picker has a value if there's a saved one
        if($('#hiddenReportDateRange').val() && $('#reportDateRange').val() === '') {
            $('#reportDateRange').val($('#hiddenReportDateRange').val());
        }
        
        // Enable print functionality even after page reload
        if($('#time-report-table').length > 0) {
            console.log('Report table detected');
            $('#print-report-btn').show();
        } else {
            console.log('No report table detected');
        }
    });
});
</script>
