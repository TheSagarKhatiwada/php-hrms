<?php
require_once __DIR__.'/../includes/db_connection.php';
require_once __DIR__.'/../includes/utilities.php';
$start='2025-10-01';
$end='2025-10-31';
$hols = get_holidays_in_range($start,$end,null);
echo "Found " . count($hols) . " holiday occurrences between $start and $end\n";
foreach(array_slice($hols,0,50) as $h){
    echo ($h['effective_date'] ?? '?') . ' => ' . ($h['name'] ?? '[no name]') . ' (id:' . ($h['id'] ?? 'N/A') . ')\n';
}

?>