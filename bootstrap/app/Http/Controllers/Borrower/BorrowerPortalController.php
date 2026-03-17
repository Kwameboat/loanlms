<?php

namespace App\Http\Controllers\Borrower;

use App\Http\Controllers\Controller;
use App\Models\Loan;
use App\Models\Repayment;
use App\Models\Borrower;
use App\Services\Payment\PaystackService;
use App\Services\Loan\ReceiptService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class BorrowerPortalController extends Controller
{
    public function __construct(
        protected PaystackService $paystackService,
        protected ReceiptService $receiptService,
    ) {
        $this->middleware('auth');
        $this->middleware('role:borrower');
    }

    protected function getBorrower(): Borrower
    {
        return Borrower::where('email', Auth::user()->email)->firstOrFail();
    }

    public function dashboard()
    {
        $borrower = $this->getBorrower();
        $borrower->load('loans.loanProduct', 'loans.schedule');

        $activeLoans = $borrower->loans()->whereIn('status', ['active', 'overdue', 'disbursed'])
            ->with(['loanProduct', 'schedule'])
            ->get();

        $summary = [
            'total_outstanding' => $activeLoans->sum('total_outstanding'),
            'active_loans'      => $activeLoans->count(),
            'overdue_loans'     => $activeLoans->where('is_overdue', true)->count(),
            'next_due_amount'   => $activeLoans->sum('next_due_amount'),
        ];

        // Next due installments across all loans
        $nextDueInstallments = collect();
        foreach ($activeLoans as $loan) {
            $next = $loan->schedule()
                ->whereIn('status', ['pending', 'partial', 'overdue'])
                ->orderBy('due_date')
                ->first();
            if ($next) {
                $next->loan = $loan;
                $nextDueInstallments->push($next);
            }
        }
        $nextDueInstallments = $nextDueInstallments->sortBy('due_date');

        $recentPayments = $borrower->repayments()
            ->with('loan')
            ->where('status', 'confirmed')
            ->latest('payment_date')
            ->take(5)
            ->get();

        return view('borrower.dashboard.index', compact('borrower', 'activeLoans', 'summary', 'nextDueInstallments', 'recentPayments'));
    }

    public function loans()
    {
        $borrower = $this->getBorrower();
        $loans = $borrower->loans()
            ->with(['loanProduct', 'branch'])
            ->latest()
            ->paginate(10);

        return view('borrower.loans.index', compact('borrower', 'loans'));
    }

    public function loanDetail(Loan $loan)
    {
        $borrower = $this->getBorrower();
        if ($loan->borrower_id !== $borrower->id) abort(403);

        $loan->load(['loanProduct', 'branch', 'schedule', 'repayments', 'statusHistory']);

        return view('borrower.loans.show', compact('loan', 'borrower'));
    }

    public function schedule(Loan $loan)
    {
        $borrower = $this->getBorrower();
        if ($loan->borrower_id !== $borrower->id) abort(403);

        $loan->load(['schedule', 'loanProduct', 'borrower']);
        return view('borrower.loans.schedule', compact('loan'));
    }

    public function downloadSchedule(Loan $loan)
    {
        $borrower = $this->getBorrower();
        if ($loan->borrower_id !== $borrower->id) abort(403);

        $loan->load(['schedule', 'loanProduct', 'borrower', 'branch']);
        $path = $this->receiptService->generateSchedulePdf($loan);
        return response()->download(storage_path('app/public/' . $path));
    }

    public function payments()
    {
        $borrower = $this->getBorrower();
        $payments = $borrower->repayments()
            ->with(['loan.loanProduct'])
            ->where('status', 'confirmed')
            ->latest('payment_date')
            ->paginate(20);

        return view('borrower.payments.index', compact('borrower', 'payments'));
    }

    public function receipt(Repayment $repayment)
    {
        $borrower = $this->getBorrower();
        if ($repayment->borrower_id !== $borrower->id) abort(403);

        return $this->receiptService->streamReceipt($repayment);
    }

    public function payOnline(Request $request, Loan $loan)
    {
        $borrower = $this->getBorrower();
        if ($loan->borrower_id !== $borrower->id) abort(403);

        if (!$loan->isActive()) {
            return back()->with('error', 'This loan is not currently active.');
        }

        $request->validate([
            'amount'      => 'required|numeric|min:1',
            'purpose'     => 'required|in:installment,full_settlement,partial',
            'schedule_id' => 'nullable|exists:repayment_schedules,id',
        ]);

        $email = $borrower->email ?? Auth::user()->email;
        $result = $this->paystackService->initializePayment(
            $loan,
            (float) $request->amount,
            $email,
            $request->purpose,
            $request->schedule_id
        );

        if ($result['status']) {
            return redirect($result['data']['authorization_url']);
        }

        return back()->with('error', 'Could not initialize payment. Please try again or contact support.');
    }

    public function profile()
    {
        $borrower = $this->getBorrower();
        $borrower->load(['documents', 'guarantors']);
        return view('borrower.profile', compact('borrower'));
    }

    public function applyForLoan(Request $request)
    {
        $borrower = $this->getBorrower();

        if ($borrower->activeLoans()->count() >= 2) {
            return back()->with('error', 'You have reached the maximum number of active loans. Please repay an existing loan before applying.');
        }

        $products = \App\Models\LoanProduct::active()
            ->whereHas('branches', fn($q) => $q->where('branches.id', $borrower->branch_id))
            ->get();

        return view('borrower.loans.apply', compact('borrower', 'products'));
    }

    public function submitApplication(Request $request)
    {
        $borrower = $this->getBorrower();

        $request->validate([
            'loan_product_id'      => 'required|exists:loan_products,id',
            'requested_amount'     => 'required|numeric|min:1',
            'term_months'          => 'required|integer|min:1',
            'repayment_frequency'  => 'required|in:daily,weekly,biweekly,monthly',
            'loan_purpose'         => 'required|string|max:500',
            'existing_debt_monthly'=> 'nullable|numeric|min:0',
        ]);

        $product = \App\Models\LoanProduct::findOrFail($request->loan_product_id);

        // Assign a loan officer (branch officer)
        $officer = \App\Models\User::role('loan_officer')
            ->where('branch_id', $borrower->branch_id)
            ->where('is_active', true)
            ->inRandomOrder()
            ->first();

        if (!$officer) {
            return back()->with('error', 'No loan officer available. Please visit the branch.');
        }

        $loan = \App\Models\Loan::create([
            'loan_number'          => \App\Models\Loan::generateLoanNumber(),
            'branch_id'            => $borrower->branch_id,
            'borrower_id'          => $borrower->id,
            'loan_product_id'      => $request->loan_product_id,
            'loan_officer_id'      => $officer->id,
            'created_by'           => Auth::id(),
            'requested_amount'     => $request->requested_amount,
            'term_months'          => $request->term_months,
            'repayment_frequency'  => $request->repayment_frequency,
            'loan_purpose'         => $request->loan_purpose,
            'existing_debt_monthly'=> $request->existing_debt_monthly ?? 0,
            'interest_type'        => $product->interest_type,
            'interest_rate'        => $product->interest_rate,
            'application_date'     => today(),
            'status'               => 'submitted',
        ]);

        \App\Models\LoanStatusHistory::create([
            'loan_id'    => $loan->id,
            'changed_by' => Auth::id(),
            'from_status'=> null,
            'to_status'  => 'submitted',
            'note'       => 'Submitted by borrower via portal',
        ]);

        return redirect()->route('borrower.loans.show', $loan)
            ->with('success', "Application {$loan->loan_number} submitted. You will be notified of updates.");
    }
}
