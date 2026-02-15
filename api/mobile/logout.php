<?php
require_once __DIR__ . '/../../includes/mobile_api.php';

header('Content-Type: application/json');

$auth = mobile_require_auth($pdo);
$token = $auth['token'];

$revoked = mobile_revoke_token($pdo, $token);

echo json_encode([
    'success' => true,
    'revoked' => $revoked
]);
