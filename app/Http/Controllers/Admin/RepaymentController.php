<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Loan;
use App\Models\Repayment;
use App\Models\Branch;
use App\Services\Loan\RepaymentAllocationService;
use App\Services\Loan\ReceiptService;
use App\Services\Payment\PaystackService;
use App\Services\Notification\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;
use App\Imports\RepaymentsImport;

class RepaymentController extends Controller
{
    public function __construct(
        protected RepaymentAllocationService $allocationService,
        protected ReceiptService $receiptService,
        protected PaystackService $paystackService,
        protected NotificationService $notificationService,
    ) {
        $this->middleware('permission:repayments.view')->only(['index', 'show', 'receipt']);
        $this->middleware('permission:repayments.create')->only(['create', 'store', 'bulkUpload', 'processBulkUpload']);
        $this->middleware('permission:repayments.reverse')->only(['reverse']);
    }

    public function index(Request $request)
    {
        $user     = Auth::user();
        $branchId = $user->isSuperAdmin() || $user->hasRole('admin')
            ? ($request->branch_id ?? null)
            : $user->branch_id;

        $query = Repayment::with(['loan', 'borrower', 'collectedBy', 'branch'])
            ->when($branchId, fn($q) => $q->where('branch_id', $branchId))
            ->when($request->status, fn($q) => $q->where('status', $request->status))
            ->when($request->method, fn($q) => $q->where('payment_method', $request->method))
            ->when($request->date_from, fn($q) => $q->where('payment_date', '>=', $request->date_from))
            ->when($request->date_to, fn($q) => $q->where('payment_date', '<=', $request->date_to))
            ->when($request->search, fn($q) => $q
                ->where('receipt_number', 'like', "%{$request->search}%")
                ->orWhereHas('borrower', fn($bq) => $bq->search($request->search))
                ->orWhereHas('loan', fn($lq) => $lq->where('loan_number', 'like', "%{$request->search}%")))
            ->when($user->hasRole('collector'), fn($q) => $q->where('collected_by', $user->id))
            ->latest('payment_date');

        $repayments = $query->paginate(20)->withQueryString();
        $branches   = Branch::active()->get();

        return view('admin.repayments.index', compact('repayments', 'branches'));
    }

    public function create(Request $request)
    {
        $loan = $request->loan_id ? Loan::with(['borrower', 'schedule', 'loanProduct'])->findOrFail($request->loan_id) : null;
        return view('admin.repayments.create', compact('loan'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'loan_id'         => 'required|exists:loans,id',
            'amount'          => 'required|numeric|min:1',
            'payment_method'  => 'required|in:cash,mobile_money,bank_transfer,cheque,other',
            'payment_date'    => 'required|date|before_or_equal:today',
            'payment_reference'=> 'nullable|string|max:100',
            'mobile_money_number' => 'nullable|string|max:20',
            'mobile_money_provider' => 'nullable|in:mtn,vodafone,airteltigo',
            'bank_name'       => 'nullable|string',
            'cheque_number'   => 'nullable|string',
            'notes'           => 'nullable|string|max:500',
        ]);

        $loan = Loan::findOrFail($request->loan_id);

        if (!$loan->isActive()) {
            return back()->with('error', 'Repayment cannot be recorded for a loan that is not active.');
        }

        // Validate amount does not exceed outstanding
        if ($request->amount > $loan->total_outstanding + 0.01) {
            return back()->withErrors(['amount' => "Amount cannot exceed outstanding balance of ₵" . number_format($loan->total_outstanding, 2)])->withInput();
        }

        DB::transaction(function () use ($request, $loan, &$repayment) {
            $repayment = Repayment::create([
                'receipt_number'        => Repayment::generateReceiptNumber(),
                'loan_id'               => $loan->id,
                'borrower_id'           => $loan->borrower_id,
                'branch_id'             => $loan->branch_id,
                'collected_by'          => Auth::id(),
                'amount'                => $request->amount,
                'payment_method'        => $request->payment_method,
                'payment_reference'     => $request->payment_reference,
                'mobile_money_number'   => $request->mobile_money_number,
                'mobile_money_provider' => $request->mobile_money_provider,
                'bank_name'             => $request->bank_name,
                'cheque_number'         => $request->cheque_number,
                'payment_date'          => $request->payment_date,
                'payment_time'          => now()->toTimeString(),
                'status'                => 'confirmed',
                'notes'                 => $request->notes,
            ]);

            $this->allocationService->allocate($loan, (float)$request->amount, $repayment);

            // Generate receipt PDF
            $this->receiptService->generate($repayment);

            // Send notification
            $this->notificationService->send(
                $loan->borrower, 'repayment_received', $loan,
                ['amount' => number_format($request->amount, 2), 'receipt' => $repayment->receipt_number]
            );
        });

        return redirect()->route('admin.repayments.receipt', $repayment)
            ->with('success', "Repayment of ₵{$request->amount} recorded. Receipt: {$repayment->receipt_number}");
    }

    public function receipt(Repayment $repayment)
    {
        return $this->receiptService->streamReceipt($repayment);
    }

    public function show(Repayment $repayment)
    {
        $repayment->load(['loan.borrower', 'collectedBy', 'branch', 'reversedBy', 'schedule']);
        return view('admin.repayments.show', compact('repayment'));
    }

    public function reverse(Request $request, Repayment $repayment)
    {
        $request->validate(['reason' => 'required|string|max:500']);

        if ($repayment->status !== 'confirmed') {
            return back()->with('error', 'Only confirmed repayments can be reversed.');
        }

        // Paystack repayments need extra check
        if ($repayment->payment_method === 'paystack') {
            return back()->with('error', 'Online payments must be reversed through Paystack dashboard. Record the reversal manually after processing.');
        }

        $this->allocationService->reverse($repayment, $request->reason, Auth::id());

        activity('repayment')->causedBy(Auth::user())->performedOn($repayment)
            ->withProperties(['reason' => $request->reason])->log('Repayment reversed');

        return back()->with('success', "Repayment {$repayment->receipt_number} reversed.");
    }

    public function bulkUpload()
    {
        return view('admin.repayments.bulk-upload');
    }

    public function processBulkUpload(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,csv|max:2048',
        ]);

        $batchId = 'BULK-' . strtoupper(Str::random(8));

        try {
            $import = new RepaymentsImport($batchId, Auth::user());
            Excel::import($import, $request->file('file'));

            $result = $import->getResult();
            return back()->with('success', "Bulk upload processed: {$result['success']} succeeded, {$result['failed']} failed. Batch: {$batchId}");
        } catch (\Exception $e) {
            return back()->with('error', 'Upload failed: ' . $e->getMessage());
        }
    }

    // ─── Paystack Online Payment Initiation ──────────────────────────────────

    public function initiateOnlinePayment(Request $request, Loan $loan)
    {
        $request->validate([
            'amount'    => 'required|numeric|min:1',
            'purpose'   => 'required|in:installment,penalty,full_settlement,partial',
            'schedule_id' => 'nullable|exists:repayment_schedules,id',
        ]);

        $email = $loan->borrower->email ?? $request->email;
        if (! $email) {
            return back()->with('error', 'Borrower email is required for online payment.');
        }

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

        return back()->with('error', 'Could not initialize payment: ' . ($result['message'] ?? 'Unknown error'));
    }
}
