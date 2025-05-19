<?php
/**
 * Cache Control Headers
 * 
 * This file sets headers to prevent browsers from caching PHP pages.
 * Include this file at the top of your PHP scripts before any output.
 */

// Prevent caching in browsers and proxies
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Expires: 0");
?>
