# Big Cash PWA Setup Guide

## Overview

Big Cash is a full Progressive Web App (PWA). This guide covers every step to make it installable, offline-capable, and push-notification-ready.

---

## What's Included

| Feature | File | Status |
|---|---|---|
| Web App Manifest | `public/manifest.json` | Complete |
| Service Worker | `public/service-worker.js` | Complete |
| Offline Page | `public/offline.html` | Complete |
| PWA Client Manager | `public/js/pwa.js` | Complete |
| Install UI (bottom sheet) | `layouts/pwa-install.blade.php` | Complete |
| PWA Head Meta | `layouts/pwa-head.blade.php` | Complete |
| App Icons (8 sizes) | `public/icons/` | Complete |
| Push Subscription API | `PushSubscriptionController` | Complete |
| Push Cron Command | `SendPushNotifications` | Complete |
| VAPID Key Generator | `php artisan webpush:vapid` | Complete |
| iOS Safari hint | In `pwa-install.blade.php` | Complete |
| Offline queue (IndexedDB) | Service worker + pwa.js | Complete |
| Background sync | Service worker | Complete |
| Periodic sync | Service worker | Complete |
| Update banner | pwa.js | Complete |

---

## Step 1 — Generate App Icons

Icons have been pre-generated in `public/icons/`. To regenerate:

```bash
pip install Pillow
python3 generate_icons.py
```

This creates all 8 required sizes: 72, 96, 128, 144, 152, 192, 384, 512px.

For **maskable icons** (Android adaptive icons), ensure the main content is within the inner 80% of the image. The generator does this automatically.

---

## Step 2 — Generate VAPID Keys (for Push Notifications)

```bash
php artisan webpush:vapid
```

This will:
1. Generate an EC P-256 key pair using OpenSSL
2. Print the Base64URL-encoded public and private keys
3. Automatically write them to your `.env` file

Your `.env` will then contain:
```env
VAPID_PUBLIC_KEY=BNxxxxxxxxxxxxxxxxxxxxxxxxxxxxx
VAPID_PRIVATE_KEY=xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx
VAPID_SUBJECT=mailto:noreply@yourdomain.com
```

> The public key is also exposed to the browser via `window.KOBOFLOW_VAPID_KEY` in `pwa-head.blade.php`.

---

## Step 3 — Install Push Library (Optional but recommended)

For server-side push sending:

```bash
composer require minishlink/web-push
```

This enables the `SendPushNotifications` Artisan command and `PushSubscriptionController::sendTest()`.

---

## Step 4 — Run the Push Migration

```bash
php artisan migrate
```

This creates the `push_subscriptions` table with columns:
- `user_id`, `endpoint`, `p256dh_key`, `auth_token`
- `device_type`, `browser`, `is_active`, `last_used_at`

---

## Step 5 — Serve Over HTTPS

PWA features (service worker, push, install prompt) **require HTTPS**.

On cPanel:
1. Go to **SSL/TLS** → **Let's Encrypt** (or your SSL provider)
2. Issue a certificate for your domain
3. Enable **Force HTTPS** redirect

In Laravel `.env`:
```env
APP_URL=https://yourdomain.com
SESSION_SECURE_COOKIE=true
```

---

## Step 6 — Test the PWA

### Browser DevTools
1. Open Chrome → DevTools → **Application** tab
2. Check: **Manifest** (should show all icons and settings)
3. Check: **Service Workers** (should show registered and activated)
4. Check: **Cache Storage** (should show `bigcash-shell-v1.0.0`)

### Lighthouse Audit
Run a Lighthouse audit in DevTools → **Lighthouse** → check PWA category. Target: 100/100 installable score.

### Manual test checklist
- [ ] App installs on Android Chrome (shows install banner)
- [ ] App installs on iOS Safari (manual Add to Home Screen hint shown)
- [ ] Offline page loads when device is disconnected
- [ ] Cached pages load without internet
- [ ] Push notification received after subscribing

---

## How the Install Prompt Works

