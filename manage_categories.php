<?php
ob_start(); // Start output buffering
$page = 'Asset Categories';
require_once __DIR__ . '/includes/header.php';
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
?>
<!-- Page-specific CSS -->
<link rel="stylesheet" href="<?php echo $home;?>plugins/datatables-bs4/css/dataTables.bootstrap4.min.css">
<link rel="stylesheet" href="<?php echo $home;?>plugins/datatables-responsive/css/responsive.bootstrap4.min.css">
<link rel="stylesheet" href="<?php echo $home;?>plugins/datatables-buttons/css/buttons.bootstrap4.min.css">
<style>
  .badge {
    font-size: 85%;
  }
  #categoriesTable td {
    vertical-align: middle;
  }
</style>

<!-- Content Wrapper (already started in header.php) -->
<!-- Main content -->
<div class="container-fluid p-4">
  <!-- Page header -->
  <div class="d-flex justify-content-between align-items-center mb-4">
      <h1 class="fs-2 fw-bold mb-1">Asset Categories</h1>
      <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addCategoryModal">
        <i class="fas fa-plus me-2"></i> Add Category
      </button>
  </div>
  
  <!-- Categories Table Card -->
  <div class="card border-0 shadow-sm">
    <div class="card-body">
      <div class="table-responsive">
        <table id="categoriesTable" class="table table-hover">
          <thead>
            <tr>
              <th>Code</th>
              <th>Category Name</th>
              <th>Description</th>
              <th class="text-center">Assets</th>
              <th class="text-center">Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($categories as $category): ?>
              <tr>
                <td class="fw-bold"><?php echo htmlspecialchars($category['CategoryShortCode']); ?></td>
                <td><?php echo htmlspecialchars($category['CategoryName']); ?></td>
                <td>
                  <?php 
                    $description = htmlspecialchars($category['Description']);
                    echo strlen($description) > 100 ? substr($description, 0, 100) . '...' : $description; 
                  ?>
                </td>
                <td class="text-center">
                  <span class="badge bg-info rounded-pill px-3 py-2">
                    <?php echo $category['asset_count']; ?>
                  </span>
                </td>
                <td class="text-center">
                  <div class="dropdown">
                    <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                      <i class="fas fa-ellipsis-v"></i>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end">
                      <li>
                        <button class="dropdown-item view-category" 
                                data-id="<?php echo $category['CategoryID']; ?>"
                                data-code="<?php echo htmlspecialchars($category['CategoryShortCode']); ?>"
                                data-name="<?php echo htmlspecialchars($category['CategoryName']); ?>"
                                data-description="<?php echo htmlspecialchars($category['Description']); ?>"
                                data-asset-count="<?php echo $category['asset_count']; ?>">
                          <i class="fas fa-eye me-2"></i> View
                        </button>
                      </li>
                      <li>
                        <button class="dropdown-item edit-category" 
                                data-id="<?php echo $category['CategoryID']; ?>"
                                data-code="<?php echo htmlspecialchars($category['CategoryShortCode']); ?>"
                                data-name="<?php echo htmlspecialchars($category['CategoryName']); ?>"
                                data-description="<?php echo htmlspecialchars($category['Description']); ?>">
                          <i class="fas fa-edit me-2"></i> Edit
                        </button>
                      </li>
                      <?php if ($category['asset_count'] == 0): ?>
                      <li><hr class="dropdown-divider"></li>
                      <li>
                        <button class="dropdown-item text-danger delete-category" 
                                data-id="<?php echo $category['CategoryID']; ?>"
                                data-name="<?php echo htmlspecialchars($category['CategoryName']); ?>">
                          <i class="fas fa-trash me-2"></i> Delete
                        </button>
                      </li>
                      <?php endif; ?>
                    </ul>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<?php include 'includes/footer.php'; ?>

<!-- Add Category Modal -->
<div class="modal fade" id="addCategoryModal" tabindex="-1" aria-labelledby="addCategoryModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="addCategoryModalLabel">Add New Category</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form method="POST" action="manage_categories.php">
        <div class="modal-body">
          <input type="hidden" name="action" value="add">
          <div class="mb-3">
            <label for="categoryShortCode" class="form-label">Category Short Code <span class="text-danger">*</span></label>
            <input type="text" class="form-control" id="categoryShortCode" name="categoryShortCode" required>
            <div class="form-text">A unique identifier for this category (e.g., IT, HW, SW)</div>
          </div>
          <div class="mb-3">
            <label for="categoryName" class="form-label">Category Name <span class="text-danger">*</span></label>
            <input type="text" class="form-control" id="categoryName" name="categoryName" required>
          </div>
          <div class="mb-3">
            <label for="description" class="form-label">Description</label>
            <textarea class="form-control" id="description" name="description" rows="3" placeholder="Enter a description of this asset category"></textarea>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary">Save Category</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Edit Category Modal -->
