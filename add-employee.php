<?php
$page = 'Add Employee';
$accessRole = isset($_SESSION['role']) ? $_SESSION['role'] : null;
if ($accessRole === '0') {
    header('Location: dashboard.php');
    exit();
}
include 'includes/header.php';
include 'includes/db_connection.php'; // Include the database connection file

// Get query parameters
$empId = $_GET['empId'] ?? '';
$machId = $_GET['machId'] ?? '';
$empBranch = $_GET['empBranch'] ?? '';
$empFirstName = $_GET['empFirstName'] ?? '';
$empMiddleName = $_GET['empMiddleName'] ?? '';
$empLastName = $_GET['empLastName'] ?? '';
$gender = $_GET['gender'] ?? '';
$empEmail = $_GET['empEmail'] ?? '';
$empPhone = $_GET['empPhone'] ?? '';
$empJoinDate = $_GET['empJoinDate'] ?? '';
$designation = $_GET['designation'] ?? '';
$loginAccess = $_GET['login_access'] ?? '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
  // Get form data
  $machId = $_POST['machId'];
  $empBranch = $_POST['empBranch'];
  $empFirstName = $_POST['empFirstName'];
  $empMiddleName = $_POST['empMiddleName'];
  $empLastName = $_POST['empLastName'];
  $gender = $_POST['gender'];
  $empEmail = $_POST['empEmail'];
  $empPhone = $_POST['empPhone'];
  $empJoinDate = $_POST['empJoinDate'];
  $designation = $_POST['designation']; 
  $loginAccess = $_POST['login_access']; 
  $croppedImage = $_POST['croppedImage'];

  // Handle file upload
  if ($croppedImage) {
      $targetDir = "resources/userimg/uploads/";
      $imageData = base64_decode(preg_replace('#^data:image/\w+;base64,#i', '', $croppedImage));
      $imageName = uniqid() . '.png';
      $targetFile = $targetDir . $imageName;
      file_put_contents($targetFile, $imageData);
  } else {
      $targetFile = "resources/userimg/default-image.jpg";
  }

  // Generate empID based on branch value and auto-increment
  $stmt = $pdo->prepare("SELECT COUNT(*) AS count FROM employees WHERE branch = :branch");
  $stmt->execute([':branch' => $empBranch]);
  $row = $stmt->fetch();
  $count = $row['count'] + 1;
  $empId = $empBranch . str_pad($count, 2, '0', STR_PAD_LEFT);

  // Insert data into the database using prepared statements
  $sql = "INSERT INTO employees (emp_id, mach_id, branch, first_name, middle_name, last_name, gender, email, phone, join_date, designation, login_access, user_image)
          VALUES (:empId, :machId, :empBranch, :empFirstName, :empMiddleName, :empLastName, :gender, :empEmail, :empPhone, :empJoinDate, :designation, :loginAccess, :userImage)";
  $stmt = $pdo->prepare($sql);
  $stmt->execute([
      ':empId' => $empId,
      ':machId' => $machId,
      ':empBranch' => $empBranch,
      ':empFirstName' => $empFirstName,
      ':empMiddleName' => $empMiddleName,
      ':empLastName' => $empLastName,
      ':gender' => $gender,
      ':empEmail' => $empEmail,
      ':empPhone' => $empPhone,
      ':empJoinDate' => $empJoinDate,
      ':designation' => $designation,
      ':loginAccess' => $loginAccess,
      ':userImage' => $targetFile
  ]);

  echo "New record created successfully";

  // Update attendance_logs with emp_Id from employees based on machine_id
  try {
    // SQL query to update attendance_log with emp_Id from employees based on machine_id
    $sql = "UPDATE attendance_logs a JOIN employees e ON a.mach_id = e.mach_id SET a.emp_Id = e.emp_id;";

    // Prepare and execute the statement
    $stmt = $pdo->prepare($sql);
    $stmt->execute();

    echo '<div class="alert alert-success">Records updated successfully.</div>';

  } catch (PDOException $e) {
    echo '<div class="alert alert-danger">Error updating records: </div>' . $e->getMessage();
  }

  // Redirect to the users page to prevent form resubmission
  header("Location: employees.php");
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
            <h1 class="m-0">Add Employee</h1>
          </div><!-- /.col -->
          <div class="col-sm-6">
            <ol class="breadcrumb float-sm-right">
              <li class="breadcrumb-item"><a href="#">Home</a></li>
              <li class="breadcrumb-item active">Add Employee</li>
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
              <!-- <div class="card-header">
                <h3 class="card-title">Add New User</h3>
              </div> -->
              <!-- /.card-header -->
              <div class="card-body">
                <form id="addUserForm" method="POST" action="add-employee.php" enctype="multipart/form-data">
                  <div class="row">
                    <div class="col-md-4">
                      <div class="row">
                        <div class="col-md-4">
                          <div class="form-group">
                            <input type="text" class="form-control" autofocus id="machId" name="machId" placeholder=" " value="<?php echo htmlspecialchars($machId); ?>">
                            <label for="machId" class="form-label">Machine ID</label>
                          </div>
                        </div>
                        <div class="col-md-6">
                        </div>
                      </div>
                      <div class="row">
                        <div class="col-md-4">
                          <div class="form-group">
                            <input type="text" class="form-control text-capitalize" id="empFirstName" name="empFirstName" placeholder=" " required value="<?php echo htmlspecialchars($empFirstName); ?>">
                            <label for="empFirstName" class="form-label">First Name <span style="color:red;">*</span></label>
                          </div>
                        </div>
                        <div class="col-md-4">
                          <div class="form-group">
                            <input type="text" class="form-control text-capitalize" id="empMiddleName" name="empMiddleName" placeholder=" " value="<?php echo htmlspecialchars($empMiddleName); ?>">
                            <label for="empMiddleName" class="form-label">Middle Name</label>
                          </div>
                        </div>
                        <div class="col-md-4">
                          <div class="form-group">
                            <input type="text" class="form-control text-capitalize" id="empLastName" name="empLastName" placeholder=" " required value="<?php echo htmlspecialchars($empLastName); ?>">
                            <label for="empLastName" class="form-label">Last Name <span style="color:red;">*</span></label>
                          </div>
                        </div>
                      </div>
                      <div class="row">
                        <div class="col-md-6">
                          <div class="form-group">
                            <select class="form-control select2" style="width: 100%;" id="gender" name="gender" required>
                              <option selected disabled>Select a Gender</option>
                              <option value="M">Male</option>
                              <option value="F">Female</option>
                            </select>
                            <label for="gender" class="form-label">Gender <span style="color:red;">*</span></label>
                          </div>
                        </div>
                        <div class="col-md-6">
                          <div class="form-group">
                            <input type="text" class="form-control" id="empPhone" name="empPhone" placeholder=" " required pattern="^\+?[0-9]*$" title="Phone number can contain only numbers and the + sign" value="<?php echo htmlspecialchars($empPhone); ?>">
                            <label for="empPhone" class="form-label">Phone <span style="color:red;">*</span></label>
                          </div>
                        </div>
                        <div class="col-md-4">
                        </div>
                      </div>
                      <div class="form-group">
                        <input type="email" class="form-control" id="empEmail" name="empEmail" placeholder=" " required value="<?php echo htmlspecialchars($empEmail); ?>">
                        <label for="empEmail" class="form-label">Email <span style="color:red;">*</span></label>
                      </div>
                      <div class="row">
                        <div class="col-md-6">
                          <div class="form-group">
                            <select class="form-control select2" style="width: 100%;" id="empBranch" name="empBranch" required>
                              <option selected disabled>Select a Branch</option>
                              <!-- Optionally populate this statically or dynamically from the database -->
                              <?php 
                                  $branchQuery = "SELECT DISTINCT id, name FROM branches";
                                  $stmt = $pdo->query($branchQuery);
                                  while ($row = $stmt->fetch()) {
                                      echo "<option value='{$row['id']}'>{$row['name']}</option>";
                                  }
                              ?>
                            </select>
                            <label for="empBranch" class="form-label">Branch <span style="color:red;">*</span></label>
                          </div>
                        </div>
                        <div class="col-md-6">
                          <div class="form-group">
                            <input type="date" class="form-control" id="empJoinDate" name="empJoinDate" placeholder=" " value="<?php echo htmlspecialchars($empJoinDate); ?>" min="<?php echo date('Y-m-d', strtotime('-30 days')); ?>" max="<?php echo date('Y-m-d', strtotime('15 days')); ?>" required>
                            <label for="empJoinDate" class="form-label">Joining Date <span style="color:red;">*</span></label>
                          </div>
                        </div>
                      </div>
                      <div class="row">
                        <div class="col-md-6">
                          <div class="form-group">
                            <input type="text" class="form-control text-capitalize" id="designation" name="designation" placeholder=" " required value="">
                            <label for="designation" class="form-label">Designation <span style="color:red;">*</span></label>
                          </div>
                        </div>
                        <div class="col-md-6">
                          <div class="form-group">
                            <select class="form-control select2" style="width: 100%;" id="login_access" name="login_access" required>
                              <option selected disabled>Select Login Access</option>
                              <option value="1">Granted</option>
                              <option value="0">Denied</option>
                            </select>
                            <label for="loginAccess" class="form-label">Login Access <span style="color:red;">*</span></label>
                          </div>
                        </div>
                      </div>
                    </div>
                    <div class="col-md-4">
                    </div>
                    <div class="col-md-4">
                      <div class="form-group">
                        <img id="photoPreview" src="<?php echo $home;?>resources/userimg/default-image.jpg" alt="Photo Preview" style="max-width: 100; width: 300px; display: block; border: 1px solid #ccc; border-radius: 5px;">
                      </div>
                      <div class="form-group">
                        <input type="file" class="form-control-file" id="empPhoto" name="empPhoto" accept="image/*" onchange="openCropModal(event)">
                        <input type="hidden" id="croppedImage" name="croppedImage">
                      </div>
                    </div>
                  </div>
                  <div class="action-buttons">
                    <button type="submit" class="btn btn-primary">Add User</button>
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
  <!-- Crop Modal -->
