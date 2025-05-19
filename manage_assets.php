<?php
$page = 'Manage Assets';
require_once __DIR__ . '/includes/session_config.php';
require_once __DIR__ . '/includes/utilities.php';


include 'includes/db_connection.php';
require_once 'manage_assets_handler.php';
include 'includes/header.php';
?>

<!-- Main content -->
<div class="container-fluid p-4">
  <!-- Page header -->
  <div class="d-flex justify-content-between align-items-center mb-4">
    <div>
      <h1 class="fs-2 fw-bold mb-1">Fixed Assets</h1>
    </div>
    <div>
      <a href="manage_categories.php?addModal=open" class="btn btn-outline-secondary">
        <i class="fas fa-plus me-2"></i> Add Category
      </a>
      <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addAssetModal">
        <i class="fas fa-plus me-2"></i> Add Asset
      </button>
    </div>
  </div>
  
  <!-- Alert messages -->
  <?php if (isset($_SESSION['success'])): ?>
    <div class="alert alert-success alert-dismissible fade show">
      <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
      <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
    </div>
  <?php endif; ?>
  
  <?php if (isset($_SESSION['error'])): ?>
    <div class="alert alert-danger alert-dismissible fade show">
      <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
      <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
    </div>
  <?php endif; ?>
  
  <!-- Assets Table Card -->
  <div class="card border-0 shadow-sm">
    <div class="card-body">
      <!-- Filter controls -->
      <div class="filter-controls p-3 mb-4">
        <div class="row">
          <div class="col-md-3">
            <label for="statusFilter" class="form-label fw-bold">Status Filter</label>
            <select id="statusFilter" class="form-select" title="Filter by status">
              <option value="">All Statuses</option>
              <option value="Available">Available</option>
              <option value="Maintenance">Maintenance</option>
              <option value="Assigned">Assigned</option>
              <option value="Disposed">Disposed</option>
            </select>
          </div>
          <div class="col-md-3">
            <label for="categoryFilter" class="form-label fw-bold">Category Filter</label>
            <select id="categoryFilter" class="form-select" title="Filter by category">
              <option value="">All Categories</option>
              <?php foreach ($categories as $category): ?>
                <option value="<?php echo htmlspecialchars($category['CategoryName']); ?>"><?php echo htmlspecialchars($category['CategoryName']); ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-3">
            <label for="searchFilter" class="form-label fw-bold">Search</label>
            <div class="input-group">
              <input type="text" id="searchFilter" class="form-control" placeholder="Search assets...">
              <button class="btn btn-outline-secondary" type="button" id="clearSearch">
                <i class="fas fa-times"></i>
              </button>
            </div>
          </div>
          <div class="col-md-3 d-flex align-items-end">
            <button type="button" id="resetFilters" class="btn btn-outline-secondary">
              <i class="fas fa-redo-alt me-1"></i> Reset Filters
            </button>
          </div>
        </div>
      </div>

      <div class="table-responsive">
        <table id="assetsTable" class="table table-hover">
          <thead>
            <tr>
              <th title="Unique serial number for each asset">Serial Number</th>
              <th title="Asset name and image">Asset</th>
              <th title="Date of purchase">Purchase Date</th>
              <th title="Purchase cost in NPR">Purchase Cost</th>
              <th title="Warranty end date">Warranty End Date</th>
              <th title="Current condition of asset">Condition</th>
              <th class="text-center" title="Current status (Available, Maintenance, Assigned, etc.)">Status</th>
              <th class="text-center">Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($assets as $asset): ?>
              <tr data-category="<?php echo htmlspecialchars($asset['CategoryName']); ?>">
                <td class="align-middle"><?php echo $asset['AssetSerial']; ?></td>
                <td>
                  <div class="d-flex align-items-center">
                    <?php if (!empty($asset['AssetImage']) && file_exists($asset['AssetImage'])): ?>
                      <img src="<?php echo $asset['AssetImage']; ?>" 
                          alt="<?php echo htmlspecialchars($asset['AssetName']); ?>" 
                          class="rounded me-3" 
                          style="width: 40px; height: 40px; object-fit: cover;">
                    <?php else: ?>
                      <div class="default-asset-icon no-image-icon rounded me-3" style="width: 40px; height: 40px; font-size:14px;">
                        <i class="fas fa-boxes"></i>
                      </div>
                    <?php endif; ?>
                    <div>
                      <div class="fw-bold"><?php echo htmlspecialchars($asset['AssetName']); ?></div>
                      <small class="text-muted"><?php echo htmlspecialchars($asset['CategoryName']); ?></small>
                    </div>
                  </div>
                </td>
                <td class="align-middle"><?php echo date('M d, Y', strtotime($asset['PurchaseDate'])); ?></td>
                <td class="align-middle">Rs. <?php echo number_format($asset['PurchaseCost'], 2); ?></td>
                <td class="align-middle"><?php echo $asset['WarrantyEndDate'] ? date('M d, Y', strtotime($asset['WarrantyEndDate'])) : '-'; ?></td>
                <td class="align-middle"><?php echo htmlspecialchars($asset['AssetCondition'] ? $asset['AssetCondition'] : '-'); ?></td>
                <td class="text-center align-middle">
                  <span class="badge <?php 
                      echo $asset['Status'] == 'Available' ? 'bg-success' : 
                          ($asset['Status'] == 'Maintenance' ? 'bg-warning' : 'bg-danger'); 
                  ?>">
                    <?php echo $asset['Status']; ?>
                  </span>
                </td>
                <td class="text-center align-middle">
                  <div class="dropdown">
                    <a href="#" class="text-secondary" role="button" id="dropdownMenuButton<?php echo $asset['AssetID']; ?>" data-bs-toggle="dropdown" aria-expanded="false">
                      <i class="fas fa-ellipsis-v"></i>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="dropdownMenuButton<?php echo $asset['AssetID']; ?>">
                      <li><a class="dropdown-item view-asset" href="#" data-bs-toggle="modal" data-bs-target="#viewAssetModal"
                            data-id="<?php echo $asset['AssetID']; ?>"
                            data-name="<?php echo htmlspecialchars($asset['AssetName']); ?>"
                            data-category="<?php echo htmlspecialchars($asset['CategoryName']); ?>"
                            data-serial="<?php echo $asset['AssetSerial']; ?>"
                            data-purchase-date="<?php echo date('d M Y', strtotime($asset['PurchaseDate'])); ?>"
                            data-purchase-cost="Rs. <?php echo number_format($asset['PurchaseCost'], 2); ?>"
                            data-warranty-end="<?php echo !empty($asset['WarrantyEndDate']) ? date('d M Y', strtotime($asset['WarrantyEndDate'])) : '-'; ?>"
                            data-condition="<?php echo htmlspecialchars($asset['AssetCondition'] ?? ''); ?>"
                            data-location="<?php echo htmlspecialchars($asset['AssetLocation'] ?? ''); ?>"
                            data-status="<?php echo $asset['Status'] ?? ''; ?>"
                            data-description="<?php echo htmlspecialchars($asset['AssetsDescription'] ?? ''); ?>"
                            data-image="<?php echo !empty($asset['AssetImage']) ? $asset['AssetImage'] : ''; ?>">
                        <i class="fas fa-eye me-2"></i> View</a></li>
                      <li><a class="dropdown-item edit-asset" href="#" data-bs-toggle="modal" data-bs-target="#editAssetModal"
                            data-id="<?php echo $asset['AssetID']; ?>"
                            data-name="<?php echo htmlspecialchars($asset['AssetName']); ?>"
                            data-category-id="<?php echo $asset['CategoryID']; ?>"
                            data-purchase-date="<?php echo $asset['PurchaseDate']; ?>"
                            data-purchase-cost="<?php echo $asset['PurchaseCost']; ?>"
                            data-warranty-end="<?php echo $asset['WarrantyEndDate']; ?>"
                            data-condition="<?php echo htmlspecialchars($asset['AssetCondition'] ?? ''); ?>"
                            data-location="<?php echo htmlspecialchars($asset['AssetLocation'] ?? ''); ?>"
                            data-description="<?php echo htmlspecialchars($asset['AssetsDescription'] ?? ''); ?>"
                            data-image="<?php echo !empty($asset['AssetImage']) ? $asset['AssetImage'] : ''; ?>">
                        <i class="fas fa-edit me-2"></i> Edit</a></li>
                      <li><a class="dropdown-item print-sticker" href="#"
                              data-id="<?php echo $asset['AssetID']; ?>"
                              data-name="<?php echo htmlspecialchars($asset['AssetName']); ?>"
                              data-serial="<?php echo $asset['AssetSerial']; ?>">
                        <i class="fas fa-print me-2"></i> Print Sticker</a></li>
                      <li><hr class="dropdown-divider"></li>
                      <li><a class="dropdown-item text-danger delete-asset" href="#" data-bs-toggle="modal" data-bs-target="#deleteAssetModal"
                            data-id="<?php echo $asset['AssetID']; ?>"
                            data-name="<?php echo htmlspecialchars($asset['AssetName']); ?>">
                        <i class="fas fa-trash me-2"></i> Delete</a></li>
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
</div> <!-- /.container-fluid -->

