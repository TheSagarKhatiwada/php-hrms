<?php
$page = 'employee';
$accessRole = isset($_SESSION['role']) ? $_SESSION['role'] : null;
if ($accessRole === '0') {
    header('Location: dashboard.php');
    exit();
}
include 'includes/header.php';
include 'includes/db_connection.php';

// Get empId from the query parameter
$empId = $_GET['empId'] ?? '';

// Fetch employee details from the database
if ($empId) {
    $stmt = $pdo->prepare("SELECT e.*, b.name AS branch_name FROM employees e INNER JOIN branches b ON e.branch = b.id WHERE e.emp_id = :empId");
    $stmt->execute([':empId' => $empId]);
    $employee = $stmt->fetch();

    if (!$employee) {
        echo "<p>Employee not found.</p>";
        exit();
    }
} else {
    // Redirect back to the Employees table
    header("Location: employees.php");
    exit();  // Make sure to stop the script after redirection
}
?>

<!-- Bootstrap CSS -->
<link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">

</head>
<body class="hold-transition sidebar-mini layout-footer-fixed layout-navbar-fixed layout-fixed dark-mode sidebar-collapse">
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
            <h1 class="m-0">Employee Details</h1>
          </div><!-- /.col -->
          <div class="col-sm-6">
            <ol class="breadcrumb float-sm-right">
              <li class="breadcrumb-item"><a href="#">Home</a></li>
              <li class="breadcrumb-item active">Employee</li>
            </ol>
          </div><!-- /.col -->
        </div><!-- /.row -->
      </div><!-- /.container-fluid -->
    </div>
    <!-- /.content-header -->

    <!-- Main content -->
    <section class="content">
     <div class="container-fluid">
     <div class="card">
              <div class="card-header">

              <!-- </div>/.card-header -->
              <div class="card-body">
                <div class="row">
                    <div class="header h3 mb-3" style="text-decoration: underline;">
                    Basic Details
                    </div>
                </div>
                <div class="row">
                  <div class="col-md-4">
                    <div class="form-group">
                      <input type="text" class="form-control" id="empId" value="<?php echo htmlspecialchars($employee['emp_id']); ?>" disabled>
                      <label for="empId" class="form-label">Employee ID</label> 
                    </div>
                    <div class="form-group">
                      <input type="text" class="form-control" id="empFullName" value="<?php echo htmlspecialchars($employee['first_name']) . ($employee['middle_name'] ? ' ' . htmlspecialchars($employee['middle_name']) : '') . ' ' . htmlspecialchars($employee['last_name']); ?>" disabled>
                      <label for="empFullName" class="form-label">Employee Full Name</label>
                    </div>
                    <div class="form-group">
                      <input type="text" class="form-control" id="gender" value="<?php echo ($employee['gender'] == 'M') ? 'Male' : 'Female'; ?>" disabled>
                        <label for="gender" class="form-label">Gender</label>
                    </div>
                    <div class="form-group">
                      <input type="email" class="form-control" id="empEmail" value="<?php echo htmlspecialchars($employee['email']); ?>" disabled>
                      <label for="empEmail" class="form-label">Email</label>
                    </div>
                    <div class="form-group">
                        <input type="text" class="form-control" id="empPhone" value="<?php echo htmlspecialchars($employee['phone']); ?>" disabled>
                        <label for="empPhone" class="form-label">Phone</label>
                    </div>
                    <div class="form-group">
                        <input type="text" class="form-control" id="dob" value="<?php echo htmlspecialchars($employee['dob']); ?>" disabled>
                        <label for="dob" class="form-label">Date of Birth</label>
                    </div>  
                  </div>
                <div class="col-md-4">
                    <div class="form-group">
                        <input type="text" class="form-control" id="empBranch" value="<?php echo htmlspecialchars($employee['branch_name']); ?>" disabled>
                        <label for="empBranch" class="form-label">Branch</label>
                    </div>
                    <div class="form-group">
                        <input type="text" class="form-control" id="empJoinDate" value="<?php echo htmlspecialchars($employee['join_date']); ?>" disabled>
                        <label for="empJoinDate" class="form-label">Joining Date</label>
                    </div>
                    <div class="form-group">
                        <input type="text" class="form-control" id="empDesignation" value="<?php echo htmlspecialchars($employee['designation']); ?>" disabled>
                        <label for="empDesignation" class="form-label">Designation</label>
                    </div>
                    <div class="form-group">
                        <input type="text" class="form-control" id="empStatus" value="<?php echo htmlspecialchars($employee['exit_date'] ? 'Exit on ' . $employee['exit_date'] : 'Working'); ?>" disabled>
                        <label for="empStatus" class="form-label">Employee Status</label>
                    </div>
                    <div class="form-group">
                        <input type="text" class="form-control" id="login_access" value="<?php echo htmlspecialchars($employee['login_access'] == '1' ? 'Granded' : 'Denied'); ?>" disabled>
                        <label for="login_access" class="form-label">Login Access</label>
                    </div>
                    <div class="form-group">
    <input type="text" class="form-control" id="birthday" value="<?php 
    $dob_date = new DateTime($employee['dob']);
    $current_date = new DateTime();
    $dob_date_this_year = (new DateTime())->setDate($current_date->format('Y'), $dob_date->format('m'), $dob_date->format('d'));

    if ($dob_date_this_year < $current_date) {
        $dob_date_this_year->modify('+1 year');
    }

    $days_until_birthday = $current_date->diff($dob_date_this_year)->days;
    $is_today_birthday = ($dob_date->format('m-d') == $current_date->format('m-d'));

    echo $is_today_birthday ? 'Today' : 'After ' . htmlspecialchars($days_until_birthday) . ' days'; ?>" disabled>
                        <label for="birthday" class="form-label">Birthday</label>
                    </div>
                </div>
                  <div class="col-md-4">
                    <div class="form-group text-center">
                      <div class="float-right">
                        <img id="photoPreview" src="<?php echo htmlspecialchars($employee['user_image']); ?>" alt="Employee Photo" style="max-width: 70%; display: block; border: 1px solid #ccc; border-radius: 50%;">
                      </div>
                    </div>
                  </div>
                </div>
              </div><!-- /.card-body -->
            </div><!-- /.card -->
          </div><!-- /.col -->
        </div><!-- /.row -->
     </div><!-- /.container-fluid -->
    </section><!-- /.content -->
  </div><!-- /.content-wrapper -->

  <?php 
  include 'includes/footer.php';
  ?>
</div>
<!-- ./wrapper -->

<!-- REQUIRED SCRIPTS -->
<script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.bundle.min.js"></script>

<!-- AdminLTE -->
<script src="<?php echo $home;?>dist/js/adminlte.js"></script>

</body>
</html>
