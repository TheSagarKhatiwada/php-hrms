<?php
/**
 * Notification Service Class
 * 
 * Handles all notification operations for the HRMS system
 */
class NotificationService {
    private $db;
    
    /**
     * Constructor
     * 
     * @param PDO $db Database connection
     */
    public function __construct($db) {
        $this->db = $db;
    }
    
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
    public function sendNotification($userId, $title, $message, $type = 'info', $link = null) {
        try {
            $stmt = $this->db->prepare(
                "INSERT INTO notifications (user_id, title, message, type, link, created_at) 
                 VALUES (:user_id, :title, :message, :type, :link, NOW())"
            );
            
            $stmt->execute([
                'user_id' => $userId,
                'title' => $title,
                'message' => $message,
                'type' => $type,
                'link' => $link
            ]);
            
            return $this->db->lastInsertId();
        } catch (PDOException $e) {
            error_log('Error sending notification: ' . $e->getMessage());
            return false;
        }
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
    public function sendNotificationToMany($userIds, $title, $message, $type = 'info', $link = null) {
        try {
            $this->db->beginTransaction();
            
            $stmt = $this->db->prepare(
                "INSERT INTO notifications (user_id, title, message, type, link, created_at) 
                 VALUES (:user_id, :title, :message, :type, :link, NOW())"
            );
            
            foreach ($userIds as $userId) {
                $stmt->execute([
                    'user_id' => $userId,
                    'title' => $title,
                    'message' => $message,
                    'type' => $type,
                    'link' => $link
                ]);
            }
            
            $this->db->commit();
            return true;
        } catch (PDOException $e) {
            $this->db->rollBack();
            error_log('Error sending bulk notifications: ' . $e->getMessage());
            return false;
        }
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
    public function sendNotificationToAll($title, $message, $type = 'info', $link = null, $excludeIds = []) {
        try {
            // Get all user IDs
            $queryExclude = '';
            $params = [];
            
            if (!empty($excludeIds)) {
                $placeholders = implode(',', array_fill(0, count($excludeIds), '?'));
                $queryExclude = " WHERE id NOT IN ($placeholders)";
                $params = $excludeIds;
            }
            
            $stmt = $this->db->prepare("SELECT id FROM employees" . $queryExclude);
            
            if (!empty($params)) {
                $stmt->execute($params);
            } else {
                $stmt->execute();
            }
            
            $userIds = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            if (empty($userIds)) {
                return true; // No users to notify
            }
            
            return $this->sendNotificationToMany($userIds, $title, $message, $type, $link);
        } catch (PDOException $e) {
            error_log('Error sending notification to all: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get unread notifications for a user
     * 
     * @param int $userId User ID
     * @param int $limit Maximum number of notifications to return
     * @return array Array of notifications
     */
    public function getUnreadNotifications($userId, $limit = 10) {
        try {
            $stmt = $this->db->prepare(
                "SELECT * FROM notifications 
                 WHERE user_id = :user_id AND is_read = 0 
                 ORDER BY created_at DESC 
                 LIMIT :limit"
            );
            $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
            $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log('Error getting unread notifications: ' . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get all notifications for a user with pagination
     * 
     * @param int $userId User ID
     * @param int $limit Maximum number of notifications per page
     * @param int $offset Offset for pagination
     * @return array Array of notifications
     */
    public function getAllNotifications($userId, $limit = 20, $offset = 0) {
        try {
            $stmt = $this->db->prepare(
                "SELECT * FROM notifications 
                 WHERE user_id = :user_id 
                 ORDER BY created_at DESC 
                 LIMIT :limit OFFSET :offset"
            );
            $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
            $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
            $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
            $stmt->execute();
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log('Error getting all notifications: ' . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Count all notifications for a user
     * 
     * @param int $userId User ID
     * @return int Total number of notifications
     */
    public function countAllNotifications($userId) {
        try {
            $stmt = $this->db->prepare(
                "SELECT COUNT(*) FROM notifications 
                 WHERE user_id = :user_id"
            );
            $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
            $stmt->execute();
            
            return $stmt->fetchColumn();
        } catch (PDOException $e) {
            error_log('Error counting all notifications: ' . $e->getMessage());
            return 0;
        }
    }
    
    /**
     * Count unread notifications for a user
     * 
     * @param int $userId User ID
     * @return int Number of unread notifications
     */
    public function countUnreadNotifications($userId) {
        try {
            $stmt = $this->db->prepare(
                "SELECT COUNT(*) FROM notifications 
                 WHERE user_id = :user_id AND is_read = 0"
            );
            $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
            $stmt->execute();
            
            return $stmt->fetchColumn();
        } catch (PDOException $e) {
            error_log('Error counting unread notifications: ' . $e->getMessage());
            return 0;
        }
    }
    
    /**
     * Mark a notification as read
     * 
     * @param int $notificationId Notification ID
     * @param int $userId User ID (for security)
     * @return bool Success status
     */
    public function markAsRead($notificationId, $userId) {
        try {
            $stmt = $this->db->prepare(
                "UPDATE notifications 
                 SET is_read = 1, read_at = NOW() 
                 WHERE id = :id AND user_id = :user_id"
            );
            $stmt->execute([
                'id' => $notificationId,
                'user_id' => $userId
            ]);
            
            return $stmt->rowCount() > 0;
        } catch (PDOException $e) {
            error_log('Error marking notification as read: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Mark all notifications as read for a user
     * 
     * @param int $userId User ID
     * @return bool Success status
     */
    public function markAllAsRead($userId) {
        try {
            $stmt = $this->db->prepare(
                "UPDATE notifications 
                 SET is_read = 1, read_at = NOW() 
                 WHERE user_id = :user_id AND is_read = 0"
            );
            $stmt->execute([
                'user_id' => $userId
            ]);
            
            return true;
        } catch (PDOException $e) {
            error_log('Error marking all notifications as read: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Delete a notification
     * 
     * @param int $notificationId Notification ID
     * @param int $userId User ID (for security)
     * @return bool Success status
     */
    public function deleteNotification($notificationId, $userId) {
        try {
            $stmt = $this->db->prepare(
                "DELETE FROM notifications 
                 WHERE id = :id AND user_id = :user_id"
            );
            $stmt->execute([
                'id' => $notificationId,
                'user_id' => $userId
            ]);
            
            return $stmt->rowCount() > 0;
        } catch (PDOException $e) {
            error_log('Error deleting notification: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Delete all read notifications for a user
     * 
     * @param int $userId User ID
     * @return bool Success status
     */
    public function deleteAllReadNotifications($userId) {
        try {
            $stmt = $this->db->prepare(
                "DELETE FROM notifications 
                 WHERE user_id = :user_id AND is_read = 1"
            );
            $stmt->execute([
                'user_id' => $userId
            ]);
            
            return true;
        } catch (PDOException $e) {
            error_log('Error deleting read notifications: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Delete notifications older than a certain date
     * 
     * @param int $days Number of days to keep notifications for
     * @return bool Success status
     */
    public function deleteOldNotifications($days = 30) {
        try {
            $stmt = $this->db->prepare(
                "DELETE FROM notifications 
                 WHERE created_at < DATE_SUB(NOW(), INTERVAL :days DAY)"
            );
            $stmt->bindParam(':days', $days, PDO::PARAM_INT);
            $stmt->execute();
            
            return true;
        } catch (PDOException $e) {
            error_log('Error deleting old notifications: ' . $e->getMessage());
            return false;
        }
    }
}