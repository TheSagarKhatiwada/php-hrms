<?php
/**
 * Configuration Test Script
 * Use this to diagnose configuration issues
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>PHP HRMS Configuration Test</h1>";

// Test 1: Check if config file exists
$configFile = __DIR__ . '/includes/config.php';
echo "<h2>Test 1: Config File Check</h2>";
echo "Config file path: " . $configFile . "<br>";
echo "File exists: " . (file_exists($configFile) ? "✅ YES" : "❌ NO") . "<br>";
echo "File readable: " . (is_readable($configFile) ? "✅ YES" : "❌ NO") . "<br>";

if (!file_exists($configFile)) {
    echo "<p style='color: red;'>❌ Config file not found! This is the main issue.</p>";
    echo "<p>Please ensure the config.php file exists in the includes/ directory.</p>";
    exit;
}

// Test 2: Include and test config
echo "<h2>Test 2: Config Loading</h2>";
try {
    define('INCLUDE_CHECK', true);
    include $configFile;
    
    echo "Config file included: ✅ SUCCESS<br>";
    
    if (isset($DB_CONFIG)) {
        echo "DB_CONFIG variable found: ✅ YES<br>";
        echo "DB_CONFIG is array: " . (is_array($DB_CONFIG) ? "✅ YES" : "❌ NO") . "<br>";
        
        if (is_array($DB_CONFIG)) {
            echo "<h3>DB_CONFIG Contents:</h3>";
            echo "<pre>";
            $safe_config = $DB_CONFIG;
            $safe_config['pass'] = '***HIDDEN***'; // Hide password
            print_r($safe_config);
            echo "</pre>";
            
            // Check required fields
            $required = ['host', 'name', 'user', 'pass'];
            $missing = [];
            foreach ($required as $field) {
                if (!isset($DB_CONFIG[$field])) {
                    $missing[] = $field;
                }
            }
            
            if (empty($missing)) {
                echo "All required fields present: ✅ YES<br>";
            } else {
                echo "Missing fields: ❌ " . implode(', ', $missing) . "<br>";
            }
        }
    } else {
        echo "DB_CONFIG variable found: ❌ NO<br>";
        echo "<p style='color: red;'>The config.php file was included but \$DB_CONFIG variable is not set!</p>";
    }
    
} catch (Exception $e) {
    echo "❌ Error including config: " . $e->getMessage() . "<br>";
}

// Test 3: DatabaseInstaller test
echo "<h2>Test 3: DatabaseInstaller Test</h2>";
try {
    require_once 'includes/DatabaseInstaller.php';
    $testResult = DatabaseInstaller::testConfigFile();
    
    echo "<h3>DatabaseInstaller Test Results:</h3>";
    echo "<pre>";
    print_r($testResult);
    echo "</pre>";
    
    if ($testResult['db_config_valid']) {
        echo "✅ DatabaseInstaller can load config successfully!<br>";
        
        // Test the checkRequirements method
        echo "<h3>Testing checkRequirements Method:</h3>";
        try {
            $installer = new DatabaseInstaller();
            $requirements = $installer->checkRequirements();
            echo "<pre>";
            print_r($requirements);
            echo "</pre>";
            echo "✅ checkRequirements method works correctly!<br>";
        } catch (Exception $e) {
            echo "❌ checkRequirements method failed: " . $e->getMessage() . "<br>";
        }
        
    } else {
        echo "❌ DatabaseInstaller config test failed: " . ($testResult['error'] ?? 'Unknown error') . "<br>";
    }
    
} catch (Exception $e) {
    echo "❌ DatabaseInstaller test failed: " . $e->getMessage() . "<br>";
}

// Test 4: Setup.php requirements structure test
echo "<h2>Test 4: Setup Requirements Structure</h2>";
define('INCLUDE_CHECK', true);
try {
    // Simulate what setup.php does
    $step = 1;
    $requirements = [];
    if ($step == 1) {
        try {
            $installer = new DatabaseInstaller();
            $requirements = $installer->checkRequirements();
            echo "✅ Requirements loaded successfully<br>";
        } catch (Exception $e) {
            echo "❌ Requirements loading failed, using fallback structure<br>";
            $requirements = [
                'config_file' => [
                    'required' => 'Valid config.php',
                    'current' => 'Error: ' . $e->getMessage(),
                    'status' => false
                ],
                'php_version' => [
                    'required' => '7.4.0',
                    'current' => PHP_VERSION,
                    'status' => version_compare(PHP_VERSION, '7.4.0', '>=')
                ]
            ];
        }
    }
    
    echo "<h3>Requirements Structure:</h3>";
    echo "<pre>";
    print_r($requirements);
    echo "</pre>";
    
    // Test the template logic
    echo "<h3>Template Logic Test:</h3>";
    foreach ($requirements as $req => $info) {
        echo "<strong>" . ucwords(str_replace('_', ' ', $req)) . "</strong><br>";
        $required = is_bool($info['required'] ?? false) ? (($info['required'] ?? false) ? 'Yes' : 'No') : ($info['required'] ?? 'N/A');
        $current = is_bool($info['current'] ?? false) ? (($info['current'] ?? false) ? 'Yes' : 'No') : ($info['current'] ?? 'N/A');
        $status = ($info['status'] ?? false) ? 'PASS' : 'FAIL';
        
        echo "Required: $required | Current: $current | Status: $status<br><br>";
    }
    
} catch (Exception $e) {
    echo "❌ Setup requirements test failed: " . $e->getMessage() . "<br>";
}

echo "<h2>Recommendations:</h2>";
if (file_exists($configFile) && isset($DB_CONFIG) && is_array($DB_CONFIG)) {
    echo "<p style='color: green;'>✅ Configuration appears to be working. The issue might be elsewhere.</p>";
} else {
    echo "<p style='color: red;'>❌ Configuration issue detected. Please check the errors above.</p>";
}

echo "<p><a href='setup.php'>← Back to Setup</a></p>";
?>
