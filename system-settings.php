<?php
// Start output buffering to prevent "headers already sent" issues
ob_start();

// First, check if this is an AJAX image upload request and process it immediately
// before including any other files that might send output
if (isset($_POST['image_data']) && $_SERVER['REQUEST_METHOD'] === 'POST' && 
    isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
    
    // Include only the essential files needed for processing the upload
    require_once __DIR__ . '/includes/db_connection.php';
    require_once __DIR__ . '/includes/settings.php';
    
    $response = [
        'success' => false,
        'message' => '',
        'file_path' => ''
    ];
    
    try {
        // Get the image data from the request
        $imageData = $_POST['image_data'];
        
        // Extract the base64 encoded image
        if (preg_match('/^data:image\/(\w+);base64,/', $imageData, $matches)) {
            $imageType = $matches[1];
            $imageData = substr($imageData, strpos($imageData, ',') + 1);
            $imageData = base64_decode($imageData);
            
            if ($imageData === false) {
                throw new Exception('Failed to decode image data');
            }
            
            // Create a unique filename
            $filename = 'company_logo_' . time() . '.' . $imageType;
            $uploadDir = __DIR__ . '/resources/images/';
            $uploadPath = $uploadDir . $filename;
            $relativePath = 'resources/images/' . $filename;
            
            // Ensure upload directory exists
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            
            // Save the image file
            if (file_put_contents($uploadPath, $imageData)) {
                // Update the company_logo setting in the database
                save_setting('company_logo', $relativePath);
                
                $response['success'] = true;
                $response['message'] = 'Logo uploaded and saved successfully!';
                $response['file_path'] = $relativePath;
            } else {
                throw new Exception('Failed to save the image file');
            }
        } else {
            throw new Exception('Invalid image data format');
        }
    } catch (Exception $e) {
        $response['message'] = 'Error: ' . $e->getMessage();
    }
    
    // Clear any previous output
    if (ob_get_length()) {
        ob_clean();
    }
    
    // Return JSON response for AJAX request
    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}

$page = 'System Setting';
require_once __DIR__ . '/includes/header.php';
include 'includes/db_connection.php';

// Handle regular form submission
if ($_SERVER["REQUEST_METHOD"] == "POST" && !isset($_POST['image_data'])) {
  try {
    // Define the settings we want to save
    $settings = [
      'app_name' => $_POST['app_name'] ?? 'HRMS Pro',
      'company_name' => $_POST['company_name'] ?? 'Prime Express',
      'company_full_name' => $_POST['company_full_name'] ?? 'Prime Express Courier & Cargo Pvt. Ltd.',
      'company_address' => $_POST['company_address'] ?? '',
      'company_phone' => $_POST['company_phone'] ?? '',
      'company_logo' => $_POST['company_logo'] ?? '',
      'company_primary_color' => $_POST['company_primary_color'] ?? '#007bff',
      'company_secondary_color' => $_POST['company_secondary_color'] ?? '#6c757d',
      'company_work_hour' => $_POST['company_work_hour'] ?? '9:00 AM - 5:00 PM',
      'timezone' => $_POST['timezone'] ?? 'Asia/Kathmandu'
    ];
    
    // Update or insert each setting
    foreach ($settings as $key => $value) {
      // Check if this setting exists
      $stmt = $pdo->prepare("SELECT COUNT(*) FROM settings WHERE setting_key = ?");
      $stmt->execute([$key]);
      $exists = $stmt->fetchColumn() > 0;
      
      if ($exists) {
        // Update existing setting
        $stmt = $pdo->prepare("UPDATE settings SET value = ?, modified_at = NOW() WHERE setting_key = ?");
        $stmt->execute([$value, $key]);
      } else {
        // Insert new setting
        $stmt = $pdo->prepare("INSERT INTO settings (setting_key, value, created_at, modified_at) VALUES (?, ?, NOW(), NOW())");
        $stmt->execute([$key, $value]);
      }
    }
    
    // Set timezone from settings
    if (isset($settings['timezone'])) {
      date_default_timezone_set($settings['timezone']);
    }
    
    $_SESSION['success'] = "System settings updated successfully!";
    header('Location: system-settings.php');
    exit();
  } catch (PDOException $e) {
    $_SESSION['error'] = "Error updating settings: " . $e->getMessage();
  }
}

