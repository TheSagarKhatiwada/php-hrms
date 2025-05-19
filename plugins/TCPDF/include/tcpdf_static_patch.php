<?php
/**
 * TCPDF Static Method Patch for getPageSizeFromFormat
 * 
 * This file patches the TCPDF_STATIC::getPageSizeFromFormat method to ensure it uses a safe,
 * self-contained implementation, avoiding potential issues if the original method
 * had dependencies that might not be met (e.g., cURL constants if it were to call getHttp indirectly).
 */

// Define a replacement for the TCPDF_STATIC::getPageSizeFromFormat method
if (!function_exists('tcpdf_static_getPageSizeFromFormat_safe_impl')) {
    /**
     * A safe, self-contained implementation for getPageSizeFromFormat.
     * @param string|array $format The page format name or array with custom format
     * @return array Page width and height, or an empty array on failure
     */
    function tcpdf_static_getPageSizeFromFormat_safe_impl($format) {
        static $allPageSizes = [
            'A0' => [2383.94, 3370.39], 'A1' => [1683.78, 2383.94], 'A2' => [1190.55, 1683.78], 'A3' => [841.89, 1190.55], 'A4' => [595.28, 841.89], 'A5' => [419.53, 595.28], 'A6' => [297.64, 419.53], 'A7' => [209.76, 297.64], 'A8' => [147.40, 209.76], 'A9' => [104.88, 147.40], 'A10' => [73.70, 104.88],
            'B0' => [2834.65, 4008.19], 'B1' => [2004.09, 2834.65], 'B2' => [1417.32, 2004.09], 'B3' => [1000.63, 1417.32], 'B4' => [708.66, 1000.63], 'B5' => [498.90, 708.66], 'B6' => [354.33, 498.90], 'B7' => [249.45, 354.33], 'B8' => [175.75, 249.45], 'B9' => [124.72, 175.75], 'B10' => [87.87, 124.72],
            'C0' => [2599.37, 3676.54], 'C1' => [1836.85, 2599.37], 'C2' => [1298.27, 1836.85], 'C3' => [918.43, 1298.27], 'C4' => [649.13, 918.43], 'C5' => [459.21, 649.13], 'C6' => [323.15, 459.21], 'C7' => [229.61, 323.15], 'C8' => [161.57, 229.61], 'C9' => [113.39, 161.57], 'C10' => [79.37, 113.39],
            'RA0' => [2437.80, 3458.27], 'RA1' => [1729.13, 2437.80], 'RA2' => [1218.90, 1729.13], 'RA3' => [864.57, 1218.90], 'RA4' => [609.45, 864.57],
            'SRA0' => [2551.18, 3628.35], 'SRA1' => [1814.17, 2551.18], 'SRA2' => [1275.59, 1814.17], 'SRA3' => [907.09, 1275.59], 'SRA4' => [637.80, 907.09],
            'LETTER' => [612.00, 792.00], 'LEGAL' => [612.00, 1008.00], 'EXECUTIVE' => [521.86, 756.00], 'FOLIO' => [612.00, 936.00],
        ];

        if (is_array($format)) {
            $pf = array(); // Page format array
            if (isset($format['custom']) && is_array($format['custom']) && count($format['custom']) == 2) {
                $pf = array(floatval($format['custom'][0]), floatval($format['custom'][1]));
            } elseif (isset($format[0]) && is_numeric($format[0]) && isset($format[1]) && is_numeric($format[1])) {
                $pf = array(floatval($format[0]), floatval($format[1]));
            } else {
                // If it's an array but not a recognized custom format, try to see if it's a named format string within the array
                // This case is less common for TCPDF, but good to be defensive.
                // Or, more likely, it's an invalid format array, so default to A4.
                return $allPageSizes['A4']; 
            }

            if (isset($pf[0]) && isset($pf[1])) { // Ensure $pf was populated
                $format_rotate_int = 0;
                if (isset($format['Rotate'])) {
                    $format_rotate_int = intval($format['Rotate']);
                }

                if ($format_rotate_int != 0) {
                    if (($format_rotate_int % 90) != 0) {
                        // Invalid rotation, TCPDF original might throw an error or ignore.
                        // For safety, we just don't rotate.
                    } elseif ((($format_rotate_int / 90) % 2) != 0) {
                        // Swap width and height for 90, 270, etc.
                        $pf = array($pf[1], $pf[0]);
                    }
                }
                return $pf;
            }
            // If $pf is not correctly populated, default to A4
            return $allPageSizes['A4'];
        }

        // If $format is a string
        $format_upper = strtoupper((string)$format);
        if (isset($allPageSizes[$format_upper])) {
            return $allPageSizes[$format_upper];
        }
        
        // Default to A4 if format string not found
        return $allPageSizes['A4'];
    }
}

