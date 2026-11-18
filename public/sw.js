/* SuperShop POS — offline service worker.
 * Lets the POS terminal load and run with no network:
 *   - static assets (css/js/manifest)  -> cache-first, refreshed in the background
 *   - the POS page itself (navigation)  -> network-first, falls back to last cached copy
 * Only GET requests are handled; POST (checkout/sync) always hits the network and is
 * queued client-side by pos-offline.js when it fails.
 */
const CACHE = 'supershop-pos-v1';
const ASSETS = [
    '/css/app.css',
    '/css/pos.css',
    '/js/pos.js',
    '/js/pos-offline.js',
    '/manifest.webmanifest',
];

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(CACHE).then((cache) => cache.addAll(ASSETS)).then(() => self.skipWaiting())
    );
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys()
            .then((keys) => Promise.all(keys.filter((k) => k !== CACHE).map((k) => caches.delete(k))))
            .then(() => self.clients.claim())
    );
});

self.addEventListener('fetch', (event) => {
    const req = event.request;
    if (req.method !== 'GET') return; // never intercept checkout/sync POSTs

    const url = new URL(req.url);

    // The POS terminal page (and any navigation): network-first so an online
    // load always refreshes the embedded catalogue, but keep a copy for offline.
    if (req.mode === 'navigate') {
        event.respondWith(
            fetch(req)
                .then((res) => {
                    const copy = res.clone();
                    caches.open(CACHE).then((c) => c.put(req, copy));
                    return res;
                })
                .catch(() => caches.match(req).then((hit) => hit || caches.match('/pos')))
        );
        return;
    }

    // Same-origin static assets: cache-first, revalidate in the background.
    if (url.origin === self.location.origin) {
        event.respondWith(
            caches.match(req).then((hit) => {
                const network = fetch(req)
                    .then((res) => {
                        if (res && res.status === 200) {
                            const copy = res.clone();
                            caches.open(CACHE).then((c) => c.put(req, copy));
                        }
                        return res;
                    })
                    .catch(() => hit);
                return hit || network;
            })
        );
        return;
    }

    // Cross-origin (e.g. Google Fonts): try cache, fall back to network.
    event.respondWith(
        caches.match(req).then((hit) => hit || fetch(req).then((res) => {
            const copy = res.clone();
            caches.open(CACHE).then((c) => c.put(req, copy));
            return res;
        }).catch(() => hit))
    );
});
