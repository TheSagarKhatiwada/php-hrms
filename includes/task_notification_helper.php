<?php
/**
 * Task Notification Helper
 * Handles email and SMS notifications for task-related events
 */

require_once __DIR__ . '/mail_helper.php';
require_once __DIR__ . '/../modules/sms/SparrowSMS.php';

/**
 * Get base URL for the application
 */
function getBaseUrl() {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    return $protocol . '://' . $host;
}

/**
 * Get user notification preferences
 * 
 * @param PDO $pdo Database connection
 * @param int $employee_id Employee ID
 * @param string $notification_type Type of notification
 * @return array Preferences with email_enabled and sms_enabled
 */
function getUserNotificationPreferences($pdo, $employee_id, $notification_type) {
    try {
        // employee_id parameter is emp_id from employees table
        $stmt = $pdo->prepare("
            SELECT email_enabled, sms_enabled 
            FROM notification_preferences 
            WHERE employee_id = ? AND notification_type = ?
        ");
        $stmt->execute([$employee_id, $notification_type]);
        $prefs = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Default to email enabled, SMS disabled if no preference found
        if (!$prefs) {
            return ['email_enabled' => 1, 'sms_enabled' => 0];
        }
        
        return $prefs;
    } catch (Exception $e) {
        // On error, default to email only
        error_log("Failed to get notification preferences: " . $e->getMessage());
        return ['email_enabled' => 1, 'sms_enabled' => 0];
    }
}

/**
 * Send task assignment notification
 * 
 * @param PDO $pdo Database connection
 * @param int $task_id Task ID
 * @param string $assigned_to Employee ID of assignee
 * @param string $assigned_by Employee ID of assigner
 * @return array Result with success status and message
 */
function sendTaskAssignmentNotification($pdo, $task_id, $assigned_to, $assigned_by) {
    $results = ['email' => false, 'sms' => false, 'messages' => []];
    
    try {
        // Get task details
        $stmt = $pdo->prepare("
            SELECT t.*, 
                   assignee.emp_id as assignee_emp_id, assignee.first_name as assignee_first, assignee.last_name as assignee_last, 
                   assignee.email as assignee_email, assignee.phone as assignee_phone,
                   assignor.first_name as assignor_first, assignor.last_name as assignor_last
            FROM tasks t
            LEFT JOIN employees assignee ON t.assigned_to = assignee.emp_id
            LEFT JOIN employees assignor ON t.assigned_by = assignor.emp_id
            WHERE t.id = ?
        ");
        $stmt->execute([$task_id]);
        $task = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$task) {
            return ['email' => false, 'sms' => false, 'messages' => ['Task not found']];
        }
        
        // Validate employee data exists
        if (empty($task['assignee_emp_id']) || empty($task['assignee_email'])) {
            return ['email' => false, 'sms' => false, 'messages' => ['Assignee not found or no email address']];
        }
        
        // Check user preferences
        $prefs = getUserNotificationPreferences($pdo, $task['assignee_emp_id'], 'task_assigned');
        
        $assignee_name = trim(($task['assignee_first'] ?? '') . ' ' . ($task['assignee_last'] ?? ''));
        $assignor_name = trim(($task['assignor_first'] ?? '') . ' ' . ($task['assignor_last'] ?? '')) ?: 'System';
        
        // Send Email Notification (only if enabled)
        if ($prefs['email_enabled'] && !empty($task['assignee_email'])) {
            $subject = "New Task Assigned: " . $task['title'];
            $due_date_text = !empty($task['due_date']) ? date('M d, Y', strtotime($task['due_date'])) : 'Not set';
            
            $email_body = "
                <html>
                <head>
                    <style>
                        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                        .header { background-color: #007bff; color: white; padding: 20px; text-align: center; border-radius: 5px 5px 0 0; }
                        .content { background-color: #f8f9fa; padding: 20px; border-radius: 0 0 5px 5px; }
                        .task-details { background-color: white; padding: 15px; margin: 15px 0; border-left: 4px solid #007bff; }
                        .detail-row { margin: 10px 0; }
                        .label { font-weight: bold; color: #555; }
                        .priority-high { color: #dc3545; font-weight: bold; }
                        .priority-urgent { color: #dc3545; font-weight: bold; text-transform: uppercase; }
                        .priority-medium { color: #ffc107; }
                        .priority-low { color: #28a745; }
                        .button { background-color: #007bff; color: white; padding: 12px 24px; text-decoration: none; border-radius: 5px; display: inline-block; margin-top: 15px; }
                        .footer { text-align: center; margin-top: 20px; color: #777; font-size: 12px; }
                    </style>
                </head>
                <body>
                    <div class='container'>
                        <div class='header'>
                            <h2>✓ New Task Assigned</h2>
                        </div>
                        <div class='content'>
                            <p>Hi <strong>{$assignee_name}</strong>,</p>
                            <p>You have been assigned a new task by <strong>{$assignor_name}</strong>.</p>
                            
                            <div class='task-details'>
                                <div class='detail-row'>
                                    <span class='label'>Task Title:</span><br>
                                    <h3 style='margin: 5px 0;'>{$task['title']}</h3>
                                </div>
                                
                                " . (!empty($task['description']) ? "
                                <div class='detail-row'>
                                    <span class='label'>Description:</span><br>
                                    <p style='margin: 5px 0;'>" . nl2br(htmlspecialchars($task['description'])) . "</p>
                                </div>
                                " : "") . "
                                
                                <div class='detail-row'>
                                    <span class='label'>Priority:</span> 
                                    <span class='priority-" . $task['priority'] . "'>" . ucfirst($task['priority']) . "</span>
                                </div>
                                
                                <div class='detail-row'>
                                    <span class='label'>Due Date:</span> {$due_date_text}
                                </div>
                                
                                " . (!empty($task['category']) ? "
                                <div class='detail-row'>
                                    <span class='label'>Category:</span> {$task['category']}
                                </div>
                                " : "") . "
                            </div>
                            
                            <p>Please log in to the HRMS system to view the complete task details and update its progress.</p>
                            
                            <center>
                                <a href='" . getBaseUrl() . "/modules/tasks/view_task.php?id={$task_id}' class='button'>View Task Details</a>
                            </center>
                            
                            <div class='footer'>
                                <p>This is an automated notification from the HRMS Task Management System.<br>
                                Please do not reply to this email.</p>
                            </div>
                        </div>
                    </div>
                </body>
                </html>
            ";
            
            $results['email'] = send_email($task['assignee_email'], $subject, $email_body, 'HRMS Task Management');
            $results['messages'][] = $results['email'] ? 'Email notification sent successfully' : 'Email notification failed';
        } else {
            $results['messages'][] = 'Email notification skipped (disabled or no email address)';
        }
        
        // Send SMS Notification (only if enabled)
        if ($prefs['sms_enabled'] && !empty($task['assignee_phone'])) {
            $sms_message = "New task assigned: \"{$task['title']}\" by {$assignor_name}. Priority: " . ucfirst($task['priority']) . ". Due: {$due_date_text}. Check HRMS for details.";
            
            try {
                $sms_result = sendTaskSMS($task['assignee_phone'], $sms_message);
                $results['sms'] = $sms_result['success'];
                $results['messages'][] = $sms_result['message'];
            } catch (Exception $e) {
                $results['messages'][] = 'SMS notification failed: ' . $e->getMessage();
            }
        } else {
            $results['messages'][] = 'SMS notification skipped (disabled or no phone number)';
        }
        
    } catch (Exception $e) {
        $results['messages'][] = 'Notification error: ' . $e->getMessage();
        error_log("Task notification error: " . $e->getMessage());
    }
    
    return $results;
}

/**
 * Send task status update notification
 * 
 * @param PDO $pdo Database connection
 * @param int $task_id Task ID
 * @param string $new_status New status
 * @param string $updated_by Employee ID of who updated
 * @return array Result with success status
 */
function sendTaskStatusUpdateNotification($pdo, $task_id, $new_status, $updated_by) {
    $results = ['email' => false, 'sms' => false, 'messages' => []];
    
    try {
        // Get task details
        $stmt = $pdo->prepare("
            SELECT t.*, 
                   assignee.email as assignee_email, assignee.phone as assignee_phone,
                   assignor.emp_id as assignor_emp_id, assignor.first_name as assignor_first, assignor.last_name as assignor_last,
                   assignor.email as assignor_email, assignor.phone as assignor_phone,
                   updater.first_name as updater_first, updater.last_name as updater_last
            FROM tasks t
            LEFT JOIN employees assignee ON t.assigned_to = assignee.emp_id
            LEFT JOIN employees assignor ON t.assigned_by = assignor.emp_id
            LEFT JOIN employees updater ON ? = updater.emp_id
            WHERE t.id = ?
        ");
        $stmt->execute([$updated_by, $task_id]);
        $task = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$task) return $results;
        
        // Check user preferences
        $prefs = getUserNotificationPreferences($pdo, $task['assignor_emp_id'], 'task_status_update');
        
        $updater_name = $task['updater_first'] . ' ' . $task['updater_last'];
        $assignor_name = $task['assignor_first'] . ' ' . $task['assignor_last'];
        
        // Notify the assignor (task creator) about status change (only if enabled)
        if ($task['assigned_by'] != $updated_by && $prefs['email_enabled'] && !empty($task['assignor_email'])) {
            $status_display = ucfirst(str_replace('_', ' ', $new_status));
            $subject = "Task Status Updated: " . $task['title'];
            
            $email_body = "
                <html>
                <head>
                    <style>
                        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                        .header { background-color: #28a745; color: white; padding: 20px; text-align: center; border-radius: 5px 5px 0 0; }
                        .content { background-color: #f8f9fa; padding: 20px; border-radius: 0 0 5px 5px; }
                        .task-details { background-color: white; padding: 15px; margin: 15px 0; border-left: 4px solid #28a745; }
                        .status-badge { padding: 5px 15px; border-radius: 15px; display: inline-block; font-weight: bold; }
                        .status-completed { background-color: #d4edda; color: #155724; }
                        .status-in-progress { background-color: #fff3cd; color: #856404; }
                        .status-pending { background-color: #f8d7da; color: #721c24; }
                        .button { background-color: #28a745; color: white; padding: 12px 24px; text-decoration: none; border-radius: 5px; display: inline-block; margin-top: 15px; }
                    </style>
                </head>
                <body>
                    <div class='container'>
                        <div class='header'>
                            <h2>📊 Task Status Updated</h2>
                        </div>
                        <div class='content'>
                            <p>Hi <strong>{$assignor_name}</strong>,</p>
                            <p>The status of a task you assigned has been updated by <strong>{$updater_name}</strong>.</p>
                            
                            <div class='task-details'>
                                <h3>{$task['title']}</h3>
                                <p><strong>New Status:</strong> <span class='status-badge status-{$new_status}'>{$status_display}</span></p>
                                " . (!empty($task['progress']) ? "<p><strong>Progress:</strong> {$task['progress']}%</p>" : "") . "
                            </div>
                            
                            <center>
                                <a href='" . getBaseUrl() . "/modules/tasks/view_task.php?id={$task_id}' class='button'>View Task Details</a>
                            </center>
                        </div>
                    </div>
                </body>
                </html>
            ";
            
            $results['email'] = send_email($task['assignor_email'], $subject, $email_body, 'HRMS Task Management');
        }
        
    } catch (Exception $e) {
        error_log("Task status notification error: " . $e->getMessage());
    }
    
    return $results;
}

/**
 * Send task completion notification
 * 
 * @param PDO $pdo Database connection
 * @param int $task_id Task ID
 * @return array Result with success status
 */
function sendTaskCompletionNotification($pdo, $task_id) {
    $results = ['email' => false, 'sms' => false, 'messages' => []];
    
    try {
        // Get task details
        $stmt = $pdo->prepare("
            SELECT t.*, 
                   assignee.first_name as assignee_first, assignee.last_name as assignee_last,
                   assignor.emp_id as assignor_emp_id, assignor.first_name as assignor_first, assignor.last_name as assignor_last,
                   assignor.email as assignor_email, assignor.phone as assignor_phone
            FROM tasks t
            LEFT JOIN employees assignee ON t.assigned_to = assignee.emp_id
            LEFT JOIN employees assignor ON t.assigned_by = assignor.emp_id
            WHERE t.id = ?
        ");
        $stmt->execute([$task_id]);
        $task = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$task) return $results;
        
        // Check user preferences
        $prefs = getUserNotificationPreferences($pdo, $task['assignor_emp_id'], 'task_completed');
        
        $assignee_name = $task['assignee_first'] . ' ' . $task['assignee_last'];
        $assignor_name = $task['assignor_first'] . ' ' . $task['assignor_last'];
        
        // Send email to task creator (only if enabled)
        if ($prefs['email_enabled'] && !empty($task['assignor_email'])) {
            $subject = "Task Completed: " . $task['title'];
            
            $email_body = "
                <html>
                <head>
                    <style>
                        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                        .header { background-color: #28a745; color: white; padding: 20px; text-align: center; border-radius: 5px 5px 0 0; }
                        .content { background-color: #f8f9fa; padding: 20px; border-radius: 0 0 5px 5px; }
                        .task-details { background-color: white; padding: 15px; margin: 15px 0; border-left: 4px solid #28a745; }
                        .button { background-color: #28a745; color: white; padding: 12px 24px; text-decoration: none; border-radius: 5px; display: inline-block; margin-top: 15px; }
                    </style>
                </head>
                <body>
                    <div class='container'>
                        <div class='header'>
                            <h2>✅ Task Completed</h2>
                        </div>
                        <div class='content'>
                            <p>Hi <strong>{$assignor_name}</strong>,</p>
                            <p><strong>{$assignee_name}</strong> has marked the following task as completed:</p>
                            
                            <div class='task-details'>
                                <h3>{$task['title']}</h3>
                                " . (!empty($task['description']) ? "<p>" . nl2br(htmlspecialchars($task['description'])) . "</p>" : "") . "
                                <p><strong>Completed on:</strong> " . date('M d, Y H:i') . "</p>
                            </div>
                            
                            <center>
                                <a href='" . getBaseUrl() . "/modules/tasks/view_task.php?id={$task_id}' class='button'>View Task Details</a>
                            </center>
                        </div>
                    </div>
                </body>
                </html>
            ";
            
            $results['email'] = send_email($task['assignor_email'], $subject, $email_body, 'HRMS Task Management');
            $results['messages'][] = $results['email'] ? 'Completion email sent' : 'Completion email failed';
        } else {
            $results['messages'][] = 'Email notification skipped (disabled or no email address)';
        }
        
        // Send SMS notification (only if enabled)
        if ($prefs['sms_enabled'] && !empty($task['assignor_phone'])) {
            $sms_message = "Task completed: \"{$task['title']}\" by {$assignee_name}. Check HRMS for details.";
            try {
                $sms_result = sendTaskSMS($task['assignor_phone'], $sms_message);
                $results['sms'] = $sms_result['success'];
                $results['messages'][] = $sms_result['message'];
            } catch (Exception $e) {
                $results['messages'][] = 'SMS failed: ' . $e->getMessage();
            }
        } else {
            $results['messages'][] = 'SMS notification skipped (disabled or no phone number)';
        }
        
    } catch (Exception $e) {
        error_log("Task completion notification error: " . $e->getMessage());
    }
    
    return $results;
}

/**
 * Send overdue task reminder
 * 
 * @param PDO $pdo Database connection
 * @param int $task_id Task ID
 * @return array Result with success status
 */
function sendOverdueTaskReminder($pdo, $task_id) {
    $results = ['email' => false, 'sms' => false, 'messages' => []];
    
    try {
        // Get task details
        $stmt = $pdo->prepare("
            SELECT t.*, 
                   assignee.emp_id as assignee_emp_id, assignee.first_name as assignee_first, assignee.last_name as assignee_last,
                   assignee.email as assignee_email, assignee.phone as assignee_phone,
                   assignor.first_name as assignor_first, assignor.last_name as assignor_last
            FROM tasks t
            LEFT JOIN employees assignee ON t.assigned_to = assignee.emp_id
            LEFT JOIN employees assignor ON t.assigned_by = assignor.emp_id
            WHERE t.id = ? AND t.status != 'completed' AND t.due_date < CURDATE()
        ");
        $stmt->execute([$task_id]);
        $task = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$task) return $results;
        
        // Check user preferences
        $prefs = getUserNotificationPreferences($pdo, $task['assignee_emp_id'], 'task_overdue');
        
        $assignee_name = $task['assignee_first'] . ' ' . $task['assignee_last'];
        $days_overdue = (strtotime('today') - strtotime($task['due_date'])) / (60 * 60 * 24);
        
        // Send email reminder (only if enabled)
        if ($prefs['email_enabled'] && !empty($task['assignee_email'])) {
            $subject = "⚠️ Overdue Task Reminder: " . $task['title'];
            
            $email_body = "
                <html>
                <head>
                    <style>
                        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                        .header { background-color: #dc3545; color: white; padding: 20px; text-align: center; border-radius: 5px 5px 0 0; }
                        .content { background-color: #f8f9fa; padding: 20px; border-radius: 0 0 5px 5px; }
                        .task-details { background-color: white; padding: 15px; margin: 15px 0; border-left: 4px solid #dc3545; }
                        .overdue-badge { background-color: #f8d7da; color: #721c24; padding: 5px 15px; border-radius: 15px; font-weight: bold; }
                        .button { background-color: #dc3545; color: white; padding: 12px 24px; text-decoration: none; border-radius: 5px; display: inline-block; margin-top: 15px; }
                    </style>
                </head>
                <body>
                    <div class='container'>
                        <div class='header'>
                            <h2>⚠️ Overdue Task Reminder</h2>
                        </div>
                        <div class='content'>
                            <p>Hi <strong>{$assignee_name}</strong>,</p>
                            <p>This is a reminder that the following task is overdue:</p>
                            
                            <div class='task-details'>
                                <h3>{$task['title']}</h3>
                                <p class='overdue-badge'>Overdue by {$days_overdue} day(s)</p>
                                <p><strong>Due Date:</strong> " . date('M d, Y', strtotime($task['due_date'])) . "</p>
                                <p><strong>Priority:</strong> " . ucfirst($task['priority']) . "</p>
                            </div>
                            
                            <p>Please update the task status or complete it as soon as possible.</p>
                            
                            <center>
                                <a href='" . getBaseUrl() . "/modules/tasks/view_task.php?id={$task_id}' class='button'>View Task Details</a>
                            </center>
                        </div>
                    </div>
                </body>
                </html>
            ";
            
            $results['email'] = send_email($task['assignee_email'], $subject, $email_body, 'HRMS Task Management');
            $results['messages'][] = $results['email'] ? 'Reminder email sent' : 'Reminder email failed';
        } else {
            $results['messages'][] = 'Email notification skipped (disabled or no email address)';
        }
        
        // Send SMS reminder (only if enabled)
        if ($prefs['sms_enabled'] && !empty($task['assignee_phone'])) {
            $sms_message = "REMINDER: Task \"{$task['title']}\" is overdue by {$days_overdue} day(s). Please complete urgently.";
            try {
                $sms_result = sendTaskSMS($task['assignee_phone'], $sms_message);
                $results['sms'] = $sms_result['success'];
            } catch (Exception $e) {
                error_log("SMS reminder failed: " . $e->getMessage());
            }
        } else {
            $results['messages'][] = 'SMS notification skipped (disabled or no phone number)';
        }
        
    } catch (Exception $e) {
        error_log("Overdue reminder error: " . $e->getMessage());
    }
    
    return $results;
}

/**
 * Helper function to send SMS for tasks
 * 
 * @param string $phone Phone number
 * @param string $message SMS message
 * @return array Result with success and message
 */
function sendTaskSMS($phone, $message) {
    try {
        // Check if SMS is enabled
        global $pdo;
        $stmt = $pdo->prepare("SELECT config_value FROM sms_config WHERE config_key = 'sms_enabled'");
        $stmt->execute();
        $sms_enabled = $stmt->fetchColumn();
        
        if ($sms_enabled != '1') {
            return ['success' => false, 'message' => 'SMS notifications are disabled'];
        }
        
        // Initialize SMS service
        $smsService = new SparrowSMS();
        $result = $smsService->sendSMS($phone, $message);
        
        return [
            'success' => $result['success'],
            'message' => $result['message'] ?? 'SMS sent successfully'
        ];
        
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'SMS error: ' . $e->getMessage()];
    }
}
?>