<?php 
include 'includes/footer.php';
?>

<!-- Page Specific Scripts - moved after footer include -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.12/cropper.min.js"></script>

<script>
$(document).ready(function() {
  // Initialize DataTable with consistent styling as employees.php
  const assetsTable = new DataTable('#assetsTable', {
    responsive: true,
    lengthChange: true,
    autoWidth: false,
    order: [[2, 'desc']], // Sort by purchase date
    pageLength: 10,
    language: {
      paginate: {
        previous: '<i class="fas fa-chevron-left"></i>',
        next: '<i class="fas fa-chevron-right"></i>'
      }
    }
  });
  
  // Add Asset button handler (update for the new button ID)
  $('#add-asset-btn').on('click', function() {
    const modal = new bootstrap.Modal(document.getElementById('addAssetModal'));
    modal.show();
  });
  
  // Status filter change event
  $('#statusFilter').on('change', function() {
    const table = assetsTable;
    const status = this.value;
    
    // Custom filtering function for status column
    $.fn.dataTable.ext.search.push(
      function(settings, data, dataIndex) {
        if (!status || status === '') {
          return true;
        }
        // Extract the status text from the badge in the 6th column (index 6)
        const statusCell = $(table.row(dataIndex).node()).find('td:eq(6)');
        const statusText = statusCell.find('.badge').text().trim();
        return statusText === status;
      }
    );
    
    table.draw();
    $.fn.dataTable.ext.search.pop();
  });
  
  // Category filter change event
  $('#categoryFilter').on('change', function() {
    const table = assetsTable;
    const category = this.value;
    
    // Custom filtering function for category
    $.fn.dataTable.ext.search.push(
      function(settings, data, dataIndex) {
        if (!category || category === '') {
          return true;
        }
        const tr = table.row(dataIndex).node();
        const rowCategory = $(tr).data('category');
        return rowCategory === category;
      }
    );
    
    table.draw();
    $.fn.dataTable.ext.search.pop();
  });

  // Search filter functionality
  $('#searchFilter').on('keyup', function() {
    assetsTable.search(this.value).draw();
  });
  
  // Clear search button
  $('#clearSearch').on('click', function() {
    $('#searchFilter').val('').trigger('keyup');
  });
  
  // Reset all filters button
  $('#resetFilters').on('click', function() {
    $('#statusFilter').val('').trigger('change');
    $('#categoryFilter').val('').trigger('change');
    $('#searchFilter').val('').trigger('keyup');
  });

  let cropper;
  let currentImageInput;
  let currentImagePreview;

  // Function to initialize cropper
  function initializeCropper(input, preview) {
    if (input.files && input.files[0]) {
      const reader = new FileReader();
      reader.onload = function(e) {
        $('#imageToCrop').attr('src', e.target.result);
        const modal = new bootstrap.Modal(document.getElementById('imageCropModal'));
        modal.show();
        
        // Destroy previous cropper instance if exists
        if (cropper) {
          cropper.destroy();
        }
        
        // Initialize new cropper with better options
        cropper = new Cropper(document.getElementById('imageToCrop'), {
          aspectRatio: 1,
          viewMode: 2,
          autoCropArea: 1,
          responsive: true,
          restore: false,
          modal: true,
          guides: true,
          highlight: true,
          cropBoxMovable: true,
          cropBoxResizable: true,
          minContainerWidth: 300,
          minContainerHeight: 300,
          preview: preview
        });
      };
      reader.readAsDataURL(input.files[0]);
    }
  }

  // Handle image selection for add form
  $('#assetImage').change(function() {
    currentImageInput = this;
    currentImagePreview = '#imagePreview';
    $('#addDefaultIcon').hide();
    $(currentImagePreview).show();
    initializeCropper(this, currentImagePreview);
  });

  // Handle image selection for edit form
  $('#newAssetImage').change(function() {
    currentImageInput = this;
    currentImagePreview = '#editAssetImage';
    $('#editDefaultIcon').hide();
    $(currentImagePreview).show();
    initializeCropper(this, currentImagePreview);
  });

  // Handle crop button click
  $('#cropImageBtn').click(function() {
    if (cropper) {
      const canvas = cropper.getCroppedCanvas({
        width: 400,
        height: 400
      });
      
      if (canvas) {
        canvas.toBlob(function(blob) {
          const url = URL.createObjectURL(blob);
          
          // Update the preview image
          $(currentImagePreview).attr('src', url).show();
          
          // For browsers that don't support DataTransfer, we'll use a different approach
          try {
            // Try the modern approach first
            const file = new File([blob], 'cropped-image.jpg', { type: 'image/jpeg' });
            
            // Handle file transfer to the input in a cross-browser way
            if ('DataTransfer' in window) {
              const dataTransfer = new DataTransfer();
              dataTransfer.items.add(file);
              currentImageInput.files = dataTransfer.files;
            } else {
              // For browsers without DataTransfer support, use a hidden input approach
              // and track the image data using a hidden field to pass to the server
              $('#croppedImage').val(canvas.toDataURL('image/jpeg'));
            }
            
            // Update the hidden currentImage field with the path
            const imageName = 'cropped-' + Date.now() + '.jpg';
            if (currentImagePreview === '#imagePreview') {
              $('#currentImage').val('resources/assetsimages/' + imageName);
            } else if (currentImagePreview === '#editAssetImage') {
              $('#currentImage').val('resources/assetsimages/' + imageName);
            }
          } catch (e) {
            console.error("Browser doesn't support File API or DataTransfer:", e);
            // Fallback: Store the image data directly
            $('#croppedImage').val(canvas.toDataURL('image/jpeg'));
          }
          
          const modal = bootstrap.Modal.getInstance(document.getElementById('imageCropModal'));
          modal.hide();
        }, 'image/jpeg');
      }
    }
  });

  // Reset image preview when modal is closed
  $('#addAssetModal').on('hidden.bs.modal', function () {
    $('#addDefaultIcon').show();
    $('#imagePreview').hide();
  });

  $('#editAssetModal').on('hidden.bs.modal', function () {
    $('#editDefaultIcon').show();
    $('#editAssetImage').hide();
  });

  // View asset button click - updated for Bootstrap 5
  $(document).on('click', '.view-asset', function() {
    const assetData = $(this).data();
    
    // Update modal content
    $('#viewAssetName').text(assetData.name);
    $('#viewCategory').text(assetData.category);
    $('#viewSerial').text(assetData.serial);
    $('#viewPurchaseDate').text(assetData.purchaseDate);
    $('#viewPurchaseCost').text(assetData.purchaseCost);
    $('#viewWarrantyEnd').text(assetData.warrantyEnd);
    $('#viewCondition').text(assetData.condition);
    $('#viewLocation').text(assetData.location);
    $('#viewStatus').text(assetData.status);
    $('#viewDescription').text(assetData.description);
    
    // Update image
    const imagePath = assetData.image;
    if (imagePath && imagePath.length > 0) {
      $('#viewAssetImage').attr('src', imagePath).show();
      $('#viewDefaultIcon').hide();
    } else {
      $('#viewAssetImage').hide();
      $('#viewDefaultIcon').show();
    }
  });

  // Edit asset button click - updated for Bootstrap 5
  $(document).on('click', '.edit-asset', function() {
    const assetData = $(this).data();
    $('#editAssetId').val(assetData.id);
    $('#editAssetName').val(assetData.name);
    $('#editCategoryId').val(assetData.categoryId);
    $('#editPurchaseDate').val(assetData.purchaseDate);
    $('#editPurchaseCost').val(assetData.purchaseCost);
    $('#editWarrantyEndDate').val(assetData.warrantyEnd);
    $('#editAssetCondition').val(assetData.condition);
    $('#editAssetLocation').val(assetData.location);
    $('#editDescription').val(assetData.description);
    $('#currentImage').val(assetData.image);
    $('#editStatus').val(assetData.status);
    
    // Update edit modal image
    const imagePath = assetData.image;
    if (imagePath && imagePath.length > 0) {
      $('#editAssetImage').attr('src', imagePath).show();
      $('#editDefaultIcon').hide();
    } else {
      $('#editAssetImage').hide();
      $('#editDefaultIcon').show();
    }
  });

  // Handle edit form submission
  $('#editAssetForm').on('submit', function(e) {
    e.preventDefault();
    
    // Get form data
    const formData = new FormData(this);
    
    // Send AJAX request
    $.ajax({
      url: 'manage_assets.php',
      type: 'POST',
      data: formData,
      processData: false,
      contentType: false,
      success: function(response) {
        // Close the modal
        const modal = bootstrap.Modal.getInstance(document.getElementById('editAssetModal'));
        modal.hide();
        // Reload the page to show updated data
        window.location.reload();
      },
      error: function(xhr, status, error) {
        alert('Error updating asset: ' + error);
      }
    });
  });

  // Delete asset button click
  $(document).on('click', '.delete-asset', function() {
    const assetData = $(this).data();
    $('#deleteAssetId').val(assetData.id);
    $('#deleteAssetName').text(assetData.name);
  });

  // Print Sticker button click
  $(document).on('click', '.print-sticker', function(e) {
    e.preventDefault();
    const button = $(this);
    const assetData = button.data();
    const assetName = assetData.name;
    const serialNumber = assetData.serial;

    if (!assetName || !serialNumber) {
      console.error("Error: Could not retrieve name or serial from button data.", assetData);
      alert("Error retrieving asset details for printing. Please try again.");
      return;
    }

    // Directly call the function to open the print window
    printSticker(assetName, serialNumber);
  });
});
</script>

