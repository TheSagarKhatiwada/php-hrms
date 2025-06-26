<?php
require_once '../../includes/session_config.php';
require_once '../../includes/db_connection.php';
require_once '../../includes/utilities.php';
require_once 'task_helpers.php';

$page = 'Create Task';

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

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $title = trim($_POST['title']);
        $description = trim($_POST['description']);
        $assigned_to = $_POST['assigned_to'];
        $priority = $_POST['priority'];
        $due_date = !empty($_POST['due_date']) ? $_POST['due_date'] : null;
        $category = trim($_POST['category']);
        
        // Validate required fields
        if (empty($title) || empty($assigned_to)) {
            throw new Exception("Title and assignee are required.");
        }
        
        // Create the task
        $stmt = $pdo->prepare("
            INSERT INTO tasks (title, description, assigned_by, assigned_to, priority, due_date, category, created_at) 
            VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        
        $stmt->execute([
            $title,
            $description,
            $current_user_id,
            $assigned_to,
            $priority,
            $due_date,
            $category
        ]);
        
        $task_id = $pdo->lastInsertId();
        
        // Add to task history
        $stmt = $pdo->prepare("
            INSERT INTO task_history (task_id, employee_id, action, new_value, created_at) 
            VALUES (?, ?, 'created', ?, NOW())
        ");
        $stmt->execute([$task_id, $current_user_id, "Task created and assigned"]);
        
        $_SESSION['success'] = "Task created successfully!";
        header("Location: view_task.php?id=" . $task_id);
        exit();
        
    } catch (Exception $e) {
        $error_message = $e->getMessage();
    }
}

