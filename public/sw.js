const CACHE_NAME = 'captura-irec-static-v1';
const STATIC_ASSETS = [
    '/manifest.json',
    '/img/favicon.png',
    '/img/pwa-192.png',
    '/img/pwa-512.png'
];

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(CACHE_NAME)
            .then((cache) => cache.addAll(STATIC_ASSETS))
            .then(() => self.skipWaiting())
    );
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys().then((keys) =>
            Promise.all(keys.filter((k) => k !== CACHE_NAME).map((k) => caches.delete(k)))
        ).then(() => self.clients.claim())
    );
});

// Estrategia Network-Only para peticiones dinámicas, API, PDFs y POST/PUT/DELETE.
// Solo usa caché de respaldo para los íconos/manifest estáticos.
self.addEventListener('fetch', (event) => {
    const req = event.request;
    if (req.method !== 'GET') return;

    const url = new URL(req.url);
    if (url.origin !== self.location.origin) return;

    const esEstatico = STATIC_ASSETS.includes(url.pathname);
    if (!esEstatico) {
        return;
    }

    event.respondWith(
        fetch(req).catch(() => caches.match(req))
    );
});
