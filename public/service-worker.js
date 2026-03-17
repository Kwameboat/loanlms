/**
 * Big Cash Finance — Service Worker
 * Production PWA Service Worker with:
 *  - Precaching of app shell
 *  - Network-first strategy for API/dynamic routes
 *  - Cache-first strategy for static assets
 *  - Background sync for offline form submissions
 *  - Push notification handling
 *  - Periodic background sync for loan data refresh
 */

const APP_VERSION   = 'v1.0.0';
const CACHE_SHELL   = `bigcash-shell-${APP_VERSION}`;
const CACHE_DYNAMIC = `bigcash-dynamic-${APP_VERSION}`;
const CACHE_IMAGES  = `bigcash-images-${APP_VERSION}`;

// ── App Shell — precache on install ──────────────────────────────────────────
const SHELL_ASSETS = [
    '/',
    '/portal/dashboard',
    '/offline.html',
    '/manifest.json',
    '/icons/icon-192x192.png',
    '/icons/icon-512x512.png',
    // Bootstrap CSS CDN will be cached on first load via dynamic cache
];

// ── Routes that should NEVER be cached ───────────────────────────────────────
const NO_CACHE_PATTERNS = [
    /\/webhook\//,
    /\/paystack\//,
    /\/admin\//,        // Admin panel — staff only, not PWA
    /\/_debugbar\//,
    /\/sanctum\//,
    /logout/,
];

// ── API routes — network first with cache fallback ────────────────────────────
const NETWORK_FIRST_PATTERNS = [
    /\/portal\/api\//,
    /\/portal\/loans/,
    /\/portal\/payments/,
    /\/portal\/dashboard/,
];

// ── Static assets — cache first ───────────────────────────────────────────────
const CACHE_FIRST_PATTERNS = [
    /\.css$/,
    /\.js$/,
    /\.(png|jpg|jpeg|svg|gif|webp|ico)$/,
    /\.(woff|woff2|ttf|otf)$/,
    /cdn\.jsdelivr\.net/,
    /cdnjs\.cloudflare\.com/,
];

// ─────────────────────────────────────────────────────────────────────────────
// INSTALL — Precache app shell
// ─────────────────────────────────────────────────────────────────────────────
self.addEventListener('install', (event) => {
    console.log('[SW] Installing Big Cash Service Worker', APP_VERSION);

    event.waitUntil(
        caches.open(CACHE_SHELL)
            .then(cache => cache.addAll(SHELL_ASSETS))
            .then(() => self.skipWaiting()) // Activate immediately
    );
});

// ─────────────────────────────────────────────────────────────────────────────
// ACTIVATE — Clean old caches
// ─────────────────────────────────────────────────────────────────────────────
self.addEventListener('activate', (event) => {
    console.log('[SW] Activating Big Cash Service Worker', APP_VERSION);

    const CURRENT_CACHES = [CACHE_SHELL, CACHE_DYNAMIC, CACHE_IMAGES];

    event.waitUntil(
        caches.keys()
            .then(keys => Promise.all(
                keys
                    .filter(key => !CURRENT_CACHES.includes(key))
                    .map(key => {
                        console.log('[SW] Deleting old cache:', key);
                        return caches.delete(key);
                    })
            ))
            .then(() => self.clients.claim()) // Take control of all pages immediately
    );
});

// ─────────────────────────────────────────────────────────────────────────────
// FETCH — Smart caching strategies
// ─────────────────────────────────────────────────────────────────────────────
self.addEventListener('fetch', (event) => {
    const { request } = event;
    const url = new URL(request.url);

    // Skip non-GET requests (POST, PUT, DELETE — these go to network directly)
    if (request.method !== 'GET') {
        return;
    }

    // Skip chrome-extension and non-http(s) requests
    if (!request.url.startsWith('http')) {
        return;
    }

    // Never cache certain routes
    if (NO_CACHE_PATTERNS.some(pattern => pattern.test(request.url))) {
        return;
    }

    // Cache-first for static assets
    if (CACHE_FIRST_PATTERNS.some(pattern => pattern.test(request.url))) {
        event.respondWith(cacheFirst(request, CACHE_IMAGES));
        return;
    }

    // Network-first for API/dynamic portal routes
    if (NETWORK_FIRST_PATTERNS.some(pattern => pattern.test(request.url))) {
        event.respondWith(networkFirst(request, CACHE_DYNAMIC));
        return;
    }

    // Default: stale-while-revalidate for everything else
    event.respondWith(staleWhileRevalidate(request, CACHE_DYNAMIC));
});

// ─────────────────────────────────────────────────────────────────────────────
// CACHING STRATEGIES
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Cache First — serve from cache, fall back to network, update cache.
 * Best for: fonts, images, CSS, JS bundles that don't change often.
 */
async function cacheFirst(request, cacheName) {
    const cached = await caches.match(request);
    if (cached) {
        return cached;
    }
    try {
        const response = await fetch(request);
        if (response.ok) {
            const cache = await caches.open(cacheName);
            cache.put(request, response.clone());
        }
        return response;
    } catch {
        return caches.match('/offline.html');
    }
}

