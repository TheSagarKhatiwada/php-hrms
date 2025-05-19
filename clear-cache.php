<?php
// Include session configuration
require_once 'includes/session_config.php';

// Set even stricter cache control headers
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Expires: 0");

// Output a script to unregister all service workers
echo "<script>
if ('serviceWorker' in navigator) {
    navigator.serviceWorker.getRegistrations().then(function(registrations) {
        for(let registration of registrations) {
            registration.unregister();
            console.log('Service Worker unregistered');
        }
        
        // Clear all caches
        if ('caches' in window) {
            caches.keys().then(function(cacheNames) {
                cacheNames.forEach(function(cacheName) {
                    caches.delete(cacheName);
                    console.log('Cache deleted:', cacheName);
                });
                // Force a page reload without cache
                window.location.replace('employees.php?_nocache=' + new Date().getTime());
            });
        } else {
            // Force a page reload without cache if caches API is not available
            window.location.replace('employees.php?_nocache=' + new Date().getTime());
        }
    });
}
</script>";
exit;
?>
