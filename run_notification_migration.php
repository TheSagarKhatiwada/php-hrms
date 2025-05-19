<?php
/**
 * Run Notification Migration
 * 
 * This script checks if the notifications table exists and creates it if not.
 * It also adds any necessary columns to support the notification system.
 */

// Include necessary files
require_once 'includes/db_connection.php';

// Check if notifications table exists
try {
    $stmt = $pdo->query("SHOW TABLES LIKE 'notifications'");
    $tableExists = $stmt->rowCount() > 0;
    
    if (!$tableExists) {
        echo "Creating notifications table...\n";
        
        // Create notifications table
        $sql = "CREATE TABLE `notifications` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `user_id` int(11) NOT NULL,
            `title` varchar(255) NOT NULL,
            `message` text NOT NULL,
            `type` enum('info','success','warning','danger') NOT NULL DEFAULT 'info',
            `link` varchar(255) DEFAULT NULL,
            `is_read` tinyint(1) NOT NULL DEFAULT 0,
            `read_at` datetime DEFAULT NULL,
            `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `user_id` (`user_id`),
            KEY `is_read` (`is_read`),
            CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;";
        
        $pdo->exec($sql);
        echo "Notifications table created successfully.\n";
    } else {
        echo "Notifications table already exists.\n";
        
        // Check if we need to add any columns to the existing table
        $stmt = $pdo->query("SHOW COLUMNS FROM `notifications` LIKE 'type'");
        $typeColumnExists = $stmt->rowCount() > 0;
        
        if (!$typeColumnExists) {
            echo "Adding 'type' column to notifications table...\n";
            $pdo->exec("ALTER TABLE `notifications` 
                       ADD COLUMN `type` enum('info','success','warning','danger') NOT NULL DEFAULT 'info' 
                       AFTER `message`");
            echo "'type' column added successfully.\n";
        }
    }
    
    // Record this migration in migrations table if it exists
    $stmt = $pdo->query("SHOW TABLES LIKE 'migrations'");
    $migrationsTableExists = $stmt->rowCount() > 0;
    
    if ($migrationsTableExists) {
        $migrationName = 'add_notifications_table';
        $stmt = $pdo->prepare("SELECT * FROM migrations WHERE migration = ?");
        $stmt->execute([$migrationName]);
        
        if ($stmt->rowCount() == 0) {
            $insertStmt = $pdo->prepare("INSERT INTO migrations (migration, executed_at) VALUES (?, NOW())");
            $insertStmt->execute([$migrationName]);
            echo "Migration recorded in migrations table.\n";
        }
    }
    
    echo "Notification system is ready to use.\n";
    
} catch (PDOException $e) {
    die("Database error: " . $e->getMessage() . "\n");
}
?>