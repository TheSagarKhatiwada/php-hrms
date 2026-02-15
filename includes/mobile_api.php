<?php
require_once __DIR__ . '/db_connection.php';
require_once __DIR__ . '/utilities.php';

if (!function_exists('mobile_get_json_body')) {
    function mobile_get_json_body() {
        $raw = file_get_contents('php://input');
        if (!$raw) return [];
        $data = json_decode($raw, true);
        return is_array($data) ? $data : [];
    }
}

if (!function_exists('mobile_get_bearer_token')) {
    function mobile_get_bearer_token() {
        $header = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
        if (!$header && function_exists('getallheaders')) {
            $headers = getallheaders();
            $header = $headers['Authorization'] ?? $headers['authorization'] ?? '';
        }
        if (stripos($header, 'Bearer ') === 0) {
            return trim(substr($header, 7));
        }
        return null;
    }
}

if (!function_exists('mobile_issue_token')) {
    function mobile_issue_token(PDO $pdo, $employeeId, $deviceId = null, $deviceName = null, $ttlDays = 30) {
        $token = bin2hex(random_bytes(32));
        $hash = hash('sha256', $token);
        $stmt = $pdo->prepare("INSERT INTO mobile_access_tokens
            (employee_id, token_hash, device_id, device_name, ip_address, user_agent, created_at, last_seen_at, expires_at)
            VALUES (:employee_id, :token_hash, :device_id, :device_name, :ip, :ua, NOW(), NOW(), DATE_ADD(NOW(), INTERVAL :ttl DAY))");
        $stmt->execute([
            ':employee_id' => $employeeId,
            ':token_hash' => $hash,
            ':device_id' => $deviceId,
            ':device_name' => $deviceName,
            ':ip' => $_SERVER['REMOTE_ADDR'] ?? null,
            ':ua' => substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255),
            ':ttl' => (int)$ttlDays
        ]);
        return $token;
    }
}

if (!function_exists('mobile_revoke_token')) {
    function mobile_revoke_token(PDO $pdo, $token) {
        $hash = hash('sha256', $token);
        $stmt = $pdo->prepare("UPDATE mobile_access_tokens SET revoked_at = NOW() WHERE token_hash = :hash AND revoked_at IS NULL");
        $stmt->execute([':hash' => $hash]);
        return $stmt->rowCount() > 0;
    }
}

if (!function_exists('mobile_require_auth')) {
    function mobile_require_auth(PDO $pdo) {
        $token = mobile_get_bearer_token();
        if (!$token) {
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'Missing token']);
            exit;
        }
        $hash = hash('sha256', $token);
        $stmt = $pdo->prepare("SELECT id, employee_id, expires_at, revoked_at FROM mobile_access_tokens
            WHERE token_hash = :hash LIMIT 1");
        $stmt->execute([':hash' => $hash]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row || !empty($row['revoked_at']) || strtotime($row['expires_at']) < time()) {
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'Invalid or expired token']);
            exit;
        }
        $touch = $pdo->prepare("UPDATE mobile_access_tokens SET last_seen_at = NOW() WHERE id = :id");
        $touch->execute([':id' => $row['id']]);
        return ['employee_id' => $row['employee_id'], 'token' => $token, 'token_id' => $row['id']];
    }
}

if (!function_exists('mobile_store_location')) {
    function mobile_store_location(PDO $pdo, $employeeId, $lat, $lon, $accuracy = null, $provider = 'mobile', $sessionId = null, $capturedAt = null) {
        if (!is_numeric($lat) || !is_numeric($lon)) return false;
        $stmt = $pdo->prepare("INSERT INTO location_logs
            (employee_id, session_id, latitude, longitude, accuracy_meters, provider, ip_address, user_agent, created_at)
            VALUES (:employee_id, :session_id, :lat, :lon, :accuracy, :provider, :ip, :ua, :created_at)");
        $createdAt = $capturedAt && strtotime($capturedAt) ? date('Y-m-d H:i:s', strtotime($capturedAt)) : date('Y-m-d H:i:s');
        $stmt->execute([
            ':employee_id' => $employeeId,
            ':session_id' => $sessionId,
            ':lat' => $lat,
            ':lon' => $lon,
            ':accuracy' => is_numeric($accuracy) ? $accuracy : null,
            ':provider' => $provider,
            ':ip' => $_SERVER['REMOTE_ADDR'] ?? null,
            ':ua' => substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255),
            ':created_at' => $createdAt
        ]);
        return true;
    }
}

