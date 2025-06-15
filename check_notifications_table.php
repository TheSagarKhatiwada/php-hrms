<?php
require_once 'includes/db_connection.php';

echo "=== CHECKING NOTIFICATIONS TABLE STRUCTURE ===\n";
$stmt = $pdo->query('DESCRIBE notifications');
while ($row = $stmt->fetch()) {
    echo "Field: {$row['Field']}, Type: {$row['Type']}, Null: {$row['Null']}, Key: {$row['Key']}, Default: {$row['Default']}, Extra: {$row['Extra']}\n";
}
?>
