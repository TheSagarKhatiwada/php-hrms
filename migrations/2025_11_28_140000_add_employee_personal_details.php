<?php
/**
 * Migration: add_employee_personal_details
 * Description: Adds extended family, emergency contact, health, and address fields to employees.
 * Created: 2025-11-28 14:00:00
 */

return [
    'up' => function ($pdo) {
        $columns = [
            'father_name' => "ALTER TABLE employees ADD COLUMN father_name VARCHAR(150) NULL AFTER allow_web_attendance",
            'mother_name' => "ALTER TABLE employees ADD COLUMN mother_name VARCHAR(150) NULL AFTER father_name",
            'spouse_name' => "ALTER TABLE employees ADD COLUMN spouse_name VARCHAR(150) NULL AFTER mother_name",
            'marital_status' => "ALTER TABLE employees ADD COLUMN marital_status VARCHAR(50) NULL AFTER spouse_name",
            'emergency_contact_name' => "ALTER TABLE employees ADD COLUMN emergency_contact_name VARCHAR(150) NULL AFTER marital_status",
            'emergency_contact_relationship' => "ALTER TABLE employees ADD COLUMN emergency_contact_relationship VARCHAR(100) NULL AFTER emergency_contact_name",
            'emergency_contact_phone' => "ALTER TABLE employees ADD COLUMN emergency_contact_phone VARCHAR(25) NULL AFTER emergency_contact_relationship",
            'emergency_contact_email' => "ALTER TABLE employees ADD COLUMN emergency_contact_email VARCHAR(150) NULL AFTER emergency_contact_phone",
            'blood_group' => "ALTER TABLE employees ADD COLUMN blood_group VARCHAR(8) NULL AFTER emergency_contact_email",
            'allergies' => "ALTER TABLE employees ADD COLUMN allergies TEXT NULL AFTER blood_group",
            'medical_conditions' => "ALTER TABLE employees ADD COLUMN medical_conditions TEXT NULL AFTER allergies",
            'medical_notes' => "ALTER TABLE employees ADD COLUMN medical_notes TEXT NULL AFTER medical_conditions",
            'current_address' => "ALTER TABLE employees ADD COLUMN current_address TEXT NULL AFTER medical_notes",
            'current_city' => "ALTER TABLE employees ADD COLUMN current_city VARCHAR(100) NULL AFTER current_address",
            'current_district' => "ALTER TABLE employees ADD COLUMN current_district VARCHAR(100) NULL AFTER current_city",
            'current_state' => "ALTER TABLE employees ADD COLUMN current_state VARCHAR(100) NULL AFTER current_district",
            'current_postal_code' => "ALTER TABLE employees ADD COLUMN current_postal_code VARCHAR(20) NULL AFTER current_state",
            'current_country' => "ALTER TABLE employees ADD COLUMN current_country VARCHAR(100) NULL AFTER current_postal_code",
            'permanent_address' => "ALTER TABLE employees ADD COLUMN permanent_address TEXT NULL AFTER current_country",
            'permanent_city' => "ALTER TABLE employees ADD COLUMN permanent_city VARCHAR(100) NULL AFTER permanent_address",
            'permanent_district' => "ALTER TABLE employees ADD COLUMN permanent_district VARCHAR(100) NULL AFTER permanent_city",
            'permanent_state' => "ALTER TABLE employees ADD COLUMN permanent_state VARCHAR(100) NULL AFTER permanent_district",
            'permanent_postal_code' => "ALTER TABLE employees ADD COLUMN permanent_postal_code VARCHAR(20) NULL AFTER permanent_state",
            'permanent_country' => "ALTER TABLE employees ADD COLUMN permanent_country VARCHAR(100) NULL AFTER permanent_postal_code"
        ];

        foreach ($columns as $name => $ddl) {
            try {
                $stmt = $pdo->prepare("SELECT COUNT(*) AS cnt FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'employees' AND COLUMN_NAME = :column");
                $stmt->execute([':column' => $name]);
                $exists = (int)($stmt->fetch(PDO::FETCH_ASSOC)['cnt'] ?? 0) > 0;
                if (!$exists) {
                    $pdo->exec($ddl);
                }
            } catch (Throwable $e) {
                // ignore to keep migration chain resilient
            }
        }
    },

    'down' => function ($pdo) {
        $columns = [
            'permanent_country',
            'permanent_postal_code',
            'permanent_state',
            'permanent_district',
            'permanent_city',
            'permanent_address',
            'current_country',
            'current_postal_code',
            'current_state',
            'current_district',
            'current_city',
            'current_address',
            'medical_notes',
            'medical_conditions',
            'allergies',
            'blood_group',
            'emergency_contact_email',
            'emergency_contact_phone',
            'emergency_contact_relationship',
            'emergency_contact_name',
            'marital_status',
            'spouse_name',
            'mother_name',
            'father_name'
        ];

        foreach ($columns as $name) {
            try {
                $pdo->exec("ALTER TABLE employees DROP COLUMN $name");
            } catch (Throwable $e) {
                // ignore
            }
        }
    }
];
