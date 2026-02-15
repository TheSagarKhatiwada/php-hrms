<?php
/**
 * Migration: create_mobile_auth_and_wifi
 * Description: Adds mobile access tokens, device tokens, and branch Wi-Fi access points.
 */

define('MIGRATION_NAME', '2026_02_06_090000_create_mobile_auth_and_wifi');

function up(PDO $pdo) {
    $pdo->exec("CREATE TABLE IF NOT EXISTS mobile_access_tokens (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        employee_id VARCHAR(20) NOT NULL,
        token_hash CHAR(64) NOT NULL,
        device_id VARCHAR(128) NULL,
        device_name VARCHAR(128) NULL,
        ip_address VARCHAR(64) NULL,
        user_agent VARCHAR(255) NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        last_seen_at TIMESTAMP NULL DEFAULT NULL,
        expires_at TIMESTAMP NOT NULL,
        revoked_at TIMESTAMP NULL DEFAULT NULL,
        PRIMARY KEY (id),
        UNIQUE KEY uniq_token_hash (token_hash),
        KEY idx_employee_id (employee_id),
        KEY idx_expires_at (expires_at),
        CONSTRAINT fk_mobile_tokens_employee FOREIGN KEY (employee_id) REFERENCES employees(emp_id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    $pdo->exec("CREATE TABLE IF NOT EXISTS mobile_device_tokens (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        employee_id VARCHAR(20) NOT NULL,
        device_id VARCHAR(128) NULL,
        token VARCHAR(255) NOT NULL,
        platform ENUM('android','ios') NOT NULL DEFAULT 'android',
        is_active TINYINT(1) NOT NULL DEFAULT 1,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY uniq_device_token (token),
        KEY idx_employee_device (employee_id, device_id),
        CONSTRAINT fk_mobile_device_tokens_employee FOREIGN KEY (employee_id) REFERENCES employees(emp_id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    $pdo->exec("CREATE TABLE IF NOT EXISTS branch_wifi_access_points (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        branch_id INT(11) NOT NULL,
        ssid VARCHAR(128) NULL,
        bssid VARCHAR(32) NULL,
        is_active TINYINT(1) NOT NULL DEFAULT 1,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY idx_branch (branch_id),
        KEY idx_bssid (bssid),
        CONSTRAINT fk_branch_wifi_branch FOREIGN KEY (branch_id) REFERENCES branches(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
}

function down(PDO $pdo) {
    $pdo->exec("DROP TABLE IF EXISTS branch_wifi_access_points");
    $pdo->exec("DROP TABLE IF EXISTS mobile_device_tokens");
    $pdo->exec("DROP TABLE IF EXISTS mobile_access_tokens");
}
