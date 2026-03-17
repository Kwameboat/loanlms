<?php

return [

    'default' => env('FILESYSTEM_DISK', 'public'),

    'disks' => [

        'local' => [
            'driver' => 'local',
            'root'   => storage_path('app'),
            'throw'  => false,
        ],

        'public' => [
            'driver'     => 'local',
            'root'       => storage_path('app/public'),
            'url'        => env('APP_URL') . '/storage',
            'visibility' => 'public',
            'throw'      => false,
        ],

        /*
        |----------------------------------------------------------------------
        | Vercel / Production — use S3-compatible storage
        | On Vercel, local storage is ephemeral (/tmp only).
        | For persistent file uploads (KYC docs, receipts) configure S3 or
        | Cloudflare R2 below and set FILESYSTEM_DISK=s3 in Vercel env vars.
        |----------------------------------------------------------------------
        */
        's3' => [
            'driver'                  => 's3',
            'key'                     => env('AWS_ACCESS_KEY_ID'),
            'secret'                  => env('AWS_SECRET_ACCESS_KEY'),
            'region'                  => env('AWS_DEFAULT_REGION', 'us-east-1'),
            'bucket'                  => env('AWS_BUCKET'),
            'url'                     => env('AWS_URL'),
            'endpoint'                => env('AWS_ENDPOINT'),       // For Cloudflare R2
            'use_path_style_endpoint' => env('AWS_USE_PATH_STYLE_ENDPOINT', false),
            'throw'                   => false,
        ],

        /*
        |----------------------------------------------------------------------
        | Cloudflare R2 — Recommended for Ghana / Africa latency
        | R2 has no egress fees. Set endpoint to your R2 account endpoint.
        |----------------------------------------------------------------------
        */
        'r2' => [
            'driver'                  => 's3',
            'key'                     => env('CLOUDFLARE_R2_ACCESS_KEY_ID'),
            'secret'                  => env('CLOUDFLARE_R2_SECRET_ACCESS_KEY'),
            'region'                  => 'auto',
            'bucket'                  => env('CLOUDFLARE_R2_BUCKET'),
            'endpoint'                => env('CLOUDFLARE_R2_ENDPOINT'), // https://ACCOUNT.r2.cloudflarestorage.com
            'use_path_style_endpoint' => true,
            'url'                     => env('CLOUDFLARE_R2_PUBLIC_URL'),
            'visibility'              => 'public',
            'throw'                   => false,
        ],

    ],

    'links' => [
        public_path('storage') => storage_path('app/public'),
    ],

];
