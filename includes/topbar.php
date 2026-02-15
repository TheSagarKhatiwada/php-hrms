<?php
$__hasPermissionFunc = function_exists('has_permission');
$__isAdminUser = function_exists('is_admin') && is_admin();

$__canAccessContacts = $__isAdminUser;
if (!$__canAccessContacts && $__hasPermissionFunc) {
  if (has_permission('topbar_contacts_shortcut') || has_permission('manage_contacts')) {
    $__canAccessContacts = true;
  }
}

$__canUseGlobalSearch = $__isAdminUser ? true : ($__hasPermissionFunc ? has_permission('topbar_global_search') : true);
$__canViewNotifications = $__isAdminUser ? true : ($__hasPermissionFunc ? has_permission('topbar_notifications_panel') : true);
?>
<!-- Main Header -->
<header class="main-header">
  <!-- Modernized Navbar with Bootstrap 5 -->
  <nav class="navbar navbar-expand-lg shadow-sm">
    <div class="container-fluid">
      <!-- Mobile Sidebar Toggle -->
      <button id="mobile-sidebar-toggle" class="btn btn-icon d-md-none me-2" aria-label="Toggle sidebar">
        <i class="fas fa-bars"></i>
      </button>

      <!-- Contacts Icon -->
      <?php if ($__canAccessContacts): ?>
      <a href="<?php echo isset($home) ? $home : './'; ?>contacts.php" class="navbar-brand d-none d-sm-inline-block me-2" title="Contacts">
        <i class="fas fa-address-book text-primary"></i>
      </a>
      <?php endif; ?>

      <?php if ($__canUseGlobalSearch): ?>
      <!-- Search Form - Only visible on medium screens and larger -->
      <div class="d-none d-md-flex me-auto position-relative">
        <form action="<?php echo isset($home) ? $home : './'; ?>search-results.php" method="GET" class="search-form flex-grow-1">
          <div class="input-group input-group-navbar">
            <input class="form-control" type="search" name="query" placeholder="Search..." aria-label="Search">
            <button class="btn btn-primary" type="submit">
              <i class="fas fa-search"></i>
            </button>
          </div>
        </form>
      </div>
      <?php endif; ?>

      <!-- Mobile brand centered -->
      <div class="d-sm-none mx-auto">
        <a href="<?php echo isset($home) ? $home : './'; ?>" class="navbar-brand">
          <span class="fw-bold text-primary"><?php echo APP_NAME; ?></span>
        </a>
      </div>

      <!-- Mobile Search & Menu Toggle -->
      <div class="d-flex d-md-none align-items-center">
        <?php if ($__canUseGlobalSearch): ?>
        <!-- Search button for mobile -->
        <div class="nav-item">
          <a class="nav-link px-2" data-bs-toggle="collapse" href="#mobileSearch" role="button" aria-expanded="false" aria-controls="mobileSearch">
            <i class="fas fa-search"></i>
          </a>
        </div>
        <?php endif; ?>
        
        
        <?php if ($__canViewNotifications): ?>
        <!-- Mobile Notifications Dropdown -->
        <div class="nav-item dropdown ms-2">
          <a class="nav-link p-0 position-relative" href="#" id="mobileNotificationsDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
            <i class="fas fa-bell"></i>
            <span id="mobileNotificationBadge" class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger d-none" style="font-size: 0.5rem; padding: 0.2rem 0.4rem;">
              0
              <span class="visually-hidden">unread notifications</span>
            </span>
          </a>
          <div class="dropdown-menu dropdown-menu-end py-0 shadow-sm mobile-notifications-dropdown" aria-labelledby="mobileNotificationsDropdown">
            <div class="dropdown-header d-flex justify-content-between align-items-center p-3 border-bottom">
              <h6 class="m-0 fw-semibold">Notifications</h6>
              <a href="#" id="mobileMarkAllRead" class="text-muted small">Mark all as read</a>
            </div>
            <div class="notification-dropdown-body">
              <div id="mobileNotificationList" class="notification-list" style="max-height: 300px; overflow-y: auto;">
                <!-- Notifications will be loaded here dynamically -->
                <div class="d-flex justify-content-center p-3">
                  <div class="spinner-border spinner-border-sm text-primary" role="status">
                    <span class="visually-hidden">Loading...</span>
                  </div>
                </div>
              </div>
            </div>
            <div class="dropdown-footer text-center p-2 border-top">
              <a href="notifications.php" class="text-muted small">View all notifications</a>
            </div>
          </div>
        </div>
        <?php endif; ?>
        
        <!-- Mobile User Menu Toggle -->
        <div class="nav-item dropdown ms-2">
          <?php
          // Ensure database connection is available before querying
          global $pdo;
          if (!isset($pdo) || !$pdo instanceof PDO) {
            include_once __DIR__ . '/../includes/db_connection.php';
          }
          
          // Fetch user details from the database if not already available
          if (!isset($user) && isset($_SESSION['user_id']) && $pdo instanceof PDO) {
            $user_id = $_SESSION['user_id'];
            $stmt = $pdo->prepare("SELECT * FROM employees WHERE emp_id = :emp_id");
            $stmt->execute(['emp_id' => $user_id]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            $stmt->closeCursor();
          }

          if (!isset($user) || !is_array($user)) {
            $user = [];
          }
          
          // Default home directory if not set
          $homeDir = isset($home) ? $home : './';
          
          // Company logo from settings
          $companyLogo = defined('COMPANY_LOGO') ? COMPANY_LOGO : 'company_logo.png';

          $userImagePath = (!empty($user['user_image']))
            ? $user['user_image']
            : $homeDir . 'resources/userimg/default-image.jpg';
          $userFullName = trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''));
          if ($userFullName === '') {
            $userFullName = 'Team Member';
          }
          $userDesignation = !empty($user['designation']) ? $user['designation'] : 'Not Assigned';
          ?>
          <a class="nav-link p-0" href="#" id="mobileMenuToggle" role="button" data-bs-toggle="dropdown" aria-expanded="false">
            <img src="<?php echo htmlspecialchars($userImagePath); ?>" 
                alt="User" class="rounded-circle border" width="32" height="32" style="object-fit: cover;">
          </a>
          
          <div class="dropdown-menu dropdown-menu-end py-0" aria-labelledby="mobileMenuToggle">
            <!-- Mobile Menu Items -->
            <div class="mobile-menu-header">
              <div class="d-flex align-items-center p-2 border-bottom">
                <img src="<?php echo htmlspecialchars($userImagePath); ?>" 
                    alt="User" class="rounded-circle me-2 border" width="40" height="40" style="object-fit: cover;">
                <div>
                  <h6 class="mb-0"><?php echo htmlspecialchars($userFullName); ?></h6>
                  <small class="text-muted"><?php echo htmlspecialchars($userDesignation); ?></small>
                </div>
              </div>
            </div>
            
            <!-- Theme Toggle in mobile dropdown -->
            <a href="#" class="dropdown-item d-flex align-items-center py-2" id="mobileDarkModeToggle">
              <i class="fas fa-moon me-2 mobile-dark-icon"></i>
              <i class="fas fa-sun me-2 mobile-light-icon d-none"></i>
              <span class="mobile-theme-text">Dark Mode</span>
            </a>
            
            <div class="dropdown-divider m-0"></div>
            
            <!-- User Profile Link -->
            <a class="dropdown-item py-2" href="<?php echo $homeDir; ?>profile.php">
              <i class="fas fa-user me-2 text-primary"></i>My Profile
            </a>
            
            <?php if (isset($user['role']) && $user['role'] == 1): // Admin Only ?>
            <a class="dropdown-item py-2" href="<?php echo $homeDir; ?>system-settings.php">
              <i class="fas fa-cog me-2 text-primary"></i>Settings
            </a>
            <?php endif; ?>
            
            <div class="dropdown-divider m-0"></div>
            
            <!-- Sign Out -->
            <a class="dropdown-item py-2" href="<?php echo $homeDir; ?>signout.php">
              <i class="fas fa-sign-out-alt me-2 text-danger"></i>Sign Out
            </a>
          </div>
        </div>
      </div>
      <?php if ($__canUseGlobalSearch): ?>
      <!-- Mobile Search - Collapsible -->
      <div class="collapse w-100 mt-2 mb-2 d-md-none" id="mobileSearch">
        <form action="<?php echo isset($home) ? $home : './'; ?>search-results.php" method="GET">
          <div class="input-group">
            <input class="form-control" type="search" name="query" placeholder="Search employees, assets, etc..." aria-label="Search">
            <button class="btn btn-primary" type="submit">
              <i class="fas fa-search"></i>
            </button>
          </div>
        </form>
      </div>
      <?php endif; ?>

      <!-- Right navbar items - Only visible on medium screens and larger -->
      <ul class="navbar-nav ms-auto align-items-center mobile-nav-icons d-none d-md-flex">
        <?php
          // Ensure DB and settings available
          if (!isset($pdo)) {
            include_once __DIR__ . '/../includes/db_connection.php';
          }
          if (!function_exists('get_setting')) {
            include_once __DIR__ . '/../includes/settings.php';
          }
          // Determine today's attendance state for logged-in user
          $isClockedInTop = false;
          $empIdTop = null;
          $attendanceUrl = (isset($home) ? $home : './') . 'modules/attendance/record_attendance.php';
          $canUseWebAttendanceTop = false;
          try {
            if (isset($_SESSION['user_id'])) {
              $empIdTop = $_SESSION['user_id'];
              $tzTop = get_setting('timezone', 'UTC');
              date_default_timezone_set($tzTop);
              $todayTop = date('Y-m-d');
              $stmtTop = $pdo->prepare("SELECT COUNT(*) FROM attendance_logs WHERE emp_id = ? AND date = ?");
              $stmtTop->execute([$empIdTop, $todayTop]);
              $isClockedInTop = ((int)$stmtTop->fetchColumn()) > 0;

              if (isset($user) && array_key_exists('allow_web_attendance', $user)) {
                $canUseWebAttendanceTop = ((int)$user['allow_web_attendance'] === 1);
              } else {
                $stmtAllow = $pdo->prepare("SELECT allow_web_attendance FROM employees WHERE emp_id = ? LIMIT 1");
                $stmtAllow->execute([$empIdTop]);
                $canUseWebAttendanceTop = ((int)$stmtAllow->fetchColumn() === 1);
              }

              // Geofence gating for topbar button
              if ($canUseWebAttendanceTop) {
                require_once __DIR__ . '/../includes/utilities.php';
                $geofenceTop = hrms_get_branch_geofence_for_employee($pdo, $empIdTop);
                if (!empty($geofenceTop) && (int)($geofenceTop['geofence_enabled'] ?? 0) === 1) {
                  $metaLoc = $_SESSION['meta']['last_location'] ?? null;
                  $lat = $metaLoc['lat'] ?? null;
                  $lon = $metaLoc['lon'] ?? null;
                  if ($lat === null || $lon === null) {
                    try {
                      $locStmt = $pdo->prepare("SELECT latitude, longitude FROM location_logs
                                                WHERE employee_id = :emp AND session_id = :sid
                                                ORDER BY created_at DESC LIMIT 1");
                      $locStmt->execute([
                        ':emp' => $empIdTop,
                        ':sid' => session_id()
                      ]);
                      if ($row = $locStmt->fetch(PDO::FETCH_ASSOC)) {
                        $lat = $row['latitude'];
                        $lon = $row['longitude'];
                      }
                    } catch (Throwable $e) {
                      // ignore
                    }
                  }
                  if ($lat === null || $lon === null) {
                    $canUseWebAttendanceTop = false;
                  } else {
                    $canUseWebAttendanceTop = hrms_is_within_geofence($lat, $lon, $geofenceTop);
                  }
                }
              }
            }
          } catch (Throwable $e) { /* ignore */ }
        ?>
        <!-- Topbar Clock In/Out Button -->
        <?php
          $attendanceBtnTitle = $isClockedInTop ? 'Clock Out' : 'Clock In';
        ?>
        <?php if ($canUseWebAttendanceTop): ?>
        <li class="nav-item me-1">
          <button
            id="topbarAttendanceBtn"
            class="nav-link btn-icon btn-icon-auto rounded-pill px-3"
            title="<?php echo $attendanceBtnTitle; ?>"
            aria-label="<?php echo $attendanceBtnTitle; ?>"
            data-action="<?php echo $isClockedInTop ? 'CO' : 'CI'; ?>"
            data-emp-id="<?php echo htmlspecialchars((string)$empIdTop); ?>"
            data-url="<?php echo htmlspecialchars($attendanceUrl); ?>">
            <i class="fas <?php echo $isClockedInTop ? 'fa-sign-out-alt text-primary' : 'fa-sign-in-alt text-success'; ?>"></i>
            <span class="d-none d-lg-inline ms-2 <?php echo $isClockedInTop ? 'text-primary' : 'text-success'; ?>"><?php echo $attendanceBtnTitle; ?></span>
          </button>
        </li>
        <?php endif; ?>

        
        
        <?php $dateMode = function_exists('hrms_get_date_display_mode') ? hrms_get_date_display_mode() : 'ad'; ?>
        <li class="nav-item me-1 date-mode-toggle-item">
          <button
            type="button"
            class="btn btn-sm date-mode-chip"
            id="dateModeToggleBtn"
            data-current="<?php echo $dateMode; ?>"
            data-endpoint="<?php echo isset($home) ? $home : './'; ?>api/set-date-mode.php"
            title="Toggle between AD and BS dates">
            <span class="chip-text fw-semibold"><?php echo strtoupper($dateMode); ?></span>
          </button>
        </li>

        <!-- Theme Toggle - Enhanced for better visibility and accessibility -->
        <li class="nav-item me-1">
          <button class="nav-link btn-icon theme-toggle px-2" id="darkModeToggle" aria-label="Toggle dark mode">
            <div class="theme-toggle-wrapper position-relative">
              <i class="fas fa-moon dark-icon"></i>
              <i class="fas fa-sun light-icon d-none"></i>
              <span class="visually-hidden theme-status">Dark mode is disabled</span>
            </div>
          </button>
        </li>
        
        <?php if ($__canViewNotifications): ?>
        <!-- Notifications Dropdown -->
        <li class="nav-item dropdown me-1">
          <a class="nav-link position-relative px-2 no-caret" href="#" id="notificationDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
            <i class="fas fa-bell"></i>
            <span id="notificationBadge" class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger d-none">
              0
              <span class="visually-hidden">unread notifications</span>
            </span>
          </a>
          <div class="dropdown-menu dropdown-menu-end dropdown-menu-lg py-0 shadow-sm" aria-labelledby="notificationDropdown">
            <div class="dropdown-header d-flex justify-content-between align-items-center p-3 border-bottom">
              <h6 class="m-0 fw-semibold">Notifications</h6>
              <a href="#" id="markAllRead" class="text-muted small">Mark all as read</a>
            </div>
            <div class="notification-dropdown-body">
              <div id="notificationList" class="notification-list" style="max-height: 350px; overflow-y: auto;">
                <!-- Notifications will be loaded here dynamically -->
                <div class="d-flex justify-content-center p-3">
                  <div class="spinner-border spinner-border-sm text-primary" role="status">
                    <span class="visually-hidden">Loading...</span>
                  </div>
                </div>
              </div>
            </div>
            <div class="dropdown-footer text-center p-2 border-top">
              <a href="notifications.php" class="text-muted small">View all notifications</a>
            </div>
          </div>
        </li>
        <?php endif; ?>
        
        <!-- Fullscreen Toggle -->
        <li class="nav-item me-1">
          <button id="fullscreen-btn" class="nav-link btn-icon px-2" aria-label="Toggle fullscreen">
            <i class="fas fa-expand-arrows-alt"></i>
          </button>
        </li>
      </ul>
    </div>
  </nav>

</header>

<style>
  /* Core Navbar Styles */
  .navbar {
    padding: 0.5rem 1rem;
    background-color: #ffffff;
    border-bottom: 1px solid rgba(0, 0, 0, 0.05);
  }

  .date-mode-toggle-item .date-mode-chip {
    background: transparent;
    border-radius: 999px;
    color: var(--primary-color);
    padding: 6px 10px;
    line-height: 1;
    box-shadow: none;
    transition: transform 0.1s ease, box-shadow 0.2s ease, background 0.2s ease;
  }

  .date-mode-toggle-item .date-mode-chip:hover {
    border: 1px solid var(--primary-color);
    background: rgba(13,110,253,0.06);
    box-shadow: 0 6px 14px rgba(0,0,0,0.08);
    transform: translateY(-1px);
  }

  .date-mode-toggle-item .date-mode-chip:focus-visible {
    outline: 2px solid var(--primary-color);
    outline-offset: 2px;
  }

  .date-mode-toggle-item .chip-text {
    letter-spacing: 0.4px;
    font-size: 0.9rem;
  }

  /* Reduced margins for mobile */
  @media (max-width: 767.98px) {
    .navbar {
      padding: 0.3rem 0.5rem;
    }
    
    .container-fluid {
      padding-left: 0.5rem;
      padding-right: 0.5rem;
    }
  }

  /* Dark Mode Navbar */
  body.dark-mode .navbar {
    background-color: #2c3136;
    border-color: rgba(255, 255, 255, 0.05);
  }

  body.dark-mode .list-group-item {
    background-color: #343a40;
    border-color: rgba(255, 255, 255, 0.05);
  }

  body.dark-mode .dropdown-menu {
    background-color: #343a40;
    border-color: rgba(255, 255, 255, 0.05);
  }

  body.dark-mode .dropdown-item {
    color: rgba(255, 255, 255, 0.85);
  }

  body.dark-mode .dropdown-item:hover {
    background-color: #2c3136;
    color: #ffffff;
  }

  /* Breadcrumb styling */
  .breadcrumb {
    background-color: transparent;
    margin-bottom: 0;
    padding: 0.5rem 0;
  }

  .breadcrumb-item a {
    color: #0d6efd;
    text-decoration: none;
  }

  .breadcrumb-item.active {
    color: #6c757d;
  }

  /* Button Icons */
  .btn-icon {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 38px;
    height: 38px;
    padding: 0;
    border-radius: 0.25rem;
    color: #6c757d;
    background-color: transparent;
    border: 1px solid transparent;
    transition: all 0.2s ease;
  }

  .btn-icon:hover {
    background-color: rgba(108, 117, 125, 0.1);
    color: #495057;
  }

  /* Allow icon buttons that need text to auto-size and keep text on one line */
  .btn-icon-auto {
    width: auto;
    min-width: 38px; /* keep at least the icon size */
    height: 38px;
    padding-left: 0.75rem;
    padding-right: 0.75rem;
    white-space: nowrap;
  }

  body.dark-mode .btn-icon {
    color: rgba(255, 255, 255, 0.7);
  }

  body.dark-mode .btn-icon:hover {
    background-color: rgba(255, 255, 255, 0.1);
    color: #ffffff;
  }

  /* Search Form */
  .input-group-navbar {
    width: 280px;
  }

  .input-group-navbar .form-control {
    border-top-right-radius: 0;
    border-bottom-right-radius: 0;
    border-right: 0;
    padding: 0.375rem 0.75rem;
    height: 38px;
  }

  .input-group-navbar .btn {
    border-top-left-radius: 0;
    border-bottom-left-radius: 0;
    padding: 0.375rem 0.75rem;
    height: 38px;
  }

  /* Navbar Links */
  .navbar .nav-link {
    color: #6c757d;
    padding: 0.5rem;
    height: 38px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 0.25rem;
    position: relative;
    transition: all 0.2s ease;
  }

  .navbar .nav-link:hover {
    color: #495057;
    background-color: rgba(108, 117, 125, 0.1);
  }

  body.dark-mode .navbar .nav-link {
    color: rgba(255, 255, 255, 0.7);
  }

  body.dark-mode .navbar .nav-link:hover {
    color: #ffffff;
    background-color: rgba(255, 255, 255, 0.1);
  }

  /* Notification Dropdown */
  .dropdown-menu-lg {
    min-width: 320px;
  }

  .dropdown-menu-header {
    padding: 0.75rem 1rem;
    border-bottom: 1px solid rgba(0, 0, 0, 0.05);
    display: flex;
    align-items: center;
    justify-content: space-between;
    background-color: #f8f9fa;
    transition: background-color 0.3s ease, color 0.3s ease, border-color 0.3s ease;
  }

  body.dark-mode .dropdown-menu-header {
    background-color: #2c3136;
    border-color: rgba(255, 255, 255, 0.05);
  }

  .dropdown-notifications-all {
    font-size: 0.8rem;
    text-decoration: none;
  }

  .dropdown-menu-footer {
    padding: 0.75rem 1rem;
    border-top: 1px solid rgba(0, 0, 0, 0.05);
    text-align: center;
    background-color: #f8f9fa;
    transition: background-color 0.3s ease, color 0.3s ease, border-color 0.3s ease;
  }

  body.dark-mode .dropdown-menu-footer {
    background-color: #2c3136;
    border-color: rgba(255, 255, 255, 0.05);
  }

  .dropdown-menu-footer a {
    font-size: 0.8rem;
    text-decoration: none;
    color: #6c757d;
    transition: color 0.3s ease;
  }

  body.dark-mode .dropdown-menu-footer a {
    color: rgba(255, 255, 255, 0.7);
  }

  body.dark-mode .dropdown-menu-footer a:hover {
    color: #ffffff;
  }

  .list-group-item {
    border-left: 0;
    border-right: 0;
    padding: 0.75rem 1rem;
  }

  .list-group-item:first-child {
    border-top-left-radius: 0;
    border-top-right-radius: 0;
  }

  .list-group-item:last-child {
    border-bottom-left-radius: 0;
    border-bottom-right-radius: 0;
  }

  /* Notification Panel Specific Styling */
  .notification-list {
    max-height: 350px;
    overflow-y: auto;
    scrollbar-width: thin;
  }

  .notification-item {
    transition: background-color 0.2s ease, transform 0.2s ease;
    border-left: 3px solid transparent;
    padding: 0.75rem 1rem;
    cursor: pointer;
    background-color: #ffffff;
  }

  /* Make notification cards more compact on mobile */
  @media (max-width: 767.98px) {
    .notification-item {
      padding: 0.5rem 0.75rem;
    }
    
    .notification-item h6 {
      font-size: 0.875rem;
      margin-bottom: 0.2rem;
    }
    
    .notification-item p {
      font-size: 0.8125rem;
      margin-bottom: 0.2rem;
    }
    
    .notification-item small {
      font-size: 0.75rem;
    }
    
    .notification-actions .btn-outline-secondary,
    .notification-actions .btn-outline-primary {
      padding: 0.1rem 0.4rem;
      font-size: 0.7rem;
    }
    
    .mobile-notifications-dropdown .dropdown-header {
      padding: 0.5rem 0.75rem;
    }
    
    .mobile-notifications-dropdown .dropdown-footer {
      padding: 0.35rem 0.5rem;
    }
  }

  .notification-item:hover {
    background-color: rgba(13, 110, 253, 0.05);
    transform: translateY(-1px);
    border-left-color: rgba(13, 110, 253, 0.5);
  }

  body.dark-mode .notification-item {
    background-color: #343a40;
    border-color: rgba(255, 255, 255, 0.05);
  }

  body.dark-mode .notification-item:hover {
    background-color: #3a4148;
    border-left-color: rgba(110, 168, 254, 0.5);
  }

  /* Notification type indicators */
  .notification-item .fas.fa-check-circle {
    color: #198754;
  }

  .notification-item .fas.fa-info-circle {
    color: #0d6efd;  
  }

  .notification-item .fas.fa-exclamation-triangle {
    color: #ffc107;
  }

  .notification-item .fas.fa-times-circle {
    color: #dc3545;
  }

  body.dark-mode .notification-item .fas.fa-check-circle {
    color: #2ea868;
  }

  body.dark-mode .notification-item .fas.fa-info-circle {
    color: #6ea8fe;
  }

  body.dark-mode .notification-item .fas.fa-exclamation-triangle {
    color: #ffcd39;
  }

  body.dark-mode .notification-item .fas.fa-times-circle {
    color: #e35d6a;
  }

  /* Notification text styling */
  .notification-item h6 {
    font-weight: 600;
    margin-bottom: 0.3rem;
    color: #212529;
    transition: color 0.3s ease;
  }

  .notification-item p {
    color: #495057;
    margin-bottom: 0.3rem;
    transition: color 0.3s ease;
  }

  .notification-item small {
    color: #6c757d;
    transition: color 0.3s ease;
  }

  body.dark-mode .notification-item h6 {
    color: #f8f9fa;
  }

  body.dark-mode .notification-item p {
    color: #dee2e6;
  }

  body.dark-mode .notification-item small {
    color: #adb5bd;
  }

  /* Notification actions */
  .notification-actions {
    display: flex;
    align-items: center;
  }

  .notification-actions .btn-outline-secondary,
  .notification-actions .btn-outline-primary {
    padding: 0.15rem 0.5rem;
    font-size: 0.75rem;
    border-radius: 0.2rem;
    transition: all 0.2s ease;
  }

  .notification-actions .btn-outline-secondary {
    color: #6c757d;
    border-color: #6c757d;
  }

  .notification-actions .btn-outline-primary {
    color: #0d6efd;
    border-color: #0d6efd;
  }

  .notification-actions .btn-outline-secondary:hover {
    background-color: #6c757d;
    color: #fff;
  }

  .notification-actions .btn-outline-primary:hover {
    background-color: #0d6efd;
    color: #fff;
  }

  body.dark-mode .notification-actions .btn-outline-secondary {
    color: #adb5bd;
    border-color: #6c757d;
  }

  body.dark-mode .notification-actions .btn-outline-primary {
    color: #6ea8fe;
    border-color: #0d6efd;
  }

  body.dark-mode .notification-actions .btn-outline-secondary:hover {
    background-color: #6c757d;
    color: #f8f9fa;
  }

  body.dark-mode .notification-actions .btn-outline-primary:hover {
    background-color: #0d6efd;
    color: #f8f9fa;
  }

  /* Mark all as read link */
  #markAllRead, #mobileMarkAllRead {
    transition: color 0.2s ease;
  }

  #markAllRead:hover, #mobileMarkAllRead:hover {
    color: #0d6efd !important;
    text-decoration: underline;
  }

  body.dark-mode #markAllRead, 
  body.dark-mode #mobileMarkAllRead {
    color: rgba(255, 255, 255, 0.7) !important;
  }

  body.dark-mode #markAllRead:hover, 
  body.dark-mode #mobileMarkAllRead:hover {
    color: #ffffff !important;
  }

  /* Read notifications */
  .notification-item.read {
    opacity: 0.7;
    border-left-color: transparent;
  }

  .notification-item.read:hover {
    opacity: 0.9;
  }

  /* Mobile notification dropdown */
  .mobile-notifications-dropdown {
    max-width: 320px;
  }

  @media (max-width: 576px) {
    .mobile-notifications-dropdown {
      max-width: 290px;
    }
  }

  /* Notification Icon Styles */
  .icon-circle {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 2.5rem;
    height: 2.5rem;
    border-radius: 50%;
    box-shadow: 0 0.1rem 0.2rem rgba(0, 0, 0, 0.05);
  }

  /* User Profile Dropdown */
  .user-dropdown {
    display: flex;
    align-items: center;
    padding: 0.25rem 0.5rem;
  }

  .user-menu {
    padding: 0;
    overflow: hidden;
  }

  .user-menu .dropdown-item {
    padding: 0.5rem 1rem;
  }

  /* Connection Status */
  .connection-status .badge {
    padding: 0.35em 0.65em;
    font-size: 0.75em;
  }

  /* Connection Status Dot */
  .connection-dot {
    display: inline-block;
    width: 10px;
    height: 10px;
    border-radius: 50%;
    margin-right: 5px;
  }

  .connection-dot.online {
    background-color: #28a745;
    box-shadow: 0 0 5px #28a745;
  }

  .connection-dot.offline {
    background-color: #dc3545;
    box-shadow: 0 0 5px #dc3545;
  }

  /* Theme Toggle */
  .theme-toggle {
    position: relative;
    cursor: pointer;
  }

  .theme-toggle-wrapper {
    display: flex;
    align-items: center;
    justify-content: center;
  }

  .theme-toggle .dark-icon,
  .theme-toggle .light-icon {
    font-size: 1.1rem;
    transition: all 0.3s ease;
  }

  .theme-toggle .dark-icon {
    color: #6c757d;
  }

  .theme-toggle .light-icon {
    color: #ffc107;
  }

  /* Mobile Menu */
  @media (max-width: 767.98px) {
    .input-group-navbar {
      width: 100%;
    }
    
    /* Tighten up spacing on mobile */
    .mobile-nav-icons .nav-item {
      margin-right: 2px !important;
    }
    
    .mobile-nav-icons .nav-link {
      padding: 0.4rem;
      height: 32px;
      width: 32px;
    }
    
    /* Ensure dropdowns appear correctly on mobile */
    .dropdown-menu-lg {
      min-width: 260px;
      max-width: 95vw;
      margin-top: 0.5rem;
      right: -5px;
    }
    
    /* Mobile sidebar toggle button styling */
    #mobile-sidebar-toggle {
      z-index: 1036;
    }
    
    /* Make sure dropdowns are properly positioned on mobile */
    .dropdown-menu {
      position: absolute;
    }
  }

  /* Fullscreen button functionality */
  .fullscreen-enabled {
    background-color: #fff;
  }

  body.dark-mode.fullscreen-enabled {
    background-color: #212529;
  }

  /* Hide dropdown arrows/carets */
  .dropdown-toggle::after,
  .nav-item.dropdown .nav-link::after,
  .no-caret::after {
    display: none !important;
  }
  
  /* Mobile Auto-Hide Header on Scroll */
  @media (max-width: 767.98px) {
    .main-header {
      position: sticky;
      top: 0;
      z-index: 1030;
      transition: transform 0.3s ease;
    }
    
    .header-scroll-up {
      transform: translateY(0);
    }
    
    .header-scroll-down {
      transform: translateY(-100%);
    }
  }
