<?php
// Helper functions for manual attendance reasons

if (!function_exists('hrms_reason_label_map')) {
    function hrms_reason_label_map(): array {
        return [
            '1' => 'Card Forgot',
            '2' => 'Card Lost',
            '3' => 'Forgot to Punch',
            '4' => 'Office Work Delay',
            '5' => 'Field Visit',
            '6' => 'Power Outage',
            '7' => 'Reader Offline',
            '8' => 'Card Not Issued'
        ];
    }
}

if (!function_exists('hrms_map_reason_code_to_label')) {
    /**
     * Map a numeric/string code to a human-readable label.
     * Falls back to the original value if unknown.
     */
    function hrms_map_reason_code_to_label($code): string {
        $codeStr = is_null($code) ? '' : trim((string)$code);
        $map = hrms_reason_label_map();
        if ($codeStr === '') return '';
        return $map[$codeStr] ?? $codeStr; // if it's already a label or unrecognized code
    }
}

if (!function_exists('hrms_parse_manual_reason')) {
    /**
     * Parse a stored manual_reason string into [label, remarks].
     * Supports both " || " and legacy "|" separators and plain labels.
     * @return array{label:string, remarks:string}
     */
    function hrms_parse_manual_reason($raw): array {
        $rawStr = is_null($raw) ? '' : trim((string)$raw);
        if ($rawStr === '') return ['label' => '', 'remarks' => ''];

        // Prefer spaced separator first
        if (strpos($rawStr, ' || ') !== false) {
            $parts = explode(' || ', $rawStr, 2);
            $codeOrLabel = trim($parts[0]);
            $remarks = isset($parts[1]) ? trim($parts[1]) : '';
            $label = hrms_map_reason_code_to_label($codeOrLabel);
            return ['label' => $label, 'remarks' => $remarks];
        }

        // Backward compat: single pipe with optional spaces, only split on the first occurrence
        if (strpos($rawStr, '|') !== false) {
            // Normalize spaces around the first pipe only
            $pos = strpos($rawStr, '|');
            $left = trim(substr($rawStr, 0, $pos));
            $right = trim(substr($rawStr, $pos + 1));
            $label = hrms_map_reason_code_to_label($left);
            return ['label' => $label, 'remarks' => $right];
        }

        // No separator: treat entire string as code or label
        $label = hrms_map_reason_code_to_label($rawStr);
        return ['label' => $label, 'remarks' => ''];
    }
}

if (!function_exists('hrms_format_reason_for_report')) {
    /**
     * Format a manual_reason raw value for reports, e.g., "Label (remarks)" or just "Label".
     */
    function hrms_format_reason_for_report($raw): string {
        $parsed = hrms_parse_manual_reason($raw);
        $label = $parsed['label'];
        $remarks = $parsed['remarks'];
        if ($label === '' && $remarks === '') return '';
        if ($label !== '' && $remarks !== '') return $label . ' (' . $remarks . ')';
        return $label !== '' ? $label : $remarks;
    }
}
