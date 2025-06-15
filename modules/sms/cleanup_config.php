<?php
/**
 * Cleanup script to remove unused SMS configuration fields
 * 
 * This script removes the following unused SMS config fields:
 * - sms_enabled
 * - sms_auto_attendance  
 * - sms_attendance_time
 * - sms_low_credit_alert
 * - sms_daily_limit
 */

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/db_connection.php';

echo "=== SMS Configuration Cleanup ===\n";
echo "Removing unused SMS configuration fields...\n\n";

try {
    // Start transaction
    $pdo->beginTransaction();
    
    // List of unused configuration keys to remove
    $unusedKeys = [
        'sms_enabled',
        'sms_auto_attendance', 
        'sms_attendance_time',
        'sms_low_credit_alert',
        'sms_daily_limit'
    ];
    
    echo "Removing unused configuration fields:\n";
    
    foreach ($unusedKeys as $key) {
        // Check if the key exists
        $stmt = $pdo->prepare("SELECT config_key FROM sms_config WHERE config_key = ?");
        $stmt->execute([$key]);
        
        if ($stmt->fetch()) {
            // Delete the configuration
            $stmt = $pdo->prepare("DELETE FROM sms_config WHERE config_key = ?");
            $result = $stmt->execute([$key]);
            
            if ($result) {
                echo "   ✓ Removed: '$key'\n";
            } else {
                echo "   ✗ Failed to remove: '$key'\n";
            }
        } else {
            echo "   - Not found: '$key' (already removed)\n";
        }
    }
    
    // Commit transaction
    $pdo->commit();
    
    echo "\n=== Cleanup Completed Successfully ===\n";
    echo "Current SMS configuration fields:\n";
    
    // Display remaining configuration
    $stmt = $pdo->query("SELECT config_key, config_value, description FROM sms_config ORDER BY config_key");
    $configs = $stmt->fetchAll();
    
    if (empty($configs)) {
        echo "  (No SMS configuration fields found)\n";
    } else {
        foreach ($configs as $config) {
            $value = $config['config_value'] ?: '(empty)';
            echo "  • {$config['config_key']}: $value\n";
            echo "    Description: {$config['description']}\n\n";
        }
    }
    
    echo "SMS configuration now contains only the essential SparrowSMS API fields:\n";
    echo "1. api_token - Your SparrowSMS API token\n";
    echo "2. sender_identity - Your approved sender identity\n";
    echo "3. api_endpoint - SparrowSMS API endpoint URL\n";
    
} catch (Exception $e) {
    // Rollback transaction on error
    if ($pdo->inTransaction()) {
        $pdo->rollback();
    }
    
    echo "ERROR: Cleanup failed - " . $e->getMessage() . "\n";
    echo "No changes have been made to the database.\n";
    exit(1);
}
?>
