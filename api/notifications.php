<?php
/**
 * Notifications API
 * 
 * This file handles API requests for notification operations:
 * - Fetch notification count
 * - Fetch notifications
 * - Mark notifications as read
 * - Delete notifications
 */

// Include required files
require_once "../includes/session_config.php";
require_once "../includes/config.php";
require_once "../includes/db_connection.php";
require_once "../includes/csrf_protection.php";
require_once "../includes/utilities.php";

// Verify user is logged in
if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'Authentication required',
        'redirect' => 'index.php'
    ]);
    exit;
}

// Get the user ID from session
$user_id = $_SESSION['user_id'];

// Process GET requests (fetch data)
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $action = isset($_GET['action']) ? $_GET['action'] : '';
    
    // Get count of unread notifications
    if ($action === 'get_count') {
        try {
            $query = "SELECT COUNT(*) as count FROM notifications 
                      WHERE user_id = ? AND is_read = 0";
            $stmt = $pdo->prepare($query);
            $stmt->execute([$user_id]);
            $row = $stmt->fetch();
            
            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'count' => (int)$row['count']
            ]);
            exit;
        } catch (Exception $e) {
            logError('Error fetching notification count: ' . $e->getMessage());
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'message' => 'Error fetching notification count'
            ]);
            exit;
        }
    }
    
    // Get notifications for the current user
    if ($action === 'get_notifications') {
        try {
            // Get only 3 unread notifications, sorted by time desc
            $query = "SELECT * FROM notifications 
                      WHERE user_id = ? AND is_read = 0
                      ORDER BY created_at DESC 
                      LIMIT 3";
            $stmt = $pdo->prepare($query);
            $stmt->execute([$user_id]);
            
            $notifications = [];
            while ($row = $stmt->fetch()) {
                $notifications[] = [
                    'id' => $row['id'],
                    'title' => $row['title'],
                    'message' => $row['message'],
                    'type' => $row['type'],
                    'link' => $row['link'],
                    'is_read' => (bool)$row['is_read'],
                    'created_at' => $row['created_at']
                ];
            }
            
            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'notifications' => $notifications
            ]);
            exit;
        } catch (Exception $e) {
            logError('Error fetching notifications: ' . $e->getMessage());
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'message' => 'Error fetching notifications'
            ]);
            exit;
        }
    }
    
    // Invalid action
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'Invalid action specified'
    ]);
    exit;
}

// Process POST requests (update data)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || !verify_csrf_token($_POST['csrf_token'])) {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'message' => 'Invalid CSRF token'
        ]);
        exit;
    }
    
    $action = isset($_POST['action']) ? $_POST['action'] : '';
    
    // Mark notification as read
    if ($action === 'mark_read') {
        if (!isset($_POST['notification_id']) || empty($_POST['notification_id'])) {
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'message' => 'Notification ID is required'
            ]);
            exit;
        }
        
        $notification_id = (int)$_POST['notification_id'];
        
        try {
            // First, verify the notification belongs to this user
            $query = "SELECT id FROM notifications WHERE id = ? AND user_id = ?";
            $stmt = $pdo->prepare($query);
            $stmt->execute([$notification_id, $user_id]);
            $result = $stmt->rowCount();
            
            if ($result === 0) {
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => false,
                    'message' => 'Notification not found or not authorized'
                ]);
                exit;
            }
            
            // Update notification to mark as read
            $query = "UPDATE notifications SET is_read = 1 WHERE id = ?";
            $stmt = $pdo->prepare($query);
            $stmt->execute([$notification_id]);
            
            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'message' => 'Notification marked as read'
            ]);
            exit;
        } catch (Exception $e) {
            logError('Error marking notification as read: ' . $e->getMessage());
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'message' => 'Error marking notification as read'
            ]);
            exit;
        }
    }
    
    // Mark all notifications as read
    if ($action === 'mark_all_read') {
        try {
            $query = "UPDATE notifications SET is_read = 1 WHERE user_id = ?";
            $stmt = $pdo->prepare($query);
            $stmt->execute([$user_id]);
            
            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'message' => 'All notifications marked as read'
            ]);
            exit;
        } catch (Exception $e) {
            logError('Error marking all notifications as read: ' . $e->getMessage());
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'message' => 'Error marking all notifications as read'
            ]);
            exit;
        }
    }
    
    // Delete notification
    if ($action === 'delete') {
        if (!isset($_POST['notification_id']) || empty($_POST['notification_id'])) {
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'message' => 'Notification ID is required'
            ]);
            exit;
        }
        
        $notification_id = (int)$_POST['notification_id'];
        
        try {
            // First, verify the notification belongs to this user
            $query = "SELECT id FROM notifications WHERE id = ? AND user_id = ?";
            $stmt = $pdo->prepare($query);
            $stmt->execute([$notification_id, $user_id]);
            $result = $stmt->rowCount();
            
            if ($result === 0) {
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => false,
                    'message' => 'Notification not found or not authorized'
                ]);
                exit;
            }
            
            // Delete the notification
            $query = "DELETE FROM notifications WHERE id = ?";
            $stmt = $pdo->prepare($query);
            $stmt->execute([$notification_id]);
            
            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'message' => 'Notification deleted'
            ]);
            exit;
        } catch (Exception $e) {
            logError('Error deleting notification: ' . $e->getMessage());
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'message' => 'Error deleting notification'
            ]);
            exit;
        }
    }
    
    // Invalid action
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'Invalid action specified'
    ]);
    exit;
}

// Unsupported HTTP method
header('Content-Type: application/json');
echo json_encode([
    'success' => false,
    'message' => 'Unsupported HTTP method'
]);
exit;