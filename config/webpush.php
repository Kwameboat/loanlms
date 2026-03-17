<?php

return [

    /*
    |--------------------------------------------------------------------------
    | VAPID Keys for Web Push Notifications
    |--------------------------------------------------------------------------
    |
    | Generate your VAPID keys using:
    |   php artisan webpush:vapid
    |
    | Or use the online generator:
    |   https://vapidkeys.com
    |
    | Then add to your .env:
    |   VAPID_PUBLIC_KEY=...
    |   VAPID_PRIVATE_KEY=...
    |
    */

    'vapid' => [
        'subject'     => env('VAPID_SUBJECT', 'mailto:' . env('MAIL_FROM_ADDRESS', 'noreply@bigcash.com')),
        'public_key'  => env('VAPID_PUBLIC_KEY', ''),
        'private_key' => env('VAPID_PRIVATE_KEY', ''),
    ],

    /*
    |--------------------------------------------------------------------------
    | Default TTL (seconds) for push messages
    |--------------------------------------------------------------------------
    */
    'ttl' => env('WEBPUSH_TTL', 2419200), // 4 weeks

    /*
    |--------------------------------------------------------------------------
    | Default urgency level
    | Options: very-low | low | normal | high
    |--------------------------------------------------------------------------
    */
    'urgency' => env('WEBPUSH_URGENCY', 'normal'),

];
