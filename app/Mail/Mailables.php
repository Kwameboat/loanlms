<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

// ─── Generic Loan Notification ────────────────────────────────────────────────

class LoanNotificationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $emailSubject,
        public string $templateView,
        public array  $vars,
        public $borrower,
        public $loan = null
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->emailSubject);
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.loan-notification',
            with: [
                'vars'     => $this->vars,
                'borrower' => $this->borrower,
                'loan'     => $this->loan,
                'company'  => [
                    'name'   => \App\Models\Setting::get('company_name', config('bigcash.company.name')),
                    'phone'  => \App\Models\Setting::get('company_phone', ''),
                    'email'  => \App\Models\Setting::get('company_email', ''),
                    'logo'   => \App\Models\Setting::get('company_logo', ''),
                ],
            ]
        );
    }
}

// ─── OTP Mail ─────────────────────────────────────────────────────────────────

class OtpMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public $user, public string $otp) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Your Login Verification Code — ' . config('bigcash.company.name'));
    }

    public function content(): Content
    {
        return new Content(view: 'emails.otp', with: [
            'user'    => $this->user,
            'otp'     => $this->otp,
            'company' => \App\Models\Setting::get('company_name', config('bigcash.company.name')),
        ]);
    }
}

// ─── Password Reset Mail ──────────────────────────────────────────────────────

class PasswordResetMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public $user, public string $token) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Reset Your Password');
    }

    public function content(): Content
    {
        $resetUrl = url(route('password.reset', [
            'token' => $this->token,
            'email' => $this->user->email,
        ], false));

        return new Content(view: 'emails.password-reset', with: [
            'user'     => $this->user,
            'resetUrl' => $resetUrl,
            'company'  => \App\Models\Setting::get('company_name', config('bigcash.company.name')),
        ]);
    }
}

// ─── Borrower Welcome Mail ────────────────────────────────────────────────────

class BorrowerWelcomeMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public $borrower,
        public $user,
        public string $tempPassword
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Welcome to ' . \App\Models\Setting::get('company_name', 'Big Cash Finance') . ' — Your Portal Access');
    }

    public function content(): Content
    {
        return new Content(view: 'emails.borrower-welcome', with: [
            'borrower'     => $this->borrower,
            'user'         => $this->user,
            'tempPassword' => $this->tempPassword,
            'loginUrl'     => route('login'),
            'company'      => \App\Models\Setting::get('company_name', config('bigcash.company.name')),
        ]);
    }
}
