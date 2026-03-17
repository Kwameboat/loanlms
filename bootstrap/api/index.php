<?php

/**
 * Big Cash LMS — Vercel Serverless Entry Point
 *
 * This file is the PHP serverless function that Vercel invokes
 * for every HTTP request. It bootstraps Laravel and serves the response.
 *
 * Runtime: vercel-php@0.6.0
 */

// ─── Environment bootstrap ────────────────────────────────────────────────────

// Set base path to parent directory (Laravel root)
$laravelRoot = dirname(__DIR__);

// Ensure storage directories exist (Vercel has writable /tmp)
$storageDirs = [
    '/tmp/storage/app/public',
    '/tmp/storage/framework/cache/data',
    '/tmp/storage/framework/sessions',
    '/tmp/storage/framework/views',
    '/tmp/storage/logs',
    '/tmp/bootstrap/cache',
];

foreach ($storageDirs as $dir) {
    if (! is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
}

// Override storage path to writable /tmp on Vercel
// This is done via environment variable read by Laravel's Application
$_ENV['APP_STORAGE_PATH']   = '/tmp/storage';
$_ENV['APP_BOOTSTRAP_PATH'] = '/tmp/bootstrap';

// Copy compiled views/config if they don't exist in /tmp yet
// (Vercel runs buildCommand once, then serves from the snapshot)
$bootstrapCache = '/tmp/bootstrap/cache';
$srcBootstrap   = $laravelRoot . '/bootstrap/cache';

if (is_dir($srcBootstrap)) {
    foreach (glob($srcBootstrap . '/*.php') as $file) {
        $dest = $bootstrapCache . '/' . basename($file);
        if (! file_exists($dest)) {
            @copy($file, $dest);
        }
    }
}

// ─── Laravel Bootstrap ────────────────────────────────────────────────────────

require $laravelRoot . '/vendor/autoload.php';

$app = require_once $laravelRoot . '/bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);

$request  = Illuminate\Http\Request::capture();
$response = $kernel->handle($request);

$response->send();

$kernel->terminate($request, $response);
