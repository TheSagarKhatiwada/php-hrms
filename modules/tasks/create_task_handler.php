<?php
// Suppress all errors/warnings that might interfere with JSON output
error_reporting(0);
ini_set('display_errors', 0);

// Start output buffering to catch any unwanted output
ob_start();

require_once '../../includes/session_config.php';
require_once '../../includes/db_connection.php';
require_once 'task_helpers.php';

// Clean any output that might have been generated
ob_clean();

header('Content-Type: application/json');

// Add comprehensive debug logging
$debug = isset($_POST['debug']) && $_POST['debug'] == '1';
if ($debug) {
    error_log("=== TASK CREATION DEBUG START ===");
    error_log("Session data: " . print_r($_SESSION, true));
    error_log("POST data: " . print_r($_POST, true));
    error_log("Current user_id: " . ($_SESSION['user_id'] ?? 'NOT SET'));
}

// Check if user is logged in
if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
    ob_clean();
    $response = ['success' => false, 'message' => 'Session expired. Please log in again.'];
    if ($debug) error_log("ERROR: No valid user session");
    echo json_encode($response);
    exit();
}

$current_user_id = $_SESSION['user_id'];

// Verify user exists in database
try {
    $stmt = $pdo->prepare("SELECT emp_id, first_name, last_name FROM employees WHERE emp_id = ?");
    $stmt->execute([$current_user_id]);
    $user_check = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user_check) {
        ob_clean();
        $response = ['success' => false, 'message' => 'Invalid user account. Please contact administrator.'];
        if ($debug) error_log("ERROR: User ID {$current_user_id} not found in database");
        echo json_encode($response);
        exit();
    }
    
    if ($debug) error_log("✓ User verified: " . $user_check['first_name'] . ' ' . $user_check['last_name']);
    
} catch (Exception $e) {
    ob_clean();
    $response = ['success' => false, 'message' => 'Database error during user verification: ' . $e->getMessage()];
    if ($debug) error_log("ERROR: Database error during user check: " . $e->getMessage());
    echo json_encode($response);
    exit();
}

// Handle POST request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Validate required fields
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $task_type = $_POST['task_type'] ?? 'assigned';
        $assigned_to = $_POST['assigned_to'] ?? null;
        $priority = $_POST['priority'] ?? 'medium';
        $due_date = !empty($_POST['due_date']) ? $_POST['due_date'] : null;
        $category = trim($_POST['category'] ?? '');
        $notes = trim($_POST['notes'] ?? '');
        
        if (empty($title)) {
            throw new Exception("Task title is required");
        }
        
        // Validate task type
        $valid_task_types = ['assigned', 'open', 'department'];
        if (!in_array($task_type, $valid_task_types)) {
            $task_type = 'assigned';
        }
        
        // For assigned tasks, validate assignee
        if ($task_type === 'assigned') {
            if (empty($assigned_to)) {
                throw new Exception("Please select who to assign this task to");
            }
            
            // Validate that the assigned_to user exists and is assignable
            try {
                $assignable_employees = getAssignableEmployees($pdo, $current_user_id);
                if ($debug) error_log("✓ Got " . count($assignable_employees) . " assignable employees");
            } catch (Exception $e) {
                if ($debug) error_log("ERROR: getAssignableEmployees failed: " . $e->getMessage());
                throw new Exception("Error validating assignee: " . $e->getMessage());
            }
            
            $can_assign = ($assigned_to == $current_user_id); // Can always assign to self
            
            if (!$can_assign) {
                foreach ($assignable_employees as $emp) {
                    if ($emp['emp_id'] == $assigned_to) {
                        $can_assign = true;
                        break;
                    }
                }
            }
            
            if (!$can_assign) {
                throw new Exception("You cannot assign tasks to this employee");
            }
        } else {
            // For open and department tasks, assigned_to should be null initially
            $assigned_to = null;
        }
        
        // Get user's department for department tasks
        $target_department_id = null;
        if ($task_type === 'department') {
            $stmt = $pdo->prepare("SELECT department_id FROM employees WHERE emp_id = ?");
            $stmt->execute([$current_user_id]);
            $user_dept = $stmt->fetch(PDO::FETCH_ASSOC);
            $target_department_id = $user_dept['department_id'] ?? null;
        }
        
        // Validate priority
        $valid_priorities = ['low', 'medium', 'high', 'urgent'];
        if (!in_array($priority, $valid_priorities)) {
            $priority = 'medium';
        }
        
        // Validate due date
        if ($due_date && strtotime($due_date) < strtotime('today')) {
            throw new Exception("Due date cannot be in the past");
        }
        
        if ($debug) error_log("✓ Validation completed, starting task creation");
        
        // Begin transaction
        $pdo->beginTransaction();
        if ($debug) error_log("✓ Transaction started");
        
        // Create the task
        if ($debug) error_log("✓ Preparing INSERT statement");
        $stmt = $pdo->prepare("
            INSERT INTO tasks (title, description, assigned_by, assigned_to, task_type, target_department_id, priority, due_date, category, status, progress, created_at) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', 0, NOW())
        ");
        
        if ($debug) {
            error_log("✓ INSERT statement prepared");
            error_log("Data to insert: " . print_r([
                $title,
                $description,
                $current_user_id,
                $assigned_to,
                $task_type,
                $target_department_id,
                $priority,
                $due_date,
                $category
            ], true));
        }
        
        $stmt->execute([
            $title,
            $description,
            $current_user_id,
            $assigned_to,
            $task_type,
            $target_department_id,
            $priority,
            $due_date,
            $category
        ]);
        
        if ($debug) error_log("✓ INSERT executed successfully");
        
        $task_id = $pdo->lastInsertId();
        if ($debug) error_log("✓ Task created with ID: " . $task_id);
        
        // Commit transaction
        $pdo->commit();
        if ($debug) error_log("✓ Transaction committed successfully");
        
        // Clean any buffered output before sending JSON
        ob_clean();
        
        $response = [
            'success' => true,
            'message' => 'Task created successfully!',
            'task_id' => $task_id,
            'redirect' => 'view_task.php?id=' . $task_id
        ];
        
        if ($debug) error_log("✓ Response prepared: " . json_encode($response));
        
        echo json_encode($response);
        if ($debug) error_log("✓ JSON response sent successfully");
        
        // Ensure clean exit
        exit();
        
    } catch (Exception $e) {
        // Rollback transaction on error
        if ($pdo->inTransaction()) {
            $pdo->rollback();
        }
        
        // Log detailed error information
        error_log("=== TASK CREATION ERROR ===");
        error_log("Error message: " . $e->getMessage());
        error_log("Error file: " . $e->getFile());
        error_log("Error line: " . $e->getLine());
        error_log("Stack trace: " . $e->getTraceAsString());
        error_log("POST data: " . print_r($_POST, true));
        
        // Clean output buffer before sending error response
        if (ob_get_level()) {
            ob_clean();
        }
        
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage(),
            'error_details' => [
                'file' => basename($e->getFile()),
                'line' => $e->getLine(),
                'message' => $e->getMessage()
            ]
        ]);
        exit();
    }
} else {
    ob_clean();
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit();
}
