<?php
$page = 'Profile';
include 'includes/header.php';
include 'includes/db_connection.php';

// Fetch user details from the database
$user_id = $_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT e.first_name, 
                                      e.middle_name, 
                                      e.last_name, 
                                      e.gender, 
                                      e.email, 
                                      e.phone, 
                                      e.join_date, 
                                      e.designation, 
                                      e.user_image, 
                                      e.emp_id, 
                                      e.branch, 
                                      e.dob, 
                                      e.exit_date, 
                                      b.name AS branch_name 
                                      FROM employees e 
                                      INNER JOIN branches b ON e.branch = b.id 
                                      WHERE e.id = :id");
$stmt->execute(['id' => $user_id]);
$user_data = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user_data) {
    // Destroy all session data redirect to login page if user is not found
    session_unset();
    session_destroy();
    header('Location: index.php');
    exit;
}

// Handle profile picture update
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['croppedImage'])) {
    $targetDir = "resources/userimg/uploads/";
    $imageData = base64_decode(preg_replace('#^data:image/\w+;base64,#i', '', $_POST['croppedImage']));
    $imageName = uniqid() . '.png';
    $targetFile = $targetDir . $imageName;
    file_put_contents($targetFile, $imageData);

    // Update the database with the new profile picture
    $stmt = $pdo->prepare("UPDATE employees SET user_image = :user_image WHERE id = :id");
    $stmt->execute(['user_image' => $targetFile, 'id' => $user_id]);
    $user_data['user_image'] = $targetFile;
}

// Handle password reset
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['new_password'])) {
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];

    if ($new_password === $confirm_password) {
        $hashed_password = password_hash($new_password, PASSWORD_BCRYPT);
        $stmt = $pdo->prepare("UPDATE employees SET password = :password WHERE id = :id");
        $stmt->execute(['password' => $hashed_password, 'id' => $user_id]);
        echo "Password updated successfully.";
    } else {
        echo "Passwords do not match.";
    }
}
?>

    <!-- Bootstrap CSS -->
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <!-- Cropper.js CSS -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.12/cropper.min.css">
    <!-- AdminLTE CSS -->
    <link rel="stylesheet" href="<?php echo $home;?>dist/css/adminlte.min.css">

    <style>
        .profile-picture-container {
            position: relative;
            width: 100%;
            max-width: 200px;
            border-radius: 50%;
            margin: 0 auto;
            overflow: hidden
        }
        .profile-picture-container img {
            width: 100%;
        }
        .profile-picture-container .upload-overlay {
            position: absolute;
            bottom: 0;
            left: 0;
            width: 100%;
            height: 20%;
            text-align: center;
            display: none;
            background-color: rgba(0, 0, 0, 0.5);
        }
        .profile-picture-container:hover .upload-overlay {
            display: block;
        }
        .upload-overlay button {
            background: none;
            border: none;
            color: white;
        }

        /* Crop Modal CSS */


        /* Force the image to fill the container */
        .img-container img {
            width: 100%;
            display: block;
        }
        /*Crop Modal CSS Ends*/
    </style>
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
            <h1 class="m-0">Profile</h1>
          </div><!-- /.col -->
          <div class="col-sm-6">
            <ol class="breadcrumb float-sm-right">
              <li class="breadcrumb-item"><a href="#">Home</a></li>
              <li class="breadcrumb-item active">Profile</li>
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
            <h3 class="card-title">Employee Information</h3>
          </div>
          <div class="card-body">
            <div class="row">
              <div class="col-md-4">
                <div class="profile-picture-container">
                  <img id="profileImage" src="<?php echo htmlspecialchars($user_data['user_image']); ?>" alt="Profile Image" class="img-fluid rounded-circle">
                    <div class="upload-overlay">
                        <button class="pt-2" onclick="document.getElementById('profilePictureInput').click();">
                        <i class="fas fa-pencil-alt"></i><span class="text-sm"> Change</span>
                        </button>
                    </div>
                </div>
                <form id="profilePictureForm" action="profile.php" method="post" enctype="multipart/form-data">
                  <input type="file" class="form-control-file" id="profilePictureInput" name="profile_picture" accept="image/*" style="display: none;" onchange="openCropModal(event)">
                  <input type="hidden" id="croppedImage" name="croppedImage">
                  <button type="submit" class="btn btn-primary mt-3" id="uploadButton" style="display: none;">Upload</button>
                </form>
                <div class="userInfo text-center">
                  <h4 class="mt-3 mb-1"><?php echo htmlspecialchars($user_data['first_name'] . ' ' . $user_data['middle_name'] . ' ' . $user_data['last_name']); ?></h4>
                  <p class="mb-0"><?php echo htmlspecialchars($user_data['branch_name']); ?></p>
                  <p><?php echo htmlspecialchars($user_data['designation']); ?></p>
                  <a href="#" data-toggle="modal" data-target="#changePasswordModal">Change Password</a>
                </div>
              </div>
              <div class="col-md-8">
                <table class="table table-bordered">
                  <thead>
                    <th colspan="2" class="text-center">Basic Information</th>
                    <th colspan="2" class="text-center">Assigned Information</th>
                  </thead>
                  <tbody>
                    <tr>
                      <th>Full Name</th>
                      <td><?php echo htmlspecialchars($user_data['first_name'] . ' ' . $user_data['middle_name'] . ' ' . $user_data['last_name']); ?></td>
                      <th>Employee ID</th>
                      <td><?php echo htmlspecialchars($user_data['emp_id']); ?></td>
                    </tr>
                    <tr>
                      <th>Address</th>
                      <td>Tinkune, Kathmandu</td>
                      <th>Branch</th>
                      <td><?php echo htmlspecialchars($user_data['branch_name']); ?></td>
                    </tr>
                    <tr>
                      <th>Email</th>
                      <td><?php echo htmlspecialchars($user_data['email']); ?></td>
                      <th>Designation</th>
                      <td><?php echo htmlspecialchars($user_data['designation']); ?></td>
                    </tr>
                    <tr>
                      <th>Phone</th>
                      <td><?php echo htmlspecialchars($user_data['phone']); ?></td>
                      <th>Joined On</th>
                      <td><?php echo htmlspecialchars($user_data['join_date']); ?></td>
                    </tr>
                    <tr>
                      <th>Gender</th>
                      <td><?php echo htmlspecialchars($user_data['gender'] == 'M' ? 'Male' : 'Female'); ?></td>
                      <th>Status</th>
                      <td><?php echo htmlspecialchars($user_data['exit_date'] ? 'Exit on ' . $user_data['exit_date'] : 'Working'); ?></td>
                    </tr>
                    <tr>
                      <th>Date of Birth</th>
                      <td><?php echo htmlspecialchars($user_data['dob']); ?></td>
                      <th>Assigned Assets</th>
                      <td>Laptop, Sim Card, Id Card, etc.</td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>
          </div>
          <!-- /.card-body -->
        </div>
        <!-- /.card -->
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

