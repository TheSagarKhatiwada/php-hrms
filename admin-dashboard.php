<?php 
$page = 'Admin Dashboard';
include 'includes/header.php';
include 'includes/db_connection.php';

// Check if user is logged in and has admin role
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] != '1') {
    header('Location: index.php');
    exit();
}

// Get current date
$today = date('Y-m-d');

// Get attendance statistics
try {
    // Total employees
    $stmt = $pdo->query("SELECT COUNT(*) FROM employees WHERE exit_date IS NULL");
    $totalEmployees = $stmt->fetchColumn();
    
    // Total branches
    $stmt = $pdo->query("SELECT COUNT(*) FROM branches");
    $totalBranches = $stmt->fetchColumn();
    
    // Total departments
    $stmt = $pdo->query("SELECT COUNT(*) FROM departments");
    $totalDepartments = $stmt->fetchColumn();
    
    // Present today
    $stmt = $pdo->prepare("SELECT COUNT(DISTINCT emp_Id) FROM attendance_logs WHERE date = ?");
    $stmt->execute([$today]);
    $presentToday = $stmt->fetchColumn();
    
    // Absent today (total employees - present)
    $absentToday = $totalEmployees - $presentToday;
    
    // Get recent attendance records
    $stmt = $pdo->query("
        SELECT a.*, e.first_name, e.last_name, e.middle_name, e.user_image, e.designation, b.name as branch_name 
        FROM attendance_logs a
        JOIN employees e ON a.emp_Id = e.emp_id
        LEFT JOIN branches b ON e.branch = b.id
        ORDER BY a.date DESC, a.time DESC
        LIMIT 15
    ");
    $recentAttendance = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $_SESSION['error'] = "Error fetching dashboard data: " . $e->getMessage();
    $totalEmployees = $totalBranches = $totalDepartments = $presentToday = $absentToday = 0;
    $recentAttendance = [];
}
?>
  <!-- DataTables -->
  <link rel="stylesheet" href="<?php echo $home;?>plugins/datatables-bs4/css/dataTables.bootstrap4.min.css">
  <link rel="stylesheet" href="<?php echo $home;?>plugins/datatables-responsive/css/responsive.bootstrap4.min.css">
  <link rel="stylesheet" href="<?php echo $home;?>plugins/datatables-buttons/css/buttons.bootstrap4.min.css">
  
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
            <h1 class="m-0">Admin Dashboard</h1>
          </div>
          <div class="col-sm-6">
            <ol class="breadcrumb float-sm-right">
              <li class="breadcrumb-item"><a href="index.php">Home</a></li>
              <li class="breadcrumb-item active">Admin Dashboard</li>
            </ol>
          </div>
        </div>
      </div>
    </div>
    
    <!-- Main content -->
    <section class="content">
      <div class="container-fluid">
        <!-- Info boxes -->
        <div class="row">
          <div class="col-12 col-sm-6 col-md-3">
            <div class="info-box">
              <span class="info-box-icon bg-info elevation-1"><i class="fas fa-users"></i></span>
              <div class="info-box-content">
                <span class="info-box-text">Total Employees</span>
                <span class="info-box-number"><?php echo $totalEmployees; ?></span>
              </div>
            </div>
          </div>
          <div class="col-12 col-sm-6 col-md-3">
            <div class="info-box">
              <span class="info-box-icon bg-primary elevation-1"><i class="fas fa-building"></i></span>
              <div class="info-box-content">
                <span class="info-box-text">Total Branches</span>
                <span class="info-box-number"><?php echo $totalBranches; ?></span>
              </div>
            </div>
          </div>
          <div class="col-12 col-sm-6 col-md-3">
            <div class="info-box">
              <span class="info-box-icon bg-secondary elevation-1"><i class="fas fa-sitemap"></i></span>
              <div class="info-box-content">
                <span class="info-box-text">Total Departments</span>
                <span class="info-box-number"><?php echo $totalDepartments; ?></span>
              </div>
            </div>
          </div>
          <div class="col-12 col-sm-6 col-md-3">
            <div class="info-box">
              <span class="info-box-icon bg-success elevation-1"><i class="fas fa-user-check"></i></span>
              <div class="info-box-content">
                <span class="info-box-text">Present Today</span>
                <span class="info-box-number"><?php echo $presentToday; ?></span>
              </div>
            </div>
          </div>
          <div class="col-12 col-sm-6 col-md-3">
            <div class="info-box">
              <span class="info-box-icon bg-danger elevation-1"><i class="fas fa-user-times"></i></span>
              <div class="info-box-content">
                <span class="info-box-text">Absent Today</span>
                <span class="info-box-number"><?php echo $absentToday; ?></span>
              </div>
            </div>
          </div>
          <div class="col-12 col-sm-6 col-md-3">
            <div class="info-box">
              <span class="info-box-icon bg-warning elevation-1"><i class="fas fa-clock"></i></span>
              <div class="info-box-content">
                <span class="info-box-text">Attendance Rate</span>
                <span class="info-box-number"><?php echo $totalEmployees > 0 ? round(($presentToday/$totalEmployees) * 100, 1) : 0; ?>%</span>
              </div>
            </div>
          </div>
        </div>
        
        <div class="row">
          <div class="col-lg-8">
            <!-- Recent Attendance Card -->
            <div class="card">
              <div class="card-header">
                <h3 class="card-title">Recent Attendance</h3>
                <div class="card-tools">
                  <a href="attendance.php" class="btn btn-primary btn-sm">
                    <i class="fas fa-list"></i> View All
                  </a>
                </div>
              </div>
              <div class="card-body">
                <div class="table-responsive">
                  <table class="table table-bordered table-striped table-sm" id="attendanceTable">
                    <thead>
                      <tr>
                        <th>Employee</th>
                        <th>Branch</th>
                        <th>Date</th>
                        <th>Time</th>
                        <th>Method</th>
                      </tr>
                    </thead>
                    <tbody>
                      <?php
                      if (empty($recentAttendance)) {
                          echo "<tr><td colspan='6' class='text-center'>No attendance records found</td></tr>";
                      } else {
                          foreach ($recentAttendance as $record) {
                              // Format date and time
                              $date = date('M d, Y', strtotime($record['date']));
                              $time = date('h:i A', strtotime($record['time']));
                              
                              // Get employee image or default
                              $employeeImage = $record['user_image'];
                              
                              // Determine method badge color
                              $methodClass = $record['method'] == 1 ? 'badge-warning' : 'badge-success';
                              $methodText = $record['method'] == 1 ? 'Manual' : 'Auto';
                              
                              echo "<tr>
                                      <td>
                                          <div class='d-flex align-items-center'>
                                              <img src='{$employeeImage}' class='img-circle mr-2' style='width: 30px; height: 30px; object-fit: cover;'>
                                              <div>
                                                  <div class='font-weight-bold'>{$record['first_name']} {$record['middle_name']} {$record['last_name']}</div>
                                                  <small class='text-muted'>{$record['designation']}</small>
                                              </div>
                                          </div>
                                      </td>
                                      <td>{$record['branch_name']}</td>
                                      <td>{$date}</td>
                                      <td>{$time}</td>
                                      <td><span class='badge {$methodClass}'>{$methodText}</span></td>
                                  </tr>";
                          }
                      }
                      ?>
                    </tbody>
                  </table>
                </div>
              </div>
            </div>
          </div>
          
          <div class="col-lg-4">
            <!-- Attendance Chart Card -->
            <div class="card">
              <div class="card-header">
                <h3 class="card-title">Attendance Overview</h3>
              </div>
              <div class="card-body">
                <canvas id="attendanceChart" style="min-height: 250px; height: 250px; max-height: 250px; max-width: 100%;"></canvas>
              </div>
            </div>
            
            <!-- Quick Actions Card -->
            <div class="card">
              <div class="card-header">
                <h3 class="card-title">Quick Actions</h3>
              </div>
              <div class="card-body">
                <div class="row">
                  <div class="col-6">
                    <a href="add-employee.php" class="btn btn-primary btn-block mb-2">
                      <i class="fas fa-user-plus"></i> Add Employee
                    </a>
                  </div>
                  <div class="col-6">
                    <a href="attendance.php" class="btn btn-success btn-block mb-2">
                      <i class="fas fa-clock"></i> Record Attendance
                    </a>
                  </div>
                  <div class="col-6">
                    <a href="daily-report.php" class="btn btn-info btn-block mb-2">
                      <i class="fas fa-file-alt"></i> Daily Report
                    </a>
                  </div>
                  <div class="col-6">
                    <a href="monthly-report.php" class="btn btn-warning btn-block mb-2">
                      <i class="fas fa-calendar"></i> Monthly Report
                    </a>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </section>
  </div>

  <?php include 'includes/footer.php'; ?>
</div>

<!-- REQUIRED SCRIPTS -->
<!-- jQuery -->
<script src="<?php echo $home;?>plugins/jquery/jquery.min.js"></script>
<!-- Bootstrap -->
<script src="<?php echo $home;?>plugins/bootstrap/js/bootstrap.bundle.min.js"></script>
<!-- DataTables & Plugins -->
<script src="<?php echo $home;?>plugins/datatables/jquery.dataTables.min.js"></script>
<script src="<?php echo $home;?>plugins/datatables-bs4/js/dataTables.bootstrap4.min.js"></script>
<script src="<?php echo $home;?>plugins/datatables-responsive/js/dataTables.responsive.min.js"></script>
<script src="<?php echo $home;?>plugins/datatables-responsive/js/responsive.bootstrap4.min.js"></script>
<!-- ChartJS -->
<script src="<?php echo $home;?>plugins/chart.js/Chart.min.js"></script>
<!-- AdminLTE -->
<script src="<?php echo $home;?>dist/js/adminlte.js"></script>

<script>
$(document).ready(function() {
    // Initialize DataTable
    $('#attendanceTable').DataTable({
        "responsive": true,
        "lengthChange": true,
        "autoWidth": false,
        "pageLength": 5,
        "lengthMenu": [[5, 10, 15, -1], [5, 10, 15, "All"]]
    });
    
    // Attendance Chart
    var ctx = document.getElementById('attendanceChart').getContext('2d');
    var attendanceChart = new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: ['Present', 'Absent'],
            datasets: [{
                data: [<?php echo $presentToday; ?>, <?php echo $absentToday; ?>],
                backgroundColor: ['#28a745', '#dc3545'],
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            legend: {
                position: 'bottom'
            }
        }
    });
});
</script>

</body>
</html>