<?php

namespace App\Services\Payment;

use App\Models\Loan;
use App\Models\Borrower;
use App\Models\Repayment;
use App\Models\PaymentLink;
use App\Models\RepaymentSchedule;
use App\Services\Loan\RepaymentAllocationService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Request;

class PaystackService
{
    protected string $secretKey;
    protected string $baseUrl;

    public function __construct()
    {
        $this->secretKey = config('bigcash.paystack.secret_key');
        $this->baseUrl   = config('bigcash.paystack.payment_url', 'https://api.paystack.co');
    }

    // ─────────────────────────────────────────────────────────────────────────
    // INITIALIZE PAYMENT
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Initialize a Paystack transaction for a loan repayment.
     */
    public function initializePayment(
        Loan   $loan,
        float  $amount,
        string $email,
        string $purpose = 'installment',
        ?int   $scheduleId = null
    ): array {
        $amountKobo = (int) round($amount * 100); // Paystack uses smallest currency unit
        $reference  = $this->generateReference($loan->loan_number);

        $payload = [
            'email'     => $email,
            'amount'    => $amountKobo,
            'reference' => $reference,
            'currency'  => 'GHS',
            'callback_url' => route('paystack.callback'),
            'metadata'  => [
                'loan_id'              => $loan->id,
                'loan_number'          => $loan->loan_number,
                'borrower_id'          => $loan->borrower_id,
                'purpose'              => $purpose,
                'repayment_schedule_id'=> $scheduleId,
                'custom_fields'        => [
                    ['display_name' => 'Loan Number',    'variable_name' => 'loan_number',    'value' => $loan->loan_number],
                    ['display_name' => 'Borrower Name',  'variable_name' => 'borrower_name',  'value' => $loan->borrower->display_name],
                    ['display_name' => 'Payment Purpose','variable_name' => 'purpose',        'value' => ucfirst($purpose)],
                ],
            ],
        ];

        $response = $this->post('/transaction/initialize', $payload);

        if ($response['status']) {
            // Create payment link record
            PaymentLink::create([
                'loan_id'               => $loan->id,
                'borrower_id'           => $loan->borrower_id,
                'repayment_schedule_id' => $scheduleId,
                'reference'             => $reference,
                'paystack_access_code'  => $response['data']['access_code'] ?? null,
                'authorization_url'     => $response['data']['authorization_url'] ?? null,
                'amount'                => $amount,
                'email'                 => $email,
                'purpose'              => $purpose,
                'status'               => 'pending',
                'expires_at'           => now()->addHours(24),
            ]);
        }

        return $response;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // VERIFY TRANSACTION
    // ─────────────────────────────────────────────────────────────────────────

    public function verifyTransaction(string $reference): array
    {
        return $this->get("/transaction/verify/{$reference}");
    }

    // ─────────────────────────────────────────────────────────────────────────
    // WEBHOOK HANDLER
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Verify Paystack webhook signature.
     */
    public function verifyWebhookSignature(Request $request): bool
    {
        $hash = hash_hmac('sha512', $request->getContent(), $this->secretKey);
        return hash_equals($hash, $request->header('X-Paystack-Signature', ''));
    }

    /**
     * Process incoming webhook event.
     * Idempotent — safe to call multiple times for same reference.
     */
    public function processWebhook(array $payload): bool
    {
        $event = $payload['event'] ?? '';
        $data  = $payload['data'] ?? [];

        Log::channel('daily')->info('Paystack Webhook', ['event' => $event, 'reference' => $data['reference'] ?? null]);

        return match ($event) {
            'charge.success' => $this->handleChargeSuccess($data),
            'charge.failed'  => $this->handleChargeFailed($data),
            'refund.processed' => $this->handleRefund($data),
            default          => true, // Acknowledge unknown events
        };
    }

    protected function handleChargeSuccess(array $data): bool
    {
        $reference = $data['reference'] ?? null;
        if (! $reference) return false;

        // Idempotency: check if already processed
        if (Repayment::where('paystack_reference', $reference)->exists()) {
            Log::info('Paystack: duplicate webhook ignored', ['ref' => $reference]);
            return true;
        }

        // Find payment link
        $paymentLink = PaymentLink::where('reference', $reference)->first();
        if (! $paymentLink) {
            Log::warning('Paystack: payment link not found', ['ref' => $reference]);
            return false;
        }

        $loan     = $paymentLink->loan;
        $borrower = $paymentLink->borrower;
        $amount   = $data['amount'] / 100; // Convert from kobo

        // Create repayment record
        $repayment = Repayment::create([
            'receipt_number'          => Repayment::generateReceiptNumber(),
            'loan_id'                 => $loan->id,
            'borrower_id'             => $borrower->id,
            'branch_id'               => $loan->branch_id,
            'collected_by'            => $loan->loan_officer_id, // system records
            'amount'                  => $amount,
            'payment_method'          => 'paystack',
            'paystack_reference'      => $reference,
            'paystack_transaction_id' => $data['id'] ?? null,
            'paystack_channel'        => $data['channel'] ?? null,
            'paystack_fees'           => ($data['fees'] ?? 0) / 100,
            'paystack_raw_response'   => $data,
            'paystack_status'         => 'success',
            'payment_date'            => now()->toDateString(),
            'payment_time'            => now()->toTimeString(),
            'status'                  => 'confirmed',
            'repayment_schedule_id'   => $paymentLink->repayment_schedule_id,
            'notes'                   => 'Online payment via Paystack',
        ]);

        // Allocate payment
        app(RepaymentAllocationService::class)->allocate($loan, $amount, $repayment);

        // Mark payment link paid
        $paymentLink->update(['status' => 'paid']);

        // Generate receipt PDF
        try {
            app(\App\Services\Loan\ReceiptService::class)->generate($repayment);
        } catch (\Exception $e) {
            Log::warning('Receipt generation failed', ['repayment' => $repayment->id, 'error' => $e->getMessage()]);
        }

        // Send notification
        try {
            app(\App\Services\Notification\NotificationService::class)
                ->send($borrower, 'repayment_received', $loan, ['amount' => $amount, 'receipt' => $repayment->receipt_number]);
        } catch (\Exception $e) {
            Log::warning('Notification failed after payment', ['error' => $e->getMessage()]);
        }

        Log::info('Paystack: repayment recorded', ['ref' => $reference, 'amount' => $amount]);
        return true;
    }

    protected function handleChargeFailed(array $data): bool
    {
        $reference = $data['reference'] ?? null;
        if (! $reference) return false;

        $paymentLink = PaymentLink::where('reference', $reference)->first();
        if ($paymentLink) {
            $paymentLink->update(['status' => 'cancelled']);
        }

        Log::info('Paystack: charge failed', ['ref' => $reference]);
        return true;
    }

    protected function handleRefund(array $data): bool
    {
        $reference = $data['transaction_reference'] ?? null;
        if (! $reference) return false;

        $repayment = Repayment::where('paystack_reference', $reference)->first();
        if ($repayment) {
            $repayment->update(['paystack_status' => 'reversed']);
            Log::info('Paystack: refund noted for repayment', ['repayment_id' => $repayment->id]);
        }
        return true;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // BANKS & MOBILE MONEY
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Get list of supported banks in Ghana for transfer/disbursement setup.
     */
    public function listBanks(): array
    {
        $response = $this->get('/bank?currency=GHS&country=ghana');
        return $response['data'] ?? [];
    }

    /**
     * Resolve bank account (verify account number and name).
     */
    public function resolveAccount(string $accountNumber, string $bankCode): array
    {
        return $this->get("/bank/resolve?account_number={$accountNumber}&bank_code={$bankCode}");
    }

    /**
     * Create transfer recipient (for disbursement via Paystack Transfer API).
     */
    public function createTransferRecipient(string $type, string $name, string $accountNumber, string $bankCode): array
    {
        return $this->post('/transferrecipient', [
            'type'           => $type, // 'ghipss' | 'mobile_money'
            'name'           => $name,
            'account_number' => $accountNumber,
            'bank_code'      => $bankCode,
            'currency'       => 'GHS',
        ]);
    }

    /**
     * Initiate transfer (disbursement).
     * Requires Paystack transfer feature enabled on your account.
     */
    public function initiateTransfer(float $amount, string $recipient, string $reason): array
    {
        return $this->post('/transfer', [
            'source'    => 'balance',
            'amount'    => (int) round($amount * 100),
            'recipient' => $recipient,
            'reason'    => $reason,
            'currency'  => 'GHS',
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // HTTP HELPERS
    // ─────────────────────────────────────────────────────────────────────────

    protected function post(string $endpoint, array $data): array
    {
        try {
            $response = Http::withToken($this->secretKey)
                ->acceptJson()
                ->post($this->baseUrl . $endpoint, $data);
            return $response->json() ?? ['status' => false, 'message' => 'Empty response'];
        } catch (\Exception $e) {
            Log::error('Paystack POST error', ['endpoint' => $endpoint, 'error' => $e->getMessage()]);
            return ['status' => false, 'message' => $e->getMessage()];
        }
    }

    protected function get(string $endpoint): array
    {
        try {
            $response = Http::withToken($this->secretKey)
                ->acceptJson()
                ->get($this->baseUrl . $endpoint);
            return $response->json() ?? ['status' => false, 'message' => 'Empty response'];
        } catch (\Exception $e) {
            Log::error('Paystack GET error', ['endpoint' => $endpoint, 'error' => $e->getMessage()]);
            return ['status' => false, 'message' => $e->getMessage()];
        }
    }

    protected function generateReference(string $prefix = 'KBF'): string
    {
        return strtoupper($prefix) . '-' . strtoupper(uniqid()) . '-' . now()->timestamp;
    }
}
