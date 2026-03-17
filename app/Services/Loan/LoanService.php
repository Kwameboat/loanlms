<?php

namespace App\Services\Loan;

use App\Models\{Loan, LoanProduct, Borrower, RepaymentSchedule, LoanStatusHistory, LedgerEntry};
use App\Services\Notification\NotificationService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class LoanService
{
    public function __construct(
        private LoanCalculatorService $calculator,
        private NotificationService   $notifier
    ) {}

    /**
     * Create a new loan application.
     */
    public function createApplication(array $data, int $createdBy): Loan
    {
        return DB::transaction(function () use ($data, $createdBy) {
            $product = LoanProduct::findOrFail($data['loan_product_id']);

            $loan = Loan::create([
                'loan_number'        => Loan::generateLoanNumber(),
                'branch_id'          => $data['branch_id'],
                'borrower_id'        => $data['borrower_id'],
                'loan_product_id'    => $product->id,
                'loan_officer_id'    => $data['loan_officer_id'] ?? $createdBy,
                'created_by'         => $createdBy,
                'loan_purpose'       => $data['loan_purpose'] ?? null,
                'requested_amount'   => $data['requested_amount'],
                'term_months'        => $data['term_months'],
                'repayment_frequency'=> $data['repayment_frequency'],
                'interest_type'      => $product->interest_type,
                'interest_rate'      => $product->interest_rate,
                'processing_fee_amount' => $product->calculateProcessingFee($data['requested_amount']),
                'insurance_fee_amount'  => $product->calculateInsuranceFee($data['requested_amount']),
                'admin_fee_amount'      => $product->calculateAdminFee($data['requested_amount']),
                'existing_debt_monthly' => $data['existing_debt_monthly'] ?? 0,
                'application_date'   => now()->toDateString(),
                'status'             => 'draft',
            ]);

            // Compute DTI
            $this->updateCreditMetrics($loan);

            $this->logStatusChange($loan, null, 'draft', 'Loan application created', $createdBy);

            return $loan;
        });
    }

    /**
     * Transition loan status with full audit trail.
     */
    public function transitionStatus(Loan $loan, string $toStatus, string $note = '', int $userId = null): Loan
    {
        $userId    = $userId ?? Auth::id();
        $fromStatus = $loan->status;

        if (!$loan->canTransitionTo($toStatus)) {
            throw new \Exception("Cannot transition loan from [{$fromStatus}] to [{$toStatus}].");
        }

        DB::transaction(function () use ($loan, $toStatus, $fromStatus, $note, $userId) {
            $updates = ['status' => $toStatus];

            switch ($toStatus) {
                case 'submitted':
                    break;

                case 'recommended':
                    $updates['recommended_by'] = $userId;
                    $updates['recommended_at'] = now();
                    $updates['recommendation_note'] = $note;
                    break;

                case 'approved':
                    if ($loan->approved_by) {
                        // Second approval
                        $updates['second_approver_id']  = $userId;
                        $updates['second_approved_at']  = now();
                    } else {
                        $updates['approved_by']   = $userId;
                        $updates['approved_at']   = now();
                        $updates['approval_note'] = $note;
                        $updates['approved_amount'] = $loan->requested_amount;
                    }
                    break;

                case 'rejected':
                    $updates['rejected_by']       = $userId;
                    $updates['rejected_at']        = now();
                    $updates['rejection_reason']   = $note;
                    break;

                case 'active':
                    $updates['is_overdue'] = false;
                    $updates['days_past_due'] = 0;
                    break;

                case 'completed':
                    $updates['actual_completion_date'] = now()->toDateString();
                    $updates['outstanding_principal']  = 0;
                    $updates['outstanding_interest']   = 0;
                    $updates['outstanding_fees']       = 0;
                    $updates['outstanding_penalty']    = 0;
                    $updates['total_outstanding']      = 0;
                    break;
            }

            $loan->update($updates);
            $this->logStatusChange($loan, $fromStatus, $toStatus, $note, $userId);
        });

        // Send notification
        $this->notifier->sendLoanStatusNotification($loan, $toStatus);

        return $loan->fresh();
    }

    /**
     * Disburse a loan — generate schedule, update balances, record ledger entry.
     */
    public function disburse(Loan $loan, array $disbursementData, int $userId): Loan
    {
        if (!in_array($loan->status, ['approved'])) {
            throw new \Exception('Loan must be approved before disbursement.');
        }

        return DB::transaction(function () use ($loan, $disbursementData, $userId) {
            $amount = $disbursementData['amount'] ?? $loan->approved_amount;
            $firstRepaymentDate = $disbursementData['first_repayment_date']
                ?? now()->addMonth()->toDateString();

            // Run calculator
            $product = $loan->loanProduct;
            $annualRate = $product->interest_period === 'per_month'
                ? $product->interest_rate * 12
                : $product->interest_rate;

            $calc = $this->calculator->calculate(
                $amount,
                (float) $annualRate,
                $loan->term_months,
                $loan->interest_type,
                $loan->repayment_frequency,
                $firstRepaymentDate,
                (float) $loan->processing_fee_amount,
                (float) $loan->insurance_fee_amount,
                (float) $loan->admin_fee_amount
            );

            // Determine maturity date from last schedule row
            $lastRow     = end($calc['schedule']);
            $maturityDate = $lastRow['due_date'];
            reset($calc['schedule']);

            // Update loan record
            $loan->update([
                'disbursed_amount'        => $amount,
                'total_interest'          => $calc['total_interest'],
                'total_repayable'         => $calc['total_repayable'],
                'installment_amount'      => $calc['installment_amount'],
                'outstanding_principal'   => $amount,
                'outstanding_interest'    => $calc['total_interest'],
                'outstanding_fees'        => $calc['total_fees'],
                'outstanding_penalty'     => 0,
                'total_outstanding'       => $calc['total_repayable'] + $calc['total_fees'],
                'first_repayment_date'    => $firstRepaymentDate,
                'disbursement_date'       => now()->toDateString(),
                'maturity_date'           => $maturityDate,
                'status'                  => 'disbursed',
                'disbursement_method'     => $disbursementData['method'] ?? 'cash',
                'disbursement_bank'       => $disbursementData['bank'] ?? null,
                'disbursement_account'    => $disbursementData['account'] ?? null,
                'disbursement_reference'  => $disbursementData['reference'] ?? null,
                'disbursed_by'            => $userId,
                'accountant_verified_by'  => $disbursementData['accountant_id'] ?? null,
            ]);

            // Build schedule
            $this->buildSchedule($loan, $calc['schedule']);

            // Log status history
            $this->logStatusChange($loan, 'approved', 'disbursed', 'Loan disbursed', $userId);

            // Ledger entry
            LedgerEntry::create([
                'branch_id'   => $loan->branch_id,
                'loan_id'     => $loan->id,
                'created_by'  => $userId,
                'entry_type'  => 'loan_disbursement',
                'debit_credit'=> 'debit',
                'amount'      => $amount,
                'description' => "Loan disbursed to {$loan->borrower->display_name} — {$loan->loan_number}",
                'entry_date'  => now()->toDateString(),
                'reference'   => $loan->loan_number,
            ]);

            return $loan->fresh();
        });
    }

    /**
     * Build or rebuild the repayment schedule for a loan.
     */
    public function buildSchedule(Loan $loan, array $scheduleRows): void
    {
        // Remove existing schedule (for reschedules)
        $loan->schedule()->delete();

        foreach ($scheduleRows as $row) {
            RepaymentSchedule::create(array_merge($row, ['loan_id' => $loan->id]));
        }

        // Transition to active
        if ($loan->status === 'disbursed') {
            $loan->update(['status' => 'active']);
            $this->logStatusChange($loan, 'disbursed', 'active', 'Repayment schedule created', Auth::id() ?? 1);
        }
    }

    /**
     * Reschedule a loan — rebuild schedule from a new start date.
     */
    public function reschedule(Loan $loan, array $data, int $userId): Loan
    {
        return DB::transaction(function () use ($loan, $data, $userId) {
            $newFirstDate    = $data['first_repayment_date'];
            $outstandingPrincipal = $data['outstanding_principal'] ?? $loan->outstanding_principal;
            $newTerm         = $data['new_term_months'];
            $product         = $loan->loanProduct;

            $annualRate = $product->interest_period === 'per_month'
                ? $product->interest_rate * 12
                : $product->interest_rate;

            $calc = $this->calculator->calculate(
                $outstandingPrincipal,
                (float)$annualRate,
                $newTerm,
                $loan->interest_type,
                $loan->repayment_frequency,
                $newFirstDate
            );

            $lastRow = end($calc['schedule']);
            reset($calc['schedule']);

            $loan->update([
                'term_months'           => $newTerm,
                'total_interest'        => $calc['total_interest'],
                'total_repayable'       => $calc['total_repayable'],
                'installment_amount'    => $calc['installment_amount'],
                'outstanding_interest'  => $calc['total_interest'],
                'total_outstanding'     => $calc['total_repayable'],
                'maturity_date'         => $lastRow['due_date'],
                'status'                => 'rescheduled',
            ]);

            $this->buildSchedule($loan, $calc['schedule']);
            $this->logStatusChange($loan, $loan->status, 'rescheduled', $data['reason'] ?? 'Loan rescheduled', $userId);

            return $loan->fresh();
        });
    }

    /**
     * Detect and update overdue loans — run via cron.
     */
    public function processOverdue(): int
    {
        $count = 0;

        $activeLoans = Loan::whereIn('status', ['active', 'disbursed'])->get();

        foreach ($activeLoans as $loan) {
            $overdueSchedules = $loan->schedule()
                ->where('due_date', '<', now()->toDateString())
                ->whereIn('status', ['pending', 'partial'])
                ->get();

            if ($overdueSchedules->isNotEmpty()) {
                $firstOverdue = $overdueSchedules->sortBy('due_date')->first();
                $daysOverdue  = Carbon::parse($firstOverdue->due_date)->diffInDays(now());

                foreach ($overdueSchedules as $row) {
                    $row->markOverdue();
                }

                if ($loan->status !== 'overdue') {
                    $loan->update([
                        'status'       => 'overdue',
                        'is_overdue'   => true,
                        'days_past_due'=> $daysOverdue,
                        'overdue_since'=> $firstOverdue->due_date,
                    ]);
                    $this->logStatusChange($loan, 'active', 'overdue', 'Auto-detected overdue', 1);
                } else {
                    $loan->update(['days_past_due' => $daysOverdue]);
                }

                // Post penalty if product has penalty enabled
                $this->accruepenalty($loan, $overdueSchedules->sum(fn($r) => $r->balance_due), $daysOverdue);

                $count++;
            }
        }

        return $count;
    }

    /**
     * Post a penalty on overdue loan.
     */
    public function accruePenalty(Loan $loan, float $overdueAmount, int $daysOverdue): void
    {
        $product = $loan->loanProduct;

        if (!$product->penalty_enabled) return;
        if ($daysOverdue <= $product->penalty_grace_days) return;

        $effectiveDays = $daysOverdue - $product->penalty_grace_days;

        $penaltyAmount = 0;
        if ($product->penalty_type === 'fixed') {
            $penaltyAmount = (float)$product->penalty_fixed_amount;
        } else {
            // Monthly rate converted to daily
            $dailyRate     = ($product->penalty_rate / 100) / 30;
            $penaltyAmount = round($overdueAmount * $dailyRate * $effectiveDays, 2);
        }

        if ($penaltyAmount <= 0) return;

        // Only post if not already posted today
        $alreadyPosted = $loan->penalties()
            ->where('accrual_date', now()->toDateString())
            ->exists();

        if (!$alreadyPosted) {
            \App\Models\Penalty::create([
                'loan_id'     => $loan->id,
                'amount'      => $penaltyAmount,
                'days_overdue'=> $daysOverdue,
                'accrual_date'=> now()->toDateString(),
                'status'      => 'outstanding',
            ]);

            $loan->increment('outstanding_penalty', $penaltyAmount);
            $loan->increment('total_outstanding', $penaltyAmount);

            LedgerEntry::create([
                'branch_id'   => $loan->branch_id,
                'loan_id'     => $loan->id,
                'created_by'  => 1,
                'entry_type'  => 'penalty_posted',
                'debit_credit'=> 'debit',
                'amount'      => $penaltyAmount,
                'description' => "Penalty posted on {$loan->loan_number} ({$daysOverdue} days overdue)",
                'entry_date'  => now()->toDateString(),
                'reference'   => $loan->loan_number,
            ]);
        }
    }

    /**
     * Write off a loan.
     */
    public function writeOff(Loan $loan, string $reason, int $userId): Loan
    {
        return DB::transaction(function () use ($loan, $reason, $userId) {
            $writeOffAmount = $loan->total_outstanding;

            $loan->update([
                'status'            => 'written_off',
                'write_off_amount'  => $writeOffAmount,
                'written_off_by'    => $userId,
                'written_off_at'    => now(),
                'write_off_reason'  => $reason,
                'total_outstanding' => 0,
            ]);

            $this->logStatusChange($loan, $loan->getOriginal('status'), 'written_off', $reason, $userId);

            LedgerEntry::create([
                'branch_id'   => $loan->branch_id,
                'loan_id'     => $loan->id,
                'created_by'  => $userId,
                'entry_type'  => 'write_off',
                'debit_credit'=> 'credit',
                'amount'      => $writeOffAmount,
                'description' => "Loan written off: {$loan->loan_number}. Reason: {$reason}",
                'entry_date'  => now()->toDateString(),
                'reference'   => $loan->loan_number,
            ]);

            return $loan->fresh();
        });
    }

    /**
     * Update DTI and affordability score.
     */
    private function updateCreditMetrics(Loan $loan): void
    {
        $borrower     = $loan->borrower;
        $monthlyIncome = (float)($borrower->monthly_income ?? $borrower->monthly_business_revenue ?? 0);

        if ($monthlyIncome > 0) {
            $existingDebt  = (float)$loan->existing_debt_monthly;
            $installment   = (float)$loan->installment_amount;
            $dti           = (($existingDebt + $installment) / $monthlyIncome) * 100;
            $affordability = $monthlyIncome - $existingDebt - $installment;

            $loan->update([
                'debt_to_income_ratio' => round($dti, 2),
                'affordability_score'  => round($affordability, 2),
            ]);
        }
    }

    private function logStatusChange(Loan $loan, ?string $from, string $to, string $note, int $userId): void
    {
        LoanStatusHistory::create([
            'loan_id'    => $loan->id,
            'changed_by' => $userId,
            'from_status'=> $from,
            'to_status'  => $to,
            'note'       => $note,
            'ip_address' => request()->ip(),
        ]);
    }
}
