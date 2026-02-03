<?php
require_once 'includes/db_connection.php';

try {
    // Disable foreign key checks to avoid constraint errors during drop
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");

    $tables = [
        'salary_deductions',
        'employee_salaries',
        'salary_components',
        'salary_topics'
    ];

    foreach ($tables as $table) {
        $pdo->exec("DROP TABLE IF EXISTS $table");
        echo "Dropped table: $table\n";
    }

    // Re-enable foreign key checks
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");

    echo "Legacy tables cleanup completed.\n";

} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
