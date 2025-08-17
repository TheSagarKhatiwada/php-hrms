<?php
// Safe version of task creation handler with extensive logging
$log_file = __DIR__ . '/handler_debug.log';

function logMessage($message) {
    global $log_file;
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($log_file, "[$timestamp] $message\n", FILE_APPEND | LOCK_EX);
}

// Start logging
logMessage("Handler started");

try {
    // Initialize error handling
    ini_set('display_errors', 0);
    error_reporting(E_ALL);
    
    // Start session safely
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
        logMessage("Session started");
    }
    
    // Check if user is logged in
    if (!isset($_SESSION['user_id'])) {
        logMessage("No user_id in session, setting default");
        $_SESSION['user_id'] = 105; // For testing
    }
    
    logMessage("User ID: " . $_SESSION['user_id']);
    
    // Check request method
    $request_method = $_SERVER['REQUEST_METHOD'] ?? 'UNKNOWN';
    logMessage("Request method: $request_method");
    
    if ($request_method !== 'POST') {
        logMessage("Not a POST request, returning error");
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Invalid request method']);
        exit;
    }
    
    // Log POST data
    logMessage("POST data received: " . json_encode($_POST));
    
    // Include required files
    $includes_path = __DIR__ . '/../../includes/';
    require_once $includes_path . 'db_connection.php';
    logMessage("Database connection included");
    
    // Basic validation
    $required_fields = ['title', 'task_type', 'assigned_to', 'priority'];
    foreach ($required_fields as $field) {
        if (!isset($_POST[$field]) || empty(trim($_POST[$field]))) {
            logMessage("Missing required field: $field");
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => "Missing required field: $field"]);
            exit;
        }
    }
    
    // Extract data
    $title = trim($_POST['title']);
    $description = trim($_POST['description'] ?? '');
    $task_type = trim($_POST['task_type']);
    $assigned_to = trim($_POST['assigned_to']);
    $priority = trim($_POST['priority']);
    $due_date = !empty($_POST['due_date']) ? $_POST['due_date'] : null;
    $category = trim($_POST['category'] ?? '');
    $notes = trim($_POST['notes'] ?? '');
    $current_user_id = $_SESSION['user_id'];
    
    logMessage("Data extracted successfully");
    
    // Insert into database
    $sql = "INSERT INTO tasks (title, description, assigned_by, assigned_to, task_type, priority, due_date, category, created_at, updated_at) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())";
    
    $stmt = $pdo->prepare($sql);
    $result = $stmt->execute([
        $title,
        $description,
        $current_user_id,
        $assigned_to,
        $task_type,
        $priority,
        $due_date,
        $category
    ]);
    
    if ($result) {
        $task_id = $pdo->lastInsertId();
        logMessage("Task created successfully with ID: $task_id");
        
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'message' => 'Task created successfully!',
            'task_id' => $task_id
        ]);
    } else {
        logMessage("Failed to insert task into database");
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Failed to create task']);
    }
    
} catch (Exception $e) {
    logMessage("Exception caught: " . $e->getMessage());
    logMessage("Stack trace: " . $e->getTraceAsString());
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
} catch (Error $e) {
    logMessage("Fatal error caught: " . $e->getMessage());
    logMessage("Stack trace: " . $e->getTraceAsString());
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Fatal error: ' . $e->getMessage()]);
}

logMessage("Handler completed");
?>
