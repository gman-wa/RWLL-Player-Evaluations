/*
 * Keeps the pages and assets on the phone so a station still opens with bad or no signal.
 * Pages and assets: answer from the cache right away and refresh the cache in the background.
 * Nothing else is touched (photos use the normal http cache).
 * Bump VERSION only to throw the whole cache away - content updates on its own.
 */
const VERSION = 'rwll-1';
const PAGES = ['/', '/register', '/station/fielding', '/station/hitting', '/station/pitching', '/station/baserunning'];

self.addEventListener('install', (event) => {
    event.waitUntil(caches.open(VERSION).then((cache) => cache.addAll(PAGES)).then(() => self.skipWaiting()));
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys()
            .then((keys) => Promise.all(keys.filter((k) => k !== VERSION).map((k) => caches.delete(k))))
            .then(() => self.clients.claim())
    );
});

self.addEventListener('fetch', (event) => {
    const url = new URL(event.request.url);
    if (event.request.method !== 'GET' || url.origin !== location.origin) return;
    // only the station pages and /assets - everything else (api, admin, photos, old-url redirects) goes straight through
    if (!PAGES.includes(url.pathname) && !url.pathname.startsWith('/assets/')) return;

    event.respondWith(caches.open(VERSION).then(async (cache) => {
        const cached = await cache.match(event.request);
        const fresh = fetch(event.request).then((res) => {
            if (res.ok) cache.put(event.request, res.clone());
            return res;
        });
        if (cached) {
            event.waitUntil(fresh.catch(() => {}));
            return cached;
        }
        return fresh;
    }));
});
