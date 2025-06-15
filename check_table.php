<?php
define('INCLUDE_CHECK', true);
require_once 'includes/db_connection.php';

echo "=== Employees Table Structure ===\n";
$stmt = $pdo->prepare('DESCRIBE employees');
$stmt->execute();
while($row = $stmt->fetch()) {
    $default = $row['Default'] ? $row['Default'] : '(none)';
    echo "{$row['Field']} - {$row['Type']} - Null:{$row['Null']} - Default:$default\n";
}
?>
