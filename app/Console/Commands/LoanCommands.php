<?php

namespace App\Console\Commands;

use App\Models\Loan;
use App\Models\RepaymentSchedule;
use App\Services\Loan\LoanScheduleService;
use App\Services\Notification\NotificationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

// ─── Mark Overdue Loans ───────────────────────────────────────────────────────

class MarkOverdueLoans extends Command
{
    protected $signature   = 'loans:mark-overdue';
    protected $description = 'Scan all active loans and mark overdue installments and loans';

    public function handle(LoanScheduleService $scheduleService): int
    {
        $this->info('Scanning loans for overdue status...');
        $today = today();

        $activeLoans = Loan::whereIn('status', ['active', 'overdue', 'rescheduled'])
            ->with(['schedule', 'loanProduct'])
            ->get();

        $markedOverdue   = 0;
        $penaltiesPosted = 0;

        foreach ($activeLoans as $loan) {
            // Update overdue schedule items
            $overdueSchedules = $loan->schedule()
                ->whereIn('status', ['pending', 'partial'])
                ->where('due_date', '<', $today)
                ->get();

            foreach ($overdueSchedules as $schedule) {
                $daysOverdue = (int) $schedule->due_date->diffInDays($today);
                $schedule->update([
                    'is_overdue'   => true,
                    'days_past_due'=> $daysOverdue,
                    'status'       => 'overdue',
                ]);
            }

            $hasOverdue = $overdueSchedules->count() > 0
                || $loan->schedule()->where('status', 'overdue')->exists();

            if ($hasOverdue) {
                $maxDPD = $loan->schedule()
                    ->where('status', 'overdue')
                    ->max('days_past_due') ?? 0;

                $loan->update([
                    'is_overdue'    => true,
                    'status'        => 'overdue',
                    'days_past_due' => $maxDPD,
                    'overdue_since' => $loan->overdue_since ?? $today,
                ]);
                $markedOverdue++;

                // Accrue penalties
                try {
                    $scheduleService->accrueOverduePenalties($loan);
                    $penaltiesPosted++;
                } catch (\Exception $e) {
                    Log::error("Penalty accrual failed for loan {$loan->id}", ['error' => $e->getMessage()]);
                }
            } elseif ($loan->status === 'overdue') {
                // All caught up — revert to active
                $loan->update(['is_overdue' => false, 'status' => 'active', 'days_past_due' => 0]);
            }
        }

        $this->info("Done. Marked overdue: {$markedOverdue}, Penalties posted: {$penaltiesPosted}");
        Log::info("MarkOverdueLoans: overdue={$markedOverdue}, penalties={$penaltiesPosted}");

        return Command::SUCCESS;
    }
}

// ─── Send Due Reminders ───────────────────────────────────────────────────────

class SendDueReminders extends Command
{
    protected $signature   = 'loans:send-reminders';
    protected $description = 'Send SMS/email reminders for loans due in the configured number of days';

    public function handle(NotificationService $notificationService): int
    {
        $daysBefore = (int) \App\Models\Setting::get('reminder_days_before', 3);
        $targetDate = today()->addDays($daysBefore);

        $this->info("Sending reminders for loans due on {$targetDate->toDateString()}...");

        $schedules = RepaymentSchedule::with(['loan.borrower', 'loan.loanProduct'])
            ->where('due_date', $targetDate->toDateString())
            ->whereIn('status', ['pending', 'partial'])
            ->whereHas('loan', fn($q) => $q->whereIn('status', ['active', 'overdue']))
            ->get();

        $sent = 0;
        foreach ($schedules as $schedule) {
            try {
                $notificationService->send(
                    $schedule->loan->borrower,
                    'payment_due_reminder',
                    $schedule->loan,
                    [
                        'amount'   => number_format($schedule->balance_due, 2),
                        'due_date' => $schedule->due_date->format('d/m/Y'),
                    ]
                );
                $sent++;
            } catch (\Exception $e) {
                Log::warning("Reminder failed for schedule {$schedule->id}", ['error' => $e->getMessage()]);
            }
        }

        $this->info("Reminders sent: {$sent}");
        return Command::SUCCESS;
    }
}

// ─── Send Overdue Warnings ────────────────────────────────────────────────────

class SendOverdueWarnings extends Command
{
    protected $signature   = 'loans:send-overdue-warnings';
    protected $description = 'Send overdue warning notifications to borrowers with overdue loans';

    public function handle(NotificationService $notificationService): int
    {
        $this->info('Sending overdue warnings...');

        $overdueLoans = Loan::with('borrower')
            ->where('is_overdue', true)
            ->whereIn('days_past_due', [1, 7, 14, 30, 60]) // notify at key milestones
            ->get();

        $sent = 0;
        foreach ($overdueLoans as $loan) {
            try {
                $notificationService->send(
                    $loan->borrower,
                    'overdue_warning',
                    $loan,
                    [
                        'amount' => number_format($loan->total_outstanding, 2),
                        'days'   => $loan->days_past_due,
                    ]
                );
                $sent++;
            } catch (\Exception $e) {
                Log::warning("Overdue warning failed for loan {$loan->id}", ['error' => $e->getMessage()]);
            }
        }

        $this->info("Overdue warnings sent: {$sent}");
        return Command::SUCCESS;
    }
}

// ─── Clean Expired Payment Links ─────────────────────────────────────────────

class CleanExpiredPaymentLinks extends Command
{
    protected $signature   = 'paystack:clean-expired-links';
    protected $description = 'Mark expired Paystack payment links as expired';

    public function handle(): int
    {
        $count = \App\Models\PaymentLink::where('status', 'pending')
            ->where('expires_at', '<', now())
            ->update(['status' => 'expired']);

        $this->info("Marked {$count} payment links as expired.");
        return Command::SUCCESS;
    }
}
