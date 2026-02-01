<?php
require_once __DIR__ . '/../includes/db_connection.php';

$stmt = $pdo->query('DESCRIBE employees');
echo "Employees Table Structure:\n";
echo str_repeat('-', 80) . "\n";
printf("%-20s %-30s %-10s %-10s\n", "Field", "Type", "Key", "Extra");
echo str_repeat('-', 80) . "\n";

while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    printf("%-20s %-30s %-10s %-10s\n", 
        $row['Field'], 
        $row['Type'], 
        $row['Key'], 
        $row['Extra']
    );
}
