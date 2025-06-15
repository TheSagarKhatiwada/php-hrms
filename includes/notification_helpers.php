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
        error_log("[System Notification] $type: $title - $message");
          // Since we removed the users table and notifications table dependency,
        // we'll just log notifications for now
        return true;
    }
    
    /**
     * Send notification for attendance events
     * 
     * @param int $userId User ID
     * @param string $action Attendance action (checked_in, checked_out, etc.)
     * @param string $dateTime Date and time of attendance
     * @return bool Success status
     */    function notify_attendance($userId, $action, $dateTime) {        // Since we removed the users table and notifications, just log attendance events
        error_log("[Attendance Notification] $action for employee $userId at $dateTime");
        return true;
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
        // Since we removed the users table and notifications, just log asset events
        error_log("[Asset Notification] $action for asset '$assetName' - user $userId");
        return true;
        
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