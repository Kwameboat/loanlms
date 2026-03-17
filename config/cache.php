<?php

use Illuminate\Support\Str;

return [

    /*
    |--------------------------------------------------------------------------
    | Cache Store
    |--------------------------------------------------------------------------
    | On Vercel serverless, use 'array' (in-memory per request) by default.
    | For persistent caching, configure Upstash Redis (serverless-compatible).
    |--------------------------------------------------------------------------
    */
    'default' => env('CACHE_DRIVER', 'array'),

    'stores' => [

        'array' => [
            'driver'    => 'array',
            'serialize' => false,
        ],

        'database' => [
            'driver'     => 'database',
            'table'      => env('DB_CACHE_TABLE', 'cache'),
            'connection' => env('DB_CONNECTION', 'mysql'),
            'lock_connection' => null,
        ],

        'file' => [
            'driver' => 'file',
            'path'   => storage_path('framework/cache/data'),
            'lock_path' => storage_path('framework/cache/data'),
        ],

        /*
        |----------------------------------------------------------------------
        | Upstash Redis — Serverless Redis for Vercel
        | Free tier: 10,000 requests/day — perfect for caching settings
        | https://upstash.com
        |----------------------------------------------------------------------
        */
        'redis' => [
            'driver'     => 'redis',
            'connection' => 'cache',
            'lock_connection' => 'default',
        ],

    ],

    'prefix' => env('CACHE_PREFIX', Str::slug(env('APP_NAME', 'bigcash'), '_') . '_cache_'),

];
