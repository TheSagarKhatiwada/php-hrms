<?php
chdir(__DIR__ . '/..');
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db_connection.php';
require_once __DIR__ . '/../includes/settings.php';

if (!function_exists('save_setting')) {
    echo "save_setting not available\n"; exit(1);
}

$ok1 = save_setting('work_start_time','09:30');
$ok2 = save_setting('work_end_time','18:00');
echo "save_setting work_start_time: ".($ok1?"ok":"fail")."\n";
echo "save_setting work_end_time: ".($ok2?"ok":"fail")."\n";

// Print values
try {
    $q = $pdo->prepare("SELECT setting_key, COALESCE(value, setting_value) AS val FROM settings WHERE setting_key IN ('work_start_time','work_end_time')");
    $q->execute(); $rows=$q->fetchAll(PDO::FETCH_ASSOC);
    foreach($rows as $r){ echo "{$r['setting_key']} = {$r['val']}\n"; }
} catch(Exception $e) {}
