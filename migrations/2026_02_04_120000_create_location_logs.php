<?php
/**
 * Migration: create_location_logs
 * Description: Adds table for storing periodic user location logs.
 * Created: 2026-02-04 12:00:00
 */

return [
    'up' => function ($pdo) {
        $pdo->exec("CREATE TABLE IF NOT EXISTS location_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            employee_id VARCHAR(20) NOT NULL COMMENT 'Employee emp_id from employees table',
            session_id VARCHAR(128) NOT NULL,
            latitude DECIMAL(10,7) NOT NULL,
            longitude DECIMAL(10,7) NOT NULL,
            accuracy_meters DECIMAL(8,2) DEFAULT NULL,
            provider VARCHAR(32) DEFAULT 'browser',
            ip_address VARCHAR(64) DEFAULT NULL,
            user_agent VARCHAR(255) DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_location_logs_employee (employee_id, created_at),
            INDEX idx_location_logs_session (session_id, created_at),
            CONSTRAINT fk_location_logs_employee FOREIGN KEY (employee_id) REFERENCES employees(emp_id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        COMMENT='Periodic location logs per session';");
    },

    'down' => function ($pdo) {
        $pdo->exec("DROP TABLE IF EXISTS location_logs");
    }
];
