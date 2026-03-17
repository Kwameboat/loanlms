<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\BorrowerController;
use App\Http\Controllers\Admin\LoanController;
use App\Http\Controllers\Admin\RepaymentController;
use App\Http\Controllers\Admin\ReportController;
use App\Http\Controllers\Admin\LoanProductController;
use App\Http\Controllers\Admin\BranchController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\Admin\AIController;
use App\Http\Controllers\Borrower\BorrowerPortalController;
use App\Http\Controllers\PaystackWebhookController;

/*
|--------------------------------------------------------------------------
| Public Routes (No Auth Required)
|--------------------------------------------------------------------------
*/

Route::get('/', fn() => redirect()->route('login'));

// Auth
Route::middleware('guest')->group(function () {
    Route::get('/login',                [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login',               [AuthController::class, 'login']);
    Route::get('/forgot-password',      [AuthController::class, 'showForgotPassword'])->name('password.request');
    Route::post('/forgot-password',     [AuthController::class, 'sendResetLink'])->name('password.email');
    Route::get('/reset-password/{token}',[AuthController::class, 'showResetPassword'])->name('password.reset');
    Route::post('/reset-password',      [AuthController::class, 'resetPassword'])->name('password.update');
});

// 2FA (needs session, not full auth)
Route::get('/two-factor',   [AuthController::class, 'show2fa'])->name('auth.2fa');
Route::post('/two-factor',  [AuthController::class, 'verify2fa']);
Route::post('/two-factor/resend', [AuthController::class, 'resendOtp'])->name('auth.2fa.resend');

// Paystack Webhook (no CSRF — excluded in middleware)
Route::post('/webhook/paystack', [PaystackWebhookController::class, 'handle'])->name('paystack.webhook');
Route::get('/payment/callback',  [PaystackWebhookController::class, 'callback'])->name('paystack.callback');

// PWA Share Target (no auth required — auth checked inside)
Route::post('/portal/share', [\App\Http\Controllers\PushSubscriptionController::class, 'shareTarget'])->name('pwa.share');

// Push Notification subscription (authenticated)
Route::middleware('auth')->group(function () {
    Route::post('/portal/push/subscribe',   [\App\Http\Controllers\PushSubscriptionController::class, 'subscribe'])->name('push.subscribe');
    Route::post('/portal/push/unsubscribe', [\App\Http\Controllers\PushSubscriptionController::class, 'unsubscribe'])->name('push.unsubscribe');
    Route::post('/portal/push/test',        [\App\Http\Controllers\PushSubscriptionController::class, 'sendTest'])->name('push.test');
});

/*
|--------------------------------------------------------------------------
| Authenticated Routes
|--------------------------------------------------------------------------
*/

Route::middleware(['auth', 'active.user'])->group(function () {

    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::get('/change-password',  [AuthController::class, 'showChangePassword'])->name('auth.change-password');
    Route::post('/change-password', [AuthController::class, 'changePassword']);

    // ── Admin / Staff Panel ──────────────────────────────────────────────────

    Route::middleware(['role:super_admin|admin|branch_manager|loan_officer|accountant|collector'])
        ->prefix('admin')
        ->name('admin.')
        ->group(function () {

        // Dashboard
        Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
        Route::get('/',          fn() => redirect()->route('admin.dashboard'));

        // Borrowers
        Route::get('/borrowers/search',                   [BorrowerController::class, 'search'])->name('borrowers.search');
        Route::post('/borrowers/{borrower}/note',         [BorrowerController::class, 'addNote'])->name('borrowers.note');
        Route::post('/borrowers/{borrower}/document',     [BorrowerController::class, 'uploadDocument'])->name('borrowers.document');
        Route::post('/borrowers/{borrower}/blacklist',    [BorrowerController::class, 'blacklist'])->name('borrowers.blacklist');
        Route::resource('borrowers', BorrowerController::class);

        // Loans
        Route::post('/loans/{loan}/submit',        [LoanController::class, 'submit'])->name('loans.submit');
        Route::post('/loans/{loan}/recommend',     [LoanController::class, 'recommend'])->name('loans.recommend');
        Route::post('/loans/{loan}/approve',       [LoanController::class, 'approve'])->name('loans.approve');
        Route::post('/loans/{loan}/reject',        [LoanController::class, 'reject'])->name('loans.reject');
        Route::post('/loans/{loan}/disburse',      [LoanController::class, 'disburse'])->name('loans.disburse');
        Route::post('/loans/{loan}/write-off',     [LoanController::class, 'writeOff'])->name('loans.write_off');
        Route::get('/loans/{loan}/reschedule',     [LoanController::class, 'reschedule'])->name('loans.reschedule');
        Route::post('/loans/{loan}/reschedule',    [LoanController::class, 'processReschedule'])->name('loans.process_reschedule');
        Route::get('/loans/{loan}/schedule',       [LoanController::class, 'schedule'])->name('loans.schedule');
        Route::get('/loans/{loan}/schedule/pdf',   [LoanController::class, 'downloadSchedule'])->name('loans.schedule_pdf');
        Route::get('/loans/{loan}/ai-assessment',  [LoanController::class, 'aiAssessment'])->name('loans.ai_assessment');
        Route::resource('loans', LoanController::class);

        // Repayments
        Route::get('/repayments/{repayment}/receipt',   [RepaymentController::class, 'receipt'])->name('repayments.receipt');
        Route::post('/repayments/{repayment}/reverse',  [RepaymentController::class, 'reverse'])->name('repayments.reverse');
        Route::get('/repayments/bulk-upload',           [RepaymentController::class, 'bulkUpload'])->name('repayments.bulk_upload');
        Route::post('/repayments/bulk-upload',          [RepaymentController::class, 'processBulkUpload'])->name('repayments.process_bulk');
        Route::post('/loans/{loan}/pay-online',         [RepaymentController::class, 'initiateOnlinePayment'])->name('repayments.pay_online');
        Route::resource('repayments', RepaymentController::class);

        // Loan Products
        Route::get('/products/calculator',        [\App\Http\Controllers\Admin\LoanProductController::class, 'calculator'])->name('products.calculator');
        Route::post('/products/{product}/toggle', [\App\Http\Controllers\Admin\LoanProductController::class, 'toggle'])->name('products.toggle');
        Route::resource('products', \App\Http\Controllers\Admin\LoanProductController::class);

        // Branches
        Route::resource('branches', BranchController::class);

        // Users
        Route::post('/users/{user}/reset-password',  [UserController::class, 'resetPassword'])->name('users.reset_password');
        Route::post('/users/{user}/toggle-status',   [UserController::class, 'toggleStatus'])->name('users.toggle_status');
        Route::resource('users', UserController::class);

        // Reports
        Route::prefix('reports')->name('reports.')->group(function () {
            Route::get('/',                      [ReportController::class, 'index'])->name('index');
            Route::get('/collections',           [ReportController::class, 'collections'])->name('collections');
            Route::get('/disbursements',         [ReportController::class, 'disbursements'])->name('disbursements');
            Route::get('/arrears',               [ReportController::class, 'arrears'])->name('arrears');
            Route::get('/portfolio',             [ReportController::class, 'portfolio'])->name('portfolio');
            Route::get('/officer-performance',  [ReportController::class, 'officerPerformance'])->name('officer_performance');
            Route::get('/collector-performance',[ReportController::class, 'collectorPerformance'])->name('collector_performance');
            Route::get('/expected-collections', [ReportController::class, 'expectedCollections'])->name('expected_collections');
            Route::get('/ledger',               [ReportController::class, 'ledger'])->name('ledger');
        });

        // Settings
        Route::get('/settings',         [SettingsController::class, 'index'])->name('settings.index');
        Route::post('/settings',        [SettingsController::class, 'update'])->name('settings.update');
        Route::post('/settings/test-email', [SettingsController::class, 'testEmail'])->name('settings.test_email');

        // AI
        Route::prefix('ai')->name('ai.')->group(function () {
            Route::post('/chat',                       [AIController::class, 'chat'])->name('chat');
            Route::post('/chat/clear',                 [AIController::class, 'clearChat'])->name('chat_clear');
            Route::get('/assess/{loan}',               [AIController::class, 'assessLoan'])->name('assess');
            Route::post('/message/{loan}',             [AIController::class, 'generateMessage'])->name('message');
        });
    });

    // ── Borrower Portal ──────────────────────────────────────────────────────

    Route::middleware('role:borrower')
        ->prefix('portal')
        ->name('borrower.')
        ->group(function () {

        Route::get('/dashboard',                            [BorrowerPortalController::class, 'dashboard'])->name('dashboard');
        Route::get('/loans',                                [BorrowerPortalController::class, 'loans'])->name('loans.index');
        Route::get('/loans/apply',                          [BorrowerPortalController::class, 'applyForLoan'])->name('loans.apply');
        Route::post('/loans/apply',                         [BorrowerPortalController::class, 'submitApplication'])->name('loans.submit');
        Route::get('/loans/{loan}',                         [BorrowerPortalController::class, 'loanDetail'])->name('loans.show');
        Route::get('/loans/{loan}/schedule',                [BorrowerPortalController::class, 'schedule'])->name('loans.schedule');
        Route::get('/loans/{loan}/schedule/download',       [BorrowerPortalController::class, 'downloadSchedule'])->name('loans.schedule_download');
        Route::post('/loans/{loan}/pay',                    [BorrowerPortalController::class, 'payOnline'])->name('loans.pay');
        Route::get('/payments',                             [BorrowerPortalController::class, 'payments'])->name('payments.index');
        Route::get('/payments/{repayment}/receipt',         [BorrowerPortalController::class, 'receipt'])->name('payments.receipt');
        Route::get('/profile',                              [BorrowerPortalController::class, 'profile'])->name('profile');
    });

    // ── Shared: Dashboard redirect ───────────────────────────────────────────
    Route::get('/dashboard', function () {
        if (auth()->user()->hasRole('borrower')) return redirect()->route('borrower.dashboard');
        return redirect()->route('admin.dashboard');
    })->name('dashboard');
});

// ─── Vercel Cron Routes ───────────────────────────────────────────────────────
// Protected by CRON_SECRET env var — hit by Vercel Cron or cron-job.org
Route::get('/api/cron/mark-overdue', function () {
    \Artisan::call('loans:mark-overdue');
    return response()->json(['ok'=>true,'job'=>'mark-overdue','time'=>now()->toIso8601String()]);
})->middleware('cron.guard');

Route::get('/api/cron/send-reminders', function () {
    \Artisan::call('loans:send-reminders');
    app(\App\Console\Commands\SendPushNotifications::class)->handle();
    return response()->json(['ok'=>true,'job'=>'reminders','time'=>now()->toIso8601String()]);
})->middleware('cron.guard');

Route::get('/api/cron/cleanup', function () {
    \Artisan::call('paystack:clean-expired-links');
    return response()->json(['ok'=>true,'job'=>'cleanup','time'=>now()->toIso8601String()]);
})->middleware('cron.guard');

Route::get('/api/health', function () {
    $db = false;
    try { \DB::connection()->getPdo(); $db = true; } catch (\Exception $e) {}
    return response()->json(['status'=>$db?'ok':'degraded','app'=>config('app.name'),'db'=>$db?'connected':'unavailable','vercel'=>isset($_SERVER['VERCEL'])]);
});
