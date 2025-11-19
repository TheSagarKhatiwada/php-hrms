<?php
// Admin-only script to normalize manual_reason separators to " || " and map codes to labels in attendance_logs
// Usage: run once from browser with an admin session or via CLI after setting up environment.

require_once __DIR__ . '/../includes/session_config.php';
require_once __DIR__ . '/../includes/db_connection.php';
if (file_exists(__DIR__ . '/../includes/utilities.php')) {
    require_once __DIR__ . '/../includes/utilities.php';
}
require_once __DIR__ . '/../includes/reason_helpers.php';

if (php_sapi_name() !== 'cli') {
    if (!is_admin()) {
        http_response_code(403);
        echo 'Forbidden';
        exit;
    }
}

$dryRun = isset($_GET['dryRun']) ? ($_GET['dryRun'] === '1') : true; // default dry-run
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 5000; // cap per execution

// Select rows that likely need normalization: contains '|' but not ' || '
$sql = "SELECT id, manual_reason FROM attendance_logs WHERE manual_reason IS NOT NULL AND manual_reason LIKE '%|%' AND manual_reason NOT LIKE '% || %' LIMIT :lim";
$stmt = $pdo->prepare($sql);
$stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
$stmt->execute();
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$updated = 0; $skipped = 0; $errors = 0;

foreach ($rows as $row) {
    $id = (int)$row['id'];
    $raw = $row['manual_reason'];

    // Parse using helper
    $parsed = hrms_parse_manual_reason($raw);
    $label = $parsed['label'];
    $remarks = $parsed['remarks'];

    // Rebuild normalized string
    $normalized = $label;
    if ($remarks !== '') {
        $normalized .= ' || ' . $remarks;
    }

    if ($normalized === $raw) {
        $skipped++;
        continue;
    }

    if ($dryRun) {
        $updated++;
        continue;
    }

    try {
        $u = $pdo->prepare("UPDATE attendance_logs SET manual_reason = :norm WHERE id = :id");
        $u->execute([':norm' => $normalized, ':id' => $id]);
        $updated++;
    } catch (Exception $e) {
        $errors++;
    }
}

header('Content-Type: application/json');
echo json_encode([
    'dryRun' => $dryRun,
    'scanned' => count($rows),
    'updated' => $updated,
    'skipped' => $skipped,
    'errors' => $errors,
]);
