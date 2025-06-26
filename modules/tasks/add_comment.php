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
    $comment = trim($_POST['comment'] ?? '');
    
    if (!$taskId || !canAccessTask($pdo, $taskId, $current_user)) {
        $_SESSION['error'] = "Task not found or access denied.";
        header("Location: index.php");
        exit();
    }
    
    if (empty($comment)) {
        $_SESSION['error'] = "Comment cannot be empty.";
        header("Location: view_task.php?id=" . $taskId);
        exit();
    }
    
    try {
        $result = addTaskComment($pdo, $taskId, $current_user, $comment);
        
        if ($result) {
            $_SESSION['success'] = "Comment added successfully.";
        } else {
            $_SESSION['error'] = "Failed to add comment.";
        }
    } catch (Exception $e) {
        error_log("Error adding task comment: " . $e->getMessage());
        $_SESSION['error'] = "An error occurred while adding the comment.";
    }
    
    header("Location: view_task.php?id=" . $taskId);
    exit();
} else {
    header("Location: index.php");
    exit();
}
?>