<!-- Add Asset Modal -->
<div class="modal fade" id="addAssetModal" tabindex="-1" aria-labelledby="addAssetModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="addAssetModalLabel">Add New Asset</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form method="POST" action="manage_assets.php" enctype="multipart/form-data">
        <div class="modal-body">
          <input type="hidden" name="action" value="add">
          <div class="row">
            <div class="col-md-4 text-center">
              <div class="asset-image-container">
                <img id="imagePreview" src="" class="asset-image" style="display: none;">
                <div id="addDefaultIcon" class="default-asset-icon">
                  <i class="fas fa-boxes"></i>
                </div>
                <div class="upload-overlay">
                  <button type="button" onclick="document.getElementById('assetImage').click();">
                    <i class="fas fa-pencil-alt"></i><span class="text-sm"> Change</span>
                  </button>
                </div>
              </div>
              <input type="file" class="form-control-file" id="assetImage" name="assetImage" accept="image/*" style="display: none;">
              <input type="hidden" id="croppedImage" name="croppedImage">
            </div>
            <div class="col-md-8">
              <div class="mb-3">
                <label for="assetName" class="form-label">Asset Name</label>
                <input type="text" class="form-control" id="assetName" name="assetName" required>
              </div>
              <div class="mb-3">
                <label for="categoryId" class="form-label">Category</label>
                <select class="form-select" id="categoryId" name="categoryId" required>
                  <option value="">Select Category</option>
                  <?php foreach ($categories as $category): ?>
                    <option value="<?php echo $category['CategoryID']; ?>">
                      <?php echo htmlspecialchars($category['CategoryName']); ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="row">
                <div class="col-md-6">
                  <div class="mb-3">
                    <label for="purchaseDate" class="form-label">Purchase Date</label>
                    <input type="date" class="form-control" id="purchaseDate" name="purchaseDate" required>
                  </div>
                </div>
                <div class="col-md-6">
                  <div class="mb-3">
                    <label for="purchaseCost" class="form-label">Purchase Cost</label>
                    <div class="input-group">
                      <span class="input-group-text">Rs.</span>
                      <input type="number" step="0.01" class="form-control" id="purchaseCost" name="purchaseCost" required>
                    </div>
                  </div>
                </div>
              </div>
              <div class="row">
                <div class="col-md-6">
                  <div class="mb-3">
                    <label for="warrantyEndDate" class="form-label">Warranty End Date</label>
                    <input type="date" class="form-control" id="warrantyEndDate" name="warrantyEndDate">
                  </div>
                </div>
                <div class="col-md-6">
                  <div class="mb-3">
                    <label for="assetCondition" class="form-label">Condition</label>
                    <select class="form-select" id="assetCondition" name="assetCondition" required>
                      <option value="New">New</option>
                      <option value="Good">Good</option>
                      <option value="Fair">Fair</option>
                      <option value="Poor">Poor</option>
                    </select>
                  </div>
                </div>
              </div>
              <div class="mb-3">
                <label for="assetLocation" class="form-label">Location</label>
                <input type="text" class="form-control" id="assetLocation" name="assetLocation" required>
              </div>
              <div class="mb-3">
                <label for="description" class="form-label">Description</label>
                <textarea class="form-control" id="description" name="description" rows="3"></textarea>
              </div>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary">Add Asset</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Edit Asset Modal -->
