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
    
} catch (Exception $e) {
    $error_message = "Error loading dashboard data: " . $e->getMessage();
}

require_once '../../includes/header.php';
?>

<!-- Main content -->
<div class="container-fluid p-4">
    <!-- Welcome Section -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card bg-gradient-primary text-white border-0 shadow-lg">
                <div class="card-body p-4">
                    <div class="row align-items-center">
                        <div class="col-lg-8">
                            <h2 class="fw-bold mb-2">Welcome back, <?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?>!</h2>
                            <p class="mb-0 opacity-75">Manage your tasks efficiently and stay productive. You have <?php echo $my_tasks_count; ?> tasks to focus on.</p>
                        </div>
                        <div class="col-lg-4 text-lg-end">
                            <button type="button" class="btn btn-light btn-lg shadow-sm" data-bs-toggle="modal" data-bs-target="#createTaskModal">
                                <i class="fas fa-plus me-2"></i>Create New Task
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Quick Stats -->
    <div class="row mb-4">
        <div class="col-md-6 col-xl-3 mb-3">
            <div class="card border-0 shadow-sm bg-light">
                <div class="card-body text-center p-4">
                    <div class="rounded-circle bg-primary d-inline-flex align-items-center justify-content-center mb-3" style="width: 60px; height: 60px;">
                        <i class="fas fa-clipboard-list text-white fa-lg"></i>
                    </div>
                    <h3 class="fw-bold text-primary mb-1"><?php echo $my_tasks_count; ?></h3>
                    <p class="text-muted mb-0">Total Tasks</p>
                </div>
            </div>
        </div>
        <div class="col-md-6 col-xl-3 mb-3">
            <div class="card border-0 shadow-sm bg-light">
                <div class="card-body text-center p-4">
                    <div class="rounded-circle bg-warning d-inline-flex align-items-center justify-content-center mb-3" style="width: 60px; height: 60px;">
                        <i class="fas fa-clock text-white fa-lg"></i>
                    </div>
                    <h3 class="fw-bold text-warning mb-1"><?php echo $pending_count; ?></h3>
                    <p class="text-muted mb-0">Pending</p>
                </div>
            </div>
        </div>
        <div class="col-md-6 col-xl-3 mb-3">
            <div class="card border-0 shadow-sm bg-light">
                <div class="card-body text-center p-4">
                    <div class="rounded-circle bg-info d-inline-flex align-items-center justify-content-center mb-3" style="width: 60px; height: 60px;">
                        <i class="fas fa-spinner text-white fa-lg"></i>
                    </div>
                    <h3 class="fw-bold text-info mb-1"><?php echo $in_progress_count; ?></h3>
                    <p class="text-muted mb-0">In Progress</p>
                </div>
            </div>
        </div>
        <div class="col-md-6 col-xl-3 mb-3">
            <div class="card border-0 shadow-sm bg-light">
                <div class="card-body text-center p-4">
                    <div class="rounded-circle bg-success d-inline-flex align-items-center justify-content-center mb-3" style="width: 60px; height: 60px;">
                        <i class="fas fa-check-circle text-white fa-lg"></i>
                    </div>
                    <h3 class="fw-bold text-success mb-1"><?php echo $completed_count; ?></h3>
                    <p class="text-muted mb-0">Completed</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white border-0 pb-0">
                    <h5 class="fw-bold mb-0"><i class="fas fa-bolt me-2 text-primary"></i>Quick Actions</h5>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <a href="my-tasks.php" class="card border-0 shadow-sm text-decoration-none h-100">
                                <div class="card-body text-center p-4">
                                    <i class="fas fa-user-check fa-2x text-primary mb-3"></i>
                                    <h6 class="fw-bold text-dark">My Tasks</h6>
                                    <p class="text-muted small mb-0">View and manage your assigned tasks</p>
                                </div>
                            </a>
                        </div>
                        <div class="col-md-4">
                            <a href="team-tasks.php" class="card border-0 shadow-sm text-decoration-none h-100">
                                <div class="card-body text-center p-4">
                                    <i class="fas fa-users fa-2x text-info mb-3"></i>
                                    <h6 class="fw-bold text-dark">Team Tasks</h6>
                                    <p class="text-muted small mb-0">Monitor your team's progress</p>
                                </div>
                            </a>
                        </div>
                        <div class="col-md-4">
                            <a href="assigned-tasks.php" class="card border-0 shadow-sm text-decoration-none h-100">
                                <div class="card-body text-center p-4">
                                    <i class="fas fa-share fa-2x text-success mb-3"></i>
                                    <h6 class="fw-bold text-dark">Tasks I Assigned</h6>
                                    <p class="text-muted small mb-0">Track tasks you've delegated</p>
                                </div>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Recent Tasks -->
    <div class="row">
        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white border-0 d-flex justify-content-between align-items-center">
                    <h5 class="fw-bold mb-0"><i class="fas fa-history me-2 text-primary"></i>Recent Tasks</h5>
                    <a href="my-tasks.php" class="btn btn-outline-primary btn-sm">View All</a>
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
                                <thead class="table-light">
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
                                                <div class="d-flex align-items-center">
                                                    <div class="progress me-2" style="width: 60px; height: 8px;">
                                                        <div class="progress-bar bg-primary" style="width: <?php echo $task['progress']; ?>%"></div>
                                                    </div>
                                                    <small class="text-muted"><?php echo $task['progress']; ?>%</small>
                                                </div>
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
        </div>
    </div>
</div>

<?php 
// Include the create task modal
require_once 'create_task_modal.php';

require_once '../../includes/footer.php'; 
?>