// Guard to ensure the shutdown function is registered only once
if (!defined('TCPDF_STATIC_PATCH_SHUTDOWN_REGISTERED')) {
    define('TCPDF_STATIC_PATCH_SHUTDOWN_REGISTERED', true);

    register_shutdown_function(function() {
        static $shutdown_logic_executed = false;
        if ($shutdown_logic_executed) {
            return;
        }
        $shutdown_logic_executed = true;

        if (!class_exists('TCPDF_STATIC', false)) {
            // TCPDF_STATIC class not loaded, nothing to patch.
            return; 
        }

        try {
            $ref = new ReflectionClass('TCPDF_STATIC');
            if ($ref->getName() === 'TCPDF_STATIC_PageSizePatched') {
                return; // Already patched and aliased.
            }
        } catch (ReflectionException $e) {
            // error_log("TCPDF_STATIC_PATCH: ReflectionException for TCPDF_STATIC: " . $e->getMessage());
            return; // Cannot reflect, cannot proceed safely
        }

        // Define TCPDF_STATIC_PageSizePatched if it doesn't exist.
        if (!class_exists('TCPDF_STATIC_PageSizePatched', false)) {
            class TCPDF_STATIC_PageSizePatched extends TCPDF_STATIC {
                public static function getPageSizeFromFormat($format, $orientation='') {
                    $size = tcpdf_static_getPageSizeFromFormat_safe_impl($format);
                    
                    // Ensure $size is an array with at least two elements before proceeding
                    if (is_array($size) && count($size) >= 2) {
                        if (!empty($orientation)) {
                            $orientation = strtoupper($orientation);
                            // Apply orientation (P/L) to swap width/height if necessary
                            if (($orientation == 'L' && $size[0] < $size[1]) || ($orientation == 'P' && $size[0] > $size[1])) {
                                $size = array($size[1], $size[0]);
                            }
                        }
                    } else {
                        // If $size is not as expected (e.g. from a faulty custom format), default to A4 dimensions
                        // This ensures we always return a valid array [width, height]
                        $default_A4 = [595.28, 841.89];
                        if (!empty($orientation) && strtoupper($orientation) == 'L') {
                            $size = [$default_A4[1], $default_A4[0]];
                        } else {
                            $size = $default_A4;
                        }
                    }
                    return $size;
                }
            }
        }

        // Perform the aliasing only if TCPDF_STATIC_PageSizePatched has been defined.
        if (class_exists('TCPDF_STATIC_PageSizePatched', false)) {
            // Preserve original TCPDF_STATIC as TCPDF_STATIC_ORIG_PageSize, if not already done
            // and if TCPDF_STATIC is not already the patched class.
            if (!class_exists('TCPDF_STATIC_ORIG_PageSize', false)) {
                try {
                    $currentStaticReflection = new ReflectionClass('TCPDF_STATIC');
                    if ($currentStaticReflection->getName() !== 'TCPDF_STATIC_PageSizePatched') {
                        class_alias('TCPDF_STATIC', 'TCPDF_STATIC_ORIG_PageSize');
                    }
                } catch (ReflectionException $e) {
                    // error_log("TCPDF_STATIC_PATCH: ReflectionException for TCPDF_STATIC before aliasing ORIG: " . $e->getMessage());
                }
            }
            
            // Alias TCPDF_STATIC_PageSizePatched to TCPDF_STATIC.
            // The third parameter `true` for class_alias autoloads the class if not already loaded.
            // Since we check with class_exists before, it should be loaded.
            // class_alias('TCPDF_STATIC_PageSizePatched', 'TCPDF_STATIC', false); // Changed true to false
        } else {
            // error_log("TCPDF_STATIC_PATCH: TCPDF_STATIC_PageSizePatched not defined, cannot alias.");
        }
    });
}