// Get employees for assignment dropdown
try {
    $stmt = $pdo->prepare("
        SELECT emp_id, first_name, last_name, email,
               (SELECT title FROM designations WHERE id = employees.designation) as designation_title
        FROM employees 
        WHERE exit_date IS NULL 
        ORDER BY first_name, last_name
    ");
    $stmt->execute();
    $employees = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $employees = [];
}

require_once '../../includes/header.php';
?>

<!-- Main content -->
<div class="container-fluid p-4">
    <!-- Page header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="fs-2 fw-bold mb-1"><i class="fas fa-plus-circle me-2"></i>Create New Task</h1>
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

    <?php if (isset($error_message)): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-triangle me-2"></i><?php echo $error_message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <div class="row">
        <div class="col-md-8">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white">
                    <h3 class="card-title mb-0 fw-bold">
                        <i class="fas fa-edit me-2 text-primary"></i>Task Details
                    </h3>
                </div>
                        <form method="POST" enctype="multipart/form-data">
                            <div class="card-body">
                                <div class="form-group">
                                    <label for="title">Task Title <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="title" name="title" 
                                           value="<?php echo isset($_POST['title']) ? htmlspecialchars($_POST['title']) : ''; ?>" 
                                           required placeholder="Enter a clear, descriptive task title">
                                </div>

                                <div class="form-group">
                                    <label for="description">Description</label>
                                    <textarea class="form-control" id="description" name="description" rows="4" 
                                              placeholder="Provide detailed instructions and context for this task"><?php echo isset($_POST['description']) ? htmlspecialchars($_POST['description']) : ''; ?></textarea>
                                </div>

                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="assigned_to">Assign To <span class="text-danger">*</span></label>
                                            <select class="form-control" id="assigned_to" name="assigned_to" required>
                                                <option value="">Select an employee...</option>
                                                <?php foreach ($employees as $employee): ?>
                                                    <option value="<?php echo $employee['emp_id']; ?>" 
                                                            <?php echo (isset($_POST['assigned_to']) && $_POST['assigned_to'] == $employee['emp_id']) ? 'selected' : ''; ?>>
                                                        <?php echo htmlspecialchars($employee['first_name'] . ' ' . $employee['last_name']); ?>
                                                        <?php if ($employee['designation_title']): ?>
                                                            (<?php echo htmlspecialchars($employee['designation_title']); ?>)
                                                        <?php endif; ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="priority">Priority</label>
                                            <select class="form-control" id="priority" name="priority">
                                                <option value="low" <?php echo (isset($_POST['priority']) && $_POST['priority'] == 'low') ? 'selected' : ''; ?>>Low</option>
                                                <option value="medium" <?php echo (!isset($_POST['priority']) || $_POST['priority'] == 'medium') ? 'selected' : ''; ?>>Medium</option>
                                                <option value="high" <?php echo (isset($_POST['priority']) && $_POST['priority'] == 'high') ? 'selected' : ''; ?>>High</option>
                                                <option value="urgent" <?php echo (isset($_POST['priority']) && $_POST['priority'] == 'urgent') ? 'selected' : ''; ?>>Urgent</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="due_date">Due Date</label>
                                            <input type="date" class="form-control" id="due_date" name="due_date" 
                                                   value="<?php echo isset($_POST['due_date']) ? $_POST['due_date'] : ''; ?>"
                                                   min="<?php echo date('Y-m-d'); ?>">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="category">Category</label>
                                            <input type="text" class="form-control" id="category" name="category" 
                                                   value="<?php echo isset($_POST['category']) ? htmlspecialchars($_POST['category']) : ''; ?>" 
                                                   placeholder="e.g., Development, Marketing, HR">
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="card-footer">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-plus"></i> Create Task
                                </button>
                                <a href="index.php" class="btn btn-secondary">
                                    <i class="fas fa-times"></i> Cancel
                                </a>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="col-md-4">
                    <!-- Quick Tips -->
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">💡 Tips for Creating Tasks</h3>
                        </div>
                        <div class="card-body">
                            <div class="alert alert-info">
                                <h6><i class="fas fa-lightbulb"></i> Best Practices:</h6>
                                <ul class="mb-0">
                                    <li>Use clear, action-oriented titles</li>
                                    <li>Provide specific requirements</li>
                                    <li>Set realistic due dates</li>
                                    <li>Choose appropriate priority levels</li>
                                    <li>Include relevant context</li>
                                </ul>
                            </div>
                        </div>
                    </div>

                    <!-- Priority Guide -->
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">Priority Levels</h3>
                        </div>
                        <div class="card-body">
                            <div class="mb-2">
                                <span class="badge badge-danger">Urgent</span>
                                <small class="text-muted d-block">Critical issues, immediate attention required</small>
                            </div>
                            <div class="mb-2">
                                <span class="badge badge-warning">High</span>
                                <small class="text-muted d-block">Important tasks with tight deadlines</small>
                            </div>
                            <div class="mb-2">
                                <span class="badge badge-info">Medium</span>
                                <small class="text-muted d-block">Standard priority, normal workflow</small>
                            </div>
                            <div class="mb-2">
                                <span class="badge badge-secondary">Low</span>
                                <small class="text-muted d-block">Nice to have, flexible timing</small>
                            </div>
                        </div>
                    </div>

                    <!-- Quick Actions -->
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">Quick Actions</h3>
                        </div>
                        <div class="card-body">
                            <a href="my-tasks.php" class="btn btn-outline-primary btn-block mb-2">
                                <i class="fas fa-list"></i> View My Tasks
                            </a>
                            <a href="assigned-tasks.php" class="btn btn-outline-success btn-block mb-2">
                                <i class="fas fa-share"></i> Tasks I've Assigned
                            </a>
                            <a href="team-tasks.php" class="btn btn-outline-info btn-block">
                                <i class="fas fa-users"></i> Team Tasks
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

<script>
// Auto-suggest for category field
$('#category').on('input', function() {
    // You can implement auto-complete for categories here
});

// Form validation
$('form').on('submit', function(e) {
    const title = $('#title').val().trim();
    const assignedTo = $('#assigned_to').val();
    
    if (!title) {
        alert('Please enter a task title.');
        e.preventDefault();
        return false;
    }
    
    if (!assignedTo) {
        alert('Please select someone to assign this task to.');
        e.preventDefault();
        return false;
    }
});
</script>

<?php require_once '../../includes/footer.php'; ?>