<div class="modal fade" id="editCategoryModal" tabindex="-1" aria-labelledby="editCategoryModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="editCategoryModalLabel">Edit Category</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form method="POST" action="manage_categories.php">
        <div class="modal-body">
          <input type="hidden" name="action" value="edit">
          <input type="hidden" name="categoryId" id="editCategoryId">
          <div class="mb-3">
            <label for="editCategoryShortCode" class="form-label">Category Short Code <span class="text-danger">*</span></label>
            <input type="text" class="form-control" id="editCategoryShortCode" name="categoryShortCode" required>
          </div>
          <div class="mb-3">
            <label for="editCategoryName" class="form-label">Category Name <span class="text-danger">*</span></label>
            <input type="text" class="form-control" id="editCategoryName" name="categoryName" required>
          </div>
          <div class="mb-3">
            <label for="editDescription" class="form-label">Description</label>
            <textarea class="form-control" id="editDescription" name="description" rows="3"></textarea>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary">Update Category</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- View Category Modal -->
<div class="modal fade" id="viewCategoryModal" tabindex="-1" aria-labelledby="viewCategoryModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="viewCategoryModalLabel">Category Details</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div class="row">
          <div class="col-md-6">
            <div class="mb-4">
              <h5 class="fw-bold mb-3">Basic Information</h5>
              <div class="mb-2">
                <span class="text-muted">Category Code:</span>
                <span class="fw-bold ms-2" id="viewCategoryCode"></span>
              </div>
              <div class="mb-2">
                <span class="text-muted">Category Name:</span>
                <span class="fw-bold ms-2" id="viewCategoryName"></span>
              </div>
              <div class="mb-2">
                <span class="text-muted">Total Assets:</span>
                <span class="ms-2">
                  <span class="badge bg-info px-3 py-2 rounded-pill" id="viewAssetCount"></span>
                </span>
              </div>
            </div>
          </div>
          <div class="col-md-6">
            <div class="mb-4">
              <h5 class="fw-bold mb-3">Description</h5>
              <p id="viewCategoryDescription" class="text-muted mb-0"></p>
            </div>
          </div>
        </div>

        <div id="categoryAssetsSection">
          <div class="border-top pt-4 mt-2">
            <h5 class="fw-bold mb-3">Assets in this Category</h5>
            <div id="noCategoryAssets" class="alert alert-info d-none">
              <i class="fas fa-info-circle me-2"></i> No assets have been assigned to this category yet.
            </div>
            <div class="table-responsive">
              <table id="categoryAssetsTable" class="table table-hover">
                <thead>
                  <tr>
                    <th>Asset Name</th>
                    <th>Serial</th>
                    <th>Purchase Date</th>
                    <th class="text-center">Status</th>
                    <th class="text-center">Actions</th>
                  </tr>
                </thead>
                <tbody id="categoryAssetsList">
                  <!-- Assets will be loaded here -->
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
        <button type="button" class="btn btn-primary edit-from-view">
          <i class="fas fa-edit me-2"></i> Edit Category
        </button>
      </div>
    </div>
  </div>
</div>

