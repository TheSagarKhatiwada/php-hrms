<?php
/**
 * No-op migration placeholder
 * This file was previously used as a CLI runner and accidentally placed inside
 * the migrations folder. To avoid being treated as a migration that executes
 * arbitrary code, we replace it with this safe placeholder that is a valid
 * migration returning an up/down array.
 */

return [
	'up' => function($pdo) {
		// no-op
	},
	'down' => function($pdo) {
		// no-op
	}
];
