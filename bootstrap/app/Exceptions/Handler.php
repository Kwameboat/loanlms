<?php

namespace App\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Validation\ValidationException;
use Throwable;

class Handler extends ExceptionHandler
{
    /**
     * Exceptions that should not be reported.
     */
    protected $dontReport = [];

    /**
     * Don't flash these inputs to the session on validation errors.
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    public function register(): void
    {
        $this->reportable(function (Throwable $e) {
            // On Vercel, all output goes to stderr — already handled by logging config
        });
    }

    /**
     * Render exception into HTTP response.
     * Returns JSON for API requests, Blade views for browser requests.
     */
    public function render($request, Throwable $e)
    {
        // Return JSON for API / AJAX requests
        if ($request->expectsJson() || $request->is('api/*') || $request->is('portal/api/*')) {
            return $this->renderApiException($e, $request);
        }

        return parent::render($request, $e);
    }

    protected function renderApiException(Throwable $e, $request): \Illuminate\Http\JsonResponse
    {
        $status  = method_exists($e, 'getStatusCode') ? $e->getStatusCode() : 500;
        $message = $e->getMessage() ?: 'An unexpected error occurred.';

        if ($e instanceof ValidationException) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors'  => $e->errors(),
            ], 422);
        }

        if ($e instanceof AuthenticationException) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated.',
            ], 401);
        }

        if (config('app.debug')) {
            return response()->json([
                'success'   => false,
                'message'   => $message,
                'exception' => get_class($e),
                'file'      => $e->getFile(),
                'line'      => $e->getLine(),
                'trace'     => collect($e->getTrace())->take(10)->toArray(),
            ], $status);
        }

        return response()->json([
            'success' => false,
            'message' => $status >= 500 ? 'Server error. Please try again.' : $message,
        ], $status);
    }

    /**
     * Override unauthenticated to redirect to login instead of /login route
     * (important for Vercel where route caching may differ).
     */
    protected function unauthenticated($request, AuthenticationException $exception)
    {
        if ($request->expectsJson()) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $guard = $exception->guards()[0] ?? null;

        return redirect()->guest(
            $guard === 'borrower' ? route('login') : route('login')
        );
    }
}
