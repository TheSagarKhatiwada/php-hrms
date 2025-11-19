define('INCLUDE_CHECK', true);
<?php
/**
 * Converted temporary test into a safe no-op migration so the migration runner
 * does not attempt to execute arbitrary CLI test scripts.
 */

return [
    'up' => function($pdo) {
        // no-op
    },
    'down' => function($pdo) {
        // no-op
    }
];
