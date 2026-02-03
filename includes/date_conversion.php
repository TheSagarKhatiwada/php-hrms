<?php
/**
 * Date conversion helper utilities.
 *
 * Provides lookup helpers for the manually managed `date_conversion` table
 * which stores both Gregorian (AD) and Bikram Sambat (BS) dates in
 * YYYY-MM-DD format.
 */

require_once __DIR__ . '/db_connection.php';

if (!function_exists('get_nepali_month_name')) {
    /**
     * Get Nepali month label for a BS month number (1-12).
     */
    function get_nepali_month_name(int $monthNumber): string
    {
        static $months = [
            1 => 'Baisakh',
            2 => 'Jestha',
            3 => 'Ashadh',
            4 => 'Shrawan',
            5 => 'Bhadra',
            6 => 'Ashwin',
            7 => 'Kartik',
            8 => 'Mangsir',
            9 => 'Poush',
            10 => 'Magh',
            11 => 'Falgun',
            12 => 'Chaitra',
        ];

        return $months[$monthNumber] ?? 'Unknown';
    }
}

// Remote-based implementations: replace direct DB lookups with API calls
if (!function_exists('remote_fetch_calendar_month')) {
    /**
     * Fetch a calendar month from the configured remote API and return normalized day rows.
     * Returns an array with keys: 'days', 'leading_days', 'trailing_days'.
     */
    function remote_fetch_calendar_month(string $mode, int $year, int $month): array
    {
        $apiUrl = defined('NEPALI_CALENDAR_API_URL') ? NEPALI_CALENDAR_API_URL : '';
        $apiKey = defined('NEPALI_CALENDAR_API_KEY') ? NEPALI_CALENDAR_API_KEY : '';

        if (empty($apiUrl)) {
            throw new RuntimeException('Remote calendar API is not configured; local DB lookups are disabled.');
        }

        $query = http_build_query(['mode' => $mode, 'year' => $year, 'month' => $month]);
        $requestUrl = rtrim($apiUrl, '/') . '/?' . $query;

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $requestUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);

        $headers = ['Accept: application/json'];
        if (!empty($apiKey)) {
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

        // Normalize potential wrappers
        if (isset($data['status']) && $data['status'] === 'success' && isset($data['days'])) {
            return [
                'days' => $data['days'],
                'leading_days' => $data['leading_days'] ?? [],
                'trailing_days' => $data['trailing_days'] ?? [],
                'meta' => $data['meta'] ?? [],
            ];
        }
        if (isset($data['data']) && isset($data['data']['days'])) {
            return [
                'days' => $data['data']['days'],
                'leading_days' => $data['data']['leading_days'] ?? [],
                'trailing_days' => $data['data']['trailing_days'] ?? [],
                'meta' => $data['data']['meta'] ?? [],
            ];
        }

        throw new RuntimeException('Remote calendar API returned an unexpected payload structure');
    }
}

if (!function_exists('get_bs_for_ad_date')) {
    /**
     * Convert AD date to BS by querying remote calendar API for the month containing the AD date.
     */
    function get_bs_for_ad_date(string $adDate): ?array
    {
        static $cache = [];
        $key = $adDate;
        if (isset($cache[$key])) {
            return $cache[$key];
        }

        $ts = strtotime($adDate);
        if ($ts === false) {
            return null;
        }
        $year = (int)date('Y', $ts);
        $month = (int)date('n', $ts);

        $monthData = remote_fetch_calendar_month('ad', $year, $month);
        $rows = array_merge($monthData['leading_days'] ?? [], $monthData['days'] ?? [], $monthData['trailing_days'] ?? []);
        foreach ($rows as $row) {
            if (isset($row['ad_date']) && $row['ad_date'] === $adDate) {
                [$bsYear, $bsMonth, $bsDay] = array_map('intval', explode('-', $row['bs_date']));
                $result = [
                    'ad_date' => $row['ad_date'],
                    'bs_date' => $row['bs_date'],
                    'bs_year' => $bsYear,
                    'bs_month' => $bsMonth,
                    'bs_day' => $bsDay,
                    'bs_month_name' => $row['bs_month_name'] ?? get_nepali_month_name($bsMonth),
                ];
                return $cache[$key] = $result;
            }
        }

        return $cache[$key] = null;
    }
}

