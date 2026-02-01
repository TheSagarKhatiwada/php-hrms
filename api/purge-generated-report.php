<?php
// Permanently purge (hard delete) a generated report file & DB record.
// Security: Only admins can purge. Only already soft-deleted reports may be purged.
// Expects: POST id

require_once __DIR__ . '/../includes/session_config.php';
require_once __DIR__ . '/../includes/db_connection.php';
require_once __DIR__ . '/../includes/utilities.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

$currentUserId = (string)$_SESSION['user_id'];
$isAdmin = function_exists('is_admin') ? is_admin() : false;
$canManageArtifacts = $isAdmin || has_permission('manage_attendance_report_artifacts');
if (!$canManageArtifacts) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Forbidden']);
    exit;
}

$canViewAllBranchAttendance = $isAdmin || has_permission('view_all_branch_attendance');
$branchLimitValue = null;
if (!$canViewAllBranchAttendance) {
    $branchContext = hrms_get_user_branch_context($pdo, $currentUserId);
    if ($branchContext['legacy'] !== null) {
        $branchLimitValue = (string)$branchContext['legacy'];
    } elseif ($branchContext['numeric'] !== null) {
        $branchLimitValue = (string)$branchContext['numeric'];
    }
    if ($branchLimitValue === null) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Branch access is not configured for your account.']);
        exit;
    }
}

$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
if ($id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid id']);
    exit;
}

try {
    $pdo->beginTransaction();

    // Select needed columns (file_url is what we actually store; file_path column was never created)
    $stmt = $pdo->prepare("SELECT file_url, deleted_at, branch_id FROM generated_reports WHERE id = :id FOR UPDATE");
    $stmt->execute([':id' => $id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        $pdo->rollBack();
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Report not found']);
        exit;
    }

    if (empty($row['deleted_at'])) {
        $pdo->rollBack();
        http_response_code(409);
        echo json_encode(['success' => false, 'message' => 'Report must be soft-deleted first']);
        exit;
    }

    if (!$canViewAllBranchAttendance) {
        $rowBranch = isset($row['branch_id']) ? (string)$row['branch_id'] : null;
        if ($branchLimitValue === null || $rowBranch === null || $rowBranch !== $branchLimitValue) {
            $pdo->rollBack();
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Forbidden']);
            exit;
        }
    }

    // Delete DB row
    $del = $pdo->prepare("DELETE FROM generated_reports WHERE id = :id");
    $del->execute([':id' => $id]);

    $pdo->commit();

    // Resolve local filesystem path from stored file_url (similar logic as delete API)
    $deletedFile = null;
    if (!empty($row['file_url'])) {
        $urlPath = parse_url($row['file_url'], PHP_URL_PATH);
        if ($urlPath) {
            $root = realpath(__DIR__ . '/..');
            if ($root) {
                $candidate = realpath($root . $urlPath);
                if ($candidate && strpos($candidate, $root) === 0 && is_file($candidate)) {
                    $deletedFile = $candidate;
                } else {
                    // Fallback: if realpath fails (file may already be gone) try simple join
                    $cand2 = $root . $urlPath;
                    if (is_file($cand2)) { $deletedFile = $cand2; }
                }
            }
        }
    }
    if ($deletedFile) { @unlink($deletedFile); }

    // Basic logging for audit
    try {
        $logFile = realpath(__DIR__.'/..') . '/logs/attendance_reports_purge.log';
        $line = sprintf("%s\tPURGE\tID:%d\tUSER:%s\tROLE:%s\tFILE:%s\n", date('Y-m-d H:i:s'), $id, ($_SESSION['user_id'] ?? 'unknown'), $isAdmin ? 'admin' : 'manager', $deletedFile ?: 'n/a');
        @file_put_contents($logFile, $line, FILE_APPEND | LOCK_EX);
    } catch (Throwable $e) { /* ignore logging errors */ }

    echo json_encode(['success' => true, 'message' => 'Report permanently deleted', 'file_deleted' => (bool)$deletedFile]);
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error purging report', 'error' => $e->getMessage()]);
}
