<?php
ob_start(); // Start output buffering
$page = 'Search Results';
require_once __DIR__ . '/includes/header.php';
include 'includes/db_connection.php';

// Get search query
$query = isset($_GET['query']) ? trim($_GET['query']) : '';
$results = array();

if (!empty($query)) {
    try {
        // Search employees
        $stmt = $pdo->prepare("SELECT e.*, b.name as branch_name FROM employees e 
                             LEFT JOIN branches b ON e.branch = b.id 
                             WHERE e.first_name LIKE :query 
                             OR e.last_name LIKE :query 
                             OR e.middle_name LIKE :query 
                             OR e.email LIKE :query 
                             OR e.emp_id LIKE :query 
                             OR e.mach_id LIKE :query
                             OR e.designation LIKE :query
                             LIMIT 20");
        $stmt->execute([':query' => '%' . $query . '%']);
        $results['employees'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Search assets
        $stmt = $pdo->prepare("SELECT a.*, c.CategoryName FROM FixedAssets a 
                              LEFT JOIN AssetCategories c ON a.CategoryID = c.CategoryID 
                              WHERE a.AssetName LIKE :query 
                              OR a.AssetSerial LIKE :query
                              OR a.AssetLocation LIKE :query
                              OR c.CategoryName LIKE :query
                              LIMIT 20");
        $stmt->execute([':query' => '%' . $query . '%']);
        $results['assets'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Search attendance logs
        $stmt = $pdo->prepare("SELECT a.*, e.first_name, e.last_name, e.middle_name, e.designation 
                              FROM attendance_logs a 
                              LEFT JOIN employees e ON a.emp_Id = e.emp_id 
                              WHERE e.first_name LIKE :query 
                              OR e.last_name LIKE :query
                              OR a.date LIKE :query
                              OR a.emp_Id LIKE :query
                              OR a.mach_id LIKE :query
                              LIMIT 20");
        $stmt->execute([':query' => '%' . $query . '%']);
        $results['attendance'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
    } catch (PDOException $e) {
        $_SESSION['error'] = "Search error: " . $e->getMessage();
    }
}
?>

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
            <h1 class="m-0">Search Results for "<?php echo htmlspecialchars($query); ?>"</h1>
          </div><!-- /.col -->
          <div class="col-sm-6">
            <ol class="breadcrumb float-sm-right">
              <li class="breadcrumb-item"><a href="#">Home</a></li>
              <li class="breadcrumb-item active">Search Results</li>
            </ol>
          </div><!-- /.col -->
        </div><!-- /.row -->
      </div><!-- /.container-fluid -->
    </div>
    <!-- /.content-header -->

    <!-- Main content -->
    <section class="content">
      <div class="container-fluid">
        <?php if (empty($query)): ?>
            <div class="alert alert-info">
                Please enter a search query in the search box.
            </div>
        <?php else: ?>
            <?php if (empty($results['employees']) && empty($results['assets']) && empty($results['attendance'])): ?>
                <div class="alert alert-warning">
                    No results found for "<?php echo htmlspecialchars($query); ?>".
                </div>
            <?php else: ?>
                <!-- Employees Results -->
                <?php if (!empty($results['employees'])): ?>
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Employees (<?php echo count($results['employees']); ?>)</h3>
                    </div>
                    <div class="card-body">
                        <table class="table table-bordered table-striped">
                            <thead>
                                <tr>
                                    <th>Employee ID</th>
                                    <th>Name</th>
                                    <th>Designation</th>
                                    <th>Branch</th>
                                    <th>Contact</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($results['employees'] as $employee): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($employee['emp_id']); ?></td>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <?php if (!empty($employee['user_image'])): ?>
                                            <img src="<?php echo htmlspecialchars($employee['user_image']); ?>" 
                                                alt="Employee Image" class="mr-2"
                                                style="width: 40px; height: 40px; border-radius: 50%; object-fit: cover;">
                                            <?php endif; ?>
                                            <?php echo htmlspecialchars($employee['first_name'] . ' ' . $employee['middle_name'] . ' ' . $employee['last_name']); ?>
                                        </div>
                                    </td>
                                    <td><?php echo htmlspecialchars($employee['designation'] ?? ''); ?></td>
                                    <td><?php echo htmlspecialchars($employee['branch_name'] ?? ''); ?></td>
                                    <td>
                                        <?php echo htmlspecialchars($employee['phone'] ?? ''); ?><br>
                                        <?php echo htmlspecialchars($employee['email'] ?? ''); ?>
                                    </td>
                                    <td>
                                        <div class="btn-group">
                                            <a href="app/employee-management/employee-viewer.php?empId=<?php echo $employee['emp_id']; ?>" class="btn btn-sm btn-info">
                                                <i class="fas fa-eye"></i> View
                                            </a>
                                            <a href="app/employee-management/edit-employee.php?id=<?php echo $employee['emp_id']; ?>" class="btn btn-sm btn-primary">
                                                <i class="fas fa-edit"></i> Edit
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Assets Results -->
                <?php if (!empty($results['assets'])): ?>
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Assets (<?php echo count($results['assets']); ?>)</h3>
                    </div>
                    <div class="card-body">
                        <table class="table table-bordered table-striped">
                            <thead>
                                <tr>
                                    <th>Serial Number</th>
                                    <th>Asset Name</th>
                                    <th>Category</th>
                                    <th>Location</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($results['assets'] as $asset): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($asset['AssetSerial']); ?></td>
                                    <td><?php echo htmlspecialchars($asset['AssetName']); ?></td>
                                    <td><?php echo htmlspecialchars($asset['CategoryName']); ?></td>
                                    <td><?php echo htmlspecialchars($asset['AssetLocation'] ?? ''); ?></td>
                                    <td>
                                        <span class="badge badge-<?php 
                                            echo $asset['Status'] == 'Available' ? 'success' : 
                                                ($asset['Status'] == 'Maintenance' ? 'warning' : 'danger'); 
                                        ?>">
                                            <?php echo htmlspecialchars($asset['Status']); ?>
                                        </span>
                                    </td>
                                    <td>                                        <a href="modules/assets/manage_assets.php" class="btn btn-sm btn-info">
                                            <i class="fas fa-eye"></i> View
                                        </a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Attendance Results -->
                <?php if (!empty($results['attendance'])): ?>
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Attendance Records (<?php echo count($results['attendance']); ?>)</h3>
                    </div>
                    <div class="card-body">
                        <table class="table table-bordered table-striped">
                            <thead>
                                <tr>
                                    <th>Employee</th>
                                    <th>Date</th>
                                    <th>Time</th>
                                    <th>Method</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($results['attendance'] as $attendance): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($attendance['first_name'] . ' ' . $attendance['last_name']); ?></td>
                                    <td><?php echo htmlspecialchars($attendance['date']); ?></td>
                                    <td><?php echo htmlspecialchars($attendance['time']); ?></td>
                                    <td><?php echo $attendance['method'] == 0 ? 'Auto' : 'Manual'; ?></td>
                                    <td>
                                        <a href="attendance.php" class="btn btn-sm btn-info">
                                            <i class="fas fa-eye"></i> View All Records
                                        </a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <?php endif; ?>
            <?php endif; ?>
        <?php endif; ?>
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
<script src="<?php echo $home;?>plugins/jquery/jquery.min.js"></script>
<!-- Bootstrap -->
<script src="<?php echo $home;?>plugins/bootstrap/js/bootstrap.bundle.min.js"></script>
<!-- AdminLTE -->
<script src="<?php echo $home;?>dist/js/adminlte.js"></script>
<!-- DataTables -->
<script src="<?php echo $home;?>plugins/datatables/jquery.dataTables.min.js"></script>
<script src="<?php echo $home;?>plugins/datatables-bs4/js/dataTables.bootstrap4.min.js"></script>

<script>
$(function() {
    $('.table').DataTable({
        "paging": true,
        "lengthChange": false,
        "searching": false,
        "ordering": true,
        "info": true,
        "autoWidth": false,
        "responsive": true,
        "pageLength": 10
    });
});
</script>

</body>
</html>