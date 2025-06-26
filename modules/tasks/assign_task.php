<?php
require_once '../../includes/session_config.php';
require_once '../../includes/db_connection.php';
require_once 'task_helpers.php';

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

$current_user_id = $_SESSION['user_id'];

// Handle POST request for task assignment
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $task_id = intval($_POST['task_id'] ?? 0);
        $action = $_POST['action'] ?? '';
        
        if ($task_id <= 0) {
            throw new Exception("Invalid task ID");
        }
        
        // Get task details
        $stmt = $pdo->prepare("
            SELECT t.*, e.department_id, e.role_id 
            FROM tasks t
            LEFT JOIN employees e ON e.emp_id = ?
            WHERE t.id = ?
        ");
        $stmt->execute([$current_user_id, $task_id]);
        $task = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$task) {
            throw new Exception("Task not found");
        }
        
        // Check if user can assign this task to themselves
        $can_assign = false;
        $message = '';
        
        switch ($task['task_type']) {
            case 'open':
                // Anyone can assign open tasks to themselves
                $can_assign = true;
                break;
                
            case 'department':
                // Only department members can assign department tasks
                if ($task['target_department_id'] == $task['department_id']) {
                    $can_assign = true;
                }
                break;
                
            case 'assigned':
                // Already assigned tasks cannot be self-assigned
                break;
        }
        
        if (!$can_assign) {
            throw new Exception("You cannot assign this task to yourself");
        }
        
        // Check if task is already assigned
        if ($task['assigned_to'] !== null) {
            throw new Exception("This task is already assigned to someone else");
        }
        
        if ($action === 'assign') {
            // Begin transaction
            $pdo->beginTransaction();
            
            // Check if self_assigned_at column exists
            $stmt = $pdo->prepare("SHOW COLUMNS FROM tasks LIKE 'self_assigned_at'");
            $stmt->execute();
            $has_self_assigned_column = $stmt->rowCount() > 0;
            
            // Assign task to user
            if ($has_self_assigned_column) {
                $stmt = $pdo->prepare("
                    UPDATE tasks 
                    SET assigned_to = ?, self_assigned_at = NOW(), status = 'in_progress', updated_at = NOW()
                    WHERE id = ?
                ");
            } else {
                $stmt = $pdo->prepare("
                    UPDATE tasks 
                    SET assigned_to = ?, status = 'in_progress', updated_at = NOW()
                    WHERE id = ?
                ");
            }
            $stmt->execute([$current_user_id, $task_id]);
            
            // Check if task_history table exists and add history entry
            try {
                $stmt = $pdo->prepare("SHOW TABLES LIKE 'task_history'");
                $stmt->execute();
                $has_history_table = $stmt->rowCount() > 0;
                
                if ($has_history_table) {
                    $stmt = $pdo->prepare("
                        INSERT INTO task_history (task_id, employee_id, action, new_value, created_at) 
                        VALUES (?, ?, 'self_assigned', 'Task self-assigned', NOW())
                    ");
                    $stmt->execute([$task_id, $current_user_id]);
                }
            } catch (Exception $e) {
                // Continue even if history table doesn't exist or has issues
            }
            
            // Commit transaction
            $pdo->commit();
            
            echo json_encode([
                'success' => true,
                'message' => 'Task successfully assigned to you!',
                'task_id' => $task_id
            ]);
            
        } elseif ($action === 'unassign') {
            // Allow unassigning only if user assigned it to themselves
            if ($task['assigned_to'] != $current_user_id) {
                throw new Exception("You can only unassign tasks that you assigned to yourself");
            }
            
            // Begin transaction
            $pdo->beginTransaction();
            
            // Check if self_assigned_at column exists
            $stmt = $pdo->prepare("SHOW COLUMNS FROM tasks LIKE 'self_assigned_at'");
            $stmt->execute();
            $has_self_assigned_column = $stmt->rowCount() > 0;
            
            // Unassign task
            if ($has_self_assigned_column) {
                $stmt = $pdo->prepare("
                    UPDATE tasks 
                    SET assigned_to = NULL, self_assigned_at = NULL, status = 'pending', progress = 0, updated_at = NOW()
                    WHERE id = ?
                ");
            } else {
                $stmt = $pdo->prepare("
                    UPDATE tasks 
                    SET assigned_to = NULL, status = 'pending', progress = 0, updated_at = NOW()
                    WHERE id = ?
                ");
            }
            $stmt->execute([$task_id]);
            
            // Add to task history if table exists
            try {
                $stmt = $pdo->prepare("SHOW TABLES LIKE 'task_history'");
                $stmt->execute();
                $has_history_table = $stmt->rowCount() > 0;
                
                if ($has_history_table) {
                    $stmt = $pdo->prepare("
                        INSERT INTO task_history (task_id, employee_id, action, new_value, created_at) 
                        VALUES (?, ?, 'unassigned', 'Task unassigned by user', NOW())
                    ");
                    $stmt->execute([$task_id, $current_user_id]);
                }
            } catch (Exception $e) {
                // Continue even if history table doesn't exist or has issues
            }
            
            // Commit transaction
            $pdo->commit();
            
            echo json_encode([
                'success' => true,
                'message' => 'Task unassigned successfully!',
                'task_id' => $task_id
            ]);
            
        } else {
            throw new Exception("Invalid action");
        }
        
    } catch (Exception $e) {
        // Rollback transaction on error
        if ($pdo->inTransaction()) {
            $pdo->rollback();
        }
        
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}
?>
