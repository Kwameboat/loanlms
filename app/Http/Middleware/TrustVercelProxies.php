<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Vercel Proxy Trust Middleware
 *
 * Vercel sits behind a CDN/edge network and forwards requests via proxy.
 * This middleware ensures Laravel trusts Vercel's forwarded headers so that:
 *  - HTTPS is detected correctly (session secure cookies work)
 *  - The real client IP is used for rate limiting
 *  - URL generation uses the correct scheme (https://)
 */
class TrustVercelProxies extends \Illuminate\Http\Middleware\TrustProxies
{
    /**
     * Trust all Vercel proxies.
     * Vercel uses * to indicate all proxies should be trusted.
     */
    protected $proxies = '*';

    /**
     * Headers to use for detecting the client's real IP, protocol, and host.
     */
    protected $headers =
        Request::HEADER_X_FORWARDED_FOR    |
        Request::HEADER_X_FORWARDED_HOST   |
        Request::HEADER_X_FORWARDED_PORT   |
        Request::HEADER_X_FORWARDED_PROTO  |
        Request::HEADER_X_FORWARDED_AWS_ELB;
}