if (!function_exists('mobile_branch_requires_wifi')) {
    function mobile_branch_requires_wifi(PDO $pdo, $branchId) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM branch_wifi_access_points WHERE branch_id = :branch AND is_active = 1");
        $stmt->execute([':branch' => $branchId]);
        return (int)$stmt->fetchColumn() > 0;
    }
}

if (!function_exists('mobile_branch_wifi_matches')) {
    function mobile_branch_wifi_matches(PDO $pdo, $branchId, $ssid = null, $bssid = null) {
        $ssid = $ssid ? trim($ssid) : null;
        $bssid = $bssid ? strtolower(trim($bssid)) : null;
        $stmt = $pdo->prepare("SELECT ssid, bssid FROM branch_wifi_access_points WHERE branch_id = :branch AND is_active = 1");
        $stmt->execute([':branch' => $branchId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        foreach ($rows as $row) {
            $dbSsid = $row['ssid'] ? trim($row['ssid']) : null;
            $dbBssid = $row['bssid'] ? strtolower(trim($row['bssid'])) : null;
            if ($bssid && $dbBssid && $bssid === $dbBssid) return true;
            if ($ssid && $dbSsid && strcasecmp($ssid, $dbSsid) === 0) return true;
        }
        return false;
    }
}

if (!function_exists('mobile_record_attendance')) {
    function mobile_record_attendance(PDO $pdo, $employeeId, $lat, $lon, $accuracy, $wifiSsid, $wifiBssid, $capturedAt = null, $reason = 'mobile') {
        $geofence = hrms_get_branch_geofence_for_employee($pdo, $employeeId);
        if (!empty($geofence) && (int)($geofence['geofence_enabled'] ?? 0) === 1) {
            if (!hrms_is_within_geofence($lat, $lon, $geofence)) {
                return ['success' => false, 'message' => 'Outside branch geofence'];
            }
        }

        if (!empty($geofence) && !empty($geofence['branch_id']) && mobile_branch_requires_wifi($pdo, $geofence['branch_id'])) {
            if (!mobile_branch_wifi_matches($pdo, $geofence['branch_id'], $wifiSsid, $wifiBssid)) {
                return ['success' => false, 'message' => 'Wi-Fi not in office range'];
            }
        }

        $timestamp = $capturedAt && strtotime($capturedAt) ? strtotime($capturedAt) : time();
        $date = date('Y-m-d', $timestamp);
        $time = date('H:i:s', $timestamp);

        $check = $pdo->prepare("SELECT COUNT(*) FROM attendance_logs WHERE emp_id = :emp AND date = :date AND time = :time");
        $check->execute([':emp' => $employeeId, ':date' => $date, ':time' => $time]);
        if ((int)$check->fetchColumn() > 0) {
            return ['success' => true, 'message' => 'Duplicate entry ignored', 'duplicate' => true];
        }

        $stmt = $pdo->prepare("INSERT INTO attendance_logs (emp_id, date, time, method, mach_sn, mach_id, manual_reason)
            VALUES (:emp, :date, :time, 2, 0, 0, :reason)");
        $stmt->execute([
            ':emp' => $employeeId,
            ':date' => $date,
            ':time' => $time,
            ':reason' => $reason
        ]);

        mobile_store_location($pdo, $employeeId, $lat, $lon, $accuracy, 'mobile');

        return ['success' => true, 'message' => 'Attendance recorded', 'date' => $date, 'time' => $time];
    }
}