<div class="modal fade" id="editAssetModal" tabindex="-1" aria-labelledby="editAssetModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="editAssetModalLabel">Edit Asset</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form id="editAssetForm" method="POST" action="manage_assets.php" enctype="multipart/form-data">
        <div class="modal-body">
          <input type="hidden" name="action" value="edit">
          <input type="hidden" name="assetId" id="editAssetId">
          <input type="hidden" name="currentImage" id="currentImage">
          <input type="hidden" name="status" id="editStatus">
          <div class="row">
            <div class="col-md-4 text-center">
              <div class="asset-image-container">
                <img id="editAssetImage" src="" class="asset-image" style="display: none;">
                <div id="editDefaultIcon" class="default-asset-icon">
                  <i class="fas fa-boxes"></i>
                </div>
                <div class="upload-overlay">
                  <button type="button" onclick="document.getElementById('newAssetImage').click();">
                    <i class="fas fa-pencil-alt"></i><span class="text-sm"> Change</span>
                  </button>
                </div>
              </div>
              <input type="file" class="form-control-file" id="newAssetImage" name="assetImage" accept="image/*" style="display: none;">
            </div>
            <div class="col-md-8">
              <div class="row">
                <div class="col-md-6">
                  <div class="mb-3">
                    <label for="editAssetName" class="form-label">Asset Name</label>
                    <input type="text" class="form-control" id="editAssetName" name="assetName" required>
                  </div>
                  <div class="mb-3">
                    <label for="editCategoryId" class="form-label">Category</label>
                    <select class="form-select" id="editCategoryId" name="categoryId" required>
                      <option value="">Select Category</option>
                      <?php foreach ($categories as $category): ?>
                        <option value="<?php echo $category['CategoryID']; ?>">
                          <?php echo htmlspecialchars($category['CategoryName']); ?>
                        </option>
                      <?php endforeach; ?>
                    </select>
                  </div>
                  <div class="mb-3">
                    <label for="editPurchaseDate" class="form-label">Purchase Date</label>
                    <input type="date" class="form-control" id="editPurchaseDate" name="purchaseDate" required>
                  </div>
                  <div class="mb-3">
                    <label for="editWarrantyEndDate" class="form-label">Warranty End Date</label>
                    <input type="date" class="form-control" id="editWarrantyEndDate" name="warrantyEndDate">
                  </div>
                </div>
                <div class="col-md-6">
                  <div class="mb-3">
                    <label for="editPurchaseCost" class="form-label">Purchase Cost</label>
                    <div class="input-group">
                      <span class="input-group-text">Rs.</span>
                      <input type="number" step="0.01" class="form-control" id="editPurchaseCost" name="purchaseCost" required>
                    </div>
                  </div>
                  <div class="mb-3">
                    <label for="editAssetCondition" class="form-label">Condition</label>
                    <select class="form-select" id="editAssetCondition" name="assetCondition" required>
                      <option value="New">New</option>
                      <option value="Good">Good</option>
                      <option value="Fair">Fair</option>
                      <option value="Poor">Poor</option>
                    </select>
                  </div>
                  <div class="mb-3">
                    <label for="editAssetLocation" class="form-label">Location</label>
                    <input type="text" class="form-control" id="editAssetLocation" name="assetLocation" required>
                  </div>
                  <div class="mb-3">
                    <label for="editDescription" class="form-label">Description</label>
                    <textarea class="form-control" id="editDescription" name="description" rows="3"></textarea>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary">Update Asset</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- View Asset Modal -->
