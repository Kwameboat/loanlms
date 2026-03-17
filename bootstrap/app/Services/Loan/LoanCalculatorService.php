<?php

namespace App\Services\Loan;

use App\Models\LoanProduct;
use Carbon\Carbon;

class LoanCalculatorService
{
    /**
     * Calculate a full amortization schedule and loan summary.
     *
     * @param float  $principal         Loan amount
     * @param float  $annualRate        Annual interest rate as percentage (e.g. 24 = 24%)
     * @param int    $termMonths        Loan duration in months
     * @param string $interestType      'flat' or 'reducing'
     * @param string $frequency         'daily'|'weekly'|'biweekly'|'monthly'
     * @param string $firstRepayment    First repayment date (Y-m-d)
     * @param float  $processingFee     Processing fee amount
     * @param float  $insuranceFee      Insurance fee amount
     * @param float  $adminFee          Admin fee amount
     * @return array
     */
    public function calculate(
        float  $principal,
        float  $annualRate,
        int    $termMonths,
        string $interestType,
        string $frequency,
        string $firstRepayment,
        float  $processingFee = 0,
        float  $insuranceFee  = 0,
        float  $adminFee      = 0
    ): array {
        $periodsPerYear = $this->periodsPerYear($frequency);
        $totalPeriods   = $this->totalPeriods($termMonths, $frequency);
        $periodRate     = ($annualRate / 100) / $periodsPerYear;

        if ($interestType === 'flat') {
            return $this->calculateFlat(
                $principal, $annualRate, $termMonths, $totalPeriods,
                $frequency, $firstRepayment, $processingFee, $insuranceFee, $adminFee
            );
        }

        return $this->calculateReducing(
            $principal, $periodRate, $totalPeriods,
            $frequency, $firstRepayment, $processingFee, $insuranceFee, $adminFee
        );
    }

    // ─── Flat Rate ────────────────────────────────────────────────────────────

    private function calculateFlat(
        float $principal, float $annualRate, int $termMonths,
        int $totalPeriods, string $frequency, string $firstRepayment,
        float $processingFee, float $insuranceFee, float $adminFee
    ): array {
        $monthlyRate   = $annualRate / 100 / 12;
        $totalInterest = $principal * $monthlyRate * $termMonths;
        $totalRepay    = $principal + $totalInterest;
        $installment   = round($totalRepay / $totalPeriods, 2);

        // Correct for rounding on last installment
        $scheduleRows = [];
        $dueDate      = Carbon::parse($firstRepayment);
        $balance      = $principal;
        $principalPerPeriod = round($principal / $totalPeriods, 2);
        $interestPerPeriod  = round($totalInterest / $totalPeriods, 2);

        for ($i = 1; $i <= $totalPeriods; $i++) {
            $adjustedDate = $this->adjustForHolidayWeekend($dueDate->copy());

            // Last installment: take remainder to fix rounding
            $principalThisPeriod = ($i === $totalPeriods)
                ? round($balance, 2)
                : $principalPerPeriod;
            $interestThisPeriod  = ($i === $totalPeriods)
                ? round($totalInterest - ($interestPerPeriod * ($totalPeriods - 1)), 2)
                : $interestPerPeriod;

            $totalDue    = $principalThisPeriod + $interestThisPeriod;
            $closingBal  = round($balance - $principalThisPeriod, 2);

            $scheduleRows[] = [
                'installment_number' => $i,
                'due_date'           => $adjustedDate->format('Y-m-d'),
                'opening_balance'    => round($balance, 2),
                'principal_due'      => $principalThisPeriod,
                'interest_due'       => $interestThisPeriod,
                'fees_due'           => 0,
                'penalty_due'        => 0,
                'total_due'          => round($totalDue, 2),
                'closing_balance'    => max(0, $closingBal),
            ];

            $balance = max(0, $closingBal);
            $dueDate = $this->nextDueDate($dueDate, $frequency);
        }

        return [
            'principal'         => $principal,
            'total_interest'    => round($totalInterest, 2),
            'total_fees'        => $processingFee + $insuranceFee + $adminFee,
            'processing_fee'    => $processingFee,
            'insurance_fee'     => $insuranceFee,
            'admin_fee'         => $adminFee,
            'total_repayable'   => round($totalRepay, 2),
            'installment_amount'=> round($installment, 2),
            'total_periods'     => $totalPeriods,
            'interest_type'     => 'flat',
            'annual_rate'       => $annualRate,
            'schedule'          => $scheduleRows,
        ];
    }

    // ─── Reducing Balance (Amortization) ─────────────────────────────────────

