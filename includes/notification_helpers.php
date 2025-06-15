<?php
/**
 * Notification Helper
 * 
 * This file provides helper functions to easily send notifications from anywhere in the application.
 */

// Include the NotificationService class
require_once __DIR__ . '/services/NotificationService.php';

// Make sure we don't redeclare functions if the file is included multiple times
if (!function_exists('notify_user')) {
    
    /**
     * Send a notification to a specific user
     * 
     * @param int $userId User ID to send notification to
     * @param string $title Notification title
     * @param string $message Notification message
     * @param string $type Notification type (info, success, warning, danger)
     * @param string $link Optional link for the notification
     * @return int|false The ID of the created notification or false on failure
     */
    function notify_user($userId, $title, $message, $type = 'info', $link = null) {
        global $pdo;
        
        // Initialize notification service if not already available
        $notificationService = new NotificationService($pdo);
        
        return $notificationService->sendNotification($userId, $title, $message, $type, $link);
    }
    
    /**
     * Send a notification to multiple users
     * 
     * @param array $userIds Array of user IDs to send notification to
     * @param string $title Notification title
     * @param string $message Notification message
     * @param string $type Notification type (info, success, warning, danger)
     * @param string $link Optional link for the notification
     * @return bool Success status
     */
    function notify_users($userIds, $title, $message, $type = 'info', $link = null) {
        global $pdo;
        
        $notificationService = new NotificationService($pdo);
        
        return $notificationService->sendNotificationToMany($userIds, $title, $message, $type, $link);
    }
    
    /**
     * Send notification to all users
     * 
     * @param string $title Notification title
     * @param string $message Notification message
     * @param string $type Notification type (info, success, warning, danger)
     * @param string $link Optional link for the notification
     * @param array $excludeIds Optional array of user IDs to exclude
     * @return bool Success status
     */
    function notify_all($title, $message, $type = 'info', $link = null, $excludeIds = []) {
        global $pdo;
        
        $notificationService = new NotificationService($pdo);
        
        return $notificationService->sendNotificationToAll($title, $message, $type, $link, $excludeIds);
    }
    
    /**
     * Send a system notification (logged in system logs and optionally sent to admins)
     * 
     * @param string $title Notification title
     * @param string $message Notification message
     * @param string $type Notification type (info, success, warning, danger)
     * @param bool $notifyAdmins Whether to notify admin users
     * @return bool Success status
     */
    function notify_system($title, $message, $type = 'info', $notifyAdmins = true) {
        global $pdo;
        
        // Log the notification
        error_log("[System Notification] $type: $title - $message");        // If we should notify admins
        if ($notifyAdmins) {
            try {
                // Get all admin emp_ids 
                $stmt = $pdo->prepare("SELECT emp_id FROM employees WHERE role_id = 1 AND emp_id IS NOT NULL");
                $stmt->execute();
                $adminIds = $stmt->fetchAll(PDO::FETCH_COLUMN);
                
                if (!empty($adminIds)) {
                    $notificationService = new NotificationService($pdo);
                    $notificationService->sendNotificationToMany($adminIds, $title, $message, $type, 'system-settings.php');
                } else {
                    error_log('No admin users found with valid emp_id for notifications');
                }
                
                return true;
            } catch (PDOException $e) {
                error_log('Error sending bulk notifications: ' . $e->getMessage());
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Send notification for attendance events
     * 
     * @param int $userId User ID
     * @param string $action Attendance action (checked_in, checked_out, etc.)
     * @param string $dateTime Date and time of attendance
     * @return bool Success status
     */
    function notify_attendance($userId, $action, $dateTime) {
        global $pdo;
          try {
            // Get employee details using emp_id
            $stmt = $pdo->prepare("SELECT emp_id, CONCAT(first_name, ' ', last_name) as name FROM employees WHERE emp_id = ?");
            $stmt->execute([$userId]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$user) {
                error_log("Could not find employee with emp_id: $userId");
                return false; // Employee not found, can't send notification
            }
            
            $empId = $user['emp_id']; // This is the emp_id for the notifications table
            $userName = $user['name'];
            
            // Format title and message based on action
            $title = "Attendance " . ucfirst(str_replace('_', ' ', $action));
            $message = "";
            $type = "info";
            $link = "attendance.php";
            
            switch ($action) {
                case 'checked_in':
                case 'clocked in':
                    $message = "You checked in at $dateTime";
                    $type = "success";
                    break;
                case 'checked_out':
                    $message = "You checked out at $dateTime";
                    $type = "info";
                    break;
                case 'manual_record':
                    $message = "Your attendance was manually recorded for $dateTime";
                    $type = "warning";
                    break;
                default:
                    $message = "Attendance $action recorded at $dateTime";
            }
            
            // First check if notifications table exists to avoid errors
            $stmt = $pdo->prepare("SHOW TABLES LIKE 'notifications'");
            $stmt->execute();
            if ($stmt->rowCount() == 0) {
                error_log("Notifications table doesn't exist - notification skipped");
                return true; // Return true to avoid breaking the check-in process
            }
            
            // Try to send notification, but don't let it break the attendance process
            try {
                // Send notification using the internal user ID
                $notificationService = new NotificationService($pdo);
                return $notificationService->sendNotification($internalUserId, $title, $message, $type, $link);
            } catch (Exception $e) {
                error_log("Error sending notification in notify_attendance: " . $e->getMessage());
                return true; // Return true so attendance process completes successfully
            }
        } catch (Exception $e) {
            error_log("Error in notify_attendance: " . $e->getMessage());
            return true; // Return true anyway so attendance process isn't broken
        }
    }
    
    /**
     * Send notification for asset management events
     * 
     * @param int $userId User ID
     * @param string $action Asset action (assigned, returned, etc.)
     * @param string $assetName Name of the asset
     * @return bool Success status
     */
    function notify_asset($userId, $action, $assetName) {
        global $pdo;
        
        // Format title and message based on action        $title = "Asset " . ucfirst(str_replace('_', ' ', $action));
        $message = "";
        $type = "info";
        $link = "modules/assets/assets.php";
        
        switch ($action) {
            case 'assigned':
                $message = "Asset '$assetName' has been assigned to you";
                $type = "success";
                break;
            case 'returned':
                $message = "You have returned the asset '$assetName'";
                $type = "info";
                break;
            case 'maintenance':
                $message = "Asset '$assetName' assigned to you is scheduled for maintenance";
                $type = "warning";
                break;
            case 'overdue':
                $message = "Asset '$assetName' is overdue for return";
                $type = "danger";
                break;
            default:
                $message = "Asset '$assetName' $action";
        }
        
        // Send notification
        $notificationService = new NotificationService($pdo);
        return $notificationService->sendNotification($userId, $title, $message, $type, $link);
    }
    
    /**
     * Send a notification for employee events
     * 
     * @param int $userId User ID
     * @param string $action Employee action (joined, updated, etc.)
     * @param array $data Additional data related to the action
     * @return bool Success status
     */
    function notify_employee($userId, $action, $data = []) {
        global $pdo;
        
        // Format title and message based on action
        $title = "Employee " . ucfirst(str_replace('_', ' ', $action));
        $message = "";
        $type = "info";
        $link = "profile.php";
        
        switch ($action) {
            case 'joined':
                $message = "Welcome to the company! Your account has been created";
                $type = "success";
                break;
            case 'updated':
                $message = "Your employee profile has been updated";
                $type = "info";
                break;
            case 'birthday':
                $message = "Happy Birthday! Best wishes from the whole team";
                $type = "success";
                break;
            case 'work_anniversary':
                $years = isset($data['years']) ? $data['years'] : '';
                $yearText = $years > 1 ? "years" : "year";
                $message = "Congratulations on completing " . $years . " $yearText with the company!";
                $type = "success";
                break;
            default:
                $message = "Employee update: $action";
        }
        
        // Send notification
        $notificationService = new NotificationService($pdo);
        return $notificationService->sendNotification($userId, $title, $message, $type, $link);
    }
}