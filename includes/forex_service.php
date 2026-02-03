<?php

if (!function_exists('forex_cache_file_path')) {
    function forex_cache_file_path(): string
    {
        $logDir = dirname(__DIR__) . '/logs';
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0775, true);
        }
        return $logDir . '/forex_snapshot.json';
    }
}

if (!function_exists('cache_forex_snapshot')) {
    function cache_forex_snapshot(array $snapshot): void
    {
        $payload = [
            'snapshot' => $snapshot,
            'cached_at' => gmdate('c'),
        ];
        @file_put_contents(forex_cache_file_path(), json_encode($payload), LOCK_EX);
    }
}

if (!function_exists('load_cached_forex_snapshot')) {
    function load_cached_forex_snapshot(): ?array
    {
        $path = forex_cache_file_path();
        if (!is_file($path)) {
            return null;
        }
        $raw = @file_get_contents($path);
        if ($raw === false) {
            return null;
        }
        $decoded = json_decode($raw, true);
        if (!is_array($decoded) || !isset($decoded['snapshot']) || !is_array($decoded['snapshot'])) {
            return null;
        }
        $snapshot = $decoded['snapshot'];
        $snapshot['stale'] = true;
        $snapshot['source'] = 'cache';
        $snapshot['cached_at'] = $decoded['cached_at'] ?? null;
        if (!isset($snapshot['fetched_at']) && $snapshot['cached_at']) {
            $snapshot['fetched_at'] = $snapshot['cached_at'];
        }
        return $snapshot;
    }
}

if (!function_exists('get_latest_forex_snapshot')) {
    function get_latest_forex_snapshot(int $rangeDays = 7, bool $allowCacheFallback = true): array
    {
        $rangeDays = max(1, min(30, $rangeDays));
        $timezone = new DateTimeZone('Asia/Kathmandu');
        $today = new DateTime('now', $timezone);
        $from = (clone $today)->modify('-' . $rangeDays . ' days');

        $query = http_build_query([
            'from' => $from->format('Y-m-d'),
            'to' => $today->format('Y-m-d'),
            'per_page' => $rangeDays,
            'page' => 1,
        ]);
        $url = 'https://www.nrb.org.np/api/forex/v1/rates?' . $query;

        try {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 12,
                CURLOPT_CONNECTTIMEOUT => 5,
                CURLOPT_HTTPHEADER => [
                    'Accept: application/json',
                    'User-Agent: HRMS-Forex-Client/1.0'
                ],
            ]);
            $response = curl_exec($ch);
            if ($response === false) {
                $error = curl_error($ch);
                curl_close($ch);
                throw new RuntimeException('Unable to reach Nepal Rastra Bank: ' . ($error ?: 'unknown error'));
            }
            $status = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
            curl_close($ch);
            if ($status !== 200) {
                throw new RuntimeException('Nepal Rastra Bank returned status ' . $status);
            }

            $decoded = json_decode($response, true);
            if (!is_array($decoded)) {
                throw new RuntimeException('Invalid forex response received.');
            }
            $payload = $decoded['data']['payload'] ?? [];
            if (!is_array($payload) || empty($payload)) {
                throw new RuntimeException('Forex data unavailable.');
            }

            usort($payload, static function ($a, $b) {
                return strtotime($b['date'] ?? '1970-01-01') <=> strtotime($a['date'] ?? '1970-01-01');
            });
            $latest = $payload[0];

            $snapshot = [
                'date' => $latest['date'] ?? null,
                'published_on' => $latest['published_on'] ?? null,
                'modified_on' => $latest['modified_on'] ?? null,
                'rates' => $latest['rates'] ?? [],
                'raw' => $latest,
                'stale' => false,
                'source' => 'live',
                'fetched_at' => gmdate('c'),
            ];
            cache_forex_snapshot($snapshot);
            return $snapshot;
        } catch (Throwable $e) {
            error_log('Forex fetch failed: ' . $e->getMessage(), 3, 'error_log.txt');
            if ($allowCacheFallback) {
                $cached = load_cached_forex_snapshot();
                if ($cached) {
                    return $cached;
                }
            }
            throw $e;
        }
    }
}