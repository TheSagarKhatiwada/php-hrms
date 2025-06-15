<?php
/**
 * Migration script to update SMS configuration keys to match SparrowSMS API requirements
 * 
 * This script updates the SMS configuration to use the correct field names
 * based on SparrowSMS API documentation at https://docs.sparrowsms.com/
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db_connection.php';

echo "=== SMS Configuration Migration ===\n";
echo "Updating SMS config fields to match SparrowSMS API requirements...\n\n";

try {
    // Start transaction
    $pdo->beginTransaction();
    
    echo "1. Updating configuration keys...\n";
    
    // Map old keys to new keys
    $keyMappings = [
        'sms_token' => 'api_token',
        'sms_from' => 'sender_identity'
    ];
    
    foreach ($keyMappings as $oldKey => $newKey) {
        // Check if old key exists
        $stmt = $pdo->prepare("SELECT config_value, description FROM sms_config WHERE config_key = ?");
        $stmt->execute([$oldKey]);
        $oldConfig = $stmt->fetch();
        
        if ($oldConfig) {
            echo "   - Migrating '$oldKey' to '$newKey'...\n";
            
            // Update the description for the new key
            $newDescription = '';
            if ($newKey === 'api_token') {
                $newDescription = 'SparrowSMS API Token (Required - Get from sparrowsms.com dashboard)';
            } elseif ($newKey === 'sender_identity') {
                $newDescription = 'Sender Identity provided by SparrowSMS (Required)';
            } else {
                $newDescription = $oldConfig['description'];
            }
            
            // Insert or update the new key
            $stmt = $pdo->prepare("
                INSERT INTO sms_config (config_key, config_value, description) 
                VALUES (?, ?, ?) 
                ON DUPLICATE KEY UPDATE 
                config_value = VALUES(config_value), 
                description = VALUES(description)
            ");
            $stmt->execute([$newKey, $oldConfig['config_value'], $newDescription]);
            
            // Delete the old key
            $stmt = $pdo->prepare("DELETE FROM sms_config WHERE config_key = ?");
            $stmt->execute([$oldKey]);
            
            echo "   ✓ Successfully migrated '$oldKey' to '$newKey'\n";
        } else {
            echo "   - '$oldKey' not found, skipping...\n";
        }
    }
    
    echo "\n2. Adding new configuration fields...\n";
    
    // Add new configuration fields if they don't exist
    $newConfigs = [
        ['api_endpoint', 'https://api.sparrowsms.com/v2/sms/', 'SparrowSMS API Endpoint URL']
    ];
    
    foreach ($newConfigs as $config) {
        $stmt = $pdo->prepare("
            INSERT IGNORE INTO sms_config (config_key, config_value, description) 
            VALUES (?, ?, ?)
        ");
        $result = $stmt->execute($config);
        
        if ($result && $stmt->rowCount() > 0) {
            echo "   ✓ Added new config: '{$config[0]}'\n";
        } else {
            echo "   - Config '{$config[0]}' already exists, skipping...\n";
        }
    }
    
    echo "\n3. Updating descriptions for existing configs...\n";
    
    // Update descriptions for existing configs
    $descriptionUpdates = [
        ['sms_enabled', 'Enable/Disable SMS functionality (0=Disabled, 1=Enabled)']
    ];
    
    foreach ($descriptionUpdates as $update) {
        $stmt = $pdo->prepare("UPDATE sms_config SET description = ? WHERE config_key = ?");
        $result = $stmt->execute([$update[1], $update[0]]);
        
        if ($result && $stmt->rowCount() > 0) {
            echo "   ✓ Updated description for '{$update[0]}'\n";
        } else {
            echo "   - '{$update[0]}' not found or description unchanged\n";
        }
    }
    
    // Commit transaction
    $pdo->commit();
    
    echo "\n=== Migration Completed Successfully ===\n";
    echo "Current SMS configuration fields:\n";
    
    // Display current configuration
    $stmt = $pdo->query("SELECT config_key, config_value, description FROM sms_config ORDER BY config_key");
    $configs = $stmt->fetchAll();
    
    foreach ($configs as $config) {
        $value = $config['config_value'] ?: '(empty)';
        echo "  • {$config['config_key']}: $value\n";
        echo "    Description: {$config['description']}\n\n";
    }
    
    echo "Next steps:\n";
    echo "1. Get your API token from https://web.sparrowsms.com/\n";
    echo "2. Get your sender identity from SparrowSMS\n";
    echo "3. Update the 'api_token' and 'sender_identity' values in SMS configuration\n";
    echo "4. Enable SMS functionality by setting 'sms_enabled' to '1'\n";
    
} catch (Exception $e) {
    // Rollback transaction on error
    if ($pdo->inTransaction()) {
        $pdo->rollback();
    }
    
    echo "ERROR: Migration failed - " . $e->getMessage() . "\n";
    echo "No changes have been made to the database.\n";
    exit(1);
}
?>
