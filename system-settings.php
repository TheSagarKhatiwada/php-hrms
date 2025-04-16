<?php
$page = 'System Setting';
include 'includes/header.php';
include 'includes/db_connection.php';

// Fetch user details from the database
$stmt = $pdo->prepare("SELECT * FROM settings");
$stmt->execute();
$settings = $stmt->fetch(PDO::FETCH_ASSOC);
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
            <h3 class="card-title">Card Title</h3>
          </div>
          <div class="card-body">
            <!-- contents goes here -->
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

</body>
</html>