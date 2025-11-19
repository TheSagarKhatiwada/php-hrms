<?php
/**
 * Leave Module Navigation Integration
 * This file provides navigation menu items for integration with the main HRMS navigation
 */

include_once 'config.php';

/**
 * Get navigation menu items for the Leave Module based on user role
 */
function getLeaveModuleNavigation($user_role) {
    global $leave_navigation;
    
    $menu_items = [];
    
    foreach ($leave_navigation as $item) {
        if (hasLeavePermission($user_role, $item['permission'])) {
            $menu_items[] = [
                'title' => $item['title'],
                'url' => 'modules/leave/' . $item['url'],
                'icon' => $item['icon'],
                'permission' => $item['permission']
            ];
        }
    }
    
    return $menu_items;
}

/**
 * Get Leave Module menu for sidebar integration
 */
function getLeaveModuleMenuHtml($user_role, $current_page = '') {
    $menu_items = getLeaveModuleNavigation($user_role);
    
    if (empty($menu_items)) {
        return '';
    }
    
    $html = '<li class="nav-item has-treeview">';
    $html .= '<a href="#" class="nav-link">';
    $html .= '<i class="nav-icon fas fa-calendar-alt"></i>';
    $html .= '<p>Leave Management <i class="fas fa-angle-left right"></i></p>';
    $html .= '</a>';
    $html .= '<ul class="nav nav-treeview">';
    
    foreach ($menu_items as $item) {
        $active_class = (strpos($current_page, $item['url']) !== false) ? 'active' : '';
        $html .= '<li class="nav-item">';
        $html .= '<a href="' . htmlspecialchars($item['url']) . '" class="nav-link ' . $active_class . '">';
        $html .= '<i class="' . htmlspecialchars($item['icon']) . ' nav-icon"></i>';
        $html .= '<p>' . htmlspecialchars($item['title']) . '</p>';
        $html .= '</a>';
        $html .= '</li>';
    }
    
    $html .= '</ul>';
    $html .= '</li>';
    
    return $html;
}

/**
 * Get quick stats for dashboard widgets
 */
function getLeaveModuleQuickStats($user_id, $user_role) {
    global $pdo;
    
    $stats = [];
    
    if ($user_role === 'employee') {
        // Employee stats
        $stats_query = "
            SELECT 
                COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending_requests,
                COUNT(CASE WHEN status = 'approved' AND start_date > CURDATE() THEN 1 END) as upcoming_leaves,
                SUM(CASE WHEN status = 'approved' AND YEAR(start_date) = YEAR(CURDATE()) THEN days_requested ELSE 0 END) as days_taken_this_year
            FROM leave_requests 
            WHERE employee_id = $user_id AND deleted_at IS NULL
        ";
    } else {
        // Admin/HR stats
        $stats_query = "
            SELECT 
                COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending_requests,
                COUNT(CASE WHEN status = 'approved' AND start_date > CURDATE() THEN 1 END) as upcoming_leaves,
                COUNT(CASE WHEN DATE(created_at) = CURDATE() THEN 1 END) as requests_today
            FROM leave_requests 
            WHERE deleted_at IS NULL
        ";
    }
      $stmt = $pdo->prepare($stats_query);
    $stmt->execute();
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);
    
    return $stats;
}

/**
 * Get recent leave activities for dashboard
 */
function getRecentLeaveActivities($user_id, $user_role, $limit = 5) {
    global $pdo;
    
    if ($user_role === 'employee') {
        $query = "
            SELECT 
                lr.id,
                lr.status,
                lr.start_date,
                lr.end_date,
                lr.days_requested,
                lr.created_at,
                lt.name as leave_type,
                lt.color
            FROM leave_requests lr
            JOIN leave_types lt ON lr.leave_type_id = lt.id
            WHERE lr.employee_id = $user_id AND lr.deleted_at IS NULL
            ORDER BY lr.created_at DESC
            LIMIT $limit
        ";
    } else {
        $query = "
            SELECT 
                lr.id,
                lr.status,
                lr.start_date,
                lr.end_date,
                lr.days_requested,
                lr.created_at,
                lt.name as leave_type,
                lt.color,
                e.first_name,
                e.last_name
            FROM leave_requests lr
            JOIN leave_types lt ON lr.leave_type_id = lt.id
            JOIN employees e ON lr.employee_id = e.emp_id
            WHERE lr.deleted_at IS NULL
            ORDER BY lr.created_at DESC
            LIMIT $limit
        ";
    }
      $stmt = $pdo->prepare($query);
    $stmt->execute();
    $activities = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    return $activities;
}

