<?php
$page = 'daily-report';
// Include utilities for role check functions
require_once '../../includes/session_config.php';
require_once '../../includes/utilities.php';

// Include the header (handles head, body, topbar, sidebar, opens wrappers)
require_once __DIR__ . '/../../includes/header.php';
include '../../includes/db_connection.php'; // DB connection needed after header potentially?
?>
<!-- Page-specific CSS (DataTables) - Ideally loaded via header.php, but placed here for now -->
<link rel="stylesheet" href="<?php echo $home;?>plugins/datatables-bs4/css/dataTables.bootstrap4.min.css">
<link rel="stylesheet" href="<?php echo $home;?>plugins/datatables-responsive/css/responsive.bootstrap4.min.css">
<link rel="stylesheet" href="<?php echo $home;?>plugins/datatables-buttons/css/buttons.bootstrap4.min.css">

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
        
        /* Show only the table and logo */
        #daily-report-table, 
        #daily-report-table *,
        .print-logo,
        .print-logo * {
            visibility: visible !important;
        }
        
        /* Make the logo div visible and position it */
        .print-logo {
            display: block !important;
            position: fixed !important;
            top: 0 !important;
            right: 0 !important;
            width: auto !important;
            z-index: 9999 !important;
        }
        
        /* Position the table below the logo */
        #daily-report-table {
            position: fixed !important;
            top: 85px !important; /* Leave space for the logo */
            left: 0 !important;
            width: 100% !important;
            max-width: none !important;
            border-collapse: collapse !important;
            font-size: 8pt !important;
            display: table !important;
            box-sizing: border-box !important;
            transform: none !important;
        }
        
        /* Ensure all table elements display properly */
        #daily-report-table thead { display: table-header-group !important; }
        #daily-report-table tbody { display: table-row-group !important; }
        #daily-report-table tfoot { display: table-footer-group !important; }
        #daily-report-table tr { display: table-row !important; }
        #daily-report-table th, 
        #daily-report-table td {
            display: table-cell !important;
            padding: 1px !important;
            border: 0.5px solid #ddd !important;
            font-size: 8pt !important;
            white-space: nowrap !important;
            color: #000 !important;
        }
        
        /* Make sure the table rows are as compact as possible */
        #daily-report-table tr {
            height: auto !important;
            line-height: 1 !important;
        }
        
        /* Set landscape orientation with minimal margins */
        @page {
            size: landscape !important;
            margin: 0.5cm !important;
        }
    }
</style>

<!-- Body tag is opened in header.php -->
<!-- Wrapper div is removed -->
<!-- Topbar include is removed (handled by header.php) -->
<!-- Sidebar include is removed (handled by header.php) -->
<!-- Content Wrapper div is opened in header.php -->

<!-- Content Header (Page header) -->
<div class="content-header">
  <div class="container-fluid">
    <div class="row mb-2">
      <div class="col-sm-6">
        <h1 class="m-0">Daily Report</h1>
      </div><!-- /.col -->
    </div><!-- /.row -->
  </div><!-- /.container-fluid -->
</div>
<!-- /.content-header -->

