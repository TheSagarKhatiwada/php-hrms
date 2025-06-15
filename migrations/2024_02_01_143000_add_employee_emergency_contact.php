<?php
/**
 * Migration: add_employee_emergency_contact
 * Description: Add emergency contact information to employees
 * Created: 2024-02-01 14:30:00
 */

return [
    'up' => function($pdo) {
        // Add emergency contact fields to employees table
        $pdo->exec("ALTER TABLE employees ADD COLUMN emergency_contact_name VARCHAR(255) DEFAULT NULL AFTER country");
        $pdo->exec("ALTER TABLE employees ADD COLUMN emergency_contact_phone VARCHAR(20) DEFAULT NULL AFTER emergency_contact_name");
        $pdo->exec("ALTER TABLE employees ADD COLUMN emergency_contact_relationship VARCHAR(100) DEFAULT NULL AFTER emergency_contact_phone");
        
        // Create emergency contacts table for multiple contacts
        $pdo->exec("CREATE TABLE IF NOT EXISTS employee_emergency_contacts (
            id INT AUTO_INCREMENT PRIMARY KEY,
            employee_id INT NOT NULL,
            contact_name VARCHAR(255) NOT NULL,
            contact_phone VARCHAR(20) NOT NULL,
            relationship VARCHAR(100) NOT NULL,
            address TEXT DEFAULT NULL,
            is_primary TINYINT(1) DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
            INDEX idx_employee_id (employee_id),
            INDEX idx_is_primary (is_primary)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
    },
    
    'down' => function($pdo) {
        // Drop emergency contacts table
        $pdo->exec("DROP TABLE IF EXISTS employee_emergency_contacts");
        
        // Remove emergency contact fields from employees table
        $pdo->exec("ALTER TABLE employees DROP COLUMN emergency_contact_name");
        $pdo->exec("ALTER TABLE employees DROP COLUMN emergency_contact_phone");
        $pdo->exec("ALTER TABLE employees DROP COLUMN emergency_contact_relationship");
    }
];
