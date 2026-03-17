<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Loan;
use App\Models\Borrower;
use App\Models\Branch;
use App\Models\LoanProduct;
use App\Models\LoanStatusHistory;
use App\Models\LedgerEntry;
use App\Services\Loan\LoanScheduleService;
use App\Services\Loan\ReceiptService;
use App\Services\Notification\NotificationService;
use App\Services\AI\AIService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class LoanController extends Controller
{
    public function __construct(
        protected LoanScheduleService $scheduleService,
        protected NotificationService $notificationService,
        protected ReceiptService $receiptService,
        protected AIService $aiService,
    ) {
        $this->middleware('permission:loans.view')->only(['index', 'show', 'schedule']);
        $this->middleware('permission:loans.create')->only(['create', 'store']);
        $this->middleware('permission:loans.edit')->only(['edit', 'update']);
        $this->middleware('permission:loans.approve')->only(['approve', 'reject']);
        $this->middleware('permission:loans.recommend')->only(['recommend']);
        $this->middleware('permission:loans.disburse')->only(['disburse', 'confirmDisbursement']);
        $this->middleware('permission:loans.write_off')->only(['writeOff']);
        $this->middleware('permission:loans.reschedule')->only(['reschedule', 'processReschedule']);
    }

    public function index(Request $request)
    {
        $user     = Auth::user();
        $branchId = $this->getRestrictionBranchId($user, $request);

        $query = Loan::with(['borrower', 'loanProduct', 'branch', 'loanOfficer'])
            ->when($branchId, fn($q) => $q->where('branch_id', $branchId))
            ->when($request->status, fn($q) => $q->where('status', $request->status))
            ->when($request->search, fn($q) => $q->whereHas('borrower', fn($bq) =>
                $bq->search($request->search))->orWhere('loan_number', 'like', "%{$request->search}%"))
            ->when($request->product_id, fn($q) => $q->where('loan_product_id', $request->product_id))
            ->when($request->officer_id && ($user->isSuperAdmin() || $user->hasRole(['admin', 'branch_manager'])),
                fn($q) => $q->where('loan_officer_id', $request->officer_id))
            ->when($user->hasRole('loan_officer') && !$user->isSuperAdmin(),
                fn($q) => $q->where('loan_officer_id', $user->id))
            ->latest();

        $loans    = $query->paginate(20)->withQueryString();
        $products = LoanProduct::active()->get();
        $branches = Branch::active()->get();

        $statuses = config('bigcash.loan.statuses');

        return view('admin.loans.index', compact('loans', 'products', 'branches', 'statuses'));
    }

    public function create(Request $request)
    {
        $user      = Auth::user();
        $borrower  = $request->borrower_id ? Borrower::findOrFail($request->borrower_id) : null;
        $products  = LoanProduct::active()->get();
        $branches  = Branch::active()->get();
        $officers  = \App\Models\User::role('loan_officer')->active()
            ->when($user->branch_id && !$user->isSuperAdmin(), fn($q) => $q->where('branch_id', $user->branch_id))
            ->get();

        return view('admin.loans.create', compact('borrower', 'products', 'branches', 'officers', 'user'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'borrower_id'          => 'required|exists:borrowers,id',
            'loan_product_id'      => 'required|exists:loan_products,id',
            'loan_officer_id'      => 'required|exists:users,id',
            'branch_id'            => 'required|exists:branches,id',
            'requested_amount'     => 'required|numeric|min:1',
            'term_months'          => 'required|integer|min:1',
            'repayment_frequency'  => 'required|in:daily,weekly,biweekly,monthly',
            'loan_purpose'         => 'required|string|max:500',
            'existing_debt_monthly'=> 'nullable|numeric|min:0',
            'first_repayment_date' => 'nullable|date|after:today',
        ]);

        $borrower = Borrower::findOrFail($request->borrower_id);
        $product  = LoanProduct::findOrFail($request->loan_product_id);

        // Validate amount within product limits
        if ($request->requested_amount < $product->min_amount || $request->requested_amount > $product->max_amount) {
            return back()->withErrors(['requested_amount' =>
                "Amount must be between ₵{$product->min_amount} and ₵{$product->max_amount} for this product."])->withInput();
        }

        DB::transaction(function () use ($request, $product, $borrower, &$loan) {
            $loan = Loan::create([
                'loan_number'          => Loan::generateLoanNumber(),
                'branch_id'            => $request->branch_id,
                'borrower_id'          => $request->borrower_id,
                'loan_product_id'      => $request->loan_product_id,
                'loan_officer_id'      => $request->loan_officer_id,
                'created_by'           => Auth::id(),
                'requested_amount'     => $request->requested_amount,
                'term_months'          => $request->term_months,
                'repayment_frequency'  => $request->repayment_frequency,
                'loan_purpose'         => $request->loan_purpose,
                'existing_debt_monthly'=> $request->existing_debt_monthly ?? 0,
                'interest_type'        => $product->interest_type,
                'interest_rate'        => $product->interest_rate,
                'application_date'     => today(),
                'status'               => 'draft',
            ]);

            $this->logStatusChange($loan, null, 'draft', 'Loan application created', $request);

            // Upload loan documents
            if ($request->hasFile('documents')) {
                foreach ($request->file('documents') as $docType => $file) {
                    $path = $file->store('loan_documents', 'public');
                    $loan->documents()->create([
                        'uploaded_by'   => Auth::id(),
                        'document_type' => $docType,
                        'document_name' => ucwords(str_replace('_', ' ', $docType)),
                        'file_path'     => $path,
                        'file_type'     => $file->getClientOriginalExtension(),
                        'file_size'     => $file->getSize(),
                    ]);
                }
            }
        });

        return redirect()->route('admin.loans.show', $loan)
            ->with('success', "Loan {$loan->loan_number} created. Please submit for review.");
    }

    public function show(Loan $loan)
    {
        $this->authorizeLoanAccess($loan);
        $loan->load([
            'borrower.guarantors', 'loanProduct', 'branch',
            'loanOfficer', 'approvedBy', 'disbursedByUser',
            'schedule', 'repayments.collectedBy',
            'statusHistory.changedBy', 'documents',
            'penalties', 'ledgerEntries',
        ]);

        // AI assessment if available
        $aiAssessment = session('ai_assessment_' . $loan->id);

        return view('admin.loans.show', compact('loan', 'aiAssessment'));
    }

    public function submit(Request $request, Loan $loan)
    {
        $this->authorizeLoanAccess($loan);
        if ($loan->status !== 'draft') return back()->with('error', 'Loan cannot be submitted in its current state.');

        $loan->update(['status' => 'submitted']);
        $this->logStatusChange($loan, 'draft', 'submitted', $request->note ?? 'Submitted for review', $request);

        return back()->with('success', 'Loan submitted for review.');
    }

    public function recommend(Request $request, Loan $loan)
    {
        $request->validate(['note' => 'required|string|max:1000']);
        $this->authorizeLoanAccess($loan);

        if (!in_array($loan->status, ['submitted', 'under_review'])) {
            return back()->with('error', 'Loan is not in a reviewable state.');
        }

        $loan->update([
            'status'             => 'recommended',
            'recommended_by'     => Auth::id(),
            'recommended_at'     => now(),
            'recommendation_note'=> $request->note,
        ]);
        $this->logStatusChange($loan, $loan->getOriginal('status'), 'recommended', $request->note, $request);

        return back()->with('success', 'Loan recommended for approval.');
    }

    public function approve(Request $request, Loan $loan)
    {
        $request->validate([
            'approved_amount' => 'required|numeric|min:1',
            'note'            => 'nullable|string|max:1000',
        ]);

        if (!in_array($loan->status, ['submitted', 'under_review', 'recommended'])) {
            return back()->with('error', 'Loan cannot be approved in its current state.');
        }

        DB::transaction(function () use ($request, $loan) {
            $product = $loan->loanProduct;

            $loan->update([
                'status'                  => 'approved',
                'approved_amount'         => $request->approved_amount,
                'approved_by'             => Auth::id(),
                'approved_at'             => now(),
                'approval_note'           => $request->note,
                'processing_fee_amount'   => $product->calculateProcessingFee($request->approved_amount),
                'insurance_fee_amount'    => $product->calculateInsuranceFee($request->approved_amount),
                'admin_fee_amount'        => $product->calculateAdminFee($request->approved_amount),
            ]);

            $this->logStatusChange($loan, 'recommended', 'approved', $request->note ?? 'Approved', $request);

            $this->notificationService->send($loan->borrower, 'loan_approved', $loan);
        });

        return back()->with('success', "Loan {$loan->loan_number} approved for ₵{$request->approved_amount}.");
    }

    public function reject(Request $request, Loan $loan)
    {
        $request->validate(['reason' => 'required|string|max:1000']);

        $prevStatus = $loan->status;
        $loan->update([
            'status'          => 'rejected',
            'rejected_by'     => Auth::id(),
            'rejected_at'     => now(),
            'rejection_reason'=> $request->reason,
        ]);
        $this->logStatusChange($loan, $prevStatus, 'rejected', $request->reason, $request);
        $this->notificationService->send($loan->borrower, 'loan_rejected', $loan);

        return back()->with('success', 'Loan rejected.');
    }

    public function disburse(Request $request, Loan $loan)
    {
        $request->validate([
            'disbursed_amount'     => 'required|numeric|min:1|max:' . $loan->approved_amount,
            'disbursement_method'  => 'required|in:cash,bank_transfer,mobile_money,cheque,paystack',
            'disbursement_date'    => 'required|date',
            'first_repayment_date' => 'required|date|after:disbursement_date',
            'disbursement_bank'    => 'nullable|string',
            'disbursement_account' => 'nullable|string',
            'disbursement_reference' => 'nullable|string',
        ]);

        if ($loan->status !== 'approved') {
            return back()->with('error', 'Loan must be approved before disbursement.');
        }

        DB::transaction(function () use ($request, $loan) {
            $loan->update([
                'status'                 => 'disbursed',
                'disbursed_amount'       => $request->disbursed_amount,
                'disbursement_method'    => $request->disbursement_method,
                'disbursement_date'      => $request->disbursement_date,
                'first_repayment_date'   => $request->first_repayment_date,
                'disbursed_by'           => Auth::id(),
                'disbursement_bank'      => $request->disbursement_bank,
                'disbursement_account'   => $request->disbursement_account,
                'disbursement_reference' => $request->disbursement_reference,
                'outstanding_principal'  => $request->disbursed_amount,
            ]);

            // Generate amortization schedule
            $this->scheduleService->persistSchedule($loan);

            // Move to active
            $loan->update(['status' => 'active']);

            $this->logStatusChange($loan, 'approved', 'active', 'Loan disbursed', $request);

            // Post ledger entry
            LedgerEntry::create([
                'branch_id'   => $loan->branch_id,
                'loan_id'     => $loan->id,
                'created_by'  => Auth::id(),
                'entry_type'  => 'loan_disbursement',
                'debit_credit'=> 'debit',
                'amount'      => $request->disbursed_amount,
                'description' => "Loan disbursed: {$loan->loan_number}",
                'entry_date'  => $request->disbursement_date,
                'reference'   => $request->disbursement_reference ?? $loan->loan_number,
            ]);

            $this->notificationService->send($loan->borrower, 'loan_disbursed', $loan);
        });

        return redirect()->route('admin.loans.show', $loan)
            ->with('success', "Loan {$loan->loan_number} disbursed successfully.");
    }

    public function aiAssessment(Loan $loan)
    {
        $this->middleware('permission:ai.credit_analysis');
        $assessment = $this->aiService->assessCreditRisk($loan);
        session(['ai_assessment_' . $loan->id => $assessment]);
        return response()->json($assessment);
    }

    public function schedule(Loan $loan)
    {
        $this->authorizeLoanAccess($loan);
        $loan->load(['borrower', 'loanProduct', 'branch', 'schedule']);
        return view('admin.loans.schedule', compact('loan'));
    }

    public function downloadSchedule(Loan $loan)
    {
        $this->authorizeLoanAccess($loan);
        $path = $this->receiptService->generateSchedulePdf($loan);
        return response()->download(storage_path('app/public/' . $path));
    }

    public function writeOff(Request $request, Loan $loan)
    {
        $request->validate(['reason' => 'required|string|max:1000']);

        $loan->update([
            'status'          => 'written_off',
            'write_off_amount'=> $loan->total_outstanding,
            'written_off_by'  => Auth::id(),
            'written_off_at'  => now(),
            'write_off_reason'=> $request->reason,
        ]);

        $this->logStatusChange($loan, $loan->getOriginal('status'), 'written_off', $request->reason, $request);

        LedgerEntry::create([
            'branch_id'   => $loan->branch_id,
            'loan_id'     => $loan->id,
            'created_by'  => Auth::id(),
            'entry_type'  => 'write_off',
            'debit_credit'=> 'debit',
            'amount'      => $loan->total_outstanding,
            'description' => "Write-off: {$loan->loan_number} - {$request->reason}",
            'entry_date'  => today(),
            'reference'   => $loan->loan_number,
        ]);

        return back()->with('success', 'Loan written off.');
    }

    public function reschedule(Loan $loan)
    {
        $this->authorizeLoanAccess($loan);
        return view('admin.loans.reschedule', compact('loan'));
    }

    public function processReschedule(Request $request, Loan $loan)
    {
        $request->validate([
            'new_term_months'          => 'required|integer|min:1',
            'new_first_repayment_date' => 'required|date',
            'reason'                   => 'required|string|max:500',
        ]);

        DB::transaction(function () use ($request, $loan) {
            $prevStatus = $loan->status;
            $loan->update([
                'term_months'        => $request->new_term_months,
                'first_repayment_date'=> $request->new_first_repayment_date,
                'status'             => 'rescheduled',
                'internal_notes'     => ($loan->internal_notes ?? '') . "\nRescheduled: {$request->reason}",
            ]);

            $this->scheduleService->persistSchedule($loan);
            $loan->update(['status' => 'active']);

            $this->logStatusChange($loan, $prevStatus, 'rescheduled', "Rescheduled: {$request->reason}", $request);
        });

        return redirect()->route('admin.loans.show', $loan)
            ->with('success', 'Loan rescheduled and new schedule generated.');
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    protected function logStatusChange(Loan $loan, ?string $from, string $to, ?string $note, Request $request): void
    {
        LoanStatusHistory::create([
            'loan_id'    => $loan->id,
            'changed_by' => Auth::id(),
            'from_status'=> $from,
            'to_status'  => $to,
            'note'       => $note,
            'ip_address' => $request->ip(),
        ]);
    }

    protected function authorizeLoanAccess(Loan $loan): void
    {
        $user = Auth::user();
        if ($user->isSuperAdmin() || $user->hasRole('admin')) return;
        if ($user->branch_id && $user->branch_id !== $loan->branch_id) abort(403);
        if ($user->hasRole('loan_officer') && $loan->loan_officer_id !== $user->id &&
            !in_array($loan->status, ['submitted', 'under_review', 'recommended', 'approved'])) {
            // Officers can see applications in review
        }
    }

    protected function getRestrictionBranchId($user, $request): ?int
    {
        if ($user->isSuperAdmin() || $user->hasRole('admin')) {
            return $request->branch_id ? (int) $request->branch_id : null;
        }
        return $user->branch_id;
    }
}
