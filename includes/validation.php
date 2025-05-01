<?php
/**
 * Input Validation Functions
 * Provides consistent validation across the application
 */

/**
 * Validate and sanitize a string
 * 
 * @param string $input The input to validate
 * @param int $min_length Minimum string length
 * @param int $max_length Maximum string length
 * @return string|false Sanitized string or false if validation fails
 */
function validate_string($input, $min_length = 1, $max_length = 255) {
    $input = trim($input);
    
    if (strlen($input) < $min_length || strlen($input) > $max_length) {
        return false;
    }
    
    // Remove any potentially harmful characters
    return filter_var($input, FILTER_SANITIZE_STRING);
}

/**
 * Validate and sanitize an integer
 * 
 * @param mixed $input The input to validate
 * @param int $min Minimum value
 * @param int $max Maximum value
 * @return int|false Sanitized integer or false if validation fails
 */
function validate_int($input, $min = null, $max = null) {
    $options = [];
    
    if ($min !== null) {
        $options['min_range'] = $min;
    }
    
    if ($max !== null) {
        $options['max_range'] = $max;
    }
    
    $filtered = filter_var($input, FILTER_VALIDATE_INT, ['options' => $options]);
    
    return $filtered;
}

/**
 * Validate and sanitize a float/decimal value
 * 
 * @param mixed $input The input to validate
 * @param float $min Minimum value
 * @param float $max Maximum value
 * @return float|false Sanitized float or false if validation fails
 */
function validate_float($input, $min = null, $max = null) {
    // Replace comma with dot for international number formats
    $input = str_replace(',', '.', $input);
    
    $filtered = filter_var($input, FILTER_VALIDATE_FLOAT);
    
    if ($filtered === false) {
        return false;
    }
    
    if ($min !== null && $filtered < $min) {
        return false;
    }
    
    if ($max !== null && $filtered > $max) {
        return false;
    }
    
    return $filtered;
}

/**
 * Validate an email address
 * 
 * @param string $email The email to validate
 * @return string|false Sanitized email or false if validation fails
 */
function validate_email($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

/**
 * Validate a date in Y-m-d format
 * 
 * @param string $date The date string to validate
 * @return string|false Valid date string or false if invalid
 */
function validate_date($date) {
    // First check the format
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        return false;
    }
    
    // Now check if it's a valid date
    $parts = explode('-', $date);
    if (!checkdate($parts[1], $parts[2], $parts[0])) {
        return false;
    }
    
    return $date;
}

/**
 * Validate a time in H:i:s format
 * 
 * @param string $time The time string to validate
 * @return string|false Valid time string or false if invalid
 */
function validate_time($time) {
    // Check format
    if (!preg_match('/^\d{2}:\d{2}:\d{2}$/', $time)) {
        return false;
    }
    
    // Split time into components
    list($hour, $minute, $second) = explode(':', $time);
    
    // Check range
    if ($hour < 0 || $hour > 23 || $minute < 0 || $minute > 59 || $second < 0 || $second > 59) {
        return false;
    }
    
    return $time;
}

/**
 * Validate a file upload
 * 
 * @param array $file $_FILES array element
 * @param array $allowed_types Array of allowed MIME types
 * @param int $max_size Maximum file size in bytes
 * @return bool True if file is valid
 */
function validate_file($file, $allowed_types = [], $max_size = 5242880) {
    // Check if file was uploaded properly
    if (!isset($file['error']) || is_array($file['error'])) {
        return false;
    }
    
    // Check the error code
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return false;
    }
    
    // Check file size
    if ($file['size'] > $max_size) {
        return false;
    }
    
    // Check file type if types specified
    if (!empty($allowed_types)) {
        // Get file mime type
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime_type = $finfo->file($file['tmp_name']);
        
        if (!in_array($mime_type, $allowed_types)) {
            return false;
        }
    }
    
    return true;
}

/**
 * Sanitize output for HTML display
 * 
 * @param string $output String to be displayed in HTML
 * @return string Sanitized string
 */
function h($output) {
    return htmlspecialchars($output, ENT_QUOTES, 'UTF-8');
}
?>