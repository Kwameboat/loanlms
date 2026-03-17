/**
 * Big Cash PWA Manager
 * Handles: service worker registration, install prompt, push notifications,
 * update detection, offline queue, and background sync.
 */

(function Big CashPWA() {
    'use strict';

    const CONFIG = {
        SW_PATH:          '/service-worker.js',
        SW_SCOPE:         '/',
        VAPID_PUBLIC_KEY: window.KOBOFLOW_VAPID_KEY || '',
        DEBUG:            window.KOBOFLOW_DEBUG || false,
    };

    let swRegistration  = null;
    let deferredPrompt  = null;   // BeforeInstallPromptEvent
    let isInstalled     = false;
    let isOnline        = navigator.onLine;

    // ─── Logging ─────────────────────────────────────────────────────────────
    function log(...args) {
        if (CONFIG.DEBUG) console.log('[Big Cash PWA]', ...args);
    }

    // ─── Init ─────────────────────────────────────────────────────────────────
    function init() {
        log('Initialising PWA manager');

        registerServiceWorker();
        setupInstallPrompt();
        setupOnlineOfflineHandlers();
        setupUpdateDetection();
        checkIfInstalled();
        setupPushNotificationHandler();
        registerPeriodicSync();
    }

    // ─── Service Worker Registration ──────────────────────────────────────────
    async function registerServiceWorker() {
        if (!('serviceWorker' in navigator)) {
            log('Service workers not supported');
            return;
        }

        try {
            swRegistration = await navigator.serviceWorker.register(CONFIG.SW_PATH, {
                scope: CONFIG.SW_SCOPE,
                updateViaCache: 'none',
            });

            log('Service worker registered:', swRegistration.scope);

            swRegistration.addEventListener('updatefound', onUpdateFound);
            navigator.serviceWorker.addEventListener('message', onSwMessage);
            navigator.serviceWorker.addEventListener('controllerchange', () => {
                log('New service worker activated');
            });

        } catch (err) {
            console.error('[Big Cash PWA] Service worker registration failed:', err);
        }
    }

    // ─── Update Detection ─────────────────────────────────────────────────────
    function onUpdateFound() {
        const newWorker = swRegistration.installing;
        log('New service worker found');

        newWorker.addEventListener('statechange', () => {
            if (newWorker.state === 'installed' && navigator.serviceWorker.controller) {
                log('New version available');
                showUpdateBanner();
            }
        });
    }

    function showUpdateBanner() {
        const existing = document.getElementById('pwa-update-banner');
        if (existing) return;

        const banner = document.createElement('div');
        banner.id = 'pwa-update-banner';
        banner.style.cssText = `
            position: fixed; bottom: 0; left: 0; right: 0; z-index: 9999;
            background: #1d4ed8; color: #fff; padding: 14px 20px;
            display: flex; align-items: center; gap: 12px;
            font-family: -apple-system, system-ui, sans-serif; font-size: 13px;
            box-shadow: 0 -4px 20px rgba(0,0,0,0.2);
        `;
        banner.innerHTML = `
            <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor" style="flex-shrink:0"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/></svg>
            <span style="flex:1">A new version of Big Cash is available.</span>
            <button onclick="Big CashPWA.applyUpdate()" style="background:rgba(255,255,255,.2);border:1px solid rgba(255,255,255,.3);color:#fff;padding:6px 14px;border-radius:20px;font-size:12px;font-weight:600;cursor:pointer">Update now</button>
            <button onclick="this.closest('#pwa-update-banner').remove()" style="background:none;border:none;color:rgba(255,255,255,.6);cursor:pointer;font-size:18px;line-height:1">×</button>
        `;
        document.body.appendChild(banner);
    }

    function setupUpdateDetection() {
        // Check for updates every 30 minutes
        setInterval(() => {
            if (swRegistration) {
                swRegistration.update();
                log('Checking for service worker updates');
            }
        }, 30 * 60 * 1000);
    }

    // ─── Install Prompt ───────────────────────────────────────────────────────
    function setupInstallPrompt() {
        window.addEventListener('beforeinstallprompt', (e) => {
            e.preventDefault();
            deferredPrompt = e;
            log('Install prompt captured');

            // Show install UI after a short delay (let page load first)
            const delay = parseInt(localStorage.getItem('pwa_install_dismiss_count') || '0') * 2000;
            setTimeout(() => {
                if (!isInstalled && deferredPrompt) {
                    showInstallPrompt();
                }
            }, 3000 + delay);
        });

        window.addEventListener('appinstalled', () => {
            isInstalled = true;
            deferredPrompt = null;
            log('App installed successfully');
            hideInstallPrompt();
            localStorage.setItem('pwa_installed', '1');
            showToast('Big Cash has been added to your home screen!', 'success');

            // Track install event
            trackEvent('pwa_installed');
        });
    }

    function showInstallPrompt() {
        // Don't show if user dismissed recently (within 24h)
        const lastDismiss = localStorage.getItem('pwa_install_dismissed_at');
        if (lastDismiss && Date.now() - parseInt(lastDismiss) < 24 * 60 * 60 * 1000) {
            return;
        }

        // Emit event so Blade template can react
        document.dispatchEvent(new CustomEvent('bigcash:show-install-prompt', {
            detail: { deferredPrompt }
        }));

        // Also show built-in banner if no custom UI listens
        setTimeout(() => {
            if (document.getElementById('pwa-install-banner')) return;
            renderInstallBanner();
        }, 500);
    }

    function renderInstallBanner() {
        const banner = document.createElement('div');
        banner.id = 'pwa-install-banner';
        banner.style.cssText = `
            position: fixed; bottom: 16px; left: 16px; right: 16px; z-index: 9998;
            background: #0f172a; border: 1px solid rgba(255,255,255,.1);
            border-radius: 16px; padding: 16px;
            display: flex; align-items: center; gap: 12px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.4);
            font-family: -apple-system, system-ui, sans-serif;
            animation: slideUp .3s ease;
        `;

        const style = document.createElement('style');
        style.textContent = '@keyframes slideUp{from{opacity:0;transform:translateY(20px)}to{opacity:1;transform:translateY(0)}}';
        document.head.appendChild(style);

        banner.innerHTML = `
            <div style="width:44px;height:44px;border-radius:12px;background:linear-gradient(135deg,#1d4ed8,#7c3aed);display:flex;align-items:center;justify-content:center;flex-shrink:0;box-shadow:0 4px 14px rgba(37,99,235,.4)">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="#fff"><path d="M20 6h-2.18c.07-.44.18-.86.18-1.3C18 2.12 15.88 0 13.3 0c-1.3 0-2.49.52-3.3 1.43L9 2.6 7.99 1.43C7.19.52 6.01 0 4.7 0 2.12 0 0 2.12 0 4.7c0 .44.1.86.18 1.3H0v2h2v11h20V8h-2V6z"/></svg>
            </div>
            <div style="flex:1">
                <div style="font-size:13px;font-weight:700;color:#fff;letter-spacing:-.01em">Install Big Cash</div>
                <div style="font-size:11px;color:rgba(255,255,255,.55);margin-top:2px">Faster access, offline support, push alerts</div>
            </div>
            <button id="pwa-install-btn" style="background:linear-gradient(135deg,#1d4ed8,#7c3aed);color:#fff;border:none;padding:8px 16px;border-radius:20px;font-size:12px;font-weight:700;cursor:pointer;box-shadow:0 4px 12px rgba(37,99,235,.3);white-space:nowrap">Install</button>
            <button id="pwa-dismiss-btn" style="background:none;border:none;color:rgba(255,255,255,.4);cursor:pointer;font-size:20px;line-height:1;padding:0 2px">×</button>
        `;

        document.body.appendChild(banner);

        document.getElementById('pwa-install-btn').addEventListener('click', triggerInstall);
        document.getElementById('pwa-dismiss-btn').addEventListener('click', dismissInstallBanner);
    }

    function hideInstallPrompt() {
        const banner = document.getElementById('pwa-install-banner');
        if (banner) banner.remove();
    }

    function dismissInstallBanner() {
        hideInstallPrompt();
        const count = parseInt(localStorage.getItem('pwa_install_dismiss_count') || '0');
        localStorage.setItem('pwa_install_dismiss_count', count + 1);
        localStorage.setItem('pwa_install_dismissed_at', Date.now().toString());
        log('Install banner dismissed (count:', count + 1, ')');
    }

    async function triggerInstall() {
        if (!deferredPrompt) {
            log('No deferred prompt available');
            return;
        }

        deferredPrompt.prompt();
        const { outcome } = await deferredPrompt.userChoice;
        log('Install prompt outcome:', outcome);

        if (outcome === 'accepted') {
            deferredPrompt = null;
            hideInstallPrompt();
        } else {
            dismissInstallBanner();
        }
    }

    function checkIfInstalled() {
        isInstalled =
            window.matchMedia('(display-mode: standalone)').matches ||
            window.navigator.standalone === true ||
            localStorage.getItem('pwa_installed') === '1';

        if (isInstalled) {
            log('Running as installed PWA');
            document.documentElement.classList.add('pwa-installed');
        }
    }

    // ─── Online / Offline ─────────────────────────────────────────────────────
    function setupOnlineOfflineHandlers() {
        window.addEventListener('online',  onOnline);
        window.addEventListener('offline', onOffline);
        updateNetworkStatus(navigator.onLine);
    }

    function onOnline() {
        isOnline = true;
        log('Connection restored');
        updateNetworkStatus(true);
        showToast('Connection restored', 'success');
        document.dispatchEvent(new CustomEvent('bigcash:online'));
    }

    function onOffline() {
        isOnline = false;
        log('Connection lost');
        updateNetworkStatus(false);
        showToast('You are offline — cached data is available', 'warning');
        document.dispatchEvent(new CustomEvent('bigcash:offline'));
    }

    function updateNetworkStatus(online) {
        document.documentElement.setAttribute('data-network', online ? 'online' : 'offline');

        const indicators = document.querySelectorAll('.network-status-indicator');
        indicators.forEach(el => {
            el.textContent = online ? 'Online' : 'Offline';
            el.className = `network-status-indicator ${online ? 'online' : 'offline'}`;
        });
    }

    // ─── Push Notifications ───────────────────────────────────────────────────
    function setupPushNotificationHandler() {
        // Listen for messages from SW
        navigator.serviceWorker?.addEventListener('message', (event) => {
            const { type, message } = event.data || {};
            if (type === 'SYNC_SUCCESS') {
                showToast(message || 'Synced successfully', 'success');
            }
        });
    }

    async function requestPushPermission() {
        if (!('Notification' in window)) {
            log('Notifications not supported');
            return false;
        }

        if (Notification.permission === 'granted') {
            return await subscribeToPush();
        }

        if (Notification.permission === 'denied') {
            log('Push notifications denied by user');
            return false;
        }

        const permission = await Notification.requestPermission();
        log('Push permission result:', permission);

        if (permission === 'granted') {
            return await subscribeToPush();
        }

        return false;
    }

    async function subscribeToPush() {
        if (!swRegistration || !CONFIG.VAPID_PUBLIC_KEY) {
            log('Push subscription skipped — no VAPID key or SW');
            return false;
        }

        try {
            const subscription = await swRegistration.pushManager.subscribe({
                userVisibleOnly:      true,
                applicationServerKey: urlBase64ToUint8Array(CONFIG.VAPID_PUBLIC_KEY),
            });

            log('Push subscription created');

            // Send subscription to server
            await fetch('/portal/push/subscribe', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                },
                body: JSON.stringify({ subscription }),
            });

            return true;
        } catch (err) {
            console.error('[Big Cash PWA] Push subscription failed:', err);
            return false;
        }
    }

    // ─── Periodic Background Sync ─────────────────────────────────────────────
    async function registerPeriodicSync() {
        if (!swRegistration || !('periodicSync' in swRegistration)) {
            log('Periodic sync not supported');
            return;
        }

        try {
            const status = await navigator.permissions.query({ name: 'periodic-background-sync' });
            if (status.state === 'granted') {
                await swRegistration.periodicSync.register('bigcash-refresh-loans', {
                    minInterval: 60 * 60 * 1000, // Every hour
                });
                log('Periodic sync registered');
            }
        } catch (err) {
            log('Periodic sync registration failed:', err);
        }
    }

    // ─── Offline Queue ────────────────────────────────────────────────────────
    async function queueLoanApplication(data, csrfToken) {
        if (!swRegistration) return false;

        swRegistration.active?.postMessage({
            type: 'QUEUE_LOAN_APPLICATION',
            payload: { data, csrf: csrfToken, timestamp: Date.now() },
        });

        showToast('Application saved. Will submit when connection is restored.', 'info');
        return true;
    }

    // ─── SW Message Handler ───────────────────────────────────────────────────
    function onSwMessage(event) {
        const { type, version, message } = event.data || {};
        switch (type) {
            case 'VERSION':
                log('SW version:', version);
                break;
            case 'SYNC_SUCCESS':
                showToast(message, 'success');
                break;
        }
    }

    // ─── Toast Notifications ──────────────────────────────────────────────────
    const toastQueue = [];
    let toastTimer   = null;

    function showToast(message, type = 'info') {
        toastQueue.push({ message, type });
        if (!toastTimer) processToastQueue();
    }

    function processToastQueue() {
        if (toastQueue.length === 0) { toastTimer = null; return; }

        const { message, type } = toastQueue.shift();
        const colors = {
            success: { bg: '#166534', border: '#22c55e', icon: '✓' },
            warning: { bg: '#92400e', border: '#f59e0b', icon: '⚠' },
            error:   { bg: '#991b1b', border: '#ef4444', icon: '✕' },
            info:    { bg: '#1e3a5f', border: '#3b82f6', icon: 'ℹ' },
        };
        const c = colors[type] || colors.info;

        const existing = document.getElementById('pwa-toast');
        if (existing) existing.remove();

        const toast = document.createElement('div');
        toast.id = 'pwa-toast';
        toast.style.cssText = `
            position: fixed; top: 16px; left: 50%; transform: translateX(-50%) translateY(-20px);
            background: #0f172a; border: 1px solid ${c.border}40;
            border-left: 3px solid ${c.border};
            color: #fff; padding: 10px 16px; border-radius: 10px;
            font-family: -apple-system, system-ui, sans-serif; font-size: 13px; font-weight: 500;
            white-space: nowrap; z-index: 10000; max-width: calc(100vw - 32px);
            display: flex; align-items: center; gap: 8px;
            box-shadow: 0 8px 24px rgba(0,0,0,0.3);
            transition: transform .3s cubic-bezier(.34,1.56,.64,1), opacity .3s;
            opacity: 0;
        `;
        toast.innerHTML = `<span style="color:${c.border};font-size:14px">${c.icon}</span><span style="overflow:hidden;text-overflow:ellipsis">${message}</span>`;
        document.body.appendChild(toast);

        requestAnimationFrame(() => {
            toast.style.transform = 'translateX(-50%) translateY(0)';
            toast.style.opacity   = '1';
        });

        toastTimer = setTimeout(() => {
            toast.style.opacity   = '0';
            toast.style.transform = 'translateX(-50%) translateY(-10px)';
            setTimeout(() => { toast.remove(); processToastQueue(); }, 300);
        }, 3500);
    }

    // ─── Analytics ────────────────────────────────────────────────────────────
    function trackEvent(name, data = {}) {
        try {
            fetch('/portal/analytics/event', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '' },
                body: JSON.stringify({ event: name, data, timestamp: Date.now(), pwa: isInstalled }),
            }).catch(() => {}); // Non-critical
        } catch {}
    }

    // ─── Utilities ────────────────────────────────────────────────────────────
    function urlBase64ToUint8Array(base64String) {
        const padding = '='.repeat((4 - base64String.length % 4) % 4);
        const base64  = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
        const rawData = window.atob(base64);
        return Uint8Array.from([...rawData].map(c => c.charCodeAt(0)));
    }

    // ─── Public API ───────────────────────────────────────────────────────────
    window.Big CashPWA = {
        init,
        triggerInstall,
        requestPushPermission,
        queueLoanApplication,
        showToast,
        isInstalled:   () => isInstalled,
        isOnline:      () => isOnline,
        applyUpdate: () => {
            swRegistration?.waiting?.postMessage({ type: 'SKIP_WAITING' });
            window.location.reload();
        },
        getVersion: () => {
            swRegistration?.active?.postMessage({ type: 'GET_VERSION' });
        },
    };

    // Auto-init when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

})();
