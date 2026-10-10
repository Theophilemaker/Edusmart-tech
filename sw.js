const CACHE_NAME = 'edusmart-pro-v1';
const urlsToCache = [
  '/',
  '/index.php',
  '/products.php',
  '/assets/css/style.css',
  '/assets/css/global.css',
  '/assets/css/ai-widget.css',
  '/assets/js/main.js',
  '/assets/js/ai-widget.js',
  '/manifest.json',
  '/offline.html'
];

// Install event – cache static assets
self.addEventListener('install', event => {
  event.waitUntil(
    caches.open(CACHE_NAME)
      .then(cache => cache.addAll(urlsToCache))
  );
});

// Activate event – clean up old caches
self.addEventListener('activate', event => {
  event.waitUntil(
    caches.keys().then(keys =>
      Promise.all(keys.filter(key => key !== CACHE_NAME).map(key => caches.delete(key)))
    )
  );
});

// Fetch event – handle different request types
self.addEventListener('fetch', event => {
  const url = new URL(event.request.url);

  // 1. API requests (network‑first, cache fallback)
  if (url.pathname.startsWith('/api/')) {
    event.respondWith(
      fetch(event.request)
        .then(response => {
          const cloned = response.clone();
          caches.open('api-cache-v1').then(cache => {
            cache.put(event.request, cloned);
          });
          return response;
        })
        .catch(() => caches.match(event.request))
    );
    return;
  }

  // 2. Static assets (cache‑first, network fallback)
  event.respondWith(
    caches.match(event.request)
      .then(cached => cached || fetch(event.request).then(response => {
        // Optionally cache new static assets
        const cloned = response.clone();
        caches.open(CACHE_NAME).then(cache => cache.put(event.request, cloned));
        return response;
      }))
      .catch(() => {
        // If all fails and it's a navigation request, show offline page
        if (event.request.mode === 'navigate') {
          return caches.match('/offline.html');
        }
      })
  );
});