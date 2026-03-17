<?php

use Illuminate\Support\Str;

return [

    /*
    |--------------------------------------------------------------------------
    | Session Driver
    |--------------------------------------------------------------------------
    | On Vercel (serverless), file-based sessions don't persist across
    | function invocations. Use 'cookie' or 'database'.
    |
    | Recommended for Vercel: 'cookie' (encrypted, stateless)
    | For production with DB: 'database'
    |--------------------------------------------------------------------------
    */
    'driver' => env('SESSION_DRIVER', 'cookie'),

    'lifetime' => env('SESSION_LIFETIME', 120),

    'expire_on_close' => false,

    'encrypt' => true,

    'files' => storage_path('framework/sessions'),

    'connection' => env('SESSION_CONNECTION'),

    'table' => 'sessions',

    'store' => env('SESSION_STORE'),

    'lottery' => [2, 100],

    'cookie' => env(
        'SESSION_COOKIE',
        Str::slug(env('APP_NAME', 'bigcash'), '_') . '_session'
    ),

    'path' => '/',

    'domain' => env('SESSION_DOMAIN'),

    'secure' => env('SESSION_SECURE_COOKIE', true),

    'http_only' => true,

    'same_site' => env('SESSION_SAME_SITE', 'lax'),

    'partitioned' => false,

];
