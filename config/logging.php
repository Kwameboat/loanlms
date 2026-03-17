<?php

use Monolog\Handler\NullHandler;
use Monolog\Handler\StreamHandler;
use Monolog\Handler\SyslogUdpHandler;
use Monolog\Processor\PsrLogMessageProcessor;

return [

    /*
    |--------------------------------------------------------------------------
    | Default Log Channel
    |--------------------------------------------------------------------------
    | On Vercel: use 'stderr' — Vercel captures stderr and shows it in
    | the Functions log tab in your project dashboard.
    | Locally: use 'stack' (writes to storage/logs/laravel.log)
    |--------------------------------------------------------------------------
    */
    'default' => env('LOG_CHANNEL', 'stderr'),

    'deprecations' => [
        'channel' => env('LOG_DEPRECATIONS_CHANNEL', 'null'),
        'trace'   => false,
    ],

    'channels' => [

        'stack' => [
            'driver'            => 'stack',
            'channels'          => ['single'],
            'ignore_exceptions' => false,
        ],

        'single' => [
            'driver' => 'single',
            'path'   => storage_path('logs/laravel.log'),
            'level'  => env('LOG_LEVEL', 'debug'),
            'replace_placeholders' => true,
        ],

        'daily' => [
            'driver' => 'daily',
            'path'   => storage_path('logs/laravel.log'),
            'level'  => env('LOG_LEVEL', 'debug'),
            'days'   => env('LOG_DAILY_DAYS', 14),
            'replace_placeholders' => true,
        ],

        /*
        |----------------------------------------------------------------------
        | stderr — Vercel's preferred logging channel
        | Logs appear in Vercel dashboard → Functions → Logs
        |----------------------------------------------------------------------
        */
        'stderr' => [
            'driver'    => 'monolog',
            'level'     => env('LOG_LEVEL', 'error'),
            'handler'   => StreamHandler::class,
            'formatter' => Monolog\Formatter\JsonFormatter::class,
            'with'      => ['stream' => 'php://stderr'],
            'processors' => [PsrLogMessageProcessor::class],
        ],

        'syslog' => [
            'driver'  => 'syslog',
            'level'   => env('LOG_LEVEL', 'debug'),
            'facility'=> LOG_USER,
            'replace_placeholders' => true,
        ],

        'null' => [
            'driver'  => 'monolog',
            'handler' => NullHandler::class,
        ],

        'emergency' => [
            'path' => storage_path('logs/laravel.log'),
        ],

    ],

];
