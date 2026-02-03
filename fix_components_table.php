<?php
require_once 'includes/db_connection.php';

try {
    // Check if 'type' column exists
    $stmt = $pdo->query("SHOW COLUMNS FROM salary_components LIKE 'type'");
    if ($stmt->rowCount() == 0) {
        echo "Adding 'type' column...\n";
        $pdo->exec("ALTER TABLE salary_components ADD COLUMN type ENUM('earning', 'deduction') NOT NULL AFTER name");
    }

    // Check if 'is_taxable' column exists
    $stmt = $pdo->query("SHOW COLUMNS FROM salary_components LIKE 'is_taxable'");
    if ($stmt->rowCount() == 0) {
        echo "Adding 'is_taxable' column...\n";
        $pdo->exec("ALTER TABLE salary_components ADD COLUMN is_taxable TINYINT(1) NOT NULL DEFAULT 1 AFTER type");
    }

    // Check if 'status' column exists
    $stmt = $pdo->query("SHOW COLUMNS FROM salary_components LIKE 'status'");
    if ($stmt->rowCount() == 0) {
        echo "Adding 'status' column...\n";
        $pdo->exec("ALTER TABLE salary_components ADD COLUMN status ENUM('active', 'inactive') NOT NULL DEFAULT 'active' AFTER is_taxable");
    }

    echo "salary_components table schema fixed.\n";

} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
