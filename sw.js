const CACHE_NAME = 'hrms-cache-v3'; // Incremented cache version
const urlsToCache = [
  // Only cache static assets, not PHP pages
  './resources/css/style.css',
  './resources/js/main.js',
  './resources/images/favicon.png',
  './manifest.json',
  'https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css',
  'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css',
  'https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css'
];

// Install event - cache the assets with better error handling
self.addEventListener('install', event => {
  event.waitUntil(
    caches.open(CACHE_NAME)
      .then(cache => {
        // Use Promise.allSettled instead of Promise.all to continue even if some files fail
        return Promise.allSettled(
          urlsToCache.map(url => {
            return cache.add(url).catch(error => {
              console.warn(`Failed to cache: ${url}`, error);
              // Continue despite the error
              return null;
            });
          })
        );
      })
  );
});

// Fetch event - serve from cache if available, but never cache PHP files
self.addEventListener('fetch', event => {
  const url = new URL(event.request.url);
  
  // For PHP files or URLs with query parameters, always fetch from network
  if (url.pathname.endsWith('.php') || url.search) {
    event.respondWith(
      fetch(event.request)
    );
  } 
  // Handle API requests - always fetch from network
  else if (event.request.url.includes('/api/')) {
    event.respondWith(
      fetch(event.request)
    );
  } else {
    // For static assets, check cache first, then network
    event.respondWith(
      caches.match(event.request)
        .then(cachedResponse => {
          if (cachedResponse) {
            return cachedResponse;
          }
          
          // Not in cache, fetch from network
          return fetch(event.request);
        })
    );
  }
});

// Activate event - clean up old caches and avoid caching employees.php
self.addEventListener('activate', event => {
  const cacheWhitelist = [CACHE_NAME];
  event.waitUntil(
    caches.keys().then(cacheNames => {
      return Promise.all(
        cacheNames.map(cacheName => {
          if (cacheWhitelist.indexOf(cacheName) === -1) {
            return caches.delete(cacheName);
          }
        }).concat([
          // Ensure employees.php is never cached
          caches.open(CACHE_NAME).then(cache => {
            return cache.keys().then(requests => {
              return Promise.all(
                requests
                  .filter(request => request.url.includes('employees.php'))
                  .map(request => cache.delete(request))
              );
            });
          })
        ])
      );
    })
  );
});