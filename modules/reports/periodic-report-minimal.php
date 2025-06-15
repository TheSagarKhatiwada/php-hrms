<?php
session_start();
$page = 'periodic-report';
$home = './';

// Set up basic session for testing
if (!isset($_SESSION['user_id'])) {
    $_SESSION['user_id'] = 1;
    $_SESSION['role'] = 'admin';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Periodic Report - Minimal</title>
    <link rel="stylesheet" href="<?php echo $home; ?>plugins/fontawesome-free/css/all.min.css">
    <link rel="stylesheet" href="<?php echo $home; ?>dist/css/adminlte.min.css">
    <link rel="stylesheet" href="<?php echo $home; ?>plugins/datatables-bs4/css/dataTables.bootstrap4.min.css">
    <link rel="stylesheet" href="<?php echo $home; ?>plugins/daterangepicker/daterangepicker.css">
</head>
<body class="hold-transition sidebar-mini">
<div class="wrapper">
    <!-- Minimal navbar -->
    <nav class="main-header navbar navbar-expand navbar-white navbar-light">
        <ul class="navbar-nav">
            <li class="nav-item">
                <a class="nav-link" data-widget="pushmenu" href="#" role="button"><i class="fas fa-bars"></i></a>
            </li>
        </ul>
    </nav>

    <!-- Minimal sidebar -->
    <aside class="main-sidebar sidebar-dark-primary elevation-4">
        <div class="sidebar">
            <nav class="mt-2">
                <ul class="nav nav-pills nav-sidebar flex-column">
                    <li class="nav-item">
                        <a href="#" class="nav-link active">
                            <i class="nav-icon fas fa-chart-bar"></i>
                            <p>Periodic Report</p>
                        </a>
                    </li>
                </ul>
            </nav>
        </div>
    </aside>

    <!-- Content Wrapper -->
    <div class="content-wrapper">
        <!-- Content Header -->
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
                    <div class="card-header">
                        <form action="api/fetch-periodic-report-data.php" method="POST" id="periodic-report-form">
                            <div class="row">
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label for="reportDateRange">Date Range <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" id="reportDateRange" name="reportDateRange" required>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label for="empBranch">Branch <span class="text-danger">*</span></label>
                                        <select class="form-control" id="empBranch" name="empBranch" required>
                                            <option value="">All Branches</option>
                                            <option value="1">Main Branch</option>
                                            <option value="2">Secondary Branch</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-filter mr-1"></i> Generate Report
                                    </button>
                                </div>
                                <div class="col-md-3">
                                    <button type="button" id="print-report-btn" class="btn btn-success">
                                        <i class="fas fa-print mr-1"></i> Print
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>

                    <div class="card-body">
                        <!-- Sample table to test structure -->
                        <div class="card employee-card mb-4">
                            <div class="card-body">
                                <table class="table table-sm table-bordered table-striped periodic-report-table">
                                    <thead>
                                        <tr>
                                            <th class="text-center" colspan="16">Periodic Attendance Report: May 2025</th>
                                        </tr>
                                        <tr>
                                            <th class="text-center" colspan="2">Emp. ID: EMP001</th>
                                            <th class="text-left" colspan="3">Name: John Doe</th>
                                            <th class="text-center" colspan="4">Designation: Manager</th>
                                            <th class="text-center" colspan="4">Branch: Main</th>
                                            <th class="text-center" colspan="3">Department: IT</th>
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
                                        <tr>
                                            <td class="text-center">1</td>
                                            <td class="text-left">2025-05-01, Thursday</td>
                                            <td class="text-center">09:00</td>
                                            <td class="text-center">17:00</td>
                                            <td class="text-center">8:00</td>
                                            <td class="text-center">09:15</td>
                                            <td class="text-center">17:30</td>
                                            <td class="text-center">8:15</td>
                                            <td class="text-center">0:30</td>
                                            <td class="text-center">0:15</td>
                                            <td class="text-center">-</td>
                                            <td class="text-center">-</td>
                                            <td class="text-center">0:30</td>
                                            <td class="text-center">Present</td>
                                            <td class="text-center">A | A</td>
                                            <td class="text-center">-</td>
                                        </tr>
                                    </tbody>
                                    <tfoot>
                                        <tr>
                                            <th class="text-right" colspan="1">Summary:</th>
                                            <th class="text-center" colspan="2">Present: 1</th>
                                            <th class="text-center" colspan="2">Absent: 0</th>
                                            <th class="text-center" colspan="2">Weekend: 0</th>
                                            <th class="text-center" colspan="2">Holiday: 0</th>
                                            <th class="text-center" colspan="2">Paid Leave: 0</th>
                                            <th class="text-center" colspan="2">Unpaid Leave: 0</th>
                                            <th class="text-center" colspan="1">Missed: 0</th>
                                            <th class="text-center" colspan="2">Manual: 0</th>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </div>
</div>

<!-- Scripts -->
<script src="<?php echo $home; ?>plugins/jquery/jquery.min.js"></script>
<script src="<?php echo $home; ?>plugins/bootstrap/js/bootstrap.bundle.min.js"></script>
<script src="<?php echo $home; ?>dist/js/adminlte.min.js"></script>
<script src="<?php echo $home; ?>plugins/moment/moment.min.js"></script>
<script src="<?php echo $home; ?>plugins/daterangepicker/daterangepicker.js"></script>
<script src="<?php echo $home; ?>plugins/datatables/jquery.dataTables.min.js"></script>
<script src="<?php echo $home; ?>plugins/datatables-bs4/js/dataTables.bootstrap4.min.js"></script>

<script>
$(document).ready(function() {
    // Initialize daterangepicker
    $('#reportDateRange').daterangepicker({
        locale: {
            format: 'DD/MM/YYYY'
        },
        startDate: moment().subtract(1, 'months').startOf('month'),
        endDate: moment().subtract(1, 'months').endOf('month'),
        maxDate: moment()
    });
    
    // Initialize DataTable
    $('.periodic-report-table').DataTable({
        "responsive": true,
        "lengthChange": false,
        "autoWidth": true,
        "paging": false,
        "searching": true,
        "ordering": false,
        "info": false
    });
    
    // Print button functionality
    $('#print-report-btn').on('click', function(e) {
        e.preventDefault();
        console.log('Print button clicked');
        window.print();
    });
    
    console.log('Periodic report loaded successfully');
});
</script>

</body>
</html>
