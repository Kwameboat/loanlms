<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * Protects Vercel cron endpoints with a shared secret.
 * Vercel Cron sends: Authorization: Bearer {CRON_SECRET}
 * cron-job.org: add the secret as a query param or custom header.
 */
class CronGuard
{
    public function handle(Request $request, Closure $next)
    {
        $secret = env('CRON_SECRET', '');

        // If no secret is set, only allow from localhost (dev)
        if (empty($secret)) {
            if (! in_array($request->ip(), ['127.0.0.1', '::1'])) {
                abort(403, 'CRON_SECRET not configured.');
            }
            return $next($request);
        }

        // Check Authorization: Bearer header (Vercel Cron)
        $auth = $request->header('Authorization', '');
        $token = str_replace('Bearer ', '', $auth);

        // Also allow ?token= query parameter (for cron-job.org)
        if (empty($token)) {
            $token = $request->query('token', '');
        }

        if (! hash_equals($secret, $token)) {
            abort(401, 'Invalid cron authentication.');
        }

        return $next($request);
    }
}
