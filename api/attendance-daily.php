<?php
require_once __DIR__ . '/../includes/session_config.php';
require_once __DIR__ . '/../includes/db_connection.php';
require_once __DIR__ . '/../includes/utilities.php';
require_once __DIR__ . '/../includes/reason_helpers.php';
require_once __DIR__ . '/../includes/schedule_helpers.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

$attDate = isset($_GET['date']) && $_GET['date'] !== '' ? $_GET['date'] : date('Y-m-d');
$manualAttendanceReasons = function_exists('hrms_reason_label_map')
    ? hrms_reason_label_map()
    : [
        '1' => 'Card Forgot',
        '2' => 'Card Lost',
        '3' => 'Forgot to Punch',
        '4' => 'Office Work Delay',
        '5' => 'Field Visit'
    ];

try {
    $stmt = $pdo->prepare("\n        SELECT e.emp_id, e.first_name, e.last_name, e.middle_name, e.user_image,\n               e.work_start_time, e.work_end_time,\n               d.title as designation_name, b.name as branch_name,\n               ag.date, ag.in_time, ag.out_time, ag.cnt,\n               (SELECT manual_reason FROM attendance_logs l WHERE l.emp_id = e.emp_id AND l.date = :att_date AND l.time = ag.in_time LIMIT 1) AS in_reason,\n               (SELECT method FROM attendance_logs l WHERE l.emp_id = e.emp_id AND l.date = :att_date AND l.time = ag.in_time LIMIT 1) AS in_method,\n               (SELECT manual_reason FROM attendance_logs l WHERE l.emp_id = e.emp_id AND l.date = :att_date AND l.time = ag.out_time LIMIT 1) AS out_reason,\n               (SELECT method FROM attendance_logs l WHERE l.emp_id = e.emp_id AND l.date = :att_date AND l.time = ag.out_time LIMIT 1) AS out_method\n        FROM employees e\n        LEFT JOIN (\n            SELECT a.emp_id, a.date,\n                   MIN(a.time) AS in_time,\n                   CASE WHEN COUNT(*)>1 THEN MAX(a.time) ELSE NULL END AS out_time,\n                   COUNT(*) AS cnt\n            FROM attendance_logs a\n            WHERE a.date = :att_date\n            GROUP BY a.emp_id, a.date\n        ) ag ON e.emp_id = ag.emp_id\n        LEFT JOIN branches b ON e.branch = b.id\n        LEFT JOIN designations d ON e.designation_id = d.id\n        WHERE e.exit_date IS NULL\n          AND (e.join_date IS NULL OR e.join_date <= :att_date)\n          AND (e.mach_id_not_applicable IS NULL OR e.mach_id_not_applicable = 0)\n        ORDER BY e.first_name, e.last_name\n    ");
    $stmt->execute([':att_date' => $attDate]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $empIds = array_values(array_unique(array_filter(array_map(function($row) {
        return $row['emp_id'] ?? null;
    }, $rows))));
    $scheduleOverrides = !empty($empIds)
        ? prefetch_schedule_overrides($pdo, $empIds, $attDate, $attDate)
        : [];

    $timeToSeconds = function($time) {
        if (empty($time) || strpos($time, ':') === false) {
            return null;
        }
        $parts = array_pad(explode(':', $time), 3, 0);
        return ((int)$parts[0] * 3600) + ((int)$parts[1] * 60) + (int)$parts[2];
    };

    $formatDuration = function($seconds) {
        $seconds = max(0, (int)$seconds);
        $hours = intdiv($seconds, 3600);
        $minutes = intdiv($seconds % 3600, 60);
        if ($hours > 0) {
            return $hours . 'h ' . $minutes . 'm';
        }
        return $minutes . 'm';
    };

    $renderMeta = function($method, $reason) use ($manualAttendanceReasons) {
        $parts = [];
        if ($method !== null) {
            switch ((int)$method) {
                case 0: $parts[] = 'Auto'; break;
                case 1: $parts[] = 'Manual'; break;
                case 2: $parts[] = 'Web'; break;
            }
        }
        if (!empty($reason)) {
            if (strpos($reason, '||') !== false) {
                [$rId, $rRem] = array_map('trim', explode('||', $reason, 2));
            } elseif (strpos($reason, '|') !== false) {
                [$rId, $rRem] = array_map('trim', explode('|', $reason, 2));
            } else {
                $rId = trim($reason);
                $rRem = '';
            }
            $reasonLabel = (is_numeric($rId) && isset($manualAttendanceReasons[$rId])) ? $manualAttendanceReasons[$rId] : $rId;
            if ($reasonLabel !== '') {
                $parts[] = $reasonLabel;
            }
            if (!empty($rRem)) {
                $parts[] = $rRem;
            }
        }
        return implode(' | ', array_filter($parts));
    };

    $output = [];
    foreach ($rows as $row) {
        $empId = $row['emp_id'] ?? null;
        $overridesForEmp = (!empty($empId) && isset($scheduleOverrides[$empId])) ? $scheduleOverrides[$empId] : [];
        $schedule = resolve_schedule_for_emp_date([
            'emp_id' => $empId,
            'work_start_time' => $row['work_start_time'] ?? null,
            'work_end_time' => $row['work_end_time'] ?? null
        ], $attDate, $overridesForEmp, '09:00', '18:00');

        $startSec = $timeToSeconds($schedule['start'] ?? null);
        $endSec = $timeToSeconds($schedule['end'] ?? null);
        $inSec = $timeToSeconds($row['in_time'] ?? null);
        $outSec = $timeToSeconds($row['out_time'] ?? null);

        $remarks = [];
        if ($inSec !== null && $startSec !== null && $inSec !== $startSec) {
            $diff = $inSec - $startSec;
            if ($diff > 0) {
                $remarks[] = 'Late In (' . $formatDuration($diff) . ')';
            } else {
                $remarks[] = 'Early In (' . $formatDuration(abs($diff)) . ')';
            }
        }
        if ($outSec !== null && $endSec !== null && $outSec !== $endSec) {
            $diff = $outSec - $endSec;
            if ($diff > 0) {
                $remarks[] = 'Late Out (' . $formatDuration($diff) . ')';
            } else {
                $remarks[] = 'Early Out (' . $formatDuration(abs($diff)) . ')';
            }
        }

        $output[] = [
            'emp_id' => $row['emp_id'] ?? '',
            'first_name' => $row['first_name'] ?? '',
            'last_name' => $row['last_name'] ?? '',
            'middle_name' => $row['middle_name'] ?? '',
            'designation_name' => $row['designation_name'] ?? '',
            'branch_name' => $row['branch_name'] ?? '',
            'user_image' => $row['user_image'] ?? '',
            'date' => $row['date'] ?? $attDate,
            'in_time' => $row['in_time'] ?? null,
            'out_time' => $row['out_time'] ?? null,
            'in_meta' => $renderMeta($row['in_method'] ?? null, $row['in_reason'] ?? ''),
            'out_meta' => $renderMeta($row['out_method'] ?? null, $row['out_reason'] ?? ''),
            'remarks' => $remarks
        ];
    }

    echo json_encode(['status' => 'ok', 'data' => $output]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Failed to load attendance']);
}
