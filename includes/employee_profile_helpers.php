<?php
/**
 * Helper utilities for employee profile tabs (academic and experience data).
 */

if (!function_exists('collect_academic_records_from_request')) {
    function collect_academic_records_from_request(array $request): array
    {
        $levels = $request['academic_degree_level'] ?? [];
        $institutions = $request['academic_institution'] ?? [];
        $fields = $request['academic_field'] ?? [];
        $years = $request['academic_graduation_year'] ?? [];
        $grades = $request['academic_grade'] ?? [];
        $remarks = $request['academic_remarks'] ?? [];

        $records = [];
        foreach ($levels as $index => $level) {
            $level = trim((string) $level);
            $institution = trim((string) ($institutions[$index] ?? ''));
            $field = trim((string) ($fields[$index] ?? ''));
            $year = trim((string) ($years[$index] ?? ''));
            $grade = trim((string) ($grades[$index] ?? ''));
            $note = trim((string) ($remarks[$index] ?? ''));

            if ($level === '' && $institution === '' && $field === '' && $year === '' && $grade === '' && $note === '') {
                continue;
            }

            $records[] = [
                'degree_level' => $level ?: 'Unspecified',
                'institution' => $institution ?: 'N/A',
                'field_of_study' => $field ?: null,
                'graduation_year' => ($year !== '' && ctype_digit($year)) ? (int) $year : null,
                'grade' => $grade ?: null,
                'remarks' => $note ?: null,
            ];
        }

        return $records;
    }
}

if (!function_exists('collect_experience_records_from_request')) {
    function collect_experience_records_from_request(array $request): array
    {
        $organizations = $request['experience_organization'] ?? [];
        $titles = $request['experience_job_title'] ?? [];
        $startDates = $request['experience_start_date'] ?? [];
        $endDates = $request['experience_end_date'] ?? [];
        $responsibilities = $request['experience_responsibilities'] ?? [];
        $achievements = $request['experience_achievements'] ?? [];
        $currentFlags = $request['experience_currently_working'] ?? [];

        $records = [];
        foreach ($organizations as $index => $org) {
            $org = trim((string) $org);
            $title = trim((string) ($titles[$index] ?? ''));
            $start = trim((string) ($startDates[$index] ?? ''));
            $end = trim((string) ($endDates[$index] ?? ''));
            $resp = trim((string) ($responsibilities[$index] ?? ''));
            $ach = trim((string) ($achievements[$index] ?? ''));
            $currentFlag = isset($currentFlags[$index]) ? (int) (bool)$currentFlags[$index] : 0;

            if ($org === '' && $title === '' && $start === '' && $end === '' && $resp === '' && $ach === '') {
                continue;
            }

            if ($currentFlag) {
                $end = '';
            }

            $records[] = [
                'organization' => $org ?: 'N/A',
                'job_title' => $title ?: 'N/A',
                'start_date' => $start !== '' ? $start : null,
                'end_date' => $currentFlag ? null : ($end !== '' ? $end : null),
                'responsibilities' => $resp ?: null,
                'achievements' => $ach ?: null,
                'currently_working' => $currentFlag,
            ];
        }

        return $records;
    }
}

if (!function_exists('sync_employee_academic_records')) {
    function sync_employee_academic_records(PDO $pdo, string $employeeId, array $records): void
    {
        $deleteStmt = $pdo->prepare('DELETE FROM employee_academic_records WHERE employee_id = :employee_id');
        $deleteStmt->execute([':employee_id' => $employeeId]);

        if (empty($records)) {
            return;
        }

        $insertStmt = $pdo->prepare('INSERT INTO employee_academic_records (employee_id, degree_level, institution, field_of_study, graduation_year, grade, remarks)
            VALUES (:employee_id, :degree_level, :institution, :field_of_study, :graduation_year, :grade, :remarks)');

        foreach ($records as $record) {
            $insertStmt->execute([
                ':employee_id' => $employeeId,
                ':degree_level' => $record['degree_level'],
                ':institution' => $record['institution'],
                ':field_of_study' => $record['field_of_study'],
                ':graduation_year' => $record['graduation_year'],
                ':grade' => $record['grade'],
                ':remarks' => $record['remarks'],
            ]);
        }
    }
}

if (!function_exists('sync_employee_experience_records')) {
    function sync_employee_experience_records(PDO $pdo, string $employeeId, array $records): void
    {
        $deleteStmt = $pdo->prepare('DELETE FROM employee_experience_records WHERE employee_id = :employee_id');
        $deleteStmt->execute([':employee_id' => $employeeId]);

        if (empty($records)) {
            return;
        }

        $insertStmt = $pdo->prepare('INSERT INTO employee_experience_records (employee_id, organization, job_title, start_date, end_date, responsibilities, achievements, currently_working)
            VALUES (:employee_id, :organization, :job_title, :start_date, :end_date, :responsibilities, :achievements, :currently_working)');

        foreach ($records as $record) {
            $insertStmt->execute([
                ':employee_id' => $employeeId,
                ':organization' => $record['organization'],
                ':job_title' => $record['job_title'],
                ':start_date' => $record['start_date'],
                ':end_date' => $record['end_date'],
                ':responsibilities' => $record['responsibilities'],
                ':achievements' => $record['achievements'],
                ':currently_working' => $record['currently_working'],
            ]);
        }
    }
}