<div class="modal fade" id="viewAssetModal" tabindex="-1" aria-labelledby="viewAssetModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="viewAssetModalLabel">Asset Details</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div class="row">
          <div class="col-md-4 text-center">
            <div id="viewImageContainer">
              <img id="viewAssetImage" src="" class="asset-image" style="display: none; width: 200px; height: 200px; object-fit: cover; border-radius: 0.25rem;">
              <div id="viewDefaultIcon" class="default-asset-icon" style="width: 200px; height: 200px; margin: 0 auto;">
                <i class="fas fa-boxes"></i>
              </div>
            </div>
          </div>
          <div class="col-md-8">
            <div class="row">
              <div class="col-md-6">
                <div class="mb-3">
                  <label class="fw-bold">Asset Name:</label>
                  <p id="viewAssetName"></p>
                </div>
                <div class="mb-3">
                  <label class="fw-bold">Category:</label>
                  <p id="viewCategory"></p>
                </div>
                <div class="mb-3">
                  <label class="fw-bold">Serial Number:</label>
                  <p id="viewSerial"></p>
                </div>
                <div class="mb-3">
                  <label class="fw-bold">Purchase Date:</label>
                  <p id="viewPurchaseDate"></p>
                </div>
                <div class="mb-3">
                  <label class="fw-bold">Warranty End Date:</label>
                  <p id="viewWarrantyEnd"></p>
                </div>
              </div>
              <div class="col-md-6">
                <div class="mb-3">
                  <label class="fw-bold">Purchase Cost:</label>
                  <p id="viewPurchaseCost"></p>
                </div>
                <div class="mb-3">
                  <label class="fw-bold">Condition:</label>
                  <p id="viewCondition"></p>
                </div>
                <div class="mb-3">
                  <label class="fw-bold">Location:</label>
                  <p id="viewLocation"></p>
                </div>
                <div class="mb-3">
                  <label class="fw-bold">Status:</label>
                  <p id="viewStatus"></p>
                </div>
              </div>
            </div>
            <div class="mb-3">
              <label class="fw-bold">Description:</label>
              <p id="viewDescription"></p>
            </div>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>

