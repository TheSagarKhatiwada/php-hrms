<!-- Main Sidebar Container with Bootstrap 5 -->
<aside class="sidebar vh-100 position-fixed top-0 start-0 overflow-auto" id="main-sidebar">
  <!-- Brand Logo -->
  <div class="sidebar-brand d-flex justify-content-between align-items-center p-3">
    <a href="<?php echo $home;?>" class="text-decoration-none d-flex align-items-center">
      <img src="<?php echo $home;?><?php echo COMPANY_LOGO; ?>" alt="<?php echo COMPANY_NAME; ?> Logo" class="img-fluid me-2" width="38" height="38">
      <span class="fs-4 fw-semibold"><?php echo APP_NAME; ?></span>
    </a>
    <button id="sidebar-close" class="btn btn-sm btn-icon d-md-none">
      <i class="fas fa-times"></i>
    </button>
  </div>

  <!-- Navigation Menu -->
  <nav class="sidebar-nav">
    <ul class="nav flex-column">
      <?php if ($user['role'] == 1): // Admin Navigation ?>
        <li class="nav-header">MAIN NAVIGATION</li>
        
        <li class="nav-item">
          <a href="<?php echo $home;?>admin-dashboard.php" class="nav-link <?php if($page == 'Admin Dashboard'){echo 'active';}?>">
            <i class="nav-icon fas fa-tachometer-alt"></i>
            <span>Dashboard</span>
          </a>
        </li>
        
        <li class="nav-header">EMPLOYEE MANAGEMENT</li>
        
        <li class="nav-item">
          <a href="<?php echo $home;?>employees.php" class="nav-link <?php if($page == 'employees'){echo 'active';}?>">
            <i class="nav-icon fas fa-users"></i>
            <span>Employees</span>
          </a>
        </li>
        
        <li class="nav-item">
          <a href="<?php echo $home;?>attendance.php" class="nav-link <?php if($page == 'attendance'){echo 'active';}?>">
            <i class="nav-icon fas fa-clipboard-check"></i>
            <span>Attendance</span>
          </a>
        </li>
        
        <li class="nav-header">REPORTS & ANALYTICS</li>
        
        <!-- Reports Dropdown -->
        <li class="nav-item">
          <a href="#reportsSubmenu" data-bs-toggle="collapse" 
             class="nav-link <?php if($page == 'daily-report' || $page == 'monthly-report'){echo 'active';}?> 
                    <?php if(!($page == 'daily-report' || $page == 'monthly-report')){echo 'collapsed';}?>">
            <i class="nav-icon fas fa-chart-bar"></i>
            <span>Reports</span>
            <i class="nav-arrow fas fa-chevron-right"></i>
          </a>
          <div class="collapse <?php if($page == 'daily-report' || $page == 'monthly-report'){echo 'show';}?>" id="reportsSubmenu">
            <ul class="nav nav-sub flex-column">
              <li class="nav-item">
                <a href="<?php echo $home;?>daily-report.php" class="nav-link <?php if($page == 'daily-report'){echo 'active';}?>">
                  <i class="nav-icon fas fa-file-alt"></i>
                  <span>Daily Report</span>
                </a>
              </li>
              <li class="nav-item">
                <a href="<?php echo $home;?>monthly-report.php" class="nav-link <?php if($page == 'monthly-report'){echo 'active';}?>">
                  <i class="nav-icon fas fa-calendar"></i>
                  <span>Monthly Report</span>
                </a>
              </li>
            </ul>
          </div>
        </li>
        
        <li class="nav-header">SYSTEM</li>
        
        <!-- Asset Management Dropdown -->
        <li class="nav-item">
          <a href="#assetSubmenu" data-bs-toggle="collapse" 
             class="nav-link <?php if($page == 'Assets Management' || $page == 'Asset Categories' || $page == 'Manage Assets' || $page == 'Asset Assignments' || $page == 'Maintenance Records'){echo 'active';}?>
                    <?php if(!($page == 'Assets Management' || $page == 'Asset Categories' || $page == 'Manage Assets' || $page == 'Asset Assignments' || $page == 'Maintenance Records')){echo 'collapsed';}?>">
            <i class="nav-icon fas fa-boxes"></i>
            <span>Asset Management</span>
            <i class="nav-arrow fas fa-chevron-right"></i>
          </a>
          <div class="collapse <?php if($page == 'Assets Management' || $page == 'Asset Categories' || $page == 'Manage Assets' || $page == 'Asset Assignments' || $page == 'Maintenance Records'){echo 'show';}?>" id="assetSubmenu">
            <ul class="nav nav-sub flex-column">
              <li class="nav-item">
                <a href="<?php echo $home;?>assets.php" class="nav-link <?php if($page == 'Assets Management'){echo 'active';}?>">
                  <i class="nav-icon fas fa-clipboard-list"></i>
                  <span>Overview</span>
                </a>
              </li>
              <li class="nav-item">
                <a href="<?php echo $home;?>manage_categories.php" class="nav-link <?php if($page == 'Asset Categories'){echo 'active';}?>">
                  <i class="nav-icon fas fa-tags"></i>
                  <span>Categories</span>
                </a>
              </li>
              <li class="nav-item">
                <a href="<?php echo $home;?>manage_assets.php" class="nav-link <?php if($page == 'Manage Assets'){echo 'active';}?>">
                  <i class="nav-icon fas fa-laptop"></i>
                  <span>Fixed Assets</span>
                </a>
              </li>
              <li class="nav-item">
                <a href="<?php echo $home;?>manage_assignments.php" class="nav-link <?php if($page == 'Asset Assignments'){echo 'active';}?>">
                  <i class="nav-icon fas fa-people-carry"></i>
                  <span>Assignments</span>
                </a>
              </li>
              <li class="nav-item">
                <a href="<?php echo $home;?>manage_maintenance.php" class="nav-link <?php if($page == 'Maintenance Records'){echo 'active';}?>">
                  <i class="nav-icon fas fa-tools"></i>
                  <span>Maintenance</span>
                </a>
              </li>
            </ul>
          </div>
        </li>
        
        <!-- System Management Dropdown -->
        <li class="nav-item">
          <a href="#systemSubmenu" data-bs-toggle="collapse" 
             class="nav-link <?php if($page == 'System Setting' || $page == 'roles'){echo 'active';}?>
                    <?php if(!($page == 'system-settings' || $page == 'roles')){echo 'collapsed';}?>">
            <i class="nav-icon fas fa-cogs"></i>
            <span>System Settings</span>
            <i class="nav-arrow fas fa-chevron-right"></i>
          </a>
          <div class="collapse <?php if($page == 'System Setting' || $page == 'roles'){echo 'show';}?>" id="systemSubmenu">
            <ul class="nav nav-sub flex-column">
              <li class="nav-item">
                <a href="<?php echo $home;?>roles.php" class="nav-link <?php if($page == 'roles'){echo 'active';}?>">
                  <i class="nav-icon fas fa-user-tag"></i>
                  <span>Roles & Permissions</span>
                </a>
              </li>
              <li class="nav-item">
                <a href="<?php echo $home;?>system-settings.php" class="nav-link <?php if($page == 'System Setting'){echo 'active';}?>">
                  <i class="nav-icon fas fa-wrench"></i>
                  <span>System Settings</span>
                </a>
              </li>
            </ul>
          </div>
        </li>
        
      <?php elseif ($user['role'] == 0): // Employee Navigation ?>
        <li class="nav-header">MAIN NAVIGATION</li>
        
        <li class="nav-item">
          <a href="<?php echo $home;?>employee-dashboard.php" class="nav-link <?php if($page == 'Employee Dashboard'){echo 'active';}?>">
            <i class="nav-icon fas fa-tachometer-alt"></i>
            <span>Dashboard</span>
          </a>
        </li>
        
        <li class="nav-item">
          <a href="<?php echo $home;?>profile.php" class="nav-link <?php if($page == 'profile'){echo 'active';}?>">
            <i class="nav-icon fas fa-user"></i>
            <span>My Profile</span>
          </a>
        </li>
        
      <?php elseif ($user['role'] == 2): // Manager Navigation ?>
        <li class="nav-header">MAIN NAVIGATION</li>
        
        <li class="nav-item">
          <a href="<?php echo $home;?>manager-dashboard.php" class="nav-link <?php if($page == 'Manager Dashboard'){echo 'active';}?>">
            <i class="nav-icon fas fa-tachometer-alt"></i>
            <span>Dashboard</span>
          </a>
        </li>
        
        <li class="nav-header">REPORTS & ANALYTICS</li>
        
        <!-- Manager Reports Dropdown -->
        <li class="nav-item">
          <a href="#reportsSubmenu" data-bs-toggle="collapse" 
             class="nav-link <?php if($page == 'daily-report' || $page == 'monthly-report'){echo 'active';}?>
                    <?php if(!($page == 'daily-report' || $page == 'monthly-report')){echo 'collapsed';}?>">
            <i class="nav-icon fas fa-chart-bar"></i>
            <span>Reports</span>
            <i class="nav-arrow fas fa-chevron-right"></i>
          </a>
          <div class="collapse <?php if($page == 'daily-report' || $page == 'monthly-report'){echo 'show';}?>" id="reportsSubmenu">
            <ul class="nav nav-sub flex-column">
              <li class="nav-item">
                <a href="<?php echo $home;?>daily-report.php" class="nav-link <?php if($page == 'daily-report'){echo 'active';}?>">
                  <i class="nav-icon fas fa-file-alt"></i>
                  <span>Daily Report</span>
                </a>
              </li>
              <li class="nav-item">
                <a href="<?php echo $home;?>monthly-report.php" class="nav-link <?php if($page == 'monthly-report'){echo 'active';}?>">
                  <i class="nav-icon fas fa-calendar"></i>
                  <span>Monthly Report</span>
                </a>
              </li>
            </ul>
          </div>
        </li>
      <?php else:
        header('Location: index.php');
        exit();
      endif;
      $stmt->closeCursor();
      ?>
    </ul>
  </nav>
  
  <!-- User Profile Section at the bottom -->
  <?php
  // Include the database connection file if not already included
  if (!isset($pdo) || !$pdo) {
    include_once __DIR__ . '/../includes/db_connection.php';
  }
  // Fetch user details from the database
  $user_id = $_SESSION['user_id'];
  $stmt = $pdo->prepare("SELECT * FROM employees WHERE id = :id");
  $stmt->execute(['id' => $user_id]);
  $user = $stmt->fetch(PDO::FETCH_ASSOC);
  ?>
  <div class="sidebar-footer">
    <div class="d-flex justify-content-between align-items-center p-3 mb-2">
      <a href="<?php echo $home;?>profile.php" class="text-decoration-none">
        <div class="d-flex align-items-center">
          <div class="sidebar-user-img me-2">
            <img src="<?php echo htmlspecialchars($user['user_image'] ?: $home.'resources/images/default-user.png'); ?>" 
                 class="rounded-circle border" 
                 alt="Employee Image" 
                 width="40" height="40"
                 style="object-fit: cover;">
          </div>
          <div>
            <h6 class="mb-0 sidebar-user-name small">
              <?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?>
            </h6>
            <div class="sidebar-user-subtitle smaller">
              <?php echo htmlspecialchars($user['designation'] ?: 'Not Assigned'); ?>
            </div>
          </div>
        </div>
      </a>
      <a href="<?php echo $home;?>signout.php" class="btn btn-sm btn-outline-secondary" title="Sign Out">
        <i class="fas fa-sign-out-alt"></i>
      </a>
    </div>
  </div>
