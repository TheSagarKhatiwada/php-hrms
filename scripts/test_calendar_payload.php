<?php
require_once __DIR__ . '/../includes/session_config.php';
require_once __DIR__ . '/../includes/calendar_service.php';

try {
    $payload = get_calendar_payload('bs', 2025, 12);
    echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    echo 'ERROR: ' . $e->getMessage();
}
