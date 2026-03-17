<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>404 — Page Not Found | Big Cash Finance</title>
    @include('layouts.pwa-head')
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Inter', system-ui, sans-serif; background: #f1f5f9; display: flex; align-items: center; justify-content: center; min-height: 100vh; padding: 24px; text-align: center; }
        .card { background: #fff; border-radius: 16px; border: 1px solid #e2e8f0; padding: 40px 32px; max-width: 400px; box-shadow: 0 4px 16px rgba(15,23,42,.08); }
        .logo { width: 56px; height: 56px; border-radius: 14px; background: linear-gradient(135deg, #16a34a, #15803d); margin: 0 auto 20px; display: flex; align-items: center; justify-content: center; font-weight: 800; font-size: 18px; color: #fff; }
        h1 { font-size: 64px; font-weight: 800; color: #0f172a; line-height: 1; letter-spacing: -.04em; margin-bottom: 8px; }
        h2 { font-size: 18px; font-weight: 600; color: #334155; margin-bottom: 10px; }
        p  { font-size: 14px; color: #64748b; line-height: 1.6; margin-bottom: 24px; }
        .btn { display: inline-flex; align-items: center; gap: 6px; padding: 10px 20px; background: linear-gradient(135deg, #16a34a, #15803d); color: #fff; border-radius: 8px; font-size: 13px; font-weight: 600; text-decoration: none; }
        .btn-outline { background: #fff; color: #334155; border: 1px solid #e2e8f0; margin-left: 8px; }
    </style>
</head>
<body>
    <div class="card">
        <div class="logo">BC</div>
        <h1>404</h1>
        <h2>Page not found</h2>
        <p>The page you're looking for doesn't exist or has been moved. Check the URL or head back home.</p>
        <a href="{{ url('/') }}" class="btn">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><path d="M10 20v-6h4v6h5v-8h3L12 3 2 12h3v8z"/></svg>
            Go home
        </a>
        <a href="javascript:history.back()" class="btn btn-outline">← Go back</a>
    </div>
</body>
</html>
