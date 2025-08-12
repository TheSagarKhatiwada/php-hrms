<?php 
// Prevent caching in browsers and proxies
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Expires: 0");

// Start output buffering to prevent "headers already sent" issues
ob_start();

// Include session configuration file first - this contains the consistent session name setting
require_once __DIR__ . '/session_config.php';

include __DIR__ . '/configuration.php';
include __DIR__ . '/db_connection.php'; // Ensure DB connection is available
include __DIR__ . '/settings.php'; // Include settings
include __DIR__ . '/csrf_protection.php'; // Include CSRF protection functions

// Define the adjustBrightness function at the beginning of the file
if (!function_exists('adjustBrightness')) {
    function adjustBrightness($hex, $steps) {
        // Steps should be between -255 and 255. Negative = darker, positive = lighter
        $steps = max(-255, min(255, $steps));

        // Format the hex color string
        $hex = str_replace('#', '', $hex);
        if (strlen($hex) == 3) {
            $hex = str_repeat(substr($hex, 0, 1), 2) . str_repeat(substr($hex, 1, 1), 2) . str_repeat(substr($hex, 2, 1), 2);
        }

        // Get RGB values
        $r = hexdec(substr($hex, 0, 2));
        $g = hexdec(substr($hex, 2, 2));
        $b = hexdec(substr($hex, 4, 2));

        // Adjust
        $r = max(0, min(255, $r + $steps));
        $g = max(0, min(255, $g + $steps));
        $b = max(0, min(255, $b + $steps));

        // Convert back to hex
        $r_hex = str_pad(dechex($r), 2, '0', STR_PAD_LEFT);
        $g_hex = str_pad(dechex($g), 2, '0', STR_PAD_LEFT);
        $b_hex = str_pad(dechex($b), 2, '0', STR_PAD_LEFT);

        return '#' . $r_hex . $g_hex . $b_hex;
    }
}

// Get the current script's filename
$current_file = basename($_SERVER['SCRIPT_FILENAME']);

// Check if the current file is NOT index.php and if the session variable 'user_id' is set
if ($current_file !== 'index.php' && !isset($_SESSION['user_id']) && !isset($is_auth_page)) {
    // Get the current page URL
    $current_page = $_SERVER['REQUEST_URI'];

    // Store the current page URL in a session variable
    $_SESSION['redirect_to'] = $current_page;

    // Redirect to the login page
    header("Location: index.php");
    exit();
}

// Get application name from settings
$appName = defined('APP_NAME') ? APP_NAME : get_setting('app_name', 'HRMS Pro');

