<?php
// Include session configuration first
require_once 'includes/session_config.php';

$page = 'Assets Management';
require_once __DIR__ . '/includes/header.php';
include 'includes/db_connection.php';

?>

<!-- Add the necessary CSS for the redesigned asset cards -->
<style>
  /* Updated asset card styles to match quick links */
  .assets-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 1rem;
  }
  
  @media (max-width: 992px) {
    .assets-grid {
      grid-template-columns: repeat(2, 1fr);
    }
  }
  
  @media (max-width: 576px) {
    .assets-grid {
      grid-template-columns: 1fr;
    }
  }
  
  .asset-card {
    display: flex;
    align-items: center;
    padding: 1.25rem;
    border-radius: 0.75rem;
    transition: all 0.25s ease;
    background-color: rgba(var(--bs-light-rgb), 0.5);
    text-decoration: none !important;
    color: inherit;
  }
  
  .asset-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
    background-color: #fff;
  }
  
  body.dark-mode .asset-card {
    background-color: rgba(66, 66, 66, 0.2);
  }
  
  body.dark-mode .asset-card:hover {
    background-color: rgba(66, 66, 66, 0.4);
  }
  
  .asset-icon {
    width: 45px;
    height: 45px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-right: 1rem;
    font-size: 1.25rem;
  }
  
  .asset-content {
    flex: 1;
  }
  
  .asset-label {
    font-size: 0.9rem;
    color: #6c757d;
    margin-bottom: 0.25rem;
    font-weight: 500;
  }
  
  .asset-number {
    font-size: 1.75rem;
    font-weight: 600;
    line-height: 1.2;
  }
  
  .bg-primary-light {
    background-color: rgba(var(--bs-primary-rgb), 0.15);
  }
  
  .bg-success-light {
    background-color: rgba(var(--bs-success-rgb), 0.15);
  }
  
  .bg-info-light {
    background-color: rgba(var(--bs-info-rgb), 0.15);
  }
  
  .bg-warning-light {
    background-color: rgba(var(--bs-warning-rgb), 0.15);
  }
</style>

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
        <!-- <div class="card-header border-bottom">
          <h5 class="card-title m-0">Asset Management System</h5>
        </div> -->
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
                <div class="card-header border-bottom d-flex justify-content-between align-items-center">
                  <h5 class="card-title m-0">Asset Overview</h5>
                  <span class="badge rounded-pill bg-primary">Dashboard</span>
                </div>
                <div class="card-body">
                  <!-- Replace the old info boxes with the new grid layout -->
                  <div class="assets-grid">
                    <?php
                    $stmt = $pdo->query("SELECT COUNT(*) FROM FixedAssets");
                    $totalAssets = $stmt->fetchColumn();
                    ?>
                    <div class="asset-card">
                      <div class="asset-icon bg-primary-light">
                        <i class="fas fa-boxes"></i>
                      </div>
                      <div class="asset-content">
                        <div class="asset-label">Total Assets</div>
                        <div class="asset-number"><?php echo $totalAssets; ?></div>
                        <div class="progress mt-2" style="height: 5px;">
                          <div class="progress-bar bg-primary" style="width: 100%"></div>
                        </div>
                      </div>
                    </div>
                    
                    <?php
                    $stmt = $pdo->query("SELECT COUNT(*) FROM AssetCategories");
                    $totalCategories = $stmt->fetchColumn();
                    ?>
                    <div class="asset-card">
                      <div class="asset-icon bg-success-light">
                        <i class="fas fa-tags"></i>
                      </div>
                      <div class="asset-content">
                        <div class="asset-label">Asset Categories</div>
                        <div class="asset-number"><?php echo $totalCategories; ?></div>
                        <div class="progress mt-2" style="height: 5px;">
                          <div class="progress-bar bg-success" style="width: 100%"></div>
                        </div>
                      </div>
                    </div>
                    
                    <?php
                    $stmt = $pdo->query("SELECT COUNT(*) FROM AssetAssignments WHERE ReturnDate IS NULL");
                    $activeAssignments = $stmt->fetchColumn();
                    ?>
                    <div class="asset-card">
                      <div class="asset-icon bg-info-light">
                        <i class="fas fa-user-check"></i>
                      </div>
                      <div class="asset-content">
                        <div class="asset-label">Active Assignments</div>
                        <div class="asset-number"><?php echo $activeAssignments; ?></div>
                        <div class="progress mt-2" style="height: 5px;">
                          <div class="progress-bar bg-info" style="width: 100%"></div>
                        </div>
                      </div>
                    </div>
                    
                    <?php
                    $stmt = $pdo->query("SELECT COUNT(*) FROM AssetMaintenance WHERE MaintenanceStatus = 'In Progress' OR MaintenanceStatus = 'Scheduled'");
                    $totalMaintenance = $stmt->fetchColumn();
                    ?>
                    <div class="asset-card">
                      <div class="asset-icon bg-warning-light">
                        <i class="fas fa-tools"></i>
                      </div>
                      <div class="asset-content">
                        <div class="asset-label">Maintenance Records</div>
                        <div class="asset-number"><?php echo $totalMaintenance; ?></div>
                        <div class="progress mt-2" style="height: 5px;">
                          <div class="progress-bar bg-warning" style="width: 100%"></div>
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