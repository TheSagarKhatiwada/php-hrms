<?php
/**
 * Migration: add_user_permissions
 * Adds a user_permissions table for per-user grants/revokes (hybrid RBAC + direct permissions)
 */
return [
    'up' => function (PDO $pdo) {
        $sql = "CREATE TABLE IF NOT EXISTS user_permissions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            permission_id INT NOT NULL,
            allowed TINYINT(1) NOT NULL DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY ux_user_permission (user_id, permission_id),
            INDEX idx_user_id (user_id),
            INDEX idx_permission_id (permission_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        $pdo->exec($sql);
    },

    'down' => function (PDO $pdo) {
        $pdo->exec('DROP TABLE IF EXISTS user_permissions');
    },
];
