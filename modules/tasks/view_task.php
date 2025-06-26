<?php
require_once '../../includes/session_config.php';
require_once '../../includes/db_connection.php';
require_once '../../includes/utilities.php';
require_once '../../includes/settings.php';
require_once 'task_helpers.php';

$page = 'Task Details';

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

$taskId = intval($_GET['id'] ?? 0);
if (!$taskId || !canAccessTask($pdo, $taskId, $current_user_id)) {
    $_SESSION['error'] = "Task not found or access denied.";
    header("Location: index.php");
    exit();
}

// Get task details
$stmt = $pdo->prepare("SELECT t.*, 
                             assignor.first_name as assignor_first, assignor.last_name as assignor_last,
                             assignee.first_name as assignee_first, assignee.last_name as assignee_last,
                             d_assignor.title as assignor_designation,
                             d_assignee.title as assignee_designation
                      FROM tasks t
                      LEFT JOIN employees assignor ON t.assigned_by = assignor.emp_id
                      LEFT JOIN employees assignee ON t.assigned_to = assignee.emp_id
                      LEFT JOIN designations d_assignor ON assignor.designation = d_assignor.id
                      LEFT JOIN designations d_assignee ON assignee.designation = d_assignee.id
                      WHERE t.id = :id");
$stmt->execute(['id' => $taskId]);
$task = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$task) {
    $_SESSION['error'] = "Task not found.";
    header("Location: index.php");
    exit();
}

// Get task comments
$comments = getTaskComments($pdo, $taskId);

