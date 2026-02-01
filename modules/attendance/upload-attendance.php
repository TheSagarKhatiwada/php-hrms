<?php
// Include session configuration (session is already started in session_config.php)
require_once '../../includes/session_config.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['attendanceFile'])) {
    require '../../includes/db_connection.php'; // Include the PDO connection

    $file = $_FILES['attendanceFile'];

    // Validate file upload
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $uploadErrors = [
            UPLOAD_ERR_INI_SIZE => 'File exceeds upload_max_filesize in server settings.',
            UPLOAD_ERR_FORM_SIZE => 'File exceeds MAX_FILE_SIZE in form.',
            UPLOAD_ERR_PARTIAL => 'File was only partially uploaded.',
            UPLOAD_ERR_NO_FILE => 'No file was uploaded.',
            UPLOAD_ERR_NO_TMP_DIR => 'Missing a temporary folder on the server.',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk.',
            UPLOAD_ERR_EXTENSION => 'File upload stopped by a PHP extension.'
        ];
        $errorMessage = $uploadErrors[$file['error']] ?? 'File upload error.';
        $_SESSION['error'] = $errorMessage;
        header('Location: attendance.php');
        exit();
    }

    // Ensure it's a .txt or .csv file
    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($extension, ['txt', 'csv'], true)) {
        $_SESSION['error'] = 'Invalid file type. Only .txt or .csv files are allowed.';
        header('Location: attendance.php');
        exit();
    }

    $isCsv = $extension === 'csv';

    $parseLine = function(string $line) use ($isCsv): array {
        $line = trim($line);
        if ($line === '') {
            return [];
        }
        if ($isCsv || strpos($line, ',') !== false) {
            $csv = str_getcsv($line);
            if (count($csv) > 1) {
                return $csv;
            }
        }
        $data = preg_split('/\t+/', $line);
        if (count($data) < 2) {
            $data = preg_split('/\s+/', $line);
        }
        return $data ?: [];
    };

    $detectColumns = function(array $header): ?array {
        if (empty($header)) {
            return null;
        }
        $map = [];
        foreach ($header as $idx => $col) {
            $label = strtolower(trim((string)$col));
            $label = preg_replace('/\s+/', ' ', $label);
            if ($label === '') {
                continue;
            }
            if ((strpos($label, 'date') !== false || strpos($label, 'datetime') !== false) && !isset($map['date']) && !isset($map['datetime'])) {
                if (strpos($label, 'datetime') !== false) {
                    $map['datetime'] = $idx;
                } else {
                    $map['date'] = $idx;
                }
                continue;
            }
            if (strpos($label, 'time') !== false && !isset($map['time'])) {
                $map['time'] = $idx;
                continue;
            }
            if ((strpos($label, 'unique') !== false || $label === 'sn' || $label === 'no' || $label === 'sno' || strpos($label, 'serial') !== false || $label === 'mach_sn') && !isset($map['mach_sn'])) {
                $map['mach_sn'] = $idx;
                continue;
            }
            if ((strpos($label, 'machine id') !== false || $label === 'mach_id' || $label === 'userid' || $label === 'user id' || $label === 'employee id' || $label === 'enno') && !isset($map['mach_id'])) {
                $map['mach_id'] = $idx;
                continue;
            }
        }

        if (!isset($map['mach_id'])) {
            foreach ($header as $idx => $col) {
                $label = strtolower(trim((string)$col));
                if (in_array($label, ['id', 'employeeid', 'empid'], true)) {
                    $map['mach_id'] = $idx;
                    break;
                }
            }
        }

        if (isset($map['mach_sn'], $map['mach_id'], $map['datetime'])) {
            return $map;
        }

        if (isset($map['mach_sn'], $map['mach_id'], $map['date'], $map['time'])) {
            return $map;
        }

        return null;
    };

    $normalizeDate = function(string $value): ?string {
        $value = trim($value);
        if ($value === '') {
            return null;
        }
        $formats = ['Y-m-d', 'Y/m/d', 'd/m/Y', 'm/d/Y', 'd-m-Y', 'm-d-Y'];
        foreach ($formats as $format) {
            $dt = DateTime::createFromFormat($format, $value);
            if ($dt instanceof DateTime) {
                return $dt->format('Y-m-d');
            }
        }
        return null;
    };

    $normalizeTime = function(string $value): ?string {
        $value = trim($value);
        if ($value === '') {
            return null;
        }
        $formats = ['H:i:s', 'H:i'];
        foreach ($formats as $format) {
            $dt = DateTime::createFromFormat($format, $value);
            if ($dt instanceof DateTime) {
                return $dt->format('H:i:s');
            }
        }
        return null;
    };

    // Read the file
    $fileContent = file($file['tmp_name'], FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $inserted = 0;
    $updated = 0;
    $skipped = 0;
    $invalidSkipped = 0;
    $duplicateSkipped = 0;
    $columnMap = null;
    $hasHeader = false;

    if (!empty($fileContent)) {
        $headerRow = $parseLine($fileContent[0]);
        $columnMap = $detectColumns($headerRow);
        $hasHeader = $columnMap !== null;
    }

    try {
        // Begin transaction for data integrity
        $pdo->beginTransaction();

        foreach ($fileContent as $index => $line) {
            if ($hasHeader && $index === 0) {
                continue;
            }

            $data = $parseLine($line);

            if (empty($data)) {
                $skipped++;
                $invalidSkipped++;
                continue;
            }

            if ($columnMap) {
                $mach_sn = isset($data[$columnMap['mach_sn']]) ? trim((string)$data[$columnMap['mach_sn']]) : '';
                $mach_id = isset($data[$columnMap['mach_id']]) ? trim((string)$data[$columnMap['mach_id']]) : '';
                $dateRaw = isset($columnMap['date']) && isset($data[$columnMap['date']]) ? trim((string)$data[$columnMap['date']]) : '';
                $timeRaw = isset($columnMap['time']) && isset($data[$columnMap['time']]) ? trim((string)$data[$columnMap['time']]) : '';
                $dateTimeRaw = isset($columnMap['datetime']) && isset($data[$columnMap['datetime']]) ? trim((string)$data[$columnMap['datetime']]) : '';
            } else {
                if (count($data) < 7) {
                    $skipped++;
                    $invalidSkipped++;
                    continue;
                }
                $mach_sn = isset($data[0]) ? trim((string)$data[0]) : '';
                $mach_id = isset($data[2]) ? trim((string)$data[2]) : '';
                $dateRaw = '';
                $timeRaw = '';
                $dateTimeRaw = isset($data[6]) ? trim((string)$data[6]) : '';
                if (count($data) > 7 && empty($dateTimeRaw)) {
                    $dateRaw = isset($data[6]) ? trim((string)$data[6]) : '';
                    $timeRaw = isset($data[7]) ? trim((string)$data[7]) : '';
                    $dateTimeRaw = '';
                }
            }

            if (!empty($dateTimeRaw) && (empty($dateRaw) || empty($timeRaw))) {
                $parts = preg_split('/\s+/', $dateTimeRaw, 2);
                $dateRaw = $parts[0] ?? '';
                $timeRaw = $parts[1] ?? '';
                if (empty($timeRaw) && $columnMap && isset($columnMap['datetime'])) {
                    $timeCandidate = $data[$columnMap['datetime'] + 1] ?? '';
                    if (!empty($timeCandidate)) {
                        $timeRaw = trim((string)$timeCandidate);
                    }
                }
            }

            $date = $normalizeDate($dateRaw) ?? '';
            $time = $normalizeTime($timeRaw) ?? '';

            // Check if any required field is missing or invalid
            if (empty($mach_sn) || empty($mach_id) || empty($date) || empty($time)) {
                $skipped++; // Skip this row if there's invalid data
                $invalidSkipped++;
                continue;
            }

            // Check if the record already exists (prefer mach_id + date + time)
            if (!empty($mach_id) && !empty($date) && !empty($time)) {
                $checkQuery = "SELECT COUNT(*) FROM attendance_logs WHERE mach_id = ? AND date = ? AND time = ? AND method = 0";
                $stmt = $pdo->prepare($checkQuery);
                $stmt->execute([$mach_id, $date, $time]);
            } else {
                $checkQuery = "SELECT COUNT(*) FROM attendance_logs WHERE mach_sn = ?";
                $stmt = $pdo->prepare($checkQuery);
                $stmt->execute([$mach_sn]);
            }
            $count = $stmt->fetchColumn();

            if ($count > 0) {
                // Skip duplicates instead of updating existing entries
                $skipped++;
                $duplicateSkipped++;
                continue;
            }

            // Insert new record with NULL emp_id (will be updated later based on machine mapping)
            $insertQuery = "INSERT INTO attendance_logs (mach_sn, mach_id, emp_id, date, time, method) VALUES (?, ?, NULL, ?, ?, 0)";
            $stmt = $pdo->prepare($insertQuery);
            if ($stmt->execute([$mach_sn, $mach_id, $date, $time])) {
                $inserted++;
            }
        }

        // Set success message with inserted and updated counts
        $_SESSION['success'] = "$inserted records inserted, $updated updated, $duplicateSkipped skipped (already exists), $invalidSkipped skipped (invalid data).";

    // Update attendance_logs with emp_id from employees based on machine_id
    // Exclude manual method entries (method != 0) if needed
    $sql = "UPDATE attendance_logs a 
        JOIN employees e ON a.mach_id = e.mach_id 
        SET a.emp_id = e.emp_id 
        WHERE a.method = 0;";
    
        // Prepare and execute the statement
        $stmt = $pdo->prepare($sql);
        $stmt->execute();
    
        // Append to existing success message rather than overwriting it
        if (!empty($_SESSION['success'])) {
            $_SESSION['success'] .= ' Employee IDs updated successfully.';
        }
        
        // Commit the transaction if everything went well
        $pdo->commit();
    } catch (PDOException $e) {
        // Roll back the transaction if something failed
        $pdo->rollBack();
        $_SESSION['error'] = 'Error processing attendance data: ' . $e->getMessage();
        
        // Log the error to a file
        error_log('Upload attendance error: ' . $e->getMessage(), 3, 'error_log.txt');
    }

    header('Location: attendance.php');
    exit();
} else {
    $_SESSION['error'] = 'No file uploaded.';
    header('Location: attendance.php');
    exit();
}
?>