</style>
  
<script>
// Only keep essential scripts if needed, or remove entirely if handled by footer
document.addEventListener('DOMContentLoaded', function() {
  console.log('Topbar DOMContentLoaded (minimal)'); // Log: Topbar DOM ready

  // --- Dropdown closing prevention --- 
  document.querySelectorAll('.dropdown-menu').forEach(function(element) {
    element.addEventListener('click', function (e) {
      // Prevent closing only if the click is inside a form within the dropdown
      if (e.target.closest('.dropdown-menu-form')) { 
        e.stopPropagation();
      }
    });
  });
  console.log('Topbar Dropdown prevention initialized'); // Log: Topbar Dropdown init

  // Date mode toggle handling (chip button)
  const dateModeToggleBtn = document.getElementById('dateModeToggleBtn');
  if (dateModeToggleBtn) {
    const endpoint = dateModeToggleBtn.dataset.endpoint || 'api/set-date-mode.php';
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    dateModeToggleBtn.addEventListener('click', function() {
      const current = dateModeToggleBtn.dataset.current === 'bs' ? 'bs' : 'ad';
      const desiredMode = current === 'bs' ? 'ad' : 'bs';
      dateModeToggleBtn.disabled = true;
      fetch(endpoint, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': csrfToken
        },
        body: JSON.stringify({ mode: desiredMode })
      }).then(function(response) {
        if (!response.ok) {
          throw new Error('Failed to update date preference');
        }
        return response.json();
      }).then(function(json) {
        if (json.status === 'success') {
          window.location.reload();
        } else {
          throw new Error(json.message || 'Unable to update preference');
        }
      }).catch(function(error) {
        console.error('Date mode toggle error:', error);
        alert('Unable to switch calendar mode. Please try again.');
        dateModeToggleBtn.disabled = false;
      });
    });
  }
  
  // Auto-hide header on scroll for mobile
  if (window.innerWidth < 768) {
    const header = document.querySelector('.main-header');
    let lastScrollTop = 0;
    
    // Add initial class
    header.classList.add('header-scroll-up');
    
    window.addEventListener('scroll', function() {
      const scrollTop = window.pageYOffset || document.documentElement.scrollTop;
      
      // If scrolled more than 100px
      if (scrollTop > 100) {
        // Scrolling down and not at the bottom
        if (scrollTop > lastScrollTop) {
          header.classList.remove('header-scroll-up');
          header.classList.add('header-scroll-down');
        } 
        // Scrolling up
        else {
          header.classList.remove('header-scroll-down');
          header.classList.add('header-scroll-up');
        }
      }
      
      lastScrollTop = scrollTop <= 0 ? 0 : scrollTop;
    }, { passive: true });
  }
});
</script>

