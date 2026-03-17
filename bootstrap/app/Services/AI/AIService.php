<?php

namespace App\Services\AI;

use App\Models\Loan;
use App\Models\Borrower;
use Illuminate\Support\Facades\Log;
use OpenAI\Laravel\Facades\OpenAI;

class AIService
{
    protected bool $enabled;
    protected string $model;

    public function __construct()
    {
        $this->enabled = config('bigcash.ai.enabled', false);
        $this->model   = config('bigcash.ai.model', 'gpt-4o-mini');
    }

    // ─────────────────────────────────────────────────────────────────────────
    // CREDIT ASSESSMENT
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Perform AI-powered credit assessment for a loan application.
     * Returns a structured assessment report.
     */
    public function assessCreditRisk(Loan $loan): array
    {
        if (! $this->enabled) {
            return ['enabled' => false, 'message' => 'AI features are disabled.'];
        }

        $borrower = $loan->borrower;
        $product  = $loan->loanProduct;

        $loanHistory = $this->buildLoanHistory($borrower);
        $financials  = $this->buildFinancials($borrower, $loan);

        $prompt = <<<PROMPT
You are a credit risk analyst for a Ghanaian microfinance lending company called Big Cash Finance.

Analyze the following loan application and provide a structured credit assessment.

## Borrower Profile
- Name: {$borrower->full_name}
- Age: {$borrower->age} years
- Employment Status: {$borrower->employment_status}
- Occupation: {$borrower->occupation}
- Monthly Income: GHS {$borrower->monthly_income}
- Marital Status: {$borrower->marital_status}
- Dependants: {$borrower->number_of_dependants}
- Residential Address: {$borrower->region}, {$borrower->district}

## Loan Request
- Product: {$product->name} ({$product->product_type})
- Requested Amount: GHS {$loan->requested_amount}
- Term: {$loan->term_months} months
- Repayment Frequency: {$loan->repayment_frequency}
- Purpose: {$loan->loan_purpose}
- Existing Monthly Debt: GHS {$loan->existing_debt_monthly}

## Financial Analysis
- Debt-to-Income Ratio: {$financials['dti']}%
- Monthly Installment: GHS {$financials['monthly_installment']}
- Affordability: {$financials['affordability_percentage']}% of income
- Existing Debt Burden: GHS {$loan->existing_debt_monthly}/month

## Loan History
{$loanHistory}

## Guarantor
{$this->buildGuarantorInfo($borrower)}

Please provide your assessment in the following JSON format only, no markdown:
{
  "risk_score": <0-100, where 100 is lowest risk>,
  "risk_level": "<low|medium|high|very_high>",
  "recommendation": "<approve|conditional_approve|reject>",
  "dti_assessment": "<acceptable|borderline|high>",
  "affordability_assessment": "<comfortable|stretched|unaffordable>",
  "key_strengths": ["<point1>", "<point2>", "<point3>"],
  "key_concerns": ["<concern1>", "<concern2>"],
  "conditions": ["<condition if conditional_approve>"],
  "suggested_amount": <number or null>,
  "suggested_term": <months or null>,
  "summary": "<2-3 sentence summary for loan officer>",
  "red_flags": ["<flag1>"] 
}
PROMPT;

        try {
            $response = OpenAI::chat()->create([
                'model'    => $this->model,
                'messages' => [
                    ['role' => 'system', 'content' => 'You are a professional credit risk analyst. Always respond with valid JSON only.'],
                    ['role' => 'user',   'content' => $prompt],
                ],
                'temperature' => 0.3,
                'max_tokens'  => 1000,
            ]);

            $content = $response->choices[0]->message->content;
            $result  = json_decode($content, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \Exception('AI returned invalid JSON: ' . $content);
            }

            // Persist the credit score
            $loan->update([
                'credit_assessment_notes' => $result['summary'] ?? '',
                'debt_to_income_ratio'    => $financials['dti'],
                'affordability_score'     => $result['risk_score'] ?? null,
            ]);

            if (isset($result['risk_score'])) {
                $borrower->update(['credit_score' => $result['risk_score']]);
            }

            return array_merge($result, ['enabled' => true, 'financials' => $financials]);

        } catch (\Exception $e) {
            Log::error('AI credit assessment failed', ['loan' => $loan->id, 'error' => $e->getMessage()]);
            return [
                'enabled'     => true,
                'error'       => true,
                'message'     => 'AI assessment could not be completed: ' . $e->getMessage(),
                'financials'  => $financials,
            ];
        }
    }