<!-- Page specific script -->
<script>
document.addEventListener('DOMContentLoaded', function() {
  // Initialize DataTable
  const categoriesTable = new DataTable('#categoriesTable', {
    responsive: true,
    lengthChange: true,
    autoWidth: false,
    order: [[1, 'asc']], // Sort by Category Name by default
    pageLength: 10,
    language: {
      paginate: {
        previous: '<i class="fas fa-chevron-left"></i>',
        next: '<i class="fas fa-chevron-right"></i>'
      }
    },
    columnDefs: [
      { orderable: false, targets: 4 } // Disable sorting on Actions column
    ]
  });

  // Edit category button click
  document.querySelectorAll('.edit-category').forEach(button => {
    button.addEventListener('click', function() {
      const id = this.getAttribute('data-id');
      const code = this.getAttribute('data-code');
      const name = this.getAttribute('data-name');
      const description = this.getAttribute('data-description');
      
      document.getElementById('editCategoryId').value = id;
      document.getElementById('editCategoryShortCode').value = code;
      document.getElementById('editCategoryName').value = name;
      document.getElementById('editDescription').value = description;
      
      const editModal = new bootstrap.Modal(document.getElementById('editCategoryModal'));
      editModal.show();
    });
  });

  // View category button click
  document.querySelectorAll('.view-category').forEach(button => {
    button.addEventListener('click', function() {
      const id = this.getAttribute('data-id');
      const code = this.getAttribute('data-code');
      const name = this.getAttribute('data-name');
      const description = this.getAttribute('data-description');
      const assetCount = this.getAttribute('data-asset-count');
      
      document.getElementById('viewCategoryCode').textContent = code;
      document.getElementById('viewCategoryName').textContent = name;
      document.getElementById('viewAssetCount').textContent = assetCount;
      document.getElementById('viewCategoryDescription').textContent = description || 'No description provided';
      
      // Show or hide assets section based on count
      if (parseInt(assetCount) > 0) {
        document.getElementById('categoryAssetsSection').classList.remove('d-none');
        document.getElementById('noCategoryAssets').classList.add('d-none');
        document.getElementById('categoryAssetsTable').classList.remove('d-none');
        
        // Clear the table body
        document.getElementById('categoryAssetsList').innerHTML = '';
        
        // Load assets for this category via AJAX
        fetch('fetch_assets.php', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
          },
          body: 'categoryId=' + id
        })
        .then(response => response.json())
        .then(data => {
          if (data.data && data.data.length > 0) {
            const tbody = document.getElementById('categoryAssetsList');
            data.data.forEach(asset => {
              const row = document.createElement('tr');
              
              // Asset Name
              const nameCell = document.createElement('td');
              nameCell.textContent = asset.AssetName;
              row.appendChild(nameCell);
              
              // Serial
              const serialCell = document.createElement('td');
              serialCell.textContent = asset.Serial;
              row.appendChild(serialCell);
              
              // Purchase Date
              const dateCell = document.createElement('td');
              dateCell.textContent = asset.PurchaseDate;
              row.appendChild(dateCell);
              
              // Status
              const statusCell = document.createElement('td');
              statusCell.className = 'text-center';
              const badgeType = asset.Status === 'Available' ? 'success' : 
                              (asset.Status === 'Maintenance' ? 'warning' : 'danger');
              statusCell.innerHTML = `<span class="badge bg-${badgeType}">${asset.Status}</span>`;
              row.appendChild(statusCell);
              
              // Actions
              const actionsCell = document.createElement('td');
              actionsCell.className = 'text-center';
              actionsCell.innerHTML = `
                <a href="manage_assets.php?view=${asset.AssetID}" class="btn btn-sm btn-outline-info me-1">
                  <i class="fas fa-eye"></i>
                </a>
                <a href="manage_assets.php?edit=${asset.AssetID}" class="btn btn-sm btn-outline-primary">
                  <i class="fas fa-edit"></i>
                </a>
              `;
              row.appendChild(actionsCell);
              
              tbody.appendChild(row);
            });
          } else {
            document.getElementById('categoryAssetsList').innerHTML = '<tr><td colspan="5" class="text-center">No assets found in this category</td></tr>';
          }
        })
        .catch(error => {
          console.error('Error loading assets:', error);
          document.getElementById('categoryAssetsList').innerHTML = '<tr><td colspan="5" class="text-center text-danger">Error loading assets. Please try again.</td></tr>';
        });
        
      } else {
        document.getElementById('categoryAssetsSection').classList.remove('d-none');
        document.getElementById('noCategoryAssets').classList.remove('d-none');
        document.getElementById('categoryAssetsTable').classList.add('d-none');
      }
      
      const viewModal = new bootstrap.Modal(document.getElementById('viewCategoryModal'));
      viewModal.show();
    });
  });

  // Handle "Edit" button in View modal
  document.querySelector('.edit-from-view').addEventListener('click', function() {
    const viewModal = bootstrap.Modal.getInstance(document.getElementById('viewCategoryModal'));
    viewModal.hide();
    
    // Get data from view modal
    const code = document.getElementById('viewCategoryCode').textContent;
    const name = document.getElementById('viewCategoryName').textContent;
    const description = document.getElementById('viewCategoryDescription').textContent;
    
    // Find the category's ID by matching code and name
    let categoryId = null;
    document.querySelectorAll('.edit-category').forEach(button => {
      if (button.getAttribute('data-code') === code && button.getAttribute('data-name') === name) {
        categoryId = button.getAttribute('data-id');
      }
    });
    
    if (categoryId) {
      // Fill edit modal
      document.getElementById('editCategoryId').value = categoryId;
      document.getElementById('editCategoryShortCode').value = code;
      document.getElementById('editCategoryName').value = name;
      document.getElementById('editDescription').value = description === 'No description provided' ? '' : description;
      
      // Show edit modal
      setTimeout(() => {
        const editModal = new bootstrap.Modal(document.getElementById('editCategoryModal'));
        editModal.show();
      }, 400);
    }
  });

  // Delete category button click
  document.querySelectorAll('.delete-category').forEach(button => {
    button.addEventListener('click', function() {
      const id = this.getAttribute('data-id');
      const name = this.getAttribute('data-name');
      
      if (confirm(`Are you sure you want to delete the category "${name}"? This action cannot be undone.`)) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = 'manage_categories.php';
        
        const actionInput = document.createElement('input');
        actionInput.type = 'hidden';
        actionInput.name = 'action';
        actionInput.value = 'delete';
        form.appendChild(actionInput);
        
        const idInput = document.createElement('input');
        idInput.type = 'hidden';
        idInput.name = 'categoryId';
        idInput.value = id;
        form.appendChild(idInput);
        
        document.body.appendChild(form);
        form.submit();
      }
    });
  });
});
</script>
<script>
    document.addEventListener("DOMContentLoaded", function() {
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.get("addModal") === "open") {
            // Trigger the modal to open
            new bootstrap.Modal(document.getElementById('addCategoryModal')).show();
        }
    });
</script>
</body>
</html>