<?php
$dbConn = require_once __DIR__ . '/../includes/db_connection.php';
require_once __DIR__ . '/../includes/utilities.php';
$start = $argv[1] ?? '2025-10-01';
$end = $argv[2] ?? '2025-11-30';
$branch = isset($argv[3]) ? $argv[3] : null;
$res = get_holidays_in_range($start, $end, $branch);
$out = [];
foreach($res as $h){
    $out[] = [ 'effective_date' => ($h['effective_date'] ?? null), 'name' => ($h['name'] ?? ($h['title'] ?? null)), 'id' => ($h['id'] ?? null), 'branch_id' => ($h['branch_id'] ?? null) ];
}
echo json_encode($out, JSON_PRETTY_PRINT);
