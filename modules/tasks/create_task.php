<?php
require_once '../../includes/session_config.php';
require_once '../../includes/db_connection.php';
require_once 'task_helpers.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $current_user = $_SESSION['user_id'] ?? ($_SESSION['emp_id'] ?? null);
    if (!$current_user) {
        $_SESSION['error'] = "Session expired. Please login again.";
        header("Location: ../../index.php");
        exit();
    }
    
    // Validate required fields
    $title = trim($_POST['title'] ?? '');
    $assigned_to = trim($_POST['assigned_to'] ?? '');
    
    if ($title === '' || $assigned_to === '') {
        $_SESSION['error'] = "Task title and assignee are required.";
        header("Location: tasks.php");
        exit();
    }
    
    // Validate assigned_to employee exists and is assignable
    $assignableEmployees = getAssignableEmployees($pdo, $current_user);
    $validAssignee = false;
    
    foreach ($assignableEmployees as $emp) {
        if ((string)$emp['emp_id'] === (string)$assigned_to) { $validAssignee = true; break; }
    }
    
    if (!$validAssignee) {
        $_SESSION['error'] = "Invalid assignee selected.";
        header("Location: tasks.php");
        exit();
    }
    
    // Prepare task data
    $taskData = [
        'title' => $title,
        'description' => trim($_POST['description'] ?? ''),
        'assigned_by' => $current_user,
        'assigned_to' => $assigned_to,
        'priority' => $_POST['priority'] ?? 'medium',
        'due_date' => !empty($_POST['due_date']) ? $_POST['due_date'] : null,
        'category' => trim($_POST['category'] ?? ''),
        'notes' => trim($_POST['notes'] ?? '')
    ];
    
    try {
        $taskId = createTask($pdo, $taskData);
        
        if ($taskId) {
            $_SESSION['success'] = "Task created successfully and assigned to " . 
                                 ($assigned_to == $current_user ? "yourself" : "the selected employee") . ".";
        } else {
            $_SESSION['error'] = "Failed to create task. Please try again.";
        }
    } catch (Exception $e) {
        error_log("Error creating task: " . $e->getMessage());
        $_SESSION['error'] = "An error occurred while creating the task.";
    }
    
    header("Location: tasks.php");
    exit();
} else {
    // Redirect if not POST request
    header("Location: tasks.php");
    exit();
}
?>
