<?php
/**
 * Check Asset Assignment Table Structure
 */

require_once 'includes/db_connection.php';

echo "=== CHECKING ASSET ASSIGNMENT TABLE STRUCTURE ===\n\n";

try {
    // Check if assetassignments table exists (lowercase)
    $stmt = $pdo->query("SHOW TABLES LIKE 'assetassignments'");
    $lowercaseTable = $stmt->fetch();
    
    // Check if AssetAssignments table exists (capitalized)
    $stmt = $pdo->query("SHOW TABLES LIKE 'AssetAssignments'");
    $capitalizedTable = $stmt->fetch();
    
    echo "Table existence:\n";
    echo "- assetassignments (lowercase): " . ($lowercaseTable ? "EXISTS" : "NOT FOUND") . "\n";
    echo "- AssetAssignments (capitalized): " . ($capitalizedTable ? "EXISTS" : "NOT FOUND") . "\n";
    
    if ($lowercaseTable) {
        echo "\nDescribing assetassignments table:\n";
        $stmt = $pdo->query("DESCRIBE assetassignments");
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($columns as $column) {
            echo "  - {$column['Field']} ({$column['Type']}) - Key: {$column['Key']}\n";
        }
    }
    
    if ($capitalizedTable) {
        echo "\nDescribing AssetAssignments table:\n";
        $stmt = $pdo->query("DESCRIBE AssetAssignments");
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($columns as $column) {
            echo "  - {$column['Field']} ({$column['Type']}) - Key: {$column['Key']}\n";
        }
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