<!-- Topbar Attendance Script -->
<script>
document.addEventListener('DOMContentLoaded', function() {
  var btn = document.getElementById('topbarAttendanceBtn');
  if (!btn) return;
  var submitting = false;
  var hasSwal = typeof window.Swal !== 'undefined';
  var empId = btn.getAttribute('data-emp-id') || '';
  var url = btn.getAttribute('data-url') || 'modules/attendance/record_attendance.php';
  function showLoading(msg){ if(hasSwal){ Swal.fire({title:'Processing...',text:msg,allowOutsideClick:false,showConfirmButton:false,willOpen:()=>{Swal.showLoading();}});} }
  function showSuccess(title,text,cb){ if(hasSwal){ Swal.fire({icon:'success',title:title,text:text,showConfirmButton:false,timer:1600}).then(cb);} else { alert(title+(text?'\n'+text:'')); if(cb) cb(); } }
  function showError(title,html,retry){ if(hasSwal){ Swal.fire({icon:'error',title:title,html:html,showConfirmButton:true,confirmButtonText:'Try Again',showCancelButton:true,cancelButtonText:'Cancel'}).then(r=>{ if(r.isConfirmed&&retry) retry();}); } else { var again=confirm(title); if(again&&retry) retry(); } }
  btn.addEventListener('click', function(e){
    e.preventDefault();
    if (submitting) return;
    submitting = true;
    btn.disabled = true;
    var isCI = (btn.getAttribute('data-action') === 'CI');
    showLoading(isCI ? 'Recording clock in...' : 'Recording clock out...');
    fetch(url, {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
      credentials: 'same-origin',
      body: 'action=record_attendance&emp_id=' + encodeURIComponent(empId)
    }).then(async function(res){
      if(!res.ok) throw new Error('HTTP '+res.status);
      var text = await res.text(); var data;
      try { data = JSON.parse(text); } catch(e){ if(text && text.indexOf('success') !== -1){ return {success:true, action:(isCI?'CI':'CO'), message:'Attendance recorded'}; } throw e; }
      return data;
    }).then(function(data){
      if (data && data.success) {
        showSuccess(data.action==='CI'?'Clocked In!':'Clocked Out!', data.message||'', function(){ location.reload(); });
      } else {
        showError('Error', (data && data.message) || 'Failed to record attendance.', function(){ btn.click(); });
      }
    }).catch(function(err){
      console.error('Topbar attendance error', err);
      showError('Network Error', 'Please try again.', function(){ btn.click(); });
    }).finally(function(){ submitting = false; btn.disabled = false; });
  });
});
</script>

