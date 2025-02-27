<?php
session_start();
$page = 'employees';
$accessRole = isset($_SESSION['role']) ? $_SESSION['role'] : null;
if ($accessRole === '0') {
    header('Location: dashboard.php');
    exit();
}
include 'includes/header.php';
include 'includes/db_connection.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Get form data
    $machId = $_POST['machId'];
    $empBranch = $_POST['empBranch'];
    $empFirstName = $_POST['empFirstName'];
    $empMiddleName = $_POST['empMiddleName'];
    $empLastName = $_POST['empLastName'];
    $empEmail = $_POST['empEmail'];
    $empPhone = $_POST['empPhone'];
    $empJoinDate = $_POST['empJoinDate'];

    // Generate empID based on branch value and auto-increment
    $stmt = $pdo->prepare("SELECT COUNT(*) AS count FROM employees WHERE branch = :branch");
    $stmt->execute([':branch' => $empBranch]);
    $row = $stmt->fetch();
    $count = $row['count'] + 1;
    $empId = $empBranch . str_pad($count, 2, '0', STR_PAD_LEFT);

    // Insert data into the database using prepared statements
    $sql = "INSERT INTO employees (emp_id, mach_id, branch, first_name, middle_name, last_name, email, phone, join_date)
            VALUES (:empId, :machId, :empBranch, :empFirstName, :empMiddleName, :empLastName, :empEmail, :empPhone, :empJoinDate)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':empId' => $empId,
        ':machId' => $machId,
        ':empBranch' => $empBranch,
        ':empFirstName' => $empFirstName,
        ':empMiddleName' => $empMiddleName,
        ':empLastName' => $empLastName,
        ':empEmail' => $empEmail,
        ':empPhone' => $empPhone,
        ':empJoinDate' => $empJoinDate
    ]);

    echo "New record created successfully";

    // Redirect to the same page to prevent form resubmission
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

// Initialize the $employees variable
$employees = [];

// Fetch data from the database "SELECT * FROM employees");
$stmt = $pdo->prepare("SELECT e.*, b.name FROM employees e JOIN branches b ON e.branch = b.id ORDER BY e.id DESC");
$stmt->execute();
$employees = $stmt->fetchAll();
?>
  <!-- DataTables -->
  <link rel="stylesheet" href="<?php echo $home;?>plugins/datatables-bs4/css/dataTables.bootstrap4.min.css">
  <link rel="stylesheet" href="<?php echo $home;?>plugins/datatables-responsive/css/responsive.bootstrap4.min.css">
  <link rel="stylesheet" href="<?php echo $home;?>plugins/datatables-buttons/css/buttons.bootstrap4.min.css">
  
</head>
<body class="hold-transition sidebar-mini layout-footer-fixed layout-navbar-fixed layout-fixed layout-footer-fixed dark-mode">
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
            <h1 class="m-0">Employees</h1>
          </div><!-- /.col -->
          <div class="col-sm-6">
            <ol class="breadcrumb float-sm-right">
              <li class="breadcrumb-item"><a href="#">Home</a></li>
              <li class="breadcrumb-item active">Employees</li>
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
                <h3 class="card-title">Users</h3>
              </div> -->
              <!-- /.card-header -->
              <div class="card-body">
                <table id="user-table" class="table table-bordered table-striped" width="100%">
                  <thead>
                    <tr>
                      <th class="align-items-center text-center">Emp. ID</th>
                      <th class="align-items-center text-left">Employee Details</th>
                      <th class="align-items-center text-left">Personal Contacts</th>
                      <th class="align-items-center text-left">Official Contacts</th>
                      <th class="align-items-center text-center">Branch</th>
                      <th class="align-items-center text-center">Joining Date</th>
                      <th class="align-items-center text-center">Status</th>
                      <th class="align-items-center text-center">Login Access</th>
                      <th class="align-items-center text-center">Action</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach ($employees as $employee): ?>
                    <tr>
                      <td class="align-items-center text-center"><?php echo htmlspecialchars($employee['emp_id']); ?></td>
                      <td class="align-items-center text-left">
                        <div style="display: flex; align-items: center;">
                          <div class="empImage" style="margin-right: 1rem;">
                            <img src="<?php echo htmlspecialchars($employee['user_image']); ?>" alt="Employee Image" class="employee-image" style="width: 50px; height: 50px; border-radius: 50%; object-fit: cover;">
                          </div>
                          <div class="empNameDegi">
                            <b><?php echo htmlspecialchars($employee['first_name'] . ' ' . $employee['middle_name'] . ' ' . $employee['last_name']); ?></b><span><?php echo " (" . htmlspecialchars($employee['gender']) . ")"; ?></span></br><?php echo htmlspecialchars($employee['designation']); ?>
                          </div>
                        </div>
                      </td>
                      <td class="align-items-center text-left"><?php echo htmlspecialchars($employee['phone']); ?></br><?php echo htmlspecialchars($employee['email']); ?></td>
                      <td class="align-items-center text-left">
                        <?php echo $employee['office_phone'] ?  htmlspecialchars($employee['office_phone']) : '-'; ?></br>
                        <?php echo $employee['office_email'] ?  htmlspecialchars($employee['office_email']) : '-'; ?>
                      </td>
                      <td class="align-items-center text-center"><?php echo htmlspecialchars($employee['name']); ?></td>
                      <td class="align-items-center text-center"><?php echo htmlspecialchars($employee['join_date']); ?></td>
                      <td class="align-items-center text-center">
                        <?php echo $employee['exit_date'] ? 'Exited on ' . htmlspecialchars($employee['exit_date']) : 'Working'; ?>
                      </td>
                      <td class="align-items-center text-center"><?php echo $employee['login_access'] ? 'Granted' : 'Denied'; ?></td>
                      <td class="align-items-center text-center">
                        <a href="employee-viewer.php?empId=<?php echo $employee['emp_id']; ?>" class="btn btn-secondary btn-sm">View</a>
                        <a href="edit-employee.php?id=<?php echo $employee['emp_id']; ?>" class="btn btn-primary btn-sm">Edit</a>
                        <a href="delete-employee.php?id=<?php echo $employee['emp_id']; ?>" class="btn btn-danger btn-sm">Delete</a>
                      </td>
                    </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
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
<script src="<?php echo $home;?>plugins/jquery/jquery.min.js"></script>
<!-- Bootstrap -->
<script src="<?php echo $home;?>plugins/bootstrap/js/bootstrap.bundle.min.js"></script>
<!-- DataTables  & Plugins -->
<script src="<?php echo $home;?>plugins/datatables/jquery.dataTables.min.js"></script>
<script src="<?php echo $home;?>plugins/datatables-bs4/js/dataTables.bootstrap4.min.js"></script>
<script src="<?php echo $home;?>plugins/datatables-responsive/js/dataTables.responsive.min.js"></script>
<script src="<?php echo $home;?>plugins/datatables-responsive/js/responsive.bootstrap4.min.js"></script>
<script src="<?php echo $home;?>plugins/datatables-buttons/js/dataTables.buttons.min.js"></script>
<script src="<?php echo $home;?>plugins/datatables-buttons/js/buttons.bootstrap4.min.js"></script>
<script src="<?php echo $home;?>plugins/jszip/jszip.min.js"></script>
<script src="<?php echo $home;?>plugins/pdfmake/pdfmake.min.js"></script>
<script src="<?php echo $home;?>plugins/pdfmake/vfs_fonts.js"></script>
<script src="<?php echo $home;?>plugins/datatables-buttons/js/buttons.html5.min.js"></script>
<script src="<?php echo $home;?>plugins/datatables-buttons/js/buttons.print.min.js"></script>
<script src="<?php echo $home;?>plugins/datatables-buttons/js/buttons.colVis.min.js"></script>
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
      "order": [[5, 'desc']], // [columnIndex, 'asc' or 'desc']
      "info": true,
      "pageLength": 5, // Set the default number of rows to display
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
    $('#user-table_filter').after('<div class="custom-filter-button" style="display: flex; justify-content: flex-end;"><button class="btn btn-primary" id="custom-filter-btn">Add Employee</button></div>');

    // Custom button action
    // $('#custom-filter-btn').on('click', function() {
    //   $('#addUserModal').modal({
    //     backdrop: 'static',
    //     keyboard: false
    //   });
    // });

    // alternativ action of the button
    $('#custom-filter-btn').on('click', function() {
        window.location.href = 'add-employee.php';
    });
  });
