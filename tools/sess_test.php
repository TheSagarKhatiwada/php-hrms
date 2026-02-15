<?php
$session_dir = __DIR__ . '/../sessions';
foreach (glob($session_dir . '/sess_*') as $f) {
    $c = file_get_contents($f);
    if (strpos($c, 'user_id') !== false && strpos($c, '101') !== false) {
        echo basename($f) . PHP_EOL;
        echo "--- contents ---\n" . $c . "\n";
    }
}
?>