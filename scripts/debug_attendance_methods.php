<?php
require_once __DIR__.'/../includes/db_connection.php';
$emp = $argv[1] ?? '101';
$date = $argv[2] ?? '2025-10-10';
$st = $pdo->prepare('SELECT id, time, method, manual_reason FROM attendance_logs WHERE emp_id = ? AND date = ? ORDER BY time ASC');
$st->execute([$emp, $date]);
$rows = $st->fetchAll(PDO::FETCH_ASSOC);
echo json_encode($rows, JSON_PRETTY_PRINT)."\n";
