{{--
    Big Cash PWA Install Prompt Component
    Include before </body> in layouts/borrower.blade.php and layouts/app.blade.php
    @include('layouts.pwa-install')
--}}

{{-- ─── Install Bottom Sheet ─────────────────────────────────────────────── --}}
<div id="pwa-install-sheet" style="display:none;position:fixed;inset:0;z-index:10000">
    {{-- Backdrop --}}
    <div id="pwa-backdrop" onclick="PWAInstall.dismiss()"
         style="position:absolute;inset:0;background:rgba(15,23,42,0.7);backdrop-filter:blur(4px)"></div>

    {{-- Sheet --}}
    <div id="pwa-sheet" style="
        position:absolute;bottom:0;left:0;right:0;
        background:#fff;border-radius:24px 24px 0 0;
        padding:20px;
        transform:translateY(100%);
        transition:transform .4s cubic-bezier(.34,1.2,.64,1);
        max-height:90vh;overflow-y:auto;
    ">
        {{-- Handle --}}
        <div style="width:40px;height:4px;border-radius:2px;background:#e2e8f0;margin:0 auto 20px"></div>

        {{-- App Icon + Title --}}
        <div style="text-align:center;margin-bottom:20px">
            <div style="width:72px;height:72px;border-radius:18px;background:linear-gradient(135deg,#1d4ed8,#7c3aed);margin:0 auto 14px;display:flex;align-items:center;justify-content:center;box-shadow:0 12px 32px rgba(37,99,235,0.35)">
                <svg width="36" height="36" viewBox="0 0 24 24" fill="#fff">
                    <path d="M20 6h-2.18c.07-.44.18-.86.18-1.3C18 2.12 15.88 0 13.3 0c-1.3 0-2.49.52-3.3 1.43L9 2.6 7.99 1.43C7.19.52 6.01 0 4.7 0 2.12 0 0 2.12 0 4.7c0 .44.1.86.18 1.3H0v2h2v11h20V8h-2V6z"/>
                </svg>
            </div>
            <div style="font-size:20px;font-weight:800;color:#0f172a;letter-spacing:-.03em">
                Add Big Cash to your home screen
            </div>
            <div style="font-size:13px;color:#64748b;margin-top:6px;line-height:1.5">
                Apply for loans, view repayments, and get real-time alerts — directly from your phone.
            </div>
        </div>

        {{-- Features --}}
        <div style="display:flex;flex-direction:column;gap:8px;margin-bottom:20px">
            <div style="display:flex;align-items:center;gap:12px;padding:12px;background:#f8fafc;border-radius:12px;border:1px solid #f1f5f9">
                <div style="width:38px;height:38px;border-radius:10px;background:#dbeafe;display:flex;align-items:center;justify-content:center;flex-shrink:0">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="#1d4ed8"><path d="M13 2.05V2c0-1.1-.9-2-2-2s-2 .9-2 2v.05C4.84 2.55 2 5.79 2 9.5 2 14.02 5.41 17.83 10 18.93V22h4v-3.07c4.59-1.1 8-4.91 8-9.43 0-3.71-2.84-6.95-9-7.45z"/></svg>
                </div>
                <div>
                    <div style="font-size:13px;font-weight:600;color:#0f172a">One-tap loan applications</div>
                    <div style="font-size:11px;color:#64748b;margin-top:1px">Apply in under 5 minutes, anytime</div>
                </div>
            </div>
            <div style="display:flex;align-items:center;gap:12px;padding:12px;background:#f8fafc;border-radius:12px;border:1px solid #f1f5f9">
                <div style="width:38px;height:38px;border-radius:10px;background:#dcfce7;display:flex;align-items:center;justify-content:center;flex-shrink:0">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="#15803d"><path d="M12 1L3 5v6c0 5.55 3.84 10.74 9 12 5.16-1.26 9-6.45 9-12V5l-9-4z"/></svg>
                </div>
                <div>
                    <div style="font-size:13px;font-weight:600;color:#0f172a">Secure offline access</div>
                    <div style="font-size:11px;color:#64748b;margin-top:1px">View your loans without internet</div>
                </div>
            </div>
            <div style="display:flex;align-items:center;gap:12px;padding:12px;background:#f8fafc;border-radius:12px;border:1px solid #f1f5f9">
                <div style="width:38px;height:38px;border-radius:10px;background:#fef9c3;display:flex;align-items:center;justify-content:center;flex-shrink:0">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="#854f0b"><path d="M12 22c1.1 0 2-.9 2-2h-4c0 1.1.9 2 2 2zm6-6v-5c0-3.07-1.64-5.64-4.5-6.32V4c0-.83-.67-1.5-1.5-1.5s-1.5.67-1.5 1.5v.68C7.63 5.36 6 7.92 6 11v5l-2 2v1h16v-1l-2-2z"/></svg>
                </div>
                <div>
                    <div style="font-size:13px;font-weight:600;color:#0f172a">Push payment reminders</div>
                    <div style="font-size:11px;color:#64748b;margin-top:1px">Never miss a repayment due date</div>
                </div>
            </div>
            <div style="display:flex;align-items:center;gap:12px;padding:12px;background:#f8fafc;border-radius:12px;border:1px solid #f1f5f9">
                <div style="width:38px;height:38px;border-radius:10px;background:#ede9fe;display:flex;align-items:center;justify-content:center;flex-shrink:0">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="#5b21b6"><path d="M17 12h-5v5h5v-5zM16 1v2H8V1H6v2H5c-1.11 0-1.99.9-1.99 2L3 19c0 1.1.89 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2h-1V1h-2zm3 18H5V8h14v11z"/></svg>
                </div>
                <div>
                    <div style="font-size:13px;font-weight:600;color:#0f172a">Instant disbursement alerts</div>
                    <div style="font-size:11px;color:#64748b;margin-top:1px">Know the moment funds are sent</div>
                </div>
            </div>
        </div>

        {{-- How to install steps --}}
        <div style="display:flex;background:#f1f5f9;border-radius:10px;overflow:hidden;margin-bottom:20px">
            <div style="flex:1;padding:10px 8px;text-align:center;border-right:1px solid #e2e8f0">
                <div style="font-size:16px;font-weight:800;color:#1d4ed8">1</div>
                <div style="font-size:10px;color:#64748b;font-weight:500;margin-top:2px">Tap Install</div>
            </div>
            <div style="flex:1;padding:10px 8px;text-align:center;border-right:1px solid #e2e8f0">
                <div style="font-size:16px;font-weight:800;color:#1d4ed8">2</div>
                <div style="font-size:10px;color:#64748b;font-weight:500;margin-top:2px">Confirm</div>
            </div>
            <div style="flex:1;padding:10px 8px;text-align:center">
                <div style="font-size:16px;font-weight:800;color:#16a34a">3</div>
                <div style="font-size:10px;color:#64748b;font-weight:500;margin-top:2px">Launch app</div>
            </div>
        </div>

        {{-- Buttons --}}
        <button id="pwa-install-confirm-btn" onclick="PWAInstall.confirm()" style="
            width:100%;padding:14px;
            background:linear-gradient(135deg,#1d4ed8,#7c3aed);
            color:#fff;border:none;border-radius:12px;
            font-size:15px;font-weight:700;cursor:pointer;
            box-shadow:0 8px 24px rgba(37,99,235,0.35);
            margin-bottom:10px;
            display:flex;align-items:center;justify-content:center;gap:8px;
            transition:opacity .15s;
        ">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><path d="M19 9h-4V3H9v6H5l7 7 7-7zM5 18v2h14v-2H5z"/></svg>
            Add to Home Screen
        </button>
        <button onclick="PWAInstall.dismiss()" style="
            width:100%;padding:12px;
            background:#f8fafc;color:#64748b;
            border:1.5px solid #e2e8f0;border-radius:12px;
            font-size:14px;font-weight:600;cursor:pointer;
        ">Not right now</button>

        {{-- Safe area for iOS --}}
        <div style="height:env(safe-area-inset-bottom, 16px)"></div>
    </div>
</div>

{{-- ─── iOS Safari Manual Install Instructions (shown on iOS when no prompt) ─ --}}
<div id="pwa-ios-hint" style="display:none;position:fixed;bottom:0;left:0;right:0;z-index:10000;padding:16px">
    <div style="background:#0f172a;border-radius:16px;padding:16px;box-shadow:0 -4px 40px rgba(0,0,0,0.4);border:1px solid rgba(255,255,255,0.1)">
        <div style="display:flex;align-items:center;gap:10px;margin-bottom:12px">
            <div style="width:36px;height:36px;border-radius:9px;background:linear-gradient(135deg,#1d4ed8,#7c3aed);display:flex;align-items:center;justify-content:center;flex-shrink:0">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="#fff"><path d="M20 6h-2.18c.07-.44.18-.86.18-1.3C18 2.12 15.88 0 13.3 0c-1.3 0-2.49.52-3.3 1.43L9 2.6 7.99 1.43C7.19.52 6.01 0 4.7 0 2.12 0 0 2.12 0 4.7c0 .44.1.86.18 1.3H0v2h2v11h20V8h-2V6z"/></svg>
            </div>
            <div>
                <div style="font-size:13px;font-weight:700;color:#fff">Install Big Cash on your iPhone</div>
                <div style="font-size:11px;color:rgba(255,255,255,0.5);margin-top:1px">Follow these 3 steps</div>
            </div>
            <button onclick="document.getElementById('pwa-ios-hint').style.display='none';localStorage.setItem('pwa_ios_hint_dismissed','1')" style="margin-left:auto;background:none;border:none;color:rgba(255,255,255,0.4);cursor:pointer;font-size:20px">×</button>
        </div>
        <div style="display:flex;flex-direction:column;gap:8px;font-size:12px;color:rgba(255,255,255,0.7)">
            <div style="display:flex;align-items:center;gap:8px">
                <span style="width:22px;height:22px;border-radius:50%;background:rgba(255,255,255,0.1);display:flex;align-items:center;justify-content:center;font-size:11px;font-weight:700;flex-shrink:0;color:#93c5fd">1</span>
                Tap the <strong style="color:#fff">Share</strong> button at the bottom of your browser
                <svg width="14" height="14" viewBox="0 0 24 24" fill="#93c5fd" style="flex-shrink:0"><path d="M20 6h-2.18c.07-.44.18-.86.18-1.3C18 2.12 15.88 0 13.3 0c-1.3 0-2.49.52-3.3 1.43L9 2.6 7.99 1.43C7.19.52 6.01 0 4.7 0 2.12 0 0 2.12 0 4.7c0 .44.1.86.18 1.3H0v2h2v11h20V8h-2V6z"/></svg>
            </div>
            <div style="display:flex;align-items:center;gap:8px">
                <span style="width:22px;height:22px;border-radius:50%;background:rgba(255,255,255,0.1);display:flex;align-items:center;justify-content:center;font-size:11px;font-weight:700;flex-shrink:0;color:#93c5fd">2</span>
                Scroll down and tap <strong style="color:#fff">Add to Home Screen</strong>
            </div>
            <div style="display:flex;align-items:center;gap:8px">
                <span style="width:22px;height:22px;border-radius:50%;background:rgba(255,255,255,0.1);display:flex;align-items:center;justify-content:center;font-size:11px;font-weight:700;flex-shrink:0;color:#86efac">3</span>
                Tap <strong style="color:#fff">Add</strong> in the top right corner
            </div>
        </div>
    </div>
</div>

{{-- ─── Network Status Bar ─────────────────────────────────────────────────── --}}
<div id="network-bar" style="display:none;position:fixed;top:0;left:0;right:0;z-index:9999;padding:10px 16px;font-size:12px;font-weight:600;text-align:center;transition:all .3s">
    Offline — Showing cached data
</div>

<script>
const PWAInstall = {
    show() {
        const sheet = document.getElementById('pwa-install-sheet');
        const inner = document.getElementById('pwa-sheet');
        sheet.style.display = 'block';
        requestAnimationFrame(() => {
            requestAnimationFrame(() => {
                inner.style.transform = 'translateY(0)';
            });
        });
    },
    dismiss() {
        const inner = document.getElementById('pwa-sheet');
        inner.style.transform = 'translateY(100%)';
        setTimeout(() => {
            document.getElementById('pwa-install-sheet').style.display = 'none';
        }, 400);
        localStorage.setItem('pwa_install_dismissed_at', Date.now());
        localStorage.setItem('pwa_install_dismiss_count',
            (parseInt(localStorage.getItem('pwa_install_dismiss_count') || '0') + 1).toString()
        );
    },
    async confirm() {
        const btn = document.getElementById('pwa-install-confirm-btn');
        btn.style.opacity = '0.7';
        btn.disabled = true;
        await window.Big CashPWA?.triggerInstall?.();
        this.dismiss();
    }
};

// Show the sheet when PWA manager fires the event
document.addEventListener('bigcash:show-install-prompt', () => {
    // Don't show if dismissed in last 24h
    const lastDismiss = localStorage.getItem('pwa_install_dismissed_at');
    if (lastDismiss && Date.now() - parseInt(lastDismiss) < 24 * 60 * 60 * 1000) return;
    PWAInstall.show();
});

// iOS Safari detection — show manual hint
(function() {
    const isIos   = /iPad|iPhone|iPod/.test(navigator.userAgent);
    const isSafari = /^((?!chrome|android).)*safari/i.test(navigator.userAgent);
    const isStandalone = window.navigator.standalone;
    const dismissed = localStorage.getItem('pwa_ios_hint_dismissed');

    if (isIos && isSafari && !isStandalone && !dismissed) {
        setTimeout(() => {
            document.getElementById('pwa-ios-hint').style.display = 'block';
        }, 4000);
    }
})();

// Network status bar
(function() {
    const bar = document.getElementById('network-bar');

    function updateBar(online) {
        if (online) {
            bar.style.display = 'none';
        } else {
            bar.style.display  = 'block';
            bar.style.background = '#7f1d1d';
            bar.style.color      = '#fca5a5';
        }
    }

    window.addEventListener('online',  () => updateBar(true));
    window.addEventListener('offline', () => updateBar(false));
    updateBar(navigator.onLine);
})();
</script>
