<?php
require __DIR__ . '/../includes/session_config.php';
require __DIR__ . '/../includes/db_connection.php';

$_SESSION['user_id'] = 1;
$_SESSION['user_role'] = '1';
$_SESSION['csrf_token'] = 'token123';
$_SERVER['REQUEST_METHOD'] = 'POST';
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';

$_POST = [
    'action' => 'add',
    'csrf_token' => 'token123',
    'assetName' => 'Simulated Asset',
    'categoryId' => 1,
    'purchaseDate' => '2024-01-01',
    'purchaseCost' => '1000',
    'warrantyEndDate' => '2024-12-31',
    'assetCondition' => 'Good',
    'assetLocation' => 'HQ',
    'description' => 'Simulated asset',
];

$_FILES = [
    'assetImage' => [
        'name' => '',
        'type' => '',
        'tmp_name' => '',
        'error' => UPLOAD_ERR_NO_FILE,
        'size' => 0,
    ],
];

require __DIR__ . '/../modules/assets/manage_assets_handler.php';
