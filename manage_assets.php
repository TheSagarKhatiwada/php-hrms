<?php
  $page = 'Fixed Assets';
  $accessRole = isset($_SESSION['role']) ? $_SESSION['role'] : null;
  if ($accessRole === '0') {
      header('Location: dashboard.php');
      exit();
  }
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
              $assetName = $_POST['assetName'];
              $categoryId = $_POST['categoryId'];
              $purchaseDate = $_POST['purchaseDate'];
              $purchaseCost = $_POST['purchaseCost'];
              $description = $_POST['description'];
              $status = "Available"; // Set default status as Available
              
              try {
                  // Get category details
                  $stmt = $pdo->prepare("SELECT CategoryName, CategoryShortCode FROM AssetCategories WHERE CategoryID = :categoryId");
                  $stmt->execute([':categoryId' => $categoryId]);
                  $category = $stmt->fetch(PDO::FETCH_ASSOC);
                  
                  // Get the last serial number for this category
                  $stmt = $pdo->prepare("SELECT MAX(CAST(SUBSTRING_INDEX(AssetSerial, '-', -1) AS UNSIGNED)) as last_number 
                                        FROM fixedassets 
                                        WHERE AssetSerial LIKE :categoryCode");
                  $stmt->execute([':categoryCode' => $category['CategoryShortCode'] . '-%']);
                  $result = $stmt->fetch(PDO::FETCH_ASSOC);
                  
                  // Generate new serial number with 2-digit leading zeros
                  $nextNumber = ($result['last_number'] ?? 0) + 1;
                  $serialNumber = $category['CategoryShortCode'] . '-' . str_pad($nextNumber, 2, '0', STR_PAD_LEFT);
                  
                  // Handle image upload
                  $imagePath = ''; // Empty path for default icon
                  if (isset($_FILES['assetImage']) && $_FILES['assetImage']['error'] == 0) {
                      $uploadDir = 'resources/assetsimages/';
                      if (!file_exists($uploadDir)) {
                          mkdir($uploadDir, 0777, true);
                      }
                      
                      $fileExtension = strtolower(pathinfo($_FILES['assetImage']['name'], PATHINFO_EXTENSION));
                      $allowedExtensions = ['jpg', 'jpeg', 'png'];
                      
                      if (in_array($fileExtension, $allowedExtensions)) {
                          $fileName = uniqid('asset_') . '.' . $fileExtension;
                          $targetPath = $uploadDir . $fileName;
                          
                          if (move_uploaded_file($_FILES['assetImage']['tmp_name'], $targetPath)) {
                              $imagePath = $targetPath;
                          }
                      }
                  }
                  
                  $stmt = $pdo->prepare("INSERT INTO fixedassets (AssetName, CategoryID, PurchaseDate, PurchaseCost, AssetsDescription, Status, AssetImage, AssetSerial) 
                                      VALUES (:assetName, :categoryId, :purchaseDate, :purchaseCost, :description, :status, :imagePath, :serialNumber)");
                  $stmt->execute([
                      ':assetName' => $assetName,
                      ':categoryId' => $categoryId,
                      ':purchaseDate' => $purchaseDate,
                      ':purchaseCost' => $purchaseCost,
                      ':description' => $description,
                      ':status' => $status,
                      ':imagePath' => $imagePath,
                      ':serialNumber' => $serialNumber
                  ]);
                  
                  $_SESSION['success'] = "Asset added successfully! Serial Number: " . $serialNumber;
              } catch (PDOException $e) {
                  $_SESSION['error'] = "Error adding asset: " . $e->getMessage();
              }
          } 
          elseif ($action == 'edit') {
              $assetId = $_POST['assetId'];
              $assetName = $_POST['assetName'];
              $categoryId = $_POST['categoryId'];
              $purchaseDate = $_POST['purchaseDate'];
              $purchaseCost = $_POST['purchaseCost'];
              $warrantyEndDate = $_POST['warrantyEndDate'];
              $assetCondition = $_POST['assetCondition'];
              $assetLocation = $_POST['assetLocation'];
              $description = $_POST['description'];
              $currentImage = $_POST['currentImage'];
              
              try {
                  // Get current asset details including serial number
                  $stmt = $pdo->prepare("SELECT AssetSerial FROM fixedassets WHERE AssetID = :assetId");
                  $stmt->execute([':assetId' => $assetId]);
                  $currentAsset = $stmt->fetch(PDO::FETCH_ASSOC);
                  
                  // If no serial number exists, generate one
                  if (empty($currentAsset['AssetSerial'])) {
                      // Get category details
                      $stmt = $pdo->prepare("SELECT CategoryShortCode FROM AssetCategories WHERE CategoryID = :categoryId");
                      $stmt->execute([':categoryId' => $categoryId]);
                      $category = $stmt->fetch(PDO::FETCH_ASSOC);
                      
                      // Get the last serial number for this category
                      $stmt = $pdo->prepare("SELECT MAX(CAST(SUBSTRING_INDEX(AssetSerial, '-', -1) AS UNSIGNED)) as last_number 
                                            FROM fixedassets 
                                            WHERE AssetSerial LIKE :categoryCode");
                      $stmt->execute([':categoryCode' => $category['CategoryShortCode'] . '-%']);
                      $result = $stmt->fetch(PDO::FETCH_ASSOC);
                      
                      // Generate new serial number with 2-digit leading zeros
                      $nextNumber = ($result['last_number'] ?? 0) + 1;
                      $serialNumber = $category['CategoryShortCode'] . '-' . str_pad($nextNumber, 2, '0', STR_PAD_LEFT);
                  } else {
                      $serialNumber = $currentAsset['AssetSerial'];
                  }
                  
                  // Handle image upload
                  $imagePath = $currentImage; // Keep current image by default
                  if (isset($_FILES['assetImage']) && $_FILES['assetImage']['error'] == 0) {
                      $uploadDir = 'resources/assetsimages/';
                      if (!file_exists($uploadDir)) {
                          mkdir($uploadDir, 0777, true);
                      }
                      
                      $fileExtension = strtolower(pathinfo($_FILES['assetImage']['name'], PATHINFO_EXTENSION));
                      $allowedExtensions = ['jpg', 'jpeg', 'png'];
                      
                      if (in_array($fileExtension, $allowedExtensions)) {
                          $fileName = uniqid('asset_') . '.' . $fileExtension;
                          $targetPath = $uploadDir . $fileName;
                          
                          if (move_uploaded_file($_FILES['assetImage']['tmp_name'], $targetPath)) {
                              // Delete old image if it's not the default image
                              if ($currentImage != 'resources/assetsimages/default-asset.png' && file_exists($currentImage)) {
                                  unlink($currentImage);
                              }
                              $imagePath = $targetPath;
                          }
                      }
                  }
                  
                  // Get current status from database
                  $stmt = $pdo->prepare("SELECT Status FROM fixedassets WHERE AssetID = :assetId");
                  $stmt->execute([':assetId' => $assetId]);
                  $currentStatus = $stmt->fetchColumn();
                  
                  $stmt = $pdo->prepare("UPDATE fixedassets SET 
                                      AssetName = :assetName,
                                      CategoryID = :categoryId,
                                      PurchaseDate = :purchaseDate,
                                      PurchaseCost = :purchaseCost,
                                      WarrantyEndDate = :warrantyEndDate,
                                      AssetCondition = :assetCondition,
                                      AssetLocation = :assetLocation,
                                      AssetsDescription = :description,
                                      AssetImage = :imagePath,
                                      AssetSerial = :serialNumber
                                      WHERE AssetID = :assetId");
                  $stmt->execute([
                      ':assetId' => $assetId,
                      ':assetName' => $assetName,
                      ':categoryId' => $categoryId,
                      ':purchaseDate' => $purchaseDate,
                      ':purchaseCost' => $purchaseCost,
                      ':warrantyEndDate' => $warrantyEndDate,
                      ':assetCondition' => $assetCondition,
                      ':assetLocation' => $assetLocation,
                      ':description' => $description,
                      ':imagePath' => $imagePath,
                      ':serialNumber' => $serialNumber
                  ]);
                  
                  $_SESSION['success'] = "Asset updated successfully!";
                  if (empty($currentAsset['AssetSerial'])) {
                      $_SESSION['success'] .= " Serial Number assigned: " . $serialNumber;
                  }
              } catch (PDOException $e) {
                  $_SESSION['error'] = "Error updating asset: " . $e->getMessage();
              }
          }
          elseif ($action == 'delete') {
              $assetId = $_POST['assetId'];
              
              try {
                  // Get the image path before deleting
                  $stmt = $pdo->prepare("SELECT AssetImage FROM fixedassets WHERE AssetID = :assetId");
                  $stmt->execute([':assetId' => $assetId]);
                  $imagePath = $stmt->fetchColumn();
                  
                  // Delete the asset
                  $stmt = $pdo->prepare("DELETE FROM fixedassets WHERE AssetID = :assetId");
                  $stmt->execute([':assetId' => $assetId]);
                  
                  // Delete the image file if it's not the default image
                  if ($imagePath != 'resources/assetsimages/default-asset.png' && file_exists($imagePath)) {
                      unlink($imagePath);
                  }
                  
                  $_SESSION['success'] = "Asset deleted successfully!";
              } catch (PDOException $e) {
                  $_SESSION['error'] = "Error deleting asset: " . $e->getMessage();
              }
          }
          
          // Redirect to prevent form resubmission
          header("Location: manage_assets.php");
          exit();
      }
  }

  // Fetch all assets with their categories
  try {
      $stmt = $pdo->query("SELECT a.*, c.CategoryName 
                          FROM fixedassets a 
                          LEFT JOIN AssetCategories c ON a.CategoryID = c.CategoryID 
                          ORDER BY a.AssetName");
      $assets = $stmt->fetchAll(PDO::FETCH_ASSOC);
      
      // Fetch categories for dropdown
      $stmt = $pdo->query("SELECT CategoryID, CategoryName FROM AssetCategories ORDER BY CategoryName");
      $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
  } catch (PDOException $e) {
      $_SESSION['error'] = "Error fetching data: " . $e->getMessage();
      $assets = [];
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
    
    <!-- Add these in the head section after other CSS links -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.12/cropper.min.css">
    <style>
      .image-preview {
        max-width: 100%;
        max-height: 200px;
        margin-bottom: 10px;
      }
      .cropper-container {
        max-height: 70vh;
        margin: 0 auto;
      }
      .cropper-view-box {
        outline: 1px solid #39f;
      }
      .cropper-point {
        background-color: #39f;
        width: 8px;
        height: 8px;
      }
      .cropper-line {
        background-color: #39f;
      }
      .cropper-modal {
        background-color: rgba(0, 0, 0, 0.5);
      }
      #imageToCrop {
        max-width: 100%;
        max-height: 70vh;
        display: block;
        margin: 0 auto;
      }
      .img-container {
        max-height: 70vh;
        overflow: hidden;
        text-align: center;
      }
      .img-preview {
        width: 100%;
        height: 200px;
        overflow: hidden;
      }
      /* Data table image size */
      .asset-image {
        width: 50px;
        height: 50px;
        object-fit: cover;
        border-radius: 0.25rem;
      }
      .d-flex {
        display: flex;
      }
      .align-items-center {
        align-items: center;
      }
      .mr-3 {
        margin-right: 1rem;
      }
      /* Modal image sizes */
      #viewImageContainer {
          min-height: 200px;
          display: flex;
          align-items: center;
          justify-content: center;
      }
      #viewDefaultIcon {
          font-size: 3rem;
          width: 200px;
          height: 200px;
      }
      #viewAssetImage {
          width: 200px;
          height: 200px;
          object-fit: cover;
          border-radius: 0.25rem;
      }
      .asset-image-container {
          position: relative;
          width: 200px;
          height: 200px;
          border-radius: 0.25rem;
          margin: 0 auto;
          overflow: hidden;
      }
      .asset-image-container img {
          width: 200px;
          height: 200px;
          object-fit: cover;
      }
      .asset-image-container .upload-overlay {
          position: absolute;
          bottom: 0;
          left: 0;
          width: 100%;
          height: 20%;
          text-align: center;
          display: none;
          background-color: rgba(0, 0, 0, 0.5);
      }
      .asset-image-container:hover .upload-overlay {
          display: block;
      }
      .upload-overlay button {
          background: none;
          border: none;
          color: white;
      }
      .default-asset-icon {
          width: 200px;
          height: 200px;
          display: flex;
          align-items: center;
          justify-content: center;
          background-color: #f8f9fa;
          border-radius: 0.25rem;
          margin: 0 auto;
      }
      .default-asset-icon i {
          font-size: 3rem;
          color: #6c757d;
      }
      /* Fix for auto-filled form fields text color */
      input:-webkit-autofill,
      input:-webkit-autofill:hover,
      input:-webkit-autofill:focus,
      input:-webkit-autofill:active {
    </style>

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
              <h1 class="m-0">Manage Assets</h1>
            </div>
            <div class="col-sm-6">
              <ol class="breadcrumb float-sm-right">
                <li class="breadcrumb-item"><a href="assets.php">Manage Assets</a></li>
                <li class="breadcrumb-item active">Assets</li>
              </ol>
            </div>
          </div>
        </div>
      </div>
      <!-- /.content-header -->
      <!-- Main content -->
      <section class="content">
        <div class="container-fluid">
              <?php if (isset($_SESSION['success'])): ?>
                <div class="alert alert-success alert-dismissible">
                  <button type="button" class="close" data-dismiss="alert" aria-hidden="true">&times;</button>
              <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
                </div>
              <?php endif; ?>
              
              <?php if (isset($_SESSION['error'])): ?>
                <div class="alert alert-danger alert-dismissible">
                  <button type="button" class="close" data-dismiss="alert" aria-hidden="true">&times;</button>
              <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
                </div>
              <?php endif; ?>
              
      <div class="card">
                <!-- <div class="card-header">
                  <h3 class="card-title">Users</h3>
                </div> -->
                <!-- /.card-header -->
                <div class="card-body">
              <table id="assetsTable" class="table table-bordered table-striped">
                <thead>
                  <tr>
                    <th>Serial Number</th>
                    <th>Asset</th>
                    <th>Purchase Date</th>
                    <th>Purchase Cost</th>
                    <th>Warranty End Date</th>
                    <th>Condition</th>
                    <th>Status</th>
                    <th>Actions</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($assets as $asset): ?>
                    <tr>
                        <td><?php echo $asset['AssetSerial']; ?></td>
                        <td>
                          <div class="d-flex align-items-center">
                            <div class="mr-3">
                              <?php if (!empty($asset['AssetImage']) && file_exists($asset['AssetImage'])): ?>
                                <img src="<?php echo $asset['AssetImage']; ?>" 
                                    alt="<?php echo htmlspecialchars($asset['AssetName']); ?>" 
                                    class="asset-image">
                              <?php else: ?>
                                <div class="default-asset-icon">
                                  <i class="fas fa-boxes"></i>
                                </div>
                              <?php endif; ?>
                            </div>
                            <div>
                              <?php echo htmlspecialchars($asset['AssetName']); ?>
                            </div>
                          </div>
                        </td>
                        <td><?php echo date('d M Y', strtotime($asset['PurchaseDate'])); ?></td>
                        <td>Rs. <?php echo number_format($asset['PurchaseCost'], 2); ?></td>
                        <td><?php echo $asset['WarrantyEndDate'] ? date('d M Y', strtotime($asset['WarrantyEndDate'])) : '-'; ?></td>
                        <td><?php echo htmlspecialchars($asset['AssetCondition'] ? $asset['AssetCondition'] : '-'); ?></td>
                        <td>
                          <span class="badge badge-<?php 
                              echo $asset['Status'] == 'Available' ? 'success' : 
                                  ($asset['Status'] == 'Maintenance' ? 'warning' : 'danger'); 
                          ?>">
                            <?php echo $asset['Status']; ?>
                        </span>
                      </td>
                      <td>
                        <div class="dropdown">
                          <a class="btn btn-secondary font-weight-bold" style="background-color: transparent; border: none; color: inherit;" id="dropdownMenuButton" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                          <i class="fas fa-ellipsis-v"></i>
                          </a>
                          <div class="dropdown-menu dropdown-menu-right" aria-labelledby="dropdownMenuButton">
                            <button type="button" class="dropdown-item view-asset" 
                                  data-toggle="modal" data-target="#viewAssetModal"
                                  data-id="<?php echo $asset['AssetID']; ?>"
                                  data-name="<?php echo htmlspecialchars($asset['AssetName']); ?>"
                                  data-category="<?php echo htmlspecialchars($asset['CategoryName']); ?>"
                                  data-serial="<?php echo $asset['AssetSerial']; ?>"
                                  data-purchase-date="<?php echo date('d M Y', strtotime($asset['PurchaseDate'])); ?>"
                                  data-purchase-cost="Rs. <?php echo number_format($asset['PurchaseCost'], 2); ?>"
                                  data-warranty-end="<?php echo date('d M Y', strtotime($asset['WarrantyEndDate'])); ?>"
                                  data-condition="<?php echo htmlspecialchars($asset['AssetCondition']); ?>"
                                  data-location="<?php echo htmlspecialchars($asset['AssetLocation']); ?>"
                                  data-status="<?php echo $asset['Status']; ?>"
                                  data-description="<?php echo htmlspecialchars($asset['AssetsDescription']); ?>"
                                  data-image="<?php echo !empty($asset['AssetImage']) ? $asset['AssetImage'] : ''; ?>">
                              <i class="fas fa-eye"></i> View
                          </button>
                            <button type="button" class="dropdown-item edit-asset" 
                                  data-toggle="modal" data-target="#editAssetModal"
                                data-id="<?php echo $asset['AssetID']; ?>"
                                data-name="<?php echo htmlspecialchars($asset['AssetName']); ?>"
                                  data-category-id="<?php echo $asset['CategoryID']; ?>"
                                data-purchase-date="<?php echo $asset['PurchaseDate']; ?>"
                                data-purchase-cost="<?php echo $asset['PurchaseCost']; ?>"
                                  data-warranty-end="<?php echo $asset['WarrantyEndDate']; ?>"
                                  data-condition="<?php echo htmlspecialchars($asset['AssetCondition']); ?>"
                                  data-location="<?php echo htmlspecialchars($asset['AssetLocation']); ?>"
                                data-description="<?php echo htmlspecialchars($asset['AssetsDescription']); ?>"
                                  data-image="<?php echo !empty($asset['AssetImage']) ? $asset['AssetImage'] : ''; ?>">
                              <i class="fas fa-edit"></i> Edit
                        </button>
                            <button type="button" class="dropdown-item print-sticker" 
                                    data-id="<?php echo $asset['AssetID']; ?>"
                                    data-name="<?php echo htmlspecialchars($asset['AssetName']); ?>"
                                    data-serial="<?php echo $asset['AssetSerial']; ?>">
                              <i class="fas fa-print"></i> Print Sticker
                            </button>
                            <div class="dropdown-divider"></div>
                            <button type="button" class="dropdown-item delete-asset" 
                                  data-toggle="modal" data-target="#deleteAssetModal"
                                data-id="<?php echo $asset['AssetID']; ?>"
                                data-name="<?php echo htmlspecialchars($asset['AssetName']); ?>">
                              <i class="fas fa-trash"></i> Delete
                        </button>
                          </div>
                        </div>
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
  $(document).ready(function() {
      // Initialize DataTable
      $("#assetsTable").DataTable({
        "responsive": true,
        "lengthChange": true,
        "autoWidth": false,
        "paging": true,
        "searching": true,
        "ordering": true,
        "order": [[5, 'desc']], // [columnIndex, 'asc' or 'desc']
        "info": true,
        "pageLength": 10, // Set the default number of rows to display
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
      }).buttons().container().appendTo('#assetsTable_wrapper .col-md-6:eq(0)');

      // Add custom button below the filter
      $('#assetsTable_filter').after('<div class="custom-filter-button" style="display: flex; justify-content: flex-end;"><button class="btn btn-primary" id="custom-filter-btn"><i class="fas fa-boxes"></i> Add Assets</button></div>');

      // Custom button action
      $('#custom-filter-btn').on('click', function() {
        $('#addAssetModal').modal({
          backdrop: 'static',
          keyboard: false
        });
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
                  $('#imageCropModal').modal('show');
                  
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
                      toggleDragModeOnDblclick: false,
                      minContainerWidth: 300,
                      minContainerHeight: 300,
                      minCanvasWidth: 300,
                      minCanvasHeight: 300,
                      minCropBoxWidth: 100,
                      minCropBoxHeight: 100,
                      background: true,
                      center: true,
                      zoomable: true,
                      zoomOnTouch: true,
                      zoomOnWheel: true,
                      wheelZoomRatio: 0.1,
                      cropBoxResizable: true,
                      cropBoxMovable: true,
                      toggleDragModeOnDblclick: false,
                      dragMode: 'crop',
                      initialAspectRatio: 1,
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
          // Hide default icon and show preview container
          $('#addDefaultIcon').hide();
          $(currentImagePreview).show();
          initializeCropper(this, currentImagePreview);
      });

      // Handle image selection for edit form
      $('#newAssetImage').change(function() {
          currentImageInput = this;
          currentImagePreview = '#editAssetImage';
          // Hide default icon and show preview container
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
                      
                      // Create a new File object from the blob
                      const file = new File([blob], 'cropped-image.jpg', { type: 'image/jpeg' });
                      
                      // Create a new DataTransfer object and add the file
                      const dataTransfer = new DataTransfer();
                      dataTransfer.items.add(file);
                      
                      // Set the files property of the input
                      currentImageInput.files = dataTransfer.files;
                      
                      // Update the hidden currentImage field with the new image path
                      if (currentImagePreview === '#imagePreview') {
                          $('#currentImage').val('resources/assetsimages/' + file.name);
                      } else if (currentImagePreview === '#editAssetImage') {
                          $('#currentImage').val('resources/assetsimages/' + file.name);
                      }
                      
                      $('#imageCropModal').modal('hide');
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

      // View asset button click
      $('.view-asset').click(function() {
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

      // Edit asset button click
      $('.edit-asset').click(function() {
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

          // Show the edit modal
          $('#editAssetModal').modal('show');
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
                  $('#editAssetModal').modal('hide');
                  // Reload the page to show updated data
                  window.location.reload();
              },
              error: function(xhr, status, error) {
                  alert('Error updating asset: ' + error);
              }
          });
      });

      // Delete asset button click
      $('.delete-asset').click(function() {
          const assetData = $(this).data();
          $('#deleteAssetId').val(assetData.id);
          $('#deleteAssetName').text(assetData.name);
      });

      // Print Sticker button click
      $('.print-sticker').click(function() {
          const assetData = $(this).data();
          $('#stickerAssetName').text(assetData.name);
          $('#stickerSerialNumber').text(assetData.serial);
          
          // Generate barcode
          JsBarcode("#barcode", assetData.serial, {
              format: "CODE128",
              lineColor: "#000",
              width: 2,
              height: 100,
              displayValue: true,
              fontSize: 20,
              margin: 10
          });
          
          $('#printStickerModal').modal('show');
      });
  });

  function printSticker() {
      const printWindow = window.open('', '', 'width=600,height=600');
      const content = document.getElementById('stickerContent').innerHTML;
      const serialNumber = $('#stickerSerialNumber').text();
      
      printWindow.document.write(`
          <html>
              <head>
                  <title>Asset Sticker</title>
                  <style>
                      @media print {
                          body { margin: 0; }
                          .no-print { display: none; }
                      }
                      .sticker {
                          width: 100mm;
                          height: 50mm;
                          border: 1px solid #000;
                          text-align: center;
                          font-family: Arial, sans-serif;
                          margin: 0;
                          padding: 0;
                      }
                      .sticker h4 {
                          margin: 0;
                          padding: 0.5mm 0 0 0;
                          font-size: 20px;
                      }
                      .sticker p {
                          display: none;
                      }
                      .barcode {
                          margin: 0;
                          padding: 0;
                      }
                      @page {
                          size: A4;
                          margin: 0;
                      }
                  </style>
                  <script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.5/dist/JsBarcode.all.min.js"><\/script>
              </head>
              <body>
                  <div class="sticker">
                      ${content}
                  </div>
                  <script>
                      window.onload = function() {
                          JsBarcode("#barcode", "${serialNumber}", {
                              format: "CODE128",
                              lineColor: "#000",
                              width: 2,
                              height: 100,
                              displayValue: true,
                              fontSize: 20,
                              margin: 0
                          });
                          window.print();
                          window.close();
                      }
                  </script>
              </body>
          </html>
      `);
      printWindow.document.close();
  }

  function downloadSticker() {
      const content = document.getElementById('stickerContent');
      const serialNumber = $('#stickerSerialNumber').text();
      
      // Create a canvas element
      const canvas = document.createElement('canvas');
      const context = canvas.getContext('2d');
      
      // Set canvas size
      canvas.width = 400;
      canvas.height = 200;
      
      // Draw white background
      context.fillStyle = 'white';
      context.fillRect(0, 0, canvas.width, canvas.height);
      
      // Draw asset name
      context.font = '20px Arial';
      context.fillStyle = 'black';
      context.textAlign = 'center';
      context.fillText($('#stickerAssetName').text(), canvas.width/2, 40);
      
      // Draw barcode
      const svg = document.getElementById('barcode');
      const img = new Image();
      const svgData = new XMLSerializer().serializeToString(svg);
      const svgBlob = new Blob([svgData], {type: 'image/svg+xml;charset=utf-8'});
      const svgUrl = URL.createObjectURL(svgBlob);
      
      img.onload = function() {
          context.drawImage(img, 50, 40, 300, 150);
          
          // Convert canvas to image
          const dataURL = canvas.toDataURL('image/png');
          
          // Create download link
          const link = document.createElement('a');
          link.download = 'asset-' + serialNumber + '.png';
          link.href = dataURL;
          link.click();
          
          // Clean up
          URL.revokeObjectURL(svgUrl);
      };
      
      img.src = svgUrl;
  }
  </script>
  <!-- AdminLTE for demo purposes -->
  <script src="<?php echo $home;?>dist/js/demo.js"></script>

  <!-- Add these before the closing body tag -->
  <script src="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.12/cropper.min.js"></script>

  <!-- Update the Add Asset Modal -->
  <div class="modal fade" id="addAssetModal" tabindex="-1" role="dialog" aria-labelledby="addAssetModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="addAssetModalLabel">Add New Asset</h5>
          <button type="button" class="close" data-dismiss="modal" aria-label="Close">
            <span aria-hidden="true">&times;</span>
          </button>
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
                  <input type="file" class="form-control-file" id="assetImage" name="assetImage" accept="image/*" style="display: none;" onchange="openCropModal(event)">
                  <input type="hidden" id="croppedImage" name="croppedImage">
              </div>
              <div class="col-md-8">
            <div class="form-group">
              <label for="assetName">Asset Name</label>
              <input type="text" class="form-control" id="assetName" name="assetName" required>
            </div>
            <div class="form-group">
              <label for="categoryId">Category</label>
              <select class="form-control" id="categoryId" name="categoryId" required>
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
            <div class="form-group">
              <label for="purchaseDate">Purchase Date</label>
              <input type="date" class="form-control" id="purchaseDate" name="purchaseDate" required>
            </div>
              </div>
                  <div class="col-md-6">
            <div class="form-group">
              <label for="purchaseCost">Purchase Cost</label>
                      <div class="input-group">
                        <div class="input-group-prepend">
                          <span class="input-group-text">Rs.</span>
                        </div>
              <input type="number" step="0.01" class="form-control" id="purchaseCost" name="purchaseCost" required>
            </div>
                    </div>
                  </div>
                </div>
                <div class="row">
                  <div class="col-md-6">
            <div class="form-group">
                      <label for="warrantyEndDate">Warranty End Date</label>
                      <input type="date" class="form-control" id="warrantyEndDate" name="warrantyEndDate">
                    </div>
                  </div>
                </div>
                <div class="form-group">
                  <label for="description">Description</label>
                  <textarea class="form-control" id="description" name="description" rows="3"></textarea>
                </div>
              </div>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-primary">Add Asset</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- Update the Edit Asset Modal -->
  <div class="modal fade" id="editAssetModal" tabindex="-1" role="dialog" aria-labelledby="editAssetModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="editAssetModalLabel">Edit Asset</h5>
          <button type="button" class="close" data-dismiss="modal" aria-label="Close">
            <span aria-hidden="true">&times;</span>
          </button>
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
            <div class="form-group">
              <label for="editAssetName">Asset Name</label>
              <input type="text" class="form-control" id="editAssetName" name="assetName" required>
            </div>
            <div class="form-group">
              <label for="editCategoryId">Category</label>
              <select class="form-control" id="editCategoryId" name="categoryId" required>
                <option value="">Select Category</option>
                <?php foreach ($categories as $category): ?>
                  <option value="<?php echo $category['CategoryID']; ?>">
                    <?php echo htmlspecialchars($category['CategoryName']); ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="form-group">
              <label for="editPurchaseDate">Purchase Date</label>
              <input type="date" class="form-control" id="editPurchaseDate" name="purchaseDate" required>
            </div>
                    <div class="form-group">
                      <label for="editWarrantyEndDate">Warranty End Date</label>
                      <input type="date" class="form-control" id="editWarrantyEndDate" name="warrantyEndDate">
                    </div>
                  </div>
                  <div class="col-md-6">
            <div class="form-group">
              <label for="editPurchaseCost">Purchase Cost</label>
              <input type="number" step="0.01" class="form-control" id="editPurchaseCost" name="purchaseCost" required>
            </div>
                    <div class="form-group">
                      <label for="editAssetCondition">Condition</label>
                      <select class="form-control" id="editAssetCondition" name="assetCondition" required>
                        <option value="New">New</option>
                        <option value="Good">Good</option>
                        <option value="Fair">Fair</option>
                        <option value="Poor">Poor</option>
                      </select>
                    </div>
                    <div class="form-group">
                      <label for="editAssetLocation">Location</label>
                      <input type="text" class="form-control" id="editAssetLocation" name="assetLocation" required>
            </div>
            <div class="form-group">
              <label for="editDescription">Description</label>
              <textarea class="form-control" id="editDescription" name="description" rows="3"></textarea>
            </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-primary">Update Asset</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- Update the View Asset Modal -->
  <div class="modal fade" id="viewAssetModal" tabindex="-1" role="dialog" aria-labelledby="viewAssetModalLabel" aria-hidden="true">
      <div class="modal-dialog modal-lg" role="document">
          <div class="modal-content">
              <div class="modal-header">
                  <h5 class="modal-title" id="viewAssetModalLabel">Asset Details</h5>
                  <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                      <span aria-hidden="true">&times;</span>
                  </button>
              </div>
              <div class="modal-body">
                  <div class="row">
                      <div class="col-md-4 text-center">
                          <div id="viewImageContainer">
                              <img id="viewAssetImage" src="" class="asset-image" style="display: none;">
                              <div id="viewDefaultIcon" class="default-asset-icon">
                                  <i class="fas fa-boxes"></i>
                              </div>
                          </div>
                      </div>
                      <div class="col-md-8">
                          <div class="row">
                              <div class="col-md-6">
                                  <div class="form-group">
                                      <label><strong>Asset Name:</strong></label>
                                      <p id="viewAssetName"></p>
                                  </div>
                                  <div class="form-group">
                                      <label><strong>Category:</strong></label>
                                      <p id="viewCategory"></p>
                                  </div>
                                  <div class="form-group">
                                      <label><strong>Serial Number:</strong></label>
                                      <p id="viewSerial"></p>
                                  </div>
                                  <div class="form-group">
                                      <label><strong>Purchase Date:</strong></label>
                                      <p id="viewPurchaseDate"></p>
                                  </div>
                                  <div class="form-group">
                                      <label><strong>Warranty End Date:</strong></label>
                                      <p id="viewWarrantyEnd"></p>
                                  </div>
                              </div>
                              <div class="col-md-6">
                                  <div class="form-group">
                                      <label><strong>Purchase Cost:</strong></label>
                                      <p id="viewPurchaseCost"></p>
                                  </div>
                                  <div class="form-group">
                                      <label><strong>Condition:</strong></label>
                                      <p id="viewCondition"></p>
                                  </div>
                                  <div class="form-group">
                                      <label><strong>Location:</strong></label>
                                      <p id="viewLocation"></p>
                                  </div>
                                  <div class="form-group">
                                      <label><strong>Status:</strong></label>
                                      <p id="viewStatus"></p>
                                  </div>
                              </div>
                          </div>
                          <div class="form-group">
                              <label><strong>Description:</strong></label>
                              <p id="viewDescription"></p>
                          </div>
                      </div>
                  </div>
              </div>
              <div class="modal-footer">
                  <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
              </div>
          </div>
      </div>
  </div>

  <!-- Add Image Cropping Modal -->
  <div class="modal fade" id="imageCropModal" tabindex="-1" role="dialog" aria-labelledby="imageCropModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="imageCropModalLabel">Crop Image</h5>
          <button type="button" class="close" data-dismiss="modal" aria-label="Close">
            <span aria-hidden="true">&times;</span>
          </button>
        </div>
        <div class="modal-body">
          <div class="img-container">
            <img id="imageToCrop" src="" alt="Image to crop">
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
          <button type="button" class="btn btn-primary" id="cropImageBtn">Crop & Save</button>
        </div>
      </div>
    </div>
  </div>

  <!-- Delete Asset Modal -->
  <div class="modal fade" id="deleteAssetModal" tabindex="-1" role="dialog" aria-labelledby="deleteAssetModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="deleteAssetModalLabel">Delete Asset</h5>
          <button type="button" class="close" data-dismiss="modal" aria-label="Close">
            <span aria-hidden="true">&times;</span>
          </button>
        </div>
        <form method="POST" action="manage_assets.php">
          <div class="modal-body">
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="assetId" id="deleteAssetId">
            <p>Are you sure you want to delete the asset: <strong id="deleteAssetName"></strong>?</p>
            <p class="text-danger">This action cannot be undone. All related assignments and maintenance records will also be deleted.</p>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-danger">Delete Asset</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- Add Print Sticker Modal -->
  <div class="modal fade" id="printStickerModal" tabindex="-1" role="dialog" aria-labelledby="printStickerModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="printStickerModalLabel">Print Asset Sticker</h5>
          <button type="button" class="close" data-dismiss="modal" aria-label="Close">
            <span aria-hidden="true">&times;</span>
          </button>
        </div>
        <div class="modal-body">
          <div id="stickerContent" class="text-center">
            <h4 id="stickerAssetName"></h4>
            <svg id="barcode"></svg>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-primary" onclick="downloadSticker()">Download</button>
          <button type="button" class="btn btn-primary" onclick="printSticker()">Print</button>
        </div>
      </div>
    </div>
  </div>

  <!-- Add JsBarcode library -->
  <script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.5/dist/JsBarcode.all.min.js"></script>

  <!-- Add the assets-db.js script -->
  <script src="resources/js/assets-db.js"></script>

  <script>
  // Initialize IndexedDB with server data
  $(document).ready(async function() {
      // Initialize the database with server data
      await assetsDB.initializeDB(<?php echo json_encode($assets); ?>, <?php echo json_encode($categories); ?>);
      
      // Check online status and update UI
      updateOnlineStatus();
      
      // Listen for online/offline events
      window.addEventListener('online', updateOnlineStatus);
      window.addEventListener('offline', updateOnlineStatus);
  });

  // Function to update online status and UI
  function updateOnlineStatus() {
      const isOnline = assetsDB.isOnline();
      const offlineMessage = $('#offline-message');
      
      if (!isOnline) {
          offlineMessage.removeClass('d-none');
          showWarningToast('You are currently offline. Changes will be synced when you are back online.');
          updatePendingChangesCount();
      } else {
          offlineMessage.addClass('d-none');
          // Try to sync any pending changes
          syncPendingChanges();
      }
  }

  // Function to sync pending changes
  async function syncPendingChanges() {
      if (!assetsDB.isOnline()) return;
      
      const changes = await assetsDB.getPendingChanges();
      if (changes.length > 0) {
          try {
              for (const change of changes) {
                  const response = await fetch('/api/sync-assets', {
                      method: 'POST',
                      headers: {
                          'Content-Type': 'application/json'
                      },
                      body: JSON.stringify(change)
                  });
                  
                  if (response.ok) {
                      await assetsDB.markChangeAsSynced(change.id);
                      await updatePendingChangesCount();
                  }
              }
              showSuccessToast('Offline changes synced successfully!');
          } catch (error) {
              console.error('Error syncing changes:', error);
              showErrorToast('Error syncing offline changes');
          }
      }
  }

  // Function to handle asset operations
  async function handleAssetOperation(operation, assetData) {
      if (assetsDB.isOnline()) {
          // Online operation - send to server
          try {
              const response = await fetch('/api/assets', {
                  method: 'POST',
                  headers: {
                      'Content-Type': 'application/json'
                  },
                  body: JSON.stringify({ operation, ...assetData })
              });
              
              if (!response.ok) throw new Error('Server error');
              
              // Update local database
              if (operation === 'add' || operation === 'update') {
                  await assetsDB.saveAsset(assetData);
              } else if (operation === 'delete') {
                  await assetsDB.deleteAsset(assetData.AssetID);
              }
              
              showSuccessToast(`Asset ${operation}d successfully!`);
          } catch (error) {
              showErrorToast(`Error ${operation}ing asset: ${error.message}`);
          }
      } else {
          // Offline operation - queue for later sync
          try {
              await assetsDB.queueOfflineChange({
                  type: operation,
                  data: assetData
              });
              
              // Update local database
              if (operation === 'add' || operation === 'update') {
                  await assetsDB.saveAsset(assetData);
              } else if (operation === 'delete') {
                  await assetsDB.deleteAsset(assetData.AssetID);
              }
              
              await updatePendingChangesCount();
              showSuccessToast(`Asset ${operation}d (offline). Changes will sync when online.`);
          } catch (error) {
              showErrorToast(`Error ${operation}ing asset offline: ${error.message}`);
          }
      }
      
      // Refresh the asset list
      refreshAssetList();
  }

  // Function to refresh the asset list
  async function refreshAssetList() {
      const assets = await assetsDB.getAllAssets();
      const categories = await assetsDB.getAllCategories();
      
      // Update the table with the new data
      const tbody = $('#assetsTable tbody');
      tbody.empty();
      
      assets.forEach(asset => {
          const category = categories.find(c => c.CategoryID === asset.CategoryID);
          const row = `
              <tr>
                  <td>${asset.AssetSerial || ''}</td>
                  <td>${asset.AssetName}</td>
                  <td>${category ? category.CategoryName : ''}</td>
                  <td>${asset.PurchaseDate}</td>
                  <td>Rs. ${asset.PurchaseCost}</td>
                  <td>${asset.Status}</td>
                  <td>
                      <div class="btn-group">
                          <button type="button" class="btn btn-default dropdown-toggle" data-toggle="dropdown">
                              <i class="fas fa-ellipsis-v"></i>
                          </button>
                          <div class="dropdown-menu">
                              <a class="dropdown-item view-asset" href="#" data-id="${asset.AssetID}">
                                  <i class="fas fa-eye"></i> View
                              </a>
                              <a class="dropdown-item edit-asset" href="#" data-id="${asset.AssetID}">
                                  <i class="fas fa-edit"></i> Edit
                              </a>
                              <a class="dropdown-item print-sticker" href="#" data-id="${asset.AssetID}">
                                  <i class="fas fa-print"></i> Print Sticker
                              </a>
                              <a class="dropdown-item delete-asset" href="#" data-id="${asset.AssetID}">
                                  <i class="fas fa-trash"></i> Delete
                              </a>
                          </div>
                      </div>
                  </td>
              </tr>
          `;
          tbody.append(row);
      });
  }

  // Add offline message with pending changes count
  $('#content-wrapper').prepend(`
      <div id="offline-message" class="alert alert-warning d-none" role="alert">
          <i class="fas fa-exclamation-triangle"></i> 
          <span id="offline-status">You are currently offline.</span>
          <span id="pending-changes" class="d-none">
              <span id="pending-count">0</span> changes pending sync.
          </span>
      </div>
  `);

  // Function to update pending changes count
  async function updatePendingChangesCount() {
      const changes = await assetsDB.getPendingChanges();
      const count = changes.length;
      const pendingCount = $('#pending-count');
      const pendingChanges = $('#pending-changes');
      
      if (count > 0) {
          pendingCount.text(count);
          pendingChanges.removeClass('d-none');
      } else {
          pendingChanges.addClass('d-none');
      }
  }
  </script>

  </body>
  </html>