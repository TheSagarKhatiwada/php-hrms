<?php
/**
 * Migration: create_employee_branch_transfers
 * Description: Track employee branch transfer events with audit-friendly metadata.
 * Created: 2025-11-24 10:15:00
 */

return [
    'up' => function($pdo) {
        $pdo->exec("CREATE TABLE IF NOT EXISTS employee_branch_transfers (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            employee_id VARCHAR(20) COLLATE utf8mb4_unicode_ci NOT NULL,
            from_branch_id INT NULL,
            to_branch_id INT NOT NULL,
            from_supervisor_id VARCHAR(20) NULL,
            to_supervisor_id VARCHAR(20) NULL,
            effective_date DATE NOT NULL,
            last_day_in_previous_branch DATE NULL,
            reason TEXT NULL,
            previous_work_start_time TIME NULL,
            previous_work_end_time TIME NULL,
            new_work_start_time TIME NULL,
            new_work_end_time TIME NULL,
            notify_stakeholders TINYINT(1) NOT NULL DEFAULT 0,
            processed_by VARCHAR(20) NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            KEY idx_employee_id (employee_id),
            KEY idx_to_branch_id (to_branch_id),
            KEY idx_effective_date (effective_date),
            CONSTRAINT fk_transfer_employee FOREIGN KEY (employee_id) REFERENCES employees(emp_id) ON DELETE CASCADE,
            CONSTRAINT fk_transfer_branch_to FOREIGN KEY (to_branch_id) REFERENCES branches(id) ON DELETE RESTRICT,
            CONSTRAINT fk_transfer_branch_from FOREIGN KEY (from_branch_id) REFERENCES branches(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    },

    'down' => function($pdo) {
        $pdo->exec("DROP TABLE IF EXISTS employee_branch_transfers");
    }
];
