<?php
require_once '../../includes/session_config.php';
require_once '../../includes/db_connection.php';
require_once '../../includes/utilities.php';
require_once '../../includes/settings.php';
require_once 'task_helpers.php';

$page = 'My Tasks';

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

// Get status filter
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'all';

// Get tasks assigned to current user
try {
    $sql = "SELECT t.*, 
                   creator.first_name as creator_first_name, 
                   creator.last_name as creator_last_name
            FROM tasks t
            LEFT JOIN employees creator ON t.assigned_by = creator.emp_id
            WHERE t.assigned_to = ?";
    
    $params = [$current_user_id];
    
    if ($status_filter !== 'all') {
        $sql .= " AND t.status = ?";
        $params[] = $status_filter;
    }
    
    $sql .= " ORDER BY 
              CASE t.priority 
                WHEN 'urgent' THEN 1 
                WHEN 'high' THEN 2 
                WHEN 'medium' THEN 3 
                WHEN 'low' THEN 4 
              END ASC,
              t.due_date ASC,
              t.created_at DESC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    $error_message = "Error loading tasks: " . $e->getMessage();
    $tasks = [];
}

require_once '../../includes/header.php';
?>

<!-- Main content -->
<div class="container-fluid p-4">
    <!-- Page header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="fs-2 fw-bold mb-1"><i class="fas fa-user-check me-2"></i>My Tasks</h1>
        </div>
        <div class="d-flex gap-2">
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createTaskModal">
                <i class="fas fa-plus me-1"></i>Create Task
            </button>
            <a href="team-tasks.php" class="btn btn-outline-info">
                <i class="fas fa-users me-1"></i>Team Tasks
            </a>
            <a href="index.php" class="btn btn-outline-secondary">
                <i class="fas fa-tachometer-alt me-1"></i>Dashboard
            </a>
        </div>
    </div>

    <?php if (isset($error_message)): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-triangle me-2"></i>
            <?php echo $error_message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <!-- Filter Tabs -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white">
            <h3 class="card-title mb-0 fw-bold">
                <i class="fas fa-filter me-2 text-primary"></i>Filter Tasks
            </h3>
        </div>
        <div class="card-body">
            <div class="btn-group" role="group">
                <a href="my-tasks.php" class="btn btn-<?php echo $status_filter === 'all' ? 'primary' : 'outline-primary'; ?>">
                    <i class="fas fa-list me-1"></i>All Tasks
                </a>
                <a href="my-tasks.php?status=pending" class="btn btn-<?php echo $status_filter === 'pending' ? 'warning' : 'outline-warning'; ?>">
                    <i class="fas fa-clock me-1"></i>Pending
                </a>
                <a href="my-tasks.php?status=in_progress" class="btn btn-<?php echo $status_filter === 'in_progress' ? 'info' : 'outline-info'; ?>">
                    <i class="fas fa-spinner me-1"></i>In Progress
                </a>
                <a href="my-tasks.php?status=completed" class="btn btn-<?php echo $status_filter === 'completed' ? 'success' : 'outline-success'; ?>">
                    <i class="fas fa-check me-1"></i>Completed
                </a>
                <a href="my-tasks.php?status=on_hold" class="btn btn-<?php echo $status_filter === 'on_hold' ? 'secondary' : 'outline-secondary'; ?>">
                    <i class="fas fa-pause me-1"></i>On Hold
                </a>
            </div>
        </div>
    </div>

    <!-- Tasks List -->
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white">
            <div class="d-flex align-items-center justify-content-between">
                <h3 class="card-title mb-0 fw-bold">
                    <i class="fas fa-user-check me-2 text-primary"></i>
                    <?php echo ucfirst(str_replace('_', ' ', $status_filter)); ?> Tasks
                </h3>
                <span class="badge bg-light text-dark">
                    <?php echo count($tasks); ?> Tasks
                </span>
            </div>
        </div>
        <div class="card-body p-0">
            <?php if (empty($tasks)): ?>
                <div class="text-center py-5">
                    <div class="empty-state">
                        <i class="fas fa-tasks fa-4x text-muted mb-4"></i>
                        <h4 class="text-muted mb-3">No Tasks Found</h4>
                        <p class="text-muted mb-4">
                            <?php if ($status_filter === 'all'): ?>
                                You don't have any tasks assigned yet.
                            <?php else: ?>
                                You don't have any <?php echo str_replace('_', ' ', $status_filter); ?> tasks.
                            <?php endif; ?>
                        </p>
                        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createTaskModal">
                            <i class="fas fa-plus me-2"></i>Create Your First Task
                        </button>
                    </div>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0" id="myTasksTable">
                        <thead class="table-light">
                            <tr>
                                <th><i class="fas fa-tasks me-2"></i>Task Details</th>
                                <th><i class="fas fa-user-cog me-2"></i>Assigned By</th>
                                <th><i class="fas fa-flag me-2"></i>Priority</th>
                                <th><i class="fas fa-info-circle me-2"></i>Status</th>
                                <th><i class="fas fa-calendar me-2"></i>Due Date</th>
                                <th><i class="fas fa-chart-line me-2"></i>Progress</th>
                                <th><i class="fas fa-cogs me-2"></i>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($tasks as $task): ?>
                                <tr class="task-row">
                                    <td>
                                                <div class="task-info">
                                                    <h6 class="mb-1 fw-bold"><?php echo htmlspecialchars($task['title']); ?></h6>
                                                    <?php if (!empty($task['description'])): ?>
                                                        <small class="text-muted d-block">
                                                            <?php echo htmlspecialchars(substr($task['description'], 0, 100)) . (strlen($task['description']) > 100 ? '...' : ''); ?>
                                                        </small>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <div class="avatar-sm me-2">
                                                        <div class="avatar-title bg-secondary rounded-circle">
                                                            <?php echo strtoupper(substr($task['creator_first_name'], 0, 1)); ?>
                                                        </div>
                                                    </div>
                                                    <div>
                                                        <span class="fw-medium"><?php echo htmlspecialchars($task['creator_first_name'] . ' ' . $task['creator_last_name']); ?></span>
                                                        <small class="text-muted d-block">
                                                            <i class="fas fa-calendar-alt me-1"></i>
                                                            <?php echo date('M d, Y', strtotime($task['created_at'])); ?>
                                                        </small>
                                                    </div>
                                                </div>
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
                                                <?php if ($task['due_date']): ?>
                                                    <?php 
                                                    $due_date = date('M d, Y', strtotime($task['due_date']));
                                                    $is_overdue = strtotime($task['due_date']) < time() && $task['status'] !== 'completed';
                                                    ?>
                                                    <div class="d-flex align-items-center">
                                                        <i class="fas fa-calendar me-1 <?php echo $is_overdue ? 'text-danger' : 'text-muted'; ?>"></i>
                                                        <span class="<?php echo $is_overdue ? 'text-danger fw-bold' : ''; ?>">
                                                            <?php echo $due_date; ?>
                                                        </span>
                                                        <?php if ($is_overdue): ?>
                                                            <i class="fas fa-exclamation-triangle text-danger ms-1"></i>
                                                        <?php endif; ?>
                                                    </div>
                                                <?php else: ?>
                                                    <span class="text-muted">
                                                        <i class="fas fa-calendar-times me-1"></i>
                                                        Not set
                                                    </span>
                                                <?php endif; ?>
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
                                                <div class="btn-group" role="group">
                                                    <a href="view_task.php?id=<?php echo $task['id']; ?>" 
                                                       class="btn btn-sm btn-outline-primary" 
                                                       data-bs-toggle="tooltip" 
                                                       title="View Task Details">
                                                        <i class="fas fa-eye"></i>
                                                    </a>
                                                    <?php if ($task['status'] !== 'completed' && $task['status'] !== 'cancelled'): ?>
                                                        <button type="button" 
                                                                class="btn btn-sm btn-outline-warning" 
                                                                onclick="updateProgress(<?php echo $task['id']; ?>)" 
                                                                data-bs-toggle="tooltip" 
                                                                title="Update Progress">
                                                            <i class="fas fa-edit"></i>
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
        </div>