<!-- Image Cropping Modal -->
<div class="modal fade" id="imageCropModal" tabindex="-1" aria-labelledby="imageCropModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="imageCropModalLabel">Crop Image</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div class="img-container">
          <img id="imageToCrop" src="" alt="Image to crop">
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-primary" id="cropImageBtn">Crop & Save</button>
      </div>
    </div>
  </div>
</div>

<!-- Delete Asset Modal -->
<div class="modal fade" id="deleteAssetModal" tabindex="-1" aria-labelledby="deleteAssetModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="deleteAssetModalLabel">Delete Asset</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form method="POST" action="manage_assets.php">
        <div class="modal-body">
          <input type="hidden" name="action" value="delete">
          <input type="hidden" name="assetId" id="deleteAssetId">
          <p>Are you sure you want to delete the asset: <strong id="deleteAssetName"></strong>?</p>
          <p class="text-danger">This action cannot be undone. All related assignments and maintenance records will also be deleted.</p>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-danger">Delete Asset</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Print Sticker Modal -->
<div class="modal fade" id="printStickerModal" tabindex="-1" aria-labelledby="printStickerModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="printStickerModalLabel">Print Asset Sticker</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div id="stickerContent" class="text-center">
          <h4 id="stickerAssetName"></h4>
          <svg id="barcode"></svg>
          <span id="stickerSerialNumber" style="display: none;"></span>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-link text-primary" onclick="downloadSticker()" title="Download Sticker">
          <i class="fas fa-download fa-lg"></i>
        </button>
        <button type="button" class="btn btn-link text-primary print-action-button" title="Print Sticker">
          <i class="fas fa-print fa-lg"></i>
        </button>
        <button type="button" class="btn btn-link text-secondary" data-bs-dismiss="modal" title="Close">
          <i class="fas fa-times fa-lg"></i>
        </button>
      </div>
    </div>
  </div>