// Fetch settings from the database
try {
  $stmt = $pdo->prepare("SELECT setting_key, value FROM settings");
  $stmt->execute();
  $settings = [];
  
  while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $settings[$row['setting_key']] = $row['value'];
  }
  
  // Set default values for missing settings
  $defaultSettings = [
    'app_name' => 'HRMS Pro',
    'company_name' => 'Prime Express',
    'company_address' => '123 Main St, City, Country',
    'company_phone' => '+1234567890',
    'company_logo' => 'company_logo.png',
    'company_primary_color' => '#007bff',
    'company_secondary_color' => '#6c757d',
    'company_work_hour' => '9:00 AM - 5:00 PM',
    'timezone' => 'Asia/Kathmandu'
  ];
  
  foreach ($defaultSettings as $key => $value) {
    if (!isset($settings[$key])) {
      $settings[$key] = $value;
    }
  }
  
  // Set timezone from settings
  if (isset($settings['timezone'])) {
    date_default_timezone_set($settings['timezone']);
  }
  
} catch (PDOException $e) {
  $_SESSION['error'] = "Error fetching settings: " . $e->getMessage();
  
  // Default settings as fallback
  $settings = [
    'app_name' => 'HRMS Pro',
    'company_name' => 'Prime Express',
    'company_address' => '123 Main St, City, Country',
    'company_phone' => '+1234567890',
    'company_logo' => 'company_logo.png',
    'company_primary_color' => '#007bff',
    'company_secondary_color' => '#6c757d',
    'company_work_hour' => '9:00 AM - 5:00 PM',
    'timezone' => 'Asia/Kathmandu'
  ];
}

?>

<!-- Cropper.js CSS -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.12/cropper.min.css">

<style>
    .profile-picture-container {
        position: relative;
        width: 200px;
        height: 200px;
        border-radius: 50%;
        margin: 0 auto;
        overflow: hidden;
        border: 5px solid rgba(var(--bs-primary-rgb), 0.1);
        box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
    }
    .profile-picture-container img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }
    .profile-picture-container .upload-overlay {
        position: absolute;
        bottom: 0;
        left: 0;
        width: 100%;
        height: 30%;
        text-align: center;
        display: none;
        background-color: rgba(0, 0, 0, 0.6);
        transition: all 0.3s ease;
    }
    .profile-picture-container:hover .upload-overlay {
        display: flex;
        align-items: center;
        justify-content: center;
    }
    .upload-overlay button {
        background: none;
        border: none;
        color: white;
        font-size: 1rem;
        cursor: pointer;
    }
    .upload-overlay button:hover {
        color: var(--bs-info);
    }

    /* Crop Modal CSS */
    .img-container {
        max-height: 400px;
        overflow: hidden;
    }
    .img-container img {
        max-width: 100%;
        display: block;
    }
    
    /* Dark mode adjustments */
    body.dark-mode .profile-picture-container {
        border-color: rgba(255, 255, 255, 0.1);
    }
    
    body.dark-mode .card-header {
        background-color: #2c3136;
        border-color: #495057;
    }
    
    body.dark-mode .card {
        background-color: #343a40;
        border-color: #495057;
    }
</style>