/**
 * Generate dashboard widget HTML for leave module
 */
function getLeaveModuleDashboardWidget($user_id, $user_role) {
    $stats = getLeaveModuleQuickStats($user_id, $user_role);
    $activities = getRecentLeaveActivities($user_id, $user_role, 3);
    
    ob_start();
    ?>
    <div class="card card-primary">
        <div class="card-header">
            <h3 class="card-title"><i class="fas fa-calendar-alt"></i> Leave Overview</h3>
            <div class="card-tools">
                <a href="modules/leave/index.php" class="btn btn-tool">
                    <i class="fas fa-external-link-alt"></i>
                </a>
            </div>
        </div>
        <div class="card-body">
            <div class="row">
                <?php if ($user_role === 'employee'): ?>
                    <div class="col-4">
                        <div class="description-block border-right">
                            <span class="description-percentage text-warning">
                                <i class="fas fa-clock"></i>
                            </span>
                            <h5 class="description-header"><?php echo $stats['pending_requests'] ?? 0; ?></h5>
                            <span class="description-text">Pending</span>
                        </div>
                    </div>
                    <div class="col-4">
                        <div class="description-block border-right">
                            <span class="description-percentage text-success">
                                <i class="fas fa-calendar-day"></i>
                            </span>
                            <h5 class="description-header"><?php echo $stats['upcoming_leaves'] ?? 0; ?></h5>
                            <span class="description-text">Upcoming</span>
                        </div>
                    </div>
                    <div class="col-4">
                        <div class="description-block">
                            <span class="description-percentage text-info">
                                <i class="fas fa-chart-line"></i>
                            </span>
                            <h5 class="description-header"><?php echo $stats['days_taken_this_year'] ?? 0; ?></h5>
                            <span class="description-text">Days Used</span>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="col-4">
                        <div class="description-block border-right">
                            <span class="description-percentage text-warning">
                                <i class="fas fa-clock"></i>
                            </span>
                            <h5 class="description-header"><?php echo $stats['pending_requests'] ?? 0; ?></h5>
                            <span class="description-text">Pending</span>
                        </div>
                    </div>
                    <div class="col-4">
                        <div class="description-block border-right">
                            <span class="description-percentage text-success">
                                <i class="fas fa-calendar-day"></i>
                            </span>
                            <h5 class="description-header"><?php echo $stats['upcoming_leaves'] ?? 0; ?></h5>
                            <span class="description-text">Upcoming</span>
                        </div>
                    </div>
                    <div class="col-4">
                        <div class="description-block">
                            <span class="description-percentage text-primary">
                                <i class="fas fa-plus"></i>
                            </span>
                            <h5 class="description-header"><?php echo $stats['requests_today'] ?? 0; ?></h5>
                            <span class="description-text">Today</span>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
            
            <?php if (!empty($activities)): ?>
                <div class="mt-3">
                    <h6 class="text-muted">Recent Activities</h6>
                    <?php foreach ($activities as $activity): ?>
                        <div class="d-flex justify-content-between align-items-center border-bottom py-2">
                            <div>
                                <span class="badge" style="background-color: <?php echo $activity['color']; ?>; color: white;">
                                    <?php echo htmlspecialchars($activity['leave_type']); ?>
                                </span>
                                <?php if (isset($activity['first_name'])): ?>
                                    <small class="text-muted">
                                        - <?php echo htmlspecialchars($activity['first_name'] . ' ' . $activity['last_name']); ?>
                                    </small>
                                <?php endif; ?>
                            </div>
                            <div class="text-right">
                                <span class="badge <?php echo getLeaveStatusBadgeClass($activity['status']); ?>">
                                    <?php echo formatLeaveStatus($activity['status']); ?>
                                </span>
                                <br>
                                <small class="text-muted">
                                    <?php echo date('M d', strtotime($activity['start_date'])); ?>
                                </small>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            
            <div class="mt-3 text-center">
                <a href="modules/leave/index.php" class="btn btn-primary btn-sm">
                    <i class="fas fa-eye"></i> View Dashboard
                </a>
                <?php if (hasLeavePermission($user_role, PERMISSION_CREATE_REQUESTS)): ?>
                    <button type="button" class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#applyLeaveModal">
                        <i class="fas fa-plus"></i> Apply Leave
                    </button>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php
    return ob_get_clean();
}

