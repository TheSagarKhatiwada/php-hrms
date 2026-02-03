<?php
/**
 * Migration script to create assets management tables
 * 
 * This script creates the following tables:
 * - assetcategories
 * - fixedassets  
 * - assetassignments
 * - assetmaintenance
 */

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/db_connection.php';

echo "=== Assets Management Tables Migration ===\n";
echo "Creating assets management tables...\n\n";

try {
    echo "1. Checking existing tables...\n";
    
    // Check which tables already exist
    $tables_to_create = ['assetcategories', 'fixedassets', 'assetassignments', 'assetmaintenance'];
    $existing_tables = [];
    
    foreach ($tables_to_create as $table) {
        $stmt = $pdo->query("SHOW TABLES LIKE '$table'");
        if ($stmt->fetch()) {
            $existing_tables[] = $table;
            echo "   - $table: EXISTS\n";
        } else {
            echo "   - $table: MISSING\n";
        }
    }
    
    echo "\n2. Creating missing tables...\n";
    
    // Create assetcategories table
    if (!in_array('assetcategories', $existing_tables)) {
        echo "   Creating assetcategories table...\n";
        $pdo->exec("
            CREATE TABLE `assetcategories` (
              `CategoryID` int(11) NOT NULL AUTO_INCREMENT,
              `CategoryShortCode` varchar(10) NOT NULL,
              `CategoryName` varchar(100) NOT NULL,
              `Description` text DEFAULT NULL,
              `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
              `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
              PRIMARY KEY (`CategoryID`),
              UNIQUE KEY `CategoryShortCode` (`CategoryShortCode`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        echo "   ✓ assetcategories table created successfully\n";
    }
    
    // Create fixedassets table
    if (!in_array('fixedassets', $existing_tables)) {
        echo "   Creating fixedassets table...\n";
        $pdo->exec("
            CREATE TABLE `fixedassets` (
              `AssetID` int(11) NOT NULL AUTO_INCREMENT,
              `AssetName` varchar(200) NOT NULL,
              `CategoryID` int(11) NOT NULL,
              `AssetSerial` varchar(50) NOT NULL,
              `ProductSerial` varchar(100) DEFAULT NULL,
              `PurchaseDate` date NOT NULL,
              `PurchaseCost` decimal(10,2) NOT NULL,
              `WarrantyEndDate` date DEFAULT NULL,
              `AssetCondition` enum('Excellent','Good','Fair','Poor') DEFAULT 'Good',
              `AssetLocation` varchar(200) DEFAULT NULL,
              `AssetsDescription` text DEFAULT NULL,
              `Status` enum('Available','Assigned','Maintenance','Retired') DEFAULT 'Available',
              `AssetImage` varchar(255) DEFAULT NULL,
              `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
              `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
              PRIMARY KEY (`AssetID`),
              UNIQUE KEY `AssetSerial` (`AssetSerial`),
              KEY `CategoryID` (`CategoryID`),
              FOREIGN KEY (`CategoryID`) REFERENCES `assetcategories` (`CategoryID`) ON DELETE RESTRICT ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        echo "   ✓ fixedassets table created successfully\n";
    }
    
    // Create assetassignments table
    if (!in_array('assetassignments', $existing_tables)) {
        echo "   Creating assetassignments table...\n";
        $pdo->exec("
            CREATE TABLE `assetassignments` (
              `AssignmentID` int(11) NOT NULL AUTO_INCREMENT,
              `AssetID` int(11) NOT NULL,
              `EmployeeID` int(11) NOT NULL,
              `AssignmentDate` date NOT NULL,
              `ExpectedReturnDate` date DEFAULT NULL,
              `ReturnDate` date DEFAULT NULL,
              `Notes` text DEFAULT NULL,
              `ReturnNotes` text DEFAULT NULL,
              `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
              `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
              PRIMARY KEY (`AssignmentID`),
              KEY `AssetID` (`AssetID`),
              KEY `EmployeeID` (`EmployeeID`),
              FOREIGN KEY (`AssetID`) REFERENCES `fixedassets` (`AssetID`) ON DELETE CASCADE ON UPDATE CASCADE,
              FOREIGN KEY (`EmployeeID`) REFERENCES `employees` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        echo "   ✓ assetassignments table created successfully\n";
    }
    
    // Create assetmaintenance table
    if (!in_array('assetmaintenance', $existing_tables)) {
        echo "   Creating assetmaintenance table...\n";
        $pdo->exec("
            CREATE TABLE `assetmaintenance` (
              `RecordID` int(11) NOT NULL AUTO_INCREMENT,
              `AssetID` int(11) NOT NULL,
              `MaintenanceDate` date NOT NULL,
              `MaintenanceType` enum('Preventive','Corrective','Emergency','Routine') NOT NULL,
              `Description` text NOT NULL,
              `Cost` decimal(10,2) DEFAULT 0.00,
              `MaintenancePerformBy` varchar(200) DEFAULT NULL,
              `MaintenanceStatus` enum('Scheduled','In Progress','Completed','Cancelled') DEFAULT 'Scheduled',
              `CompletionDate` date DEFAULT NULL,
              `CompletionNotes` text DEFAULT NULL,
              `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
              `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
              PRIMARY KEY (`RecordID`),
              KEY `AssetID` (`AssetID`),
              FOREIGN KEY (`AssetID`) REFERENCES `fixedassets` (`AssetID`) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        echo "   ✓ assetmaintenance table created successfully\n";
    }
    
    echo "\n3. Adding default asset categories...\n";
    
    // Check if categories already exist
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM assetcategories");
    $categoryCount = $stmt->fetch()['count'];
    
    if ($categoryCount == 0) {
        echo "   Inserting default asset categories...\n";
        $categories = [
            ['IT', 'Information Technology', 'Computers, laptops, servers, networking equipment'],
            ['FURN', 'Furniture', 'Office furniture, chairs, desks, cabinets'],
            ['VEH', 'Vehicles', 'Company vehicles, cars, trucks, motorcycles'],
            ['ELEC', 'Electronics', 'Printers, scanners, projectors, audio/visual equipment'],
            ['OFF', 'Office Equipment', 'Photocopiers, fax machines, shredders, general office equipment'],
            ['TOOL', 'Tools & Equipment', 'Specialized tools, machinery, equipment'],
            ['SEC', 'Security', 'Security cameras, access control systems, safes'],
            ['COMM', 'Communication', 'Phones, mobile devices, radio equipment']
        ];
        
        $stmt = $pdo->prepare("INSERT INTO assetcategories (CategoryShortCode, CategoryName, Description) VALUES (?, ?, ?)");
        
        foreach ($categories as $category) {
            $stmt->execute($category);
            echo "   ✓ Added category: {$category[1]} ({$category[0]})\n";
        }
    } else {
        echo "   Default categories already exist ($categoryCount categories found)\n";
    }
    
    echo "\n=== Migration Completed Successfully ===\n";
    echo "Assets management tables are now ready!\n\n";
    
    echo "Available tables:\n";
    echo "  • assetcategories - Asset categories (IT, Furniture, etc.)\n";
    echo "  • fixedassets - Asset inventory with details\n";
    echo "  • assetassignments - Asset assignments to employees\n";
    echo "  • assetmaintenance - Asset maintenance records\n\n";
    
    echo "Default asset categories created:\n";
    $stmt = $pdo->query("SELECT CategoryShortCode, CategoryName FROM assetcategories ORDER BY CategoryName");
    $categories = $stmt->fetchAll();
    
    foreach ($categories as $category) {
        echo "  • {$category['CategoryName']} ({$category['CategoryShortCode']})\n";
    }
    
    echo "\nYou can now access the assets management features in the HRMS system!\n";
    
} catch (Exception $e) {
    echo "ERROR: Migration failed - " . $e->getMessage() . "\n";
    echo "Please check the error and try again.\n";
    exit(1);
}
?>