<!-- Content Header (Page header) -->
<div class="container-fluid p-4">
  <!-- Page header -->
  <div class="d-flex justify-content-between align-items-center mb-4">
    <div>
      <h1 class="fs-2 fw-bold mb-1">System Settings</h1>
    </div>
    <button type="submit" form="settingsForm" class="btn btn-primary">
      <i class="fas fa-save me-2"></i> Save Settings
    </button>
  </div>
  
  <!-- Settings Card -->
  <div class="card border-0 shadow-sm">
    <div class="card-body">
      <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
          <?php echo $_SESSION['success']; ?>
          <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php unset($_SESSION['success']); ?>
      <?php endif; ?>
      
      <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
          <?php echo $_SESSION['error']; ?>
          <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php unset($_SESSION['error']); ?>
      <?php endif; ?>
      
      <form id="settingsForm" method="POST" action="">
        <div class="row">
          <div class="col-md-3 order-md-2">
            <!-- Company Logo Preview and Upload -->
            <div class="card border-0 shadow-sm mb-4">
              <div class="card-body box-profile text-center">
                <div class="profile-picture-container mb-3">
                  <?php 
                  $logoPath = $settings['company_logo'] ?? 'company_logo.png';
                  $logoFullPath = $logoPath;
                  
                  // Check if the path is relative and convert to a path the file_exists function can check
                  if (!empty($logoPath) && $logoPath[0] !== '/' && !preg_match('~^\w+://~', $logoPath)) {
                    $logoFullPath = __DIR__ . '/' . $logoPath;
                  }
                  
                  // Debug the logo path (can be removed after fixing)
                  // error_log("Logo path: " . $logoPath . ", Full path: " . $logoFullPath . ", Exists: " . (file_exists($logoFullPath) ? 'Yes' : 'No'));
                  
                  if (!empty($logoPath) && (file_exists($logoFullPath) || filter_var($logoPath, FILTER_VALIDATE_URL))): 
                  ?>
                    <img id="companyLogo" src="<?php echo htmlspecialchars($logoPath); ?>?t=<?php echo time(); ?>" alt="Company Logo" class="img-fluid">
                  <?php else: ?>
                    <div class="d-flex justify-content-center align-items-center" style="height: 100%; background-color: #f8f9fa;">
                      <i class="fas fa-building fa-4x text-muted"></i>
                    </div>
                  <?php endif; ?>
                  <div class="upload-overlay">
                    <button type="button" onclick="document.getElementById('logoFileInput').click();">
                      <i class="fas fa-camera"></i> Change
                    </button>
                  </div>
                </div>
                <input type="file" class="d-none" id="logoFileInput" accept="image/*" onchange="openCropModal(event)">
                <input type="hidden" id="croppedImage" name="croppedImage">
                <input type="hidden" id="company_logo" name="company_logo" value="<?php echo htmlspecialchars($settings['company_logo'] ?? 'company_logo.png'); ?>">
                <h5 class="mb-1">Company Logo</h5>
                <p class="text-muted small">Recommended size: 200x200 pixels</p>
              </div>
            </div>
          </div>
          
          <div class="col-md-9 order-md-1">
            <div class="mb-3">
              <label for="app_name" class="form-label">Application Name</label>
              <input type="text" class="form-control" id="app_name" name="app_name" 
                     value="<?php echo htmlspecialchars($settings['app_name'] ?? 'HRMS Pro'); ?>" required>
            </div>
            
            <div class="row">
              <div class="col-md-6 mb-3">
                <label for="company_name" class="form-label">Company Short Name</label>
                <input type="text" class="form-control" id="company_name" name="company_name" 
                       value="<?php echo htmlspecialchars($settings['company_name'] ?? 'Prime Express'); ?>" required>
              </div>
              
              <div class="col-md-6 mb-3">
                <label for="company_full_name" class="form-label">Company Full Name</label>
                <input type="text" class="form-control" id="company_full_name" name="company_full_name" 
                       value="<?php echo htmlspecialchars($settings['company_full_name'] ?? 'Prime Express Courier & Cargo Pvt. Ltd.'); ?>">
              </div>
            </div>
            
            <div class="row">
              <div class="col-md-6 mb-3">
                <label for="company_address" class="form-label">Company Address</label>
                <input type="text" class="form-control" id="company_address" name="company_address" 
                       value="<?php echo htmlspecialchars($settings['company_address'] ?? '123 Main St, City'); ?>">
              </div>
              
              <div class="col-md-6 mb-3">
                <label for="company_phone" class="form-label">Company Phone</label>
                <input type="text" class="form-control" id="company_phone" name="company_phone" 
                       value="<?php echo htmlspecialchars($settings['company_phone'] ?? '+1234567890'); ?>">
              </div>
            </div>
            
            <div class="row">
              <div class="col-md-6 mb-3">
                <label for="company_primary_color" class="form-label">Primary Color</label>
                <div class="input-group">
                  <input type="color" class="form-control form-control-color" id="company_primary_color" name="company_primary_color" 
                         value="<?php echo htmlspecialchars($settings['company_primary_color'] ?? '#007bff'); ?>" 
                         title="Choose primary color">
                  <input type="text" class="form-control" id="primary_color_hex" 
                         value="<?php echo htmlspecialchars($settings['company_primary_color'] ?? '#007bff'); ?>" 
                         readonly>
                </div>
              </div>
              
              <div class="col-md-6 mb-3">
                <label for="company_secondary_color" class="form-label">Secondary Color</label>
                <div class="input-group">
                  <input type="color" class="form-control form-control-color" id="company_secondary_color" name="company_secondary_color" 
                         value="<?php echo htmlspecialchars($settings['company_secondary_color'] ?? '#6c757d'); ?>" 
                         title="Choose secondary color">
                  <input type="text" class="form-control" id="secondary_color_hex" 
                         value="<?php echo htmlspecialchars($settings['company_secondary_color'] ?? '#6c757d'); ?>" 
                         readonly>
                </div>
              </div>
            </div>
            
            <div class="row">
              <div class="col-md-6 mb-3">
                <label for="company_work_hour" class="form-label">Work Hours</label>
                <div class="input-group">
                  <span class="input-group-text"><i class="fas fa-clock"></i></span>
                  <input type="text" class="form-control" id="company_work_hour" name="company_work_hour" 
                         value="<?php echo htmlspecialchars($settings['company_work_hour'] ?? '9:00 AM - 5:00 PM'); ?>"
                         placeholder="9:00 AM - 5:00 PM">
                </div>
                <small class="form-text text-muted">Enter work hours in format: Start Time - End Time</small>
              </div>
              
              <div class="col-md-6 mb-3">
                <label for="timezone" class="form-label">Timezone</label>
                <select class="form-select" id="timezone" name="timezone">
                  <?php
                  $timezones = DateTimeZone::listIdentifiers();
                  $currentTimezone = $settings['timezone'] ?? 'Asia/Kathmandu';
                  
                  foreach ($timezones as $timezone) {
                    // Create a DateTime object for this timezone to get the current time and offset
                    $dateTime = new DateTime('now', new DateTimeZone($timezone));
                    $offset = $dateTime->format('P'); // Format as +00:00
                    
                    // Get a more user-friendly name (replace slashes with spaces, remove underscores)
                    $displayName = str_replace(['_', '/'], [' ', ' - '], $timezone);
                    
                    // Format the option as "Region - City (GMT+00:00)"
                    $formattedOption = $displayName . ' (GMT' . $offset . ')';
                    
                    $selected = ($timezone == $currentTimezone) ? 'selected' : '';
                    echo "<option value=\"$timezone\" $selected>$formattedOption</option>";
                  }
                  ?>
                </select>
              </div>
            </div>
          </div>
        </div>
        
        <!-- Rest of the form remains the same -->
        <div class="row mt-3">
          <div class="col-12">
            <h5 class="mb-3">Preview</h5>
            <div class="row">
              <!-- Light Mode Preview (Forcing light mode styles) -->
              <div class="col-md-6">
                <div class="card border mb-3" style="background-color: #ffffff; color: #212529;">
                  <div class="card-header" style="background-color: #f8f9fa; color: #212529; border-bottom: 1px solid rgba(0,0,0,.125);">
                    <h6 class="mb-0">Light Mode</h6>
                  </div>
                  <div class="card-body p-3 border-bottom light-preview" style="border-color: rgba(0,0,0,.125);">
                    <h4 class="preview-app-name" style="color: #212529;"><?php echo htmlspecialchars($settings['app_name'] ?? 'HRMS Pro'); ?></h4>
                    <p class="preview-company-name" style="color: #212529;"><?php echo htmlspecialchars($settings['company_name'] ?? 'Prime Express'); ?></p>
                    <p class="preview-company-address small" style="color: #6c757d;"><?php echo htmlspecialchars($settings['company_address'] ?? '123 Main St, City, Country'); ?></p>
                    <p class="preview-company-phone small" style="color: #6c757d;"><?php echo htmlspecialchars($settings['company_phone'] ?? '+1234567890'); ?></p>
                    <p class="preview-company-hours small" style="color: #6c757d;"><?php echo htmlspecialchars($settings['company_work_hour'] ?? '9:00 AM - 5:00 PM'); ?></p>
                    <div class="d-flex mt-3">
                      <button class="btn preview-primary-btn-light">Primary Button</button>
                      <button class="btn preview-secondary-btn-light ms-2">Secondary Button</button>
                    </div>
                    <div class="mt-3">
                      <div class="form-group">
                        <label class="form-label" style="color: #212529;">Sample Input</label>
                        <input type="text" class="form-control" style="background-color: #fff; color: #212529; border-color: #ced4da;" placeholder="Sample input field">
                      </div>
                      <div class="form-check mt-2">
                        <input class="form-check-input" type="checkbox" id="lightCheckbox" style="background-color: #fff; border-color: #ced4da;">
                        <label class="form-check-label" for="lightCheckbox" style="color: #212529;">Sample checkbox</label>
                      </div>
                      <div class="alert alert-info mt-2" style="background-color: #cff4fc; color: #055160; border-color: #b6effb;">
                        This is how alerts will appear
                      </div>
                    </div>
                  </div>
                </div>
              </div>
              
              <!-- Dark Mode Preview (Forcing dark mode styles) -->
              <div class="col-md-6">
                <div class="card border mb-3" style="background-color: #343a40; color: #f8f9fa; border-color: #495057;">
                  <div class="card-header" style="background-color: #212529; color: #f8f9fa; border-bottom: 1px solid #495057;">
                    <h6 class="mb-0">Dark Mode</h6>
                  </div>
                  <div class="card-body p-3 border-bottom dark-preview" style="border-color: #495057;">
                    <h4 class="preview-app-name" style="color: #f8f9fa;"><?php echo htmlspecialchars($settings['app_name'] ?? 'HRMS Pro'); ?></h4>
                    <p class="preview-company-name" style="color: #f8f9fa;"><?php echo htmlspecialchars($settings['company_name'] ?? 'Prime Express'); ?></p>
                    <p class="preview-company-address small" style="color: #adb5bd;"><?php echo htmlspecialchars($settings['company_address'] ?? '123 Main St, City, Country'); ?></p>
                    <p class="preview-company-phone small" style="color: #adb5bd;"><?php echo htmlspecialchars($settings['company_phone'] ?? '+1234567890'); ?></p>
                    <p class="preview-company-hours small" style="color: #adb5bd;"><?php echo htmlspecialchars($settings['company_work_hour'] ?? '9:00 AM - 5:00 PM'); ?></p>
                    <div class="d-flex mt-3">
                      <button class="btn preview-primary-btn-dark">Primary Button</button>
                      <button class="btn preview-secondary-btn-dark ms-2">Secondary Button</button>
                    </div>
                    <div class="mt-3">
                      <div class="form-group">
                        <label class="form-label" style="color: #f8f9fa;">Sample Input</label>
                        <input type="text" class="form-control" style="background-color: #343a40; color: #f8f9fa; border-color: #495057;" placeholder="Sample input field">
                      </div>
                      <div class="form-check mt-2">
                        <input class="form-check-input" type="checkbox" id="darkCheckbox" style="background-color: #343a40; border-color: #495057;">
                        <label class="form-check-label" for="darkCheckbox" style="color: #f8f9fa;">Sample checkbox</label>
                      </div>
                      <div class="alert mt-2" style="background-color: #32383e; color: #9aaab7; border-color: #475561;">
                        This is how alerts will appear
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Image Cropping Modal -->
<div class="modal fade" id="imageCropModal" tabindex="-1" aria-labelledby="imageCropModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="imageCropModalLabel">Crop Company Logo</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div class="img-container" style="max-height: 400px;">
          <img id="imageToCrop" src="" class="img-fluid" alt="Image to crop">
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-primary" id="cropImageBtn">Crop & Save</button>
      </div>
    </div>
  </div>
