<?php
require_once __DIR__ . '/utilities.php';
if (!isset($pdo) || !$pdo) {
    include_once __DIR__ . '/db_connection.php';
}
if (!isset($page)) {
    $page = '';
}
if (!isset($home)) {
    $home = './';
}

$menuCatalog = hrms_menu_permissions_catalog();
hrms_sync_permissions_from_catalog();
$currentPath = trim((string)parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH), '/');
$appDisplayName = defined('APP_NAME') ? APP_NAME : 'HRMS';
$companyLogoPath = defined('COMPANY_LOGO') ? COMPANY_LOGO : 'resources/images/company_logo.png';
$companyName = defined('COMPANY_NAME') ? COMPANY_NAME : $appDisplayName;

$currentUserName = isset($_SESSION['fullName']) && trim((string)$_SESSION['fullName']) !== ''
  ? trim((string)$_SESSION['fullName'])
  : (isset($_SESSION['user_name']) ? trim((string)$_SESSION['user_name']) : 'User');

$currentUserRoleName = $_SESSION['user_role_name'] ?? null;
if (!$currentUserRoleName && isset($_SESSION['user_role_id'])) {
  try {
    $roleStmt = $pdo->prepare('SELECT name FROM roles WHERE id = ? LIMIT 1');
    $roleStmt->execute([(int)$_SESSION['user_role_id']]);
    $currentUserRoleName = $roleStmt->fetchColumn() ?: null;
    if ($currentUserRoleName) {
      $_SESSION['user_role_name'] = $currentUserRoleName;
    }
  } catch (PDOException $e) {
    $currentUserRoleName = null;
  }
}

$currentUserDesignation = $_SESSION['user_designation_title'] ?? null;
if (!$currentUserDesignation && isset($_SESSION['user_id'])) {
  try {
    $designationStmt = $pdo->prepare('SELECT d.title FROM employees e LEFT JOIN designations d ON e.designation_id = d.id WHERE e.emp_id = ? LIMIT 1');
    $designationStmt->execute([(int)$_SESSION['user_id']]);
    $currentUserDesignation = $designationStmt->fetchColumn() ?: null;
    if ($currentUserDesignation) {
      $_SESSION['user_designation_title'] = $currentUserDesignation;
    }
  } catch (PDOException $e) {
    $currentUserDesignation = null;
  }
}

$rawUserImage = isset($_SESSION['userImage']) ? trim((string)$_SESSION['userImage']) : '';
if ($rawUserImage === '') {
  $currentUserImage = $home . 'resources/userimg/default-image.jpg';
} elseif (preg_match('#^(https?:)?//#i', $rawUserImage) || stripos($rawUserImage, 'data:') === 0) {
  $currentUserImage = $rawUserImage;
} else {
  $currentUserImage = $home . ltrim($rawUserImage, '/');
}

$canAccessMenu = static function (array $menu) {
    $codes = [];
    foreach ($menu['permissions'] ?? [] as $meta) {
        if (!empty($meta['code'])) {
            $codes[] = $meta['code'];
        }
    }
    if (empty($codes)) {
        return true;
    }
    return has_any_permission($codes);
};

$isMenuActive = static function (array $menu) use ($page, $currentPath) {
    $pages = $menu['pages'] ?? [];
    foreach ($pages as $matchPage) {
        if ((string)$matchPage === (string)$page) {
            return true;
        }
    }
    $matchUri = trim((string)($menu['match_uri'] ?? ''), '/');
    if ($matchUri !== '' && strpos($currentPath, $matchUri) !== false) {
        return true;
    }
    $route = trim((string)($menu['route'] ?? ''), '/');
    if ($route !== '' && strpos($currentPath, $route) !== false) {
        return true;
    }
    return false;
};

