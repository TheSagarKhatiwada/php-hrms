<?php
// Simple endpoint to get the current public IP address
header('Content-Type: application/json');

// Use a public API to get the server's public IP
$ip = @file_get_contents('https://api.ipify.org');
if ($ip === false) {
    echo json_encode(['success' => false, 'ip' => null, 'error' => 'Unable to fetch public IP.']);
    exit;
}
echo json_encode(['success' => true, 'ip' => $ip]);
