<?php
/**
 * Migration: create_employee_education_and_experience_tables
 * Description: Store structured academic achievements and prior experience entries for employees.
 * Created: 2025-11-28 10:30:00
 */

return [
    'up' => function ($pdo) {
        $pdo->exec("CREATE TABLE IF NOT EXISTS employee_academic_records (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            employee_id VARCHAR(20) COLLATE utf8mb4_unicode_ci NOT NULL,
            degree_level VARCHAR(100) COLLATE utf8mb4_unicode_ci NOT NULL,
            institution VARCHAR(255) COLLATE utf8mb4_unicode_ci NOT NULL,
            field_of_study VARCHAR(255) COLLATE utf8mb4_unicode_ci NULL,
            graduation_year SMALLINT UNSIGNED NULL,
            grade VARCHAR(50) COLLATE utf8mb4_unicode_ci NULL,
            remarks TEXT COLLATE utf8mb4_unicode_ci NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            KEY idx_academic_employee (employee_id),
            CONSTRAINT fk_academic_employee FOREIGN KEY (employee_id) REFERENCES employees(emp_id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $pdo->exec("CREATE TABLE IF NOT EXISTS employee_experience_records (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            employee_id VARCHAR(20) COLLATE utf8mb4_unicode_ci NOT NULL,
            organization VARCHAR(255) COLLATE utf8mb4_unicode_ci NOT NULL,
            job_title VARCHAR(255) COLLATE utf8mb4_unicode_ci NOT NULL,
            start_date DATE NULL,
            end_date DATE NULL,
            responsibilities TEXT COLLATE utf8mb4_unicode_ci NULL,
            achievements TEXT COLLATE utf8mb4_unicode_ci NULL,
            currently_working TINYINT(1) NOT NULL DEFAULT 0,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            KEY idx_experience_employee (employee_id),
            KEY idx_experience_dates (start_date, end_date),
            CONSTRAINT fk_experience_employee FOREIGN KEY (employee_id) REFERENCES employees(emp_id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    },

    'down' => function ($pdo) {
        $pdo->exec("DROP TABLE IF EXISTS employee_experience_records");
        $pdo->exec("DROP TABLE IF EXISTS employee_academic_records");
    }
];
