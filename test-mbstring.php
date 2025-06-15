<?php
/**
 * Test mbstring fallback functionality
 */

echo "<h1>mbstring Extension Test</h1>";

echo "<h2>Extension Status:</h2>";
echo "mbstring extension loaded: " . (extension_loaded('mbstring') ? "✅ YES" : "❌ NO") . "<br>";
echo "iconv function available: " . (function_exists('iconv') ? "✅ YES" : "❌ NO") . "<br>";

echo "<h2>Encoding Test:</h2>";
$test_content = "Hello World! This is a test.";
echo "Test content: $test_content<br>";

// Test the UTF-8 validation without mbstring
if (extension_loaded('mbstring')) {
    echo "Using mbstring: " . (mb_check_encoding($test_content, 'UTF-8') ? "✅ Valid UTF-8" : "❌ Invalid UTF-8") . "<br>";
} else {
    echo "Using fallback: " . (preg_match('//u', $test_content) ? "✅ Valid UTF-8" : "❌ Invalid UTF-8") . "<br>";
}

echo "<h2>File Read Test:</h2>";
try {
    require_once 'includes/DatabaseInstaller.php';
    $installer = new DatabaseInstaller();
    
    // Test reading the schema file
    $schemaFile = __DIR__ . '/schema/hrms_schema.sql';
    if (file_exists($schemaFile)) {
        echo "Schema file exists: ✅ YES<br>";
        echo "File size: " . filesize($schemaFile) . " bytes<br>";
        
        // This should work now without mbstring errors
        $reflection = new ReflectionClass($installer);
        $method = $reflection->getMethod('readSqlFile');
        $method->setAccessible(true);
        
        $content = $method->invoke($installer, $schemaFile);
        if ($content !== false) {
            echo "File read successfully: ✅ YES<br>";
            echo "Content length: " . strlen($content) . " characters<br>";
        } else {
            echo "File read failed: ❌ NO<br>";
        }
    } else {
        echo "Schema file exists: ❌ NO<br>";
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "<br>";
}

echo "<p><a href='setup.php'>← Back to Setup</a></p>";
?>
