const CACHE_NAME = 'hrms-cache-v1';
const urlsToCache = [
  './',
  './index.php',
  './login.php',
  './admin-dashboard.php',
  './user-dashboard.php',
  './manage_categories.php',
  './manage_assets.php',
  './attendance.php',
  './employees.php',
  './manage_assignments.php',
  './manage_maintenance.php',
  './profile.php',
  './assets.php',
  './monthly-report.php',
  './daily-report.php',
  './resources/css/style.css',
  './resources/js/main.js',
  './resources/js/assets-db.js',
  './resources/images/logo.png',
  './manifest.json',
  'https://code.jquery.com/jquery-3.6.0.min.js',
  'https://cdn.jsdelivr.net/npm/bootstrap@4.6.0/dist/css/bootstrap.min.css',
  'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css',
  'https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css',
  'https://cdn.jsdelivr.net/npm/sweetalert2@11',
  './resources/images/icon.svg'
];

// Install event - cache the assets
self.addEventListener('install', event => {
  event.waitUntil(
    caches.open(CACHE_NAME)
      .then(cache => {
        return cache.addAll(urlsToCache);
      })
  );
});

// Fetch event - serve from cache if available
self.addEventListener('fetch', event => {
  // Handle API requests
  if (event.request.url.includes('/api/')) {
    event.respondWith(
      fetch(event.request)
        .catch(() => {
          // Return offline response for API requests
          return new Response(JSON.stringify({
            offline: true,
            message: 'You are currently offline. Changes will be synced when you are back online.'
          }), {
            headers: { 'Content-Type': 'application/json' }
          });
        })
    );
  } else {
    // Handle regular requests
    event.respondWith(
      caches.match(event.request)
        .then(response => response || fetch(event.request))
    );
  }
});

// Sync event - handle offline changes when back online
self.addEventListener('sync', event => {
  if (event.tag === 'sync-assets') {
    event.waitUntil(syncOfflineChanges());
  }
});

// Function to sync offline changes
async function syncOfflineChanges() {
  try {
    const db = await openDB();
    const transaction = db.transaction(['offlineChanges'], 'readonly');
    const store = transaction.objectStore('offlineChanges');
    const index = store.index('status');
    const changes = await index.getAll('pending');
    
    for (const change of changes) {
      try {
        const response = await fetch('/api/sync-assets', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json'
          },
          body: JSON.stringify(change)
        });
        
        if (response.ok) {
          // Mark change as synced
          const syncTransaction = db.transaction(['offlineChanges'], 'readwrite');
          const syncStore = syncTransaction.objectStore('offlineChanges');
          change.status = 'synced';
          await syncStore.put(change);
        }
      } catch (error) {
        console.error('Error syncing change:', error);
      }
    }
  } catch (error) {
    console.error('Error in sync process:', error);
  }
}

// Activate event - clean up old caches
self.addEventListener('activate', event => {
  const cacheWhitelist = [CACHE_NAME];
  event.waitUntil(
    caches.keys().then(cacheNames => {
      return Promise.all(
        cacheNames.map(cacheName => {
          if (cacheWhitelist.indexOf(cacheName) === -1) {
            return caches.delete(cacheName);
          }
        })
      );
    })
  );
}); 