### Android / Chrome
1. User visits the site 3+ times (Chrome's engagement heuristic)
2. `beforeinstallprompt` event fires
3. `pwa.js` captures it and waits 3 seconds
4. The **teaser bar** appears at the top of the portal (gradient strip)
5. User taps "Install" → **bottom sheet slides up** with features list
6. User taps "Add to Home Screen" → native Chrome install dialog
7. App installs → welcome toast fires → icon on home screen

### iOS Safari
1. No `beforeinstallprompt` event (Apple limitation)
2. `pwa.js` detects iOS Safari + not standalone
3. After 4 seconds, **iOS hint card** slides up from bottom
4. Shows step-by-step: Share button → Add to Home Screen → Add
5. User dismisses → stored in localStorage for 24h

### Already installed
- `window.matchMedia('(display-mode: standalone)')` detects installed state
- `pwa-installed` class added to `<html>` for CSS targeting
- No install prompts shown

---

## Caching Strategy

| Content Type | Strategy | Cache Name |
|---|---|---|
| App shell (HTML, offline page) | Precache on install | `bigcash-shell-v1.0.0` |
| Static assets (CSS, JS, fonts, images) | Cache-first | `bigcash-images-v1.0.0` |
| Portal pages (dashboard, loans) | Network-first with cache fallback | `bigcash-dynamic-v1.0.0` |
| Admin panel | Never cached | — |
| Paystack webhooks | Never cached | — |
| Logout routes | Never cached | — |

### Updating the cache
Change `APP_VERSION = 'v1.0.0'` in `service-worker.js` to `'v1.0.1'` on your next deployment. Old caches are automatically deleted on activation.

---

## Push Notifications

### Notification types supported

| Type | Trigger | Actions |
|---|---|---|
| `payment_due` | 3 days before due date | Pay Now, Remind me later |
| `overdue_warning` | 1, 7, 14, 30, 60 DPD | Open loan |
| `loan_approved` | Loan approval | View loan |
| `repayment_received` | Payment confirmed | View receipt |

### Sending a push from PHP

```php
use App\Console\Commands\SendPushNotifications;

// Trigger via scheduler (automatic)
// Or manually:
Artisan::call('push:send-reminders', ['--type' => 'due_reminders']);
```

### Testing push in browser
1. Log in as a borrower
2. Subscribe: `Big CashPWA.requestPushPermission()`
3. Test: POST to `/portal/push/test`

---

## Offline Queue

When a user submits a loan application offline:

1. `pwa.js` calls `Big CashPWA.queueLoanApplication(data, csrf)`
2. Service worker stores in IndexedDB (`bigcash-offline` DB, `pending_applications` store)
3. Service worker registers `Background Sync` tag `bigcash-loan-applications`
4. When connection returns, sync fires → POST to `/portal/loans/apply`
5. On success, `pending_application` deleted from IndexedDB
6. All open app windows receive `SYNC_SUCCESS` message → toast shown

---

## Periodic Background Sync

Registered when `periodic-background-sync` permission is granted (Chrome on Android):

- Tag: `bigcash-refresh-loans`
- Interval: every 60 minutes
- Action: fetches `/portal/api/summary` and updates cache

This keeps the dashboard data fresh even when the app is closed.

---

## Update Flow

When a new version is deployed:

1. Browser detects new `service-worker.js` (different byte content)
2. New SW installs in background
3. `updatefound` event fires → **update banner** appears at bottom of screen
4. User sees: *"A new version of Big Cash is available. Update now."*
5. User clicks "Update now" → SW sends `SKIP_WAITING` message → page reloads
6. New service worker activates, old caches deleted

---

## File Structure

```
public/
├── manifest.json           # Web App Manifest (full spec)
├── service-worker.js       # Service Worker (caching + push + sync)
├── offline.html            # Beautiful offline fallback page
├── js/
│   └── pwa.js              # Client PWA manager
└── icons/
    ├── icon-72x72.png
    ├── icon-96x96.png
    ├── icon-128x128.png
    ├── icon-144x144.png
    ├── icon-152x152.png
    ├── icon-192x192.png    # maskable
    ├── icon-384x384.png
    └── icon-512x512.png    # maskable

resources/views/layouts/
├── pwa-head.blade.php      # All PWA <head> meta tags
└── pwa-install.blade.php   # Install bottom sheet + iOS hint + network bar
```

---

## Troubleshooting

### Service worker not registering
- Must be served over HTTPS (or localhost)
- File must be at root: `/service-worker.js` not `/js/service-worker.js`
- Check browser console for errors

### Install prompt not showing
- Chrome requires user engagement (multiple visits or scrolling)
- In DevTools → Application → Manifest, click "Add to homescreen" to test manually
- On iOS: `beforeinstallprompt` never fires — iOS hint appears instead

### Push notifications not working
- Check VAPID keys are set in `.env`
- Ensure `minishlink/web-push` is installed: `composer require minishlink/web-push`
- Check browser notification permission: `Notification.permission`
- Expired subscriptions are automatically disabled in `push_subscriptions` table

### Cache not updating
- Hard refresh: Ctrl+Shift+R (Chrome)
- Clear all: DevTools → Application → Storage → Clear site data
- Bump `APP_VERSION` in `service-worker.js`

---

## PWA Score Targets

After full setup, your Lighthouse PWA audit should show:

- Installable: ✅
- PWA Optimized: ✅
- HTTPS: ✅
- Valid manifest: ✅
- Service worker: ✅
- Offline page: ✅
- Maskable icon: ✅
- Apple touch icon: ✅