    /**
     * AI-powered chat assistant for loan officers.
     */
    public function chatAssistant(string $userMessage, array $conversationHistory = []): string
    {
        if (! $this->enabled) {
            return 'AI features are currently disabled. Please contact your system administrator.';
        }

        $systemPrompt = 'You are BigCashAI, a helpful assistant for Big Cash Finance loan officers in Ghana. '
            . 'You help with loan analysis, policy questions, borrower assessment, and general lending best practices. '
            . 'Always be professional, concise, and relevant to Ghanaian lending context. '
            . 'Currency is Ghana Cedi (GHS / ₵).';

        $messages = [['role' => 'system', 'content' => $systemPrompt]];

        // Add conversation history (last 10 messages)
        foreach (array_slice($conversationHistory, -10) as $msg) {
            $messages[] = $msg;
        }
        $messages[] = ['role' => 'user', 'content' => $userMessage];

        try {
            $response = OpenAI::chat()->create([
                'model'       => $this->model,
                'messages'    => $messages,
                'temperature' => 0.7,
                'max_tokens'  => 600,
            ]);
            return $response->choices[0]->message->content;
        } catch (\Exception $e) {
            Log::error('AI chat failed', ['error' => $e->getMessage()]);
            return 'I apologize, I am unable to respond at this time. Please try again.';
        }
    }

    /**
     * Generate a loan collection message template using AI.
     */
    public function generateCollectionMessage(Loan $loan, string $tone = 'firm_professional'): string
    {
        if (! $this->enabled) return '';

        $borrower = $loan->borrower;

        $prompt = "Write a {$tone} SMS collection message for a Ghanaian microfinance company. "
            . "Borrower: {$borrower->display_name}, Loan: {$loan->loan_number}, "
            . "Overdue Amount: GHS " . number_format($loan->total_outstanding, 2) . ", "
            . "Days Overdue: {$loan->days_past_due}. "
            . "Keep it under 160 characters. Do not include threatening language.";

        try {
            $response = OpenAI::chat()->create([
                'model'       => $this->model,
                'messages'    => [['role' => 'user', 'content' => $prompt]],
                'max_tokens'  => 100,
                'temperature' => 0.5,
            ]);
            return trim($response->choices[0]->message->content, '"\'');
        } catch (\Exception $e) {
            return '';
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // PRIVATE HELPERS
    // ─────────────────────────────────────────────────────────────────────────

    private function buildFinancials(Borrower $borrower, Loan $loan): array
    {
        $income    = (float) ($borrower->monthly_income ?? 0);
        $existing  = (float) ($loan->existing_debt_monthly ?? 0);

        // Estimate monthly installment
        $requestedAmount = (float) $loan->requested_amount;
        $termMonths      = $loan->term_months;
        $interestRate    = (float) $loan->loanProduct->interest_rate;
        $monthlyInterest = $requestedAmount * ($interestRate / 100);
        $monthlyPrincipal = $requestedAmount / $termMonths;
        $estimatedInstallment = $monthlyPrincipal + ($loan->loanProduct->interest_type === 'flat' ? $monthlyInterest : 0);

        $totalObligations = $existing + $estimatedInstallment;
        $dti  = $income > 0 ? round(($totalObligations / $income) * 100, 2) : 0;
        $affordabilityPct = $income > 0 ? round(($estimatedInstallment / $income) * 100, 2) : 0;

        return [
            'monthly_income'           => $income,
            'existing_debt_monthly'    => $existing,
            'monthly_installment'      => round($estimatedInstallment, 2),
            'total_monthly_obligations'=> round($totalObligations, 2),
            'dti'                      => $dti,
            'affordability_percentage' => $affordabilityPct,
            'disposable_income'        => round($income - $totalObligations, 2),
        ];
    }

    private function buildLoanHistory(Borrower $borrower): string
    {
        $loans = $borrower->loans()->with('loanProduct')
            ->whereNotIn('status', ['draft', 'rejected'])
            ->latest()
            ->take(5)
            ->get();

        if ($loans->isEmpty()) return 'No previous loan history.';

        $lines = [];
        foreach ($loans as $l) {
            $lines[] = "- {$l->loan_number}: {$l->loanProduct->name}, GHS {$l->disbursed_amount}, Status: {$l->status}";
        }
        return implode("\n", $lines);
    }

    private function buildGuarantorInfo(Borrower $borrower): string
    {
        $g = $borrower->guarantors()->first();
        if (! $g) return 'No guarantor provided.';
        return "Name: {$g->name}, Relationship: {$g->relationship}, Monthly Income: GHS {$g->monthly_income}, Status: {$g->status}";
    }
}
