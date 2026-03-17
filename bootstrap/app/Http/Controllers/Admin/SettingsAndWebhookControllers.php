<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Artisan, Cache};

class SettingsController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth', 'permission:settings.view']);
        $this->middleware('permission:settings.edit')->only(['update', 'updateGroup']);
    }

    public function index()
    {
        $settings = Setting::all()->groupBy('group');
        return view('admin.settings.index', compact('settings'));
    }

    public function updateGroup(Request $request, string $group)
    {
        $this->authorize('update', Setting::class);

        $data = $request->except(['_token', '_method', 'group']);

        foreach ($data as $key => $value) {
            Setting::updateOrCreate(
                ['key' => "{$group}.{$key}"],
                ['value' => is_array($value) ? json_encode($value) : $value, 'group' => $group]
            );
        }

        // Handle logo upload
        if ($request->hasFile('company_logo')) {
            $path = $request->file('company_logo')->store('company', 'public');
            Setting::updateOrCreate(['key' => 'company.logo'], ['value' => $path, 'group' => 'company']);
        }

        Cache::flush(); // Clear cached settings

        activity()->causedBy(auth()->user())->log("Updated {$group} settings");

        return back()->with('success', ucfirst($group) . ' settings saved successfully.');
    }

    public function testSmtp(Request $request)
    {
        $request->validate(['email' => 'required|email']);

        try {
            \Illuminate\Support\Facades\Mail::raw(
                'This is a test email from Big Cash LMS.',
                fn($msg) => $msg->to($request->email)->subject('Big Cash Test Email')
            );
            return response()->json(['success' => true, 'message' => 'Test email sent successfully.']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    public function testSms(Request $request)
    {
        $request->validate(['phone' => 'required|string']);

        try {
            app(\App\Services\Notification\NotificationService::class)
                ->sendTestSMS($request->phone, 'Big Cash LMS SMS test. If you received this, SMS is working.');
            return response()->json(['success' => true, 'message' => 'Test SMS sent.']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    public function testPaystack(Request $request)
    {
        try {
            $ps     = app(\App\Services\Payment\PaystackService::class);
            $result = $ps->listTransactions(1, 1);
            $ok     = $result['status'] ?? false;
            return response()->json(['success' => $ok, 'message' => $ok ? 'Paystack connection successful.' : 'Failed: ' . ($result['message'] ?? 'Unknown')]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    public function runMigrations()
    {
        if (!auth()->user()->isSuperAdmin()) abort(403);
        try {
            Artisan::call('migrate', ['--force' => true]);
            return back()->with('success', 'Migrations ran successfully.');
        } catch (\Exception $e) {
            return back()->with('error', 'Migration error: ' . $e->getMessage());
        }
    }
}


// ─── Paystack Webhook Controller ──────────────────────────────────────────────

namespace App\Http\Controllers;

use App\Services\Loan\RepaymentService;
use App\Services\Payment\PaystackService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PaystackWebhookController extends Controller
{
    public function handle(Request $request, PaystackService $paystack, RepaymentService $repaymentService)
    {
        $payload   = $request->getContent();
        $signature = $request->header('X-Paystack-Signature', '');

        // 1. Verify webhook signature
        if (!$paystack->verifyWebhookSignature($payload, $signature)) {
            Log::warning('Paystack webhook: invalid signature');
            return response()->json(['message' => 'Invalid signature'], 401);
        }

        $data = json_decode($payload, true);
        if (!$data) {
            return response()->json(['message' => 'Invalid payload'], 400);
        }

        // 2. Process (idempotency handled inside service)
        try {
            $result = $paystack->handleWebhook($data, $repaymentService);
            return response()->json($result, 200);
        } catch (\Exception $e) {
            Log::error('Paystack webhook exception', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Internal error'], 500);
        }
    }

    /**
     * Paystack redirects here after payment (non-webhook flow).
     */
    public function callback(Request $request, PaystackService $paystack, RepaymentService $repaymentService)
    {
        $reference = $request->reference ?? $request->trxref;

        if (!$reference) {
            return redirect()->route('borrower.dashboard')->with('error', 'Invalid payment reference.');
        }

        try {
            $transaction = $paystack->verifyTransaction($reference);

            if ($transaction['status'] === 'success') {
                // Check idempotency
                $existing = \App\Models\Repayment::where('paystack_reference', $reference)->first();

                if (!$existing) {
                    $paymentLink = \App\Models\PaymentLink::where('reference', $reference)->first();
                    if ($paymentLink && $paymentLink->loan->isActive()) {
                        $repaymentService->recordRepayment($paymentLink->loan, [
                            'amount'             => $transaction['amount'] / 100,
                            'payment_method'     => 'paystack',
                            'payment_date'       => now()->toDateString(),
                            'paystack_reference' => $reference,
                            'paystack_channel'   => $transaction['channel'] ?? '',
                            'paystack_fees'      => ($transaction['fees'] ?? 0) / 100,
                            'paystack_raw'       => $transaction,
                            'paystack_status'    => 'success',
                        ], auth()->id() ?? 1);

                        $paymentLink->update(['status' => 'paid']);
                    }
                }

                return redirect()->route('borrower.loans.show', $paymentLink->loan_id ?? 0)
                    ->with('success', 'Payment successful! Your receipt has been generated.');
            }

            return redirect()->back()->with('error', 'Payment was not successful. Please try again.');
        } catch (\Exception $e) {
            Log::error('Paystack callback error', ['reference' => $reference, 'error' => $e->getMessage()]);
            return redirect()->back()->with('error', 'Payment verification failed. Contact support.');
        }
    }
}


// ─── AI Controller ─────────────────────────────────────────────────────────────

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\{Loan, Borrower};
use App\Services\AI\AIService;
use Illuminate\Http\Request;

class AIController extends Controller
{
    public function __construct(private AIService $ai)
    {
        $this->middleware(['auth', 'permission:ai.use']);
    }

    public function analyzeLoan(Request $request, Loan $loan)
    {
        $result = $this->ai->analyzeLoanApplication($loan);
        return response()->json($result);
    }

    public function analyzeBorrower(Request $request, Borrower $borrower)
    {
        $result = $this->ai->analyzeBorrowerProfile($borrower);
        return response()->json($result);
    }

    public function askQuestion(Request $request, Loan $loan)
    {
        $request->validate(['question' => 'required|string|max:500']);
        $answer = $this->ai->answerLoanQuestion($loan, $request->question);
        return response()->json(['answer' => $answer]);
    }

    public function generateReminder(Request $request, Loan $loan)
    {
        $type    = $request->type ?? 'due_soon';
        $message = $this->ai->generateReminderMessage($loan, $type);
        return response()->json(['message' => $message]);
    }
}
