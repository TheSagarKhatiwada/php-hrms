<?php
require_once __DIR__.'/../includes/db_connection.php';
require_once __DIR__.'/../includes/utilities.php';
$dates = ['2025-10-01','2025-10-03','2025-10-21','2025-09-30','2025-10-22'];
foreach($dates as $d){ $h = is_holiday($d, null); echo $d . ' => ' . ($h?($h['name'].' (id:'.$h['id'].')'):'no') . PHP_EOL; }
