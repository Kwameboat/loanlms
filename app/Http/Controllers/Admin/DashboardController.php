<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Loan;
use App\Models\Borrower;
use App\Models\Repayment;
use App\Models\Branch;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $user      = Auth::user();
        $branchId  = $user->isSuperAdmin() || $user->hasRole('admin')
                     ? ($request->branch_id ?? null)
                     : $user->branch_id;

        $today = today();

        // ── Core Metrics ──────────────────────────────────────────────────────

        $loansQuery = Loan::query()->when($branchId, fn($q) => $q->where('branch_id', $branchId));

        $metrics = [
            'total_active_loans'   => (clone $loansQuery)->whereIn('status', ['active', 'overdue', 'disbursed'])->count(),
            'total_overdue_loans'  => (clone $loansQuery)->where('is_overdue', true)->count(),
            'due_today_count'      => (clone $loansQuery)->dueToday()->count(),
            'disbursed_this_month' => (clone $loansQuery)->whereMonth('disbursement_date', $today->month)
                                        ->whereYear('disbursement_date', $today->year)
                                        ->sum('disbursed_amount'),
        ];

        // ── Repayment Metrics ─────────────────────────────────────────────────

        $repaymentsQuery = Repayment::where('status', 'confirmed')
            ->when($branchId, fn($q) => $q->where('branch_id', $branchId));

        $metrics['collections_today']    = (clone $repaymentsQuery)->whereDate('payment_date', $today)->sum('amount');
        $metrics['collections_this_month'] = (clone $repaymentsQuery)
            ->whereMonth('payment_date', $today->month)
            ->whereYear('payment_date', $today->year)
            ->sum('amount');

        // Expected today
        $metrics['expected_today'] = \App\Models\RepaymentSchedule::query()
            ->whereHas('loan', fn($q) => $q->when($branchId, fn($q2) => $q2->where('branch_id', $branchId)))
            ->where('due_date', $today)
            ->whereIn('status', ['pending', 'partial'])
            ->sum(DB::raw('total_due - total_paid'));

        // ── Portfolio ─────────────────────────────────────────────────────────

        $metrics['total_outstanding_principal'] = (clone $loansQuery)
            ->whereIn('status', ['active', 'overdue'])->sum('outstanding_principal');
        $metrics['total_interest_earned_month']  = (clone $repaymentsQuery)
            ->whereMonth('payment_date', $today->month)->sum('interest_paid');
        $metrics['total_penalty_earned_month']   = (clone $repaymentsQuery)
            ->whereMonth('payment_date', $today->month)->sum('penalty_paid');

        // ── Portfolio At Risk ─────────────────────────────────────────────────

        $overdueOutstanding = (clone $loansQuery)->where('is_overdue', true)->sum('outstanding_principal');
        $totalOutstanding   = $metrics['total_outstanding_principal'];
        $metrics['par30']   = $totalOutstanding > 0 ? round(($overdueOutstanding / $totalOutstanding) * 100, 2) : 0;

        // ── Borrower Stats ────────────────────────────────────────────────────

        $metrics['total_active_borrowers'] = Borrower::query()
            ->when($branchId, fn($q) => $q->where('branch_id', $branchId))
            ->where('status', 'active')->count();

        $metrics['new_borrowers_month'] = Borrower::query()
            ->when($branchId, fn($q) => $q->where('branch_id', $branchId))
            ->whereMonth('created_at', $today->month)
            ->whereYear('created_at', $today->year)
            ->count();

        // ── Recent Activity ───────────────────────────────────────────────────

        $recentLoans = Loan::with(['borrower', 'loanProduct'])
            ->when($branchId, fn($q) => $q->where('branch_id', $branchId))
            ->latest()->take(8)->get();

        $recentRepayments = Repayment::with(['loan', 'borrower'])
            ->when($branchId, fn($q) => $q->where('branch_id', $branchId))
            ->where('status', 'confirmed')
            ->latest()->take(8)->get();

        // ── Overdue Summary ───────────────────────────────────────────────────

        $overdueLoans = Loan::with(['borrower'])
            ->when($branchId, fn($q) => $q->where('branch_id', $branchId))
            ->where('is_overdue', true)
            ->orderBy('days_past_due', 'desc')
            ->take(10)
            ->get();

        // ── Branch Summary (Super Admin / Admin) ──────────────────────────────

        $branchSummaries = [];
        if ($user->isSuperAdmin() || $user->hasRole('admin')) {
            $branchSummaries = Branch::active()
                ->withCount(['loans as active_loan_count' => fn($q) => $q->whereIn('status', ['active', 'overdue'])])
                ->with(['loans' => fn($q) => $q->select('branch_id', 'outstanding_principal', 'status')
                    ->whereIn('status', ['active', 'overdue'])])
                ->get()
                ->map(function ($branch) {
                    $branch->total_outstanding = $branch->loans->sum('outstanding_principal');
                    $overdueP = $branch->loans->where('is_overdue', true)->sum('outstanding_principal');
                    $total    = $branch->total_outstanding;
                    $branch->par = $total > 0 ? round(($overdueP / $total) * 100, 2) : 0;
                    return $branch;
                });
        }

        // ── Collection Performance (This Month) ──────────────────────────────

        $collectionPerformance = Repayment::select(
                DB::raw("CAST(payment_date AS DATE) as date"),
                DB::raw('SUM(amount) as total')
            )
            ->where('status', 'confirmed')
            ->when($branchId, fn($q) => $q->where('branch_id', $branchId))
            ->whereMonth('payment_date', $today->month)
            ->whereYear('payment_date', $today->year)
            ->groupBy(DB::raw("CAST(payment_date AS DATE)"))
            ->orderBy('date')
            ->pluck('total', 'date');

        $branches = $user->isSuperAdmin() || $user->hasRole('admin')
            ? Branch::active()->get()
            : collect();

        return view('admin.dashboard.index', compact(
            'metrics', 'recentLoans', 'recentRepayments', 'overdueLoans',
            'branchSummaries', 'collectionPerformance', 'branches', 'branchId'
        ));
    }
}