/**
 * Get leave notifications for top navbar
 */
function getLeaveNotifications($user_id, $user_role) {
    global $pdo;
    
    $notifications = [];
    
    if ($user_role === 'employee') {
        // Employee notifications
        $query = "
            SELECT 
                lr.id,
                lr.status,
                lr.start_date,
                lr.reviewed_date,
                lt.name as leave_type
            FROM leave_requests lr
            JOIN leave_types lt ON lr.leave_type_id = lt.id
            WHERE lr.employee_id = $user_id 
                AND lr.deleted_at IS NULL
                AND (lr.status = 'approved' OR lr.status = 'rejected')
                AND lr.reviewed_date >= DATE_SUB(NOW(), INTERVAL 7 DAY)
            ORDER BY lr.reviewed_date DESC
            LIMIT 5
        ";
    } else {
        // Admin/HR notifications
        $query = "
            SELECT 
                lr.id,
                lr.status,
                lr.start_date,
                lr.created_at,
                lt.name as leave_type,
                e.first_name,
                e.last_name
            FROM leave_requests lr
            JOIN leave_types lt ON lr.leave_type_id = lt.id
            JOIN employees e ON lr.employee_id = e.emp_id
            WHERE lr.status = 'pending' 
                AND lr.deleted_at IS NULL
            ORDER BY lr.created_at ASC
            LIMIT 5
        ";
    }
      $stmt = $pdo->prepare($query);
    $stmt->execute();
    
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        if ($user_role === 'employee') {
            $message = "Your {$row['leave_type']} request was " . $row['status'];
            $time = time_elapsed_string($row['reviewed_date']);
        } else {
            $message = "{$row['first_name']} {$row['last_name']} requested {$row['leave_type']}";
            $time = time_elapsed_string($row['created_at']);
        }
        
        $notifications[] = [
            'id' => $row['id'],
            'message' => $message,
            'time' => $time,
            'url' => 'modules/leave/view.php?id=' . $row['id'],
            'icon' => 'fas fa-calendar-alt',
            'type' => $row['status']
        ];
    }
    
    return $notifications;
}

/**
 * Helper function to calculate time elapsed
 */
function time_elapsed_string($datetime, $full = false) {
    $now = new DateTime;
    $ago = new DateTime($datetime);
    $diff = $now->diff($ago);

    $diff->w = floor($diff->d / 7);
    $diff->d -= $diff->w * 7;

    $string = array(
        'y' => 'year',
        'm' => 'month',
        'w' => 'week',
        'd' => 'day',
        'h' => 'hour',
        'i' => 'minute',
        's' => 'second',
    );
    foreach ($string as $k => &$v) {
        if ($diff->$k) {
            $v = $diff->$k . ' ' . $v . ($diff->$k > 1 ? 's' : '');
        } else {
            unset($string[$k]);
        }
    }

    if (!$full) $string = array_slice($string, 0, 1);
    return $string ? implode(', ', $string) . ' ago' : 'just now';
}
?>
