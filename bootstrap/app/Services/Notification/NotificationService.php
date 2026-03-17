<?php

namespace App\Services\Notification;

use App\Models\Borrower;
use App\Models\Loan;
use App\Models\NotificationLog;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class NotificationService
{
    protected SmsProviderInterface $smsProvider;

    public function __construct()
    {
        $this->smsProvider = $this->resolveSmsProvider();
    }

    // ─────────────────────────────────────────────────────────────────────────
    // TEMPLATE DEFINITIONS
    // ─────────────────────────────────────────────────────────────────────────

    protected array $templates = [
        'loan_approved' => [
            'subject' => 'Your Loan Application Has Been Approved',
            'sms'     => 'Dear {name}, your loan of {currency}{amount} ({loan_number}) has been APPROVED. Disbursement will be processed shortly. - {company}',
            'email'   => 'loan_approved',
        ],
        'loan_rejected' => [
            'subject' => 'Update on Your Loan Application',
            'sms'     => 'Dear {name}, we regret to inform you that your loan application {loan_number} was not approved at this time. Please visit your branch for more details. - {company}',
            'email'   => 'loan_rejected',
        ],
        'loan_disbursed' => [
            'subject' => 'Loan Disbursed - {loan_number}',
            'sms'     => 'Dear {name}, your loan of {currency}{amount} ({loan_number}) has been disbursed. First repayment of {currency}{installment} is due on {due_date}. - {company}',
            'email'   => 'loan_disbursed',
        ],
        'repayment_received' => [
            'subject' => 'Payment Received - Receipt {receipt}',
            'sms'     => 'Dear {name}, we received your payment of {currency}{amount} for loan {loan_number}. Receipt: {receipt}. Outstanding balance: {currency}{balance}. Thank you! - {company}',
            'email'   => 'repayment_received',
        ],
        'payment_due_reminder' => [
            'subject' => 'Payment Reminder - Due {due_date}',
            'sms'     => 'Dear {name}, your loan repayment of {currency}{amount} for loan {loan_number} is due on {due_date}. Please make your payment to avoid penalties. - {company}',
            'email'   => 'payment_due_reminder',
        ],
        'overdue_warning' => [
            'subject' => 'IMPORTANT: Overdue Loan Payment - {loan_number}',
            'sms'     => 'Dear {name}, your loan {loan_number} payment of {currency}{amount} is {days} day(s) overdue. Penalties are accruing. Please pay immediately or contact us. - {company}',
            'email'   => 'overdue_warning',
        ],
        'settlement_confirmation' => [
            'subject' => 'Loan Fully Settled - {loan_number}',
            'sms'     => 'Congratulations {name}! Your loan {loan_number} has been fully settled. We appreciate your business. - {company}',
            'email'   => 'settlement_confirmation',
        ],
        'otp_verification' => [
            'subject' => 'Your Login Verification Code',
            'sms'     => 'Your {company} verification code is: {otp}. Valid for 10 minutes. Do not share this code.',
            'email'   => 'otp_verification',
        ],
    ];

    // ─────────────────────────────────────────────────────────────────────────
    // PUBLIC METHODS
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Send a notification to a borrower.
     */
    public function send(Borrower $borrower, string $templateType, ?Loan $loan = null, array $extra = []): void
    {
        $template = $this->templates[$templateType] ?? null;
        if (! $template) {
            Log::warning("Notification template not found: {$templateType}");
            return;
        }

        $vars = $this->buildVars($borrower, $loan, $extra);

        // Send SMS
        if ($borrower->primary_phone) {
            $this->sendSms($borrower, $templateType, $template['sms'], $vars, $loan);
        }

        // Send Email
        if ($borrower->email) {
            $this->sendEmail($borrower, $templateType, $template['subject'], $template['email'], $vars, $loan);
        }
    }

    /**
     * Send OTP to user (staff or borrower).
     */
    public function sendOtp(User $user, string $otp): void
    {
        $vars = [
            '{name}'    => $user->name,
            '{otp}'     => $otp,
            '{company}' => config('bigcash.company.name'),
        ];

        $template = $this->templates['otp_verification'];
        $message  = str_replace(array_keys($vars), array_values($vars), $template['sms']);

        // Send email OTP
        try {
            Mail::to($user->email)->send(new \App\Mail\OtpMail($user, $otp));
            $this->logNotification(null, null, null, 'email', 'otp_verification', $user->email, $message, 'sent');
        } catch (\Exception $e) {
            Log::error('OTP email failed', ['user' => $user->id, 'error' => $e->getMessage()]);
        }

        // Optionally SMS
        if ($user->phone) {
            $this->smsProvider->send($user->phone, $message);
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // PRIVATE HELPERS
    // ─────────────────────────────────────────────────────────────────────────

    protected function sendSms(Borrower $borrower, string $type, string $template, array $vars, ?Loan $loan): void
    {
        $message = str_replace(array_keys($vars), array_values($vars), $template);

        try {
            $this->smsProvider->send($borrower->primary_phone, $message);
            $this->logNotification($borrower->id, $loan?->id, null, 'sms', $type, $borrower->primary_phone, $message, 'sent');
        } catch (\Exception $e) {
            Log::error('SMS failed', ['borrower' => $borrower->id, 'type' => $type, 'error' => $e->getMessage()]);
            $this->logNotification($borrower->id, $loan?->id, null, 'sms', $type, $borrower->primary_phone, $message, 'failed', $e->getMessage());
        }
    }

    protected function sendEmail(Borrower $borrower, string $type, string $subject, string $view, array $vars, ?Loan $loan): void
    {
        try {
            Mail::to($borrower->email)->send(new \App\Mail\LoanNotificationMail($subject, $view, $vars, $borrower, $loan));
            $this->logNotification($borrower->id, $loan?->id, null, 'email', $type, $borrower->email, $subject, 'sent');
        } catch (\Exception $e) {
            Log::error('Email failed', ['borrower' => $borrower->id, 'type' => $type, 'error' => $e->getMessage()]);
            $this->logNotification($borrower->id, $loan?->id, null, 'email', $type, $borrower->email, $subject, 'failed', $e->getMessage());
        }
    }

    protected function buildVars(Borrower $borrower, ?Loan $loan, array $extra): array
    {
        $currency = config('bigcash.company.currency_symbol', '₵');
        $company  = config('bigcash.company.name', 'Big Cash Finance');

        $vars = [
            '{name}'        => $borrower->display_name,
            '{full_name}'   => $borrower->full_name,
            '{phone}'       => $borrower->primary_phone,
            '{currency}'    => $currency,
            '{company}'     => $company,
        ];

        if ($loan) {
            $vars['{loan_number}'] = $loan->loan_number;
            $vars['{amount}']      = number_format($loan->disbursed_amount ?? $loan->approved_amount, 2);
            $vars['{balance}']     = number_format($loan->total_outstanding, 2);
            $vars['{installment}'] = number_format($loan->installment_amount, 2);
            $vars['{due_date}']    = $loan->next_due_date ?? 'N/A';
            $vars['{days}']        = $loan->days_past_due ?? 0;
        }

        foreach ($extra as $key => $value) {
            $vars['{' . $key . '}'] = $value;
        }

        return $vars;
    }

    protected function logNotification(?int $borrowerId, ?int $loanId, ?int $userId, string $channel, string $type, string $recipient, string $message, string $status, ?string $error = null): void
    {
        NotificationLog::create([
            'user_id'       => $userId,
            'borrower_id'   => $borrowerId,
            'loan_id'       => $loanId,
            'channel'       => $channel,
            'template_type' => $type,
            'recipient'     => $recipient,
            'message'       => $message,
            'status'        => $status,
            'error_message' => $error,
        ]);
    }

    protected function resolveSmsProvider(): SmsProviderInterface
    {
        $provider = config('bigcash.sms.provider', 'log');
        return match ($provider) {
            'arkesel'   => new ArkeselSmsProvider(),
            'hubtel'    => new HubtelSmsProvider(),
            'mnotify'   => new MnotifySmsProvider(),
            default     => new LogSmsProvider(),
        };
    }
}
