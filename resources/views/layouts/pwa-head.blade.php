{{--
    Big Cash PWA Head Partial
    Include inside <head> of all layouts: @include('layouts.pwa-head')
--}}

{{-- Primary PWA Meta --}}
<meta name="application-name" content="{{ \App\Models\Setting::get('company_name', 'Big Cash Finance') }}">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<meta name="apple-mobile-web-app-title" content="Big Cash">
<meta name="theme-color" content="#1d4ed8" media="(prefers-color-scheme: light)">
<meta name="theme-color" content="#0f172a" media="(prefers-color-scheme: dark)">
<meta name="msapplication-TileColor" content="#1d4ed8">
<meta name="msapplication-TileImage" content="{{ asset('icons/icon-144x144.png') }}">

{{-- Manifest --}}
<link rel="manifest" href="{{ asset('manifest.json') }}">

{{-- Apple Touch Icons --}}
<link rel="apple-touch-icon" href="{{ asset('icons/icon-192x192.png') }}">
<link rel="apple-touch-icon" sizes="72x72"   href="{{ asset('icons/icon-72x72.png') }}">
<link rel="apple-touch-icon" sizes="96x96"   href="{{ asset('icons/icon-96x96.png') }}">
<link rel="apple-touch-icon" sizes="128x128" href="{{ asset('icons/icon-128x128.png') }}">
<link rel="apple-touch-icon" sizes="144x144" href="{{ asset('icons/icon-144x144.png') }}">
<link rel="apple-touch-icon" sizes="152x152" href="{{ asset('icons/icon-152x152.png') }}">
<link rel="apple-touch-icon" sizes="192x192" href="{{ asset('icons/icon-192x192.png') }}">
<link rel="apple-touch-icon" sizes="512x512" href="{{ asset('icons/icon-512x512.png') }}">

{{-- Favicons --}}
<link rel="icon" type="image/png" sizes="32x32" href="{{ asset('icons/icon-96x96.png') }}">
<link rel="icon" type="image/png" sizes="16x16" href="{{ asset('icons/icon-72x72.png') }}">
<link rel="shortcut icon" href="{{ asset('icons/icon-96x96.png') }}">

{{-- Apple Splash Screens (iPhone / iPad) --}}
{{-- iPhone 14 Pro Max --}}
<link rel="apple-touch-startup-image" media="screen and (device-width: 430px) and (device-height: 932px) and (-webkit-device-pixel-ratio: 3)" href="{{ asset('screenshots/splash-1290x2796.png') }}">
{{-- iPhone 14 --}}
<link rel="apple-touch-startup-image" media="screen and (device-width: 390px) and (device-height: 844px) and (-webkit-device-pixel-ratio: 3)" href="{{ asset('screenshots/splash-1170x2532.png') }}">
{{-- iPhone SE --}}
<link rel="apple-touch-startup-image" media="screen and (device-width: 375px) and (device-height: 667px) and (-webkit-device-pixel-ratio: 2)" href="{{ asset('screenshots/splash-750x1334.png') }}">

{{-- Open Graph / Social (PWA share) --}}
<meta property="og:type"        content="website">
<meta property="og:title"       content="{{ \App\Models\Setting::get('company_name', 'Big Cash Finance') }}">
<meta property="og:description" content="Apply for loans, track repayments, manage your finances.">
<meta property="og:image"       content="{{ asset('icons/icon-512x512.png') }}">
<meta property="og:url"         content="{{ url()->current() }}">

{{-- VAPID Public Key for Push Notifications --}}
<script>
    window.KOBOFLOW_VAPID_KEY = '{{ config('webpush.vapid.public_key', '') }}';
    window.KOBOFLOW_DEBUG     = {{ config('app.debug') ? 'true' : 'false' }};
</script>
