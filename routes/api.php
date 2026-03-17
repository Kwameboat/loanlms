<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Big Cash API Routes
| Used by the PWA (background sync, periodic refresh, push)
| and future mobile app integration.
|--------------------------------------------------------------------------
*/

Route::middleware('auth:sanctum')->group(function () {

    // ── Auth ──────────────────────────────────────────────────────────────
    Route::get('/user', fn (Request $request) => $request->user());

    // ── Borrower Portal Summary (used by PWA periodic sync) ───────────────
    Route::get('/portal/summary', function (Request $request) {
        $user     = $request->user();
        $borrower = \App\Models\Borrower::where('email', $user->email)->first();

        if (! $borrower) {
            return response()->json(['error' => 'Borrower profile not found'], 404);
        }

        $activeLoans = $borrower->loans()
            ->whereIn('status', ['active', 'overdue', 'disbursed'])
            ->with('loanProduct')
            ->get();

        return response()->json([
            'borrower_number'   => $borrower->borrower_number,
            'total_outstanding' => $activeLoans->sum('total_outstanding'),
            'active_loans'      => $activeLoans->count(),
            'overdue_loans'     => $activeLoans->where('is_overdue', true)->count(),
            'next_due_amount'   => $activeLoans->sum('next_due_amount'),
            'credit_score'      => $borrower->credit_score,
            'loans'             => $activeLoans->map(fn ($l) => [
                'id'              => $l->id,
                'loan_number'     => $l->loan_number,
                'product'         => $l->loanProduct->name,
                'outstanding'     => $l->total_outstanding,
                'next_due_date'   => $l->next_due_date,
                'next_due_amount' => $l->next_due_amount,
                'status'          => $l->status,
                'is_overdue'      => $l->is_overdue,
                'days_past_due'   => $l->days_past_due,
            ]),
            'last_updated' => now()->toIso8601String(),
        ]);
    });

    // ── Recent Payments ───────────────────────────────────────────────────
    Route::get('/portal/payments', function (Request $request) {
        $user     = $request->user();
        $borrower = \App\Models\Borrower::where('email', $user->email)->first();

        if (! $borrower) {
            return response()->json(['error' => 'Not found'], 404);
        }

        $payments = $borrower->repayments()
            ->where('status', 'confirmed')
            ->with('loan')
            ->latest('payment_date')
            ->take(20)
            ->get();

        return response()->json([
            'payments' => $payments->map(fn ($p) => [
                'id'             => $p->id,
                'receipt_number' => $p->receipt_number,
                'amount'         => $p->amount,
                'loan_number'    => $p->loan->loan_number,
                'payment_date'   => $p->payment_date->toDateString(),
                'method'         => $p->payment_method,
                'status'         => $p->status,
            ]),
        ]);
    });

    // ── Push Subscription ─────────────────────────────────────────────────
    Route::post('/push/subscribe',   [\App\Http\Controllers\PushSubscriptionController::class, 'subscribe']);
    Route::post('/push/unsubscribe', [\App\Http\Controllers\PushSubscriptionController::class, 'unsubscribe']);
    Route::post('/push/test',        [\App\Http\Controllers\PushSubscriptionController::class, 'sendTest']);

    // ── PWA Analytics Event ───────────────────────────────────────────────
    Route::post('/analytics/event', function (Request $request) {
        // Non-critical — just acknowledge
        \Log::info('PWA Event', [
            'user'  => $request->user()->id,
            'event' => $request->event,
            'data'  => $request->data,
            'pwa'   => $request->pwa,
        ]);
        return response()->json(['ok' => true]);
    });

    // ── Loan Application (offline queue sync) ────────────────────────────
    Route::post('/portal/loans/apply', function (Request $request) {
        // Re-use borrower portal controller method
        return app()->call(
            [\App\Http\Controllers\Borrower\BorrowerPortalController::class, 'submitApplication'],
            ['request' => $request]
        );
    });

});

// ── Health check (no auth) ────────────────────────────────────────────────
Route::get('/health', function () {
    $dbOk = false;
    try {
        \DB::connection()->getPdo();
        $dbOk = true;
    } catch (\Exception $e) {}

    return response()->json([
        'status'    => $dbOk ? 'ok' : 'degraded',
        'app'       => config('app.name'),
        'env'       => config('app.env'),
        'db'        => $dbOk ? 'connected' : 'unavailable',
        'timestamp' => now()->toIso8601String(),
        'vercel'    => isset($_SERVER['VERCEL']) ? true : false,
    ], $dbOk ? 200 : 503);
});
