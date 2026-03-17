<?php

namespace App\Http\Middleware;

use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken as Middleware;

class VerifyCsrfToken extends Middleware
{
    /**
     * Paystack webhook must be excluded from CSRF — verified by signature instead.
     */
    protected $except = [
        '/webhook/paystack',
    ];
}
