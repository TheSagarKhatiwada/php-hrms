<?php
// Start the session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Debug: Log session values to error_log
error_log("Regular Dashboard - Session Debug - user_id: " . (isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 'not set'));
error_log("Regular Dashboard - Session Debug - user_role: " . (isset($_SESSION['user_role']) ? $_SESSION['user_role'] : 'not set'));

$page = 'User Dashboard';
require_once __DIR__ . '/includes/header.php';
include 'includes/db_connection.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    // User not logged in, redirect to login page
    header('Location: index.php');
    exit();
} elseif (isset($_SESSION['user_role']) && $_SESSION['user_role'] == '1') {
    // If admin user somehow lands here, redirect to admin dashboard
    header('Location: admin-dashboard.php');
    exit();
}

// Get current date
$today = date('Y-m-d');

// Get current user data
$userId = $_SESSION['user_id'];
try {
    $stmt = $pdo->prepare("SELECT * FROM employees WHERE id = ?");
    $stmt->execute([$userId]);
    $userData = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Get current user's attendance for today
    $stmt = $pdo->prepare("SELECT * FROM attendance_logs WHERE emp_Id = ? AND date = ? ORDER BY time DESC LIMIT 1");
    $stmt->execute([$userData['emp_id'], $today]);
    $todayAttendance = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Get attendance for last 7 days
    $stmt = $pdo->prepare("
        SELECT date, MIN(time) as clock_in, MAX(time) as clock_out
        FROM attendance_logs 
        WHERE emp_Id = ? 
        AND date >= DATE_SUB(CURRENT_DATE, INTERVAL 7 DAY)
        GROUP BY date
        ORDER BY date DESC
    ");
    $stmt->execute([$userData['emp_id']]);
    $recentAttendance = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    error_log("Database error: " . $e->getMessage());
    $_SESSION['error'] = "Error retrieving user data";
    $userData = [];
    $todayAttendance = [];
    $recentAttendance = [];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>User Dashboard | HR Management System</title>
  
  <!-- DataTables -->
  <link rel="stylesheet" href="plugins/datatables-bs4/css/dataTables.bootstrap4.min.css">
  <link rel="stylesheet" href="plugins/datatables-responsive/css/responsive.bootstrap4.min.css">
  <link rel="stylesheet" href="plugins/datatables-buttons/css/buttons.bootstrap4.min.css">
  
  <!-- Dashboard custom styles -->
  <style>
    /* Base styles for modern info boxes */
    .modern-info-box {
      border-radius: 10px;
      transition: transform 0.3s, box-shadow 0.3s;
      overflow: hidden;
      box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
    }
    
    .modern-info-box:hover {
      transform: translateY(-5px);
      box-shadow: 0 8px 15px rgba(0, 0, 0, 0.2);
    }
    
    .bg-gradient-info {
      background: linear-gradient(45deg, #17a2b8, #00c9ff) !important;
    }
    
    .bg-gradient-primary {
      background: linear-gradient(45deg, #007bff, #00c6ff) !important;
    }
    
    .bg-gradient-success {
      background: linear-gradient(45deg, #28a745, #48d368) !important;
    }
    
    .welcome-section {
      background: linear-gradient(120deg, #2b4b6f, #1e7ba5);
      color: white;
      border-radius: 10px;
      padding: 20px;
      margin-bottom: 20px;
    }
    
    .welcome-section h2 {
      font-weight: 300;
      margin-bottom: 5px;
    }
    
    .status-dot {
      width: 10px;
      height: 10px;
      border-radius: 50%;
      margin-right: 8px;
    }
    
    .status-active {
      background-color: #28a745;
    }
    
    .time-refresh {
      animation: fadeInOut 2s infinite;
    }
    
    @keyframes fadeInOut {
      0% { opacity: 0.5; }
      50% { opacity: 1; }
      100% { opacity: 0.5; }
    }
    
    .modern-card {
      border-radius: 10px;
      box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
      overflow: hidden;
      transition: transform 0.3s, box-shadow 0.3s;
      margin-bottom: 20px;
    }
    
    .modern-card:hover {
      transform: translateY(-5px);
      box-shadow: 0 8px 25px rgba(0, 0, 0, 0.2);
    }
    
    .clock-card {
      text-align: center;
      padding: 20px;
    }
    
    .clock-display {
      font-size: 3rem;
      font-weight: 600;
      margin: 10px 0;
    }
    
    .attendance-status {
      display: inline-block;
      padding: 8px 15px;
      border-radius: 20px;
      font-weight: 500;
      margin-top: 10px;
    }
    
    .status-present {
      background-color: rgba(40, 167, 69, 0.15);
      color: #28a745;
    }
    
    .status-absent {
      background-color: rgba(220, 53, 69, 0.15);
      color: #dc3545;
    }
  </style>
</head>
<body class="hold-transition sidebar-mini layout-footer-fixed layout-navbar-fixed layout-fixed dark-mode">
<div class="wrapper">
  <?php 
    include 'includes/topbar.php';
    include 'includes/sidebar.php';
  ?>

  <!-- Content Wrapper. Contains page content -->
  <div class="content-wrapper">
    <!-- Main content -->
    <section class="content">
      <div class="container-fluid">
        <!-- Welcome Section -->
        <div class="welcome-section mb-4 mt-3">
          <div class="row">
            <div class="col-md-8">
              <h2>Welcome, <?php echo $userData['first_name']; ?>!</h2>
              <p><?php echo date('l, F j, Y'); ?> • <span class="time-refresh" id="live-time">Loading time...</span></p>
            </div>
            <div class="col-md-4 text-right">
              <div class="attendance-status <?php echo isset($todayAttendance) ? 'status-present' : 'status-absent'; ?>">
                <?php echo isset($todayAttendance) ? 'Present Today' : 'Not Checked In'; ?>
              </div>
            </div>
          </div>
        </div>
        
        <!-- Main content row -->
        <div class="row">
          <!-- Left column -->
          <div class="col-lg-8">
            <!-- Clock In/Out Card -->
            <div class="card modern-card">
              <div class="card-header">
                <h3 class="card-title">Today's Attendance</h3>
              </div>
              <div class="card-body">
                <div class="row">
                  <div class="col-md-7 clock-card">
                    <h5>Current Time</h5>
                    <div class="clock-display" id="digital-clock">00:00:00</div>
                    <div class="mt-3">
                      <?php if (!isset($todayAttendance)): ?>
                        <button id="clockInBtn" class="btn btn-success btn-lg">
                          <i class="fas fa-sign-in-alt mr-2"></i> Clock In
                        </button>
                      <?php else: ?>
                        <div class="text-success mb-3">
                          <i class="fas fa-check-circle mr-1"></i>
                          You clocked in at <?php echo date('h:i A', strtotime($todayAttendance['time'])); ?>
                        </div>
                        <button id="clockOutBtn" class="btn btn-primary btn-lg">
                          <i class="fas fa-sign-out-alt mr-2"></i> Clock Out
                        </button>
                      <?php endif; ?>
                    </div>
                  </div>
                  <div class="col-md-5">
                    <div class="info-box modern-info-box mb-3">
                      <span class="info-box-icon bg-gradient-info"><i class="far fa-calendar-check"></i></span>
                      <div class="info-box-content">
                        <span class="info-box-text">Last 7 Days</span>
                        <span class="info-box-number"><?php echo count($recentAttendance); ?> days</span>
                        <div class="progress">
                          <div class="progress-bar" style="width: <?php echo (count($recentAttendance)/7) * 100; ?>%"></div>
                        </div>
                      </div>
                    </div>
                    <div class="info-box modern-info-box">
                      <span class="info-box-icon bg-gradient-success"><i class="fas fa-business-time"></i></span>
                      <div class="info-box-content">
                        <span class="info-box-text">Your Role</span>
                        <span class="info-box-number"><?php echo $userData['designation']; ?></span>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
            
            <!-- Recent Attendance Table -->
            <div class="card modern-card">
              <div class="card-header">
                <h3 class="card-title">Your Recent Attendance</h3>
              </div>
              <div class="card-body">
                <table class="table table-bordered table-striped">
                  <thead>
                    <tr>
                      <th>Date</th>
                      <th>Clock In</th>
                      <th>Clock Out</th>
                      <th>Duration</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach($recentAttendance as $record): ?>
                      <tr>
                        <td><?php echo date('D, M d', strtotime($record['date'])); ?></td>
                        <td><?php echo date('h:i A', strtotime($record['clock_in'])); ?></td>
                        <td>
                          <?php 
                            if($record['clock_out'] && $record['clock_out'] != $record['clock_in']) {
                              echo date('h:i A', strtotime($record['clock_out']));
                            } else {
                              echo '<span class="badge badge-warning">No checkout</span>';
                            }
                          ?>
                        </td>
                        <td>
                          <?php 
                            if($record['clock_out'] && $record['clock_out'] != $record['clock_in']) {
                              $in = strtotime($record['clock_in']);
                              $out = strtotime($record['clock_out']);
                              $hours = round(($out - $in) / 3600, 2);
                              echo $hours . ' hrs';
                            } else {
                              echo '-';
                            }
                          ?>
                        </td>
                      </tr>
                    <?php endforeach; ?>
                    <?php if(empty($recentAttendance)): ?>
                      <tr>
                        <td colspan="4" class="text-center">No attendance records found for the last 7 days</td>
                      </tr>
                    <?php endif; ?>
                  </tbody>
                </table>
              </div>
            </div>
          </div>
          
          <!-- Right column -->
          <div class="col-lg-4">
            <!-- User Profile Card -->
            <div class="card modern-card">
              <div class="card-body box-profile">
                <div class="text-center">
                  <img class="profile-user-img img-fluid img-circle" 
                      src="<?php echo $userData['user_image'] ?? 'dist/img/default-avatar.png'; ?>" 
                      alt="User profile picture">
                </div>
                <h3 class="profile-username text-center">
                  <?php echo $userData['first_name'] . ' ' . $userData['last_name']; ?>
                </h3>
                <p class="text-muted text-center"><?php echo $userData['designation']; ?></p>
                <ul class="list-group list-group-unbordered mb-3">
                  <li class="list-group-item">
                    <b>Email</b> <a class="float-right"><?php echo $userData['email']; ?></a>
                  </li>
                  <li class="list-group-item">
                    <b>Employee ID</b> <a class="float-right"><?php echo $userData['emp_id']; ?></a>
                  </li>
                  <li class="list-group-item">
                    <b>Department</b>
                    <a class="float-right">
                      <?php 
                        try {
                          $stmt = $pdo->prepare("SELECT departments.dept_name FROM departments 
                                                JOIN employees ON departments.id = employees.department 
                                                WHERE employees.id = ?");
                          $stmt->execute([$userId]);
                          $dept = $stmt->fetchColumn();
                          echo $dept ?? 'Not assigned';
                        } catch (PDOException $e) {
                          echo 'Not available';
                        }
                      ?>
                    </a>
                  </li>
                </ul>
                <a href="profile.php" class="btn btn-primary btn-block">
                  <b>View Full Profile</b>
                </a>
              </div>
            </div>
            
            <!-- Quick Links Card -->
            <div class="card modern-card">
              <div class="card-header">
                <h3 class="card-title">Quick Links</h3>
              </div>
              <div class="card-body p-0">
                <ul class="list-group list-group-flush">
                  <li class="list-group-item">
                    <a href="attendance.php" class="d-flex align-items-center">
                      <i class="fas fa-calendar-alt mr-2 text-primary"></i> View My Attendance
                    </a>
                  </li>
                  <li class="list-group-item">
                    <a href="profile.php" class="d-flex align-items-center">
                      <i class="fas fa-user-edit mr-2 text-success"></i> Update My Profile
                    </a>
                  </li>
                  <li class="list-group-item">
                    <a href="#" class="d-flex align-items-center" data-toggle="modal" data-target="#leaveRequestModal">
                      <i class="fas fa-calendar-minus mr-2 text-danger"></i> Request Leave
                    </a>
                  </li>
                  <li class="list-group-item">
                    <a href="#" class="d-flex align-items-center">
                      <i class="fas fa-file-alt mr-2 text-warning"></i> Download Pay Slip
                    </a>
                  </li>
                </ul>
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
<script src="plugins/jquery/jquery.min.js"></script>
<!-- Bootstrap -->
<script src="plugins/bootstrap/js/bootstrap.bundle.min.js"></script>
<!-- AdminLTE -->
<script src="dist/js/adminlte.js"></script>

<script>
$(document).ready(function() {
    // Live digital clock function
    function updateClock() {
        const now = new Date();
        let hours = now.getHours();
        const ampm = hours >= 12 ? 'PM' : 'AM';
        hours = hours % 12;
        hours = hours ? hours : 12; // the hour '0' should be '12'
        const minutes = now.getMinutes().toString().padStart(2, '0');
        const seconds = now.getSeconds().toString().padStart(2, '0');
        const timeString = hours + ':' + minutes + ':' + seconds + ' ' + ampm;
        
        $('#digital-clock').text(timeString);
        $('#live-time').text(timeString);
    }
    
    // Initial call and then update every second
    updateClock();
    setInterval(updateClock, 1000);
    
    // Handle clock in button click
    $('#clockInBtn').click(function() {
        // You would implement an AJAX call to record attendance
        alert('Clock in functionality would be implemented here');
        // After successful clock in, reload the page
        // window.location.reload();
    });
    
    // Handle clock out button click
    $('#clockOutBtn').click(function() {
        // You would implement an AJAX call to update attendance
        alert('Clock out functionality would be implemented here');
        // After successful clock out, reload the page
        // window.location.reload();
    });
});
</script>

</body>
</html>