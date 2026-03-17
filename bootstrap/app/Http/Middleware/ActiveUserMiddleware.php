<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ActiveUserMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        if (Auth::check() && !Auth::user()->is_active) {
            Auth::logout();
            $request->session()->invalidate();
            return redirect()->route('login')
                ->with('error', 'Your account has been deactivated. Please contact your administrator.');
        }
        return $next($request);
    }
}
