<?php
/**
 * Migration: create_generated_reports_table
 * Description: Create table to persist generated attendance reports so they survive session logout.
 * Created: 2025-08-17 12:00:00
 */

return [
    'up' => function($pdo) {
        $pdo->exec("CREATE TABLE IF NOT EXISTS generated_reports (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id VARCHAR(50) NULL,
            generated_by VARCHAR(150) NULL,
            report_type VARCHAR(30) NOT NULL,
            type_label VARCHAR(100) NOT NULL,
            date_label VARCHAR(100) NOT NULL,
            branch_id VARCHAR(50) NULL,
            branch_label VARCHAR(150) NOT NULL,
            employees_label VARCHAR(255) NOT NULL,
            file_url TEXT NOT NULL,
            generated_at DATETIME NOT NULL,
            deleted_at DATETIME NULL,
            deleted_by VARCHAR(50) NULL,
            deleted_by_name VARCHAR(150) NULL,
            purge_at DATETIME NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_report_type (report_type),
            INDEX idx_generated_at (generated_at),
            INDEX idx_deleted_at (deleted_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    },
    'down' => function($pdo) {
        try { $pdo->exec("DROP TABLE IF EXISTS generated_reports"); } catch(Throwable $e) {}
    }
];