<div class="modal fade" id="cropModal" tabindex="-1" role="dialog" aria-labelledby="cropModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="cropModalLabel">Crop Image</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
        <div class="img-container">
          <img id="imageToCrop" src="" alt="Image to Crop">
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-primary" id="cropButton">Crop</button>
      </div>
    </div>
  </div>
</div>
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

<!-- Page Specific Scripts -->
<script>
  $(function () {
    $("#user-table").DataTable({
      "responsive": true,
      "lengthChange": true,
      "autoWidth": true,
      "paging": true,
      "searching": true,
      "ordering": true,
      "info": true,
      "pageLength": 10, // Set the default number of rows to display
      "lengthMenu": [ [10, 25, 50, -1], [10, 25, 50, "All"] ], // Define the options in the page length dropdown menu
      "pagingType": "full_numbers", // Controls the pagination controls' appearance (options: 'simple', 'simple_numbers', 'full', 'full_numbers', 'first_last_numbers')
      "buttons": ["colvis"], //copy, csv, excel, pdf, print, colvis
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
    }).buttons().container().appendTo('#user-table_wrapper .col-md-6:eq(0)');

    // Add custom button below the filter
    $('#user-table_filter').after('<div class="custom-filter-button" style="display: flex; justify-content: flex-end;"><button class="btn btn-primary" id="custom-filter-btn">Add User</button></div>');

    // Custom button action
    $('#custom-filter-btn').on('click', function() {
      $('#addUserModal').modal({
        backdrop: 'static',
        keyboard: false
      });
    });
  });

  function previewPhoto(event) {
    var reader = new FileReader();
    reader.onload = function(){
      var output = document.getElementById('photoPreview');
      output.src = reader.result;
      output.style.display = 'block';
      initializeCropper();
    };
    reader.readAsDataURL(event.target.files[0]);
  }

  function initializeCropper() {
    var image = document.getElementById('photoPreview');
    var cropper = new Cropper(image, {
      aspectRatio: 1,
      viewMode: 1,
      autoCropArea: 1,
      cropBoxResizable: true,
      cropBoxMovable: true,
      dragMode: 'move'
    });
  }

  let cropper;
const imageToCrop = document.getElementById('imageToCrop');
const cropModal = new bootstrap.Modal(document.getElementById('cropModal'));

function openCropModal(event) {
    const file = event.target.files[0];
    const reader = new FileReader();
    reader.onload = function (e) {
        imageToCrop.src = e.target.result;
        cropModal.show();
        cropper = new Cropper(imageToCrop, {
            aspectRatio: 1,
            viewMode: 1,
            autoCropArea: 1,
            cropBoxResizable: true,
            cropBoxMovable: true,
            dragMode: 'move'
        });
    };
    reader.readAsDataURL(file);
}

document.getElementById('cropButton').addEventListener('click', function () {
    const canvas = cropper.getCroppedCanvas();
    canvas.toBlob(function (blob) {
        const reader = new FileReader();
        reader.onload = function (e) {
            document.getElementById('croppedImage').value = e.target.result;
            document.getElementById('photoPreview').src = e.target.result;
            cropModal.hide();
            cropper.destroy();
        };
        reader.readAsDataURL(blob);
    });
});
</script>


<!-- AdminLTE for demo purposes -->
<script src="<?php echo $home;?>dist/js/demo.js"></script>


</body>
</html>