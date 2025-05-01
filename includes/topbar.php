<!-- Main Header -->
<header class="main-header">
  <!-- Modernized Navbar with Bootstrap 5 -->
  <nav class="navbar navbar-expand-lg shadow-sm">
    <div class="container-fluid">
      <!-- Mobile Sidebar Toggle -->
      <button id="mobile-sidebar-toggle" class="btn btn-icon d-md-none me-2" aria-label="Toggle sidebar">
        <i class="fas fa-bars"></i>
      </button>

      <!-- Home Icon and Search Form Aligned Left -->
      <a href="<?php echo isset($home) ? $home : './'; ?>" class="navbar-brand d-none d-sm-inline-block me-2">
        <i class="fas fa-home"></i>
      </a>

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

      <!-- Mobile brand centered -->
      <div class="d-sm-none mx-auto">
        <a href="<?php echo isset($home) ? $home : './'; ?>" class="navbar-brand">
          <span class="fw-bold"><?php echo APP_NAME; ?></span>
        </a>
      </div>

      <!-- Mobile Search & Menu Toggle -->
      <div class="d-flex d-md-none align-items-center">
        <!-- Search button for mobile -->
        <div class="nav-item">
          <a class="nav-link px-2" data-bs-toggle="collapse" href="#mobileSearch" role="button" aria-expanded="false" aria-controls="mobileSearch">
            <i class="fas fa-search"></i>
          </a>
        </div>
        
        <!-- Mobile Notifications Dropdown -->
        <div class="nav-item dropdown ms-2">
          <a class="nav-link dropdown-toggle p-0 position-relative" href="#" id="mobileNotificationsDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
            <i class="fas fa-bell"></i>
            <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" style="font-size: 0.5rem; padding: 0.2rem 0.4rem;">
              3
              <span class="visually-hidden">unread notifications</span>
            </span>
          </a>
          <div class="dropdown-menu dropdown-menu-end py-0 mobile-notifications-dropdown" aria-labelledby="mobileNotificationsDropdown">
            <div class="notifications-header d-flex justify-content-between align-items-center p-2 border-bottom">
              <h6 class="mb-0">Notifications</h6>
              <a href="#" class="text-muted small">Mark all as read</a>
            </div>
            <div class="notifications-body" style="max-height: 300px; overflow-y: auto;">
              <div class="list-group list-group-flush">
                <a href="#" class="list-group-item border-0 px-3 py-2">
                  <div class="d-flex align-items-center">
                    <div class="flex-shrink-0 me-2">
                      <i class="fas fa-users text-primary"></i>
                    </div>
                    <div class="flex-grow-1 small">
                      <div>5 new employees</div>
                      <div class="text-muted">30 minutes ago</div>
                    </div>
                  </div>
                </a>
                <a href="#" class="list-group-item border-0 px-3 py-2">
                  <div class="d-flex align-items-center">
                    <div class="flex-shrink-0 me-2">
                      <i class="fas fa-exclamation-circle text-warning"></i>
                    </div>
                    <div class="flex-grow-1 small">
                      <div>System update required</div>
                      <div class="text-muted">2 hours ago</div>
                    </div>
                  </div>
                </a>
                <a href="#" class="list-group-item border-0 px-3 py-2">
                  <div class="d-flex align-items-center">
                    <div class="flex-shrink-0 me-2">
                      <i class="fas fa-file-alt text-success"></i>
                    </div>
                    <div class="flex-grow-1 small">
                      <div>Monthly report ready</div>
                      <div class="text-muted">5 hours ago</div>
                    </div>
                  </div>
                </a>
              </div>
            </div>
            <div class="notifications-footer text-center p-2 border-top">
              <a href="#" class="text-muted small">View all notifications</a>
            </div>
          </div>
        </div>
        
        <!-- Mobile User Menu Toggle -->
        <div class="nav-item dropdown ms-2">
          <?php
          // Include the database connection file if not already included
          if (!isset($pdo)) {
            include_once __DIR__ . '/../includes/db_connection.php';
          }
          
          // Fetch user details from the database if not already available
          if (!isset($user) && isset($_SESSION['user_id'])) {
            $user_id = $_SESSION['user_id'];
            $stmt = $pdo->prepare("SELECT * FROM employees WHERE id = :id");
            $stmt->execute(['id' => $user_id]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            $stmt->closeCursor();
          }
          
          // Default home directory if not set
          $homeDir = isset($home) ? $home : './';
          
          // Company logo from settings
          $companyLogo = defined('COMPANY_LOGO') ? COMPANY_LOGO : 'company_logo.png';
          ?>
          <a class="nav-link dropdown-toggle p-0" href="#" id="mobileMenuToggle" role="button" data-bs-toggle="dropdown" aria-expanded="false">
            <img src="<?php echo htmlspecialchars($user['user_image'] ?: $homeDir.'resources/images/default-user.png'); ?>" 
                alt="User" class="rounded-circle border" width="32" height="32" style="object-fit: cover;">
          </a>
          <div class="dropdown-menu dropdown-menu-end py-0" aria-labelledby="mobileMenuToggle">
            <!-- Mobile Menu Items -->
            <div class="mobile-menu-header">
              <div class="d-flex align-items-center p-2 border-bottom">
                <img src="<?php echo htmlspecialchars($user['user_image'] ?: $homeDir.'resources/images/default-user.png'); ?>" 
                    alt="User" class="rounded-circle me-2 border" width="40" height="40" style="object-fit: cover;">
                <div>
                  <h6 class="mb-0"><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></h6>
                  <small class="text-muted"><?php echo htmlspecialchars($user['designation'] ?: 'Not Assigned'); ?></small>
                </div>
              </div>
            </div>
            
            <!-- Theme Toggle in mobile dropdown -->
            <a href="#" class="dropdown-item d-flex align-items-center py-2" id="mobileDarkModeToggle">
              <i class="fas fa-moon me-2 mobile-dark-icon"></i>
              <i class="fas fa-sun me-2 mobile-light-icon d-none"></i>
              <span class="mobile-theme-text">Dark Mode</span>
            </a>
            
            <!-- Online Status in mobile dropdown -->
            <a href="#" class="dropdown-item d-flex align-items-center py-2">
              <span class="connection-dot online me-2"></span>
              <span>Online</span>
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

      <!-- Right navbar items - Only visible on medium screens and larger -->
      <ul class="navbar-nav ms-auto align-items-center mobile-nav-icons d-none d-md-flex">
        <!-- Brand name for desktop -->
        <li class="nav-item me-3 d-none d-lg-flex align-items-center">
          <span class="fw-bold"><?php echo COMPANY_NAME; ?></span>
        </li>
        
        <!-- Online/Offline Status Indicator -->
        <li class="nav-item me-1 me-sm-2">
          <span class="connection-status d-flex align-items-center">
            <span class="connection-dot online me-1"></span>
            <span id="connection-text" class="d-none d-sm-inline small">Online</span>
          </span>
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
        
        <!-- Notifications Dropdown -->
        <li class="nav-item dropdown me-1">
          <a class="nav-link position-relative px-2" href="#" id="notificationsDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
            <i class="fas fa-bell"></i>
            <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">
              3
              <span class="visually-hidden">unread notifications</span>
            </span>
          </a>
          <div class="dropdown-menu dropdown-menu-end dropdown-menu-lg py-0" aria-labelledby="notificationsDropdown">
            <div class="dropdown-menu-header">
              <div class="position-relative">
                <h5 class="dropdown-header m-0">Notifications</h5>
                <a href="#" class="dropdown-notifications-all text-body">Mark all as read</a>
              </div>
            </div>
            <div class="list-group list-group-flush">
              <a href="#" class="list-group-item">
                <div class="row g-0 align-items-center">
                  <div class="col-2 text-center">
                    <i class="fas fa-users text-primary"></i>
                  </div>
                  <div class="col-10">
                    <div class="text-dark">5 new employees</div>
                    <div class="text-muted small mt-1">New employees registered today.</div>
                    <div class="text-muted small mt-1">30 minutes ago</div>
                  </div>
                </div>
              </a>
              <a href="#" class="list-group-item">
                <div class="row g-0 align-items-center">
                  <div class="col-2 text-center">
                    <i class="fas fa-exclamation-circle text-warning"></i>
                  </div>
                  <div class="col-10">
                    <div class="text-dark">System update required</div>
                    <div class="text-muted small mt-1">Please update to the latest version.</div>
                    <div class="text-muted small mt-1">2 hours ago</div>
                  </div>
                </div>
              </a>
              <a href="#" class="list-group-item">
                <div class="row g-0 align-items-center">
                  <div class="col-2 text-center">
                    <i class="fas fa-file-alt text-success"></i>
                  </div>
                  <div class="col-10">
                    <div class="text-dark">Monthly report ready</div>
                    <div class="text-muted small mt-1">The monthly report is ready to view.</div>
                    <div class="text-muted small mt-1">5 hours ago</div>
                  </div>
                </div>
              </a>
            </div>
            <div class="dropdown-menu-footer">
              <a href="#" class="text-center">View all notifications</a>
            </div>
          </div>
        </li>
        
        <!-- Fullscreen Toggle -->
        <li class="nav-item me-1">
          <button id="fullscreen-btn" class="nav-link btn-icon px-2" aria-label="Toggle fullscreen">
            <i class="fas fa-expand-arrows-alt"></i>
          </button>
        </li>
      </ul>
    </div>
  </nav>

  <!-- Mobile Search Collapse -->
  <div class="collapse" id="mobileSearch">
    <div class="card card-body rounded-0 border-top-0 p-2">
      <form action="<?php echo isset($home) ? $home : './'; ?>search-results.php" method="GET">
        <div class="input-group">
          <input class="form-control" type="search" name="query" placeholder="Search employees, assets, etc..." aria-label="Search">
          <button class="btn btn-primary" type="submit">
            <i class="fas fa-search"></i>
          </button>
        </div>
      </form>
    </div>
  </div>
</header>

<style>
/* Core Navbar Styles */
.navbar {
  padding: 0.5rem 1rem;
  background-color: #ffffff;
  border-bottom: 1px solid rgba(0, 0, 0, 0.05);
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
}

.dropdown-notifications-all {
  font-size: 0.8rem;
  text-decoration: none;
}

.dropdown-menu-footer {
  padding: 0.75rem 1rem;
  border-top: 1px solid rgba(0, 0, 0, 0.05);
  text-align: center;
}

.dropdown-menu-footer a {
  font-size: 0.8rem;
  text-decoration: none;
  color: #6c757d;
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
});
</script>