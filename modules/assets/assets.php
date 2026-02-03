<?php
// Include session configuration first
require_once '../../includes/session_config.php';

$page = 'Assets Management';
require_once __DIR__ . '/../../includes/header.php';
include '../../includes/db_connection.php';

?>

<!-- Assets dashboard styling -->
<style>
  .icon-circle {
    width: 44px;
    height: 44px;
    border-radius: 999px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border: 1px solid var(--bs-border-color);
    font-size: 1.15rem;
  }

  .quick-actions-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 1rem;
  }

  @media (max-width: 1200px) {
    .quick-actions-grid {
      grid-template-columns: repeat(2, 1fr);
    }
  }

  @media (max-width: 576px) {
    .quick-actions-grid {
      grid-template-columns: 1fr;
    }
  }

  .action-card {
    border-radius: 0.9rem;
    transition: transform 0.2s ease, box-shadow 0.2s ease;
  }

  .action-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
  }

  .metrics-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 1rem;
  }

  @media (max-width: 1200px) {
    .metrics-grid {
      grid-template-columns: repeat(2, 1fr);
    }
  }

  @media (max-width: 576px) {
    .metrics-grid {
      grid-template-columns: 1fr;
    }
  }

  .metric-tile {
    border-radius: 0.9rem;
  }

  .metric-label {
    color: var(--bs-secondary-color);
    font-size: 0.85rem;
    text-transform: uppercase;
    letter-spacing: 0.04em;
  }

  .metric-value {
    font-size: 1.7rem;
    font-weight: 700;
  }

  .insight-item {
    padding: 0.75rem 0;
    border-bottom: 1px solid var(--bs-border-color);
  }

  .insight-item:last-child {
    border-bottom: none;
  }

  .table thead th {
    font-size: 0.8rem;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    color: var(--bs-secondary-color);
  }
</style>

<?php
// Summary metrics
try {
  $totalAssets = (int)$pdo->query("SELECT COUNT(*) FROM fixedassets")->fetchColumn();
} catch (Throwable $e) {
  $totalAssets = 0;
}

try {
  $totalCategories = (int)$pdo->query("SELECT COUNT(*) FROM assetcategories")->fetchColumn();
} catch (Throwable $e) {
  $totalCategories = 0;
}

try {
  $activeAssignments = (int)$pdo->query("SELECT COUNT(*) FROM assetassignments WHERE ReturnDate IS NULL")->fetchColumn();
} catch (Throwable $e) {
  $activeAssignments = 0;
}

try {
  $totalMaintenance = (int)$pdo->query("SELECT COUNT(*) FROM assetmaintenance WHERE MaintenanceStatus IN ('In Progress', 'Scheduled')")->fetchColumn();
} catch (Throwable $e) {
  $totalMaintenance = 0;
}

// Operational insights
try {
  $availableAssets = (int)$pdo->query("SELECT COUNT(*) FROM fixedassets WHERE Status = 'Available'")->fetchColumn();
} catch (Throwable $e) {
  $availableAssets = 0;
}

try {
  $overdueReturns = (int)$pdo->query("SELECT COUNT(*) FROM assetassignments WHERE ReturnDate IS NULL AND ExpectedReturnDate IS NOT NULL AND ExpectedReturnDate < CURDATE()")->fetchColumn();
} catch (Throwable $e) {
  $overdueReturns = 0;
}

try {
  $warrantyExpiring = (int)$pdo->query("SELECT COUNT(*) FROM fixedassets WHERE WarrantyEndDate IS NOT NULL AND WarrantyEndDate >= CURDATE() AND WarrantyEndDate <= DATE_ADD(CURDATE(), INTERVAL 30 DAY)")->fetchColumn();
} catch (Throwable $e) {
  $warrantyExpiring = 0;
}