// Get task history
$stmt = $pdo->prepare("SELECT th.*, e.first_name, e.last_name,
                              CONCAT(e.first_name, ' ', e.last_name) as employee_name
                       FROM task_history th
                       JOIN employees e ON th.employee_id = e.emp_id
                       WHERE th.task_id = :task_id
                       ORDER BY th.created_at DESC");
$stmt->execute(['task_id' => $taskId]);
$history = $stmt->fetchAll(PDO::FETCH_ASSOC);

require_once '../../includes/header.php';
?>

<style>
.timeline-wrapper .timeline-item:last-child .timeline-content {
    border-bottom: none;
}
.timeline-wrapper .timeline-item .timeline-content {
    border-bottom: 1px solid #f0f0f0;
    padding-bottom: 1rem;
}
.text-sm {
    font-size: 0.875rem;
}
/* Additional spacing for cards */
.card + .card {
    margin-top: 1.5rem;
}
.card-header.bg-primary .btn-outline-light:hover {
    background-color: rgba(255, 255, 255, 0.2);
    border-color: rgba(255, 255, 255, 0.3);
}
</style>

<!-- Main content -->
<div class="container-fluid p-4">
    <!-- Page header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="fs-2 fw-bold mb-1"><i class="fas fa-eye me-2"></i>Task Details</h1>
        </div>
        <div class="d-flex gap-2">
            <a href="index.php" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-1"></i>Back to Dashboard
            </a>
            <a href="my-tasks.php" class="btn btn-outline-info">
                <i class="fas fa-list me-1"></i>My Tasks
            </a>
        </div>
    </div>

    <!-- Main Content Row -->
    <div class="row">
        <!-- Task Details -->
        <div class="col-md-8">
            <!-- Task Information Card -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                    <h3 class="card-title mb-0">
                        <i class="fas fa-tasks me-2"></i><?php echo htmlspecialchars($task['title']); ?>
                    </h3>
                    <div class="d-flex gap-2">
                        <?php if ($task['assigned_to'] == $current_user_id): ?>
                        <button class="btn btn-light btn-sm" data-bs-toggle="modal" data-bs-target="#updateProgressModal">
                            <i class="fas fa-edit"></i> Update Progress
                        </button>
                        <?php endif; ?>
                        <a href="index.php" class="btn btn-outline-light btn-sm">
                            <i class="fas fa-arrow-left"></i> Back to Tasks
                        </a>
                    </div>
                </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <strong>Assigned To:</strong>
                                    <?php echo htmlspecialchars($task['assignee_first'] . ' ' . $task['assignee_last']); ?>
                                    <?php if ($task['assignee_designation']): ?>
                                        <br><small class="text-muted"><?php echo htmlspecialchars($task['assignee_designation']); ?></small>
                                    <?php endif; ?>
                                </div>
                                <div class="col-md-6">
                                    <strong>Assigned By:</strong>
                                    <?php echo htmlspecialchars($task['assignor_first'] . ' ' . $task['assignor_last']); ?>
                                    <?php if ($task['assignor_designation']): ?>
                                        <br><small class="text-muted"><?php echo htmlspecialchars($task['assignor_designation']); ?></small>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <hr>
                            
                            <div class="row">
                                <div class="col-md-3">
                                    <strong>Priority:</strong><br>
                                    <span class="badge badge-<?php 
                                        echo $task['priority'] === 'urgent' ? 'danger' : 
                                            ($task['priority'] === 'high' ? 'warning' : 
                                            ($task['priority'] === 'medium' ? 'info' : 'secondary')); 
                                    ?>">
                                        <?php echo ucfirst($task['priority']); ?>
                                    </span>
                                </div>
                                <div class="col-md-3">
                                    <strong>Status:</strong><br>
                                    <span class="badge badge-<?php 
                                        echo $task['status'] === 'completed' ? 'success' : 
                                            ($task['status'] === 'in_progress' ? 'primary' : 
                                            ($task['status'] === 'on_hold' ? 'warning' : 
                                            ($task['status'] === 'cancelled' ? 'danger' : 'secondary'))); 
                                    ?>">
                                        <?php echo str_replace('_', ' ', ucfirst($task['status'])); ?>
                                    </span>
                                </div>
                                <div class="col-md-3">
                                    <strong>Progress:</strong><br>
                                    <div class="progress progress-sm">
                                        <div class="progress-bar bg-<?php echo $task['progress'] == 100 ? 'success' : 'primary'; ?>" 
                                             style="width: <?php echo $task['progress']; ?>%"></div>
                                    </div>
                                    <small><?php echo $task['progress']; ?>%</small>
                                </div>
                                <div class="col-md-3">
                                    <strong>Due Date:</strong><br>
                                    <?php if ($task['due_date']): ?>
                                        <?php 
                                        $due_date = new DateTime($task['due_date']);
                                        $today = new DateTime();
                                        $is_overdue = $due_date < $today && !in_array($task['status'], ['completed', 'cancelled']);
                                        ?>
                                        <span class="<?php echo $is_overdue ? 'text-danger' : ''; ?>">
                                            <?php echo $due_date->format('M d, Y'); ?>
                                        </span>
                                        <?php if ($is_overdue): ?>
                                            <i class="fas fa-exclamation-triangle text-danger ml-1"></i>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="text-muted">No due date</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <?php if ($task['category']): ?>
                            <hr>
                            <strong>Category:</strong> <?php echo htmlspecialchars($task['category']); ?>
                            <?php endif; ?>
                            
                            <?php if ($task['description']): ?>
                            <hr>
                            <strong>Description:</strong>
                            <p><?php echo nl2br(htmlspecialchars($task['description'])); ?></p>
                            <?php endif; ?>
                            
                            <?php if ($task['notes']): ?>
                            <hr>
                            <strong>Notes:</strong>
                            <p><?php echo nl2br(htmlspecialchars($task['notes'])); ?></p>
                            <?php endif; ?>
                            
                            <hr>
                            <div class="row">
                                <div class="col-md-6">
                                    <strong>Created:</strong> <?php echo date('M d, Y \a\t h:i A', strtotime($task['created_at'])); ?>
                                </div>
                                <div class="col-md-6">
                                    <strong>Last Updated:</strong> <?php echo date('M d, Y \a\t h:i A', strtotime($task['updated_at'])); ?>
                                </div>
                            </div>
                            
                            <?php if ($task['completed_at']): ?>
                            <div class="row mt-2">
                                <div class="col-md-6">
                                    <strong>Completed:</strong> <?php echo date('M d, Y \a\t h:i A', strtotime($task['completed_at'])); ?>
                                </div>
                            </div>
                            <?php endif; ?>
                    </div>
                </div>

                <!-- Comments Section -->
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-info text-white">
                        <h3 class="card-title mb-0">
                            <i class="fas fa-comments me-2"></i>Comments (<?php echo count($comments); ?>)
                        </h3>
                    </div>
                        <div class="card-body">
                            <!-- Add Comment Form -->
                            <form action="add_comment.php" method="POST" class="mb-4">
                                <input type="hidden" name="task_id" value="<?php echo $taskId; ?>">
                                <div class="form-group">
                                    <label for="comment">Add Comment</label>
                                    <textarea class="form-control" id="comment" name="comment" rows="3" placeholder="Add your comment here..." required></textarea>
                                </div>
                                <button type="submit" class="btn btn-primary btn-sm">
                                    <i class="fas fa-paper-plane"></i> Add Comment
                                </button>
                            </form>
                            
                            <!-- Comments List -->
                            <?php if (empty($comments)): ?>
                            <p class="text-muted">No comments yet.</p>
                            <?php else: ?>
                            <?php foreach ($comments as $comment): ?>
                            <div class="comment mb-3 p-3 rounded">
                                <div class="comment-header d-flex justify-content-between">
                                    <strong><?php echo htmlspecialchars($comment['commenter_name']); ?></strong>
                                    <small class="text-muted"><?php echo date('M d, Y \a\t h:i A', strtotime($comment['created_at'])); ?></small>
                                </div>
                                <div class="comment-body mt-2">
                                    <?php echo nl2br(htmlspecialchars($comment['comment'])); ?>
                                </div>
                            </div>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Sidebar -->
                <div class="col-md-4">
                    <!-- Task History -->
                    <div class="card border-0 shadow-sm">
                        <div class="card-header bg-secondary text-white">
                            <h5 class="card-title mb-0 fw-bold">
                                <i class="fas fa-history me-2"></i>Task History
                            </h5>
                        </div>
                        <div class="card-body p-3">
                            <?php if (empty($history)): ?>
                                <div class="text-center py-4">
                                    <i class="fas fa-history fa-2x text-muted mb-2"></i>
                                    <p class="text-muted mb-0">No history available</p>
                                </div>
                            <?php else: ?>
                                <div class="timeline-wrapper">
                                    <?php foreach ($history as $entry): ?>
                                        <div class="timeline-item mb-3">
                                            <div class="d-flex">
                                                <div class="timeline-icon me-3">
                                                    <div class="rounded-circle bg-primary d-flex align-items-center justify-content-center" style="width: 32px; height: 32px;">
                                                        <i class="fas fa-<?php 
                                                            echo $entry['action'] === 'created' ? 'plus' : 
                                                                ($entry['action'] === 'status_changed' ? 'exchange-alt' : 
                                                                ($entry['action'] === 'progress_updated' ? 'chart-line' : 'edit')); 
                                                        ?> text-white fa-sm"></i>
                                                    </div>
                                                </div>
                                                <div class="timeline-content flex-grow-1">
                                                    <div class="d-flex justify-content-between align-items-start mb-1">
                                                        <span class="fw-semibold text-sm">
                                                            <?php echo htmlspecialchars($entry['employee_name']); ?>
                                                        </span>
                                                        <small class="text-muted">
                                                            <?php echo date('M j, g:i A', strtotime($entry['created_at'])); ?>
                                                        </small>
                                                    </div>
                                                    <div class="text-sm text-muted">
                                                        <?php 
                                                        $action_text = str_replace('_', ' ', $entry['action']);
                                                        echo ucfirst($action_text);
                                                        if ($entry['old_value'] && $entry['new_value']) {
                                                            echo ": " . htmlspecialchars($entry['old_value']) . " → " . htmlspecialchars($entry['new_value']);
                                                        } elseif ($entry['new_value']) {
                                                            echo ": " . htmlspecialchars($entry['new_value']);
                                                        }
                                                        ?>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Update Progress Modal -->
<?php if ($task['assigned_to'] == $current_user_id): ?>
<div class="modal fade" id="updateProgressModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h4 class="modal-title">Update Task Progress</h4>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="update_progress.php" method="POST">
                <input type="hidden" name="task_id" value="<?php echo $taskId; ?>">
                <div class="modal-body">
                    <div class="form-group">
                        <label for="progress">Progress (%)</label>
                        <input type="range" class="form-control-range" id="progress" name="progress" 
                               min="0" max="100" value="<?php echo $task['progress']; ?>" 
                               oninput="document.getElementById('progressValue').textContent = this.value + '%'">
                        <div class="text-center mt-2">
                            <span id="progressValue"><?php echo $task['progress']; ?>%</span>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="status">Status</label>
                        <select class="form-control" id="status" name="status">
                            <option value="pending" <?php echo $task['status'] === 'pending' ? 'selected' : ''; ?>>Pending</option>
                            <option value="in_progress" <?php echo $task['status'] === 'in_progress' ? 'selected' : ''; ?>>In Progress</option>
                            <option value="completed" <?php echo $task['status'] === 'completed' ? 'selected' : ''; ?>>Completed</option>
                            <option value="on_hold" <?php echo $task['status'] === 'on_hold' ? 'selected' : ''; ?>>On Hold</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="update_notes">Update Notes</label>
                        <textarea class="form-control" id="update_notes" name="update_notes" rows="3" 
                                  placeholder="Optional notes about this update..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update Progress</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
// Auto-set status based on progress
document.getElementById('progress').addEventListener('input', function() {
    const progress = parseInt(this.value);
    const statusSelect = document.getElementById('status');
    
    if (progress === 0) {
        statusSelect.value = 'pending';
    } else if (progress === 100) {
        statusSelect.value = 'completed';
    } else if (progress > 0) {
        statusSelect.value = 'in_progress';
    }
});
</script>

<?php include '../../includes/footer.php'; ?>