</script>
<!-- AdminLTE for demo purposes -->
<script src="<?php echo $home;?>dist/js/demo.js"></script>

<!-- Add User Modal -->
<div class="modal fade" id="addUserModal" tabindex="-1" role="dialog" aria-labelledby="addUserModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-md" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="addUserModalLabel">Add Employee</h5>
      </div>
      <div class="modal-body">
        <form id="addUserForm" method="POST" action="users.php">
          <div class="row">
            <div class="col-md-6">
                  <div class="form-group">
                    <input type="text" class="form-control" id="machId" name="machId" placeholder=" ">
                    <label for="machId" class="form-label">Machine ID</label>
                  </div>
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
              <div class="form-group">
                <input type="text" class="form-control" id="empFirstName" name="empFirstName" placeholder=" " required>
                <label for="empFirstName" class="form-label">First Name <span style="color:red;">*</span></label>
              </div>
              <div class="form-group">
                <input type="text" class="form-control" id="empMiddleName" name="empMiddleName" placeholder=" ">
                <label for="empMiddleName" class="form-label">Middle Name</label>
              </div>
              <div class="form-group">
                <input type="text" class="form-control" id="empLastName" name="empLastName" placeholder=" " required>
                <label for="empLastName" class="form-label">Last Name <span style="color:red;">*</span></label>
              </div>
            </div>
            <div class="col-md-6">
              <div class="form-group">
                <input type="email" class="form-control" id="empEmail" name="empEmail" placeholder=" " required>
                <label for="empEmail" class="form-label">Email <span style="color:red;">*</span></label>
              </div>
              <div class="form-group">
                <input type="text" class="form-control" id="empPhone" name="empPhone" placeholder=" " required>
                <label for="empPhone" class="form-label">Phone <span style="color:red;">*</span></label>
              </div>
              <div class="form-group">
                <input type="date" class="form-control" id="empJoinDate" name="empJoinDate" placeholder=" " value="<?php echo date('Y-m-d'); ?>" min="<?php echo date('Y-m-d', strtotime('-30 days')); ?>" max="<?php echo date('Y-m-d'); ?>" required>
                <label for="empJoinDate" class="form-label">Joining Date <span style="color:red;">*</span></label>
              </div>
            </div>
          </div>
          <div class="detail-form float-left">
            <button type="button" class="btn btn-link" onclick="openDetailedForm()">Open Detailed Form</button>
          </div>
          <div class="action-buttons float-right">
            <button type="button" class="btn btn-danger" data-dismiss="modal" aria-label="Close">Cancel</button>
            <button type="submit" class="btn btn-primary">Save</button>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>

<script>
  function openDetailedForm() {
    const form = document.getElementById('addUserForm');
    const formData = new FormData(form);
    const queryString = new URLSearchParams(formData).toString();
    window.location.href = 'add-employee.php?' + queryString;
  }
</script>
</body>
</html>