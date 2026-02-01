<?php
require_once __DIR__ . '/date_conversion.php';
require_once __DIR__ . '/utilities.php';

if (!function_exists('fetch_calendar_from_remote_api')) {
    /**
     * Fetch calendar payload from a configured remote API and normalize it to the local payload format.
     * This function will throw a RuntimeException if the API is unavailable or returns an unexpected structure.
     */
    function fetch_calendar_from_remote_api(string $mode, int $requestedYear, int $requestedMonth): array
    {
        $apiUrl = defined('NEPALI_CALENDAR_API_URL') ? NEPALI_CALENDAR_API_URL : '';
        $apiKey = defined('NEPALI_CALENDAR_API_KEY') ? NEPALI_CALENDAR_API_KEY : '';

        if (empty($apiUrl)) {
            throw new RuntimeException('Remote calendar API is not configured.');
        }

        $query = http_build_query([
            'mode' => $mode,
            'year' => $requestedYear,
            'month' => $requestedMonth,
        ]);

        $requestUrl = rtrim($apiUrl, '/') . '/?' . $query;

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $requestUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);

        $headers = [
            'Accept: application/json',
        ];
        if (!empty($apiKey)) {
            // Prefer Bearer token style for Authorization
            $headers[] = 'Authorization: Bearer ' . $apiKey;
        }
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        $response = curl_exec($ch);
        if ($response === false) {
            $err = curl_error($ch);
            curl_close($ch);
            throw new RuntimeException('Remote API request failed: ' . $err);
        }

        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode < 200 || $httpCode >= 300) {
            throw new RuntimeException('Remote API returned HTTP code ' . $httpCode);
        }

        $data = json_decode($response, true);
        if (!is_array($data)) {
            throw new RuntimeException('Invalid JSON from remote calendar API');
        }

        // If the remote returns a compatible payload (status + days), trust it after minimal validation
        if (isset($data['status']) && $data['status'] === 'success' && isset($data['days']) && is_array($data['days'])) {
            $payload = $data;
        } elseif (isset($data['data']) && isset($data['data']['days'])) {
            // Common alternative wrapper
            $payload = [
                'status' => $data['status'] ?? 'success',
                'meta' => $data['data']['meta'] ?? [],
                'days' => $data['data']['days'],
                'leading_days' => $data['data']['leading_days'] ?? [],
                'trailing_days' => $data['data']['trailing_days'] ?? [],
            ];
        } else {
            throw new RuntimeException('Remote calendar API returned an unexpected payload structure');
        }

        // Ensure required fields exist on each day item
        $allDates = [];
        foreach (array_merge($payload['leading_days'] ?? [], $payload['days'] ?? [], $payload['trailing_days'] ?? []) as $day) {
            if (!isset($day['ad_date']) || !isset($day['bs_date'])) {
                throw new RuntimeException('Remote calendar API missing ad_date or bs_date on day rows');
            }
            $allDates[] = $day['ad_date'];
        }

        if (empty($allDates)) {
            throw new RuntimeException('Remote calendar API returned no days');
        }

        // Determine grid start/end
        $gridStart = reset($allDates);
        $gridEnd = end($allDates);

        // Merge local holiday and celebration data if available
        $holidayLookup = [];
        if ($gridStart && $gridEnd && function_exists('get_holidays_in_range')) {
            $holidayRows = get_holidays_in_range($gridStart, $gridEnd);
            foreach ($holidayRows as $holidayRow) {
                $effective = $holidayRow['effective_date'] ?? $holidayRow['start_date'] ?? null;
                if (!$effective) {
                    continue;
                }
                $label = trim($holidayRow['name'] ?? 'Holiday');
                if ($label === '') {
                    $label = 'Holiday';
                }
                $holidayLookup[$effective][] = $label;
            }
        }

        $celebrationLookup = [];
        if ($gridStart && $gridEnd && function_exists('get_employee_celebrations_by_date_range')) {
            $celebrations = get_employee_celebrations_by_date_range($gridStart, $gridEnd);
            foreach ($celebrations as $celebration) {
                $eventDate = $celebration['event_date'] ?? null;
                if (!$eventDate) {
                    continue;
                }
                $celebrationLookup[$eventDate][] = [
                    'emp_id' => $celebration['emp_id'] ?? null,
                    'display_name' => $celebration['display_name'] ?? '',
                    'celebration_type' => $celebration['celebration_type'] ?? '',
                    'designation_name' => $celebration['designation_name'] ?? null,
                    'years_completed' => $celebration['years_completed'] ?? null,
                ];
            }
        }

        $today = date('Y-m-d');
        $mapFn = static function (array $row, bool $isCurrentMonth) use ($holidayLookup, $celebrationLookup, $today) {
            $timestamp = strtotime($row['ad_date']);
            $weekday = (int)date('w', $timestamp);
            $base = [
                'ad_date' => $row['ad_date'],
                'bs_date' => $row['bs_date'],
                'bs_year' => $row['bs_year'] ?? null,
                'bs_month' => $row['bs_month'] ?? null,
                'bs_day' => $row['bs_day'] ?? null,
                'bs_month_name' => $row['bs_month_name'] ?? null,
                'weekday' => $weekday,
                'is_today' => $row['ad_date'] === $today,
                'is_weekend' => $weekday === 6,
                'is_current_month' => $isCurrentMonth,
                'ad_day' => (int)date('j', $timestamp),
                'is_holiday' => false,
                'holiday_name' => null,
                'celebrations' => $celebrationLookup[$row['ad_date']] ?? [],
            ];

            $labels = $holidayLookup[$row['ad_date']] ?? [];
            $isSaturday = $weekday === 6;
            $holidayName = '';
            if (!empty($labels)) {
                $holidayName = implode(', ', array_unique($labels));
            }
            if ($holidayName === '' && $isSaturday) {
                $holidayName = '-';
            }
            if ($holidayName !== '') {
                $base['is_holiday'] = true;
                $base['holiday_name'] = $holidayName;
            }

            return $base;
        };

        $days = [];
        $leadingDays = [];
        $trailingDays = [];

        foreach ($payload['days'] as $row) {
            $days[] = $mapFn($row, true);
        }
        foreach ($payload['leading_days'] ?? [] as $row) {
            $leadingDays[] = $mapFn($row, false);
        }
        foreach ($payload['trailing_days'] ?? [] as $row) {
            $trailingDays[] = $mapFn($row, false);
        }

        // Ensure meta is set
        $meta = $payload['meta'] ?? [
            'mode' => $mode,
            'requested_year' => $requestedYear,
            'requested_month' => $requestedMonth,
        ];

        return [
            'status' => 'success',
            'meta' => $meta,
            'days' => $days,
            'leading_days' => $leadingDays,
            'trailing_days' => $trailingDays,
        ];
    }
}

