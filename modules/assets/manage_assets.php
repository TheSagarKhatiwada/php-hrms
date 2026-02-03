<?php
$page = 'Manage Assets';
require_once __DIR__ . '/../../includes/session_config.php';
require_once __DIR__ . '/../../includes/utilities.php';


include '../../includes/db_connection.php';
require_once 'manage_assets_handler.php';
include '../../includes/header.php';
?>

<!-- Main content -->
<div class="container-fluid p-4">
  <?php if (!empty($_SESSION['success'])): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
      <?php echo htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?>
      <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
  <?php endif; ?>
  <?php if (!empty($_SESSION['error'])): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
      <?php echo htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?>
      <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
  <?php endif; ?>
  <!-- Page header -->
  <div class="d-flex justify-content-between align-items-center mb-4">
    <div>
      <h1 class="fs-2 fw-bold mb-1">Fixed Assets</h1>
    </div>
    <?php
      $assetToolbarInline = true;
      $assetToolbarButtons = [
        [
          'type' => 'button',
          'label' => 'Add Asset',
          'icon' => 'fas fa-plus',
          'classes' => 'btn btn-primary',
          'attributes' => [
            'type' => 'button',
            'data-bs-toggle' => 'modal',
            'data-bs-target' => '#addAssetModal'
          ]
        ],
        [
          'type' => 'link',
          'label' => 'Categories',
          'icon' => 'fas fa-tags',
          'href' => 'manage_categories.php',
          'classes' => 'btn btn-outline-secondary'
        ],
        [
          'type' => 'dropdown',
          'label' => 'More',
          'icon' => 'fas fa-ellipsis-h',
          'classes' => 'btn btn-outline-secondary',
          'items' => [
            [
              'label' => 'Assignments',
              'icon' => 'fas fa-people-carry',
              'href' => 'manage_assignments.php'
            ],
            [
              'label' => 'Maintenance',
              'icon' => 'fas fa-tools',
              'href' => 'manage_maintenance.php'
            ]
          ]
        ]
      ];
    ?>
    <?php include __DIR__ . '/partials/assets_nav.php'; ?>
  </div>

  <div class="card mb-4 shadow-sm">
    <div class="card-body">
      <div class="row g-3 align-items-end">
        <div class="col-lg-4 col-md-6">
          <label for="searchFilter" class="form-label">Search</label>
          <div class="input-group">
            <input type="text" class="form-control" id="searchFilter" placeholder="Search by name, asset serial, product serial or remarks">
            <button class="btn btn-outline-secondary" type="button" id="clearSearch">
              <i class="fas fa-times"></i>
            </button>
          </div>
        </div>
        <div class="col-lg-3 col-md-6">
          <label for="categoryFilter" class="form-label">Category</label>
          <select class="form-select" id="categoryFilter">
            <option value="">All categories</option>
            <?php foreach ($categories as $category): ?>
              <option value="<?php echo (int) $category['CategoryID']; ?>">
                <?php echo htmlspecialchars($category['CategoryName']); ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-lg-2 col-md-4">
          <label for="statusFilter" class="form-label">Status</label>
          <select class="form-select" id="statusFilter">
            <option value="">Any status</option>
            <?php foreach (['Available', 'Assigned', 'Maintenance', 'Disposed', 'Lost'] as $statusOption): ?>
              <option value="<?php echo htmlspecialchars($statusOption); ?>">
                <?php echo htmlspecialchars($statusOption); ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-lg-2 col-md-4">
          <label for="deletedFilter" class="form-label">Archive</label>
          <select class="form-select" id="deletedFilter">
            <option value="active" selected>Active only</option>
            <option value="deleted">Deleted only</option>
            <option value="all">Active + Deleted</option>
          </select>
        </div>
        <div class="col-lg-1 col-md-4 d-flex">
          <button type="button" class="btn btn-outline-secondary ms-auto w-100" id="resetFilters">
            <i class="fas fa-undo me-2"></i> Reset
          </button>
        </div>
      </div>
    </div>
  </div>

  <div class="card shadow-sm">
    <div class="card-body">
      <div class="table-responsive">
        <table id="assetsTable" class="table table-hover align-middle w-100">
          <thead class="text-muted text-uppercase small">
            <tr>
              <th>Serial</th>
              <th>Asset</th>
              <th>Purchase Date</th>
              <th>Purchase Cost</th>
              <th>Warranty End</th>
              <th>Condition</th>
              <th>Status</th>
              <th class="text-center">Actions</th>
            </tr>
          </thead>
          <tbody></tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
  const escapeHtmlFallback = (value = '') => String(value)
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#39;');

  const escapeHtml = (typeof window !== 'undefined' && typeof window.escapeHtml === 'function')
    ? window.escapeHtml
    : escapeHtmlFallback;

  const applyDigitsIfBs = (value) => (window.hrmsUseBsDates && typeof window.hrmsToNepaliDigits === 'function')
    ? window.hrmsToNepaliDigits(value)
    : value;

  const formatDate = (value) => {
    if (!value) {
      return '-';
    }

    const normalized = String(value).split(' ')[0];
    const parts = normalized.split('-');
    if (parts.length === 3) {
      const [year, month, day] = parts.map((part) => parseInt(part, 10));
      if (!Number.isNaN(year) && !Number.isNaN(month) && !Number.isNaN(day)) {
        const date = new Date(Date.UTC(year, month - 1, day));
        return applyDigitsIfBs(date.toLocaleDateString('en-GB', { year: 'numeric', month: 'short', day: 'numeric' }));
      }
    }

    const parsedDate = new Date(value);
    if (!Number.isNaN(parsedDate.getTime())) {
      return applyDigitsIfBs(parsedDate.toLocaleDateString('en-GB', { year: 'numeric', month: 'short', day: 'numeric' }));
    }

    return value;
  };

  const formatCurrency = (value) => {
    if (value === null || value === undefined || value === '') {
      return '-';
    }
    const numericValue = Number(value);
    return Number.isNaN(numericValue) ? value : `Rs. ${numericValue.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
  };
  const formatDateTime = (value) => {
    if (!value) {
      return '-';
    }
    const normalized = String(value).replace(' ', 'T');
    const date = new Date(normalized);
    if (Number.isNaN(date.getTime())) {
      return value;
    }
    return applyDigitsIfBs(date.toLocaleString('en-GB', {
      year: 'numeric',
      month: 'short',
      day: 'numeric',
      hour: '2-digit',
      minute: '2-digit'
    }));
  };
  const statusClass = (status) => {
    switch (status) {
      case 'Available':
        return 'bg-success';
      case 'Maintenance':
        return 'bg-warning text-dark';
      case 'Assigned':
        return 'bg-info text-dark';
      case 'Disposed':
        return 'bg-secondary';
      default:
        return 'bg-danger';
    }
  };
  const toInputDate = (value) => {
    if (!value) {
      return '';
    }
    return value.substring(0, 10);
  };
  const isRowDeleted = (row) => !!(row && row.deleted_at);

  const viewModalEl = document.getElementById('viewAssetModal');
  const editModalEl = document.getElementById('editAssetModal');
  const deleteModalEl = document.getElementById('deleteAssetModal');
  const restoreModalEl = document.getElementById('restoreAssetModal');

  const showModal = (element) => {
    if (!element) {
      console.warn('Attempted to open a modal that does not exist in the DOM.');
      return;
    }
    bootstrap.Modal.getOrCreateInstance(element).show();
  };

  if (typeof DataTable === 'undefined') {
    console.error('DataTable library is not available on this page.');
    return;
  }

  const assetsTable = new DataTable('#assetsTable', {
    responsive: true,
    lengthChange: true,
    autoWidth: false,
    order: [[2, 'desc']],
    pageLength: 10,
    ajax: {
      url: 'api/assets.php',
      dataSrc: (json) => (json && json.data) ? json.data : [],
      data: function(params) {
        params.status = $('#statusFilter').val();
        params.category = $('#categoryFilter').val();
        params.search = $('#searchFilter').val();
        params.per_page = 250;
        params.include_deleted = 0;
        params.only_deleted = 0;
        const deletedFilter = $('#deletedFilter').val();
        if (deletedFilter === 'deleted') {
          params.only_deleted = 1;
        } else if (deletedFilter === 'all') {
          params.include_deleted = 1;
        }
      },
      error: function(xhr) {
        console.error('Failed to load assets', xhr.responseText);
      }
    },
    columns: [
      {
        data: 'AssetSerial',
        render: (data) => escapeHtml(data || 'N/A')
      },
      {
        data: null,
        render: (row) => {
          const deleted = isRowDeleted(row);
          const image = row.AssetImage
            ? `<img src="${escapeHtml(row.AssetImage)}" class="me-3 rounded" style="width:48px;height:48px;object-fit:cover;" alt="${escapeHtml(row.AssetName || 'Asset Image')}">`
            : '<div class="default-asset-icon me-3"><i class="fas fa-boxes"></i></div>';
          const name = escapeHtml(row.AssetName || 'Unnamed asset');
          const category = escapeHtml(row.CategoryName || 'Uncategorized');
          const deletedMeta = deleted
            ? `<div class="text-danger small">Deleted ${formatDateTime(row.deleted_at)}${row.deleted_reason ? ' · ' + escapeHtml(row.deleted_reason) : ''}</div>`
            : '';
          return `<div class="d-flex align-items-center">${image}<div><div class="fw-bold">${name}</div><small class="text-muted">${category}</small>${deletedMeta}</div></div>`;
        }
      },
      {
        data: 'PurchaseDate',
        render: (data) => formatDate(data)
      },
      {
        data: 'PurchaseCost',
        render: (data) => formatCurrency(data)
      },
      {
        data: 'WarrantyEndDate',
        render: (data) => formatDate(data)
      },
      {
        data: 'AssetCondition',
        render: (data) => escapeHtml(data || 'Unknown')
      },
      {
        data: 'Status',
        className: 'text-center',
        render: (data, type, row) => {
          const deleted = isRowDeleted(row);
          const label = deleted ? 'Deleted' : (data || 'Unknown');
          const badgeClass = deleted ? 'bg-dark' : statusClass(data);
          return `<span class="badge ${badgeClass}">${escapeHtml(label)}</span>`;
        }
      },
      {
        data: null,
        className: 'text-center',
        orderable: false,
        render: (data, type, row) => {
          const deleted = isRowDeleted(row);
          const menuItems = deleted
            ? ''
            : `
              <button type="button" class="dropdown-item edit-asset">
                <i class="fas fa-edit me-2"></i> Update
              </button>
              <button type="button" class="dropdown-item delete-asset">
                <i class="fas fa-trash me-2 text-danger"></i> Delete
              </button>
            `;

          const inlineButtons = deleted
            ? `
                <button type="button" class="btn btn-sm btn-link text-secondary px-1 py-1 view-asset" title="View" aria-label="View">
                  <i class="fas fa-eye"></i>
                </button>
                <button type="button" class="btn btn-sm btn-link text-secondary px-1 py-1 restore-asset" title="Restore" aria-label="Restore">
                  <i class="fas fa-undo"></i>
                </button>
              `
            : `
                <button type="button" class="btn btn-sm btn-link text-secondary px-1 py-1 view-asset" title="View" aria-label="View">
                  <i class="fas fa-eye"></i>
                </button>
                <button type="button" class="btn btn-sm btn-link text-secondary px-1 py-1 print-sticker" title="Print sticker" aria-label="Print sticker">
                  <i class="fas fa-print"></i>
                </button>
              `;

          const kebabMenu = menuItems
            ? `
                <div class="dropdown">
                  <a href="#" class="btn btn-sm btn-link text-secondary px-1 py-1" role="button" data-bs-toggle="dropdown" aria-expanded="false" aria-label="More actions">
                    <i class="fas fa-ellipsis-v"></i>
                  </a>
                  <div class="dropdown-menu dropdown-menu-end">
                    ${menuItems}
                  </div>
                </div>
              `
            : '';

          return `
            <div class="d-flex align-items-center justify-content-center gap-2">
              ${inlineButtons}
              ${kebabMenu}
            </div>
          `;
        }
      }
    ],
    language: {
      paginate: {
        previous: '<i class="fas fa-chevron-left"></i>',
        next: '<i class="fas fa-chevron-right"></i>'
      }
    },
    rowCallback: function(row, data) {
      if (isRowDeleted(data)) {
        $(row).addClass('table-warning');
      } else {
        $(row).removeClass('table-warning');
      }
    }
  });

  const reloadAssets = () => assetsTable.ajax.reload(null, false);

  $('#statusFilter, #categoryFilter, #deletedFilter').on('change', reloadAssets);

  let searchDebounce;
  $('#searchFilter').on('input', function() {
    clearTimeout(searchDebounce);
    searchDebounce = setTimeout(reloadAssets, 300);
  });

  $('#clearSearch').on('click', function() {
    clearTimeout(searchDebounce);
    $('#searchFilter').val('');
    reloadAssets();
  });

  $('#resetFilters').on('click', function() {
    clearTimeout(searchDebounce);
    $('#statusFilter').val('');
    $('#categoryFilter').val('');
    $('#searchFilter').val('');
    $('#deletedFilter').val('active');
    reloadAssets();
  });

  const getRowData = (trigger) => {
    let $row = $(trigger).closest('tr');
    if ($row.length === 0) {
      return null;
    }
    let data = assetsTable.row($row).data();
    if (!data && $row.hasClass('child')) {
      const parentRow = $row.prevAll('tr.parent').first();
      if (parentRow.length) {
        data = assetsTable.row(parentRow).data();
      }
    }
    return data;
  };

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
              $('#currentImage').val('resources/images/' + imageName);
            } else if (currentImagePreview === '#editAssetImage') {
              $('#currentImage').val('resources/images/' + imageName);
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
  $(document).on('click', '.view-asset', function(e) {
    e.preventDefault();
    const assetData = getRowData(this);
    if (!assetData) {
      console.warn('Unable to resolve asset data for view action');
      return;
    }

    $('#viewAssetName').text(assetData.AssetName || 'N/A');
    $('#viewCategory').text(assetData.CategoryName || 'N/A');
    $('#viewSerial').text(assetData.AssetSerial || 'N/A');
    $('#viewProductSerial').text(assetData.ProductSerial || 'N/A');
    $('#viewPurchaseDate').text(formatDate(assetData.PurchaseDate));
    $('#viewPurchaseCost').text(formatCurrency(assetData.PurchaseCost));
    $('#viewWarrantyEnd').text(formatDate(assetData.WarrantyEndDate));
    $('#viewCondition').text(assetData.AssetCondition || 'N/A');
    $('#viewLocation').text(assetData.AssetLocation || 'N/A');
    $('#viewStatus').text(assetData.Status || 'N/A');
    $('#viewDescription').text(assetData.AssetsDescription || '');

    if (assetData.deleted_at) {
      $('#viewDeletedAt').text(formatDateTime(assetData.deleted_at));
      $('#viewDeletedReason').text(assetData.deleted_reason ? assetData.deleted_reason : 'Not provided');
      $('#viewDeletedInfo').removeClass('d-none');
    } else {
      $('#viewDeletedInfo').addClass('d-none');
    }

    const imagePath = assetData.AssetImage;
    if (imagePath) {
      $('#viewAssetImage').attr('src', imagePath).show();
      $('#viewDefaultIcon').hide();
    } else {
      $('#viewAssetImage').hide();
      $('#viewDefaultIcon').show();
    }

    showModal(viewModalEl);
  });

  // Edit asset button click - updated for Bootstrap 5
  $(document).on('click', '.edit-asset', function(e) {
    e.preventDefault();
    const assetData = getRowData(this);
    if (!assetData) {
      console.warn('Unable to resolve asset data for edit action');
      return;
    }

    $('#editAssetId').val(assetData.AssetID);
    $('#editAssetName').val(assetData.AssetName);
    $('#editProductSerial').val(assetData.ProductSerial || '');
    $('#editCategoryId').val(assetData.CategoryID);
    $('#editPurchaseDate').val(toInputDate(assetData.PurchaseDate));
    $('#editPurchaseCost').val(assetData.PurchaseCost);
    $('#editWarrantyEndDate').val(toInputDate(assetData.WarrantyEndDate));
    $('#editAssetCondition').val(assetData.AssetCondition);
    $('#editAssetLocation').val(assetData.AssetLocation);
    $('#editDescription').val(assetData.AssetsDescription);
    $('#currentImage').val(assetData.AssetImage || '');
    $('#editStatus').val(assetData.Status);

    const imagePath = assetData.AssetImage;
    if (imagePath) {
      $('#editAssetImage').attr('src', imagePath).show();
      $('#editDefaultIcon').hide();
    } else {
      $('#editAssetImage').hide();
      $('#editDefaultIcon').show();
    }

    showModal(editModalEl);
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
  $(document).on('click', '.delete-asset', function(e) {
    e.preventDefault();
    const assetData = getRowData(this);
    if (!assetData) {
      console.warn('Unable to resolve asset data for delete action');
      return;
    }
    $('#deleteAssetId').val(assetData.AssetID);
    $('#deleteAssetName').text(assetData.AssetName);
    showModal(deleteModalEl);
  });

  $(document).on('click', '.restore-asset', function(e) {
    e.preventDefault();
    const assetData = getRowData(this);
    if (!assetData) {
      console.warn('Unable to resolve asset data for restore action');
      return;
    }
    $('#restoreAssetId').val(assetData.AssetID);
    $('#restoreAssetName').text(assetData.AssetName || 'this asset');
    showModal(restoreModalEl);
  });

  // Print Sticker button click
  $(document).on('click', '.print-sticker', function(e) {
    e.preventDefault();
    const assetData = getRowData(this);
    if (!assetData) {
      console.warn('Unable to resolve asset data for print action');
      return;
    }

    if (!assetData.AssetID) {
      alert('Asset ID missing. Unable to print sticker.');
      return;
    }

    // Open in a new tab within the same browser window
    window.open(`print_sticker.php?id=${assetData.AssetID}`, '_blank');
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
          <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
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
                <label for="productSerial" class="form-label">Product Serial No. (Optional)</label>
                <input type="text" class="form-control" id="productSerial" name="productSerial" placeholder="Vendor serial (if any)">
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
                      <option value="Excellent">Excellent</option>
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
          <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
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
                    <label for="editProductSerial" class="form-label">Product Serial No. (Optional)</label>
                    <input type="text" class="form-control" id="editProductSerial" name="productSerial" placeholder="Vendor serial (if any)">
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
                      <option value="Excellent">Excellent</option>
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
        <div id="viewDeletedInfo" class="alert alert-warning d-none">
          <p class="mb-1"><strong>Deleted On:</strong> <span id="viewDeletedAt"></span></p>
          <p class="mb-0"><strong>Reason:</strong> <span id="viewDeletedReason"></span></p>
        </div>
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
                  <label class="fw-bold">Product Serial No.:</label>
                  <p id="viewProductSerial"></p>
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
          <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
          <p>Are you sure you want to delete the asset: <strong id="deleteAssetName"></strong>?</p>
          <p class="text-danger">The asset will move to the archive and can be restored later from the Deleted filter.</p>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-danger">Delete Asset</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Restore Asset Modal -->
<div class="modal fade" id="restoreAssetModal" tabindex="-1" aria-labelledby="restoreAssetModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="restoreAssetModalLabel">Restore Asset</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form method="POST" action="manage_assets.php">
        <div class="modal-body">
          <input type="hidden" name="action" value="restore">
          <input type="hidden" name="assetId" id="restoreAssetId">
          <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
          <p>Restore asset <strong id="restoreAssetName"></strong> back to the active list?</p>
          <p class="text-muted mb-0">The asset will become available for assignment again.</p>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-success">Restore Asset</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Add the assets-db.js script -->
<script src="resources/js/assets-db.js"></script>

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
  <?php include '../../includes/footer.php'; ?>