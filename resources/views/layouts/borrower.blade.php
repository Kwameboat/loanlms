<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'My Account') — {{ \App\Models\Setting::get('company_name', 'Big Cash') }}</title>
    @include('layouts.pwa-head')
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        :root{--brand-dark:#0f172a;--brand:#1d4ed8}
        *{box-sizing:border-box}
        body{background:#f1f5f9;font-family:-apple-system,BlinkMacSystemFont,'Inter',system-ui,sans-serif;-webkit-font-smoothing:antialiased;padding-bottom:calc(72px + env(safe-area-inset-bottom,0px));padding-top:env(safe-area-inset-top,0px)}
        @media(display-mode:standalone){body{padding-top:0}.portal-nav{padding-top:max(14px,env(safe-area-inset-top,14px))}}
        .portal-nav{background:var(--brand-dark);padding:14px 16px;position:sticky;top:0;z-index:100;display:flex;align-items:center;gap:.75rem}
        .portal-nav .brand{color:#fff;font-weight:700;font-size:1rem;text-decoration:none;display:flex;align-items:center;gap:8px}
        .brand-dot{width:28px;height:28px;border-radius:8px;background:linear-gradient(135deg,#2563eb,#7c3aed);display:flex;align-items:center;justify-content:center}
        .user-chip{margin-left:auto;background:rgba(255,255,255,.1);color:#fff;border-radius:20px;padding:4px 12px;font-size:.78rem;font-weight:500;display:flex;align-items:center;gap:5px}
        #offline-bar{display:none;background:#7f1d1d;color:#fca5a5;text-align:center;padding:8px 16px;font-size:12px;font-weight:600}
        .bottom-nav{position:fixed;bottom:0;left:0;right:0;background:#fff;border-top:1px solid #e2e8f0;display:flex;z-index:100;box-shadow:0 -4px 20px rgba(0,0,0,.08);padding-bottom:env(safe-area-inset-bottom,0px)}
        .bottom-nav a{flex:1;text-align:center;padding:8px 4px;color:#94a3b8;font-size:.65rem;text-decoration:none;display:flex;flex-direction:column;align-items:center;gap:2px;transition:color .15s;-webkit-tap-highlight-color:transparent}
        .bottom-nav a svg{width:20px;height:20px}
        .bottom-nav a.active,.bottom-nav a:hover{color:var(--brand)}
        .pwa-teaser{display:none;background:linear-gradient(135deg,#1d4ed8,#7c3aed);color:#fff;padding:10px 16px;font-size:12px;font-weight:500;align-items:center;gap:10px;cursor:pointer}
        .pwa-teaser.visible{display:flex}
        .pwa-teaser-btn{margin-left:auto;background:rgba(255,255,255,.2);border:1px solid rgba(255,255,255,.3);color:#fff;padding:4px 12px;border-radius:20px;font-size:11px;font-weight:700;white-space:nowrap}
        .flash-fixed{position:fixed;top:70px;right:12px;z-index:9999;min-width:280px;max-width:360px}
        .portal-card{background:#fff;border-radius:14px;border:1px solid #e2e8f0;overflow:hidden}
    </style>
    @stack('styles')
</head>
<body>
<div id="offline-bar"><i class="bi bi-wifi-off me-1"></i> You're offline — showing cached data</div>
<nav class="portal-nav">
    <a href="{{ route('borrower.dashboard') }}" class="brand">
        <div class="brand-dot"><svg width="14" height="14" viewBox="0 0 24 24" fill="#fff"><path d="M20 6h-2.18c.07-.44.18-.86.18-1.3C18 2.12 15.88 0 13.3 0c-1.3 0-2.49.52-3.3 1.43L9 2.6 7.99 1.43C7.19.52 6.01 0 4.7 0 2.12 0 0 2.12 0 4.7c0 .44.1.86.18 1.3H0v2h2v11h20V8h-2V6z"/></svg></div>
        {{ \App\Models\Setting::get('company_name', 'Big Cash') }}
    </a>
    <div class="user-chip">
        <svg width="12" height="12" viewBox="0 0 24 24" fill="currentColor"><path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/></svg>
        {{ auth()->user()->name }}
    </div>
</nav>
<div class="pwa-teaser" id="pwa-teaser-bar" onclick="PWAInstall.show()">
    <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M19 9h-4V3H9v6H5l7 7 7-7zM5 18v2h14v-2H5z"/></svg>
    <span>Install the Big Cash app for faster access</span>
    <span class="pwa-teaser-btn">Install</span>
</div>
@if(session('success'))<div class="flash-fixed"><div class="alert alert-success alert-dismissible shadow-sm" role="alert"><i class="bi bi-check-circle-fill me-2"></i>{{ session('success') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div></div>@endif
@if(session('error'))<div class="flash-fixed"><div class="alert alert-danger alert-dismissible shadow-sm" role="alert"><i class="bi bi-exclamation-circle-fill me-2"></i>{{ session('error') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div></div>@endif
<div class="container-fluid" style="max-width:680px;padding:1rem">@yield('content')</div>
<nav class="bottom-nav">
    <a href="{{ route('borrower.dashboard') }}" class="@if(request()->routeIs('borrower.dashboard')) active @endif"><svg viewBox="0 0 24 24" fill="currentColor"><path d="M3 13h8V3H3v10zm0 8h8v-6H3v6zm10 0h8V11h-8v10zm0-18v6h8V3h-8z"/></svg>Home</a>
    <a href="{{ route('borrower.loans.index') }}" class="@if(request()->routeIs('borrower.loans.*')) active @endif"><svg viewBox="0 0 24 24" fill="currentColor"><path d="M11.8 10.9c-2.27-.59-3-1.2-3-2.15 0-1.09 1.01-1.85 2.7-1.85 1.78 0 2.44.85 2.5 2.1h2.21c-.07-1.72-1.12-3.3-3.21-3.81V3h-3v2.16c-1.94.42-3.5 1.68-3.5 3.61 0 2.31 1.91 3.46 4.7 4.13 2.5.6 3 1.48 3 2.41 0 .69-.49 1.79-2.7 1.79-2.06 0-2.87-.92-2.98-2.1h-2.2c.12 2.19 1.76 3.42 3.68 3.83V21h3v-2.15c1.95-.37 3.5-1.5 3.5-3.55 0-2.84-2.43-3.81-4.7-4.4z"/></svg>Loans</a>
    <a href="{{ route('borrower.loans.apply') }}" style="position:relative"><div style="width:46px;height:46px;background:linear-gradient(135deg,#1d4ed8,#7c3aed);border-radius:50%;display:flex;align-items:center;justify-content:center;margin-top:-16px;box-shadow:0 4px 14px rgba(37,99,235,.4);border:3px solid #fff"><svg width="20" height="20" viewBox="0 0 24 24" fill="#fff"><path d="M19 13h-6v6h-2v-6H5v-2h6V5h2v6h6v2z"/></svg></div>Apply</a>
    <a href="{{ route('borrower.payments.index') }}" class="@if(request()->routeIs('borrower.payments.*')) active @endif"><svg viewBox="0 0 24 24" fill="currentColor"><path d="M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm-7 3c1.93 0 3.5 1.57 3.5 3.5S13.93 13 12 13s-3.5-1.57-3.5-3.5S10.07 6 12 6z"/></svg>Payments</a>
    <a href="{{ route('borrower.profile') }}" class="@if(request()->routeIs('borrower.profile')) active @endif"><svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/></svg>Profile</a>
</nav>
<form id="logout-form" method="POST" action="{{ route('logout') }}" style="display:none">@csrf</form>
@include('layouts.pwa-install')
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="{{ asset('js/pwa.js') }}"></script>
<script>
setTimeout(()=>document.querySelectorAll('.flash-fixed .alert').forEach(a=>bootstrap.Alert.getOrCreateInstance(a)?.close()),4500);
window.addEventListener('online', ()=>document.getElementById('offline-bar').style.display='none');
window.addEventListener('offline',()=>document.getElementById('offline-bar').style.display='block');
if(!navigator.onLine) document.getElementById('offline-bar').style.display='block';
document.addEventListener('bigcash:show-install-prompt',()=>{
    const bar=document.getElementById('pwa-teaser-bar');
    if(bar) bar.classList.add('visible');
});
</script>
@stack('scripts')
</body>
</html>
