<?php

namespace App\Services\Loan;

use App\Models\Loan;
use App\Models\RepaymentSchedule;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Big Cash Loan Schedule Engine
 *
 * Supports:
 *  - Flat rate interest (interest on original principal for all periods)
 *  - Reducing balance (interest on outstanding principal each period)
 *  - Frequencies: daily, weekly, biweekly, monthly
 *  - Ghana public holiday & weekend shifting
 *  - Grace periods
 *  - Rescheduling / restructuring
 */
class LoanScheduleService
{
    protected array $ghanaHolidays;

    public function __construct()
    {
        $this->ghanaHolidays = config('bigcash.ghana_holidays', []);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // PUBLIC API
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Generate amortization schedule rows for a loan.
     * Returns a Collection of arrays — does NOT persist to DB.
     */
    public function generate(
        float  $principal,
        float  $annualRatePercent,
        int    $termMonths,
        string $interestType,      // 'flat' | 'reducing'
        string $frequency,         // 'daily'|'weekly'|'biweekly'|'monthly'
        Carbon $firstRepaymentDate,
        float  $processingFee = 0,
        float  $insuranceFee  = 0,
        float  $adminFee      = 0
    ): Collection {
        $periods   = $this->periodCount($termMonths, $frequency);
        $ratePerPeriod = $this->periodRate($annualRatePercent, $frequency);

        $schedule = collect();
        $balance  = $principal;
        $date     = $firstRepaymentDate->copy();

        // Total interest (flat) — spread evenly
        $flatInterestPerPeriod = $interestType === 'flat'
            ? round(($principal * ($annualRatePercent / 100) * ($termMonths / 12)) / $periods, 2)
            : 0;

        // Reducing: EMI formula
        $emi = $interestType === 'reducing'
            ? $this->calculateEMI($principal, $ratePerPeriod, $periods)
            : 0;

        for ($i = 1; $i <= $periods; $i++) {
            $dueDate = $this->adjustForHolidaysAndWeekends($date->copy());

            if ($interestType === 'flat') {
                $interest  = $flatInterestPerPeriod;
                // Last installment absorbs rounding diff
                $principalDue = ($i === $periods)
                    ? round($balance, 2)
                    : round($principal / $periods, 2);
            } else {
                // Reducing balance
                $interest     = round($balance * $ratePerPeriod, 2);
                $principalDue = ($i === $periods)
                    ? round($balance, 2)
                    : round($emi - $interest, 2);
                if ($principalDue < 0) $principalDue = 0;
            }

            // Fees only on first installment
            $feesDue = ($i === 1) ? round($processingFee + $insuranceFee + $adminFee, 2) : 0;

            $total = round($principalDue + $interest + $feesDue, 2);

            $schedule->push([
                'installment_number' => $i,
                'due_date'           => $dueDate->toDateString(),
                'opening_balance'    => round($balance, 2),
                'principal_due'      => $principalDue,
                'interest_due'       => $interest,
                'fees_due'           => $feesDue,
                'penalty_due'        => 0,
                'total_due'          => $total,
                'closing_balance'    => round(max(0, $balance - $principalDue), 2),
                'principal_paid'     => 0,
                'interest_paid'      => 0,
                'fees_paid'          => 0,
                'penalty_paid'       => 0,
                'total_paid'         => 0,
                'status'             => 'pending',
            ]);

            $balance = max(0, $balance - $principalDue);
            $date    = $this->nextDueDate($date, $frequency);
        }

        return $schedule;
    }

    /**
     * Persist schedule to DB after loan approval / disbursement.
     */
    public function persistSchedule(Loan $loan): void
    {
        // Remove old schedule if rescheduling
        $loan->schedule()->delete();

        $firstDate = Carbon::parse($loan->first_repayment_date);

        $rows = $this->generate(
            principal:          (float) $loan->disbursed_amount,
            annualRatePercent:  (float) $loan->interest_rate,
            termMonths:         $loan->term_months,
            interestType:       $loan->interest_type,
            frequency:          $loan->repayment_frequency,
            firstRepaymentDate: $firstDate,
            processingFee:      (float) $loan->processing_fee_amount,
            insuranceFee:       (float) $loan->insurance_fee_amount,
            adminFee:           (float) $loan->admin_fee_amount,
        );

        $inserts = $rows->map(fn ($row) => array_merge($row, [
            'loan_id'    => $loan->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]))->toArray();

        RepaymentSchedule::insert($inserts);

        // Update loan totals
        $totalInterest  = $rows->sum('interest_due');
        $totalFees      = $rows->sum('fees_due');
        $totalRepayable = $rows->sum('total_due');
        $installment    = $rows->first()['total_due'] ?? 0;

        $loan->update([
            'total_interest'        => $totalInterest,
            'total_repayable'       => $totalRepayable,
            'installment_amount'    => $installment,
            'outstanding_principal' => (float) $loan->disbursed_amount,
            'outstanding_interest'  => $totalInterest,
            'outstanding_fees'      => $totalFees,
            'total_outstanding'     => $totalRepayable,
            'maturity_date'         => $rows->last()['due_date'],
        ]);
    }

    /**
     * Calculate settlement amount as of a given date.
     */
    public function settlementAmount(Loan $loan, Carbon $asOf): array
    {
        $pendingSchedules = $loan->schedule()
            ->whereIn('status', ['pending', 'partial', 'overdue'])
            ->orderBy('due_date')
            ->get();

        $totalPrincipal = 0;
        $totalInterest  = 0;
        $totalFees      = 0;
        $totalPenalty   = 0;

        foreach ($pendingSchedules as $s) {
            $totalPrincipal += max(0, $s->principal_due - $s->principal_paid);
            $totalInterest  += max(0, $s->interest_due  - $s->interest_paid);
            $totalFees      += max(0, $s->fees_due      - $s->fees_paid);
            $totalPenalty   += max(0, $s->penalty_due   - $s->penalty_paid);
        }

        // Early repayment: add penalty if configured
        $earlyFee = 0;
        if ($loan->loanProduct->allow_early_repayment && $loan->loanProduct->early_repayment_fee > 0) {
            $earlyFee = round($totalPrincipal * ($loan->loanProduct->early_repayment_fee / 100), 2);
        }

        $total = $totalPrincipal + $totalInterest + $totalFees + $totalPenalty + $earlyFee;

        return [
            'as_of_date'        => $asOf->toDateString(),
            'outstanding_principal' => round($totalPrincipal, 2),
            'outstanding_interest'  => round($totalInterest, 2),
            'outstanding_fees'      => round($totalFees, 2),
            'outstanding_penalty'   => round($totalPenalty, 2),
            'early_repayment_fee'   => round($earlyFee, 2),
            'total_settlement'      => round($total, 2),
        ];
    }

    /**
     * Accrue penalties on overdue installments.
     * Called by cron command daily.
     */
    public function accrueOverduePenalties(Loan $loan): void
    {
        if (! $loan->loanProduct->penalty_enabled) return;

        $product = $loan->loanProduct;
        $today   = today();

        $overdueSchedules = $loan->schedule()
            ->whereIn('status', ['pending', 'partial'])
            ->where('due_date', '<', $today)
            ->get();

        foreach ($overdueSchedules as $schedule) {
            $graceDays  = $product->penalty_grace_days ?? 0;
            $daysOverdue = (int) $schedule->due_date->diffInDays($today) - $graceDays;

            if ($daysOverdue <= 0) continue;

            $unpaid      = max(0, (float)$schedule->total_due - (float)$schedule->total_paid);
            $penaltyBase = (float)$schedule->principal_due; // penalty on principal portion

            if ($product->penalty_type === 'fixed') {
                $penalty = (float) $product->penalty_fixed_amount;
            } else {
                // Daily penalty rate from monthly rate
                $monthlyRate  = (float) $product->penalty_rate / 100;
                $dailyRate    = $monthlyRate / 30;
                $penalty      = round($penaltyBase * $dailyRate * $daysOverdue, 2);
            }

            if ($penalty <= 0) continue;

            // Check if penalty already accrued for today
            $exists = \App\Models\Penalty::where('loan_id', $loan->id)
                ->where('repayment_schedule_id', $schedule->id)
                ->where('accrual_date', $today)
                ->exists();

            if (! $exists) {
                \App\Models\Penalty::create([
                    'loan_id'               => $loan->id,
                    'repayment_schedule_id' => $schedule->id,
                    'amount'                => $penalty,
                    'days_overdue'          => $daysOverdue,
                    'accrual_date'          => $today,
                    'status'                => 'outstanding',
                ]);

                // Update schedule
                $schedule->increment('penalty_due', $penalty);
                $schedule->update(['is_overdue' => true, 'days_past_due' => $daysOverdue, 'status' => 'overdue']);

                // Update loan
                $loan->increment('outstanding_penalty', $penalty);
                $loan->increment('total_outstanding', $penalty);
                $loan->update(['is_overdue' => true, 'days_past_due' => $daysOverdue, 'overdue_since' => $schedule->due_date]);
            }
        }
    }

    /**
     * Recalculate loan balances from scratch based on payments made.
     */
    public function recalculateLoanBalances(Loan $loan): void
    {
        $disbursed = (float) $loan->disbursed_amount;

        $principalPaid = (float) $loan->repayments()->where('status', 'confirmed')->sum('principal_paid');
        $interestPaid  = (float) $loan->repayments()->where('status', 'confirmed')->sum('interest_paid');
        $feesPaid      = (float) $loan->repayments()->where('status', 'confirmed')->sum('fees_paid');
        $penaltyPaid   = (float) $loan->repayments()->where('status', 'confirmed')->sum('penalty_paid');
        $totalPaid     = $principalPaid + $interestPaid + $feesPaid + $penaltyPaid;

        $outPrincipal = max(0, $disbursed - $principalPaid);

        // Get outstanding from schedule
        $outInterest  = (float) $loan->schedule()->sum(\DB::raw('interest_due - interest_paid'));
        $outFees      = (float) $loan->schedule()->sum(\DB::raw('fees_due - fees_paid'));
        $outPenalty   = (float) $loan->schedule()->sum(\DB::raw('penalty_due - penalty_paid'));

        $loan->update([
            'outstanding_principal'  => max(0, $outPrincipal),
            'outstanding_interest'   => max(0, $outInterest),
            'outstanding_fees'       => max(0, $outFees),
            'outstanding_penalty'    => max(0, $outPenalty),
            'total_outstanding'      => max(0, $outPrincipal + $outInterest + $outFees + $outPenalty),
            'total_paid'             => $totalPaid,
            'total_interest_paid'    => $interestPaid,
            'total_penalty_paid'     => $penaltyPaid,
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // PRIVATE HELPERS
    // ─────────────────────────────────────────────────────────────────────────

    private function calculateEMI(float $principal, float $ratePerPeriod, int $periods): float
    {
        if ($ratePerPeriod == 0) {
            return round($principal / $periods, 2);
        }
        $emi = $principal * $ratePerPeriod * pow(1 + $ratePerPeriod, $periods)
             / (pow(1 + $ratePerPeriod, $periods) - 1);
        return round($emi, 2);
    }

    private function periodRate(float $annualRatePercent, string $frequency): float
    {
        $annual = $annualRatePercent / 100;
        return match ($frequency) {
            'daily'    => $annual / 365,
            'weekly'   => $annual / 52,
            'biweekly' => $annual / 26,
            'monthly'  => $annual / 12,
            default    => $annual / 12,
        };
    }

    private function periodCount(int $termMonths, string $frequency): int
    {
        return match ($frequency) {
            'daily'    => $termMonths * 30,
            'weekly'   => $termMonths * 4,
            'biweekly' => $termMonths * 2,
            'monthly'  => $termMonths,
            default    => $termMonths,
        };
    }

    private function nextDueDate(Carbon $current, string $frequency): Carbon
    {
        return match ($frequency) {
            'daily'    => $current->addDay(),
            'weekly'   => $current->addWeek(),
            'biweekly' => $current->addWeeks(2),
            'monthly'  => $current->addMonth(),
            default    => $current->addMonth(),
        };
    }

    /**
     * If due date falls on weekend or Ghana public holiday, push to next business day.
     */
    private function adjustForHolidaysAndWeekends(Carbon $date): Carbon
    {
        $maxShift = 10;
        $shifted  = 0;
        while ($shifted < $maxShift) {
            if ($date->isWeekend()) {
                $date->next(Carbon::MONDAY);
                $shifted++;
                continue;
            }
            $mmdd = $date->format('m-d');
            if (in_array($mmdd, $this->ghanaHolidays)) {
                $date->addDay();
                $shifted++;
                continue;
            }
            break;
        }
        return $date;
    }
}