$linkForMenu = static function (array $menu) use ($home) {
    $route = trim((string)($menu['route'] ?? ''), '/');
    if ($route === '') {
        return '#';
    }
    return append_sid($home . $route);
};
?>
<!-- Main Sidebar Container with Bootstrap 5 -->
<aside class="sidebar vh-100 position-fixed top-0 start-0 overflow-auto" id="main-sidebar">
  <!-- Brand Logo -->
  <div class="sidebar-brand d-flex justify-content-between align-items-center p-3">
    <a href="<?php echo $home;?>" class="text-decoration-none d-flex align-items-center">
      <div class="d-flex flex-row align-items-center">
        <img src="<?php echo $home . $companyLogoPath; ?>" alt="<?php echo htmlspecialchars($companyName); ?> Logo" class="img-fluid me-2" width="35" height="35">
        <span class="fw-semibold text-primary" style="font-size: 2rem;">
          <?php echo htmlspecialchars($appDisplayName); ?>
        </span>
      </div>
    </a>
    <button id="sidebar-close" class="btn btn-sm btn-icon d-md-none" aria-label="Close sidebar">
      <i class="fas fa-times"></i>
    </button>
  </div>

  <!-- Navigation Menu -->
  <nav class="sidebar-nav">
    <ul class="nav flex-column" id="sidebarAccordion">
    <?php foreach ($menuCatalog['sections'] as $sectionKey => $section): ?>
      <?php if ($sectionKey === 'topbar') { continue; } ?>
      <?php
        $children = $section['children'] ?? [];
        $visibleChildren = [];
        foreach ($children as $child) {
            if ($canAccessMenu($child)) {
                $visibleChildren[] = $child;
            }
        }
        if (empty($visibleChildren)) {
            continue;
        }
        $sectionActive = false;
        foreach ($visibleChildren as $child) {
            if ($isMenuActive($child)) {
                $sectionActive = true;
                break;
            }
        }
        $collapseId = 'section-' . $sectionKey;
      $visibleCount = count($visibleChildren);
      $forceSingleMenu = ($visibleCount === 1);
      $isCollapsible = !$forceSingleMenu && (bool)($section['collapsible'] ?? ($visibleCount > 1));
      ?>
      <?php if ($isCollapsible): ?>
        <li class="nav-item">
          <a href="#<?php echo $collapseId; ?>" data-bs-toggle="collapse"
             class="nav-link <?php echo $sectionActive ? 'active' : ''; ?> <?php echo $sectionActive ? '' : 'collapsed'; ?>">
            <i class="nav-icon <?php echo $section['icon'] ?? 'fas fa-layer-group'; ?>"></i>
            <span><?php echo htmlspecialchars($section['label']); ?></span>
            <i class="nav-arrow fas fa-chevron-right"></i>
          </a>
          <div class="collapse <?php echo $sectionActive ? 'show' : ''; ?>" id="<?php echo $collapseId; ?>" data-bs-parent="#sidebarAccordion">
            <ul class="nav nav-sub flex-column">
              <?php foreach ($visibleChildren as $child): ?>
                <?php $childActive = $isMenuActive($child); ?>
                <li class="nav-item">
                  <a href="<?php echo $linkForMenu($child); ?>" class="nav-link <?php echo $childActive ? 'active' : ''; ?>">
                    <i class="nav-icon <?php echo $child['icon'] ?? ($section['icon'] ?? 'fas fa-circle'); ?>"></i>
                    <span><?php echo htmlspecialchars($child['label']); ?></span>
                  </a>
                </li>
              <?php endforeach; ?>
            </ul>
          </div>
        </li>
      <?php else: ?>
        <?php foreach ($visibleChildren as $child): ?>
          <?php $childActive = $isMenuActive($child); ?>
          <li class="nav-item">
            <a href="<?php echo $linkForMenu($child); ?>" class="nav-link <?php echo $childActive ? 'active' : ''; ?>">
              <i class="nav-icon <?php echo $child['icon'] ?? ($section['icon'] ?? 'fas fa-circle'); ?>"></i>
              <span><?php echo htmlspecialchars($child['label']); ?></span>
            </a>
          </li>
        <?php endforeach; ?>
      <?php endif; ?>
    <?php endforeach; ?>
    </ul>
  </nav>

  <!-- User Profile Section -->
  <?php if (is_logged_in()): ?>
    <div class="sidebar-footer">
      <div class="d-flex justify-content-between align-items-center p-2 mb-2">
        <a href="<?php echo append_sid($home . 'profile.php'); ?>" class="text-decoration-none w-100">
          <div class="d-flex align-items-center">
            <div class="sidebar-user-img me-2">
              <img src="<?php echo htmlspecialchars($currentUserImage); ?>" class="rounded-circle border" alt="User Image" width="40" height="40" style="object-fit: cover;">
            </div>
            <div>
              <h6 class="mb-0 sidebar-user-name small"><?php echo htmlspecialchars($currentUserName); ?></h6>
              <div class="sidebar-user-subtitle smaller"><?php echo htmlspecialchars($currentUserDesignation ?: 'Not Assigned'); ?></div>
            </div>
          </div>
        </a>
        <a href="<?php echo append_sid($home . 'signout.php'); ?>" class="btn btn-sm btn-outline-secondary ms-3" title="Sign Out">
          <i class="fas fa-sign-out-alt"></i>
        </a>
      </div>
    </div>
  <?php endif; ?>
