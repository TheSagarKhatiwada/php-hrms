<?php
$exe = __DIR__ . DIRECTORY_SEPARATOR . 'FKBridge.exe';
$ip = '192.168.1.251';
$port = 5005;
$license = 0; // set license

if (!file_exists($exe)) {
    echo "Bridge executable not found: $exe\n";
    exit(1);
}

$cmd = escapeshellarg($exe) . ' ' . escapeshellarg($ip) . ' ' . escapeshellarg($port) . ' ' . escapeshellarg($license);
exec($cmd, $out, $code);
foreach ($out as $line) {
    echo htmlspecialchars($line) . "\n";
}
exit($code);
?>