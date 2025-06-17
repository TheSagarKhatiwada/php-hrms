<?php
$page = 'daily-report';
$home = '../../'; // Set proper path to project root for asset loading
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
    
    /* Styles for wide table handling */
    .table-responsive {
        overflow-x: auto;
    }
    
    #daily-report-table {
        min-width: 1200px; /* Ensure minimum width for all columns */
        font-size: 0.875rem; /* Slightly smaller font for better fit */
    }
    
    #daily-report-table th,
    #daily-report-table td {
        white-space: nowrap;
        min-width: 60px;
        padding: 0.375rem 0.25rem; /* Reduced padding */
    }
    
    /* Specific column widths */
    #daily-report-table th:nth-child(1),
    #daily-report-table td:nth-child(1) { min-width: 40px; } /* SN */
    #daily-report-table th:nth-child(2),
    #daily-report-table td:nth-child(2) { min-width: 150px; } /* Employee Name */
    #daily-report-table th:nth-child(3),
    #daily-report-table td:nth-child(3) { min-width: 80px; } /* Branch */
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
            
            <!-- Report Title -->
            <div class="text-center mb-3">
              <h4>Daily Attendance Report: <?php if (isset($_POST['reportdate'])) { echo $_POST['reportdate']; } ?></h4>
            </div>
            
            <!-- Removed print button from here -->
            <div class="table-responsive">
            <table id="daily-report-table" class="table table-sm table-bordered table-striped" width="100%">
              <thead>
                <tr>
                  <th class="align-items-center text-center">SN</th>
                  <th class="align-items-center text-center">Employee Name</th>
                  <th class="align-items-center text-center">Branch</th>
                  <th class="align-items-center text-center">Planned In</th>
                  <th class="align-items-center text-center">Planned Out</th>
                  <th class="align-items-center text-center">Work Hrs</th>
                  <th class="align-items-center text-center">Actual In</th>
                  <th class="align-items-center text-center">Actual Out</th>
                  <th class="align-items-center text-center">Worked Hrs</th>
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
            </div> <!-- /.table-responsive -->
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
$(document).ready(function() {
    // Check if table exists and has data before initializing DataTables
    var table = $('#daily-report-table');
    
    if (table.length > 0) {
        // Count actual data rows (exclude header)
        var dataRows = table.find('tbody tr').length;
        var headerCols = table.find('thead tr:last th').length; // Get columns from last header row
        
        console.log('Table found with', headerCols, 'columns and', dataRows, 'data rows');
        
        // Check if the row is actually a "no data" message
        var firstRowText = '';
        if (dataRows > 0) {
            firstRowText = table.find('tbody tr:first td').text().trim();
        }
        var isNoDataRow = firstRowText.includes('No data') || 
                         firstRowText.includes('no data') || 
                         firstRowText.includes('Submit above') ||
                         firstRowText.includes('no employees');
        
        console.log('First row text:', firstRowText);
        console.log('Is no-data row:', isNoDataRow);
        
        // Only initialize DataTables if we have actual data
        if (dataRows > 0 && headerCols === 17 && !isNoDataRow) {
            try {
                table.DataTable({
                    "responsive": false,
                    "lengthChange": false,
                    "autoWidth": false,
                    "paging": false,
                    "searching": true,
                    "ordering": false,
                    "info": false,
                    "destroy": true,
                    "scrollX": true,
                    "columnDefs": [
                        { "orderable": false, "targets": '_all' },
                        { "searchable": true, "targets": [1, 2, 14] },
                        { "searchable": false, "targets": [0, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 15, 16] },
                        { "className": "text-center", "targets": [0, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 15, 16] },
                        { "className": "text-left", "targets": [1] }
                    ],
                    "dom": 't',
                    "language": {
                        "emptyTable": "No data available in table",
                        "search": "Search:",
                        "zeroRecords": "No matching records found"
                    }
                });
                console.log('DataTables initialized successfully');
            } catch (error) {
                console.error('DataTables initialization error:', error);
                // Fallback: just show the table without DataTables
                table.show();
            }
        } else {
            var reason = [];
            if (headerCols !== 17) reason.push('Wrong column count (' + headerCols + ')');
            if (isNoDataRow) reason.push('No actual data (placeholder row)');
            if (dataRows === 0) reason.push('No data rows');
            
            console.log('DataTables not initialized:', reason.join(', '));
        }
    } else {
        console.log('Table not found');
    }
});

// Add functionality to the print button - ensure DOM is ready
$(document).ready(function() {
    // Check if print button exists and attach event
    const printBtn = $('#print-table-btn');
    if (printBtn.length > 0) {
        console.log('Daily report print button found, attaching event');
        printBtn.on('click', function(e) {
            e.preventDefault();
            console.log('Daily report print button clicked - initiating print');
            window.print();
        });
    } else {
        console.log('Daily report print button not found in DOM');
    }
    
    // Also handle click using event delegation for dynamic content
    $(document).on('click', '#print-table-btn', function(e) {
        e.preventDefault();
        console.log('Daily report print button clicked via delegation');
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