<?php
/**
 * Settings Helper Functions
 * This file provides functions to access system settings throughout the application
 */

// Include database connection if not already included
if (!isset($pdo)) {
    @include_once 'db_connection.php';
}

// Get a setting value from the database
if (!function_exists('get_setting')) {
    function get_setting($key, $default = null) {
        global $pdo;
        
        // If database connection is not available, return default value
        if (!isset($pdo) || $pdo === null) {
            return $default;
        }
        
        try {
            $stmt = $pdo->prepare("SELECT value FROM settings WHERE setting_key = ?");
            $stmt->execute([$key]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($result) {
                return $result['value'];
            }
        } catch (PDOException $e) {
            // Log error if needed
            error_log("Error fetching setting: " . $e->getMessage());
        }
        
        return $default;
    }
}

// Save a setting to the database
if (!function_exists('save_setting')) {
    function save_setting($key, $value) {
        global $pdo;
        
        // If database connection is not available, return false
        if (!isset($pdo) || $pdo === null) {
            return false;
        }
        
        try {
            // Check if setting exists
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM settings WHERE setting_key = ?");
            $stmt->execute([$key]);
            $exists = $stmt->fetchColumn() > 0;
            
            if ($exists) {
                // Update existing setting
                $stmt = $pdo->prepare("UPDATE settings SET value = ?, modified_at = NOW() WHERE setting_key = ?");
                $stmt->execute([$value, $key]);
            } else {
                // Insert new setting
                $stmt = $pdo->prepare("INSERT INTO settings (setting_key, value, created_at, modified_at) VALUES (?, ?, NOW(), NOW())");
                $stmt->execute([$key, $value]);
            }
            
            return true;
        } catch (PDOException $e) {
            error_log("Error saving setting: " . $e->getMessage());
            return false;
        }
    }
}

// Get all settings as an associative array
if (!function_exists('get_all_settings')) {
    function get_all_settings() {
        global $pdo;
        $settings = [];
        
        // If database connection is not available, return empty array
        if (!isset($pdo) || $pdo === null) {
            return $settings;
        }
        
        try {
            $stmt = $pdo->prepare("SELECT setting_key, value FROM settings");
            $stmt->execute();
            
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $settings[$row['setting_key']] = $row['value'];
            }
        } catch (PDOException $e) {
            error_log("Error fetching all settings: " . $e->getMessage());
        }
        
        return $settings;
    }
}

// Load common settings
if (!function_exists('load_common_settings')) {
    function load_common_settings() {
        // Set default timezone from settings
        $timezone = get_setting('timezone', 'Europe/London');
        date_default_timezone_set($timezone);
        
        // Define commonly used constants
        if (!defined('APP_NAME')) {
            define('APP_NAME', get_setting('app_name', 'Sample App'));
        }
        
        if (!defined('COMPANY_NAME')) {
            define('COMPANY_NAME', get_setting('company_name', 'Sample Company'));
        }
        
        if (!defined('COMPANY_FULL_NAME')) {
            define('COMPANY_FULL_NAME', get_setting('company_full_name', 'Sample Company Pvt. Ltd.'));
        }
        
        if (!defined('COMPANY_ADDRESS')) {
            define('COMPANY_ADDRESS', get_setting('company_address', 'Company Address'));
        }
        
        if (!defined('COMPANY_PHONE')) {
            define('COMPANY_PHONE', get_setting('company_phone', '+977-1-1234567'));
        }
        
        if (!defined('COMPANY_LOGO')) {
            define('COMPANY_LOGO', get_setting('company_logo', 'company_logo.png'));
        }
        
        if (!defined('PRIMARY_COLOR')) {
            define('PRIMARY_COLOR', get_setting('company_primary_color', '#007bff'));
        }
        
        if (!defined('SECONDARY_COLOR')) {
            define('SECONDARY_COLOR', get_setting('company_secondary_color', '#6c757d'));
        }
        
        if (!defined('WORK_HOURS')) {
            define('WORK_HOURS', get_setting('company_work_hour', '9:00 AM - 5:00 PM'));
        }
    }
}

// Load common settings when this file is included
load_common_settings();