<?php
/**
 * Script to call the report generator's API with known override dates for smoke test.
 */
$reportDate = '2025-10-29'; // A date during the override period
$empId = 101;               // Known emp_id with override
$url = 'http://localhost/api/generate-attendance-report.php';
$json = json_encode([
    'report_type' => 'daily',
    'selected_date' => $reportDate,
    'branch_id' => '',
    'emp_id' => $empId
]);
$curl = curl_init();
curl_setopt($curl, CURLOPT_URL, $url);
curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
curl_setopt($curl, CURLOPT_POST, true);
curl_setopt($curl, CURLOPT_POSTFIELDS, $json);
curl_setopt($curl, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
try {
    $response = curl_exec($curl);
    $info = curl_getinfo($curl);
    $err = curl_error($curl);
    echo 'HTTP ' . ($info['http_code'] ?? '?') . PHP_EOL;
    if ($err) { echo 'ERROR: ' . $err . PHP_EOL; }
    if ($response) { echo $response . PHP_EOL; }
} finally {
    curl_close($curl);
}