<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class LoanCalculatorTest extends TestCase
{
    public function test_flat_rate_interest_calculation(): void
    {
        $principal = 5000;
        $rate = 36;
        $termMonths = 12;
        $interest = $principal * ($rate / 100) * ($termMonths / 12);
        $this->assertEquals(1800.00, $interest);
    }

    public function test_monthly_installment_flat_rate(): void
    {
        $principal   = 5000;
        $interest    = 1800;
        $fee         = 100;
        $installment = ($principal + $interest + $fee) / 12;
        $this->assertEquals(575.00, $installment);
    }

    public function test_reducing_balance_emi(): void
    {
        $principal  = 10000;
        $annualRate = 30;
        $term       = 12;
        $r   = ($annualRate / 100) / 12;
        $emi = $principal * $r * pow(1 + $r, $term) / (pow(1 + $r, $term) - 1);
        $this->assertEqualsWithDelta(966.99, $emi, 0.5);
    }

    public function test_dti_calculation(): void
    {
        $income        = 2500;
        $existingDebt  = 300;
        $newInstalment = 575;
        $dti           = (($existingDebt + $newInstalment) / $income) * 100;
        $this->assertEqualsWithDelta(34.0, $dti, 0.1);
    }

    public function test_processing_fee(): void
    {
        $principal = 5000;
        $feeRate   = 0.02;
        $fee       = $principal * $feeRate;
        $this->assertEquals(100.00, $fee);
    }
}
