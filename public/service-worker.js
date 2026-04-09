/*
 * TriLink service worker — Sprint B.7.
 *
 * Goals (deliberately conservative):
 *
 *   1. Offline fallback for navigations: when the user opens a dashboard
 *      page with no network, serve a cached shell instead of the
 *      browser's default "no internet" page.
 *   2. Cache-first for built static assets (Vite hashes them, so cache
 *      invalidation is automatic — a new build = a new filename).
 *   3. Network-first for HTML navigations: dashboards must show fresh
 *      data when online. The cache is only consulted on failure.
 *   4. Bypass everything that isn't GET — the worker MUST NOT cache
 *      POST/PUT/DELETE responses (CSRF, idempotency, financial state).
 *
 * Out of scope for this iteration: background sync, push, periodic
 * refresh. They are tracked separately under the H2 Reverb migration.
 */

const STATIC_CACHE   = 'trilink-static-v1';
const RUNTIME_CACHE  = 'trilink-runtime-v1';
const OFFLINE_URL    = '/offline.html';

// Files we want available the first time the worker installs. Anything
// not listed here is cached lazily on first request.
const PRECACHE_URLS = [
    OFFLINE_URL,
    '/manifest.webmanifest',
    '/logo/logo.png',
    '/favicon.ico',
];

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(STATIC_CACHE)
            .then((cache) => cache.addAll(PRECACHE_URLS))
            .then(() => self.skipWaiting())
    );
});

self.addEventListener('activate', (event) => {
    // Drop any cache from a previous version of this worker so an
    // update doesn't leave the user pinned to stale assets.
    event.waitUntil(
        caches.keys().then((keys) =>
            Promise.all(
                keys
                    .filter((k) => k !== STATIC_CACHE && k !== RUNTIME_CACHE)
                    .map((k) => caches.delete(k))
            )
        ).then(() => self.clients.claim())
    );
});

self.addEventListener('fetch', (event) => {
    const request = event.request;

    // Hard rule: never touch non-GET. Caching POST/PUT/DELETE would
    // corrupt CSRF flow, idempotency keys, and financial state.
    if (request.method !== 'GET') {
        return;
    }

    // Hard rule: never cache cross-origin requests. We don't control
    // their headers and don't want to leak credentials into our cache.
    const url = new URL(request.url);
    if (url.origin !== self.location.origin) {
        return;
    }

    // Navigation requests (HTML page loads): network-first with an
    // offline fallback. Fresh data when online; a graceful "you're
    // offline" page when not.
    if (request.mode === 'navigate') {
        event.respondWith(
            fetch(request)
                .catch(() => caches.match(OFFLINE_URL))
        );
        return;
    }

    // Static built assets (Vite output): cache-first. Vite hashes the
    // filenames so a deploy automatically invalidates the cache.
    if (url.pathname.startsWith('/build/') || url.pathname.startsWith('/logo/') || url.pathname.startsWith('/icons/')) {
        event.respondWith(
            caches.match(request).then((cached) => {
                if (cached) return cached;
                return fetch(request).then((response) => {
                    // Only stash successful, basic responses — opaque
                    // responses can't be inspected and may be errors.
                    if (response && response.status === 200 && response.type === 'basic') {
                        const clone = response.clone();
                        caches.open(RUNTIME_CACHE).then((cache) => cache.put(request, clone));
                    }
                    return response;
                });
            })
        );
        return;
    }

    // Everything else (JSON endpoints, partial HTML fragments): pass
    // through. We deliberately do NOT cache these — most carry
    // tenant-scoped data that must never leak between users on a
    // shared device.
});
