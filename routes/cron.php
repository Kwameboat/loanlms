<?php

/**
 * Big Cash LMS — Vercel Cron Endpoints
 *
 * These are hit by Vercel Cron (defined in vercel.json) or an external
 * cron service like cron-job.org. Protected by a shared secret.
 *
 * Add these routes to routes/web.php:
 *
 *   require __DIR__.'/cron.php';
 *
 * Or register them directly in routes/web.php.
 */

use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;

// ─── Cron Secret Guard ────────────────────────────────────────────────────────
// All cron endpoints require X-Cron-Secret header matching CRON_SECRET env var
// Vercel Cron automatically sends Authorization: Bearer $CRON_SECRET

$cronGuard = function (Request $request, \Closure $next) {
    $secret = config('app.cron_secret', env('CRON_SECRET', ''));

    if (empty($secret)) {
        // No secret configured — allow (development mode)
        return $next($request);
    }

    $provided = $request->header('Authorization', '');
    $provided = str_replace('Bearer ', '', $provided);

    if (! hash_equals($secret, $provided)) {
        abort(401, 'Invalid cron secret');
    }

    return $next($request);
};

// ─── Mark Overdue Loans (runs at 1:00 AM Africa/Accra) ───────────────────────
Route::get('/api/cron/mark-overdue', function () {
    \Artisan::call('loans:mark-overdue');
    return response()->json([
        'ok'     => true,
        'job'    => 'mark-overdue',
        'output' => \Artisan::output(),
        'time'   => now()->toIso8601String(),
    ]);
})->middleware('cron.auth');

// ─── Send Due Reminders (runs at 8:00 AM) ────────────────────────────────────
Route::get('/api/cron/send-reminders', function () {
    \Artisan::call('loans:send-reminders');
    \Artisan::call('push:send-reminders', ['--type' => 'due_reminders']);
    return response()->json([
        'ok'   => true,
        'job'  => 'send-reminders',
        'time' => now()->toIso8601String(),
    ]);
})->middleware('cron.auth');

// ─── Overdue Warnings (runs at 9:00 AM) ──────────────────────────────────────
Route::get('/api/cron/overdue-warnings', function () {
    \Artisan::call('loans:send-overdue-warnings');
    \Artisan::call('push:send-reminders', ['--type' => 'overdue_warnings']);
    return response()->json([
        'ok'   => true,
        'job'  => 'overdue-warnings',
        'time' => now()->toIso8601String(),
    ]);
})->middleware('cron.auth');

// ─── Cleanup (runs hourly) ────────────────────────────────────────────────────
Route::get('/api/cron/cleanup', function () {
    \Artisan::call('paystack:clean-expired-links');
    return response()->json([
        'ok'   => true,
        'job'  => 'cleanup',
        'time' => now()->toIso8601String(),
    ]);
})->middleware('cron.auth');

// ─── One-time migration runner (REMOVE AFTER USE) ────────────────────────────
// Uncomment temporarily after first Vercel deploy to run migrations
// Route::get('/api/setup/migrate-' . env('MIGRATE_TOKEN', 'CHANGE_ME'), function () {
//     \Artisan::call('migrate', ['--force' => true]);
//     \Artisan::call('db:seed', ['--force' => true]);
//     return response()->json(['ok' => true, 'output' => \Artisan::output()]);
// });
