<?php include 'includes/configuration.php';
// Start the session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Get the current script's filename
$current_file = basename($_SERVER['SCRIPT_FILENAME']);

// Check if the current file is NOT index.php and if the session variable 'user_id' is set
if ($current_file !== 'index.php' && !isset($_SESSION['user_id'])) {
    // Get the current page URL
    $current_page = $_SERVER['REQUEST_URI'];

    // Store the current page URL in a session variable
    $_SESSION['redirect_to'] = $current_page;

    // Redirect to the login page
    header("Location: index.php");
    exit();
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?php echo $appName . " | " . ucwords($page);?></title>

  <!-- PWA Meta Tags -->
  <meta name="theme-color" content="#007bff">
  <meta name="description" content="Human Resource Management System">
  <link rel="manifest" href="manifest.json">
  <link rel="apple-touch-icon" href="resources/images/icon-192x192.png">
  
  <!-- Favicon -->
  <link rel="icon" type="image/png" href="resources/images/favicon.png">
  
  <!-- Google Fonts -->
  <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
  
  <!-- Font Awesome -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
  
  <!-- jQuery -->
  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
  
  <!-- Bootstrap CSS -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.0/dist/css/bootstrap.min.css">
  
  <!-- Custom CSS -->
  <link rel="stylesheet" href="<?php echo $home;?>resources/css/style.css">
  
  <!-- Google Font: Source Sans Pro -->
  <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Source+Sans+Pro:300,400,400i,700&display=fallback">
  <!-- Font Awesome Icons -->
  <link rel="stylesheet" href="<?php echo $home;?>plugins/fontawesome-free/css/all.min.css">
  <!-- IonIcons -->
  <link rel="stylesheet" href="https://code.ionicframework.com/ionicons/2.0.1/css/ionicons.min.css">
  <!-- Theme style -->
  <link rel="stylesheet" href="<?php echo $home;?>dist/css/adminlte.min.css">
  <!-- Customs Style -->
  <link rel="stylesheet" href="<?php echo $home;?>dist/css/customs.css">
  <!-- SweetAlert2 CSS -->
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
    /* Online/Offline Indicator Styles */
    .connection-status {
      display: flex;
      align-items: center;
      padding: 0.5rem;
      margin-right: 0.5rem;
    }
    .status-dot {
      width: 8px;
      height: 8px;
      border-radius: 50%;
      margin-right: 5px;
    }
    .status-dot.online {
      background-color: #28a745;
    }
    .status-dot.offline {
      background-color: #6c757d;
    }
    .status-text {
      font-size: 0.8rem;
      font-weight: 500;
      color: #343a40;
    }
    body.dark-mode .status-text {
      color: #ffffff;
    }

    /* Dark Mode Base Colors */
    body.dark-mode {
        background-color: #343a40 !important;
        color: #ffffff !important;
    }
    
    /* Dark Mode Content Wrapper */
    body.dark-mode .content-wrapper {
        background-color: #343a40 !important;
    }
    
    /* Dark Mode Brand Link */
    body.dark-mode .brand-link {
        background-color: #343a40 !important;
        border-bottom: 1px solid #4b545c !important;
    }
    
    /* Dark Mode Rows and Breadcrumbs */
    body.dark-mode .content-header {
        background-color: #343a40 !important;
        color: #ffffff !important;
    }
    body.dark-mode .content-header h1 {
        color: #ffffff !important;
    }
    body.dark-mode .breadcrumb {
        background-color: #343a40 !important;
        color: #ffffff !important;
    }
    body.dark-mode .breadcrumb-item a {
        color: #ffffff !important;
    }
    body.dark-mode .breadcrumb-item.active {
        color: #adb5bd !important;
    }
    body.dark-mode .row {
        background-color: #343a40 !important;
    }
    body.dark-mode .card {
        background-color: #343a40 !important;
        border-color: #4b545c !important;
    }
    body.dark-mode .card-header {
        background-color: #343a40 !important;
        border-bottom-color: #4b545c !important;
        color: #ffffff !important;
    }
    body.dark-mode .card-body {
        background-color: #343a40 !important;
        color: #ffffff !important;
    }
    
    /* Dark Mode Header */
    body.dark-mode .main-header {
        background-color: #343a40 !important;
        border-bottom: 1px solid #4b545c !important;
    }
    
    /* Dark Mode Forms */
    body.dark-mode .form-control {
        background-color: #343a40 !important;
        border-color: #4b545c !important;
        color: #ffffff !important;
    }
    body.dark-mode .form-control:focus {
        background-color: #343a40 !important;
        border-color: #007bff !important;
        color: #ffffff !important;
    }
    
    /* Dark Mode Buttons */
    body.dark-mode .btn-primary {
        background-color: #007bff !important;
        border-color: #007bff !important;
    }
    
    /* Dark Mode Text */
    body.dark-mode .text-muted {
        color: #adb5bd !important;
    }
    
    /* Dark Mode Input Groups */
    body.dark-mode .input-group-text {
        background-color: #343a40 !important;
        border-color: #4b545c !important;
        color: #ffffff !important;
    }
    
    /* Dark Mode Checkboxes */
    body.dark-mode .icheck-primary input[type="checkbox"]:checked + label::before {
        background-color: #007bff !important;
        border-color: #007bff !important;
    }
    body.dark-mode .icheck-primary label {
        color: #ffffff !important;
    }
    /* Topbar Styles */
    .navbar-white {
      background-color: #ffffff !important;
      color: #343a40 !important;
    }
    .navbar-light .navbar-nav .nav-link {
      color: #343a40 !important;
    }
    .navbar-light .navbar-nav .nav-link:hover {
      color: #2980b9 !important;
    }
    .navbar-light .navbar-nav .nav-link i {
      color: #343a40 !important;
    }
    .navbar-light .navbar-nav .nav-link:hover i {
      color: #2980b9 !important;
    }
    /* Dark mode topbar overrides */
    body.dark-mode .navbar-white {
      background-color: #1a1a1a !important;
      color: #ffffff !important;
    }
    body.dark-mode .navbar-light .navbar-nav .nav-link {
      color: #ffffff !important;
    }
    body.dark-mode .navbar-light .navbar-nav .nav-link:hover {
      color: #2980b9 !important;
    }
    body.dark-mode .navbar-light .navbar-nav .nav-link i {
      color: #ffffff !important;
    }
    body.dark-mode .navbar-light .navbar-nav .nav-link:hover i {
      color: #2980b9 !important;
    }
    /* light mode form label */
    body.light-mode .form-control:focus + .form-label, .form-control:not(:placeholder-shown) + .form-label {
      background-color: #ffffff !important;
    }
  </style>
<?php include 'includes/pwa_install.php'; ?>