<!-- Crop Modal -->
<div class="modal fade" id="cropModal" tabindex="-1" role="dialog" aria-labelledby="cropModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered" role="document">
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
          <button type="button" class="btn btn-primary text-right" id="cropButton">Crop</button>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Change Password Modal -->
<div class="modal fade" id="changePasswordModal" tabindex="-1" role="dialog" aria-labelledby="changePasswordModalLabel" aria-hidden="true" data-backdrop="static" data-keyboard="false">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="changePasswordModalLabel">Change Password</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <form id="passwordResetForm" action="profile.php" method="post" class="form">
                    <div class="form-group">
                        <label for="new_password_modal">New Password</label>
                        <div class="input-group">
                            <input type="password" class="form-control" id="new_password_modal" name="new_password" placeholder="New Password" required>
                            <div class="input-group-append">
                                <button type="button" class="btn btn-secondary" id="generatePassword">Generate</button>
                            </div>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="confirm_password_modal">Confirm Password</label>
                        <input type="password" class="form-control" id="confirm_password_modal" name="confirm_password" placeholder="Confirm Password" required>
                    </div>
                    <button type="submit" class="btn btn-primary">Change Password</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
    let cropper;
    const imageToCrop = document.getElementById('imageToCrop');
    const cropModal = new bootstrap.Modal(document.getElementById('cropModal'), {
      backdrop: 'static',
      keyboard: false
    });

    const changePasswordModal = new bootstrap.Modal(document.getElementById('changePasswordModal'), {
      backdrop: 'static',
      keyboard: false
    });

    // Generate a random password
    function generateRandomPassword(length = 12) {
      const charset = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*()_+~`|}{[]\:;?><,./-=";
      let password = "";
      for (let i = 0; i < length; i++) {
          const randomIndex = Math.floor(Math.random() * charset.length);
          password += charset.charAt(randomIndex);
      }
      return password;
    }

    document.getElementById('generatePassword').addEventListener('click', function(){
      const newPass = generateRandomPassword();
      const newPassInput = document.getElementById('new_password_modal');
      const confirmPassInput = document.getElementById('confirm_password_modal');
      newPassInput.value = newPass;
      confirmPassInput.value = newPass;

      // Change input types so they show the generated password as plain text
      newPassInput.type = 'text';
      confirmPassInput.type = 'text';
    });

    // Open the crop modal and initialize the cropper
    function openCropModal(event) {
        const file = event.target.files[0];
        const reader = new FileReader();
        reader.onload = function (e) {
            imageToCrop.src = e.target.result;
            cropModal.show();
            cropper = new Cropper(imageToCrop, {
                aspectRatio: 1,
                viewMode: 2,  // ensures the image fills the container better
                autoCropArea: 1,
                cropBoxResizable: true,
                cropBoxMovable: true,
                dragMode: 'move',
                background: false 
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
                document.getElementById('profileImage').src = e.target.result;
                document.getElementById('uploadButton').style.display = 'block';
                cropModal.hide();
                cropper.destroy();
            };
            reader.readAsDataURL(blob);
        });
    });
</script>
</body>
</html>