</aside>

<!-- Sidebar-specific styles -->
<style>
.sidebar {
  width: 260px;
  background: var(--sidebar-bg, var(--bs-body-bg, #f8f9fa));
  transition: all 0.3s ease;
  z-index: 1035;
  box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
  color: #212529;
  display: flex;
  flex-direction: column;
  padding: 0 0.35rem;
}

body.dark-mode .sidebar {
  background: var(--sidebar-dark-bg, #343a40);
  color: rgba(255, 255, 255, 0.85);
  box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.2);
}

.sidebar-brand {
  border-bottom: 1px solid rgba(0, 0, 0, 0.1);
  height: 60px;
}

.sidebar-brand a {
  color: var(--sidebar-brand-color, #212529);
}

body.dark-mode .sidebar-brand {
  border-bottom: 1px solid rgba(255, 255, 255, 0.1);
}

body.dark-mode .sidebar-brand a {
  color: var(--sidebar-brand-dark-color, #fff);
}

.sidebar-nav {
  flex: 1;
  overflow-y: auto;
  padding-left: 0;
}

.sidebar-footer {
  border-top: 1px solid rgba(0, 0, 0, 0.1);
  margin-top: auto;
}

body.dark-mode .sidebar-footer {
  border-top: 1px solid rgba(255, 255, 255, 0.1);
}

.sidebar-user-name {
  color: #212529;
  font-weight: 500;
}

.sidebar-user-subtitle {
  font-size: 0.75rem;
  color: #6c757d;
}

.sidebar-user-img img {
  border: 2px solid rgba(0, 0, 0, 0.1);
}

body.dark-mode .sidebar-user-name {
  color: #fff;
}

body.dark-mode .sidebar-user-subtitle {
  color: rgba(255, 255, 255, 0.6);
}

body.dark-mode .sidebar-user-img img {
  border: 2px solid rgba(255, 255, 255, 0.2);
}

.smaller {
  font-size: 0.7rem;
}

.small {
  font-size: 0.875rem;
}

.sidebar .nav-link {
  padding: 0.65rem 1rem 0.65rem 0;
  color: #495057;
  display: flex;
  align-items: center;
  border-radius: 4px;
  margin: 0 0 2px 0;
  position: relative;
  transition: all 0.2s ease;
}

.sidebar .nav-link:hover {
  color: #212529;
  background-color: rgba(0, 0, 0, 0.05);
}

.sidebar .nav-link.active {
  color: #fff;
  background-color: var(--primary-color);
  box-shadow: 0 2px 4px rgba(0, 0, 0, 0.15);
}

body.dark-mode .sidebar .nav-link {
  color: rgba(255, 255, 255, 0.8);
}

body.dark-mode .sidebar .nav-link:hover {
  color: #fff;
  background-color: rgba(255, 255, 255, 0.1);
}

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

.nav-sub {
  padding-left: 0;
  background-color: rgba(var(--primary-rgb, 13, 110, 253), 0.06);
  border-radius: 4px;
  margin: 0.1rem 0.5rem 0.5rem 0.2rem;
}

body.dark-mode .nav-sub {
  background-color: rgba(var(--primary-rgb, 13, 110, 253), 0.15);
}

.nav-sub .nav-link {
  padding-left: 2.2rem;
  font-size: 0.9rem;
}

.nav-sub .nav-link.active {
  background-color: rgba(var(--primary-rgb, 13, 110, 253), 0.8);
}

.nav-sub .nav-icon {
  width: 1.2rem;
  margin-right: 0.5rem;
  font-size: 0.9rem;
}

.btn-icon {
  padding: 0.35rem 0.5rem;
  border-radius: 4px;
  color: #6c757d;
  background: transparent;
}

.btn-icon:hover {
  color: #212529;
}

body.dark-mode .btn-icon {
  color: rgba(255, 255, 255, 0.7);
}

body.dark-mode .btn-icon:hover {
  color: #fff;
}

@media (max-width: 767.98px) {
  .sidebar {
    transform: translateX(-100%);
  }

  .sidebar.show {
    transform: translateX(0);
  }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
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
