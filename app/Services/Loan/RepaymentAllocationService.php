<?php

namespace App\Services\Loan;

use App\Models\Loan;
use App\Models\Repayment;
use App\Models\RepaymentSchedule;
use App\Models\LedgerEntry;
use Illuminate\Support\Facades\DB;

/**
 * Allocates incoming payment to loan components:
 * Order: penalty → fees → interest → principal
 * (configurable via bigcash.loan.penalty_allocation_order)
 */
class RepaymentAllocationService
{
    protected LoanScheduleService $scheduleService;

    public function __construct(LoanScheduleService $scheduleService)
    {
        $this->scheduleService = $scheduleService;
    }

    /**
     * Process a repayment and allocate it to the appropriate loan components.
     * Returns allocated breakdown.
     */
    public function allocate(Loan $loan, float $amount, Repayment $repayment): array
    {
        $remaining = $amount;
        $allocated = [
            'principal_paid' => 0,
            'interest_paid'  => 0,
            'fees_paid'      => 0,
            'penalty_paid'   => 0,
        ];

        DB::transaction(function () use ($loan, &$remaining, &$allocated, $repayment) {
            $order = config('bigcash.loan.penalty_allocation_order',
                           ['penalty', 'fees', 'interest', 'principal']);

            // Get installments in order (overdue first, then oldest pending)
            $schedules = $loan->schedule()
                ->whereIn('status', ['overdue', 'partial', 'pending'])
                ->orderByRaw("CASE status WHEN 'overdue' THEN 1 WHEN 'partial' THEN 2 WHEN 'pending' THEN 3 ELSE 4 END")
                ->orderBy('due_date')
                ->get();

            foreach ($schedules as $schedule) {
                if ($remaining <= 0) break;

                foreach ($order as $component) {
                    if ($remaining <= 0) break;

                    $due  = (float) $schedule->{$component . '_due'};
                    $paid = (float) $schedule->{$component . '_paid'};
                    $owed = max(0, $due - $paid);

                    if ($owed <= 0) continue;

                    $pay = min($remaining, $owed);
                    $schedule->{$component . '_paid'} = $paid + $pay;
                    $schedule->total_paid             += $pay;
                    $allocated[$component . '_paid']  += $pay;
                    $remaining                        -= $pay;
                }

                // Update schedule status
                $totalDue   = (float) $schedule->total_due;
                $totalPaid  = (float) $schedule->total_paid;
                $tolerance  = 0.01;

                if ($totalPaid >= ($totalDue - $tolerance)) {
                    $schedule->status    = 'paid';
                    $schedule->paid_date = now()->toDateString();
                    $schedule->paid_at   = now();
                } elseif ($totalPaid > 0) {
                    $schedule->status = 'partial';
                }

                $schedule->save();

                // Link repayment to this schedule (first one processed)
                if ($repayment->repayment_schedule_id === null) {
                    $repayment->repayment_schedule_id = $schedule->id;
                }
            }

            // Update repayment breakdown
            $repayment->fill($allocated);
            $repayment->save();

            // Recalculate loan balances
            $this->scheduleService->recalculateLoanBalances($loan);

            // Check if loan is fully paid
            $loan->refresh();
            if ((float)$loan->total_outstanding <= 0.01) {
                $loan->update([
                    'status'                 => 'completed',
                    'actual_completion_date' => now()->toDateString(),
                    'is_overdue'             => false,
                ]);
                // Mark remaining pending schedules as paid
                $loan->schedule()->whereIn('status', ['pending', 'partial'])->update(['status' => 'paid']);
            } else {
                // Check if any schedule still overdue
                $hasOverdue = $loan->schedule()
                    ->where('status', 'overdue')
                    ->orWhere(fn ($q) => $q->whereIn('status', ['pending', 'partial'])->where('due_date', '<', today()))
                    ->exists();

                $loan->update(['is_overdue' => $hasOverdue, 'status' => $hasOverdue ? 'overdue' : 'active']);
            }

            // Post ledger entry
            LedgerEntry::create([
                'branch_id'    => $loan->branch_id,
                'loan_id'      => $loan->id,
                'repayment_id' => $repayment->id,
                'created_by'   => $repayment->collected_by,
                'entry_type'   => 'repayment_received',
                'debit_credit' => 'credit',
                'amount'       => $amount - $remaining, // actually applied
                'description'  => "Repayment #{$repayment->receipt_number} on Loan {$loan->loan_number}",
                'entry_date'   => $repayment->payment_date,
                'reference'    => $repayment->receipt_number,
            ]);
        });

        return $allocated;
    }

    /**
     * Reverse a repayment — undo all allocations.
     */
    public function reverse(Repayment $repayment, string $reason, int $reversedBy): void
    {
        DB::transaction(function () use ($repayment, $reason, $reversedBy) {
            $loan = $repayment->loan;

            // Reverse schedule allocations
            if ($repayment->repayment_schedule_id) {
                $schedule = $repayment->schedule;
                if ($schedule) {
                    $schedule->principal_paid = max(0, $schedule->principal_paid - $repayment->principal_paid);
                    $schedule->interest_paid  = max(0, $schedule->interest_paid  - $repayment->interest_paid);
                    $schedule->fees_paid      = max(0, $schedule->fees_paid      - $repayment->fees_paid);
                    $schedule->penalty_paid   = max(0, $schedule->penalty_paid   - $repayment->penalty_paid);
                    $schedule->total_paid     = max(0, $schedule->total_paid     - $repayment->amount);

                    $tolerance = 0.01;
                    if ($schedule->total_paid <= $tolerance) {
                        $schedule->status    = $schedule->due_date->isPast() ? 'overdue' : 'pending';
                        $schedule->paid_date = null;
                        $schedule->paid_at   = null;
                    } else {
                        $schedule->status = 'partial';
                    }
                    $schedule->save();
                }
            }

            // Mark repayment reversed
            $repayment->update([
                'status'          => 'reversed',
                'reversed_by'     => $reversedBy,
                'reversed_at'     => now(),
                'reversal_reason' => $reason,
            ]);

            // If loan was completed, reopen
            if ($loan->status === 'completed') {
                $loan->update(['status' => 'active', 'actual_completion_date' => null]);
            }

            // Recalculate
            $this->scheduleService->recalculateLoanBalances($loan);
        });
    }
}
