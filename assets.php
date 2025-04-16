<?php
$page = 'Assets Management';
include 'includes/header.php';
include 'includes/db_connection.php';

// Check if user is logged in and has admin role
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] != '1') {
    header('Location: index.php');
    exit();
}
?>

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
            <h1 class="m-0">Assets Management</h1>
          </div>
          <div class="col-sm-6">
            <ol class="breadcrumb float-sm-right">
              <li class="breadcrumb-item"><a href="index.php">Home</a></li>
              <li class="breadcrumb-item active">Assets Management</li>
            </ol>
          </div>
        </div>
      </div>
    </div>
    
    <!-- Main content -->
    <section class="content">
      <div class="container-fluid">
        <div class="card">
          <div class="card-header">
            <h3 class="card-title">Asset Management System</h3>
          </div>
          <div class="card-body">
            <div class="row">
              <div class="col-md-3">
                <div class="card card-primary card-outline">
                  <div class="card-body box-profile">
                    <div class="text-center">
                      <i class="fas fa-boxes fa-3x text-primary"></i>
                    </div>
                    <h3 class="profile-username text-center">Fixed Assets</h3>
                    <p class="text-muted text-center">Manage company assets</p>
                    <a href="manage_assets.php" class="btn btn-primary btn-block">Manage Assets</a>
                  </div>
                </div>
              </div>
              
              <div class="col-md-3">
                <div class="card card-success card-outline">
                  <div class="card-body box-profile">
                    <div class="text-center">
                      <i class="fas fa-tags fa-3x text-success"></i>
                    </div>
                    <h3 class="profile-username text-center">Asset Categories</h3>
                    <p class="text-muted text-center">Organize assets by category</p>
                    <a href="manage_categories.php" class="btn btn-success btn-block">Manage Categories</a>
                  </div>
                </div>
              </div>
              
              <div class="col-md-3">
                <div class="card card-info card-outline">
                  <div class="card-body box-profile">
                    <div class="text-center">
                      <i class="fas fa-user-check fa-3x text-info"></i>
                    </div>
                    <h3 class="profile-username text-center">Asset Assignments</h3>
                    <p class="text-muted text-center">Assign assets to employees</p>
                    <a href="manage_assignments.php" class="btn btn-info btn-block">Manage Assignments</a>
                  </div>
                </div>
              </div>
              
              <div class="col-md-3">
                <div class="card card-warning card-outline">
                  <div class="card-body box-profile">
                    <div class="text-center">
                      <i class="fas fa-tools fa-3x text-warning"></i>
                    </div>
                    <h3 class="profile-username text-center">Maintenance Records</h3>
                    <p class="text-muted text-center">Track asset maintenance</p>
                    <a href="manage_maintenance.php" class="btn btn-warning btn-block">Manage Maintenance</a>
                  </div>
                </div>
              </div>
            </div>
            
            <div class="row mt-4">
              <div class="col-md-12">
                <div class="card">
                  <div class="card-header">
                    <h3 class="card-title">Asset Overview</h3>
                  </div>
                  <div class="card-body">
                    <div class="row">
                      <div class="col-md-3">
                        <div class="info-box">
                          <span class="info-box-icon bg-primary"><i class="fas fa-boxes"></i></span>
                          <div class="info-box-content">
                            <?php
                            $stmt = $pdo->query("SELECT COUNT(*) FROM FixedAssets");
                            $totalAssets = $stmt->fetchColumn();
                            ?>
                            <span class="info-box-text">Total Assets</span>
                            <span class="info-box-number"><?php echo $totalAssets; ?></span>
                          </div>
                        </div>
                      </div>
                      
                      <div class="col-md-3">
                        <div class="info-box">
                          <span class="info-box-icon bg-success"><i class="fas fa-tags"></i></span>
                          <div class="info-box-content">
                            <?php
                            $stmt = $pdo->query("SELECT COUNT(*) FROM AssetCategories");
                            $totalCategories = $stmt->fetchColumn();
                            ?>
                            <span class="info-box-text">Asset Categories</span>
                            <span class="info-box-number"><?php echo $totalCategories; ?></span>
                          </div>
                        </div>
                      </div>
                      
                      <div class="col-md-3">
                        <div class="info-box">
                          <span class="info-box-icon bg-info"><i class="fas fa-user-check"></i></span>
                          <div class="info-box-content">
                            <?php
                            $stmt = $pdo->query("SELECT COUNT(*) FROM AssetAssignments WHERE ReturnDate IS NULL");
                            $activeAssignments = $stmt->fetchColumn();
                            ?>
                            <span class="info-box-text">Active Assignments</span>
                            <span class="info-box-number"><?php echo $activeAssignments; ?></span>
                          </div>
                        </div>
                      </div>
                      
                      <div class="col-md-3">
                        <div class="info-box">
                          <span class="info-box-icon bg-warning"><i class="fas fa-tools"></i></span>
                          <div class="info-box-content">
                            <?php
                            $stmt = $pdo->query("SELECT COUNT(*) FROM AssetMaintenance WHERE MaintenanceStatus = 'In Progress' OR MaintenanceStatus = 'Scheduled'");
                            $totalMaintenance = $stmt->fetchColumn();
                            ?>
                            <span class="info-box-text">Maintenance Records</span>
                            <span class="info-box-number"><?php echo $totalMaintenance; ?></span>
                          </div>
                        </div>
                      </div>
                    </div>
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
<!-- AdminLTE -->
<script src="<?php echo $home;?>dist/js/adminlte.js"></script>
</body>
</html> 