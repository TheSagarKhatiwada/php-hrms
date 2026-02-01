<?php
/**
 * Migration: create_notification_preferences
 * Description: Adds table for storing employee notification channel preferences.
 * Created: 2025-01-15 00:00:00
 */

return [
    'up' => function ($pdo) {
        $pdo->exec("CREATE TABLE IF NOT EXISTS notification_preferences (
            id INT AUTO_INCREMENT PRIMARY KEY,
            employee_id VARCHAR(20) NOT NULL COMMENT 'Employee emp_id from employees table',
            notification_type VARCHAR(50) NOT NULL COMMENT 'task_assigned, task_status_update, task_completed, task_overdue',
            email_enabled TINYINT(1) DEFAULT 1,
            sms_enabled TINYINT(1) DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY unique_employee_notification (employee_id, notification_type),
            CONSTRAINT fk_notification_preferences_employee FOREIGN KEY (employee_id) REFERENCES employees(emp_id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        COMMENT='User notification preferences for email and SMS';");

        $pdo->exec("INSERT IGNORE INTO notification_preferences (employee_id, notification_type, email_enabled, sms_enabled)
            SELECT e.emp_id, type_lookup.notification_type, 1, 0
            FROM employees e
            CROSS JOIN (
                SELECT 'task_assigned' AS notification_type UNION ALL
                SELECT 'task_status_update' UNION ALL
                SELECT 'task_completed' UNION ALL
                SELECT 'task_overdue'
            ) AS type_lookup");
    },

    'down' => function ($pdo) {
        $pdo->exec("DROP TABLE IF EXISTS notification_preferences");
    }
];