</aside>

<!-- Add the CSS styles for the improved sidebar -->
<style>
/* Core Sidebar Styles */
.sidebar {
  width: 260px;
  background: #f8f9fa;
  transition: all 0.3s ease;
  /* Adjust z-index to be below header but above potential content */
  z-index: 1035; 
  box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
  color: #212529;
  display: flex;
  flex-direction: column;
}

/* Dark mode sidebar */
body.dark-mode .sidebar {
  background: #343a40;
  color: rgba(255, 255, 255, 0.85);
  box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.2);
}

/* Sidebar Brand Styles */
.sidebar-brand {
  border-bottom: 1px solid rgba(0, 0, 0, 0.1);
  height: 60px;
}

.sidebar-brand a {
  color: #212529;
}

body.dark-mode .sidebar-brand {
  border-bottom: 1px solid rgba(255, 255, 255, 0.1);
}

body.dark-mode .sidebar-brand a {
  color: white;
}

.sidebar-toggle i {
  color: #6c757d;
}

body.dark-mode .sidebar-toggle i {
  color: rgba(255, 255, 255, 0.7);
}

/* Sidebar Nav (Middle section that can scroll) */
.sidebar-nav {
  flex: 1;
  overflow-y: auto;
}

/* Footer profile section at bottom */
.sidebar-footer {
  border-top: 1px solid rgba(0, 0, 0, 0.1);
  margin-top: auto;
}

