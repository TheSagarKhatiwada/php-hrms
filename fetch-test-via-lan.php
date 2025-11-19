<?php
$ip = "192.168.1.251";
$port = 5005;

$socket = fsockopen($ip, $port, $errno, $errstr, 10);
if (!$socket) {
    echo "Connection failed: $errstr ($errno)";
} else {
    // Send device-specific command
    $command = "\x50\x00\x00\x00"; // Placeholder
    fwrite($socket, $command);

    // Read response
    $response = fread($socket, 4096);
    echo "Raw Data: " . bin2hex($response);

    fclose($socket);
}
?>
