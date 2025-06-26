<?php
require_once '../../includes/session_config.php';
require_once '../../includes/db_connection.php';
require_once '../../includes/utilities.php';
require_once '../../includes/settings.php';
require_once 'task_helpers.php';

$page = 'Team Tasks';

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

// Get team tasks (tasks in the same department or supervised by current user)  
try {
    // Get team tasks including:
    // 1. Tasks assigned to team members
    // 2. Open tasks that anyone can assign
    // 3. Department-specific tasks
    // 4. Tasks created by the user (for monitoring)
    
    $stmt = $pdo->prepare("
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
        UNION ALL
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
            -- Assigned tasks in the team/department
            SELECT t.*, 
                   assignee.first_name as assignee_first_name, 
                   assignee.last_name as assignee_last_name,
                   creator.first_name as creator_first_name,
                   creator.last_name as creator_last_name,
                   'assigned' as task_status_type
            FROM tasks t
            LEFT JOIN employees assignee ON t.assigned_to = assignee.emp_id
            LEFT JOIN employees creator ON t.assigned_by = creator.emp_id
            WHERE (assignee.department_id = ? OR creator.emp_id = ?)
            AND t.assigned_to IS NOT NULL
        )
        ORDER BY created_at DESC
    ");
    $stmt->execute([$user['department_id'], $user['department_id'], $current_user_id]);
    $tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    $error_message = "Error loading team tasks: " . $e->getMessage();
    $tasks = [];
}

require_once '../../includes/header.php';
?>

<!-- Main content -->
<div class="container-fluid p-4">
    <!-- Page header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="fs-2 fw-bold mb-1"><i class="fas fa-users me-2"></i>Team Tasks</h1>
        </div>
        <div class="d-flex gap-2">
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createTaskModal">
                <i class="fas fa-plus me-1"></i>Create Task
            </button>
            <a href="my-tasks.php" class="btn btn-outline-info">
                <i class="fas fa-user me-1"></i>My Tasks
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

    <!-- Team Tasks Card -->
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white">
            <div class="d-flex align-items-center justify-content-between">
                <h3 class="card-title mb-0 fw-bold">
                    <i class="fas fa-users me-2 text-primary"></i>Team Tasks Overview
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
                        <i class="fas fa-users fa-4x text-muted mb-4"></i>
                        <h4 class="text-muted mb-3">No Team Tasks Found</h4>
                        <p class="text-muted mb-4">Tasks from your department or team members will appear here.</p>
                        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createTaskModal">
                            <i class="fas fa-plus me-2"></i>Create New Task
                        </button>
                    </div>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0" id="teamTasksTable">
                                <thead class="table-light">
                                    <tr>
                                        <th><i class="fas fa-tasks me-2"></i>Task Details</th>
                                        <th><i class="fas fa-user me-2"></i>Assigned To</th>
                                        <th><i class="fas fa-user-cog me-2"></i>Assigned By</th>
                                        <th><i class="fas fa-flag me-2"></i>Priority</th>
                                        <th><i class="fas fa-info-circle me-2"></i>Status</th>
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
                                                            <?php echo htmlspecialchars(substr($task['description'], 0, 80)) . '...'; ?>
                                                        </small>
                                                    <?php endif; ?>
                                                    <small class="text-info">
                                                        <i class="fas fa-calendar-alt me-1"></i>
                                                        <?php echo date('M d, Y', strtotime($task['created_at'])); ?>
                                                    </small>
                                                </div>
                                            </td>
                                            <td>
                                                <?php if ($task['task_status_type'] === 'available' || $task['task_status_type'] === 'open'): ?>
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
                                                            <div class="avatar-title bg-primary rounded-circle">
                                                                <?php echo strtoupper(substr($task['assignee_first_name'] ?? 'U', 0, 1)); ?>
                                                            </div>
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
                                                        <div class="avatar-title bg-secondary rounded-circle">
                                                            <?php echo strtoupper(substr($task['creator_first_name'], 0, 1)); ?>
                                                        </div>
                                                    </div>
                                                    <div>
                                                        <span class="fw-medium"><?php echo htmlspecialchars($task['creator_first_name'] . ' ' . $task['creator_last_name']); ?></span>
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
                                                    <?php if ($task['task_status_type'] === 'available' || $task['task_status_type'] === 'open'): ?>
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

<script>
$(document).ready(function() {
    // Initialize DataTable for better functionality
    if ($.fn.DataTable) {
        $('#teamTasksTable').DataTable({
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