if (!function_exists('get_ad_for_bs_date')) {
    /**
     * Convert BS date to AD by querying remote calendar API for the BS month containing the BS date.
     */
    function get_ad_for_bs_date(string $bsDate): ?array
    {
        static $cache = [];
        $key = $bsDate;
        if (isset($cache[$key])) {
            return $cache[$key];
        }

        $parts = explode('-', $bsDate);
        if (count($parts) !== 3) {
            return null;
        }
        [$by, $bm, $bd] = array_map('intval', $parts);

        $monthData = remote_fetch_calendar_month('bs', $by, $bm);
        $rows = array_merge($monthData['leading_days'] ?? [], $monthData['days'] ?? [], $monthData['trailing_days'] ?? []);
        foreach ($rows as $row) {
            if (isset($row['bs_date']) && $row['bs_date'] === $bsDate) {
                return $cache[$key] = [
                    'ad_date' => $row['ad_date'],
                    'bs_date' => $row['bs_date'],
                    'bs_year' => $row['bs_year'] ?? $by,
                    'bs_month' => $row['bs_month'] ?? $bm,
                    'bs_day' => $row['bs_day'] ?? $bd,
                    'bs_month_name' => $row['bs_month_name'] ?? get_nepali_month_name($row['bs_month'] ?? $bm),
                ];
            }
        }

        return $cache[$key] = null;
    }
}

if (!function_exists('get_ad_range_for_bs_month')) {
    /**
     * Get inclusive AD start/end dates for a BS month by querying remote API.
     */
    function get_ad_range_for_bs_month(int $bsYear, int $bsMonth): ?array
    {
        $monthData = remote_fetch_calendar_month('bs', $bsYear, $bsMonth);
        $rows = $monthData['days'] ?? [];
        if (empty($rows)) {
            return null;
        }
        $start = reset($rows)['ad_date'];
        $end = end($rows)['ad_date'];
        return [$start, $end];
    }
}

if (!function_exists('get_conversion_rows_by_ad_range')) {
    /**
     * Fetch conversion rows for an AD date range by requesting month chunks from the remote API.
     */
    function get_conversion_rows_by_ad_range(string $startDate, string $endDate): array
    {
        $startTs = strtotime($startDate);
        $endTs = strtotime($endDate);
        if ($startTs === false || $endTs === false || $startTs > $endTs) {
            return [];
        }

        $rows = [];
        $year = (int)date('Y', $startTs);
        $month = (int)date('n', $startTs);

        while (strtotime(sprintf('%04d-%02d-01', $year, $month)) <= $endTs) {
            $monthData = remote_fetch_calendar_month('ad', $year, $month);
            foreach (array_merge($monthData['leading_days'] ?? [], $monthData['days'] ?? [], $monthData['trailing_days'] ?? []) as $r) {
                if (isset($r['ad_date']) && strtotime($r['ad_date']) >= $startTs && strtotime($r['ad_date']) <= $endTs) {
                    $rows[] = $r;
                }
            }
            // increment month
            $month++;
            if ($month > 12) { $month = 1; $year++; }
        }

        usort($rows, function($a, $b){ return strcmp($a['ad_date'], $b['ad_date']); });
        return $rows;
    }
}

if (!function_exists('get_conversion_rows_by_bs_range')) {
    /**
     * Fetch conversion rows for a BS month by calling remote API for the BS month.
     */
    function get_conversion_rows_by_bs_range(int $bsYear, int $bsMonth): array
    {
        $monthData = remote_fetch_calendar_month('bs', $bsYear, $bsMonth);
        // 'days' are already the current month rows in the remote payload
        return $monthData['days'] ?? [];
    }
}