?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?php echo $appName . " | " . ucwords(isset($page) ? $page : 'Page Title');?></title>

  <!-- Favicon -->
  <link rel="icon" type="image/x-icon" href="https://primeexpress.com.np/favicon.ico">

  <!-- PWA Meta Tags -->
  <meta name="theme-color" content="<?php echo PRIMARY_COLOR; ?>">
  <meta name="description" content="<?php echo COMPANY_FULL_NAME; ?> - Human Resource Management System">
  <link rel="manifest" href="<?php echo isset($home) ? $home : ''; ?>manifest.json">
  <link rel="apple-touch-icon" href="<?php echo isset($home) ? $home : ''; ?>resources/images/icon-192x192.png">
  <?php if (defined('ENVIRONMENT') && ENVIRONMENT === 'development' && isset($_SESSION['user_id'])): ?>
  <meta name="x-dev-seeder" content="<?php echo (isset($home) ? rtrim($home, '/') : ''); ?>/tools/seed_demo.php">
  <?php endif; ?>
  
  <!-- Meta tags to prevent caching of dynamic content -->
  <meta http-equiv="Cache-Control" content="no-store, no-cache, must-revalidate, max-age=0">
  <meta http-equiv="Pragma" content="no-cache">
  <meta http-equiv="Expires" content="0">
  
  <!-- Auto refresh for dashboard and other data-sensitive pages -->
  <?php if (in_array(basename($_SERVER['PHP_SELF']), ['dashboard.php', 'admin-dashboard.php', 'attendance.php', 'daily-report.php', 'employees.php'])): ?>
  <meta http-equiv="refresh" content="300"> <!-- Refresh every 5 minutes -->
  <?php endif; ?>
  
  <!-- CSRF Token for Ajax Requests -->
  <meta name="csrf-token" content="<?php echo isset($_SESSION['csrf_token']) ? $_SESSION['csrf_token'] : generate_csrf_token(); ?>">
  
  <!-- Favicon -->
  <link rel="icon" type="image/png" href="<?php echo isset($home) ? $home : ''; ?>resources/images/favicon.png">
  
  <!-- Google Fonts -->
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  
  <!-- Bootstrap 5 CSS (Primary CDN) -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <!-- Bootstrap 5 CSS (Fallback CDN) -->
  <link href="https://unpkg.com/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" media="print" onload="this.media='all'">
  
  <!-- Font Awesome 6 -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  
  <!-- DataTables Bootstrap 5 (Primary CDN) -->
  <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
  <!-- DataTables Bootstrap 5 (Fallback CDN via jsDelivr mirror) -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/datatables.net-bs5@1.13.6/css/dataTables.bootstrap5.min.css" media="print" onload="this.media='all'">
  
  <!-- SweetAlert2 CSS -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
  
  <!-- Custom CSS -->
  <style>
    :root {
      --primary-color: <?php echo PRIMARY_COLOR; ?>;
      --primary-hover: <?php echo adjustBrightness(PRIMARY_COLOR, -10); ?>;
      --secondary-color: <?php echo SECONDARY_COLOR; ?>;
      /* Extract RGB values from primary color for rgba usage */
      --primary-rgb: <?php 
        // Convert hex to RGB
        $hex = str_replace('#', '', PRIMARY_COLOR);
        if(strlen($hex) == 3) {
            $r = hexdec(substr($hex, 0, 1).substr($hex, 0, 1));
            $g = hexdec(substr($hex, 1, 1).substr($hex, 1, 1));
            $b = hexdec(substr($hex, 2, 1).substr($hex, 2, 1));
        } else {
            $r = hexdec(substr($hex, 0, 2));
            $g = hexdec(substr($hex, 2, 2));
            $b = hexdec(substr($hex, 4, 2));
        }
        echo "$r, $g, $b";
      ?>;
      --sidebar-width: 260px;
      --sidebar-collapsed-width: 4.5rem;
      --transition-speed: 0.3s;
      --header-height: 60px;
      --footer-height: 60px;
      /* Remove content padding */
      --content-padding: 0;
    }

    body {
      font-family: 'Poppins', sans-serif;
      min-height: 100vh;
      display: flex;
      flex-direction: column;
      color: #212529;
      margin: 0;
      padding: 0;
      background-color: #f8f9fa;
      position: relative;
    }
    
    /* Primary color elements */
    .btn-primary {
      background-color: var(--primary-color) !important;
      border-color: var(--primary-color) !important;
    }
    
    .btn-primary:hover,
    .btn-primary:focus,
    .btn-primary:active {
      background-color: var(--primary-hover) !important;
      border-color: var(--primary-hover) !important;
    }
    
    .btn-outline-primary {
      color: var(--primary-color) !important;
      border-color: var(--primary-color) !important;
    }
    
    .btn-outline-primary:hover,
    .btn-outline-primary:focus,
    .btn-outline-primary:active {
      background-color: var(--primary-color) !important;
      color: #fff !important;
    }
    
    .text-primary {
      color: var(--primary-color) !important;
    }
    
    .bg-primary {
      background-color: var(--primary-color) !important;
    }
    
    /* All hyperlinks and active states */
    a {
      color: var(--primary-color);
      text-decoration: none;
    }
    
    a:hover {
      color: var(--primary-hover);
      text-decoration: none;
    }
    
    a.active, .nav-link.active, .dropdown-item.active {
      background-color: var(--primary-color) !important;
      color: #fff !important;
    }
    
    /* Sidebar active items */
    .sidebar .nav-item.active > .nav-link,
    .sidebar .nav-item > .nav-link.active,
    .sidebar .nav-treeview > .nav-item > .nav-link.active {
      background-color: var(--primary-color) !important;
      color: #fff !important;
    }
    
    /* Table rows and cells with bg-primary */
    table tr.bg-primary, 
    table td.bg-primary, 
    table th.bg-primary {
      background-color: var(--primary-color) !important;
    }
    
    /* Forms and inputs */
    .form-control:focus,
    .form-select:focus {
      border-color: var(--primary-color);
      outline: none;
      box-shadow: 0 0 0 0.25rem rgba(var(--primary-rgb), 0.4);
    }
    
    .form-check-input:checked {
      background-color: var(--primary-color);
      border-color: var(--primary-color);
    }
    
    /* Pagination */
    .page-item.active .page-link {
      background-color: var(--primary-color);
      border-color: var(--primary-color);
    }
    
    .page-link {
      color: var(--primary-color);
    }
    
    .page-link:hover {
      color: var(--primary-hover);
    }
    
    /* Nav elements */
    .nav-pills .nav-link.active, 
    .nav-pills .show > .nav-link {
      background-color: var(--primary-color);
      color: #fff !important;
    }
    
    /* Style for inactive nav tabs to use primary color */
    .nav-pills .nav-link:not(.active) {
      color: var(--primary-color) !important;
    }
    
    /* Style for nav-tabs (used in profile.php and employee-viewer.php) */
    .nav-tabs .nav-link:not(.active) {
      color: var(--primary-color) !important;
    }
    
    .nav-tabs .nav-link.active {
      color: var(--primary-color);
      border-color: var(--primary-color);
    }
    
    /* Style for nav-tabs (used in profile.php and employee-viewer.php) */
    .nav-tabs .nav-link:not(.active) {
      color: var(--primary-color) !important;
    }
    
    .nav-tabs .nav-link.active {
      color: var(--primary-color);
      border-color: var(--primary-color);
    }
    
    /* Progress and loading indicators */
    .progress-bar {
      background-color: var(--primary-color);
    }
    
    /* All badges with bg-primary */
    .badge.bg-primary {
      background-color: var(--primary-color) !important;
    }
    
    /* Alerts with primary color */
    .alert-primary {
      background-color: rgba(var(--primary-rgb), 0.15);
      border-color: rgba(var(--primary-rgb), 0.3);
      color: var(--primary-color);
    }
    
    /* Spinner and loading animation */
    .spinner-border.text-primary,
    .spinner-grow.text-primary {
      color: var(--primary-color) !important;
    }
    
    /* Custom switch elements */
    .custom-switch .custom-control-input:checked ~ .custom-control-label::before,
    .custom-control-input:checked ~ .custom-control-label::before {
      background-color: var(--primary-color);
      border-color: var(--primary-color);
    }
    
    /* List group items */
    .list-group-item.active {
      background-color: var(--primary-color);
      border-color: var(--primary-color);
    }
    
    .text-muted {
      color: #6c757d !important;
    }
    
    /* Layout structure */
    .app-container {
      display: flex;
      flex-direction: column;
      min-height: 100vh;
      width: 100%;
    }
    
    .main-wrapper {
      display: flex;
      flex: 1;
      padding-top: var(--header-height);
      min-height: calc(100vh - var(--footer-height));
    }
    
    .content-wrapper {
      flex: 1;
      margin-left: var(--sidebar-width); /* Default margin */
      transition: margin-left var(--transition-speed); /* Transition margin */
      min-height: calc(100vh - var(--header-height) - var(--footer-height));
      position: relative;
      padding: 1.5rem; 
    }
    
    /* Apply expanded margin when body has sidebar-collapse */
    body.sidebar-collapse .content-wrapper {
      margin-left: var(--sidebar-collapsed-width);
    }
    
    /* Header/Navbar */
    .main-header {
      position: fixed;
      top: 0;
      left: 0; /* Start at 0, margin will push it */
      right: 0;
      height: var(--header-height);
      z-index: 1030; 
      background: #fff;
      margin-left: var(--sidebar-width); /* Default margin */
      box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
      transition: margin-left var(--transition-speed); /* Transition margin */
    }
    
    /* Apply expanded margin when body has sidebar-collapse */
    body.sidebar-collapse .main-header {
      margin-left: var(--sidebar-collapsed-width);
    }
    
    /* Footer styling */
    .main-footer {
      position: fixed;
      bottom: 0;
      left: 0; /* Start at 0, margin will push it */
      right: 0;
      background: #fff;
      border-top: 1px solid rgba(0, 0, 0, 0.05);
      padding: 1rem;
      color: #6c757d;
      font-size: 0.875rem;
      text-align: center;
      margin-left: var(--sidebar-width); /* Default margin */
      transition: margin-left var(--transition-speed); /* Transition margin */
      z-index: 1020;
      height: var(--footer-height); /* Ensure footer height is consistent */
      display: flex; /* Use flex for alignment if needed */
      align-items: center;
      justify-content: space-between; /* Example alignment */
    }
    
    /* Apply expanded margin when body has sidebar-collapse */
    body.sidebar-collapse .main-footer {
      margin-left: var(--sidebar-collapsed-width);
    }
    
    /* Improved scrollbar */
    ::-webkit-scrollbar {
      width: 8px;
      height: 8px;
    }
    
    ::-webkit-scrollbar-track {
      background: #f1f1f1;
    }
    
    ::-webkit-scrollbar-thumb {
      background: #c1c1c1;
      border-radius: 4px;
    }
    
    ::-webkit-scrollbar-thumb:hover {
      background: #a8a8a8;
    }
    
    body.dark-mode ::-webkit-scrollbar-track {
      background: #343a40;
    }
    
    body.dark-mode ::-webkit-scrollbar-thumb {
      background: #495057;
    }
    
    body.dark-mode ::-webkit-scrollbar-thumb:hover {
      background: #6c757d;
    }
    
    /* Page Content */
    .content {
      /* Remove padding/margin/border/background from this class if content-wrapper handles it */
      /* padding: 1rem; */
      /* border-radius: 0.375rem; */
      /* background: #fff; */
      /* border: 1px solid rgba(0, 0, 0, 0.125); */
      /* margin-bottom: 1rem; */
      position: relative;
      /* box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075); */
    }
    
    /* Dark mode support */
    body.dark-mode {
      background-color: #212529;
      color: #f8f9fa;
    }
    
    /* Remove dark mode styles for .content if it's no longer styled */
    /* body.dark-mode .content {
      background-color: #343a40;
      border-color: #495057;
    }
    
    body.dark-mode .main-header {
      background-color: #343a40;
      box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.2);
    }
    
    .dark-mode .navbar {
      background-color: #343a40 !important;
      color: #f8f9fa;
    }
    
    .dark-mode .navbar .nav-link,
    .dark-mode .navbar .navbar-brand {
      color: #f8f9fa;
    }
    
    .dark-mode .card {
      background-color: #343a40;
      color: #f8f9fa;
      border-color: #495057;
    }
    
    .dark-mode .card-header {
      background-color: #3c4347;
      border-color: #495057;
    }
    
    .dark-mode .table {
      color: #f8f9fa;
      border-color: #495057;
    }
    
    .dark-mode .table th,
    .dark-mode .table td {
      border-color: #495057;
    }
    
    .dark-mode .form-control,
    .dark-mode .form-select {
      background-color: #343a40;
      color: #f8f9fa;
      border-color: #495057;
    }
    
    .dark-mode .modal-content {
      background-color: #343a40;
      color: #f8f9fa;
    }
    
    .dark-mode .list-group-item {
      background-color: #343a40;
      color: #f8f9fa;
      border-color: #495057;
    }
    
    /* Improved Text */
    
    body.dark-mode .main-header {
      background-color: #343a40;
      box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.2);
    }
    
    .dark-mode .navbar {
      background-color: #343a40 !important;
      color: #f8f9fa;
    }
    
    .dark-mode .navbar .nav-link,
    .dark-mode .navbar .navbar-brand {
      color: #f8f9fa;
    }
    
    .dark-mode .card {
      background-color: #343a40;
      color: #f8f9fa;
      border-color: #495057;
    }
    
    .dark-mode .card-header {
      background-color: #3c4347;
      border-color: #495057;
    }
    
    .dark-mode .table {
      color: #f8f9fa;
      border-color: #495057;
    }
    
    .dark-mode .table th,
    .dark-mode .table td {
      border-color: #495057;
    }
    
    .dark-mode .form-control,
    .dark-mode .form-select {
      background-color: #343a40;
      color: #f8f9fa;
      border-color: #495057;
    }
    
    .dark-mode .modal-content {
      background-color: #343a40;
      color: #f8f9fa;
    }
    
    .dark-mode .list-group-item {
      background-color: #343a40;
      color: #f8f9fa;
      border-color: #495057;
    }
    
    /* Improved Text for Both Modes */
    body {
      color: #212529;
    }
    
    .text-muted {
      color: #6c757d !important;
    }
    
    /* Dark mode text adjustments */
    body.dark-mode .text-dark {
      color: #f8f9fa !important;
    }
    
    body.dark-mode .text-muted {
      color: #adb5bd !important;
    }
    
    body.dark-mode .text-secondary {
      color: #cbd3da !important;
    }
    
    body.dark-mode .text-body {
      color: #e9ecef !important;
    }
    
    /* Link colors for both modes */
    a {
      color: #0d6efd;
      text-decoration: none;
    }
    
    a:hover {
      color: #0a58ca;
      text-decoration: underline;
    }
    
    body.dark-mode a:not(.btn):not(.nav-link):not(.dropdown-item) {
      color: #6ea8fe;
    }
    
    body.dark-mode a:not(.btn):not(.nav-link):not(.dropdown-item):hover {
      color: #9ec5fe;
    }
    
    /* Badge and alert text readability */
    body.dark-mode .badge.bg-light,
    body.dark-mode .alert-light {
      color: #212529;
    }
    
    /* Input placeholder text */
    body.dark-mode .form-control::placeholder,
    body.dark-mode .form-select::placeholder {
      color: #adb5bd;
      opacity: 0.7;
    }
    
    /* Dropdown text */
    body.dark-mode .dropdown-menu {
      background-color: #343a40;
      border-color: #495057;
    }
    
    body.dark-mode .dropdown-item {
      color: #f8f9fa;
    }
    
    body.dark-mode .dropdown-item:hover {
      background-color: #495057;
    }
    
    body.dark-mode .dropdown-header {
      color: #adb5bd;
    }

    /* Pagination Dark Mode */
    body.dark-mode .pagination .page-link {
      background-color: #343a40;
      border-color: #495057;
      color: #dee2e6; /* Lighter text for dark background */
    }

    body.dark-mode .pagination .page-link:hover {
      background-color: #495057;
      color: #fff;
    }

    body.dark-mode .pagination .page-item.active .page-link {
      background-color: var(--primary-color); /* Use primary color variable instead of fixed blue */
      border-color: var(--primary-color);
      color: #fff;
    }

    body.dark-mode .pagination .page-item.disabled .page-link {
      background-color: #343a40;
      border-color: #495057;
      color: #6c757d; /* Muted color for disabled */
    }
    
    /* DataTables pagination icons in dark mode */
    body.dark-mode .dataTables_wrapper .pagination .page-link {
      color: #dee2e6;
    }
    
    body.dark-mode .dataTables_wrapper .pagination .page-item.active .page-link {
      background-color: var(--primary-color);
      border-color: var(--primary-color);
      color: #fff;
    }
    
    /* Breadcrumb styling */
    .breadcrumb-container {
      margin-bottom: 1rem;
      padding: 0.75rem 1rem;
      background-color: #fff;
      border-radius: 0.375rem;
      box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
      border: 1px solid rgba(0, 0, 0, 0.125);
    }
    
    body.dark-mode .breadcrumb-container {
      background-color: #343a40;
      border-color: #495057;
    }
    
    .breadcrumb {
      margin-bottom: 0;
    }
    
    /* DateRangePicker Dark Mode */
    body.dark-mode .daterangepicker {
      background-color: #343a40; /* Dark background */
      border-color: #495057;
      color: #f8f9fa; /* Light text */
    }
    body.dark-mode .daterangepicker .calendar-table th,
    body.dark-mode .daterangepicker .calendar-table td {
      border-color: #495057;
      color: #f8f9fa;
    }
    body.dark-mode .daterangepicker .calendar-table .month {
      color: #f8f9fa;
    }
    body.dark-mode .daterangepicker .calendar-table .off,
    body.dark-mode .daterangepicker .calendar-table .off.in-range,
    body.dark-mode .daterangepicker .calendar-table .off.start-date,
    body.dark-mode .daterangepicker .calendar-table .off.end-date {
      background-color: #343a40;
      border-color: #343a40;
      color: #6c757d; /* Muted color for disabled dates */
    }
    body.dark-mode .daterangepicker .calendar-table td.available:hover,
    body.dark-mode .daterangepicker .ranges li:hover {
      background-color: #495057; /* Darker hover */
      border-color: #495057;
      color: #fff;
    }
    body.dark-mode .daterangepicker .calendar-table td.active,
    body.dark-mode .daterangepicker .calendar-table td.active:hover,
    body.dark-mode .daterangepicker .ranges li.active {
      background-color: #0d6efd; /* Primary color for active/selected */
      border-color: #0d6efd;
      color: #fff;
    }
    body.dark-mode .daterangepicker .drp-buttons .btn {
      background-color: #495057;
      border-color: #6c757d;
      color: #f8f9fa;
    }
    body.dark-mode .daterangepicker .drp-buttons .btn-primary {
      background-color: #0d6efd;
      border-color: #0d6efd;
      color: #fff;
    }
    body.dark-mode .daterangepicker .drp-buttons .btn-primary:hover {
      background-color: #0b5ed7;
      border-color: #0a58ca;
    }
    body.dark-mode .daterangepicker .drp-calendar .month select {
        background-color: #495057;
        color: #f8f9fa;
        border: 1px solid #6c757d;
    }
    body.dark-mode .daterangepicker th.month {
        color: #f8f9fa;
    }

    /* Page Content */
    .page-title {
      margin-bottom: 1.5rem;
      font-weight: 600;
      color: #212529;
    }
    
    body.dark-mode .page-title {
      color: #f8f9fa;
    }
    
    /* Enhancements for mobile */
    @media (max-width: 768px) {
      /* :root { */
        /* Adjust content padding for mobile if needed */
        /* --content-padding: 1rem; */
      /* } */
      
      .content-wrapper {
        margin-top: 0 !important;
        margin-left: 0 !important;
        width: 100% !important;
        /* Apply padding directly here for mobile */
        padding: 0rem; 
      }

      .container-fluid {
        padding: 5px !important;
      }

      .main-wrapper{
        padding-top: 0 !important;
        margin-left: 0 !important;
        width: 100% !important;
      }
      
      .main-header {
        left: 0 !important;
        width: 100% !important;
      }
      
      .main-footer {
        margin-left: 0 !important;
        width: 100% !important;
        padding: 0 !important;
      }
      
      .sidebar {
        position: fixed;
        left: calc(-1 * var(--sidebar-width));
        top: var(--header-height);
        height: calc(100vh - var(--header-height));
        z-index: 1035;
        transition: left var(--transition-speed);
        box-shadow: none;
      }
      
      .sidebar.show {
        left: 0;
        box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
      }
      
      /* Ensure margins are zero on mobile regardless of collapse state */
      .content-wrapper,
      body.sidebar-collapse,
      .main-header,
      body.sidebar-collapse .main-header,
      .main-footer,
      body.sidebar-collapse .main-footer {
        margin-left: 0 !important;
        width: 100% !important;
      }
    }
    
    /* Responsive table improvements */
    @media (max-width: 768px) {
      .table-responsive {
        margin-bottom: 1rem;
      }
    }
    
    /* Form styling improvements */
    .form-control:focus, 
    .form-select:focus {
      outline: none;
      box-shadow: 0 0 0 0.25rem rgba(var(--primary-rgb), 0.4);
      border-color: var(--primary-color);
    }
    
    /* Card hover effects */
    .card-hover {
      transition: transform 0.3s ease, box-shadow 0.3s ease;
    }
    
    .card-hover:hover {
      transform: translateY(-5px);
      box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
    }

    /* Loading overlay */
    .loading-overlay {
      position: fixed;
      top: 0;
      left: 0;
      right: 0;
      bottom: 0;
      background-color: rgba(255, 255, 255, 0.7);
      display: flex;
      justify-content: center;
      align-items: center;
      z-index: 9999;
      transition: opacity 0.3s;
    }
    
    body.dark-mode .loading-overlay {
      background-color: rgba(33, 37, 41, 0.7);
    }
    
    .spinner-border {
      width: 3rem;
      height: 3rem;
    }
    
    /* Animation for sidebar toggle */
    @keyframes sidebar-toggle {
      0% { transform: rotate(0deg); }
      100% { transform: rotate(90deg); }
    }

    /* Remove the undreline form a tag */
    a:hover {
      text-decoration: none;
    }

    /* Celebration card chevron animation */
    #celebrationsChevron {
      transition: transform 0.3s ease;
    }

    /* Collapsible card button styling */
    .btn-link:focus {
      box-shadow: none;
    }

    .btn-link:hover {
      color: var(--primary-color) !important;
    }
  </style>
  <?php include __DIR__ . '/pwa_install.php'; ?>
  <script>
    // Fallback: if Bootstrap classes aren't applied (CDN blocked), inject local CSS fallbacks
    (function(){
      try {
        var test = document.createElement('div');
        test.className = 'd-none';
        document.head.appendChild(test);
        var computed = window.getComputedStyle(test).display;
        document.head.removeChild(test);
        if (computed !== 'none') {
          // Inject AdminLTE base CSS as fallback
          var link1 = document.createElement('link');
          link1.rel = 'stylesheet';
          link1.href = '<?php echo isset($home)?$home:""; ?>dist/css/adminlte.min.css';
          document.head.appendChild(link1);
          // Inject DataTables local CSS fallback (Bootstrap4 skin)
          var link2 = document.createElement('link');
          link2.rel = 'stylesheet';
          link2.href = '<?php echo isset($home)?$home:""; ?>plugins/datatables-bs4/css/dataTables.bootstrap4.min.css';
          document.head.appendChild(link2);
          // Inject FontAwesome local fallback if available
          var link3 = document.createElement('link');
          link3.rel = 'stylesheet';
          link3.href = '<?php echo isset($home)?$home:""; ?>plugins/fontawesome-free/css/all.min.css';
          document.head.appendChild(link3);
        }
      } catch (e) { /* ignore */ }
    })();
  </script>
