<?php
ob_start(); // Start output buffering
$page = 'Asset Categories';
include 'includes/header.php';
include 'includes/db_connection.php';

// Check if user is logged in and has admin role
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] != '1') {
    header('Location: index.php');
    exit();
}

// Handle form submissions
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['action'])) {
        $action = $_POST['action'];
        
        if ($action == 'add') {
            $categoryShortCode = $_POST['categoryShortCode'];
            $categoryName = $_POST['categoryName'];
            $description = $_POST['description'];
            
            try {
                $stmt = $pdo->prepare("INSERT INTO AssetCategories (CategoryShortCode, CategoryName, Description) VALUES (:categoryShortCode, :categoryName, :description)");
                $stmt->execute([
                    ':categoryShortCode' => $categoryShortCode,
                    ':categoryName' => $categoryName,
                    ':description' => $description
                ]);
                
                $_SESSION['success'] = "Category added successfully!";
            } catch (PDOException $e) {
                $_SESSION['error'] = "Error adding category: " . $e->getMessage();
            }
        } 
        elseif ($action == 'edit') {
            $categoryId = $_POST['categoryId'];
            $categoryShortCode = $_POST['categoryShortCode'];
            $categoryName = $_POST['categoryName'];
            $description = $_POST['description'];
            
            try {
                $stmt = $pdo->prepare("UPDATE AssetCategories SET CategoryShortCode = :categoryShortCode, CategoryName = :categoryName, Description = :description WHERE CategoryID = :categoryId");
                $stmt->execute([
                    ':categoryId' => $categoryId,
                    ':categoryShortCode' => $categoryShortCode,
                    ':categoryName' => $categoryName,
                    ':description' => $description
                ]);
                
                if ($stmt->rowCount() > 0) {
                    $_SESSION['success'] = "Category updated successfully!";
                } else {
                    $_SESSION['error'] = "No changes made or category not found.";
                }
            } catch (PDOException $e) {
                $_SESSION['error'] = "Error updating category: " . $e->getMessage();
            }
        }
        elseif ($action == 'delete') {
            $categoryId = $_POST['categoryId'];
            
            try {
                // Check if category has any assets
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM fixedassets WHERE CategoryID = :categoryId");
                $stmt->execute([':categoryId' => $categoryId]);
                $assetCount = $stmt->fetchColumn();
                
                if ($assetCount > 0) {
                    $_SESSION['error'] = "Cannot delete category. There are assets associated with this category.";
                } else {
                    $stmt = $pdo->prepare("DELETE FROM AssetCategories WHERE CategoryID = :categoryId");
                    $stmt->execute([':categoryId' => $categoryId]);
                    
                    $_SESSION['success'] = "Category deleted successfully!";
                }
            } catch (PDOException $e) {
                $_SESSION['error'] = "Error deleting category: " . $e->getMessage();
            }
            
            // Store the message in session and redirect
            header("Location: manage_categories.php");
            exit();
        }
        
        // Redirect to prevent form resubmission
        header("Location: manage_categories.php");
        exit();
    }
}