// Recent assignments
try {
  $recentAssignments = $pdo->query("SELECT aa.AssignmentDate, aa.ExpectedReturnDate, fa.AssetName, fa.AssetSerial, e.first_name, e.middle_name, e.last_name FROM assetassignments aa LEFT JOIN fixedassets fa ON aa.AssetID = fa.AssetID LEFT JOIN employees e ON aa.EmployeeID = e.emp_id ORDER BY aa.AssignmentDate DESC LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
  $recentAssignments = [];
}

// Upcoming or in-progress maintenance
try {
  $upcomingMaintenance = $pdo->query("SELECT am.MaintenanceDate, am.MaintenanceType, am.MaintenanceStatus, fa.AssetName, fa.AssetSerial FROM assetmaintenance am LEFT JOIN fixedassets fa ON am.AssetID = fa.AssetID WHERE am.MaintenanceStatus IN ('Scheduled','In Progress') ORDER BY am.MaintenanceDate ASC LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
  $upcomingMaintenance = [];
}
?>

<!-- Content Wrapper. Contains page content (opened in header.php) -->
<!-- <div class="content-wrapper"> -->
    <!-- Main content -->
    <div class="container-fluid p-4">
      <div class="d-flex justify-content-between flex-wrap gap-3 align-items-center mb-3">
        <div>
          <h1 class="fs-2 mb-1"><i class="fas fa-boxes me-2"></i>Assets Management</h1>
          <p class="text-muted mb-0">Track equipment, assignments, and maintenance at a glance.</p>
        </div>
      </div>

      <div class="mb-4">
        <div class="d-flex align-items-center justify-content-between mb-3">
          <h5 class="mb-0">Quick Actions</h5>
          <span class="text-muted small">Jump into the most common workflows</span>
        </div>
        <div class="quick-actions-grid">
          <a href="manage_assets.php" class="card border-0 shadow-sm action-card text-decoration-none h-100">
            <div class="card-body">
              <div class="d-flex align-items-center justify-content-between mb-3">
                <span class="icon-circle text-primary"><i class="fas fa-boxes"></i></span>
                <span class="badge text-bg-light">Assets</span>
              </div>
              <h6 class="fw-semibold text-dark">Fixed Assets</h6>
              <p class="text-muted small mb-3">Create, edit, and retire assets.</p>
              <div class="d-flex align-items-center justify-content-between">
                <span class="text-muted small">Total Assets</span>
                <span class="fw-bold fs-5 text-dark"><?php echo $totalAssets; ?></span>
              </div>
            </div>
          </a>
          <a href="manage_categories.php" class="card border-0 shadow-sm action-card text-decoration-none h-100">
            <div class="card-body">
              <div class="d-flex align-items-center justify-content-between mb-3">
                <span class="icon-circle text-success"><i class="fas fa-tags"></i></span>
                <span class="badge text-bg-light">Categories</span>
              </div>
              <h6 class="fw-semibold text-dark">Asset Categories</h6>
              <p class="text-muted small mb-3">Group assets for easy filtering.</p>
              <div class="d-flex align-items-center justify-content-between">
                <span class="text-muted small">Active Categories</span>
                <span class="fw-bold fs-5 text-dark"><?php echo $totalCategories; ?></span>
              </div>
            </div>
          </a>
          <a href="manage_assignments.php" class="card border-0 shadow-sm action-card text-decoration-none h-100">
            <div class="card-body">
              <div class="d-flex align-items-center justify-content-between mb-3">
                <span class="icon-circle text-info"><i class="fas fa-user-check"></i></span>
                <span class="badge text-bg-light">Assignments</span>
              </div>
              <h6 class="fw-semibold text-dark">Assignments</h6>
              <p class="text-muted small mb-3">Issue assets to employees.</p>
              <div class="d-flex align-items-center justify-content-between">
                <span class="text-muted small">Active Assignments</span>
                <span class="fw-bold fs-5 text-dark"><?php echo $activeAssignments; ?></span>
              </div>
            </div>
          </a>
          <a href="manage_maintenance.php" class="card border-0 shadow-sm action-card text-decoration-none h-100">
            <div class="card-body">
              <div class="d-flex align-items-center justify-content-between mb-3">
                <span class="icon-circle text-warning"><i class="fas fa-tools"></i></span>
                <span class="badge text-bg-light">Maintenance</span>
              </div>
              <h6 class="fw-semibold text-dark">Maintenance Records</h6>
              <p class="text-muted small mb-3">Log and review service tasks.</p>
              <div class="d-flex align-items-center justify-content-between">
                <span class="text-muted small">Maintenance Queue</span>
                <span class="fw-bold fs-5 text-dark"><?php echo $totalMaintenance; ?></span>
              </div>
            </div>
          </a>
        </div>
      </div>

      <div class="row g-3">
        <div class="col-lg-4">
          <div class="card border-0 shadow-sm h-100">
            <div class="card-header border-bottom d-flex justify-content-between align-items-center">
              <h6 class="card-title mb-0">Operational Insights</h6>
              <span class="badge text-bg-light">Today</span>
            </div>
            <div class="card-body">
              <div class="insight-item">
                <div class="d-flex justify-content-between">
                  <div>
                    <div class="text-muted small">Available Assets</div>
                    <div class="fw-semibold fs-5"><?php echo $availableAssets; ?></div>
                  </div>
                  <span class="icon-circle text-success"><i class="fas fa-check"></i></span>
                </div>
              </div>
              <div class="insight-item">
                <div class="d-flex justify-content-between">
                  <div>
                    <div class="text-muted small">Overdue Returns</div>
                    <div class="fw-semibold fs-5"><?php echo $overdueReturns; ?></div>
                  </div>
                  <span class="icon-circle text-danger"><i class="fas fa-exclamation"></i></span>
                </div>
              </div>
              <div class="insight-item">
                <div class="d-flex justify-content-between">
                  <div>
                    <div class="text-muted small">Warranty Expiring (30d)</div>
                    <div class="fw-semibold fs-5"><?php echo $warrantyExpiring; ?></div>
                  </div>
                  <span class="icon-circle text-warning"><i class="fas fa-shield-alt"></i></span>
                </div>
              </div>
              <div class="d-grid gap-2 mt-3">
                <a href="manage_assets.php" class="btn btn-outline-primary btn-sm">Review Assets</a>
                <a href="manage_assignments.php" class="btn btn-outline-secondary btn-sm">Check Assignments</a>
              </div>
            </div>
          </div>
        </div>

        <div class="col-lg-8">
          <div class="card border-0 shadow-sm mb-3">
            <div class="card-header border-bottom d-flex justify-content-between align-items-center">
              <h6 class="card-title mb-0">Recent Assignments</h6>
              <a href="manage_assignments.php" class="btn btn-sm btn-outline-secondary">View All</a>
            </div>
            <div class="card-body p-0">
              <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                  <thead>
                    <tr>
                      <th>Asset</th>
                      <th>Employee</th>
                      <th class="text-end">Assigned</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php if (!empty($recentAssignments)): ?>
                      <?php foreach ($recentAssignments as $row): ?>
                        <tr>
                          <td>
                            <div class="fw-semibold"><?php echo htmlspecialchars($row['AssetName'] ?? ''); ?></div>
                            <small class="text-muted"><?php echo htmlspecialchars($row['AssetSerial'] ?? ''); ?></small>
                          </td>
                          <td><?php echo htmlspecialchars(trim(($row['first_name'] ?? '') . ' ' . ($row['middle_name'] ?? '') . ' ' . ($row['last_name'] ?? ''))); ?></td>
                          <td class="text-end"><?php echo !empty($row['AssignmentDate']) ? date('M d, Y', strtotime($row['AssignmentDate'])) : '-'; ?></td>
                        </tr>
                      <?php endforeach; ?>
                    <?php else: ?>
                      <tr>
                        <td colspan="3" class="text-center text-muted py-4">No recent assignments.</td>
                      </tr>
                    <?php endif; ?>
                  </tbody>
                </table>
              </div>
            </div>
          </div>

          <div class="card border-0 shadow-sm">
            <div class="card-header border-bottom d-flex justify-content-between align-items-center">
              <h6 class="card-title mb-0">Upcoming Maintenance</h6>
              <a href="manage_maintenance.php" class="btn btn-sm btn-outline-secondary">View All</a>
            </div>
            <div class="card-body p-0">
              <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                  <thead>
                    <tr>
                      <th>Asset</th>
                      <th>Type</th>
                      <th class="text-end">Date</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php if (!empty($upcomingMaintenance)): ?>
                      <?php foreach ($upcomingMaintenance as $row): ?>
                        <tr>
                          <td>
                            <div class="fw-semibold"><?php echo htmlspecialchars($row['AssetName'] ?? ''); ?></div>
                            <small class="text-muted"><?php echo htmlspecialchars($row['AssetSerial'] ?? ''); ?></small>
                          </td>
                          <td>
                            <span class="badge text-bg-light"><?php echo htmlspecialchars($row['MaintenanceType'] ?? ''); ?></span>
                          </td>
                          <td class="text-end"><?php echo !empty($row['MaintenanceDate']) ? date('M d, Y', strtotime($row['MaintenanceDate'])) : '-'; ?></td>
                        </tr>
                      <?php endforeach; ?>
                    <?php else: ?>
                      <tr>
                        <td colspan="3" class="text-center text-muted py-4">No upcoming maintenance.</td>
                      </tr>
                    <?php endif; ?>
                  </tbody>
                </table>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

<?php include '../../includes/footer.php'; ?>
<!-- Wrapper div closed in footer.php -->