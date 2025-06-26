<?php
require_once '../../includes/session_config.php';
require_once '../../includes/db_connection.php';
require_once '../../includes/utilities.php';
require_once '../../includes/settings.php';
require_once 'task_helpers.php';

$page = 'Task Management Dashboard';

// Load user data properly
if (!isset($_SESSION['user_id'])) {
    header("Location: ../../index.php");
    exit();
}

// Get current user info - $_SESSION['user_id'] contains the emp_id
$current_user_id = $_SESSION['user_id'];

// Load user data for sidebar/header
$stmt = $pdo->prepare("SELECT e.*, d.title AS designation_title, r.name AS role_name 
                       FROM employees e 
                       LEFT JOIN designations d ON e.designation = d.id 
                       LEFT JOIN roles r ON e.role_id = r.id
                       WHERE e.emp_id = :id");
$stmt->execute(['id' => $current_user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    header("Location: ../../index.php");
    exit();
}

// Get task statistics
try {
    // My tasks count
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM tasks WHERE assigned_to = ?");
    $stmt->execute([$current_user_id]);
    $my_tasks_count = $stmt->fetchColumn();
    
    // My pending tasks
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM tasks WHERE assigned_to = ? AND status = 'pending'");
    $stmt->execute([$current_user_id]);
    $pending_count = $stmt->fetchColumn();
    
    // My in progress tasks
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM tasks WHERE assigned_to = ? AND status = 'in_progress'");
    $stmt->execute([$current_user_id]);
    $in_progress_count = $stmt->fetchColumn();
    
    // My completed tasks
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM tasks WHERE assigned_to = ? AND status = 'completed'");
    $stmt->execute([$current_user_id]);
    $completed_count = $stmt->fetchColumn();
    
    // Tasks I've assigned
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM tasks WHERE assigned_by = ?");
    $stmt->execute([$current_user_id]);
    $assigned_by_me_count = $stmt->fetchColumn();
    
    // Recent tasks (last 10)
    $stmt = $pdo->prepare("
        SELECT t.*, 
               assignee.first_name as assignee_first_name, 
               assignee.last_name as assignee_last_name,
               creator.first_name as creator_first_name, 
               creator.last_name as creator_last_name
        FROM tasks t
        LEFT JOIN employees assignee ON t.assigned_to = assignee.emp_id
        LEFT JOIN employees creator ON t.assigned_by = creator.emp_id
        WHERE t.assigned_to = ? OR t.assigned_by = ?
        ORDER BY t.created_at DESC
        LIMIT 10
    ");
    $stmt->execute([$current_user_id, $current_user_id]);
    $recent_tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Available tasks for self-assignment
    $stmt = $pdo->prepare("
        (
            -- Open tasks available for anyone
            SELECT t.*, 
                   NULL as assignee_first_name, 
                   NULL as assignee_last_name,
                   creator.first_name as creator_first_name,
                   creator.last_name as creator_last_name,
                   'open' as task_status_type
            FROM tasks t
            LEFT JOIN employees creator ON t.assigned_by = creator.emp_id
            WHERE t.task_type = 'open' 
            AND t.assigned_to IS NULL
        )
        UNION ALL
        (
            -- Department tasks available for self-assignment
            SELECT t.*, 
                   NULL as assignee_first_name, 
                   NULL as assignee_last_name,
                   creator.first_name as creator_first_name,
                   creator.last_name as creator_last_name,
                   'available' as task_status_type
            FROM tasks t
            LEFT JOIN employees creator ON t.assigned_by = creator.emp_id
            WHERE t.task_type = 'department' 
            AND t.target_department_id = ?
            AND t.assigned_to IS NULL
        )
        ORDER BY created_at DESC
        LIMIT 5
    ");
    $stmt->execute([$user['department_id']]);
    $available_tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // All tasks (if user has permission or is admin)
    $can_view_all_tasks = is_admin() || has_permission('manage_all_tasks');
    $all_tasks = []; // Initialize to prevent undefined variable error
    
    if ($can_view_all_tasks) {
        $stmt = $pdo->prepare("
            SELECT t.*, 
                   assignee.first_name as assignee_first_name, 
                   assignee.last_name as assignee_last_name,
                   assignee.image as assignee_image,
                   creator.first_name as creator_first_name, 
                   creator.last_name as creator_last_name,
                   creator.image as creator_image,
                   CASE 
                       WHEN t.assigned_to IS NULL AND t.task_type = 'open' THEN 'open'
                       WHEN t.assigned_to IS NULL AND t.task_type = 'department' THEN 'department_available'
                       ELSE 'assigned'
                   END as task_status_type
            FROM tasks t
            LEFT JOIN employees assignee ON t.assigned_to = assignee.emp_id
            LEFT JOIN employees creator ON t.assigned_by = creator.emp_id
            ORDER BY t.created_at DESC
        ");
        $stmt->execute();
        $all_tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $all_tasks = [];
    }
    
} catch (Exception $e) {
    $error_message = "Error loading dashboard data: " . $e->getMessage();
    $available_tasks = [];
    $all_tasks = []; // Ensure it's defined even on error
    $can_view_all_tasks = false; // Set to false on error
}

require_once '../../includes/header.php';
?>

<!-- Main content -->
<div class="container-fluid p-4">
    <!-- Page header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="fs-2 fw-bold mb-1"><i class="fas fa-tachometer-alt me-2"></i>Task Management Dashboard</h1>
        </div>
        <div class="d-flex gap-2">
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createTaskModal">
                <i class="fas fa-plus me-1"></i>Create Task
            </button>
            <a href="my-tasks.php" class="btn btn-outline-success">
                <i class="fas fa-user me-1"></i>My Tasks
            </a>
            <a href="team-tasks.php" class="btn btn-outline-info">
                <i class="fas fa-users me-1"></i>Team Tasks
            </a>
        </div>
    </div>

    <!-- Stats Cards -->
    <div class="row mb-4">
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs fw-bold text-primary text-uppercase mb-1">
                                My Tasks
                            </div>
                            <div class="h5 mb-0 fw-bold text-gray-800">
                                <?php echo $my_tasks_count; ?>
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-clipboard-list fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs fw-bold text-warning text-uppercase mb-1">
                                Pending Tasks
                            </div>
                            <div class="h5 mb-0 fw-bold text-gray-800">
                                <?php echo $pending_count; ?>
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-clock fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs fw-bold text-info text-uppercase mb-1">
                                In Progress
                            </div>
                            <div class="h5 mb-0 fw-bold text-gray-800">
                                <?php echo $in_progress_count; ?>
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-spinner fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs fw-bold text-success text-uppercase mb-1">
                                Completed
                            </div>
                            <div class="h5 mb-0 fw-bold text-gray-800">
                                <?php echo $completed_count; ?>
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-check-circle fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Recent Tasks -->
    <div class="card border-0 shadow-sm">
        <div class="card-header">
            <h3 class="card-title mb-0 fw-bold">
                <i class="fas fa-history me-2 text-primary"></i>Recent Tasks
            </h3>
        </div>
        <div class="card-body p-0">
            <?php if (empty($recent_tasks)): ?>
                <div class="text-center py-5">
                    <i class="fas fa-tasks fa-3x text-muted mb-3"></i>
                    <h5 class="text-muted">No tasks yet</h5>
                    <p class="text-muted">Get started by creating your first task!</p>
                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createTaskModal">
                        <i class="fas fa-plus me-1"></i>Create Task
                    </button>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table">
                            <tr>
                                <th class="border-0 fw-semibold">Task</th>
                                <th class="border-0 fw-semibold">Status</th>
                                <th class="border-0 fw-semibold">Priority</th>
                                <th class="border-0 fw-semibold">Due Date</th>
                                <th class="border-0 fw-semibold">Progress</th>
                                <th class="border-0 fw-semibold">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent_tasks as $task): ?>
                                <tr>
                                    <td>
                                        <div>
                                            <div class="fw-semibold"><?php echo htmlspecialchars($task['title']); ?></div>
                                            <?php if ($task['description']): ?>
                                                <small class="text-muted"><?php echo htmlspecialchars(substr($task['description'], 0, 50)) . (strlen($task['description']) > 50 ? '...' : ''); ?></small>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td>
                                        <?php
                                        $status_colors = [
                                            'pending' => 'warning',
                                            'in_progress' => 'info',
                                            'completed' => 'success',
                                            'cancelled' => 'danger'
                                        ];
                                        $color = $status_colors[$task['status']] ?? 'secondary';
                                        ?>
                                        <span class="badge bg-<?php echo $color; ?>"><?php echo ucfirst(str_replace('_', ' ', $task['status'])); ?></span>
                                    </td>
                                    <td>
                                        <?php
                                        $priority_colors = [
                                            'low' => 'success',
                                            'medium' => 'warning',
                                            'high' => 'danger',
                                            'urgent' => 'dark'
                                        ];
                                        $color = $priority_colors[$task['priority']] ?? 'secondary';
                                        ?>
                                        <span class="badge bg-<?php echo $color; ?>"><?php echo ucfirst($task['priority']); ?></span>
                                    </td>
                                    <td>
                                        <?php if ($task['due_date']): ?>
                                            <span class="<?php echo (strtotime($task['due_date']) < time() && $task['status'] != 'completed') ? 'text-danger' : 'text-muted'; ?>">
                                                <?php echo date('M j, Y', strtotime($task['due_date'])); ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="text-muted">No due date</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="progress" style="height: 8px; width: 100px;">
                                            <div class="progress-bar bg-primary" style="width: <?php echo $task['progress']; ?>%"></div>
                                        </div>
                                        <small class="text-muted"><?php echo $task['progress']; ?>%</small>
                                    </td>
                                    <td>
                                        <a href="view_task.php?id=<?php echo $task['id']; ?>" class="btn btn-outline-primary btn-sm">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Available Tasks for Self-Assignment -->
    <div class="card border-0 shadow-sm mt-4">
        <div class="card-header">
            <div class="d-flex align-items-center justify-content-between">
                <h3 class="card-title mb-0 fw-bold">
                    <i class="fas fa-hand-paper me-2 text-success"></i>Available Tasks
                </h3>
                <span class="badge text-dark">
                    <?php echo count($available_tasks); ?> Available
                </span>
            </div>
        </div>
        <div class="card-body p-0">
            <?php if (empty($available_tasks)): ?>
                <div class="text-center py-5">
                    <i class="fas fa-hand-paper fa-3x text-muted mb-3"></i>
                    <h5 class="text-muted">No available tasks</h5>
                    <p class="text-muted">All tasks are currently assigned or there are no open/department tasks available.</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table">
                            <tr>
                                <th class="border-0 fw-semibold">Task</th>
                                <th class="border-0 fw-semibold">Type</th>
                                <th class="border-0 fw-semibold">Created By</th>
                                <th class="border-0 fw-semibold">Priority</th>
                                <th class="border-0 fw-semibold">Created</th>
                                <th class="border-0 fw-semibold">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($available_tasks as $task): ?>
                                <tr>
                                    <td>
                                        <div>
                                            <div class="fw-semibold"><?php echo htmlspecialchars($task['title']); ?></div>
                                            <?php if ($task['description']): ?>
                                                <small class="text-muted"><?php echo htmlspecialchars(substr($task['description'], 0, 50)) . (strlen($task['description']) > 50 ? '...' : ''); ?></small>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td>
                                        <?php if ($task['task_status_type'] === 'open'): ?>
                                            <span class="badge bg-info">🌟 Open Task</span>
                                        <?php else: ?>
                                            <span class="badge bg-primary">🏢 Department</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="avatar-sm me-2">
                                                <div class="avatar-title bg-secondary rounded-circle">
                                                    <?php echo strtoupper(substr($task['creator_first_name'], 0, 1)); ?>
                                                </div>
                                            </div>
                                            <span class="fw-medium"><?php echo htmlspecialchars($task['creator_first_name'] . ' ' . $task['creator_last_name']); ?></span>
                                        </div>
                                    </td>
                                    <td>
                                        <?php
                                        $priority_colors = [
                                            'low' => 'success',
                                            'medium' => 'warning',
                                            'high' => 'danger',
                                            'urgent' => 'dark'
                                        ];
                                        $color = $priority_colors[$task['priority']] ?? 'secondary';
                                        ?>
                                        <span class="badge bg-<?php echo $color; ?>"><?php echo ucfirst($task['priority']); ?></span>
                                    </td>
                                    <td>
                                        <span class="text-muted">
                                            <?php echo date('M j, Y', strtotime($task['created_at'])); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="btn-group" role="group">
                                            <a href="view_task.php?id=<?php echo $task['id']; ?>" 
                                               class="btn btn-outline-primary btn-sm" 
                                               data-bs-toggle="tooltip" 
                                               title="View Task Details">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <button type="button" 
                                                    class="btn btn-success btn-sm assign-task-btn" 
                                                    data-task-id="<?php echo $task['id']; ?>"
                                                    data-bs-toggle="tooltip" 
                                                    title="Assign to Myself">
                                                <i class="fas fa-hand-paper"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- All Tasks Section (for admins and users with permission) -->
    <?php if ($can_view_all_tasks): ?>
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white">
            <div class="d-flex align-items-center justify-content-between">
                <h3 class="card-title mb-0 fw-bold">
                    <i class="fas fa-list me-2 text-primary"></i>All Tasks Overview
                </h3>
                <span class="badge bg-light text-dark">
                    <?php echo count($all_tasks); ?> Tasks
                </span>
            </div>
        </div>
        <div class="card-body p-0">
            <?php if (empty($all_tasks)): ?>
                <div class="text-center py-5">
                    <div class="empty-state">
                        <i class="fas fa-list fa-4x text-muted mb-4"></i>
                        <h4 class="text-muted mb-3">No Tasks Found</h4>
                        <p class="text-muted mb-4">All system tasks will appear here.</p>
                        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createTaskModal">
                            <i class="fas fa-plus me-2"></i>Create New Task
                        </button>
                    </div>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0" id="allTasksTable">
                        <thead class="table-light">
                            <tr>
                                <th><i class="fas fa-hashtag me-2"></i>ID</th>
                                <th><i class="fas fa-tasks me-2"></i>Task Details</th>
                                <th><i class="fas fa-user me-2"></i>Assigned To</th>
                                <th><i class="fas fa-user-cog me-2"></i>Assigned By</th>
                                <th><i class="fas fa-calendar-alt me-2"></i>Due Date</th>
                                <th><i class="fas fa-flag me-2"></i>Priority</th>
                                <th><i class="fas fa-info-circle me-2"></i>Status</th>
                                <th><i class="fas fa-chart-line me-2"></i>Progress</th>
                                <th><i class="fas fa-calendar me-2"></i>Created</th>
                                <th><i class="fas fa-cogs me-2"></i>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($all_tasks as $task): ?>
                                <tr class="task-row">
                                    <td><span class="badge bg-light text-dark">#<?php echo $task['id']; ?></span></td>
                                    <td>
                                        <div class="task-info">
                                            <h6 class="mb-1 fw-bold"><?php echo htmlspecialchars($task['title']); ?></h6>
                                            <?php if (!empty($task['description'])): ?>
                                                <small class="text-muted d-block">
                                                    <?php echo htmlspecialchars(substr($task['description'], 0, 80)) . '...'; ?>
                                                </small>
                                            <?php endif; ?>
                                            <?php if ($task['task_type'] !== 'assigned'): ?>
                                                <span class="badge bg-info badge-sm">
                                                    <?php echo ucfirst($task['task_type']); ?> Task
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td>
                                        <?php if ($task['task_status_type'] === 'open' || $task['task_status_type'] === 'department_available'): ?>
                                            <div class="d-flex align-items-center">
                                                <span class="badge bg-success me-2">
                                                    <i class="fas fa-hand-paper me-1"></i>Available
                                                </span>
                                                <?php if ($task['task_status_type'] === 'open'): ?>
                                                    <small class="text-muted">🌟 Open Task</small>
                                                <?php else: ?>
                                                    <small class="text-muted">🏢 Department</small>
                                                <?php endif; ?>
                                            </div>
                                        <?php else: ?>
                                            <div class="d-flex align-items-center">
                                                <div class="avatar-sm me-2">
                                                    <?php if (!empty($task['assignee_image']) && file_exists('../../uploads/' . $task['assignee_image'])): ?>
                                                        <img src="../../uploads/<?php echo htmlspecialchars($task['assignee_image']); ?>" 
                                                             alt="<?php echo htmlspecialchars($task['assignee_first_name'] . ' ' . $task['assignee_last_name']); ?>"
                                                             class="rounded-circle" 
                                                             style="width: 32px; height: 32px; object-fit: cover;">
                                                    <?php else: ?>
                                                        <div class="avatar-title bg-primary rounded-circle">
                                                            <?php echo strtoupper(substr($task['assignee_first_name'] ?? 'U', 0, 1)); ?>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                                <div>
                                                    <span class="fw-medium">
                                                        <?php echo htmlspecialchars(($task['assignee_first_name'] ?? 'Unknown') . ' ' . ($task['assignee_last_name'] ?? '')); ?>
                                                    </span>
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="avatar-sm me-2">
                                                <?php if (!empty($task['creator_image']) && file_exists('../../uploads/' . $task['creator_image'])): ?>
                                                    <img src="../../uploads/<?php echo htmlspecialchars($task['creator_image']); ?>" 
                                                         alt="<?php echo htmlspecialchars($task['creator_first_name'] . ' ' . $task['creator_last_name']); ?>"
                                                         class="rounded-circle" 
                                                         style="width: 32px; height: 32px; object-fit: cover;">
                                                <?php else: ?>
                                                    <div class="avatar-title bg-secondary rounded-circle">
                                                        <?php echo strtoupper(substr($task['creator_first_name'], 0, 1)); ?>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                            <div>
                                                <span class="fw-medium"><?php echo htmlspecialchars($task['creator_first_name'] . ' ' . $task['creator_last_name']); ?></span>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <?php if (!empty($task['due_date'])): ?>
                                            <?php 
                                                $due_date = new DateTime($task['due_date']);
                                                $today = new DateTime();
                                                $diff = $today->diff($due_date);
                                                $is_overdue = $today > $due_date;
                                                $is_due_soon = !$is_overdue && $diff->days <= 3;
                                            ?>
                                            <div class="d-flex flex-column">
                                                <span class="fw-medium <?php echo $is_overdue ? 'text-danger' : ($is_due_soon ? 'text-warning' : 'text-muted'); ?>">
                                                    <i class="fas fa-calendar-alt me-1"></i>
                                                    <?php echo $due_date->format('M d, Y'); ?>
                                                </span>
                                                <?php if ($is_overdue): ?>
                                                    <small class="badge bg-danger">
                                                        <i class="fas fa-exclamation-triangle me-1"></i>Overdue
                                                    </small>
                                                <?php elseif ($is_due_soon): ?>
                                                    <small class="badge bg-warning">
                                                        <i class="fas fa-clock me-1"></i>Due Soon
                                                    </small>
                                                <?php endif; ?>
                                            </div>
                                        <?php else: ?>
                                            <span class="text-muted">
                                                <i class="fas fa-calendar-times me-1"></i>No due date
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge bg-<?php echo getPriorityColor($task['priority']); ?> fs-6">
                                            <i class="fas fa-flag me-1"></i>
                                            <?php echo ucfirst($task['priority']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge bg-<?php echo getStatusColor($task['status']); ?> fs-6">
                                            <i class="fas fa-circle me-1"></i>
                                            <?php echo ucfirst(str_replace('_', ' ', $task['status'])); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="progress" style="height: 8px; min-width: 80px;">
                                            <div class="progress-bar bg-<?php echo getStatusColor($task['status']); ?>" 
                                                 role="progressbar" 
                                                 style="width: <?php echo $task['progress']; ?>%" 
                                                 aria-valuenow="<?php echo $task['progress']; ?>" 
                                                 aria-valuemin="0" 
                                                 aria-valuemax="100">
                                            </div>
                                        </div>
                                        <small class="text-muted"><?php echo $task['progress']; ?>%</small>
                                    </td>
                                    <td>
                                        <small class="text-muted">
                                            <?php echo date('M d, Y', strtotime($task['created_at'])); ?>
                                        </small>
                                    </td>
                                    <td>
                                        <div class="btn-group" role="group">
                                            <a href="view_task.php?id=<?php echo $task['id']; ?>" 
                                               class="btn btn-sm btn-outline-primary" 
                                               data-bs-toggle="tooltip" 
                                               title="View Task Details">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <?php if (($task['task_status_type'] === 'open') || 
                                                      ($task['task_status_type'] === 'department_available' && $task['target_department_id'] == $user['department_id'])): ?>
                                                <button type="button" 
                                                        class="btn btn-sm btn-success assign-task-btn" 
                                                        data-task-id="<?php echo $task['id']; ?>"
                                                        data-bs-toggle="tooltip" 
                                                        title="Assign to Myself">
                                                    <i class="fas fa-hand-paper"></i>
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<style>
.avatar-sm {
    width: 32px;
    height: 32px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.avatar-title {
    width: 100%;
    height: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 12px;
    font-weight: 600;
    color: white;
}

.task-row:hover {
    background-color: #f8f9fa;
}

.empty-state {
    padding: 2rem;
}

.task-info h6 {
    color: #495057;
    margin-bottom: 0.25rem;
}

.badge-sm {
    font-size: 0.75rem;
}

.progress {
    border-radius: 10px;
}

.btn-group .btn {
    margin: 0 1px;
}

@media (max-width: 768px) {
    .table-responsive table {
        font-size: 0.875rem;
    }
    
    .avatar-sm {
        width: 24px;
        height: 24px;
    }
    
    .avatar-title {
        font-size: 10px;
    }
}
</style>

<script>
$(document).ready(function() {
    // Initialize DataTable for all tasks table if it exists
    if ($.fn.DataTable && $('#allTasksTable').length) {
        $('#allTasksTable').DataTable({
            "responsive": true,
            "pageLength": 25,
            "order": [[0, "desc"]],
            "columnDefs": [
                { "orderable": false, "targets": [9] } // Actions column (now column 9)
            ]
        });
    }
    
    // Initialize tooltips
    if (typeof bootstrap !== 'undefined') {
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });
    }
    
    // Handle task self-assignment
    $('.assign-task-btn').on('click', function() {
        var taskId = $(this).data('task-id');
        var button = $(this);
        
        // Show loading state
        button.prop('disabled', true);
        button.html('<i class="fas fa-spinner fa-spin"></i>');
        
        $.ajax({
            url: 'assign_task.php',
            type: 'POST',
            data: {
                task_id: taskId,
                action: 'assign'
            },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    // Show success message
                    if (typeof Swal !== 'undefined') {
                        Swal.fire({
                            title: 'Success!',
                            text: 'Task assigned to you successfully!',
                            icon: 'success',
                            timer: 2000,
                            showConfirmButton: false
                        }).then(() => {
                            // Reload the page to show updated task list
                            window.location.reload();
                        });
                    } else {
                        alert('Task assigned to you successfully!');
                        window.location.reload();
                    }
                } else {
                    // Show error message
                    if (typeof Swal !== 'undefined') {
                        Swal.fire({
                            title: 'Error!',
                            text: response.message || 'Failed to assign task',
                            icon: 'error'
                        });
                    } else {
                        alert('Error: ' + (response.message || 'Failed to assign task'));
                    }
                    
                    // Reset button
                    button.prop('disabled', false);
                    button.html('<i class="fas fa-hand-paper"></i>');
                }
            },
            error: function() {
                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        title: 'Error!',
                        text: 'Network error occurred. Please try again.',
                        icon: 'error'
                    });
                } else {
                    alert('Network error occurred. Please try again.');
                }
                
                // Reset button
                button.prop('disabled', false);
                button.html('<i class="fas fa-hand-paper"></i>');
            }
        });
    });
});
</script>

<?php 
// Include the create task modal
require_once 'create_task_modal.php';

require_once '../../includes/footer.php'; 
?>
