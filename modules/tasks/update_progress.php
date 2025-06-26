<?php
require_once '../../includes/session_config.php';
require_once '../../includes/db_connection.php';
require_once 'task_helpers.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $current_user = $_SESSION['user_id'] ?? null;
    if (!$current_user) {
        $_SESSION['error'] = "Session expired. Please login again.";
        header("Location: ../../index.php");
        exit();
    }
    
    $taskId = intval($_POST['task_id'] ?? 0);
    if (!$taskId || !canAccessTask($pdo, $taskId, $current_user)) {
        $_SESSION['error'] = "Task not found or access denied.";
        header("Location: index.php");
        exit();
    }
    
    $progress = intval($_POST['progress'] ?? 0);
    $status = $_POST['status'] ?? null;
    $notes = trim($_POST['update_notes'] ?? '');
    
    // Validate progress
    if ($progress < 0 || $progress > 100) {
        $_SESSION['error'] = "Progress must be between 0 and 100.";
        header("Location: view_task.php?id=" . $taskId);
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
    
    try {
        $result = updateTaskProgress($pdo, $taskId, $current_user, $progress, $status, $notes);
        
        if ($result) {
            $_SESSION['success'] = "Task progress updated successfully.";
        } else {
            $_SESSION['error'] = "Failed to update task progress.";
        }
    } catch (Exception $e) {
        error_log("Error updating task progress: " . $e->getMessage());
        $_SESSION['error'] = "An error occurred while updating the task.";
    }
    
    header("Location: view_task.php?id=" . $taskId);
    exit();
} else {
    header("Location: index.php");
    exit();
}
?>
