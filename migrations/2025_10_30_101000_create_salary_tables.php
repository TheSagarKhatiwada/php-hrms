<?php
/**
 * Migration: create salary tables
 * Creates salary_topics, salary_components, salary_deductions, employee_salaries
 */

return [
    'up' => function($pdo) {
        // salary_topics
        $pdo->exec("CREATE TABLE IF NOT EXISTS salary_topics (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(255) NOT NULL,
            type ENUM('earning','deduction') NOT NULL DEFAULT 'earning',
            description TEXT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY ux_salary_topic_name (name)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

        // salary_components (link to topics, e.g., basic, hra, etc.)
        $pdo->exec("CREATE TABLE IF NOT EXISTS salary_components (
            id INT AUTO_INCREMENT PRIMARY KEY,
            topic_id INT NOT NULL,
            code VARCHAR(100) NULL,
            default_amount DECIMAL(12,2) NULL,
            is_percentage TINYINT(1) DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (topic_id) REFERENCES salary_topics(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

        // employee_salaries (assignments of salary for employee)
        $pdo->exec("CREATE TABLE IF NOT EXISTS employee_salaries (
            id INT AUTO_INCREMENT PRIMARY KEY,
            employee_id INT NOT NULL,
            effective_from DATE NOT NULL,
            effective_to DATE NULL,
            gross DECIMAL(12,2) NULL,
            net DECIMAL(12,2) NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

        // salary_deductions (per employee_salary or standalone)
        $pdo->exec("CREATE TABLE IF NOT EXISTS salary_deductions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            employee_salary_id INT NULL,
            deduction_topic_id INT NULL,
            amount DECIMAL(12,2) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (employee_salary_id) REFERENCES employee_salaries(id) ON DELETE CASCADE,
            FOREIGN KEY (deduction_topic_id) REFERENCES salary_topics(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
    },

    'down' => function($pdo) {
        $pdo->exec("DROP TABLE IF EXISTS salary_deductions");
        $pdo->exec("DROP TABLE IF EXISTS employee_salaries");
        $pdo->exec("DROP TABLE IF EXISTS salary_components");
        $pdo->exec("DROP TABLE IF EXISTS salary_topics");
    }
];