<!-- Main content -->
<section class="content">
 <div class="container-fluid">
 <div class="card">
          <div class="card-header" style="padding: 10px;">
            <form action="api/fetch-daily-report-data.php" method="POST" id="daily-report-form" class="mt-3">
              <div class="row">
                <div class="col-md-3">
                  <div class="form-group">
                    <label for="reportdate">Report Date <span class="text-danger">*</span></label>
                    <div class="input-field" style="border:1px solid #ddd; width: 100%; border-radius: 5px; padding: 2px; display: flex; align-items: center;">
                      <i class="fas fa-calendar-alt mr-2" style="font-size: 1.5rem;"></i>
                      <input type="date" class="form-control border-0" id="reportdate" name="reportdate" 
                      value="<?= isset($_POST['reportdate']) ? $_POST['reportdate'] : date('Y-m-d'); ?>" 
                      max="<?= date('Y-m-d'); ?>" required>
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
          <!-- /.card-header -->
           
          <div class="card-body">
            <!-- Hidden logo for printing -->
            <div class="d-none print-logo">
              <img src="<?php echo $home;?>resources/logo.png" alt="Company Logo" style="height: 80px; float: right; margin-bottom: 10px;">
            </div>
            <!-- Removed print button from here -->
            <table id="daily-report-table" class="table table-sm table-bordered table-striped" width="100%">
              <thead>
                <!-- <tr>
                  <th class="align-items-center text-center" style="font-size: 1.8rem;" colspan="18">Prime Express Courier & Cargo Pvt. Ltd.</th>
                </tr> -->
                <tr>
                  <?php
                    // Count the number of <th> in the next row to set dynamic colspan
                    $colspan = 0;
                    $headerRow = [
                      ['rowspan' => 2], // SN
                      ['rowspan' => 2], // Employee Name
                      ['rowspan' => 2], // Branch
                      ['colspan' => 3], // Planned Time
                      ['colspan' => 3], // Worked Time
                      ['rowspan' => 2], // Overtime
                      ['rowspan' => 2], // Late In
                      ['rowspan' => 2], // Early Out
                      ['rowspan' => 2], // Early In
                      ['rowspan' => 2], // Late Out
                      ['rowspan' => 2], // Marked As
                      ['rowspan' => 2], // Methods
                      ['rowspan' => 2], // Remarks
                    ];
                    foreach ($headerRow as $col) {
                      if (isset($col['colspan'])) {
                        $colspan += $col['colspan'];
                      } else {
                        $colspan += 1;
                      }
                    }
                  ?>
                  <th class="align-items-center text-center" colspan="<?php echo $colspan; ?>">
                    Daily Attendance Report: <?php if (isset($_POST['reportdate'])) { echo $_POST['reportdate']; } ?>
                  </th>
                </tr>
                <tr>
                  <th class="align-items-center text-center" rowspan="2">SN</th>
                  <th class="align-items-center text-center" rowspan="2">Employee Name</th>
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
                        // Display methods as already formatted from fetch script
                        echo $row['methods'];
                      ?>
                    </td>
                    <td class="align-items-center text-center"><?php echo $row['remarks'] ?></td>
                </tr>
                    <?php 
                  }
                } else {
                    echo "<tr><td class='align-items-center text-center' colspan='17'>There is no employees for Selected Branch.</td></tr>";
                }
            } else {
                echo "<tr><td class='align-items-center text-center' colspan='17'>No data fetched. Submit above to view report.</td></tr>";
            }
                ?>
          <?php
            if (isset($jsonData)){
              $totalEmployees = count($jsonData);
            }
            $presentCount = 0;
            $absentCount = 0;
            $leaveCount = 0;

            if (isset($jsonData) && is_array($jsonData)) {
              foreach ($jsonData as $row) {
                  // Count all present variations (Present, Present (Holiday), Present (Weekend))
                  if (strpos($row['marked_as'], 'Present') !== false) {
                      $presentCount++;
                  } elseif ($row['marked_as'] == 'Absent') {
                      $absentCount++;
                  } elseif ($row['marked_as'] == 'Leave') {
                      $leaveCount++;
                  }
                  // Note: Holiday, Weekend, and Exited are not counted in any of the three categories
              }
            }
          ?>
            <tfoot>
              <tr>
                <th class="align-items-center text-right" colspan="2">Daily Summary: </th>
                <th class="align-items-center text-center" colspan="3">Total Employees: <?php if (isset($jsonData)){ echo $totalEmployees;}else{echo 0;}?></th>
                <th class="align-items-center text-center" colspan="4">Total Present: <?php echo $presentCount;?></th>
                <th class="align-items-center text-center" colspan="4">Total Absent: <?php echo $absentCount;?></th>
                <th class="align-items-center text-center" colspan="4">Total on Leave: <?php echo $leaveCount;?></th>
              </tr>
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

<!-- Content wrapper div is closed in footer.php -->

<!-- Footer include (handles closing wrappers, common JS) -->
<?php 
include '../../includes/footer.php';
?>

<!-- Wrapper div closing tag is removed -->

<!-- REQUIRED SCRIPTS are removed (handled by footer.php) -->

<!-- Page Specific Scripts -->
<script>
  $(function () {
    var table = $("#daily-report-table").DataTable({
        "responsive": true,
        "lengthChange": false,
        "autoWidth": false, // Changed to false to avoid width calculations
        "paging": false,
        "searching": true,
        "ordering": false,
        "info": false,
        "columnDefs": [
            { "orderable": false, "targets": '_all' } // Disable ordering on all columns
        ],
        "dom": 't',  // Only show the table, no other controls
        "fixedHeader": false, // Disable fixed header which can cause issues with complex headers
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
                title: 'Daily Attendance Report of <?php echo  $_POST['reportdate'] ?? '' ?>',
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

// Add functionality to the print button - ensure DOM is ready
$(document).ready(function() {
    // Check if print button exists and attach event
    const printBtn = $('#print-table-btn');
    if (printBtn.length > 0) {
        console.log('Daily report print button found, attaching event'); // Debug log
        printBtn.on('click', function(e) {
            e.preventDefault();
            console.log('Daily report print button clicked - initiating print'); // Debug log
            window.print();
        });
    } else {
        console.log('Daily report print button not found in DOM'); // Debug log
    }
    
    // Also handle click using event delegation for dynamic content
    $(document).on('click', '#print-table-btn', function(e) {
        e.preventDefault();
        console.log('Daily report print button clicked via delegation'); // Debug log
        window.print();
    });
});

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
<!-- AdminLTE JavaScript -->
<script src="<?php echo $home;?>dist/js/adminlte.js"></script>
<!-- AdminLTE for demo purposes -->
<script src="<?php echo $home;?>dist/js/demo.js"></script>

</body>
</html>