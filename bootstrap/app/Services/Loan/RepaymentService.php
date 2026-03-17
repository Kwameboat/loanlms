<?php

namespace App\Services\Loan;

use App\Models\{Loan, Repayment, RepaymentSchedule, LedgerEntry, Penalty};
use App\Services\Notification\NotificationService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class RepaymentService
{
    public function __construct(private NotificationService $notifier) {}

    /**
     * Record a repayment and allocate to schedule.
     * Allocation order: penalty → fees → interest → principal
     */
    public function recordRepayment(Loan $loan, array $data, int $collectedBy): Repayment
    {
        return DB::transaction(function () use ($loan, $data, $collectedBy) {
            $amount = (float)$data['amount'];

            // Allocate across components
            $allocation = $this->allocatePayment($loan, $amount);

            $repayment = Repayment::create([
                'receipt_number'           => $this->generateReceiptNumber(),
                'loan_id'                  => $loan->id,
                'borrower_id'              => $loan->borrower_id,
                'branch_id'                => $loan->branch_id,
                'collected_by'             => $collectedBy,
                'amount'                   => $amount,
                'principal_paid'           => $allocation['principal'],
                'interest_paid'            => $allocation['interest'],
                'fees_paid'                => $allocation['fees'],
                'penalty_paid'             => $allocation['penalty'],
                'payment_method'           => $data['payment_method'],
                'payment_reference'        => $data['payment_reference'] ?? null,
                'mobile_money_number'      => $data['mobile_money_number'] ?? null,
                'mobile_money_provider'    => $data['mobile_money_provider'] ?? null,
                'bank_name'                => $data['bank_name'] ?? null,
                'cheque_number'            => $data['cheque_number'] ?? null,
                'paystack_reference'       => $data['paystack_reference'] ?? null,
                'paystack_transaction_id'  => $data['paystack_transaction_id'] ?? null,
                'paystack_channel'         => $data['paystack_channel'] ?? null,
                'paystack_fees'            => $data['paystack_fees'] ?? 0,
                'paystack_raw_response'    => isset($data['paystack_raw']) ? $data['paystack_raw'] : null,
                'paystack_status'          => $data['paystack_status'] ?? null,
                'payment_date'             => $data['payment_date'] ?? now()->toDateString(),
                'payment_time'             => now()->format('H:i:s'),
                'status'                   => 'confirmed',
                'notes'                    => $data['notes'] ?? null,
                'repayment_schedule_id'    => $allocation['schedule_id'] ?? null,
            ]);

            // Update schedule rows
            $this->applyAllocationToSchedule($loan, $allocation);

            // Update loan balances
            $this->updateLoanBalances($loan, $allocation);

            // Mark penalties as paid
            if ($allocation['penalty'] > 0) {
                $this->applyPenaltyPayments($loan, $allocation['penalty']);
            }

            // Check if loan is now complete
            $this->checkCompletion($loan);

            // Ledger entry
            LedgerEntry::create([
                'branch_id'   => $loan->branch_id,
                'loan_id'     => $loan->id,
                'repayment_id'=> $repayment->id,
                'created_by'  => $collectedBy,
                'entry_type'  => 'repayment_received',
                'debit_credit'=> 'credit',
                'amount'      => $amount,
                'description' => "Repayment received — {$loan->loan_number} — {$repayment->receipt_number}",
                'entry_date'  => $repayment->payment_date,
                'reference'   => $repayment->receipt_number,
            ]);

            // Notify borrower
            $this->notifier->sendRepaymentConfirmation($loan, $repayment);

            return $repayment;
        });
    }

    /**
     * Allocate a payment across: penalty → fees → interest → principal
     */
    private function allocatePayment(Loan $loan, float $amount): array
    {
        $remaining = $amount;
        $allocation = [
            'penalty'     => 0,
            'fees'        => 0,
            'interest'    => 0,
            'principal'   => 0,
            'schedule_id' => null,
        ];

        // 1. Penalty first
        $outstanding_penalty = (float)$loan->outstanding_penalty;
        if ($remaining > 0 && $outstanding_penalty > 0) {
            $pay = min($remaining, $outstanding_penalty);
            $allocation['penalty'] = $pay;
            $remaining -= $pay;
        }

        // Find the next due schedule row(s)
        $scheduleRows = $loan->schedule()
            ->whereIn('status', ['pending', 'partial', 'overdue'])
            ->orderBy('due_date')
            ->get();

        foreach ($scheduleRows as $row) {
            if ($remaining <= 0) break;

            // 2. Fees
            $feesDue = (float)($row->fees_due - $row->fees_paid);
            if ($remaining > 0 && $feesDue > 0) {
                $pay = min($remaining, $feesDue);
                $allocation['fees'] += $pay;
                $remaining -= $pay;
            }

            // 3. Interest
            $interestDue = (float)($row->interest_due - $row->interest_paid);
            if ($remaining > 0 && $interestDue > 0) {
                $pay = min($remaining, $interestDue);
                $allocation['interest'] += $pay;
                $remaining -= $pay;
            }

            // 4. Principal
            $principalDue = (float)($row->principal_due - $row->principal_paid);
            if ($remaining > 0 && $principalDue > 0) {
                $pay = min($remaining, $principalDue);
                $allocation['principal'] += $pay;
                $remaining -= $pay;
            }

            if ($allocation['schedule_id'] === null) {
                $allocation['schedule_id'] = $row->id;
            }
        }

        return $allocation;
    }

    /**
     * Apply allocation amounts to schedule rows.
     */
    private function applyAllocationToSchedule(Loan $loan, array $allocation): void
    {
        $remaining = [
            'fees'      => $allocation['fees'],
            'interest'  => $allocation['interest'],
            'principal' => $allocation['principal'],
        ];

        $scheduleRows = $loan->schedule()
            ->whereIn('status', ['pending', 'partial', 'overdue'])
            ->orderBy('due_date')
            ->get();

        foreach ($scheduleRows as $row) {
            $changed = false;

            foreach (['fees', 'interest', 'principal'] as $component) {
                if ($remaining[$component] <= 0) continue;
                $due  = (float)($row->{$component . '_due'} - $row->{$component . '_paid'});
                $pay  = min($remaining[$component], $due);
                if ($pay > 0) {
                    $row->increment($component . '_paid', $pay);
                    $row->increment('total_paid', $pay);
                    $remaining[$component] -= $pay;
                    $changed = true;
                }
            }

            if ($changed) {
                $row->refresh();
                $totalBalance = $row->total_due - $row->total_paid;
                if ($totalBalance <= 0.01) {
                    $row->update([
                        'status'    => 'paid',
                        'paid_date' => now()->toDateString(),
                        'paid_at'   => now(),
                    ]);
                } else {
                    $row->update(['status' => 'partial']);
                }
            }
        }
    }

    /**
     * Update loan-level balance fields.
     */
    private function updateLoanBalances(Loan $loan, array $allocation): void
    {
        $loan->decrement('outstanding_principal', $allocation['principal']);
        $loan->decrement('outstanding_interest',  $allocation['interest']);
        $loan->decrement('outstanding_fees',      $allocation['fees']);
        $loan->decrement('outstanding_penalty',   $allocation['penalty']);

        $totalPaid = $allocation['principal'] + $allocation['interest']
                   + $allocation['fees'] + $allocation['penalty'];

        $loan->increment('total_paid', $totalPaid);
        $loan->increment('total_interest_paid', $allocation['interest']);
        $loan->increment('total_penalty_paid',  $allocation['penalty']);
        $loan->decrement('total_outstanding', $totalPaid);

        // Reset overdue if all due installments are now paid
        $hasOverdue = $loan->schedule()
            ->where('is_overdue', true)
            ->whereIn('status', ['pending', 'partial', 'overdue'])
            ->exists();

        if (!$hasOverdue && in_array($loan->fresh()->status, ['overdue'])) {
            $loan->update([
                'status'       => 'active',
                'is_overdue'   => false,
                'days_past_due'=> 0,
            ]);
        }
    }

    private function applyPenaltyPayments(Loan $loan, float $amount): void
    {
        $penalties = $loan->penalties()
            ->where('status', 'outstanding')
            ->orderBy('accrual_date')
            ->get();

        $remaining = $amount;
        foreach ($penalties as $penalty) {
            if ($remaining <= 0) break;
            $due = (float)$penalty->amount - (float)$penalty->paid_amount;
            $pay = min($remaining, $due);
            $penalty->increment('paid_amount', $pay);
            $remaining -= $pay;
            if ($penalty->paid_amount >= $penalty->amount) {
                $penalty->update(['status' => 'paid']);
            }
        }
    }

    /**
     * Check if all schedule rows are paid → mark loan complete.
     */
    private function checkCompletion(Loan $loan): void
    {
        $loan->refresh();
        $anyUnpaid = $loan->schedule()
            ->whereIn('status', ['pending', 'partial', 'overdue'])
            ->exists();

        if (!$anyUnpaid && $loan->total_outstanding <= 0.01) {
            $loan->update([
                'status'                 => 'completed',
                'actual_completion_date' => now()->toDateString(),
                'outstanding_principal'  => 0,
                'outstanding_interest'   => 0,
                'outstanding_fees'       => 0,
                'outstanding_penalty'    => 0,
                'total_outstanding'      => 0,
            ]);

            LedgerEntry::create([
                'branch_id'   => $loan->branch_id,
                'loan_id'     => $loan->id,
                'created_by'  => 1,
                'entry_type'  => 'repayment_received',
                'debit_credit'=> 'credit',
                'amount'      => 0,
                'description' => "Loan fully repaid and closed — {$loan->loan_number}",
                'entry_date'  => now()->toDateString(),
                'reference'   => $loan->loan_number,
            ]);
        }
    }

    /**
     * Reverse a repayment (with permission check done in controller).
     */
    public function reverseRepayment(Repayment $repayment, string $reason, int $userId): Repayment
    {
        return DB::transaction(function () use ($repayment, $reason, $userId) {
            $loan = $repayment->loan;

            // Restore loan balances
            $loan->increment('outstanding_principal', $repayment->principal_paid);
            $loan->increment('outstanding_interest',  $repayment->interest_paid);
            $loan->increment('outstanding_fees',      $repayment->fees_paid);
            $loan->increment('outstanding_penalty',   $repayment->penalty_paid);
            $loan->decrement('total_paid', $repayment->amount);
            $loan->decrement('total_interest_paid', $repayment->interest_paid);
            $loan->decrement('total_penalty_paid',  $repayment->penalty_paid);
            $loan->increment('total_outstanding', $repayment->amount);

            // Revert schedule
            if ($repayment->repayment_schedule_id) {
                $row = RepaymentSchedule::find($repayment->repayment_schedule_id);
                if ($row) {
                    $row->decrement('principal_paid', $repayment->principal_paid);
                    $row->decrement('interest_paid',  $repayment->interest_paid);
                    $row->decrement('fees_paid',      $repayment->fees_paid);
                    $row->decrement('penalty_paid',   $repayment->penalty_paid);
                    $row->decrement('total_paid', $repayment->amount);
                    $row->update(['status' => $row->due_date < now() ? 'overdue' : 'pending']);
                }
            }

            $repayment->update([
                'status'         => 'reversed',
                'reversed_by'    => $userId,
                'reversed_at'    => now(),
                'reversal_reason'=> $reason,
            ]);

            // Restore loan status to active/overdue
            if ($loan->fresh()->status === 'completed') {
                $loan->update(['status' => 'active']);
            }

            return $repayment;
        });
    }

    /**
     * Process bulk repayment upload from CSV.
     */
    public function processBulkUpload(array $rows, int $userId): array
    {
        $results = ['success' => 0, 'failed' => 0, 'errors' => []];
        $batchRef = 'BULK-' . now()->format('YmdHis');

        foreach ($rows as $idx => $row) {
            try {
                $loan = Loan::where('loan_number', $row['loan_number'])->firstOrFail();
                $repayment = $this->recordRepayment($loan, array_merge($row, [
                    'payment_method' => $row['payment_method'] ?? 'cash',
                ]), $userId);
                $repayment->update(['bulk_upload_batch' => $batchRef]);
                $results['success']++;
            } catch (\Exception $e) {
                $results['failed']++;
                $results['errors'][] = "Row {$idx}: {$e->getMessage()}";
            }
        }

        return $results;
    }

    private function generateReceiptNumber(): string
    {
        $year  = date('Y');
        $count = Repayment::whereYear('created_at', $year)->count() + 1;
        return 'RCP-' . $year . '-' . str_pad($count, 6, '0', STR_PAD_LEFT);
    }
}
