/**
 * Hisabio - Service Worker for PWA Asset Caching
 * PRD Section 24: Safe static asset caching only. Never cache financial API data.
 */

const CACHE_NAME = 'hisabio-static-v1';
const STATIC_ASSETS = [
  'favicon.ico',
  'favicon.svg',
  'assets/css/style.css',
  'assets/css/responsive.css',
  'assets/css/animations.css',
  'assets/css/print.css',
  'assets/js/app.js',
  'assets/js/ajax.js',
  'assets/js/ledger.js',
  'assets/images/logo.svg',
  'assets/images/logo.png',
  'assets/images/app-icon.svg',
  'assets/images/pwa-icon-192.png',
  'manifest.json'
];

self.addEventListener('install', event => {
  event.waitUntil(
    caches.open(CACHE_NAME).then(cache => {
      return cache.addAll(STATIC_ASSETS);
    })
  );
  self.skipWaiting();
});

self.addEventListener('activate', event => {
  event.waitUntil(
    caches.keys().then(keys => {
      return Promise.all(
        keys.map(key => {
          if (key !== CACHE_NAME) {
            return caches.delete(key);
          }
        })
      );
    })
  );
  self.clients.claim();
});

self.addEventListener('fetch', event => {
  const url = new URL(event.request.url);

  // Network-first for all PHP pages and API endpoints (strict financial safety)
  if (url.pathname.endsWith('.php') || url.pathname.includes('/api/')) {
    event.respondWith(
      fetch(event.request).catch(() => {
        return new Response('Offline - Please reconnect to save or view live financial records.', {
          status: 503,
          statusText: 'Service Unavailable',
          headers: new Headers({ 'Content-Type': 'text/plain; charset=utf-8' })
        });
      })
    );
    return;
  }

  // Cache-first for static CSS/JS/images
  event.respondWith(
    caches.match(event.request).then(response => {
      return response || fetch(event.request);
    })
  );
});
