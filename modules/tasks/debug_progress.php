<?php
require_once '../../includes/session_config.php';
require_once '../../includes/db_connection.php';
require_once 'task_helpers.php';

echo "<h2>Debug: Progress Update Test</h2>";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    echo "<h3>POST Data Received:</h3>";
    echo "<pre>" . print_r($_POST, true) . "</pre>";
    
    echo "<h3>Session Data:</h3>";
    echo "<pre>" . print_r($_SESSION, true) . "</pre>";
    
    $current_user = $_SESSION['user_id'] ?? null;
    echo "<p>Current User ID: " . ($current_user ?? 'NULL') . "</p>";
    
    if (!$current_user) {
        echo "<p style='color: red;'>ERROR: No user in session</p>";
        exit();
    }
    
    $taskId = intval($_POST['task_id'] ?? 0);
    echo "<p>Task ID: " . $taskId . "</p>";
    
    if (!$taskId) {
        echo "<p style='color: red;'>ERROR: Invalid task ID</p>";
        exit();
    }
    
    // Check if task exists
    $stmt = $pdo->prepare("SELECT * FROM tasks WHERE id = ?");
    $stmt->execute([$taskId]);
    $task = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$task) {
        echo "<p style='color: red;'>ERROR: Task not found</p>";
        exit();
    }
    
    echo "<h3>Task Data:</h3>";
    echo "<pre>" . print_r($task, true) . "</pre>";
    
    // Check access
    $canAccess = canAccessTask($pdo, $taskId, $current_user);
    echo "<p>Can Access Task: " . ($canAccess ? 'YES' : 'NO') . "</p>";
    
    if (!$canAccess) {
        echo "<p style='color: red;'>ERROR: Access denied</p>";
        exit();
    }
    
    $progress = intval($_POST['progress'] ?? 0);
    $status = $_POST['status'] ?? null;
    $notes = trim($_POST['update_notes'] ?? '');
    
    echo "<p>Progress: " . $progress . "%</p>";
    echo "<p>Status: " . ($status ?? 'NULL') . "</p>";
    echo "<p>Notes: " . ($notes ?? 'NULL') . "</p>";
    
    // Validate progress
    if ($progress < 0 || $progress > 100) {
        echo "<p style='color: red;'>ERROR: Invalid progress value</p>";
        exit();
    }
    
    // Auto-set status based on progress if not explicitly set
    if (!$status) {
        if ($progress === 0) {
            $status = 'pending';
        } elseif ($progress === 100) {
            $status = 'completed';
        } else {
            $status = 'in_progress';
        }
    }
    
    echo "<p>Final Status: " . $status . "</p>";
    
    try {
        echo "<h3>Attempting to update task...</h3>";
        $result = updateTaskProgress($pdo, $taskId, $current_user, $progress, $status, $notes);
        
        if ($result) {
            echo "<p style='color: green;'>SUCCESS: Task progress updated successfully.</p>";
        } else {
            echo "<p style='color: red;'>ERROR: Failed to update task progress.</p>";
        }
    } catch (Exception $e) {
        echo "<p style='color: red;'>EXCEPTION: " . $e->getMessage() . "</p>";
        echo "<pre>" . $e->getTraceAsString() . "</pre>";
    }
    
} else {
    echo "<p>This is a debug page for testing progress updates. Submit a POST request to test.</p>";
    echo "<form method='POST'>";
    echo "<input type='hidden' name='task_id' value='1'>";
    echo "<label>Progress: <input type='number' name='progress' value='50' min='0' max='100'></label><br><br>";
    echo "<label>Status: <select name='status'>";
    echo "<option value='pending'>Pending</option>";
    echo "<option value='in_progress' selected>In Progress</option>";
    echo "<option value='completed'>Completed</option>";
    echo "<option value='on_hold'>On Hold</option>";
    echo "</select></label><br><br>";
    echo "<label>Notes: <textarea name='update_notes'>Test update</textarea></label><br><br>";
    echo "<button type='submit'>Test Update</button>";
    echo "</form>";
}
?>
