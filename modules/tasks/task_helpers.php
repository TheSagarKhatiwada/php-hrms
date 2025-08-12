<?php
/**
 * Task Management Helper Functions
 */

require_once __DIR__ . '/../../includes/db_connection.php';
require_once __DIR__ . '/../../includes/hierarchy_helpers.php';

/**
 * Get priority color for badges
 */
function getPriorityColor($priority) {
    switch ($priority) {
        case 'urgent':
            return 'danger';
        case 'high':
            return 'warning';
        case 'medium':
            return 'info';
        case 'low':
            return 'secondary';
        default:
            return 'secondary';
    }
}

/**
 * Get status color for badges
 */
function getStatusColor($status) {
    switch ($status) {
        case 'completed':
            return 'success';
        case 'in_progress':
            return 'primary';
        case 'on_hold':
            return 'warning';
        case 'cancelled':
            return 'danger';
        case 'pending':
        default:
            return 'secondary';
    }
}

/**
 * Get tasks assigned to or by a specific employee
 */
function getTasks($pdo, $employeeId, $filter = 'all', $limit = null) {
    $sql = "SELECT t.*, 
                   assignor.first_name as assignor_first, assignor.last_name as assignor_last,
                   assignee.first_name as assignee_first, assignee.last_name as assignee_last,
                   d_assignor.title as assignor_designation,
                   d_assignee.title as assignee_designation
            FROM tasks t
            LEFT JOIN employees assignor ON t.assigned_by = assignor.emp_id
            LEFT JOIN employees assignee ON t.assigned_to = assignee.emp_id
            LEFT JOIN designations d_assignor ON assignor.designation = d_assignor.id
            LEFT JOIN designations d_assignee ON assignee.designation = d_assignee.id
            WHERE ";
    
    $params = [];
    
    switch ($filter) {
        case 'assigned_to_me':
            $sql .= "t.assigned_to = :employee_id";
            $params['employee_id'] = $employeeId;
            break;
        case 'assigned_by_me':
            $sql .= "t.assigned_by = :employee_id";
            $params['employee_id'] = $employeeId;
            break;
        case 'my_subordinates':
            $subordinates = getSubordinates($pdo, $employeeId);
            if (empty($subordinates)) {
                return [];
            }
            $placeholders = str_repeat('?,', count($subordinates) - 1) . '?';
            $sql .= "t.assigned_to IN ($placeholders)";
            $params = $subordinates;
            break;
        default: // 'all'
            $sql .= "(t.assigned_to = :employee_id OR t.assigned_by = :employee_id)";
            $params['employee_id'] = $employeeId;
    }
    
    $sql .= " ORDER BY t.created_at DESC";
    
    if ($limit) {
        $sql .= " LIMIT " . intval($limit);
    }
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Get employees that current user can assign tasks to
 */
function getAssignableEmployees($pdo, $currentEmployeeId) {
    try {
        // Get current employee details
        $stmt = $pdo->prepare("SELECT e.*, d.title as designation_title FROM employees e 
                              LEFT JOIN designations d ON e.designation = d.id 
                              WHERE e.emp_id = ?");
        $stmt->execute([$currentEmployeeId]);
        $currentEmployee = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$currentEmployee) {
            return [];
        }
        
        // Get all eligible employees (active employees excluding current user)
        $stmt = $pdo->prepare("
            SELECT e.emp_id, e.first_name, e.last_name, e.department_id, e.supervisor_id,
                   d.title as designation_title,
                   CASE 
                       WHEN e.supervisor_id = ? THEN 1 
                       ELSE 0 
                   END as is_subordinate
            FROM employees e 
            LEFT JOIN designations d ON e.designation = d.id
            WHERE e.emp_id != ? 
            AND e.exit_date IS NULL
            ORDER BY 
                CASE 
                    WHEN e.supervisor_id = ? THEN 1 
                    WHEN e.department_id = ? THEN 2 
                    ELSE 3 
                END,
                e.first_name, e.last_name
        ");
        
        $stmt->execute([
            $currentEmployeeId, 
            $currentEmployeeId, 
            $currentEmployeeId, 
            $currentEmployee['department_id']
        ]);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
        
    } catch (Exception $e) {
        return [];
    }
}

/**
 * Create a new task
 */
function createTask($pdo, $data) {
    $sql = "INSERT INTO tasks (title, description, assigned_by, assigned_to, priority, 
                              due_date, category, notes) 
            VALUES (:title, :description, :assigned_by, :assigned_to, :priority, 
                    :due_date, :category, :notes)";
    
    $stmt = $pdo->prepare($sql);
    $result = $stmt->execute([
        'title' => $data['title'],
        'description' => $data['description'],
        'assigned_by' => $data['assigned_by'],
        'assigned_to' => $data['assigned_to'],
        'priority' => $data['priority'],
        'due_date' => $data['due_date'],
        'category' => $data['category'] ?? null,
        'notes' => $data['notes'] ?? null
    ]);
    
    if ($result) {
        $taskId = $pdo->lastInsertId();
        
        // Add to history
        addTaskHistory($pdo, $taskId, $data['assigned_by'], 'created', null, 'Task created');
        
        // Send notification
        require_once __DIR__ . '/../../includes/notification_helpers.php';
        if ($data['assigned_to'] != $data['assigned_by']) {
            notify_employee($data['assigned_to'], 'task_assigned', [
                'task_id' => $taskId,
                'task_title' => $data['title'],
                'assigned_by' => $data['assigned_by']
            ]);
        }
        
        return $taskId;
    }
    
    return false;
}

/**
 * Update task progress and status
 */
function updateTaskProgress($pdo, $taskId, $employeeId, $progress, $status = null, $notes = null) {
    // Get current task
    $stmt = $pdo->prepare("SELECT * FROM tasks WHERE id = :id");
    $stmt->execute(['id' => $taskId]);
    $task = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$task) {
        return false;
    }
    
    $sql = "UPDATE tasks SET progress = :progress";
    $params = ['id' => $taskId, 'progress' => $progress];
    
    if ($status) {
        $sql .= ", status = :status";
        $params['status'] = $status;
        
        if ($status === 'completed') {
            $sql .= ", completed_at = NOW()";
        }
    }
    
    if ($notes) {
        $sql .= ", notes = :notes";
        $params['notes'] = $notes;
    }
    
    $sql .= " WHERE id = :id";
    
    $stmt = $pdo->prepare($sql);
    $result = $stmt->execute($params);
    
    if ($result) {
        // Add to history
        if ($task['progress'] != $progress) {
            addTaskHistory($pdo, $taskId, $employeeId, 'progress_updated', 
                          $task['progress'] . '%', $progress . '%');
        }
        
        if ($status && $task['status'] != $status) {
            addTaskHistory($pdo, $taskId, $employeeId, 'status_changed', 
                          $task['status'], $status);
            
            // Notify task assignor if status changed to completed
            if ($status === 'completed' && $task['assigned_by'] != $employeeId) {
                require_once __DIR__ . '/../../includes/notification_helpers.php';
                notify_employee($task['assigned_by'], 'task_completed', [
                    'task_id' => $taskId,
                    'task_title' => $task['title'],
                    'completed_by' => $employeeId
                ]);
            }
        }
        
        return true;
    }
    
    return false;
}