<!-- Mobile Theme Toggle Script -->
<script>
document.addEventListener('DOMContentLoaded', function() {
  // Mobile dark mode toggle
  const mobileDarkModeToggle = document.getElementById('mobileDarkModeToggle');
  const mobileDarkIcon = document.querySelector('.mobile-dark-icon');
  const mobileLightIcon = document.querySelector('.mobile-light-icon');
  const mobileThemeText = document.querySelector('.mobile-theme-text');
  
  console.log('Mobile theme elements:', { 
    toggle: mobileDarkModeToggle, 
    darkIcon: mobileDarkIcon, 
    lightIcon: mobileLightIcon, 
    text: mobileThemeText 
  });
  
  if (mobileDarkModeToggle) {
    // Get current theme state
    const isDarkMode = document.body.classList.contains('dark-mode');
    
    // Update initial state of mobile toggle
    if (isDarkMode) {
      mobileDarkIcon?.classList.add('d-none');
      mobileLightIcon?.classList.remove('d-none');
      if (mobileThemeText) mobileThemeText.textContent = 'Light Mode';
    } else {
      mobileDarkIcon?.classList.remove('d-none');
      mobileLightIcon?.classList.add('d-none');
      if (mobileThemeText) mobileThemeText.textContent = 'Dark Mode';
    }
    
    // Add click event listener for mobile theme toggle
    mobileDarkModeToggle.addEventListener('click', function(e) {
      e.preventDefault();
      console.log('Mobile theme toggle clicked');
      
      // Use the same toggleDarkMode function that desktop uses
      const isDark = document.body.classList.contains('dark-mode');
      
      // Toggle dark mode in localStorage and cookies
      if (isDark) {
        document.body.classList.remove('dark-mode');
        localStorage.setItem('dark-mode', 'false');
        document.cookie = 'dark-mode=false; path=/; max-age=31536000';
        mobileDarkIcon?.classList.remove('d-none');
        mobileLightIcon?.classList.add('d-none');
        if (mobileThemeText) mobileThemeText.textContent = 'Dark Mode';
      } else {
        document.body.classList.add('dark-mode');
        localStorage.setItem('dark-mode', 'true');
        document.cookie = 'dark-mode=true; path=/; max-age=31536000';
        mobileDarkIcon?.classList.add('d-none');
        mobileLightIcon?.classList.remove('d-none');
        if (mobileThemeText) mobileThemeText.textContent = 'Light Mode';
      }
      
      // Update table classes for DataTables
      document.querySelectorAll('table').forEach(table => {
        table.classList.toggle('table-dark', !isDark);
      });
    });
  }
});
</script>