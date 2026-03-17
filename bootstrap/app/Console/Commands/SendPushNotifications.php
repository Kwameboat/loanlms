<?php

namespace App\Console\Commands;

use App\Models\Loan;
use App\Models\RepaymentSchedule;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * SendPushNotifications
 *
 * Sends push notifications for:
 *   - Payment due reminders (X days before)
 *   - Overdue warnings
 *   - Loan approval / disbursement (triggered from LoanController, not here)
 *
 * Runs via cPanel cron through Laravel scheduler.
 */
class SendPushNotifications extends Command
{
    protected $signature   = 'push:send-reminders {--type=all : due_reminders|overdue_warnings|all}';
    protected $description = 'Send push notifications for payment reminders and overdue warnings';

    public function handle(): int
    {
        $type = $this->option('type');

        if ($type === 'all' || $type === 'due_reminders') {
            $this->sendDueReminders();
        }

        if ($type === 'all' || $type === 'overdue_warnings') {
            $this->sendOverdueWarnings();
        }

        return Command::SUCCESS;
    }

    protected function sendDueReminders(): void
    {
        $daysBefore = (int) \App\Models\Setting::get('reminder_days_before', 3);
        $targetDate = today()->addDays($daysBefore)->toDateString();

        $schedules = RepaymentSchedule::with(['loan.borrower.userAccount'])
            ->where('due_date', $targetDate)
            ->whereIn('status', ['pending', 'partial'])
            ->whereHas('loan', fn($q) => $q->whereIn('status', ['active', 'overdue']))
            ->get();

        $sent = 0;
        foreach ($schedules as $schedule) {
            $borrower = $schedule->loan->borrower;
            $user     = $borrower?->userAccount;
            if (! $user) continue;

            $balanceDue = max(0, $schedule->total_due - $schedule->total_paid);

            $this->pushToUser($user->id, [
                'title'          => 'Payment Due in ' . $daysBefore . ' Days',
                'body'           => "Your repayment of ₵" . number_format($balanceDue, 2) . " for loan {$schedule->loan->loan_number} is due on " . $schedule->due_date->format('d M Y') . ".",
                'icon'           => '/icons/icon-192x192.png',
                'badge'          => '/icons/icon-72x72.png',
                'tag'            => 'due-' . $schedule->id,
                'type'           => 'payment_due',
                'action_url'     => '/portal/loans/' . $schedule->loan_id,
                'requires_interaction' => false,
            ]);

            $sent++;
        }

        $this->info("Due reminders sent: {$sent}");
        Log::info("SendPushNotifications: due_reminders={$sent}");
    }

    protected function sendOverdueWarnings(): void
    {
        $milestones = [1, 7, 14, 30, 60];

        $loans = Loan::with(['borrower.userAccount'])
            ->where('is_overdue', true)
            ->whereIn('days_past_due', $milestones)
            ->get();

        $sent = 0;
        foreach ($loans as $loan) {
            $user = $loan->borrower?->userAccount;
            if (! $user) continue;

            $this->pushToUser($user->id, [
                'title'          => 'OVERDUE: Loan ' . $loan->loan_number,
                'body'           => "Your loan payment is {$loan->days_past_due} day(s) overdue. Outstanding: ₵" . number_format($loan->total_outstanding, 2) . ". Please pay immediately to avoid further penalties.",
                'icon'           => '/icons/icon-192x192.png',
                'badge'          => '/icons/icon-72x72.png',
                'tag'            => 'overdue-' . $loan->id,
                'type'           => 'overdue_warning',
                'action_url'     => '/portal/loans/' . $loan->id,
                'requires_interaction' => true,
            ]);

            $sent++;
        }

        $this->info("Overdue warnings sent: {$sent}");
        Log::info("SendPushNotifications: overdue_warnings={$sent}");
    }

    /**
     * Send a push notification to all subscriptions of a user.
     */
    protected function pushToUser(int $userId, array $payload): void
    {
        $subscriptions = DB::table('push_subscriptions')
            ->where('user_id', $userId)
            ->where('is_active', true)
            ->get();

        if ($subscriptions->isEmpty()) return;

        $vapidPublic  = config('webpush.vapid.public_key');
        $vapidPrivate = config('webpush.vapid.private_key');
        $vapidSubject = 'mailto:' . config('bigcash.company.email', 'noreply@bigcash.com');

        if (! $vapidPublic || ! $vapidPrivate) {
            Log::warning('SendPushNotifications: VAPID keys not configured');
            return;
        }

        if (! class_exists('\Minishlink\WebPush\WebPush')) {
            // Log payload if web-push library not installed
            Log::info('Push notification (library not installed)', ['user' => $userId, 'payload' => $payload]);
            return;
        }

        $webPush = new \Minishlink\WebPush\WebPush([
            'VAPID' => [
                'subject'    => $vapidSubject,
                'publicKey'  => $vapidPublic,
                'privateKey' => $vapidPrivate,
            ],
        ]);

        foreach ($subscriptions as $sub) {
            $subscription = \Minishlink\WebPush\Subscription::create([
                'endpoint'        => $sub->endpoint,
                'publicKey'       => $sub->p256dh_key,
                'authToken'       => $sub->auth_token,
                'contentEncoding' => 'aesgcm',
            ]);

            $webPush->sendOneNotification($subscription, json_encode($payload));
        }

        // Handle responses
        foreach ($webPush->flush() as $result) {
            if ($result->isSubscriptionExpired()) {
                DB::table('push_subscriptions')
                    ->where('endpoint', $result->getEndpoint())
                    ->update(['is_active' => false]);
            }
        }
    }
}
