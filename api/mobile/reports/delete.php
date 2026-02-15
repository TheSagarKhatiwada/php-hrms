<?php
require_once __DIR__ . '/../../../includes/session_config.php';
require_once __DIR__ . '/../../../includes/mobile_api.php';

header('Content-Type: application/json');

$auth = mobile_require_auth($pdo);
$_SESSION['user_id'] = $auth['employee_id'];

require __DIR__ . '/../../delete-generated-report.php';
