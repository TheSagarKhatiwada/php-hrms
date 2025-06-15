<?php
/**
 * Migration: improve_attendance_logs
 * Description: Add GPS tracking and break time features to attendance
 * Created: 2024-03-01 09:15:00
 */

return [
    'up' => function($pdo) {
        // Add GPS and break tracking fields to attendance_logs
        $pdo->exec("ALTER TABLE attendance_logs ADD COLUMN latitude DECIMAL(10, 8) DEFAULT NULL AFTER manual_reason");
        $pdo->exec("ALTER TABLE attendance_logs ADD COLUMN longitude DECIMAL(11, 8) DEFAULT NULL AFTER latitude");
        $pdo->exec("ALTER TABLE attendance_logs ADD COLUMN location_address TEXT DEFAULT NULL AFTER longitude");
        $pdo->exec("ALTER TABLE attendance_logs ADD COLUMN is_break TINYINT(1) DEFAULT 0 AFTER location_address");
        $pdo->exec("ALTER TABLE attendance_logs ADD COLUMN break_type ENUM('break_start', 'break_end', 'lunch_start', 'lunch_end') DEFAULT NULL AFTER is_break");
        
        // Create break logs table for detailed break tracking
        $pdo->exec("CREATE TABLE IF NOT EXISTS employee_break_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            employee_id INT NOT NULL,
            attendance_log_id INT DEFAULT NULL,
            break_type ENUM('break', 'lunch', 'other') NOT NULL DEFAULT 'break',
            break_start DATETIME NOT NULL,
            break_end DATETIME DEFAULT NULL,
            duration_minutes INT DEFAULT NULL,
            reason VARCHAR(255) DEFAULT NULL,
            approved_by INT DEFAULT NULL,
            status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
            FOREIGN KEY (attendance_log_id) REFERENCES attendance_logs(id) ON DELETE SET NULL,
            FOREIGN KEY (approved_by) REFERENCES employees(id) ON DELETE SET NULL,
            INDEX idx_employee_id (employee_id),
            INDEX idx_break_date (break_start),
            INDEX idx_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
        
        // Add indexes for better performance
        $pdo->exec("CREATE INDEX idx_attendance_gps ON attendance_logs(latitude, longitude)");
        $pdo->exec("CREATE INDEX idx_attendance_break ON attendance_logs(is_break, break_type)");
    },
    
    'down' => function($pdo) {
        // Drop break logs table
        $pdo->exec("DROP TABLE IF EXISTS employee_break_logs");
        
        // Drop indexes
        $pdo->exec("DROP INDEX IF EXISTS idx_attendance_gps ON attendance_logs");
        $pdo->exec("DROP INDEX IF EXISTS idx_attendance_break ON attendance_logs");
        
        // Remove GPS and break tracking fields
        $pdo->exec("ALTER TABLE attendance_logs DROP COLUMN latitude");
        $pdo->exec("ALTER TABLE attendance_logs DROP COLUMN longitude");
        $pdo->exec("ALTER TABLE attendance_logs DROP COLUMN location_address");
        $pdo->exec("ALTER TABLE attendance_logs DROP COLUMN is_break");
        $pdo->exec("ALTER TABLE attendance_logs DROP COLUMN break_type");
    }
];