</div>

<!-- Add JsBarcode library -->
<script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.5/dist/JsBarcode.all.min.js"></script>

<!-- Add the assets-db.js script -->
<script src="resources/js/assets-db.js"></script>

<!-- Add download and print sticker functions -->
<script>
  // Function to download sticker as image
  function downloadSticker() {
    const stickerContent = document.getElementById('stickerContent');
    const assetName = document.getElementById('stickerAssetName').textContent;
    
    html2canvas(stickerContent, {
      scale: 3, // Higher scale for better quality
      backgroundColor: '#ffffff'
    }).then(canvas => {
      const link = document.createElement('a');
      link.download = 'asset-sticker-' + assetName + '.png';
      link.href = canvas.toDataURL('image/png');
      link.click();
    });
  }
  
  // Function to print sticker (accepts arguments)
  function printSticker(assetName, serialNumber) {
    if (!assetName || !serialNumber) {
      console.error("printSticker Error: Missing required parameters");
      alert("Error: Could not generate sticker. Missing asset information.");
      return;
    }

    // Create a more reliable print window
    const printWindow = window.open('', '_blank', 'width=500,height=500');
    
    if (!printWindow) {
      alert("Popup blocked! Please allow popups for this site to print stickers.");
      return;
    }
    
    // Create a complete HTML document for the print window
    const printContent = `
      <!DOCTYPE html>
      <html>
      <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Asset Sticker: ${assetName}</title>
        <style>
          @page { size: 90mm 50mm; margin: 5mm; }
          body { 
            font-family: Arial, sans-serif; 
            margin: 0; 
            padding: 10px; 
            text-align: center;
            background: white;
          }
          .sticker { 
            width: 300px; 
            padding: 20px; 
            border: 1px dashed #ccc; 
            border-radius: 5px;
            margin: 0 auto; 
            background: white;
          }
          .sticker h2 { 
            margin: 0 0 15px 0; 
            font-size: 18px; 
            font-weight: bold; 
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
          }
          .logo {
            font-size: 11px;
            color: #666;
            margin-top: 5px;
          }
          .barcode-container {
            margin: 15px 0;
          }
          .buttons { 
            margin-top: 30px; 
            display: flex;
            justify-content: center;
            gap: 10px;
          }
          .btn { 
            padding: 8px 16px; 
            cursor: pointer; 
            border: none; 
            border-radius: 4px; 
            font-weight: bold;
            transition: all 0.2s ease;
          }
          .btn-primary { 
            background-color: #007bff; 
            color: white; 
          }
          .btn-primary:hover {
            background-color: #0069d9;
          }
          .btn-secondary {
            background-color: #6c757d;
            color: white;
          }
          .btn-secondary:hover {
            background-color: #5a6268;
          }
          @media print {
            @page { size: 90mm 50mm; margin: 0; }
            body { 
              margin: 0; 
              padding: 0; 
            }
            .buttons { 
              display: none; 
            }
            .sticker { 
              border: none; 
              width: 100%;
              height: 100%;
              padding: 0;
              display: flex;
              flex-direction: column;
              justify-content: center;
            }
          }
        </style>
      </head>
      <body>
        <div class="sticker">
          <h2>${assetName}</h2>
          <div class="barcode-container">
            <svg id="printBarcode"></svg>
          </div>
          <div class="logo">Prime Express HRMS</div>
        </div>
        <div class="buttons">
          <button class="btn btn-primary" onclick="window.print();">Print Sticker</button>
          <button class="btn btn-secondary" onclick="window.close();">Close</button>
        </div>
        
        <!-- Load barcode library -->
        <script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.5/dist/JsBarcode.all.min.js"><\\/script>
        
        <script>
          // Generate barcode when page is loaded
          document.addEventListener('DOMContentLoaded', function() {
            try {
              JsBarcode("#printBarcode", "${serialNumber}", {
                format: "CODE128",
                lineColor: "#000",
                width: 2,
                height: 70,
                displayValue: true,
                fontSize: 16,
                margin: 5,
                background: "white"
              });
              
              // Auto-print on some browsers
              if (navigator.userAgent.indexOf('Chrome') > -1) {
                setTimeout(function() {
                  window.print();
                }, 500);
              }
            } catch (e) {
              console.error("Error generating barcode:", e);
              document.querySelector('.barcode-container').innerHTML = 
                '<p style="color:red;">Error generating barcode. Invalid data format.</p>';
            }
          });
        <\\/script>
      </body>
      </html>
    `;
    
    // Write content to the window and trigger print
    printWindow.document.open();
    printWindow.document.write(printContent);
    printWindow.document.close();
    
    // Focus the window to bring it to front
    printWindow.focus();
  }
