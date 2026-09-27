/*
 * DukaPOS service worker: keeps the offline till usable without a connection.
 *
 * - /pos/offline (and /pos when the network is down) is served from cache.
 * - Built assets (/build/*, Livewire's script) are cache-first.
 * - Everything else goes straight to the network; API calls are never cached.
 */
const CACHE = 'dukapos-offline-v1';
const OFFLINE_PAGE = '/pos/offline';

async function precache() {
    const cache = await caches.open(CACHE);
    const res = await fetch(OFFLINE_PAGE, { credentials: 'same-origin' });
    if (!res.ok || res.redirected) return;
    await cache.put(OFFLINE_PAGE, res.clone());
    const html = await res.text();
    const assets = [...html.matchAll(/(?:src|href)="([^"]+)"/g)]
        .map((m) => new URL(m[1], self.location.origin))
        .filter((u) => u.origin === self.location.origin && (u.pathname.startsWith('/build/') || u.pathname.startsWith('/livewire') || u.pathname === '/favicon.svg'))
        .map((u) => u.href);
    await Promise.all([...new Set(assets)].map((url) => cache.add(url).catch(() => null)));
}

self.addEventListener('install', (event) => {
    event.waitUntil(precache().catch(() => null).then(() => self.skipWaiting()));
});

self.addEventListener('activate', (event) => {
    event.waitUntil(caches.keys()
        .then((keys) => Promise.all(keys.filter((k) => k.startsWith('dukapos-') && k !== CACHE).map((k) => caches.delete(k))))
        .then(() => self.clients.claim()));
});

self.addEventListener('message', (event) => {
    if (event.data === 'clear') event.waitUntil(caches.delete(CACHE));
    if (event.data === 'refresh') event.waitUntil(precache().catch(() => null));
});

self.addEventListener('fetch', (event) => {
    const req = event.request;
    if (req.method !== 'GET') return;
    const url = new URL(req.url);
    if (url.origin !== self.location.origin) return;

    // Offline till page: network first (keeps it fresh), cache when offline.
    if (req.mode === 'navigate' && (url.pathname === OFFLINE_PAGE || url.pathname === '/pos')) {
        event.respondWith((async () => {
            try {
                const res = await fetch(req);
                if (url.pathname === OFFLINE_PAGE && res.ok && !res.redirected) {
                    const cache = await caches.open(CACHE);
                    cache.put(OFFLINE_PAGE, res.clone());
                }
                return res;
            } catch (e) {
                const cached = await caches.match(OFFLINE_PAGE);
                return cached || Response.error();
            }
        })());
        return;
    }

    // Static assets: cache first.
    if (url.pathname.startsWith('/build/') || (url.pathname.startsWith('/livewire') && url.pathname.endsWith('.js'))) {
        event.respondWith(caches.match(req).then((hit) => hit || fetch(req).then((res) => {
            if (res.ok) caches.open(CACHE).then((c) => c.put(req, res.clone()));
            return res.clone();
        })));
    }
});
