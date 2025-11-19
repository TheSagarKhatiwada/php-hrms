<?php
require_once __DIR__ . '/../includes/db_connection.php';
$stmt = $pdo->query('SELECT id,name,start_date,end_date,branch_id,recurring_type,recurring_day_of_week,is_recurring FROM holidays ORDER BY id');
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo json_encode($rows, JSON_PRETTY_PRINT);