if (!function_exists('get_calendar_payload')) {
    /**
     * Build calendar payload for AD or BS mode.
     *
     * @param string $mode 'ad' or 'bs'
     * @param int $requestedYear
     * @param int $requestedMonth
     * @param bool|null $forceUseRemote If true/false, force remote usage on/off; if null use configured behavior
     */
    function get_calendar_payload(string $mode, int $requestedYear, int $requestedMonth, ?bool $forceUseRemote = null): array
    {
        // Enforce API-only behavior: local DB-based date conversion is disabled.
        $remoteUrl = defined('NEPALI_CALENDAR_API_URL') ? NEPALI_CALENDAR_API_URL : '';

        // If caller requests a specific mode (force remote true/false), obey it but still require remote URL to be present when requesting remote
        if ($forceUseRemote === null) {
            if (empty($remoteUrl)) {
                throw new RuntimeException('Remote calendar API is not configured. Local DB-based date conversion has been disabled.');
            }
            $useRemote = true;
        } else {
            $useRemote = (bool)$forceUseRemote;
            if ($useRemote && empty($remoteUrl)) {
                throw new RuntimeException('Remote calendar API is not configured; cannot force remote usage.');
            }
            // If explicitly forced off (false), still do not allow DB usage; fail fast
            if (!$useRemote) {
                throw new RuntimeException('Local DB-based date conversion is disabled. Configure a remote calendar API to proceed.');
            }
        }

        // Use remote API and annotate payload source
        if (defined('ENVIRONMENT') && ENVIRONMENT !== 'production') {
            $logPath = __DIR__ . '/../logs/calendar.log';
            $entry = sprintf("[%s] Calendar: using remote API for %s-%02d (useRemote=%s)\n", date('c'), $requestedYear, $requestedMonth, $useRemote ? 'true' : 'false');
            @file_put_contents($logPath, $entry, FILE_APPEND | LOCK_EX);
        }

        $payload = fetch_calendar_from_remote_api($mode, $requestedYear, $requestedMonth);
        if (!isset($payload['meta'])) { $payload['meta'] = []; }
        $payload['meta']['source'] = 'remote';
        return $payload;
        return $payload;

        if ($requestedMonth < 1 || $requestedMonth > 12) {
            throw new InvalidArgumentException('Invalid month');
        }

        $mode = strtolower($mode) === 'bs' ? 'bs' : 'ad';
        $today = date('Y-m-d');
        $days = [];
        $leadingDays = [];
        $trailingDays = [];
        $meta = [
            'mode' => $mode,
            'requested_year' => $requestedYear,
            'requested_month' => $requestedMonth,
            'source' => 'db',
        ];

        $firstRow = null;
        $lastRow = null;

        if ($mode === 'bs') {
            $rows = get_conversion_rows_by_bs_range($requestedYear, $requestedMonth);
            if (empty($rows)) {
                throw new RuntimeException('No conversion data for requested BS month.');
            }
            $firstRow = $rows[0];
            $lastRow = end($rows);
            $firstAd = $firstRow['ad_date'];
            $meta['ad_start_date'] = $firstAd;
            $meta['ad_end_date'] = $lastRow['ad_date'];
            $meta['bs_month_name'] = get_nepali_month_name($requestedMonth);
            $meta['bs_year'] = $requestedYear;
            $meta['english_month_name'] = date('F', strtotime($firstAd));
            $meta['english_year'] = (int)date('Y', strtotime($firstAd));
        } else {
            $startAd = sprintf('%04d-%02d-01', $requestedYear, $requestedMonth);
            $endAd = date('Y-m-t', strtotime($startAd));
            $rows = get_conversion_rows_by_ad_range($startAd, $endAd);
            if (empty($rows)) {
                throw new RuntimeException('No conversion data for requested AD month.');
            }
            $firstRow = $rows[0];
            $lastRow = end($rows);
            $meta['ad_start_date'] = $startAd;
            $meta['ad_end_date'] = $endAd;
            $meta['english_month_name'] = date('F', strtotime($startAd));
            $meta['english_year'] = $requestedYear;
            $meta['bs_month_name'] = $firstRow['bs_month_name'];
            $meta['bs_year'] = $firstRow['bs_year'];
        }

        if ($firstRow && $lastRow) {
            $meta['bs_range_start'] = [
                'month_name' => $firstRow['bs_month_name'],
                'month_index' => $firstRow['bs_month'],
                'year' => $firstRow['bs_year'],
            ];
            $meta['bs_range_end'] = [
                'month_name' => $lastRow['bs_month_name'],
                'month_index' => $lastRow['bs_month'],
                'year' => $lastRow['bs_year'],
            ];
        }

        $firstWeekday = (int)date('w', strtotime($rows[0]['ad_date']));
        $meta['first_weekday'] = $firstWeekday;

        $leadingRows = [];
        if ($firstWeekday > 0) {
            $prevEnd = date('Y-m-d', strtotime($rows[0]['ad_date'] . ' - 1 day'));
            $prevStart = date('Y-m-d', strtotime($rows[0]['ad_date'] . ' - ' . $firstWeekday . ' days'));
            $leadingRows = get_conversion_rows_by_ad_range($prevStart, $prevEnd);
            if (count($leadingRows) > $firstWeekday) {
                $leadingRows = array_slice($leadingRows, -$firstWeekday);
            }
        }

        $filledCells = $firstWeekday + count($rows);
        $trailingNeeded = $filledCells % 7 === 0 ? 0 : 7 - ($filledCells % 7);
        $trailingRows = [];
        if ($trailingNeeded > 0) {
            $lastRow = end($rows);
            $nextStart = date('Y-m-d', strtotime($lastRow['ad_date'] . ' + 1 day'));
            $nextEnd = date('Y-m-d', strtotime($lastRow['ad_date'] . ' + ' . $trailingNeeded . ' days'));
            $trailingRows = get_conversion_rows_by_ad_range($nextStart, $nextEnd);
            if (count($trailingRows) > $trailingNeeded) {
                $trailingRows = array_slice($trailingRows, 0, $trailingNeeded);
            }
        }

        $gridStart = !empty($leadingRows) ? $leadingRows[0]['ad_date'] : $rows[0]['ad_date'];
        $gridEnd = !empty($trailingRows) ? end($trailingRows)['ad_date'] : end($rows)['ad_date'];
        $holidayLookup = [];
        if ($gridStart && $gridEnd && function_exists('get_holidays_in_range')) {
            $holidayRows = get_holidays_in_range($gridStart, $gridEnd);
            foreach ($holidayRows as $holidayRow) {
                $effective = $holidayRow['effective_date'] ?? $holidayRow['start_date'] ?? null;
                if (!$effective) {
                    continue;
                }
                $label = trim($holidayRow['name'] ?? 'Holiday');
                if ($label === '') {
                    $label = 'Holiday';
                }
                $holidayLookup[$effective][] = $label;
            }
        }

        $celebrationLookup = [];
        if ($gridStart && $gridEnd && function_exists('get_employee_celebrations_by_date_range')) {
            $celebrations = get_employee_celebrations_by_date_range($gridStart, $gridEnd);
            foreach ($celebrations as $celebration) {
                $eventDate = $celebration['event_date'] ?? null;
                if (!$eventDate) {
                    continue;
                }
                $celebrationLookup[$eventDate][] = [
                    'emp_id' => $celebration['emp_id'] ?? null,
                    'display_name' => $celebration['display_name'] ?? '',
                    'celebration_type' => $celebration['celebration_type'] ?? '',
                    'designation_name' => $celebration['designation_name'] ?? null,
                    'years_completed' => $celebration['years_completed'] ?? null,
                ];
            }
        }

        $mapRow = static function (array $row, bool $isCurrentMonth, array $holidayLookup, array $celebrationLookup) use ($today): array {
            $timestamp = strtotime($row['ad_date']);
            $weekday = (int)date('w', $timestamp);
            $base = [
                'ad_date' => $row['ad_date'],
                'bs_date' => $row['bs_date'],
                'bs_year' => $row['bs_year'],
                'bs_month' => $row['bs_month'],
                'bs_day' => $row['bs_day'],
                'bs_month_name' => $row['bs_month_name'],
                'weekday' => $weekday,
                'is_today' => $row['ad_date'] === $today,
                'is_weekend' => $weekday === 6,
                'is_current_month' => $isCurrentMonth,
                'ad_day' => (int)date('j', $timestamp),
                'is_holiday' => false,
                'holiday_name' => null,
                'celebrations' => $celebrationLookup[$row['ad_date']] ?? [],
            ];

            $labels = $holidayLookup[$row['ad_date']] ?? [];
            $isSaturday = $weekday === 6;
            $holidayName = '';
            if (!empty($labels)) {
                $holidayName = implode(', ', array_unique($labels));
            }
            if ($holidayName === '' && $isSaturday) {
                $holidayName = '-';
            }
            if ($holidayName !== '') {
                $base['is_holiday'] = true;
                $base['holiday_name'] = $holidayName;
            }

            return $base;
        };

        foreach ($rows as $row) {
            $days[] = $mapRow($row, true, $holidayLookup, $celebrationLookup);
        }

        foreach ($leadingRows as $row) {
            $leadingDays[] = $mapRow($row, false, $holidayLookup, $celebrationLookup);
        }

        foreach ($trailingRows as $row) {
            $trailingDays[] = $mapRow($row, false, $holidayLookup, $celebrationLookup);
        }

        return [
            'status' => 'success',
            'meta' => $meta,
            'days' => $days,
            'leading_days' => $leadingDays,
            'trailing_days' => $trailingDays,
        ];
    }
}