/**
 * Add task history entry
 */
function addTaskHistory($pdo, $taskId, $employeeId, $action, $oldValue = null, $newValue = null) {
    $sql = "INSERT INTO task_history (task_id, employee_id, action, old_value, new_value) 
            VALUES (:task_id, :employee_id, :action, :old_value, :new_value)";
    
    $stmt = $pdo->prepare($sql);
    return $stmt->execute([
        'task_id' => $taskId,
        'employee_id' => $employeeId,
        'action' => $action,
        'old_value' => $oldValue,
        'new_value' => $newValue
    ]);
}

/**
 * Get task comments
 */
function getTaskComments($pdo, $taskId) {
    $sql = "SELECT tc.*, e.first_name, e.last_name,
                   CONCAT(e.first_name, ' ', e.last_name) as commenter_name
            FROM task_comments tc
            JOIN employees e ON tc.employee_id = e.emp_id
            WHERE tc.task_id = :task_id
            ORDER BY tc.created_at ASC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['task_id' => $taskId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Add task comment
 */
function addTaskComment($pdo, $taskId, $employeeId, $comment) {
    $sql = "INSERT INTO task_comments (task_id, employee_id, comment) 
            VALUES (:task_id, :employee_id, :comment)";
    
    $stmt = $pdo->prepare($sql);
    return $stmt->execute([
        'task_id' => $taskId,
        'employee_id' => $employeeId,
        'comment' => $comment
    ]);
}

/**
 * Get task statistics for dashboard
 */
function getTaskStatistics($pdo, $employeeId) {
    // Tasks assigned to me
    $stmt = $pdo->prepare("SELECT 
                            COUNT(*) as total,
                            SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
                            SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) as in_progress,
                            SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
                            SUM(CASE WHEN due_date < CURDATE() AND status NOT IN ('completed', 'cancelled') THEN 1 ELSE 0 END) as overdue
                           FROM tasks 
                           WHERE assigned_to = :employee_id");
    $stmt->execute(['employee_id' => $employeeId]);
    $myTasks = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Tasks assigned by me
    $stmt = $pdo->prepare("SELECT 
                            COUNT(*) as total,
                            SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
                            SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) as in_progress,
                            SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed
                           FROM tasks 
                           WHERE assigned_by = :employee_id");
    $stmt->execute(['employee_id' => $employeeId]);
    $assignedByMe = $stmt->fetch(PDO::FETCH_ASSOC);
    
    return [
        'my_tasks' => $myTasks,
        'assigned_by_me' => $assignedByMe
    ];
}

/**
 * Check if employee can view/edit task
 */
function canAccessTask($pdo, $taskId, $employeeId) {
    $stmt = $pdo->prepare("SELECT assigned_by, assigned_to FROM tasks WHERE id = :id");
    $stmt->execute(['id' => $taskId]);
    $task = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$task) {
        return false;
    }
    
    // Can access if assigned to them, assigned by them, or if they're supervisor of assignee
    if ($task['assigned_to'] == $employeeId || $task['assigned_by'] == $employeeId) {
        return true;
    }
    
    // Check if they're supervisor of the assignee
    $subordinates = getSubordinates($pdo, $employeeId);
    return in_array($task['assigned_to'], $subordinates);
}
?>
