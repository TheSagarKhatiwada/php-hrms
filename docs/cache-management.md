# Cache Management in PHP-HRMS

## Overview
This document explains the cache control mechanisms in the PHP-HRMS system to prevent issues with outdated content being displayed in the browser.

## Cache Control Implementation

### 1. Cache Control Headers
The system includes a central `cache_control.php` file that sets HTTP headers to prevent browsers from caching PHP pages:

```php
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Expires: 0");
```

### 2. Timestamp Query Parameters
For forms and redirects, we add timestamp parameters to ensure each request is treated as unique:

```php
<input type="hidden" name="no_cache" value="<?php echo time(); ?>">
```

### 3. JavaScript Refresh Function
A JavaScript function is available to force a page refresh without cache:

```javascript
const forceRefresh = () => {
    const timestamp = new Date().getTime();
    window.location.href = window.location.pathname + '?nocache=' + timestamp;
};
```

### 4. Browser Cache Settings
For optimal results, users should:
- Use the "Refresh" button in the application interface
- If issues persist, clear browser cache manually (Ctrl+F5 or Cmd+Shift+R)
- Ensure proper PHP session management is in place

## Troubleshooting
If users still encounter cached content:
1. Check if cache_control.php is included at the top of the PHP file
2. Verify no output is sent before headers
3. Test in different browsers or private browsing mode
4. Review server-side caching mechanisms (e.g., OPcache, page caching)