/**
 * Network First — try network, fall back to cache, update cache on success.
 * Best for: dynamic pages, user-specific data.
 */
async function networkFirst(request, cacheName) {
    try {
        const response = await fetch(request);
        if (response.ok) {
            const cache = await caches.open(cacheName);
            cache.put(request, response.clone());
        }
        return response;
    } catch {
        const cached = await caches.match(request);
        if (cached) {
            return cached;
        }
        // Return offline page for navigation requests
        if (request.mode === 'navigate') {
            return caches.match('/offline.html');
        }
        return new Response(JSON.stringify({ error: 'offline', message: 'No internet connection' }), {
            headers: { 'Content-Type': 'application/json' },
            status: 503,
        });
    }
}

/**
 * Stale While Revalidate — serve from cache immediately, update cache in background.
 * Best for: pages that change occasionally but don't need to be always fresh.
 */
async function staleWhileRevalidate(request, cacheName) {
    const cache  = await caches.open(cacheName);
    const cached = await cache.match(request);

    const fetchPromise = fetch(request).then(response => {
        if (response.ok) {
            cache.put(request, response.clone());
        }
        return response;
    }).catch(() => null);

    if (cached) {
        return cached;
    }

    try {
        const response = await fetchPromise;
        if (response) return response;
    } catch {}

    if (request.mode === 'navigate') {
        return caches.match('/offline.html');
    }
    return new Response('Offline', { status: 503 });
}

// ─────────────────────────────────────────────────────────────────────────────
// BACKGROUND SYNC — Offline form submissions
// ─────────────────────────────────────────────────────────────────────────────
const SYNC_QUEUE_LOAN    = 'bigcash-loan-applications';
const SYNC_QUEUE_PAYMENT = 'bigcash-payment-reports';

self.addEventListener('sync', (event) => {
    console.log('[SW] Background sync triggered:', event.tag);

    if (event.tag === SYNC_QUEUE_LOAN) {
        event.waitUntil(syncLoanApplications());
    }

    if (event.tag === SYNC_QUEUE_PAYMENT) {
        event.waitUntil(syncPaymentReports());
    }
});

async function syncLoanApplications() {
    const db = await openDB();
    const pending = await getAllFromStore(db, 'pending_applications');

    for (const item of pending) {
        try {
            const response = await fetch('/portal/loans/apply', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': item.csrf,
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify(item.data),
            });

            if (response.ok) {
                await deleteFromStore(db, 'pending_applications', item.id);
                await notifyClients({
                    type: 'SYNC_SUCCESS',
                    message: 'Your loan application was submitted successfully.',
                    data: await response.json(),
                });
            }
        } catch (err) {
            console.error('[SW] Sync failed for application:', item.id, err);
        }
    }
}