body.dark-mode .sidebar-footer {
  border-top: 1px solid rgba(255, 255, 255, 0.1);
}

/* User Profile Section */
.sidebar-user-name {
  color: #212529;
  font-weight: 500;
}

.sidebar-user-subtitle {
  font-size: 12px;
  color: #6c757d;
}

.sidebar-user-img img {
  border: 2px solid rgba(0, 0, 0, 0.1);
}

body.dark-mode .sidebar-user-name {
  color: white;
}

body.dark-mode .sidebar-user-subtitle {
  color: rgba(255, 255, 255, 0.6);
}

body.dark-mode .sidebar-user-img img {
  border: 2px solid rgba(255, 255, 255, 0.2);
}

/* Size helpers */
.smaller {
  font-size: 0.7rem;
}

.small {
  font-size: 0.875rem;
}

/* Nav Headers */
.nav-header {
  padding: 0.75rem 1rem 0.25rem;
  font-size: 0.7rem;
  text-transform: uppercase;
  color: #6c757d;
  font-weight: bold;
  letter-spacing: 0.05rem;
}

body.dark-mode .nav-header {
  color: rgba(255, 255, 255, 0.5);
}

/* Nav Links */
.sidebar .nav-link {
  padding: 0.65rem 1rem;
  color: #495057;
  display: flex;
  align-items: center;
  border-radius: 4px;
  margin: 0 0.5rem 2px 0.5rem;
  position: relative;
  transition: all 0.2s ease;
}

