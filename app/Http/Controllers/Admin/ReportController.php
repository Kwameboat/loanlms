<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\{Loan, Repayment, Borrower, Branch, LedgerEntry};
use App\Exports\{RepaymentsExport, LoansExport, ArrearsExport};
use App\Services\Loan\LoanCalculatorService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;

class ReportController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth', 'permission:reports.view']);
    }

    // ─── Dashboard/Summary ────────────────────────────────────────────────────

    public function index()
    {
        $branchId = $this->getBranchFilter();
        $dateFrom = request('date_from', now()->startOfMonth()->toDateString());
        $dateTo   = request('date_to', now()->toDateString());

        $summary = $this->buildSummary($branchId, $dateFrom, $dateTo);
        $branches = Branch::active()->orderBy('name')->get();

        return view('admin.reports.index', compact('summary', 'branches', 'dateFrom', 'dateTo'));
    }

    // ─── Repayment Report ─────────────────────────────────────────────────────

    public function repayments(Request $request)
    {
        $query = Repayment::with(['loan', 'borrower', 'branch', 'collectedBy'])
            ->where('status', 'confirmed');

        $this->applyDateFilter($query, $request, 'payment_date');
        $this->applyBranchFilter($query, $request);

        if ($request->filled('payment_method')) {
            $query->where('payment_method', $request->payment_method);
        }

        $repayments = $query->orderBy('payment_date', 'desc')->paginate(50)->withQueryString();

        $totals = Repayment::where('status', 'confirmed')
            ->when($request->date_from, fn($q) => $q->where('payment_date', '>=', $request->date_from))
            ->when($request->date_to, fn($q) => $q->where('payment_date', '<=', $request->date_to))
            ->selectRaw('SUM(amount) as total, SUM(principal_paid) as principal, SUM(interest_paid) as interest, SUM(penalty_paid) as penalty')
            ->first();

        $branches = Branch::active()->get();

        if ($request->export === 'excel') {
            return Excel::download(new RepaymentsExport($query), 'repayments-' . now()->format('Ymd') . '.xlsx');
        }

        if ($request->export === 'pdf') {
            $pdf = Pdf::loadView('admin.reports.repayments-pdf', compact('repayments', 'totals'));
            return $pdf->download('repayments-' . now()->format('Ymd') . '.pdf');
        }

        return view('admin.reports.repayments', compact('repayments', 'totals', 'branches'));
    }

    // ─── Arrears Aging ────────────────────────────────────────────────────────

    public function arrears(Request $request)
    {
        $branchId = $request->branch_id ?? $this->getBranchFilter();

        $query = Loan::whereIn('status', ['active', 'overdue'])
            ->where('is_overdue', true)
            ->with(['borrower', 'branch', 'loanOfficer']);

        if ($branchId) {
            $query->where('branch_id', $branchId);
        }

        $loans = $query->orderBy('days_past_due', 'desc')->get();

        // Bucket into aging brackets
        $aging = [
            '1-30'   => $loans->whereBetween('days_past_due', [1, 30]),
            '31-60'  => $loans->whereBetween('days_past_due', [31, 60]),
            '61-90'  => $loans->whereBetween('days_past_due', [61, 90]),
            '91-180' => $loans->whereBetween('days_past_due', [91, 180]),
            '180+'   => $loans->where('days_past_due', '>', 180),
        ];

        $branches = Branch::active()->get();

        if ($request->export === 'excel') {
            return Excel::download(new ArrearsExport($loans), 'arrears-' . now()->format('Ymd') . '.xlsx');
        }

        if ($request->export === 'pdf') {
            $pdf = Pdf::loadView('admin.reports.arrears-pdf', compact('loans', 'aging'));
            return $pdf->download('arrears-aging-' . now()->format('Ymd') . '.pdf');
        }

        return view('admin.reports.arrears', compact('loans', 'aging', 'branches'));
    }

    // ─── Portfolio at Risk ────────────────────────────────────────────────────

    public function portfolioAtRisk(Request $request)
    {
        $branchId = $request->branch_id ?? $this->getBranchFilter();

        $activeLoans   = Loan::whereIn('status', ['active', 'overdue'])->forBranch($branchId);
        $totalPortfolio = (float)$activeLoans->sum('outstanding_principal');
        $overdueLoans   = (float)Loan::where('is_overdue', true)->forBranch($branchId)->sum('outstanding_principal');
        $par30          = (float)Loan::where('days_past_due', '>=', 30)->forBranch($branchId)->sum('outstanding_principal');
        $par60          = (float)Loan::where('days_past_due', '>=', 60)->forBranch($branchId)->sum('outstanding_principal');
        $par90          = (float)Loan::where('days_past_due', '>=', 90)->forBranch($branchId)->sum('outstanding_principal');

        $parRatio = $totalPortfolio > 0 ? round(($overdueLoans / $totalPortfolio) * 100, 2) : 0;

        // By branch
        $branchPar = Branch::withCount(['loans as active_count' => fn($q) =>
                $q->whereIn('status', ['active', 'overdue'])])
            ->get()
            ->map(fn($b) => [
                'branch'       => $b->name,
                'portfolio'    => Loan::where('branch_id', $b->id)->whereIn('status', ['active','overdue'])->sum('outstanding_principal'),
                'overdue'      => Loan::where('branch_id', $b->id)->where('is_overdue', true)->sum('outstanding_principal'),
                'par'          => $b->portfolio_at_risk,
            ]);

        $branches = Branch::active()->get();

        return view('admin.reports.portfolio-at-risk', compact(
            'totalPortfolio', 'overdueLoans', 'par30', 'par60', 'par90',
            'parRatio', 'branchPar', 'branches'
        ));
    }

    // ─── Loan Officer Performance ─────────────────────────────────────────────

    public function officerPerformance(Request $request)
    {
        $dateFrom = $request->date_from ?? now()->startOfMonth()->toDateString();
        $dateTo   = $request->date_to   ?? now()->toDateString();

        $officers = DB::table('users')
            ->join('model_has_roles', 'users.id', '=', 'model_has_roles.model_id')
            ->join('roles', 'model_has_roles.role_id', '=', 'roles.id')
            ->where('roles.name', 'loan_officer')
            ->select(
                'users.id', 'users.name', 'users.employee_id',
                DB::raw("(SELECT COUNT(*) FROM loans WHERE loan_officer_id = users.id AND CAST(created_at AS DATE) BETWEEN '{$dateFrom}' AND '{$dateTo}') as applications"),
                DB::raw("(SELECT COUNT(*) FROM loans WHERE loan_officer_id = users.id AND status = 'active') as active_loans"),
                DB::raw("(SELECT COALESCE(SUM(disbursed_amount),0) FROM loans WHERE loan_officer_id = users.id AND disbursement_date BETWEEN '{$dateFrom}' AND '{$dateTo}') as disbursed"),
                DB::raw("(SELECT COUNT(*) FROM loans WHERE loan_officer_id = users.id AND is_overdue = 1) as overdue_count"),
                DB::raw("(SELECT COALESCE(SUM(total_outstanding),0) FROM loans WHERE loan_officer_id = users.id AND is_overdue = 1) as overdue_amount")
            )
            ->get();

        return view('admin.reports.officer-performance', compact('officers', 'dateFrom', 'dateTo'));
    }

    // ─── Collector Performance ────────────────────────────────────────────────

    public function collectorPerformance(Request $request)
    {
        $dateFrom = $request->date_from ?? now()->startOfMonth()->toDateString();
        $dateTo   = $request->date_to   ?? now()->toDateString();
        $branchId = $this->getBranchFilter();

        $collectors = DB::table('users')
            ->join('model_has_roles', 'users.id', '=', 'model_has_roles.model_id')
            ->join('roles', 'model_has_roles.role_id', '=', 'roles.id')
            ->where('roles.name', 'collector')
            ->when($branchId, fn($q) => $q->where('users.branch_id', $branchId))
            ->select(
                'users.id', 'users.name',
                DB::raw("(SELECT COUNT(*) FROM repayments WHERE collected_by = users.id AND status='confirmed' AND payment_date BETWEEN '{$dateFrom}' AND '{$dateTo}') as collections_count"),
                DB::raw("(SELECT COALESCE(SUM(amount),0) FROM repayments WHERE collected_by = users.id AND status='confirmed' AND payment_date BETWEEN '{$dateFrom}' AND '{$dateTo}') as collections_amount")
            )
            ->get();

        return view('admin.reports.collector-performance', compact('collectors', 'dateFrom', 'dateTo'));
    }

    // ─── Disbursement Report ──────────────────────────────────────────────────

    public function disbursements(Request $request)
    {
        $query = Loan::whereNotNull('disbursement_date')
            ->with(['borrower', 'branch', 'loanProduct', 'disbursedByUser']);

        if ($request->filled('date_from')) {
            $query->where('disbursement_date', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->where('disbursement_date', '<=', $request->date_to);
        }
        $this->applyBranchFilter($query, $request);

        $loans  = $query->orderBy('disbursement_date', 'desc')->paginate(50)->withQueryString();
        $totals = Loan::whereNotNull('disbursed_amount')
            ->selectRaw('SUM(disbursed_amount) as total_disbursed, COUNT(*) as loan_count, SUM(total_interest) as total_interest')
            ->first();

        $branches = Branch::active()->get();

        if ($request->export === 'excel') {
            return Excel::download(new LoansExport($query), 'disbursements-' . now()->format('Ymd') . '.xlsx');
        }

        return view('admin.reports.disbursements', compact('loans', 'totals', 'branches'));
    }

    // ─── Expected Collections ─────────────────────────────────────────────────

    public function expectedCollections(Request $request)
    {
        $date     = $request->date ?? now()->toDateString();
        $branchId = $this->getBranchFilter();

        $dueToday = \App\Models\RepaymentSchedule::with(['loan.borrower', 'loan.branch'])
            ->where('due_date', $date)
            ->whereIn('status', ['pending', 'partial'])
            ->when($branchId, fn($q) => $q->whereHas('loan', fn($lq) => $lq->where('branch_id', $branchId)))
            ->get();

        $summary = [
            'count'    => $dueToday->count(),
            'expected' => $dueToday->sum(fn($r) => $r->total_due - $r->total_paid),
        ];

        $branches = Branch::active()->get();

        return view('admin.reports.expected-collections', compact('dueToday', 'summary', 'date', 'branches'));
    }

    // ─── Income Report ────────────────────────────────────────────────────────

    public function income(Request $request)
    {
        $dateFrom = $request->date_from ?? now()->startOfMonth()->toDateString();
        $dateTo   = $request->date_to   ?? now()->toDateString();
        $branchId = $this->getBranchFilter();

        $incomeData = Repayment::where('status', 'confirmed')
            ->whereBetween('payment_date', [$dateFrom, $dateTo])
            ->when($branchId, fn($q) => $q->where('branch_id', $branchId))
            ->selectRaw('
                SUM(interest_paid) as total_interest_income,
                SUM(penalty_paid)  as total_penalty_income,
                SUM(fees_paid)     as total_fee_income,
                SUM(principal_paid) as total_principal_received,
                SUM(amount)        as total_received
            ')
            ->first();

        $monthlyBreakdown = Repayment::where('status', 'confirmed')
            ->whereBetween('payment_date', [$dateFrom, $dateTo])
            ->when($branchId, fn($q) => $q->where('branch_id', $branchId))
            ->selectRaw("TO_CHAR(payment_date, 'YYYY-MM') as month, SUM(interest_paid) as interest, SUM(penalty_paid) as penalty, SUM(amount) as total")
            ->groupBy('month')
            ->orderBy('month')
            ->get();

        $branches = Branch::active()->get();

        return view('admin.reports.income', compact('incomeData', 'monthlyBreakdown', 'branches', 'dateFrom', 'dateTo'));
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    private function buildSummary(?int $branchId, string $dateFrom, string $dateTo): array
    {
        $loanQuery = Loan::when($branchId, fn($q) => $q->where('branch_id', $branchId));
        $repQuery  = Repayment::where('status', 'confirmed')
            ->when($branchId, fn($q) => $q->where('branch_id', $branchId));

        return [
            'total_active_loans'    => (clone $loanQuery)->whereIn('status', ['active', 'overdue'])->count(),
            'total_overdue'         => (clone $loanQuery)->where('is_overdue', true)->count(),
            'total_disbursed'       => (clone $loanQuery)->whereNotNull('disbursed_amount')->sum('disbursed_amount'),
            'total_outstanding'     => (clone $loanQuery)->whereIn('status', ['active','overdue'])->sum('total_outstanding'),
            'interest_earned'       => (clone $repQuery)->whereBetween('payment_date', [$dateFrom, $dateTo])->sum('interest_paid'),
            'penalties_earned'      => (clone $repQuery)->whereBetween('payment_date', [$dateFrom, $dateTo])->sum('penalty_paid'),
            'collected_period'      => (clone $repQuery)->whereBetween('payment_date', [$dateFrom, $dateTo])->sum('amount'),
            'collected_today'       => (clone $repQuery)->where('payment_date', today()->toDateString())->sum('amount'),
            'due_today'             => \App\Models\RepaymentSchedule::where('due_date', today()->toDateString())
                                        ->whereIn('status', ['pending', 'partial'])->sum(DB::raw('total_due - total_paid')),
            'par'                   => $branchId
                                        ? Branch::find($branchId)?->portfolio_at_risk ?? 0
                                        : $this->globalPar(),
        ];
    }

    private function globalPar(): float
    {
        $total   = Loan::whereIn('status', ['active', 'overdue'])->sum('outstanding_principal');
        $overdue = Loan::where('is_overdue', true)->sum('outstanding_principal');
        if ($total == 0) return 0;
        return round(($overdue / $total) * 100, 2);
    }

    private function getBranchFilter(): ?int
    {
        $user = auth()->user();
        if ($user->hasRole(['branch_manager', 'loan_officer', 'collector', 'accountant'])) {
            return $user->branch_id;
        }
        return request('branch_id') ?: null;
    }

    private function applyDateFilter($query, Request $request, string $field = 'created_at'): void
    {
        if ($request->filled('date_from')) {
            $query->where($field, '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->where($field, '<=', $request->date_to);
        }
    }

    private function applyBranchFilter($query, Request $request): void
    {
        $branchId = $this->getBranchFilter() ?? $request->branch_id;
        if ($branchId) {
            $query->where('branch_id', $branchId);
        }
    }
}
