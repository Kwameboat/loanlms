<?php

namespace App\Http\Controllers\Borrower;

use App\Http\Controllers\Controller;
use App\Models\{Loan, Repayment, RepaymentSchedule};
use App\Services\Loan\{LoanCalculatorService, LoanService};
use App\Services\Payment\PaystackService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;

class BorrowerDashboardController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth', 'role:borrower']);
    }

    public function index()
    {
        $borrower = auth()->user()->borrowerProfile;

        if (!$borrower) {
            return view('borrower.no-profile');
        }

        $activeLoans  = $borrower->activeLoans()->with('loanProduct')->get();
        $closedLoans  = $borrower->completedLoans()->with('loanProduct')->latest()->take(5)->get();
        $nextPayments = RepaymentSchedule::whereIn('loan_id', $activeLoans->pluck('id'))
            ->whereIn('status', ['pending', 'partial', 'overdue'])
            ->orderBy('due_date')
            ->take(5)
            ->get();

        $totalOutstanding = $borrower->total_active_outstanding;
        $totalPaid        = $borrower->repayments()->sum('amount');
        $overdueCount     = $activeLoans->where('is_overdue', true)->count();

        return view('borrower.dashboard', compact(
            'borrower', 'activeLoans', 'closedLoans',
            'nextPayments', 'totalOutstanding', 'totalPaid', 'overdueCount'
        ));
    }

    public function profile()
    {
        $borrower = auth()->user()->borrowerProfile;
        return view('borrower.profile', compact('borrower'));
    }
}

class BorrowerLoanController extends Controller
{
    public function __construct(
        private PaystackService $paystack,
        private LoanCalculatorService $calculator
    ) {
        $this->middleware(['auth', 'role:borrower']);
    }

    public function index()
    {
        $borrower = auth()->user()->borrowerProfile;
        $loans    = $borrower->loans()->with('loanProduct', 'branch')
            ->orderBy('created_at', 'desc')
            ->paginate(10);

        return view('borrower.loans.index', compact('loans', 'borrower'));
    }

    public function show(Loan $loan)
    {
        $this->authorizeOwnership($loan);

        $loan->load(['loanProduct', 'branch', 'schedule', 'repayments', 'statusHistory', 'loanOfficer']);
        $settlement = $this->calculator->calculateSettlement($loan, now()->toDateString());

        return view('borrower.loans.show', compact('loan', 'settlement'));
    }

    public function schedule(Loan $loan)
    {
        $this->authorizeOwnership($loan);
        $loan->load(['schedule', 'borrower', 'loanProduct', 'branch']);
        return view('borrower.loans.schedule', compact('loan'));
    }

    public function downloadSchedulePdf(Loan $loan)
    {
        $this->authorizeOwnership($loan);
        $loan->load(['schedule', 'borrower', 'loanProduct', 'branch']);
        $pdf = Pdf::loadView('pdf.repayment-schedule', compact('loan'))
                  ->setPaper('a4', 'portrait');
        return $pdf->download("schedule-{$loan->loan_number}.pdf");
    }

    public function applyOnline(Request $request)
    {
        $borrower = auth()->user()->borrowerProfile;
        if (!$borrower) {
            return redirect()->back()->with('error', 'Borrower profile not found.');
        }

        $products = \App\Models\LoanProduct::active()
            ->whereHas('branches', fn($q) => $q->where('branch_id', $borrower->branch_id))
            ->get();

        return view('borrower.loans.apply', compact('borrower', 'products'));
    }

    public function submitApplication(Request $request)
    {
        $borrower = auth()->user()->borrowerProfile;

        $validated = $request->validate([
            'loan_product_id'    => 'required|exists:loan_products,id',
            'requested_amount'   => 'required|numeric|min:100',
            'term_months'        => 'required|integer|min:1|max:60',
            'repayment_frequency'=> 'required|in:weekly,biweekly,monthly',
            'loan_purpose'       => 'required|string|max:500',
            'existing_debt_monthly' => 'nullable|numeric|min:0',
        ]);

        $loanService = app(\App\Services\Loan\LoanService::class);
        $loan = $loanService->createApplication(array_merge($validated, [
            'branch_id'  => $borrower->branch_id,
            'borrower_id'=> $borrower->id,
        ]), auth()->id());

        $loan->update(['status' => 'submitted']);

        return redirect()->route('borrower.loans.show', $loan)
            ->with('success', 'Loan application submitted successfully. We will review it shortly.');
    }

    public function initiatePayment(Request $request, Loan $loan)
    {
        $this->authorizeOwnership($loan);

        $request->validate([
            'amount'     => 'required|numeric|min:1',
            'purpose'    => 'required|in:installment,penalty,full_settlement,partial',
            'schedule_id'=> 'nullable|exists:repayment_schedules,id',
        ]);

        $borrower = $loan->borrower;
        $email    = $borrower->email ?? auth()->user()->email;

        if (!$email) {
            return back()->with('error', 'A valid email address is required for online payment.');
        }

        try {
            $result = $this->paystack->initializePayment(
                $loan,
                (float)$request->amount,
                $email,
                $request->purpose,
                $request->schedule_id
            );

            return redirect($result['authorization_url']);
        } catch (\Exception $e) {
            return back()->with('error', 'Could not initialize payment: ' . $e->getMessage());
        }
    }

    public function paymentHistory(Loan $loan)
    {
        $this->authorizeOwnership($loan);
        $repayments = $loan->repayments()->where('status', 'confirmed')->orderBy('payment_date', 'desc')->get();
        return view('borrower.loans.payment-history', compact('loan', 'repayments'));
    }

    public function downloadReceipt(Repayment $repayment)
    {
        $this->authorizeRepaymentOwnership($repayment);
        $repayment->load(['loan', 'loan.borrower', 'loan.branch', 'loan.loanProduct']);
        $pdf = Pdf::loadView('pdf.receipt', compact('repayment'))->setPaper('a5', 'portrait');
        return $pdf->download("receipt-{$repayment->receipt_number}.pdf");
    }

    private function authorizeOwnership(Loan $loan): void
    {
        $borrower = auth()->user()->borrowerProfile;
        if (!$borrower || $loan->borrower_id !== $borrower->id) {
            abort(403, 'Unauthorized access to this loan.');
        }
    }

    private function authorizeRepaymentOwnership(Repayment $repayment): void
    {
        $borrower = auth()->user()->borrowerProfile;
        if (!$borrower || $repayment->borrower_id !== $borrower->id) {
            abort(403);
        }
    }
}