// Fetch all categories with asset count
try {
    $stmt = $pdo->query("SELECT c.*, 
                         (SELECT COUNT(*) FROM fixedassets WHERE CategoryID = c.CategoryID) as asset_count 
                         FROM AssetCategories c 
                         ORDER BY c.CategoryName");
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $_SESSION['error'] = "Error fetching categories: " . $e->getMessage();
    $categories = [];
}

// Show toast notification if there's a session message
if (isset($_SESSION['success']) || isset($_SESSION['error'])) {
    $message = isset($_SESSION['success']) ? $_SESSION['success'] : $_SESSION['error'];
    $type = isset($_SESSION['success']) ? 'success' : 'error';
    echo "<script>
        $(document).ready(function() {
            if ('$type' === 'success') {
                showSuccessToast('$message');
            } else {
                showErrorToast('$message');
            }
        });
    </script>";
    unset($_SESSION['success']);
    unset($_SESSION['error']);
}
?>
<!-- DataTables -->
<link rel="stylesheet" href="<?php echo $home;?>plugins/datatables-bs4/css/dataTables.bootstrap4.min.css">
<link rel="stylesheet" href="<?php echo $home;?>plugins/datatables-responsive/css/responsive.bootstrap4.min.css">
<link rel="stylesheet" href="<?php echo $home;?>plugins/datatables-buttons/css/buttons.bootstrap4.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
<!-- Add these in the head section after other CSS links -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
<style>
  .swal2-toast {
    font-size: 0.875rem !important;
  }
  .swal2-title-custom {
    font-size: 1.2rem !important;
    margin: 0 !important;
  }
  .swal2-toast {
    font-size: 1.1rem !important;
  }
</style>
<body class="hold-transition sidebar-mini layout-footer-fixed layout-navbar-fixed layout-fixed dark-mode">
<div class="wrapper">
  <?php include 'includes/topbar.php';
    include 'includes/sidebar.php';
  ?>
  <!-- Content Wrapper. Contains page content -->
  <div class="content-wrapper">
    <!-- Content Header (Page header) -->
    <div class="content-header">
      <div class="container-fluid">
        <div class="row mb-2">
          <div class="col-sm-6">
            <h1 class="m-0">Asset Categories</h1>
          </div>
          <div class="col-sm-6">
            <ol class="breadcrumb float-sm-right">
              <li class="breadcrumb-item"><a href="assets.php">Assets</a></li>
              <li class="breadcrumb-item active">Categories</li>
            </ol>
          </div>
        </div>
      </div>
    </div>
    <!-- Main content -->
    <section class="content">
      <div class="container-fluid">
        <div class="card">
          <div class="card-body">
            <table id="categoriesTable" class="table table-bordered table-striped">
              <thead>
                <tr>
                  <th>ID</th>
                  <th>Category Short Code</th>
                  <th>Category Name</th>
                  <th>Description</th>
                  <th>Total Assets</th>
                  <th>Actions</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($categories as $category): ?>
                  <tr data-asset-count="<?php echo $category['asset_count']; ?>">
                    <td><?php echo $category['CategoryID']; ?></td>
                    <td><?php echo htmlspecialchars($category['CategoryShortCode']); ?></td>
                    <td><?php echo htmlspecialchars($category['CategoryName']); ?></td>
                    <td><?php echo htmlspecialchars($category['Description']); ?></td>
                    <td>
                      <span class="badge badge-info">
                        <?php echo $category['asset_count']; ?>
                      </span>
                    </td>
                    <td>
                      <div class="dropdown">
                        <button class="btn btn-link btn-sm p-0 text-dark" type="button" id="dropdownMenuButton" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                          <i class="fas fa-ellipsis-v"></i>
                        </button>
                        <div class="dropdown-menu dropdown-menu-right" aria-labelledby="dropdownMenuButton">
                          <button class="dropdown-item edit-category" 
                                  data-id="<?php echo $category['CategoryID']; ?>"
                                  data-code="<?php echo htmlspecialchars($category['CategoryShortCode']); ?>"
                                  data-name="<?php echo htmlspecialchars($category['CategoryName']); ?>"
                                  data-description="<?php echo htmlspecialchars($category['Description']); ?>">
                            <i class="fas fa-edit mr-2"></i> Edit
                          </button>
                          <?php if ($category['asset_count'] == 0): ?>
                          <button class="dropdown-item delete-category" 
                                  data-id="<?php echo $category['CategoryID']; ?>"
                                  data-name="<?php echo htmlspecialchars($category['CategoryName']); ?>">
                            <i class="fas fa-trash mr-2"></i> Delete
                          </button>
                          <?php endif; ?>
                        </div>
                      </div>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </section>
  </div>
</div>
<?php include 'includes/footer.php'; ?>

<!-- Add Category Modal -->
<div class="modal fade" id="addCategoryModal" tabindex="-1" role="dialog" aria-labelledby="addCategoryModalLabel" aria-hidden="true">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="addCategoryModalLabel">Add New Category</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <form method="POST" action="manage_categories.php">
        <div class="modal-body">
          <input type="hidden" name="action" value="add">
          <div class="form-group">
            <label for="categoryShortCode">Category Short Code</label>
            <input type="text" class="form-control" id="categoryShortCode" name="categoryShortCode" required>
          </div>
          <div class="form-group">
            <label for="categoryName">Category Name</label>
            <input type="text" class="form-control" id="categoryName" name="categoryName" required>
          </div>
          <div class="form-group">
            <label for="description">Description</label>
            <textarea class="form-control" id="description" name="description" rows="3"></textarea>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary">Save Category</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Edit Category Modal -->
<div class="modal fade" id="editCategoryModal" tabindex="-1" role="dialog" aria-labelledby="editCategoryModalLabel" aria-hidden="true">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="editCategoryModalLabel">Edit Category</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <form method="POST" action="manage_categories.php">
        <div class="modal-body">
          <input type="hidden" name="action" value="edit">
          <input type="hidden" name="categoryId" id="editCategoryId">
          <div class="form-group">
            <label for="editCategoryShortCode">Category Short Code</label>
            <input type="text" class="form-control" id="editCategoryShortCode" name="categoryShortCode" required>
          </div>
          <div class="form-group">
            <label for="editCategoryName">Category Name</label>
            <input type="text" class="form-control" id="editCategoryName" name="categoryName" required>
          </div>
          <div class="form-group">
            <label for="editDescription">Description</label>
            <textarea class="form-control" id="editDescription" name="description" rows="3"></textarea>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary">Update Category</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- REQUIRED SCRIPTS -->
<!-- jQuery -->
<script src="<?php echo $home;?>plugins/jquery/jquery.min.js"></script>
<!-- Bootstrap -->
<script src="<?php echo $home;?>plugins/bootstrap/js/bootstrap.bundle.min.js"></script>
<!-- DataTables & Plugins -->
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
<!-- Add these before the closing body tag -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
$(document).ready(function() {
    // Initialize DataTable
    $("#categoriesTable").DataTable({
      "responsive": true,
      "lengthChange": true,
      "autoWidth": false,
      "paging": true,
      "searching": true,
      "ordering": true,
      "order": [[1, 'asc']], // Sort by Category Name by default
      "info": true,
      "pageLength": 10, // Set the default number of rows to display
      "lengthMenu": [ [10, 25, 50, -1], [10, 25, 50, "All"] ], // Define the options in the page length dropdown menu
      "pagingType": "full_numbers", // Controls the pagination controls' appearance
      "buttons": ["colvis"], // Add column visibility button
      "columnDefs": [
        {
          "targets": -1, // Last column (Actions)
          "orderable": false
        }
      ],
      "language": {
        "emptyTable": "No data available in table",
        "info": "Showing _START_ to _END_ of _TOTAL_ entries",
        "infoEmpty": "Showing 0 to 0 of 0 entries",
        "infoFiltered": "(filtered from _MAX_ total entries)",
        "lengthMenu": "Show _MENU_ entries",
        "loadingRecords": "Loading...",
        "processing": "Processing...",
        "search": "Search:",
        "zeroRecords": "No matching records found",
        "paginate": {
          "first": '<i class="fas fa-angle-double-left"></i>',
          "previous": '<i class="fas fa-angle-left"></i>',
          "next": '<i class="fas fa-angle-right"></i>',
          "last": '<i class="fas fa-angle-double-right"></i>'
        }
      }
    }).buttons().container().appendTo('#categoriesTable_wrapper .col-md-6:eq(0)');

    // Add custom button below the filter
    $('#categoriesTable_filter').after('<div class="custom-filter-button" style="display: flex; justify-content: flex-end;"><button class="btn btn-primary" id="custom-filter-btn"><i class="fas fa-tags"></i> Add Category</button></div>');

    // Custom button action
    $('#custom-filter-btn').on('click', function() {
      $('#addCategoryModal').modal({
        backdrop: 'static',
        keyboard: false
      });
    });

    // Edit category button click - use event delegation for dynamically created buttons
    $(document).on('click', '.edit-category', function() {
      var id = $(this).data('id');
      var code = $(this).data('code');
      var name = $(this).data('name');
      var description = $(this).data('description');
      
      $('#editCategoryId').val(id);
      $('#editCategoryShortCode').val(code);
      $('#editCategoryName').val(name);
      $('#editDescription').val(description);
      
      $('#editCategoryModal').modal('show');
    });

    // Display success message if exists
    <?php if (isset($_SESSION['success'])): ?>
    const Toast = Swal.mixin({
        toast: true,
        position: 'top-end',
        showConfirmButton: false,
        timer: 3000,
        timerProgressBar: true,
        didOpen: (toast) => {
            toast.addEventListener('mouseenter', Swal.stopTimer)
            toast.addEventListener('mouseleave', Swal.resumeTimer)
        }
    });
    Toast.fire({
        icon: 'success',
        title: '<?php echo $_SESSION['success']; unset($_SESSION['success']); ?>'
    });
    <?php endif; ?>

    // Display error message if exists
    <?php if (isset($_SESSION['error'])): ?>
    const Toast = Swal.mixin({
        toast: true,
        position: 'top-end',
        showConfirmButton: false,
        timer: 3000,
        timerProgressBar: true,
        didOpen: (toast) => {
            toast.addEventListener('mouseenter', Swal.stopTimer)
            toast.addEventListener('mouseleave', Swal.resumeTimer)
        }
    });
    Toast.fire({
        icon: 'error',
        title: '<?php echo $_SESSION['error']; unset($_SESSION['error']); ?>'
    });
    <?php endif; ?>

    // Delete category button click
    $('.delete-category').click(function() {
        var id = $(this).data('id');
        var name = $(this).data('name');
        
        Swal.fire({
            title: 'Are you sure?',
            text: "You won't be able to revert this!",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#3085d6',
            cancelButtonColor: '#d33',
            confirmButtonText: 'Yes, delete it!'
        }).then((result) => {
            if (result.isConfirmed) {
                // Send AJAX request
                $.ajax({
                    url: 'manage_categories.php',
                    type: 'POST',
                    data: {
                        action: 'delete',
                        categoryId: id
                    },
                    success: function(response) {
                        // Reload the page to show the updated list
                        window.location.reload();
                    },
                    error: function(xhr, status, error) {
                        showErrorToast('Error deleting category: ' + error);
                    }
                });
            }
        });
    });

    // Initialize tooltips
    $('[title]').tooltip();
});
</script>
</body>
</html>