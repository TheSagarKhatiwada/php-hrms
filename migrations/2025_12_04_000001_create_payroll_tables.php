<?php
return [
    'up' => function($pdo) {
        // 1. Salary Components Table
        $sql = "CREATE TABLE IF NOT EXISTS salary_components (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(100) NOT NULL,
            type ENUM('earning', 'deduction') NOT NULL,
            is_taxable TINYINT(1) NOT NULL DEFAULT 1,
            status ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
        $pdo->exec($sql);

        // 2. Employee Salary Structures Table
        $sql = "CREATE TABLE IF NOT EXISTS employee_salary_structures (
            id INT AUTO_INCREMENT PRIMARY KEY,
            employee_id VARCHAR(20) COLLATE utf8mb4_unicode_ci NOT NULL,
            component_id INT NOT NULL,
            amount DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
            effective_date DATE NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (employee_id) REFERENCES employees(emp_id) ON DELETE CASCADE,
            FOREIGN KEY (component_id) REFERENCES salary_components(id) ON DELETE RESTRICT,
            UNIQUE KEY unique_emp_comp (employee_id, component_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
        $pdo->exec($sql);

        // 3. Payroll Runs Table
        $sql = "CREATE TABLE IF NOT EXISTS payroll_runs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            month INT NOT NULL,
            year INT NOT NULL,
            processed_date DATETIME NOT NULL,
            status ENUM('draft', 'finalized') NOT NULL DEFAULT 'draft',
            processed_by VARCHAR(20) COLLATE utf8mb4_unicode_ci NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (processed_by) REFERENCES employees(emp_id) ON DELETE SET NULL,
            UNIQUE KEY unique_run (month, year)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
        $pdo->exec($sql);

        // 4. Payroll Details Table
        $sql = "CREATE TABLE IF NOT EXISTS payroll_details (
            id INT AUTO_INCREMENT PRIMARY KEY,
            payroll_run_id INT NOT NULL,
            employee_id VARCHAR(20) COLLATE utf8mb4_unicode_ci NOT NULL,
            gross_salary DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
            total_deductions DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
            net_salary DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
            payable_days DECIMAL(4, 1) NOT NULL DEFAULT 0.0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (payroll_run_id) REFERENCES payroll_runs(id) ON DELETE CASCADE,
            FOREIGN KEY (employee_id) REFERENCES employees(emp_id) ON DELETE CASCADE,
            UNIQUE KEY unique_run_emp (payroll_run_id, employee_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
        $pdo->exec($sql);

        // 5. Payroll Items Table (Line items for each payslip)
        $sql = "CREATE TABLE IF NOT EXISTS payroll_items (
            id INT AUTO_INCREMENT PRIMARY KEY,
            payroll_detail_id INT NOT NULL,
            component_name VARCHAR(100) NOT NULL,
            component_type ENUM('earning', 'deduction') NOT NULL,
            amount DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (payroll_detail_id) REFERENCES payroll_details(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
        $pdo->exec($sql);
    },
    'down' => function($pdo) {
        $pdo->exec("DROP TABLE IF EXISTS payroll_items");
        $pdo->exec("DROP TABLE IF EXISTS payroll_details");
        $pdo->exec("DROP TABLE IF EXISTS payroll_runs");
        $pdo->exec("DROP TABLE IF EXISTS employee_salary_structures");
        $pdo->exec("DROP TABLE IF EXISTS salary_components");
    }
];
