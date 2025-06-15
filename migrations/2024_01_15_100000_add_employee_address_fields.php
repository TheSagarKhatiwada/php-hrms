<?php
/**
 * Migration: add_employee_address_fields
 * Description: Add address fields to employees table
 * Created: 2024-01-15 10:00:00
 */

return [
    'up' => function($pdo) {
        // Add address fields to employees table
        $pdo->exec("ALTER TABLE employees ADD COLUMN street_address VARCHAR(255) DEFAULT NULL AFTER phone");
        $pdo->exec("ALTER TABLE employees ADD COLUMN city VARCHAR(100) DEFAULT NULL AFTER street_address");
        $pdo->exec("ALTER TABLE employees ADD COLUMN state_province VARCHAR(100) DEFAULT NULL AFTER city");
        $pdo->exec("ALTER TABLE employees ADD COLUMN postal_code VARCHAR(20) DEFAULT NULL AFTER state_province");
        $pdo->exec("ALTER TABLE employees ADD COLUMN country VARCHAR(100) DEFAULT 'Nepal' AFTER postal_code");
    },
    
    'down' => function($pdo) {
        // Remove address fields from employees table
        $pdo->exec("ALTER TABLE employees DROP COLUMN street_address");
        $pdo->exec("ALTER TABLE employees DROP COLUMN city");
        $pdo->exec("ALTER TABLE employees DROP COLUMN state_province");
        $pdo->exec("ALTER TABLE employees DROP COLUMN postal_code");
        $pdo->exec("ALTER TABLE employees DROP COLUMN country");
    }
];