    private function calculateReducing(
        float $principal, float $periodRate, int $totalPeriods,
        string $frequency, string $firstRepayment,
        float $processingFee, float $insuranceFee, float $adminFee
    ): array {
        // PMT formula: P * r / (1 - (1+r)^-n)
        if ($periodRate == 0) {
            $installment = $principal / $totalPeriods;
        } else {
            $installment = $principal * $periodRate / (1 - pow(1 + $periodRate, -$totalPeriods));
        }
        $installment = round($installment, 2);

        $scheduleRows  = [];
        $balance       = $principal;
        $totalInterest = 0;
        $dueDate       = Carbon::parse($firstRepayment);

        for ($i = 1; $i <= $totalPeriods; $i++) {
            $adjustedDate   = $this->adjustForHolidayWeekend($dueDate->copy());
            $interestDue    = round($balance * $periodRate, 2);
            $principalDue   = ($i === $totalPeriods)
                ? round($balance, 2)
                : round($installment - $interestDue, 2);
            $totalDue       = $principalDue + $interestDue;
            $closingBalance = max(0, round($balance - $principalDue, 2));
            $totalInterest += $interestDue;

            $scheduleRows[] = [
                'installment_number' => $i,
                'due_date'           => $adjustedDate->format('Y-m-d'),
                'opening_balance'    => round($balance, 2),
                'principal_due'      => $principalDue,
                'interest_due'       => $interestDue,
                'fees_due'           => 0,
                'penalty_due'        => 0,
                'total_due'          => round($totalDue, 2),
                'closing_balance'    => $closingBalance,
            ];

            $balance  = $closingBalance;
            $dueDate  = $this->nextDueDate($dueDate, $frequency);
        }

        return [
            'principal'          => $principal,
            'total_interest'     => round($totalInterest, 2),
            'total_fees'         => $processingFee + $insuranceFee + $adminFee,
            'processing_fee'     => $processingFee,
            'insurance_fee'      => $insuranceFee,
            'admin_fee'          => $adminFee,
            'total_repayable'    => round($principal + $totalInterest, 2),
            'installment_amount' => $installment,
            'total_periods'      => $totalPeriods,
            'interest_type'      => 'reducing',
            'annual_rate'        => $periodRate * $this->periodsPerYear($frequency) * 100,
            'schedule'           => $scheduleRows,
        ];
    }

    // ─── Settlement Amount ────────────────────────────────────────────────────

    /**
     * Calculate the full settlement amount as of a given date.
     */
    public function calculateSettlement(\App\Models\Loan $loan, string $asOfDate): array
    {
        $date = Carbon::parse($asOfDate);

        $pendingSchedules = $loan->schedule()
            ->whereIn('status', ['pending', 'partial', 'overdue'])
            ->orderBy('installment_number')
            ->get();

        $settlementPrincipal = 0;
        $settlementInterest  = 0;
        $settlementFees      = 0;
        $settlementPenalty   = (float)$loan->outstanding_penalty;

        foreach ($pendingSchedules as $row) {
            $settlementPrincipal += ($row->principal_due - $row->principal_paid);
            $settlementInterest  += ($row->interest_due  - $row->interest_paid);
            $settlementFees      += ($row->fees_due      - $row->fees_paid);
        }

        // Early repayment discount (if product allows)
        $discount = 0;
        if ($loan->loanProduct->allow_early_repayment && $date->lt($loan->maturity_date)) {
            $earlyFeeRate = $loan->loanProduct->early_repayment_fee / 100;
            $discount     = $earlyFeeRate > 0 ? 0 : 0; // implement waiver logic here if needed
        }

        $total = $settlementPrincipal + $settlementInterest + $settlementFees + $settlementPenalty - $discount;

        return [
            'as_of_date'          => $asOfDate,
            'principal'           => round($settlementPrincipal, 2),
            'interest'            => round($settlementInterest, 2),
            'fees'                => round($settlementFees, 2),
            'penalty'             => round($settlementPenalty, 2),
            'discount'            => round($discount, 2),
            'total_settlement'    => round(max(0, $total), 2),
        ];
    }

    // ─── Period Helpers ───────────────────────────────────────────────────────

    private function periodsPerYear(string $frequency): int
    {
        return match ($frequency) {
            'daily'    => 365,
            'weekly'   => 52,
            'biweekly' => 26,
            'monthly'  => 12,
            default    => 12,
        };
    }

    private function totalPeriods(int $termMonths, string $frequency): int
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
            'daily'    => $current->copy()->addDay(),
            'weekly'   => $current->copy()->addWeek(),
            'biweekly' => $current->copy()->addWeeks(2),
            'monthly'  => $current->copy()->addMonth(),
            default    => $current->copy()->addMonth(),
        };
    }

    /**
     * Adjust a date that falls on a weekend or Ghana public holiday
     * to the next working day.
     */
    public function adjustForHolidayWeekend(Carbon $date): Carbon
    {
        $holidays = config('bigcash.ghana_holidays', []);
        $maxIter  = 10;
        $i        = 0;

        while ($i < $maxIter) {
            $isWeekend = $date->isWeekend();
            $isHoliday = in_array($date->format('m-d'), $holidays);

            if (!$isWeekend && !$isHoliday) {
                break;
            }
            $date->addDay();
            $i++;
        }

        return $date;
    }

    /**
     * Quick summary (no schedule rows) — used in loan application previews.
     */
    public function quickSummary(LoanProduct $product, float $amount, int $termMonths, string $frequency): array
    {
        $result = $this->calculate(
            $amount,
            (float)$product->interest_rate * ($product->interest_period === 'per_month' ? 12 : 1),
            $termMonths,
            $product->interest_type,
            $frequency,
            now()->addMonth()->format('Y-m-d'),
            (float)$product->calculateProcessingFee($amount),
            (float)$product->calculateInsuranceFee($amount),
            (float)$product->calculateAdminFee($amount)
        );

        // Remove schedule rows from quick summary
        unset($result['schedule']);
        return $result;
    }
}
