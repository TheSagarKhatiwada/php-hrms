<?php
/**
 * Migration Helper
 * 
 * This script checks if your system meets the requirements
 * to run the database migration and provides guidance if not.
 */

echo "======= Database Migration System Check =======\n\n";

// Check for PDO
if (!extension_loaded('pdo')) {
    echo "ERROR: PDO extension is not loaded.\n";
    echo "The PDO extension is required for database operations.\n\n";
    echo "To enable PDO on Windows:\n";
    echo "1. Edit your php.ini file (usually in your PHP installation directory)\n";
    echo "2. Uncomment or add these lines by removing the semicolon (;) at the beginning:\n";
    echo "   extension=pdo\n";
    echo "   extension=pdo_mysql\n";
    echo "3. Save the file and restart your web server\n\n";
    exit(1);
}

// Check for PDO MySQL driver
if (!extension_loaded('pdo_mysql')) {
    echo "ERROR: PDO MySQL driver is not loaded.\n";
    echo "The PDO_MYSQL extension is required for MySQL database connections.\n\n";
    echo "To enable PDO_MYSQL on Windows:\n";
    echo "1. Edit your php.ini file (usually in your PHP installation directory)\n";
    echo "2. Uncomment or add this line by removing the semicolon (;) at the beginning:\n";
    echo "   extension=pdo_mysql\n";
    echo "3. Save the file and restart your web server\n\n";
    exit(1);
}

// Test database connection
$dbError = null;
try {
    // Include database connection
    require_once __DIR__ . '/includes/db_connection.php';
    
    // Check if PDO connection was successful
    if (!isset($pdo) || !$pdo) {
        echo "ERROR: Could not establish database connection.\n";
        echo "Please check your database configuration in includes/config.php\n\n";
        exit(1);
    }
    
    // Test connection
    $pdo->query("SELECT 1");
    
    echo "SUCCESS: Database connection established successfully.\n\n";
    
} catch (PDOException $e) {
    echo "ERROR: Database connection failed: " . $e->getMessage() . "\n\n";
    echo "Please check your database configuration in includes/config.php\n";
    echo "Make sure your database server is running and the credentials are correct.\n\n";
    exit(1);
}

echo "All system requirements met. You can now run the migration manager.\n";
echo "To run migrations, use: php db_migrator.php migrate\n";
echo "To check migration status, use: php db_migrator.php status\n";
echo "\n========================================\n";
?>