<?php
define('INCLUDE_CHECK', true);
require_once 'includes/db_connection.php';

try {
    echo "Testing the holidays.php query...\n";
    
    // Test the exact query from holidays.php line 92
    $currentYear = date('Y');
    $stmt = $pdo->prepare("SELECT h.*, b.name as branch_name 
                           FROM holidays h 
                           LEFT JOIN branches b ON h.branch_id = b.id 
                           WHERE YEAR(h.date) = ? OR h.is_recurring = 1
                           ORDER BY h.date ASC");
    $stmt->execute([$currentYear]);
    $holidays = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Query executed successfully!\n";
    echo "Found " . count($holidays) . " holidays\n";
    
    if (count($holidays) > 0) {
        echo "\nSample holiday data:\n";
        foreach (array_slice($holidays, 0, 3) as $holiday) {
            echo "- " . $holiday['name'] . " (" . $holiday['date'] . ") - Type: " . $holiday['type'] . "\n";
        }
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
