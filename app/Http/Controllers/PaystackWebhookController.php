<?php

namespace App\Http\Controllers;

use App\Services\Payment\PaystackService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PaystackWebhookController extends Controller
{
    public function __construct(protected PaystackService $paystackService) {}

    /**
     * Handle Paystack webhook — no CSRF, signature-verified.
     */
    public function handle(Request $request)
    {
        // 1. Verify signature
        if (! $this->paystackService->verifyWebhookSignature($request)) {
            Log::warning('Paystack webhook: invalid signature', ['ip' => $request->ip()]);
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        // 2. Acknowledge immediately (Paystack expects 200 fast)
        $payload = $request->json()->all();
        Log::channel('daily')->info('Paystack webhook received', ['event' => $payload['event'] ?? 'unknown']);

        // 3. Process (sync — safe on shared hosting, fast enough for webhooks)
        try {
            $this->paystackService->processWebhook($payload);
        } catch (\Exception $e) {
            Log::error('Paystack webhook processing error', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            // Still return 200 to Paystack so it doesn't retry forever
        }

        return response()->json(['message' => 'Webhook received'], 200);
    }

    /**
     * Paystack redirect callback (browser redirect after payment).
     */
    public function callback(Request $request)
    {
        $reference = $request->reference;
        if (! $reference) {
            return redirect()->route('borrower.dashboard')->with('error', 'Invalid payment reference.');
        }

        // Verify directly with API as extra safety
        $result = $this->paystackService->verifyTransaction($reference);

        if ($result['status'] && ($result['data']['status'] ?? '') === 'success') {
            return redirect()->route('borrower.dashboard')
                ->with('success', 'Payment successful! Reference: ' . $reference);
        }

        return redirect()->route('borrower.dashboard')
            ->with('error', 'Payment could not be verified. Reference: ' . $reference . '. Please contact support if you were charged.');
    }
}
