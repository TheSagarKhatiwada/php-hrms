<?php
// Include utilities file for user role functions
require_once __DIR__ . '/utilities.php';
// Safe defaults to avoid undefined variable warnings
if (!isset($page)) { $page = ''; }
if (!isset($home)) { $home = './'; }
// Normalize role id
$roleId = isset($user['role_id']) ? (int)$user['role_id'] : (isset($user['role']) ? (int)$user['role'] : 0);
// Detect if current page belongs to Leave module, excluding Holiday Management page
$isLeaveSection = (strpos($_SERVER['REQUEST_URI'] ?? '', 'modules/leave/') !== false)
  
?>
<!-- Main Sidebar Container with Bootstrap 5 -->
<aside class="sidebar vh-100 position-fixed top-0 start-0 overflow-auto" id="main-sidebar">
  <!-- Brand Logo -->
  <div class="sidebar-brand d-flex justify-content-between align-items-center p-3">
    <a href="<?php echo $home;?>" class="text-decoration-none d-flex align-items-center">
      <!-- Changed flex-column to flex-row and adjusted alignment and margins -->
      <div class="d-flex flex-row align-items-center"> 
        <img src="<?php echo $home;?><?php echo COMPANY_LOGO; ?>" alt="<?php echo COMPANY_NAME; ?> Logo" class="img-fluid me-2" width="35" height="35"> <!-- Adjusted size and added margin-end -->
        <span class="fw-semibold text-primary" style="font-size: 2rem;"><?php echo APP_NAME; ?></span>
      </div>
    </a>
    <button id="sidebar-close" class="btn btn-sm btn-icon d-md-none">
      <i class="fas fa-times"></i>
    </button>
  </div>

  <!-- Navigation Menu -->
  <nav class="sidebar-nav">
  <ul class="nav flex-column" id="sidebarAccordion">
  <?php if ($roleId === 1 || has_permission('view_admin_dashboard')): // Admin Navigation ?>
        
        <?php if (is_admin() || has_permission('view_admin_dashboard')): // Show both dashboards grouped ?>
        <li class="nav-item">
          <a href="#dashboardSubmenu" data-bs-toggle="collapse" 
             class="nav-link <?php if($page == 'Admin Dashboard' || $page == 'Dashboard'){echo 'active';}?>
                    <?php if(!($page == 'Admin Dashboard' || $page == 'Dashboard')){echo 'collapsed';}?>">
            <i class="nav-icon fas fa-tachometer-alt"></i>
            <span>Dashboards</span>
            <i class="nav-arrow fas fa-chevron-right"></i>
          </a>
          <div class="collapse <?php if($page == 'Admin Dashboard' || $page == 'Dashboard'){echo 'show';}?>" id="dashboardSubmenu" data-bs-parent="#sidebarAccordion">
            <ul class="nav nav-sub flex-column">
              <li class="nav-item">
                <a href="<?php echo append_sid($home . 'admin-dashboard.php'); ?>" class="nav-link <?php if($page == 'Admin Dashboard'){echo 'active';}?>">
                  <i class="nav-icon fas fa-tachometer-alt"></i>
                  <span>Admin Dashboard</span>
                </a>
              </li>
              <li class="nav-item">
                <a href="<?php echo append_sid($home . 'dashboard.php'); ?>" class="nav-link <?php if($page == 'Dashboard'){echo 'active';}?>">
                  <i class="nav-icon fas fa-chart-line"></i>
                  <span>Dashboard</span>
                </a>
              </li>
            </ul>
          </div>
        </li>
        <?php else: // Show only admin dashboard ?>
        <li class="nav-item">
          <a href="<?php echo append_sid($home . 'admin-dashboard.php'); ?>" class="nav-link <?php if($page == 'Admin Dashboard'){echo 'active';}?>">
            <i class="nav-icon fas fa-tachometer-alt"></i>
            <span>Dashboard</span>
          </a>
        </li>
        <?php endif; ?>
        <li class="nav-item">
          <a href="#employeeSubmenu" data-bs-toggle="collapse" 
             class="nav-link <?php if($page == 'employees' || $page == 'attendance' || $page == 'schedule-overrides'){echo 'active';}?>
                    <?php if(!($page == 'employees' || $page == 'attendance' || $page == 'schedule-overrides')){echo 'collapsed';}?>">
            <i class="nav-icon fas fa-users"></i>
            <span>Employee Management</span>
            <i class="nav-arrow fas fa-chevron-right"></i>
          </a>
          <div class="collapse <?php if($page == 'employees' || $page == 'attendance' || $page == 'schedule-overrides'){echo 'show';}?>" id="employeeSubmenu" data-bs-parent="#sidebarAccordion">
            <ul class="nav nav-sub flex-column">
              <li class="nav-item">
                <a href="<?php echo append_sid($home . 'modules/employees/employees.php'); ?>" class="nav-link <?php if($page == 'employees'){echo 'active';}?>">
                  <i class="nav-icon fas fa-users"></i>
                  <span>Employees</span>
                </a>
              </li>
              <li class="nav-item">
                <a href="<?php echo append_sid($home . 'modules/attendance/attendance.php'); ?>" class="nav-link <?php if($page == 'attendance'){echo 'active';}?>">
                  <i class="nav-icon fas fa-clipboard-check"></i>
                  <span>Attendance</span>
                </a>
              </li>
              <li class="nav-item">
                <a href="<?php echo append_sid($home . 'modules/employees/schedule-overrides.php'); ?>" class="nav-link <?php if($page == 'schedule-overrides'){echo 'active';}?>">
                  <i class="nav-icon fas fa-clipboard-check"></i>
                  <span>Schedule Overrides</span>
                </a>
              </li>
            </ul>
          </div>
        </li>
       
        <!-- Leave Management Module -->
        <li class="nav-item">
       <a href="#leaveSubmenu" data-bs-toggle="collapse" 
         class="nav-link <?php if($isLeaveSection){echo 'active';}?> <?php if(!$isLeaveSection){echo 'collapsed';}?>">
            <i class="nav-icon fas fa-calendar-alt"></i>
            <span>Leaves & Holidays</span>
            <i class="nav-arrow fas fa-chevron-right"></i>
          </a>
          <div class="collapse <?php if($isLeaveSection){echo 'show';}?>" id="leaveSubmenu" data-bs-parent="#sidebarAccordion">
            <ul class="nav nav-sub flex-column">
              <li class="nav-item">
                <a href="<?php echo append_sid($home . 'modules/leave/index.php'); ?>" class="nav-link <?php if(basename($_SERVER['PHP_SELF']) == 'index.php' && strpos($_SERVER['REQUEST_URI'], 'modules/leave/') !== false){echo 'active';}?>">
                  <i class="nav-icon fas fa-tachometer-alt"></i>
                  <span>Dashboard</span>
                </a>
              </li>
              
              <li class="nav-item">
                <a href="<?php echo append_sid($home . 'modules/leave/my-requests.php'); ?>" class="nav-link <?php if(basename($_SERVER['PHP_SELF']) == 'my-requests.php' && strpos($_SERVER['REQUEST_URI'], 'modules/leave/') !== false){echo 'active';}?>">
                  <i class="nav-icon fas fa-list"></i>
                  <span>My Requests</span>
                </a>
              </li>
              <li class="nav-item">
                <a href="<?php echo append_sid($home . 'modules/leave/balance.php'); ?>" class="nav-link <?php if(basename($_SERVER['PHP_SELF']) == 'balance.php' && strpos($_SERVER['REQUEST_URI'], 'modules/leave/') !== false){echo 'active';}?>">
                  <i class="nav-icon fas fa-chart-pie"></i>
                  <span>My Balance</span>
                </a>
              </li>
              <li class="nav-item">
                <a href="<?php echo append_sid($home . 'modules/leave/calendar.php'); ?>" class="nav-link <?php if(basename($_SERVER['PHP_SELF']) == 'calendar.php' && strpos($_SERVER['REQUEST_URI'], 'modules/leave/') !== false){echo 'active';}?>">
                  <i class="nav-icon fas fa-calendar"></i>
                  <span>Calendar</span>
                </a>
              </li>
              <?php if ($roleId === 1 || has_permission('view_all_requests')): ?>
              <li class="nav-item">
                <a href="<?php echo append_sid($home . 'modules/leave/requests.php'); ?>" class="nav-link <?php if(basename($_SERVER['PHP_SELF']) == 'requests.php' && strpos($_SERVER['REQUEST_URI'], 'modules/leave/') !== false){echo 'active';}?>">
                  <i class="nav-icon fas fa-tasks"></i>
                  <span>Manage Requests</span>
                </a>
              </li>
              <li class="nav-item">
                <a href="<?php echo append_sid($home . 'modules/leave/types.php'); ?>" class="nav-link <?php if(basename($_SERVER['PHP_SELF']) == 'types.php' && strpos($_SERVER['REQUEST_URI'], 'modules/leave/') !== false || basename($_SERVER['PHP_SELF']) == 'reports.php' && strpos($_SERVER['REQUEST_URI'], 'modules/leave/') !== false){echo 'active';}?>">
                  <i class="nav-icon fas fa-cog"></i>
                  <span>Leave Settings</span>
                </a>
              </li>
              <li class="nav-item">
                <a href="<?php echo append_sid($home . 'modules/leave/accrual.php'); ?>" class="nav-link <?php if(basename($_SERVER['PHP_SELF']) == 'accrual.php' && strpos($_SERVER['REQUEST_URI'], 'modules/leave/') !== false){echo 'active';}?>">
                  <i class="nav-icon fas fa-coins"></i>
                  <span>Accrual Management</span>
                </a>
              </li>
              <!-- Holiday Management Page -->
              <li class="nav-item">
                <a href="<?php echo append_sid($home . 'modules/leave/holidays.php'); ?>" class="nav-link  <?php if(basename($_SERVER['PHP_SELF']) == 'holidays.php' && strpos($_SERVER['REQUEST_URI'], 'modules/leave/') !== false){echo 'active';}?>">
                  <i class="nav-icon fas fa-calendar-day"></i>
                  <span>Holidays</span>
                </a>
              </li>
              <?php endif; ?>
            </ul>
          </div>
        </li>

        <!-- Tasks (single link) -->
        <li class="nav-item">
          <a href="<?php echo append_sid($home . 'modules/tasks/index.php'); ?>" 
             class="nav-link <?php if(strpos($_SERVER['REQUEST_URI'], 'modules/tasks/') !== false){echo 'active';}?>">
            <i class="nav-icon fas fa-tasks"></i>
            <span>Tasks</span>
          </a>
        </li>
        
        <!-- Simplified Reports: only Attendance Reports Hub retained -->
        <li class="nav-item">
          <a href="<?php echo append_sid($home . 'modules/reports/attendance-reports.php'); ?>" class="nav-link <?php if($page == 'attendance-reports'){echo 'active';}?>">
            <i class="nav-icon fas fa-chart-bar"></i>
            <span>Attendance Reports</span>
          </a>
        </li>
        
        <li class="nav-item">
          <a href="#assetSubmenu" data-bs-toggle="collapse" 
             class="nav-link <?php if($page == 'Assets Management' || $page == 'Asset Categories' || $page == 'Manage Assets' || $page == 'Asset Assignments' || $page == 'Maintenance Records'){echo 'active';}?>
                    <?php if(!($page == 'Assets Management' || $page == 'Asset Categories' || $page == 'Manage Assets' || $page == 'Asset Assignments' || $page == 'Maintenance Records')){echo 'collapsed';}?>">
            <i class="nav-icon fas fa-clipboard-list"></i>
            <span>Asset Management</span>
            <i class="nav-arrow fas fa-chevron-right"></i>
          </a>
          <div class="collapse <?php if($page == 'Assets Management' || $page == 'Asset Categories' || $page == 'Manage Assets' || $page == 'Asset Assignments' || $page == 'Maintenance Records'){echo 'show';}?>" id="assetSubmenu" data-bs-parent="#sidebarAccordion">
            <ul class="nav nav-sub flex-column">
              <li class="nav-item">
                <a href="<?php echo append_sid($home . 'modules/assets/assets.php'); ?>" class="nav-link <?php if($page == 'Assets Management'){echo 'active';}?>">
                  <i class="nav-icon fas fa-tachometer-alt"></i>
                  <span>Overview</span>
                </a>
              </li>
              <li class="nav-item">
                <a href="<?php echo append_sid($home . 'modules/assets/manage_categories.php'); ?>" class="nav-link <?php if($page == 'Asset Categories'){echo 'active';}?>">
                  <i class="nav-icon fas fa-tags"></i>
                  <span>Categories</span>
                </a>
              </li>
              <li class="nav-item">
                <a href="<?php echo append_sid($home . 'modules/assets/manage_assets.php'); ?>" class="nav-link <?php if($page == 'Manage Assets'){echo 'active';}?>">
                  <i class="nav-icon fas fa-laptop"></i>
                  <span>Fixed Assets</span>
                </a>
              </li>
              <li class="nav-item">
                <a href="<?php echo append_sid($home . 'modules/assets/manage_assignments.php'); ?>" class="nav-link <?php if($page == 'Asset Assignments'){echo 'active';}?>">
                  <i class="nav-icon fas fa-people-carry"></i>
                  <span>Assignments</span>
                </a>
              </li>
              <li class="nav-item">
                <a href="<?php echo append_sid($home . 'modules/assets/manage_maintenance.php'); ?>" class="nav-link <?php if($page == 'Maintenance Records'){echo 'active';}?>">
                  <i class="nav-icon fas fa-tools"></i>
                  <span>Maintenance</span>
                </a>
              </li>
            </ul>
          </div>
        </li>
        <li class="nav-item">
          <a href="#organizationSubmenu" data-bs-toggle="collapse" 
             class="nav-link <?php if($page == 'branches' || $page == 'departments' || $page == 'designations' || $page == 'organizational-chart' || $page == 'hierarchy-setup' || $page == 'board-management' || $page == 'System Setting' || $page == 'Backup Management'){echo 'active';}?>
                    <?php if(!($page == 'branches' || $page == 'departments' || $page == 'designations' || $page == 'organizational-chart' || $page == 'hierarchy-setup' || $page == 'board-management' || $page == 'System Setting' || $page == 'Backup Management')){echo 'collapsed';}?>">
            <i class="nav-icon fas fa-building"></i>
            <span>Company Settings</span>
            <i class="nav-arrow fas fa-chevron-right"></i>
          </a>
          <div class="collapse <?php if($page == 'branches' || $page == 'departments' || $page == 'designations' || $page == 'organizational-chart' || $page == 'hierarchy-setup' || $page == 'board-management' || $page == 'System Setting' || $page == 'Backup Management'){echo 'show';}?>" id="organizationSubmenu" data-bs-parent="#sidebarAccordion">
            <ul class="nav nav-sub flex-column">
              <li class="nav-item">
                <a href="<?php echo append_sid($home . 'organizational-chart.php'); ?>" class="nav-link <?php if($page == 'organizational-chart'){echo 'active';}?>">
                  <i class="nav-icon fas fa-sitemap"></i>
                  <span>Organizational Chart</span>
                </a>
              </li>
              <li class="nav-item">
                <a href="<?php echo append_sid($home . 'hierarchy-setup.php'); ?>" class="nav-link <?php if($page == 'hierarchy-setup'){echo 'active';}?>">
                  <i class="nav-icon fas fa-cogs"></i>
                  <span>Hierarchy Setup</span>
                </a>
              </li>
              <li class="nav-item">
                <a href="<?php echo append_sid($home . 'board-management.php'); ?>" class="nav-link <?php if($page == 'board-management'){echo 'active';}?>">
                  <i class="nav-icon fas fa-crown"></i>
                  <span>Board of Directors</span>
                </a>
              </li>
              <li class="nav-item">
                <a href="<?php echo append_sid($home . 'branches.php'); ?>" class="nav-link <?php if($page == 'branches'){echo 'active';}?>">
                  <i class="nav-icon fas fa-code-branch"></i>
                  <span>Branches</span>
                </a>
              </li>
              <li class="nav-item">
                <a href="<?php echo append_sid($home . 'departments.php'); ?>" class="nav-link <?php if($page == 'departments'){echo 'active';}?>">
                  <i class="nav-icon fas fa-building"></i>
                  <span>Departments</span>
                </a>
              </li>
              <li class="nav-item">
                <a href="<?php echo append_sid($home . 'designations.php'); ?>" class="nav-link <?php if($page == 'designations'){echo 'active';}?>">
                  <i class="nav-icon fas fa-id-badge"></i>
                  <span>Designations</span>
                </a>
              </li>
              <li class="nav-item">
                <a href="<?php echo append_sid($home . 'system-settings.php'); ?>" class="nav-link <?php if($page == 'System Setting'){echo 'active';}?>">
                  <i class="nav-icon fas fa-wrench"></i>
                  <span>General Settings</span>
                </a>
              </li>
              <li class="nav-item">
                <a href="<?php echo append_sid($home . 'backup-management.php'); ?>" class="nav-link <?php if($page == 'Backup Management'){echo 'active';}?>">
                  <i class="nav-icon fas fa-database"></i>
                  <span>Backup & Restore</span>
                </a>
              </li>
            </ul>
          </div>
        </li>
        
        <li class="nav-item">
          <a href="<?php echo append_sid($home . 'modules/sms/sms-dashboard.php'); ?>" class="nav-link <?php if(in_array($page, ['SMS Dashboard','SMS Configuration','SMS Templates','SMS Logs'])){echo 'active';}?>">
            <i class="nav-icon fas fa-sms"></i>
            <span>SMS Management</span>
          </a>
        </li>
        <li class="nav-item">
          <a href="<?php echo append_sid($home . 'roles.php'); ?>" class="nav-link <?php if($page == 'roles'){echo 'active';}?>">
            <i class="nav-icon fas fa-user-tag"></i>
            <span>Roles & Permissions</span>
          </a>
        </li>
        
  <?php elseif ($roleId !== 1 && $roleId !== 4): // Regular Employee Navigation ?>
        
        <?php if (has_permission('view_admin_dashboard')): // Show both dashboards for users with permission ?>
        <li class="nav-item">
          <a href="#dashboardSubmenuEmployee" data-bs-toggle="collapse" 
             class="nav-link <?php if($page == 'Admin Dashboard' || $page == 'User Dashboard' || $page == 'Employee Dashboard'){echo 'active';}?>
                    <?php if(!($page == 'Admin Dashboard' || $page == 'User Dashboard' || $page == 'Employee Dashboard')){echo 'collapsed';}?>">
            <i class="nav-icon fas fa-tachometer-alt"></i>
            <span>Dashboards</span>
            <i class="nav-arrow fas fa-chevron-right"></i>
          </a>
          <div class="collapse <?php if($page == 'Admin Dashboard' || $page == 'User Dashboard' || $page == 'Employee Dashboard'){echo 'show';}?>" id="dashboardSubmenuEmployee" data-bs-parent="#sidebarAccordion">
            <ul class="nav nav-sub flex-column">
              <li class="nav-item">
                <a href="<?php echo append_sid($home . 'admin-dashboard.php'); ?>" class="nav-link <?php if($page == 'Admin Dashboard'){echo 'active';}?>">
                  <i class="nav-icon fas fa-tachometer-alt"></i>
                  <span>Admin Dashboard</span>
                </a>
              </li>
              <li class="nav-item">
                <a href="<?php echo append_sid($home . 'dashboard.php'); ?>" class="nav-link <?php if($page == 'User Dashboard' || $page == 'Employee Dashboard'){echo 'active';}?>">
                  <i class="nav-icon fas fa-chart-line"></i>
                  <span>Dashboard</span>
                </a>
              </li>
            </ul>
          </div>
        </li>
        <?php else: // Show only regular dashboard ?>
        <li class="nav-item">
          <a href="<?php echo append_sid($home . 'dashboard.php'); ?>" class="nav-link <?php if($page == 'User Dashboard' || $page == 'Employee Dashboard'){echo 'active';}?>">
            <i class="nav-icon fas fa-tachometer-alt"></i>
            <span>Dashboard</span>
          </a>
        </li>
        <?php endif; ?>
        
        <li class="nav-item">
       <a href="#leaveSubmenuEmployee" data-bs-toggle="collapse" 
         class="nav-link <?php if($isLeaveSection){echo 'active';}?> <?php if(!$isLeaveSection){echo 'collapsed';}?>">
            <i class="nav-icon fas fa-calendar-alt"></i>
            <span>Leave Management</span>
            <i class="nav-arrow fas fa-chevron-right"></i>
          </a>
          <div class="collapse <?php if($isLeaveSection){echo 'show';}?>" id="leaveSubmenuEmployee" data-bs-parent="#sidebarAccordion">
            <ul class="nav nav-sub flex-column">
              <li class="nav-item">
                <a href="<?php echo append_sid($home . 'modules/leave/index.php'); ?>" class="nav-link <?php if(basename($_SERVER['PHP_SELF']) == 'index.php' && strpos($_SERVER['REQUEST_URI'], 'modules/leave/') !== false){echo 'active';}?>">
                  <i class="nav-icon fas fa-tachometer-alt"></i>
                  <span>Dashboard</span>
                </a>
              </li>
              
              <li class="nav-item">
                <a href="<?php echo append_sid($home . 'modules/leave/my-requests.php'); ?>" class="nav-link <?php if(basename($_SERVER['PHP_SELF']) == 'my-requests.php' && strpos($_SERVER['REQUEST_URI'], 'modules/leave/') !== false){echo 'active';}?>">
                  <i class="nav-icon fas fa-list"></i>
                  <span>My Requests</span>
                </a>
              </li>
              <li class="nav-item">
                <a href="<?php echo append_sid($home . 'modules/leave/balance.php'); ?>" class="nav-link <?php if(basename($_SERVER['PHP_SELF']) == 'balance.php' && strpos($_SERVER['REQUEST_URI'], 'modules/leave/') !== false){echo 'active';}?>">
                  <i class="nav-icon fas fa-chart-pie"></i>
                  <span>My Balance</span>
                </a>
              </li>
              <li class="nav-item">
                <a href="<?php echo append_sid($home . 'modules/leave/calendar.php'); ?>" class="nav-link <?php if(basename($_SERVER['PHP_SELF']) == 'calendar.php' && strpos($_SERVER['REQUEST_URI'], 'modules/leave/') !== false){echo 'active';}?>">
                  <i class="nav-icon fas fa-calendar"></i>
                  <span>Calendar</span>
                </a>
              </li>
            </ul>
          </div>
        </li>
        
        <!-- File Converter Tools for Regular Users -->
        
        <!--
        <li class="nav-item">
          <a href="#fileConverterSubmenuUser" data-bs-toggle="collapse" 
             class="nav-link <?php if($page == 'File Converter' || $page == 'Batch File Converter'){echo 'active';}?>
                    <?php if(!($page == 'File Converter' || $page == 'Batch File Converter')){echo 'collapsed';}?>">
            <i class="nav-icon fas fa-exchange-alt"></i>
            <span>File Converter</span>
            <i class="nav-arrow fas fa-chevron-right"></i>
          </a>
          <div class="collapse <?php if($page == 'File Converter' || $page == 'Batch File Converter'){echo 'show';}?>" id="fileConverterSubmenuUser">
            <ul class="nav nav-sub flex-column">
              <li class="nav-item">
                <a href="<?php echo append_sid($home . 'converter.php'); ?>" class="nav-link <?php if($page == 'Batch File Converter' || $page == 'File Converter'){echo 'active';}?>">
                  <i class="nav-icon fas fa-files-o"></i>
                  <span>Batch Converter</span>
                </a>
              </li>
            </ul>
          </div>
        </li>
        -->
        
  <?php elseif ($roleId === 4): // Manager Navigation ?>
        
        <li class="nav-item">
          <a href="<?php echo append_sid($home . 'manager-dashboard.php'); ?>" class="nav-link <?php if($page == 'Manager Dashboard'){echo 'active';}?>">
            <i class="nav-icon fas fa-tachometer-alt"></i>
            <span>Dashboard</span>
          </a>
        </li>
        
        <!-- Manager Reports simplified to unified Attendance Reports Hub -->
        <li class="nav-item">
          <a href="<?php echo append_sid($home . 'modules/reports/attendance-reports.php'); ?>" class="nav-link <?php if($page == 'attendance-reports'){echo 'active';}?>">
            <i class="nav-icon fas fa-chart-bar"></i>
            <span>Attendance Reports</span>
          </a>
        </li>
        
        <!-- File Converter Tools for Managers -->
        
        <!--
        <li class="nav-item">
          <a href="#fileConverterSubmenuManager" data-bs-toggle="collapse" 
             class="nav-link <?php if($page == 'File Converter' || $page == 'Batch File Converter'){echo 'active';}?>
                    <?php if(!($page == 'File Converter' || $page == 'Batch File Converter')){echo 'collapsed';}?>">
            <i class="nav-icon fas fa-exchange-alt"></i>
            <span>File Converter</span>
            <i class="nav-arrow fas fa-chevron-right"></i>
          </a>
          <div class="collapse <?php if($page == 'File Converter' || $page == 'Batch File Converter'){echo 'show';}?>" id="fileConverterSubmenuManager">
            <ul class="nav nav-sub flex-column">
              <li class="nav-item">
                <a href="<?php echo append_sid($home . 'converter.php'); ?>" class="nav-link <?php if($page == 'Batch File Converter' || $page == 'File Converter'){echo 'active';}?>">
                  <i class="nav-icon fas fa-files-o"></i>
                  <span>Batch Converter</span>
                </a>
              </li>
            </ul>
          </div>
        </li>
        -->
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
  $stmt = $pdo->prepare("SELECT e.*, d.title AS designation_title, r.name AS role_name 
                          FROM employees e 
                          LEFT JOIN designations d ON e.designation = d.id 
                          LEFT JOIN roles r ON e.role_id = r.id
                          WHERE e.emp_id = :id");
  $stmt->execute(['id' => $user_id]);
  $user = $stmt->fetch(PDO::FETCH_ASSOC);
  ?>
  <div class="sidebar-footer">
    <div class="d-flex justify-content-between align-items-center p-3 mb-2">
      <a href="<?php echo $home;?>profile.php" class="text-decoration-none">
        <div class="d-flex align-items-center">
          <div class="sidebar-user-img me-2">
            <img src="<?php
  $img = $user['user_image'];
  if (empty($img)) {
    $img = $home . 'resources/userimg/default-image.jpg';
  } else if (strpos($img, 'http') === 0 || strpos($img, '/') === 0) {
    // Absolute URL or root-relative
  } else {
    $img = $home . ltrim($img, '/');
  }
  echo htmlspecialchars($img);
?>" 
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
              <?php echo htmlspecialchars($user['designation_title'] ?: 'Not Assigned'); ?>
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
  margin: 0.1rem 0.5rem 0.5rem 0.2rem;
}

body.dark-mode .nav-sub {
  background-color: rgba(0, 0, 0, 0.15);
}

.nav-sub .nav-link {
  padding-left: 2.2rem;
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