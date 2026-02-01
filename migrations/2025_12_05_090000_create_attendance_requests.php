<?php
/**
 * Migration: create_attendance_requests
 * Description: Stores manual attendance requests submitted by limited non-admin users for later approval.
 * Created: 2025-12-05 09:00:00
 */

return [
    'up' => function($pdo) {
        $pdo->exec("CREATE TABLE IF NOT EXISTS attendance_requests (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            emp_id VARCHAR(20) COLLATE utf8mb4_unicode_ci NOT NULL,
            requested_by VARCHAR(20) COLLATE utf8mb4_unicode_ci NOT NULL,
            request_date DATE NOT NULL,
            request_time TIME NOT NULL,
            entry_type ENUM('in','out') NOT NULL DEFAULT 'in',
            requested_method VARCHAR(10) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'M',
            reason_code VARCHAR(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
            reason_label VARCHAR(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
            remarks TEXT COLLATE utf8mb4_unicode_ci NULL,
            status ENUM('pending','approved','rejected','cancelled') NOT NULL DEFAULT 'pending',
            reviewed_by VARCHAR(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
            reviewed_at DATETIME NULL,
            review_notes TEXT COLLATE utf8mb4_unicode_ci NULL,
            attendance_log_id INT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            KEY idx_attendance_requests_emp_id (emp_id),
            KEY idx_attendance_requests_status (status),
            KEY idx_attendance_requests_date (request_date),
            CONSTRAINT fk_attendance_requests_employee FOREIGN KEY (emp_id) REFERENCES employees(emp_id) ON DELETE CASCADE,
            CONSTRAINT fk_attendance_requests_requested_by FOREIGN KEY (requested_by) REFERENCES employees(emp_id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    },

    'down' => function($pdo) {
        $pdo->exec("DROP TABLE IF EXISTS attendance_requests");
    }
];
