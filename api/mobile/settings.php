<?php
require_once __DIR__ . '/../../includes/mobile_api.php';
require_once __DIR__ . '/../../includes/settings.php';

header('Content-Type: application/json');

$appName = get_setting('company_name', get_setting('app_name', 'HRMS-App'));
$primary = get_setting('company_primary_color', '#1565C0');
$secondary = get_setting('company_secondary_color', '#6c757d');
$logo = get_setting('company_logo', null);

$settings = [
    'app_name' => $appName,
    'primary_color' => $primary,
    'secondary_color' => $secondary,
    'company_logo' => $logo,
];

echo json_encode([
    'success' => true,
    'settings' => $settings,
]);