<!-- Progress Update Modal -->
<div class="modal fade" id="progressModal" tabindex="-1" aria-labelledby="progressModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="progressModalLabel">Update Task Progress</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="progressForm">
                <div class="modal-body">
                    <input type="hidden" id="taskId" name="task_id">
                    <div class="mb-3">
                        <label for="progress" class="form-label">Progress (%)</label>
                        <input type="range" class="form-range" min="0" max="100" id="progress" name="progress" oninput="updateProgressValue(this.value)">
                        <div class="d-flex justify-content-between">
                            <span>0%</span>
                            <span id="progressValue">50%</span>
                            <span>100%</span>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="status" class="form-label">Status</label>
                        <select class="form-select" id="status" name="status">
                            <option value="pending">Pending</option>
                            <option value="in_progress">In Progress</option>
                            <option value="completed">Completed</option>
                            <option value="on_hold">On Hold</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="comment" class="form-label">Comment (Optional)</label>
                        <textarea class="form-control" id="comment" name="comment" rows="3" placeholder="Add a comment about this update..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update Task</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function updateProgress(taskId) {
    $('#taskId').val(taskId);
    $('#progressModal').modal('show');
}

function updateProgressValue(value) {
    $('#progressValue').text(value + '%');
    
    // Auto-update status based on progress
    if (value == 0) {
        $('#status').val('pending');
    } else if (value == 100) {
        $('#status').val('completed');
    } else {
        $('#status').val('in_progress');
    }
}

$('#progressForm').submit(function(e) {
    e.preventDefault();
    
    $.ajax({
        url: 'update_progress.php',
        method: 'POST',
        data: $(this).serialize(),
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                location.reload();
            } else {
                alert('Error: ' + response.message);
            }
        },
        error: function() {
            alert('An error occurred while updating the task.');
        }
    });
});

// Initialize DataTable for better functionality
if ($.fn.DataTable) {
    $('#myTasksTable').DataTable({
        "responsive": true,
        "pageLength": 25,
        "order": [[0, "desc"]],
        "columnDefs": [
            { "orderable": false, "targets": [6] } // Actions column
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
</script>

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

.progress {
    border-radius: 10px;
}

.task-info h6 {
    color: #495057;
    margin-bottom: 0.25rem;
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

<?php 
// Include the create task modal
require_once 'create_task_modal.php';

require_once '../../includes/footer.php'; 
?>