</head>
<body class="<?php echo isset($_COOKIE['dark-mode']) && $_COOKIE['dark-mode'] === 'true' ? 'dark-mode' : ''; ?>">
  <!-- Loading Overlay -->
  <div class="loading-overlay" id="loadingOverlay" style="display: none;">
    <div class="spinner-border text-primary" role="status">
      <span class="visually-hidden">Loading...</span>
    </div>
  </div>
  
  <!-- Add immediate script to handle loading -->
  <script>
  // Immediately set the loading overlay to display for a brief moment
  document.addEventListener('DOMContentLoaded', function() {
    document.getElementById('loadingOverlay').style.display = 'none';
  });
  
  // Global configuration for API endpoints
  window.HRMS_CONFIG = {
    BASE_URL: <?php echo json_encode(rtrim(isset($home) ? $home : '', '/')); ?>,
    API_URL: <?php echo json_encode(rtrim(isset($home) ? $home : '', '/') . '/api'); ?>
  };
  </script>

  <!-- App Container Wrapper -->
  <div class="app-container">
    <!-- Include the topbar as the header -->
    <?php 
      $home = isset($home) ? $home : './';
      // Correct the path for including topbar.php
      include __DIR__ . '/topbar.php'; 
    ?>
    
    <!-- Main Wrapper -->
    <div class="main-wrapper">
      <?php 
        // Correctly include sidebar using a relative path from the current file's directory
        include __DIR__ . '/sidebar.php'; 
      ?>
      
      <!-- Content Wrapper -->
      <div class="content-wrapper" id="content-wrapper">
        <!-- Global Modals -->
        <?php 
          // Include global Apply Leave modal so it's accessible from anywhere
          include __DIR__ . '/leave-request-modal.php';
        ?>
        <!-- Page Content - Starts Here -->