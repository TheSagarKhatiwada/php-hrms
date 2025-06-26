<?php
// Check if modal HTML is being generated correctly
require_once '../../includes/session_config.php';
require_once '../../includes/db_connection.php';
require_once 'task_helpers.php';

// Mock user data
$_SESSION['user_id'] = 1;
$current_user_id = 1;

$stmt = $pdo->prepare("SELECT * FROM employees WHERE emp_id = ?");
$stmt->execute([$current_user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// Get assignable employees
try {
    $assignable_employees = getAssignableEmployees($pdo, $current_user_id);
} catch (Exception $e) {
    $assignable_employees = [];
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Modal Test</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <div class="container mt-5">
        <h1>Modal HTML Test</h1>
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createTaskModal">
            Open Modal
        </button>
        
        <div class="mt-4">
            <h3>Debug Info:</h3>
            <p>User ID: <?php echo $current_user_id; ?></p>
            <p>User Name: <?php echo $user ? $user['first_name'] . ' ' . $user['last_name'] : 'Not found'; ?></p>
            <p>Assignable Employees: <?php echo count($assignable_employees); ?></p>
        </div>
    </div>

    <?php require_once 'create_task_modal.php'; ?>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
    // Additional debugging
    $(document).ready(function() {
        setTimeout(function() {
            console.log('=== MODAL DEBUG INFO ===');
            console.log('Modal exists:', $('#createTaskModal').length > 0);
            console.log('Form exists:', $('#createTaskForm').length > 0);
            console.log('Title input exists:', $('#task_title').length > 0);
            console.log('Assigned to select exists:', $('#task_assigned_to').length > 0);
            console.log('Button exists:', $('#createTaskBtn').length > 0);
            
            if ($('#task_title').length > 0) {
                console.log('Title input HTML:', $('#task_title')[0].outerHTML);
            }
            
            if ($('#task_assigned_to').length > 0) {
                console.log('Assigned to options:', $('#task_assigned_to option').length);
            }
        }, 1000);
    });
    </script>
</body>
</html>
