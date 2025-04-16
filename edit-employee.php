<?php
$page = 'Edit User';
$accessRole = isset($_SESSION['role']) ? $_SESSION['role'] : null;
if ($accessRole === '0') {
    header('Location: dashboard.php');
    exit();
}
require 'includes/header.php'; // Include header
require 'includes/db_connection.php'; // Include database connection

if (!isset($_GET['id'])) {
  header('Location: employees.php');
}

$emp_id = $_GET['id'];

// Fetch employee details
$stmt = $pdo->prepare("SELECT * FROM employees WHERE emp_id = :emp_id");
$stmt->execute(['emp_id' => $emp_id]);
$employee = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$employee) {
    $_SESSION['error'] = "Employee not found";
    header('Location: employees.php');
    exit();
}
?>
<!-- Bootstrap CSS -->
<link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
<!-- Cropper.js CSS -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.12/cropper.min.css">

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
            <h1 class="m-0">Edit User</h1>
          </div><!-- /.col -->
          <div class="col-sm-6">
            <ol class="breadcrumb float-sm-right">
              <li class="breadcrumb-item"><a href="#">Home</a></li>
              <li class="breadcrumb-item active">Edit User</li>
            </ol>
          </div><!-- /.col -->
        </div><!-- /.row -->
      </div><!-- /.container-fluid -->
    </div>
    <!-- /.content-header -->
    <!-- Main content -->
    <section class="content">
     <div class="container-fluid">
        <?php if (isset($_SESSION['success'])): ?>
          <script>
            showSuccessToast('<?php echo $_SESSION['success']; ?>');
            <?php unset($_SESSION['success']); ?>
          </script>
        <?php endif; ?>

        <?php if (isset($_SESSION['error'])): ?>
          <script>
            showErrorToast('<?php echo $_SESSION['error']; ?>');
            <?php unset($_SESSION['error']); ?>
          </script>
        <?php endif; ?>
     <div class="card">
              <!-- <div class="card-header">
                <h3 class="card-title">Add New User</h3>
              </div> -->
              <!-- /.card-header -->
              <div class="card-body">
                <form id="addUserForm" method="POST" action="update-employee.php" enctype="multipart/form-data">
                  <div class="row">
                    <div class="col-md-6">
                      <div class="row">
                        <div class="col-md-4">
                          <div class="form-group">
                            <input type="hidden" name="emp_id" value="<?php echo htmlspecialchars($emp_id); ?>">
                            <input type="text" class="form-control" id="machId" name="machId" placeholder=" " value="<?php echo htmlspecialchars($employee['mach_id']); ?>">
                            <label for="machId" class="form-label">Machine ID</label>
                          </div>
                        </div>
                        <div class="col-md-6">
                        </div>
                      </div>
                      <div class="row">
                        <div class="col-md-4">
                          <div class="form-group">
                            <input type="text" class="form-control" id="empFirstName" name="empFirstName" placeholder=" " required value="<?php echo htmlspecialchars($employee['first_name']); ?>">
                            <label for="empFirstName" class="form-label">First Name <span style="color:red;">*</span></label>
                          </div>
                        </div>
                        <div class="col-md-4">
                          <div class="form-group">
                            <input type="text" class="form-control" id="empMiddleName" name="empMiddleName" placeholder=" " value="<?php echo htmlspecialchars($employee['middle_name']); ?>">
                            <label for="empMiddleName" class="form-label">Middle Name</label>
                          </div>
                        </div>
                        <div class="col-md-4">
                          <div class="form-group">
                            <input type="text" class="form-control" id="empLastName" name="empLastName" placeholder=" " required value="<?php echo htmlspecialchars($employee['last_name']); ?>">
                            <label for="empLastName" class="form-label">Last Name <span style="color:red;">*</span></label>
                          </div>
                        </div>
                      </div>
                      <div class="row">
                        <div class="col-md-6">
                          <div class="form-group">
                            <select class="form-control select2" style="width: 100%;" id="gender" name="gender" required>
                              <option selected disabled>Select a Gender</option>
                              <?php 
                                $genders = ['M' => 'Male', 'F' => 'Female'];
                                foreach ($genders as $key => $value) {
                                  $selected = ($employee['gender'] == $key) ? 'selected' : '';
                                  echo "<option value='$key' $selected>$value</option>";
                                }
                              ?>
                            </select>
                            <label for="gender" class="form-label">Gender <span style="color:red;">*</span></label>
                          </div>
                        </div>
                        <div class="col-md-6">
                          <div class="form-group">
                            <input type="text" class="form-control" id="empPhone" name="empPhone" placeholder=" " required pattern="^\+?[0-9]*$" title="Phone number can contain only numbers and the + sign" value="<?php echo htmlspecialchars($employee['phone']); ?>">
                            <label for="empPhone" class="form-label">Phone <span style="color:red;">*</span></label>
                          </div>
                        </div>
                      </div>
                      <div class="form-group">
                        <input type="email" class="form-control" id="empEmail" name="empEmail" placeholder=" " required value="<?php echo htmlspecialchars($employee['email']); ?>">
                        <label for="empEmail" class="form-label">Email <span style="color:red;">*</span></label>
                      </div>
                      <div class="row">
                        <div class="col-md-6">
                          <div class="form-group">
                            <select class="form-control select2" style="width: 100%;" id="empBranch" name="empBranch" required>
                                <option selected disabled>Select a Branch</option>
                                <?php 
                                    $branchQuery = "SELECT id, name FROM branches";
                                    $stmt = $pdo->query($branchQuery);
                                    while ($row = $stmt->fetch()) {
                                        $selected = ($row['id'] == $employee['branch']) ? 'selected' : ''; 
                                        echo "<option value='{$row['id']}' $selected>{$row['name']}</option>";
                                    }
                                ?>
                            </select>
                            <label for="empBranch" class="form-label">Branch <span style="color:red;">*</span></label>
                          </div>
                        </div>
                        <div class="col-md-6">
                          <div class="form-group">
                            <input type="date" class="form-control" id="empJoinDate" name="empJoinDate" placeholder=" " value="<?php echo htmlspecialchars($employee['join_date']); ?>" max="<?php echo date('Y-m-d', strtotime('15 days')); ?>" required>
                            <label for="empJoinDate" class="form-label">Joining Date <span style="color:red;">*</span></label>
                          </div>
                        </div>
                      </div>
                      <div class="row">
                        <div class="col-md-6">
                        <div class="form-group">
                        <input type="text" class="form-control" id="designation" name="designation" placeholder=" " required value="<?php echo htmlspecialchars($employee['designation']); ?>">
                        <label for="designation" class="form-label">Designation <span style="color:red;">*</span></label>
                      </div>
                        </div>
                        <div class="col-md-6">
                          <div class="form-group">
                            <select class="form-control select2" style="width: 100%;" id="login_access" name="login_access" required>
                              <option selected disabled>Select Login Access</option>
                              <?php 
                                $LoginAccess = ['1' => 'Granted', '0' => 'Denied'];
                                foreach ($LoginAccess as $key => $value) {
                                  $selected = ($employee['login_access'] == $key) ? 'selected' : '';
                                  echo "<option value='$key' $selected>$value</option>";
                                }
                              ?>
                            </select>
                            <label for="login_access" class="form-label">Login Access <span style="color:red;">*</span></label>
                          </div>
                        </div>
                      </div>
                    </div>
                    <div class="col-md-2">
                    </div>
                    <div class="col-md-4">
                      <div class="form-group">
                        <img id="photoPreview" src="<?php echo $home . htmlspecialchars($employee['user_image']); ?>" alt="Photo Preview" style="max-width: 50%; display: block; border: 1px solid #ccc; border-radius: 5px;">
                      </div>
                      <div class="form-group">
                        <label for="empPhoto">Photo</label>
                          <input type="file" class="form-control-file" id="empPhoto" name="empPhoto" accept="image/*" onchange="openCropModal(event)">
                          <input type="hidden" id="croppedImage" name="croppedImage">
                        </div>
                    </div>
                  </div>
                  <div class="action-buttons">
                    <button type="submit" class="btn btn-primary">Update</button>
                  </div>
                </form>
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
  </div><!-- /.content-wrapper -->

  
  <?php 
  include 'includes/footer.php';
  ?>
</div>
<!-- ./wrapper -->

<!-- REQUIRED SCRIPTS -->

<!-- jQuery -->
<script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
<!-- Bootstrap JS -->
<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.bundle.min.js"></script>
<!-- Cropper.js JS -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.12/cropper.min.js"></script>

<!-- AdminLTE -->
<script src="<?php echo $home;?>dist/js/adminlte.js"></script>
