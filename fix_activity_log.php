<?php
require_once 'includes/db_connection.php';

try {
    $sql = "CREATE TABLE IF NOT EXISTS activity_log (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id VARCHAR(20) COLLATE utf8mb4_unicode_ci NULL,
        action VARCHAR(255) COLLATE utf8mb4_unicode_ci NOT NULL,
        details TEXT COLLATE utf8mb4_unicode_ci NULL,
        ip_address VARCHAR(45) COLLATE utf8mb4_unicode_ci NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES employees(emp_id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
    
    $pdo->exec($sql);
    echo "activity_log table created successfully.\n";
} catch (PDOException $e) {
    echo "Error creating table: " . $e->getMessage() . "\n";
}
