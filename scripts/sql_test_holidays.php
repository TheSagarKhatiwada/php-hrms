<?php
require_once __DIR__.'/../includes/db_connection.php';
$date='2025-10-21';
$sql = "SELECT * FROM holidays WHERE ((start_date IS NOT NULL AND start_date <= :d1 AND (end_date IS NULL OR end_date >= :d2)) OR (recurring_type IS NOT NULL AND recurring_type <> 'none') OR (COALESCE(is_recurring,0) = 1)) ORDER BY branch_id IS NOT NULL ASC";
$stmt=$pdo->prepare($sql);
$stmt->bindValue(':d1',$date);
$stmt->bindValue(':d2',$date);
$stmt->execute();
$rows=$stmt->fetchAll(PDO::FETCH_ASSOC);
echo "Found: ".count($rows)."\n";
print_r($rows);
