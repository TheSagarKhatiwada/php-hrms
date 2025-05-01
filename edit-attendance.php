<?php
ob_start(); // Start output buffering
$page = 'attendance';
require_once __DIR__ . '/includes/header.php';
include 'includes/db_connection.php';

// Check if ID is provided
if (!isset($_GET['id'])) {
    header('Location: attendance.php');
    exit();
}

$id = $_GET['id'];

// Fetch the attendance record
try {
    $stmt = $pdo->prepare("SELECT a.*, e.first_name, e.last_name, e.middle_name, e.branch, e.emp_id, e.designation, e.user_image, b.name as branch_name 
                          FROM attendance_logs a 
                          INNER JOIN employees e ON a.emp_Id = e.emp_id 
                          INNER JOIN branches b ON e.branch = b.id 
                          WHERE a.id = ?");
    $stmt->execute([$id]);
    $record = $stmt->fetch();

    if (!$record) {
        $_SESSION['error'] = "Attendance record not found.";
        header('Location: attendance.php');
        exit();
    }
} catch (PDOException $e) {
    $_SESSION['error'] = "Error fetching attendance data: " . $e->getMessage();
    header('Location: attendance.php');
    exit();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $date = $_POST['attendanceDate'];
        $time = $_POST['attendanceTime'];
        $reason = $_POST['reason'];
        $remarks = $_POST['remarks'];

        $stmt = $pdo->prepare("UPDATE attendance_logs SET date = ?, time = ?, manual_reason = ?, remarks = ? WHERE id = ?");
        $stmt->execute([$date, $time, $reason, $remarks, $id]);

        $_SESSION['success'] = "Attendance record updated successfully.";
        header('Location: attendance.php');
        exit();
    } catch (PDOException $e) {
        $_SESSION['error'] = "Error updating attendance record: " . $e->getMessage();
        header('Location: attendance.php');
        exit();
    }
}
?>

<link rel="stylesheet" href="<?php echo $home;?>plugins/select2/css/select2.min.css">
<link rel="stylesheet" href="<?php echo $home;?>plugins/select2-bootstrap4-theme/select2-bootstrap4.min.css">
<link rel="stylesheet" href="<?php echo $home;?>plugins/tempusdominus-bootstrap-4/css/tempusdominus-bootstrap-4.min.css">

</head>
<body class="hold-transition sidebar-mini layout-footer-fixed layout-navbar-fixed layout-fixed dark-mode">
<div class="wrapper">
  <?php 
    include 'includes/topbar.php';
    include 'includes/sidebar.php';
  ?>

  <!-- Content Wrapper -->
  <div class="content-wrapper">
    <!-- Content Header -->
    <div class="content-header">
      <div class="container-fluid">
        <div class="row mb-2">
          <div class="col-sm-6">
            <h1 class="m-0">Edit Attendance</h1>
          </div>
          <div class="col-sm-6">
            <ol class="breadcrumb float-sm-right">
              <li class="breadcrumb-item"><a href="#">Home</a></li>
              <li class="breadcrumb-item"><a href="attendance.php">Attendance</a></li>
              <li class="breadcrumb-item active">Edit</li>
            </ol>
          </div>
        </div>
      </div>
    </div>

    <!-- Main content -->
    <section class="content">
      <div class="container-fluid">
        <div class="card">
          <div class="card-body">
            <form method="POST">
              <!-- Employee Details Section -->
              <div class="row mb-4">
                <div class="col-md-12">
                  <div class="d-flex align-items-center">
                    <div class="employee-photo">
                      <img id="edit_emp_image" src="<?php echo htmlspecialchars($record['user_image']); ?>" alt="Employee Image" style="width: 80px; height: 80px; border-radius: 10px; object-fit: cover;">
                    </div>
                    <div class="ml-3">
                      <h5 id="edit_emp_name" class="mb-1">
                      <b><?php echo htmlspecialchars($record['first_name'] . ' ' . $record['middle_name'] . ' ' . $record['last_name']); ?></b>
                      </h5>
                      <p id="edit_emp_designation" class="mb-1 text-muted"><?php echo htmlspecialchars($record['designation']); ?></p>
                      <p id="edit_emp_branch" class="mb-0 text-muted"><?php echo htmlspecialchars($record['branch_name']); ?></p>
                    </div>
                  </div>
                </div>
              </div>
              <hr>
              <!-- Attendance Details Section -->
              <div class="row mb-6">
                <div class="col-md-6">
                  <div class="form-group">
                    <label>Attendance Date</label>
                    <input type="date" class="form-control" name="attendanceDate" value="<?php echo htmlspecialchars($record['date']); ?>" required>
                  </div>

              <div class="form-group">
                <label>Attendance Time</label>
                <input type="time" class="form-control" name="attendanceTime" value="<?php echo htmlspecialchars($record['time']); ?>" required>
              </div>

              <div class="form-group">
                <label>Reason</label>
                <select class="form-control" name="reason" required>
                  <option value="Card Forgot" <?php echo ($record['manual_reason'] == 'Card Forgot') ? 'selected' : ''; ?>>Card Forgot</option>
                  <option value="Card Lost" <?php echo ($record['manual_reason'] == 'Card Lost') ? 'selected' : ''; ?>>Card Lost</option>
                  <option value="Forgot to Punch" <?php echo ($record['manual_reason'] == 'Forgot to Punch') ? 'selected' : ''; ?>>Forgot to Punch</option>
                  <option value="Office Work Delay" <?php echo ($record['manual_reason'] == 'Office Work Delay') ? 'selected' : ''; ?>>Office Work Delay</option>
                  <option value="Field Visit" <?php echo ($record['manual_reason'] == 'Field Visit') ? 'selected' : ''; ?>>Field Visit</option>
                </select>
              </div>

              <div class="form-group">
                <label>Remarks</label>
                <input type="text" class="form-control" name="remarks" value="<?php echo htmlspecialchars($record['remarks'] ?? ''); ?>">
              </div>
              
              <div class="form-group mt-4">
                <button type="submit" class="btn btn-primary">Update Attendance</button>
                <a href="attendance.php" class="btn btn-secondary ml-2">Cancel</a>
              </div>
              </div>
              </div>
            </form>
          </div>
        </div>
      </div>
    </section>
  </div>

  <?php include 'includes/footer.php'; ?>
</div>

<!-- REQUIRED SCRIPTS -->
<script src="<?php echo $home;?>plugins/jquery/jquery.min.js"></script>
<script src="<?php echo $home;?>plugins/bootstrap/js/bootstrap.bundle.min.js"></script>
<script src="<?php echo $home;?>plugins/select2/js/select2.full.min.js"></script>
<script src="<?php echo $home;?>plugins/moment/moment.min.js"></script>
<script src="<?php echo $home;?>plugins/tempusdominus-bootstrap-4/js/tempusdominus-bootstrap-4.min.js"></script>
<script src="<?php echo $home;?>dist/js/adminlte.js"></script>

<script>
$(function () {
    $('.select2').select2();
});
</script>

</body>
</html>