</div>

<!-- Include Cropper.js -->
<link href="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.12/cropper.min.css" rel="stylesheet">
<script src="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.12/cropper.min.js"></script>

<!-- Include flatpickr JavaScript -->
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
  // Update color hex value when color picker changes
  document.getElementById('company_primary_color').addEventListener('input', function() {
    document.getElementById('primary_color_hex').value = this.value;
    updatePreview();
  });
  
  document.getElementById('company_secondary_color').addEventListener('input', function() {
    document.getElementById('secondary_color_hex').value = this.value;
    updatePreview();
  });
  
  // Update preview when input changes
  document.getElementById('app_name').addEventListener('input', updatePreview);
  document.getElementById('company_name').addEventListener('input', updatePreview);
  document.getElementById('company_address').addEventListener('input', updatePreview);
  document.getElementById('company_phone').addEventListener('input', updatePreview);
  document.getElementById('company_work_hour').addEventListener('input', updatePreview);
  
  // Initial preview update
  updatePreview();
  
  let cropper;
  const imageCropModal = new bootstrap.Modal(document.getElementById('imageCropModal'));
  
  // Function to open crop modal and initialize cropper
  window.openCropModal = function(event) {
    const file = event.target.files[0];
    if (!file) return;
    
    // Validate file size and type
    if (file.size > 5 * 1024 * 1024) {
      alert('Image file is too large. Maximum size is 5MB.');
      return;
    }
    
    if (!['image/jpeg', 'image/png', 'image/gif', 'image/webp'].includes(file.type)) {
      alert('Only JPG, PNG, GIF, and WebP images are allowed.');
      return;
    }
    
    const reader = new FileReader();
    reader.onload = function(e) {
      document.getElementById('imageToCrop').src = e.target.result;
      
      // Show the crop modal
      imageCropModal.show();
      
      // Initialize cropper with a small delay to ensure the modal is visible
      setTimeout(() => {
        if (cropper) {
          cropper.destroy();
        }
        
        cropper = new Cropper(document.getElementById('imageToCrop'), {
          aspectRatio: 1, // Square crop
          viewMode: 1,
          dragMode: 'move',
          autoCropArea: 1,
          restore: false,
          guides: true,
          center: true,
          highlight: false,
          cropBoxMovable: true,
          cropBoxResizable: true,
          toggleDragModeOnDblclick: false
        });
      }, 300);
    };
    reader.readAsDataURL(file);
  };
  
  // Handle crop and save button
  document.getElementById('cropImageBtn').addEventListener('click', function() {
    if (!cropper) return;
    
    const canvas = cropper.getCroppedCanvas({
      width: 200,
      height: 200,
      minWidth: 100,
      minHeight: 100,
      maxWidth: 1000,
      maxHeight: 1000,
      fillColor: '#fff',
      imageSmoothingEnabled: true,
      imageSmoothingQuality: 'high',
    });
    
    const dataURL = canvas.toDataURL('image/png');
    
    // Show loading state
    const btnText = this.innerHTML;
    this.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Saving...';
    this.disabled = true;
    
    // Send cropped image to server
    fetch('system-settings.php', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/x-www-form-urlencoded',
        'X-Requested-With': 'XMLHttpRequest'
      },
      body: 'image_data=' + encodeURIComponent(dataURL)
    })
    .then(response => response.json())
    .then(data => {
      // Reset button state
      this.innerHTML = btnText;
      this.disabled = false;
      
      // Close modal
      imageCropModal.hide();
      
      if (data.success) {
        // Update the logo in the form and UI
        document.getElementById('company_logo').value = data.file_path;
        
        // Update the preview image with cache-busting
        const companyLogo = document.getElementById('companyLogo');
        
        // If there's no image yet, we need to create one
        if (!companyLogo) {
          const container = document.querySelector('.profile-picture-container');
          container.innerHTML = `<img id="companyLogo" src="${data.file_path}?t=${new Date().getTime()}" alt="Company Logo" class="img-fluid">
          <div class="upload-overlay">
            <button type="button" onclick="document.getElementById('logoFileInput').click();">
              <i class="fas fa-camera"></i> Change
            </button>
          </div>`;
        } else {
          companyLogo.src = `${data.file_path}?t=${new Date().getTime()}`;
        }
        
        // Show success message
        const alertHTML = `
          <div class="alert alert-success alert-dismissible fade show" role="alert">
            Logo updated successfully!
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
          </div>
        `;
        document.querySelector('.card-body').insertAdjacentHTML('afterbegin', alertHTML);
      } else {
        // Show error message
        alert('Error uploading logo: ' + data.message);
      }
    })
    .catch(error => {
      console.error('Error:', error);
      this.innerHTML = btnText;
      this.disabled = false;
      alert('Error uploading logo. Please try again.');
    });
    
    // Clean up cropper
    if (cropper) {
      cropper.destroy();
      cropper = null;
    }
  });
  
  function updatePreview() {
    const appName = document.getElementById('app_name').value;
    const companyName = document.getElementById('company_name').value;
    const companyAddress = document.getElementById('company_address').value;
    const companyPhone = document.getElementById('company_phone').value;
    const companyHours = document.getElementById('company_work_hour').value;
    const primaryColor = document.getElementById('company_primary_color').value;
    const secondaryColor = document.getElementById('company_secondary_color').value;
    
    // Update text content in both previews
    document.querySelectorAll('.preview-app-name').forEach(el => el.textContent = appName);
    document.querySelectorAll('.preview-company-name').forEach(el => el.textContent = companyName);
    document.querySelectorAll('.preview-company-address').forEach(el => el.textContent = companyAddress);
    document.querySelectorAll('.preview-company-phone').forEach(el => el.textContent = companyPhone);
    document.querySelectorAll('.preview-company-hours').forEach(el => el.textContent = companyHours);
    
    // Update Light Mode buttons
    document.querySelector('.preview-primary-btn-light').style.backgroundColor = primaryColor;
    document.querySelector('.preview-primary-btn-light').style.borderColor = primaryColor;
    document.querySelector('.preview-primary-btn-light').style.color = '#ffffff';
    
    document.querySelector('.preview-secondary-btn-light').style.backgroundColor = secondaryColor;
    document.querySelector('.preview-secondary-btn-light').style.borderColor = secondaryColor;
    document.querySelector('.preview-secondary-btn-light').style.color = '#ffffff';
    
    // Update Dark Mode buttons
    document.querySelector('.preview-primary-btn-dark').style.backgroundColor = primaryColor;
    document.querySelector('.preview-primary-btn-dark').style.borderColor = primaryColor;
    document.querySelector('.preview-primary-btn-dark').style.color = '#ffffff';
    
    document.querySelector('.preview-secondary-btn-dark').style.backgroundColor = secondaryColor;
    document.querySelector('.preview-secondary-btn-dark').style.borderColor = secondaryColor;
    document.querySelector('.preview-secondary-btn-dark').style.color = '#ffffff';
    
    // Add a subtle outline to dark mode buttons for better visibility
    document.querySelector('.preview-primary-btn-dark').style.boxShadow = '0 0 0 1px rgba(255,255,255,0.2)';
    document.querySelector('.preview-secondary-btn-dark').style.boxShadow = '0 0 0 1px rgba(255,255,255,0.2)';
  }
});
</script>

<?php include 'includes/footer.php'; ?>
</body>
</html>