<?php
require_once __DIR__ . '/date_conversion.php';

if (!function_exists('hrms_to_nepali_digits')) {
    function hrms_to_nepali_digits($value): string
    {
        $map = ['0' => '०','1' => '१','2' => '२','3' => '३','4' => '४','5' => '५','6' => '६','7' => '७','8' => '८','9' => '९'];
        $value = (string)$value;
        $result = '';
        $length = strlen($value);
        for ($i = 0; $i < $length; $i++) {
            $char = $value[$i];
            $result .= $map[$char] ?? $char;
        }
        return $result;
    }
}

if (!function_exists('hrms_get_nepali_month_script')) {
    function hrms_get_nepali_month_script(int $month): string
    {
        $months = [
            1 => 'बैशाख',
            2 => 'जेठ',
            3 => 'असार',
            4 => 'श्रावण',
            5 => 'भाद्र',
            6 => 'आश्विन',
            7 => 'कार्तिक',
            8 => 'मंसिर',
            9 => 'पौष',
            10 => 'माघ',
            11 => 'फाल्गुन',
            12 => 'चैत्र'
        ];
        return $months[$month] ?? get_nepali_month_name($month);
    }
}

if (!function_exists('hrms_format_bs_label')) {
    function hrms_format_bs_label(int $year, int $month, int $day): string
    {
        $monthScript = hrms_get_nepali_month_script($month);
        return sprintf('%s %s %s', hrms_to_nepali_digits($year), $monthScript, hrms_to_nepali_digits($day));
    }
}

if (!function_exists('hrms_get_nepali_weekday_full')) {
    function hrms_get_nepali_weekday_full(int $weekdayZeroBased): string
    {
        // 0 = Sunday, 6 = Saturday (PHP date('w'))
        $map = [
            0 => 'आइतवार',
            1 => 'सोमवार',
            2 => 'मंगलवार',
            3 => 'बुधवार',
            4 => 'बिहीवार',
            5 => 'शुक्रवार',
            6 => 'शनिवार',
        ];
        return $map[$weekdayZeroBased] ?? '';
    }
}

if (!function_exists('hrms_get_nepali_weekday_short')) {
    function hrms_get_nepali_weekday_short(int $weekdayZeroBased): string
    {
        // Abbreviated labels aligned with the full names above
        $map = [
            0 => 'आइत',
            1 => 'सोम',
            2 => 'मंगल',
            3 => 'बुध',
            4 => 'बिही',
            5 => 'शुक्र',
            6 => 'शनि',
        ];
        return $map[$weekdayZeroBased] ?? '';
    }
}

if (!function_exists('hrms_get_date_display_mode')) {
    function hrms_get_date_display_mode(): string
    {
        if (isset($_SESSION['date_display_mode']) && $_SESSION['date_display_mode'] === 'bs') {
            return 'bs';
        }
        if (!empty($_COOKIE['date_display_mode']) && $_COOKIE['date_display_mode'] === 'bs') {
            $_SESSION['date_display_mode'] = 'bs';
            return 'bs';
        }
        return 'ad';
    }
}

if (!function_exists('hrms_set_date_display_mode')) {
    function hrms_set_date_display_mode(string $mode): string
    {
        $mode = strtolower($mode) === 'bs' ? 'bs' : 'ad';
        $_SESSION['date_display_mode'] = $mode;
        setcookie('date_display_mode', $mode, time() + (365 * 24 * 60 * 60), '/');
        return $mode;
    }
}

if (!function_exists('hrms_should_use_bs_dates')) {
    function hrms_should_use_bs_dates(): bool
    {
        return hrms_get_date_display_mode() === 'bs';
    }
}

if (!function_exists('hrms_format_preferred_date')) {
    function hrms_format_preferred_date(?string $adDate, string $format = 'd M Y'): string
    {
        if (empty($adDate) || $adDate === '0000-00-00') {
            return '-';
        }
        $timestamp = strtotime($adDate);
        if (!$timestamp) {
            return '-';
        }
        $adDateNormalized = date('Y-m-d', $timestamp);
        if (!hrms_should_use_bs_dates()) {
            return date($format, $timestamp);
        }

        $bsInfo = get_bs_for_ad_date($adDateNormalized);
        if (!$bsInfo) {
            return date($format, $timestamp);
        }

        $bsLabel = hrms_format_bs_label((int)$bsInfo['bs_year'], (int)$bsInfo['bs_month'], (int)$bsInfo['bs_day']);

        // If the requested format includes a weekday token, prepend the Nepali weekday
        $includesLongDay = strpos($format, 'l') !== false;
        $includesShortDay = strpos($format, 'D') !== false && !$includesLongDay;
        if ($includesLongDay || $includesShortDay) {
            $weekdayIndex = (int)date('w', $timestamp); // 0 (Sun) - 6 (Sat)
            $dayLabel = $includesLongDay ? hrms_get_nepali_weekday_full($weekdayIndex) : hrms_get_nepali_weekday_short($weekdayIndex);

            // If the format is only the weekday, return just that
            $strippedFormat = trim(str_replace(['l', 'D', ',', ' '], '', $format));
            if ($strippedFormat === '') {
                return $dayLabel;
            }

            return trim($dayLabel . ', ' . $bsLabel, ' ,');
        }

        return $bsLabel;
    }
}

if (!function_exists('hrms_format_preferred_datetime')) {
    function hrms_format_preferred_datetime(?string $adDateTime, string $format = 'd M Y g:i A', string $separator = ' • '): string
    {
        if (empty($adDateTime) || $adDateTime === '0000-00-00 00:00:00') {
            return '-';
        }
        $timestamp = strtotime($adDateTime);
        if (!$timestamp) {
            return '-';
        }
        if (!hrms_should_use_bs_dates()) {
            return date($format, $timestamp);
        }
        $adDate = date('Y-m-d', $timestamp);
        $bsInfo = get_bs_for_ad_date($adDate);
        if (!$bsInfo) {
            return date($format, $timestamp);
        }
        $dateLabel = hrms_format_bs_label((int)$bsInfo['bs_year'], (int)$bsInfo['bs_month'], (int)$bsInfo['bs_day']);
        $timeLabel = hrms_to_nepali_digits(date('g:i A', $timestamp));
        return $dateLabel . $separator . $timeLabel;
    }
}

if (!function_exists('hrms_format_preferred_date_range')) {
    function hrms_format_preferred_date_range(?string $startAd, ?string $endAd, string $format = 'd M Y'): string
    {
        if (!$startAd && !$endAd) {
            return '-';
        }
        if (!hrms_should_use_bs_dates()) {
            $startLabel = $startAd ? date($format, strtotime($startAd)) : '-';
            $endLabel = $endAd ? date($format, strtotime($endAd)) : '-';
            return trim($startLabel . ' - ' . $endLabel, ' -');
        }
        $startLabel = $startAd ? hrms_format_preferred_date($startAd, $format) : '-';
        $endLabel = $endAd ? hrms_format_preferred_date($endAd, $format) : '-';
        if ($startLabel === $endLabel) {
            return $startLabel;
        }
        return trim($startLabel . ' - ' . $endLabel, ' -');
    }
}