async function syncPaymentReports() {
    const db = await openDB();
    const pending = await getAllFromStore(db, 'pending_payments');

    for (const item of pending) {
        try {
            const response = await fetch('/portal/payments/report', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': item.csrf },
                body: JSON.stringify(item.data),
            });

            if (response.ok) {
                await deleteFromStore(db, 'pending_payments', item.id);
            }
        } catch (err) {
            console.error('[SW] Payment sync failed:', err);
        }
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// PERIODIC BACKGROUND SYNC — Refresh loan data silently
// ─────────────────────────────────────────────────────────────────────────────
self.addEventListener('periodicsync', (event) => {
    if (event.tag === 'bigcash-refresh-loans') {
        event.waitUntil(refreshLoanDataInBackground());
    }
});

async function refreshLoanDataInBackground() {
    try {
        const response = await fetch('/portal/api/summary');
        if (response.ok) {
            const cache = await caches.open(CACHE_DYNAMIC);
            cache.put('/portal/api/summary', response.clone());
            console.log('[SW] Loan data refreshed in background');
        }
    } catch (err) {
        console.log('[SW] Background refresh failed (offline)');
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// PUSH NOTIFICATIONS
// ─────────────────────────────────────────────────────────────────────────────
self.addEventListener('push', (event) => {
    let data = {};

    try {
        data = event.data ? event.data.json() : {};
    } catch {
        data = { title: 'Big Cash', body: event.data ? event.data.text() : 'New notification' };
    }

    const title   = data.title   || 'Big Cash Finance';
    const options = {
        body:    data.body    || 'You have a new notification.',
        icon:    data.icon    || '/icons/icon-192x192.png',
        badge:   data.badge   || '/icons/icon-72x72.png',
        image:   data.image   || null,
        tag:     data.tag     || 'bigcash-notification',
        data:    data.action_url ? { url: data.action_url } : { url: '/portal/dashboard' },
        renotify: false,
        requireInteraction: data.requires_interaction || false,
        silent: false,
        vibrate: [200, 100, 200],
        actions: buildNotificationActions(data.type),
    };

    event.waitUntil(
        self.registration.showNotification(title, options)
    );
});

function buildNotificationActions(type) {
    switch (type) {
        case 'payment_due':
            return [
                { action: 'pay',    title: 'Pay Now',        icon: '/icons/icon-72x72.png' },
                { action: 'dismiss', title: 'Remind me later' },
            ];
        case 'loan_approved':
            return [
                { action: 'view', title: 'View Loan', icon: '/icons/icon-72x72.png' },
            ];
        case 'repayment_received':
            return [
                { action: 'receipt', title: 'View Receipt', icon: '/icons/icon-72x72.png' },
            ];
        default:
            return [
                { action: 'open', title: 'Open App' },
            ];
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// NOTIFICATION CLICK
// ─────────────────────────────────────────────────────────────────────────────
self.addEventListener('notificationclick', (event) => {
    event.notification.close();

    const actionUrl = event.notification.data?.url || '/portal/dashboard';
    const targetUrl = event.action === 'pay'
        ? '/portal/loans'
        : event.action === 'receipt'
        ? '/portal/payments'
        : actionUrl;

    event.waitUntil(
        clients.matchAll({ type: 'window', includeUncontrolled: true })
            .then(clientList => {
                // Focus existing window if open
                for (const client of clientList) {
                    if (client.url.includes('/portal') && 'focus' in client) {
                        client.navigate(targetUrl);
                        return client.focus();
                    }
                }
                // Otherwise open new window
                if (clients.openWindow) {
                    return clients.openWindow(targetUrl);
                }
            })
    );
});

self.addEventListener('notificationclose', (event) => {
    console.log('[SW] Notification dismissed:', event.notification.tag);
});

// ─────────────────────────────────────────────────────────────────────────────
// MESSAGE HANDLING — Communication with main thread
// ─────────────────────────────────────────────────────────────────────────────
self.addEventListener('message', (event) => {
    const { type, payload } = event.data || {};

    switch (type) {
        case 'SKIP_WAITING':
            self.skipWaiting();
            break;

        case 'GET_VERSION':
            event.source.postMessage({ type: 'VERSION', version: APP_VERSION });
            break;

        case 'CACHE_URLS':
            if (Array.isArray(payload)) {
                caches.open(CACHE_DYNAMIC).then(cache => cache.addAll(payload));
            }
            break;

        case 'CLEAR_CACHE':
            caches.keys().then(keys => Promise.all(keys.map(k => caches.delete(k))));
            break;

        case 'QUEUE_LOAN_APPLICATION':
            queueForSync('pending_applications', payload);
            break;

        case 'QUEUE_PAYMENT':
            queueForSync('pending_payments', payload);
            break;
    }
});

// ─────────────────────────────────────────────────────────────────────────────
// IndexedDB HELPERS — Offline queue storage
// ─────────────────────────────────────────────────────────────────────────────
function openDB() {
    return new Promise((resolve, reject) => {
        const request = indexedDB.open('bigcash-offline', 1);
        request.onupgradeneeded = (event) => {
            const db = event.target.result;
            if (!db.objectStoreNames.contains('pending_applications')) {
                db.createObjectStore('pending_applications', { keyPath: 'id', autoIncrement: true });
            }
            if (!db.objectStoreNames.contains('pending_payments')) {
                db.createObjectStore('pending_payments', { keyPath: 'id', autoIncrement: true });
            }
        };
        request.onsuccess  = (e) => resolve(e.target.result);
        request.onerror    = (e) => reject(e.target.error);
    });
}

function getAllFromStore(db, storeName) {
    return new Promise((resolve, reject) => {
        const tx      = db.transaction(storeName, 'readonly');
        const store   = tx.objectStore(storeName);
        const request = store.getAll();
        request.onsuccess = (e) => resolve(e.target.result);
        request.onerror   = (e) => reject(e.target.error);
    });
}

function deleteFromStore(db, storeName, id) {
    return new Promise((resolve, reject) => {
        const tx      = db.transaction(storeName, 'readwrite');
        const store   = tx.objectStore(storeName);
        const request = store.delete(id);
        request.onsuccess = resolve;
        request.onerror   = (e) => reject(e.target.error);
    });
}

async function queueForSync(storeName, data) {
    const db = await openDB();
    const tx    = db.transaction(storeName, 'readwrite');
    const store = tx.objectStore(storeName);
    store.add({ data, timestamp: Date.now() });

    // Register for background sync
    try {
        await self.registration.sync.register(
            storeName === 'pending_applications' ? SYNC_QUEUE_LOAN : SYNC_QUEUE_PAYMENT
        );
    } catch (err) {
        console.log('[SW] Background sync not supported, will retry on next fetch');
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// HELPER — Notify all clients
// ─────────────────────────────────────────────────────────────────────────────
async function notifyClients(message) {
    const clientList = await clients.matchAll({ type: 'window' });
    clientList.forEach(client => client.postMessage(message));
}

console.log('[SW] Big Cash Service Worker loaded —', APP_VERSION);
