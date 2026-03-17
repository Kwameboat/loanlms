<?php

/*
|--------------------------------------------------------------------------
| Big Cash LMS — Application Bootstrap
| Vercel-compatible: redirects storage to /tmp when running serverless
|--------------------------------------------------------------------------
*/

$app = new Illuminate\Foundation\Application(
    $_ENV['APP_BASE_PATH'] ?? dirname(__DIR__)
);

/*
|--------------------------------------------------------------------------
| Vercel Storage Override
| On Vercel, the filesystem is read-only except for /tmp.
| We redirect storage and bootstrap/cache to /tmp.
|--------------------------------------------------------------------------
*/
if (isset($_ENV['VERCEL']) || isset($_SERVER['VERCEL'])) {
    $app->useStoragePath('/tmp/storage');

    // Ensure directories exist
    $paths = [
        '/tmp/storage/app',
        '/tmp/storage/app/public',
        '/tmp/storage/framework/cache/data',
        '/tmp/storage/framework/sessions',
        '/tmp/storage/framework/testing',
        '/tmp/storage/framework/views',
        '/tmp/storage/logs',
        '/tmp/bootstrap/cache',
    ];
    foreach ($paths as $path) {
        if (! is_dir($path)) mkdir($path, 0755, true);
    }
}

/*
|--------------------------------------------------------------------------
| Bind Important Interfaces
|--------------------------------------------------------------------------
*/
$app->singleton(
    Illuminate\Contracts\Http\Kernel::class,
    App\Http\Kernel::class
);

$app->singleton(
    Illuminate\Contracts\Console\Kernel::class,
    App\Console\Kernel::class
);

$app->singleton(
    Illuminate\Contracts\Debug\ExceptionHandler::class,
    App\Exceptions\Handler::class
);

return $app;