.sidebar .nav-link:hover {
  color: #212529;
  background-color: rgba(0, 0, 0, 0.05);
}

.sidebar .nav-link.active {
  color: white;
  background-color: #0d6efd;
  box-shadow: 0 2px 4px rgba(0, 0, 0, 0.15);
}

body.dark-mode .sidebar .nav-link {
  color: rgba(255, 255, 255, 0.8);
}

body.dark-mode .sidebar .nav-link:hover {
  color: white;
  background-color: rgba(255, 255, 255, 0.1);
}

/* Signout button styling */
body.dark-mode .btn-outline-secondary {
  color: rgba(255, 255, 255, 0.8);
  border-color: rgba(255, 255, 255, 0.2);
}

body.dark-mode .btn-outline-secondary:hover {
  background-color: rgba(255, 255, 255, 0.1);
  color: white;
  border-color: rgba(255, 255, 255, 0.4);
}

/* Nav Icons */
.nav-icon {
  display: inline-block;
  width: 1.5rem;
  margin-right: 0.7rem;
  text-align: center;
  font-size: 1rem;
}

.nav-arrow {
  position: absolute;
  right: 1rem;
  top: 50%;
  transform: translateY(-50%);
  transition: transform 0.3s ease;
  font-size: 0.8rem;
}

.nav-link:not(.collapsed) .nav-arrow {
  transform: translateY(-50%) rotate(90deg);
}

/* Submenu Styles */
.nav-sub {
  padding-left: 0;
  background-color: rgba(0, 0, 0, 0.05);
  border-radius: 4px;
  margin: 0 0.5rem 0.5rem 0.5rem;
}

body.dark-mode .nav-sub {
  background-color: rgba(0, 0, 0, 0.15);
}

.nav-sub .nav-link {
  padding-left: 3.2rem;
  font-size: 0.9rem;
}

.nav-sub .nav-link.active {
  background-color: rgba(13, 110, 253, 0.8);
}

.nav-sub .nav-icon {
  width: 1.2rem;
  margin-right: 0.5rem;
  font-size: 0.9rem;
}

/* Mobile Sidebar Toggle */
.btn-icon {
  padding: 0.35rem 0.5rem;
  border-radius: 4px;
  color: #6c757d;
  /* border: 1px solid rgba(0, 0, 0, 0.2); */
  background: transparent;
}

.btn-icon:hover {
  /* background-color: rgba(0, 0, 0, 0.05); */
  color: #212529;
}

body.dark-mode .btn-icon {
  color: rgba(255, 255, 255, 0.7);
  /* border: 1px solid rgba(255, 255, 255, 0.2); */
}

body.dark-mode .btn-icon:hover {
  /* background-color: rgba(255, 255, 255, 0.1); */
  color: white;
}

/* Mobile Sidebar */
@media (max-width: 767.98px) {
  .sidebar {
    transform: translateX(-100%);
  }
  
  .sidebar.show {
    transform: translateX(0);
  }
}

/* Sidebar Toggle Animation */
#sidebar-toggle .fas {
  transition: transform 0.3s ease;
}

#sidebar-toggle:hover .fas {
  transform: rotate(90deg);
}
</style>

<!-- Add the JS for improved sidebar behavior -->
<script>
document.addEventListener('DOMContentLoaded', function() {
  // This script only manages submenu behavior to avoid conflicts with topbar.php
  // The main sidebar toggle is now handled in topbar.php
  
  // Submenu hover effect for better UX
  const submenuToggles = document.querySelectorAll('.sidebar .nav-link[data-bs-toggle="collapse"]');
  submenuToggles.forEach(toggle => {
    toggle.addEventListener('mouseenter', function() {
      if (window.innerWidth >= 768 && this.classList.contains('collapsed')) {
        this.classList.add('hover-highlight');
      }
    });
    
    toggle.addEventListener('mouseleave', function() {
      this.classList.remove('hover-highlight');
    });
  });
});
</script>