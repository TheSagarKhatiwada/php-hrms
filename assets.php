<?php
// Include session configuration first
require_once 'includes/session_config.php';

$page = 'Assets Management';
require_once __DIR__ . '/includes/header.php';
include 'includes/db_connection.php';

?>

<!-- Content Wrapper. Contains page content (opened in header.php) -->
<!-- <div class="content-wrapper"> -->
    <!-- Main content -->
    <div class="container-fluid p-4">
      <!-- Page header -->
      <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
          <h1 class="fs-2 fw-bold mb-1">Assets Management</h1>
        </div>
      </div>
      
      <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white border-bottom">
          <h5 class="card-title m-0">Asset Management System</h5>
        </div>
        <div class="card-body">
          <div class="row">
            <div class="col-md-3 mb-3">
              <div class="card border-0 shadow-sm h-100">
                <div class="card-body text-center">
                  <i class="fas fa-boxes fa-3x text-primary mb-3"></i>
                  <h5 class="card-title mb-2">Fixed Assets</h5>
                  <p class="card-text text-muted mb-3">Manage company assets</p>
                  <a href="manage_assets.php" class="btn btn-primary w-100">Manage Assets</a>
                </div>
              </div>
            </div>
            
            <div class="col-md-3 mb-3">
              <div class="card border-0 shadow-sm h-100">
                <div class="card-body text-center">
                  <i class="fas fa-tags fa-3x text-success mb-3"></i>
                  <h5 class="card-title mb-2">Asset Categories</h5>
                  <p class="card-text text-muted mb-3">Organize assets by category</p>
                  <a href="manage_categories.php" class="btn btn-success w-100">Manage Categories</a>
                </div>
              </div>
            </div>
            
            <div class="col-md-3 mb-3">
              <div class="card border-0 shadow-sm h-100">
                <div class="card-body text-center">
                  <i class="fas fa-user-check fa-3x text-info mb-3"></i>
                  <h5 class="card-title mb-2">Asset Assignments</h5>
                  <p class="card-text text-muted mb-3">Assign assets to employees</p>
                  <a href="manage_assignments.php" class="btn btn-info w-100">Manage Assignments</a>
                </div>
              </div>
            </div>
            
            <div class="col-md-3 mb-3">
              <div class="card border-0 shadow-sm h-100">
                <div class="card-body text-center">
                  <i class="fas fa-tools fa-3x text-warning mb-3"></i>
                  <h5 class="card-title mb-2">Maintenance Records</h5>
                  <p class="card-text text-muted mb-3">Track asset maintenance</p>
                  <a href="manage_maintenance.php" class="btn btn-warning w-100">Manage Maintenance</a>
                </div>
              </div>
            </div>
          </div>
          
          <div class="row mt-4">
            <div class="col-md-12">
              <div class="card border-0 shadow-sm">
                <div class="card-header bg-white border-bottom">
                  <h5 class="card-title m-0">Asset Overview</h5>
                </div>
                <div class="card-body">
                  <div class="row">
                    <div class="col-md-3 mb-3">
                      <div class="info-box shadow-sm">
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
                    
                    <div class="col-md-3 mb-3">
                      <div class="info-box shadow-sm">
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
                    
                    <div class="col-md-3 mb-3">
                      <div class="info-box shadow-sm">
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
                    
                    <div class="col-md-3 mb-3">
                      <div class="info-box shadow-sm">
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

<?php include 'includes/footer.php'; ?>
<!-- Wrapper div closed in footer.php -->