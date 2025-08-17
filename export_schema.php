<?php
require_once 'includes/db_connection.php';

function exportCompleteSchema($conn) {
    $schema = "-- PHP HRMS Database Schema\n";
    $schema .= "-- Generated on: " . date('Y-m-d H:i:s') . "\n";
    $schema .= "-- This file contains the complete database structure\n\n";
    
    $schema .= "SET FOREIGN_KEY_CHECKS = 0;\n";
    $schema .= "SET SQL_MODE = \"NO_AUTO_VALUE_ON_ZERO\";\n";
    $schema .= "SET AUTOCOMMIT = 0;\n";
    $schema .= "START TRANSACTION;\n";
    $schema .= "SET time_zone = \"+00:00\";\n\n";
    
    // Get all tables
    $tables_query = "SHOW TABLES";
    $tables_result = $conn->query($tables_query);
    
    $tables = [];
    while ($row = $tables_result->fetch_array()) {
        $tables[] = $row[0];
    }
    
    sort($tables);
    
    foreach ($tables as $table) {
        $schema .= "-- --------------------------------------------------------\n";
        $schema .= "-- Table structure for table `$table`\n";
        $schema .= "-- --------------------------------------------------------\n\n";
        
        // Get CREATE TABLE statement
        $create_query = "SHOW CREATE TABLE `$table`";
        $create_result = $conn->query($create_query);
        $create_row = $create_result->fetch_array();
        
        $schema .= "DROP TABLE IF EXISTS `$table`;\n";
        $schema .= $create_row[1] . ";\n\n";
    }
    
    $schema .= "SET FOREIGN_KEY_CHECKS = 1;\n";
    $schema .= "COMMIT;\n";
    
    return $schema;
}

try {
    echo "Exporting complete database schema...\n";
    $complete_schema = exportCompleteSchema($conn);
    
    // Save to file
    $filename = 'schema/database_schema.sql';
    if (file_put_contents($filename, $complete_schema)) {
        echo "Complete schema exported successfully to $filename\n";
        echo "Total schema size: " . number_format(strlen($complete_schema)) . " characters\n";
        
        // Count tables
        $table_count = substr_count($complete_schema, 'CREATE TABLE');
        echo "Total tables exported: $table_count\n";
    } else {
        echo "Error: Could not write to $filename\n";
    }
    
} catch (Exception $e) {
    echo "Error exporting schema: " . $e->getMessage() . "\n";
}

$conn->close();
?>