</script>

<!-- Add html2canvas library for image export -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>

<style>
.asset-image-container {
  position: relative;
  width: 200px;
  height: 200px;
  border-radius: 5px;
  overflow: hidden;
  margin: 0 auto;
  margin-bottom: 20px;
  border: 1px solid #ddd;
  background-color: #f8f9fa;
}

.asset-image {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.default-asset-icon {
  width: 100%;
  height: 100%;
  display: flex;
  align-items: center;
  justify-content: center;
  background-color: #e9ecef;
  color: #6c757d;
}


.default-asset-icon i {
  font-size: 5rem;
}

.no-image-icon i{
  font-size: 1rem;
}

.upload-overlay {
  position: absolute;
  bottom: 0;
  left: 0;
  right: 0;
  background-color: rgba(0, 0, 0, 0.5);
  padding: 8px;
  text-align: center;
}

.upload-overlay button {
  background: none;
  border: none;
  color: white;
  cursor: pointer;
  font-size: 14px;
}

/* Additional styles for Cropper.js */
.img-container {
  max-height: 400px;
  margin-bottom: 15px;
}

#imageToCrop {
  max-width: 100%;
}

/* Improve form input styles */
.form-label {
  font-weight: 500;
  margin-bottom: 0.5rem;
}

.form-select, .form-control {
  border-radius: 0.25rem;
  border: 1px solid #ced4da;
  transition: border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
}

.form-select:focus, .form-control:focus {
  border-color: #86b7fe;
  box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.25);
}



/* Print preview styles */
@media print {
  .no-print {
    display: none !important;
  }
  .card {
    border: none !important;
    box-shadow: none !important;
  }
  .card-body {
    padding: 0 !important;
  }
}
</style>

</body>
</html>