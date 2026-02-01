<?php
require __DIR__ . '/../includes/db_connection.php';

$stmt = $pdo->query('SELECT CategoryID, CategoryName, CategoryShortCode FROM assetcategories');
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    printf("%d | %s | %s\n", $row['CategoryID'], $row['CategoryName'], $row['CategoryShortCode']);
}
