<?php
include 'includes/db_connection.php';

try {
    $stmt = $pdo->query("DESCRIBE fixedassets");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Table structure for fixedassets:\n";
    print_r($columns);
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?> 