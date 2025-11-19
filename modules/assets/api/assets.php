<?php
header('Content-Type: application/json');

require_once __DIR__ . '/../../../includes/session_config.php';
require_once __DIR__ . '/../../../includes/utilities.php';
require_once __DIR__ . '/../../../includes/db_connection.php';
require_once __DIR__ . '/../../../includes/services/AssetService.php';

if (!is_logged_in() || (!is_admin() && !has_permission('manage_assets'))) {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

try {
    $service = new AssetService($pdo);

    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
        exit;
    }

    $result = $service->listAssets([
        'page' => $_GET['page'] ?? 1,
        'per_page' => $_GET['per_page'] ?? 100,
        'status' => $_GET['status'] ?? '',
        'category' => $_GET['category'] ?? '',
        'search' => $_GET['search'] ?? '',
        'order' => $_GET['order'] ?? 'purchaseDate',
        'direction' => $_GET['direction'] ?? 'DESC',
        'include_deleted' => $_GET['include_deleted'] ?? false,
        'only_deleted' => $_GET['only_deleted'] ?? false,
    ]);

    echo json_encode([
        'success' => true,
        'data' => $result['data'],
        'meta' => $result['meta'],
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Failed to load assets',
        'message' => $e->getMessage(),
    ]);
}
