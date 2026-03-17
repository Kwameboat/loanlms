@extends('layouts.app')
@section('title', 'Dashboard')
@section('page-title', 'Dashboard Overview')

@section('content')
@php $sym = config('bigcash.company.currency_symbol','₵'); @endphp

<!-- Branch Filter (Admin/Super Admin only) -->
@if(auth()->user()->isSuperAdmin() || auth()->user()->hasRole('admin'))
<div class="mb-3 d-flex align-items-center gap-2 flex-wrap">
    <form method="GET" class="d-flex gap-2 align-items-center">
        <select name="branch_id" class="form-select form-select-sm" style="width:220px" onchange="this.form.submit()">
            <option value="">All Branches</option>
            @foreach($branches as $b)
                <option value="{{ $b->id }}" @selected($branchId == $b->id)>{{ $b->name }}</option>
            @endforeach
        </select>
    </form>
    <span class="text-muted" style="font-size:0.8rem">{{ now()->format('l, d F Y') }}</span>
</div>
@endif

<!-- ── Stat Cards Row 1 ──────────────────────────────────────────────────── -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="stat-card">
            <div class="d-flex align-items-start justify-content-between">
                <div>
                    <div class="stat-value">{{ number_format($metrics['total_active_loans']) }}</div>
                    <div class="stat-label">Active Loans</div>
                </div>
                <div class="stat-icon bg-primary bg-opacity-10">
                    <i class="bi bi-cash-stack text-primary"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card">
            <div class="d-flex align-items-start justify-content-between">
                <div>
                    <div class="stat-value text-danger">{{ number_format($metrics['total_overdue_loans']) }}</div>
                    <div class="stat-label">Overdue Loans</div>
                </div>
                <div class="stat-icon bg-danger bg-opacity-10">
                    <i class="bi bi-exclamation-triangle-fill text-danger"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card">
            <div class="d-flex align-items-start justify-content-between">
                <div>
                    <div class="stat-value">{{ $sym }}{{ number_format($metrics['collections_today'], 2) }}</div>
                    <div class="stat-label">Collections Today</div>
                </div>
                <div class="stat-icon bg-success bg-opacity-10">
                    <i class="bi bi-arrow-down-circle-fill text-success"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card">
            <div class="d-flex align-items-start justify-content-between">
                <div>
                    <div class="stat-value">{{ $sym }}{{ number_format($metrics['expected_today'], 2) }}</div>
                    <div class="stat-label">Expected Today</div>
                </div>
                <div class="stat-icon bg-warning bg-opacity-10">
                    <i class="bi bi-calendar-check text-warning"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ── Stat Cards Row 2 ──────────────────────────────────────────────────── -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="stat-card">
            <div class="d-flex align-items-start justify-content-between">
                <div>
                    <div class="stat-value">{{ $sym }}{{ number_format($metrics['total_outstanding_principal'], 2) }}</div>
                    <div class="stat-label">Outstanding Principal</div>
                </div>
                <div class="stat-icon bg-info bg-opacity-10">
                    <i class="bi bi-bank2 text-info"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card">
            <div class="d-flex align-items-start justify-content-between">
                <div>
                    <div class="stat-value {{ $metrics['par30'] > 10 ? 'text-danger' : ($metrics['par30'] > 5 ? 'text-warning' : 'text-success') }}">
                        {{ $metrics['par30'] }}%
                    </div>
                    <div class="stat-label">Portfolio at Risk (PAR30)</div>
                </div>
                <div class="stat-icon {{ $metrics['par30'] > 10 ? 'bg-danger' : 'bg-success' }} bg-opacity-10">
                    <i class="bi bi-shield-exclamation {{ $metrics['par30'] > 10 ? 'text-danger' : 'text-success' }}"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card">
            <div class="d-flex align-items-start justify-content-between">
                <div>
                    <div class="stat-value">{{ $sym }}{{ number_format($metrics['disbursed_this_month'], 2) }}</div>
                    <div class="stat-label">Disbursed This Month</div>
                </div>
                <div class="stat-icon bg-primary bg-opacity-10">
                    <i class="bi bi-send-fill text-primary"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card">
            <div class="d-flex align-items-start justify-content-between">
                <div>
                    <div class="stat-value">{{ number_format($metrics['total_active_borrowers']) }}</div>
                    <div class="stat-label">Active Borrowers</div>
                </div>
                <div class="stat-icon bg-secondary bg-opacity-10">
                    <i class="bi bi-people-fill text-secondary"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ── Charts Row ──────────────────────────────────────────────────────── -->
<div class="row g-3 mb-4">
    <div class="col-lg-8">
        <div class="table-card" style="padding:1.25rem">
            <div class="table-card-header px-0 pt-0">
                <span style="font-weight:600;font-size:0.9rem">Daily Collections — {{ now()->format('F Y') }}</span>
            </div>
            <canvas id="collectionsChart" height="120"></canvas>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="table-card h-100" style="padding:1.25rem">
            <div style="font-weight:600;font-size:0.9rem;margin-bottom:1rem">Income Breakdown (Month)</div>
            <canvas id="incomeChart" height="180"></canvas>
        </div>
    </div>
</div>

<!-- ── Tables Row ─────────────────────────────────────────────────────── -->
<div class="row g-3 mb-4">

    <!-- Recent Loans -->
    <div class="col-lg-7">
        <div class="table-card">
            <div class="table-card-header">
                <span style="font-weight:600;font-size:0.9rem">Recent Loans</span>
                <a href="{{ route('admin.loans.index') }}" class="btn btn-sm btn-outline-primary" style="font-size:0.78rem">View All</a>
            </div>
            <div class="table-responsive">
                <table class="table table-sm table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Borrower</th>
                            <th>Loan #</th>
                            <th>Amount</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($recentLoans as $loan)
                        <tr>
                            <td>
                                <a href="{{ route('admin.loans.show', $loan) }}" class="text-decoration-none fw-semibold" style="font-size:0.83rem">
                                    {{ $loan->borrower->display_name }}
                                </a>
                                <div style="font-size:0.72rem;color:#94a3b8">{{ $loan->loanProduct->name }}</div>
                            </td>
                            <td style="font-size:0.8rem;color:#64748b">{{ $loan->loan_number }}</td>
                            <td style="font-size:0.85rem">{{ $sym }}{{ number_format($loan->approved_amount ?? $loan->requested_amount, 0) }}</td>
                            <td>{!! $loan->status_badge !!}</td>
                        </tr>
                        @empty
                        <tr><td colspan="4" class="text-center text-muted py-3" style="font-size:0.83rem">No loans yet</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Overdue Loans -->
    <div class="col-lg-5">
        <div class="table-card">
            <div class="table-card-header">
                <span style="font-weight:600;font-size:0.9rem">⚠️ Overdue Loans</span>
                <a href="{{ route('admin.reports.arrears') }}" class="btn btn-sm btn-outline-danger" style="font-size:0.78rem">Full Report</a>
            </div>
            <div class="table-responsive">
                <table class="table table-sm table-hover mb-0">
                    <thead>
                        <tr><th>Borrower</th><th>Outstanding</th><th>DPD</th></tr>
                    </thead>
                    <tbody>
                        @forelse($overdueLoans as $loan)
                        <tr>
                            <td>
                                <a href="{{ route('admin.loans.show', $loan) }}" class="text-decoration-none fw-semibold" style="font-size:0.83rem">
                                    {{ $loan->borrower->display_name }}
                                </a>
                                <div style="font-size:0.7rem;color:#94a3b8">{{ $loan->loan_number }}</div>
                            </td>
                            <td style="font-size:0.83rem">{{ $sym }}{{ number_format($loan->total_outstanding, 0) }}</td>
                            <td><span class="badge bg-danger">{{ $loan->days_past_due }}d</span></td>
                        </tr>
                        @empty
                        <tr><td colspan="3" class="text-center text-muted py-3 text-success" style="font-size:0.83rem"><i class="bi bi-check-circle me-1"></i>No overdue loans</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- ── Branch Summary (Super Admin) ──────────────────────────────────────── -->
@if(count($branchSummaries) > 0)
<div class="table-card mb-4">
    <div class="table-card-header">
        <span style="font-weight:600;font-size:0.9rem">Branch Summary</span>
    </div>
    <div class="table-responsive">
        <table class="table table-sm table-hover mb-0">
            <thead>
                <tr><th>Branch</th><th>Active Loans</th><th>Outstanding</th><th>PAR30</th></tr>
            </thead>
            <tbody>
                @foreach($branchSummaries as $branch)
                <tr>
                    <td style="font-size:0.85rem;font-weight:500">{{ $branch->name }}</td>
                    <td>{{ number_format($branch->active_loan_count) }}</td>
                    <td>{{ $sym }}{{ number_format($branch->total_outstanding, 0) }}</td>
                    <td>
                        <span class="badge {{ $branch->par > 10 ? 'bg-danger' : ($branch->par > 5 ? 'bg-warning text-dark' : 'bg-success') }}">
                            {{ $branch->par }}%
                        </span>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endif

@endsection

@push('scripts')
<script>
// Collections Chart
const labels = @json($collectionPerformance->keys());
const values = @json($collectionPerformance->values());

new Chart(document.getElementById('collectionsChart'), {
    type: 'bar',
    data: {
        labels,
        datasets: [{
            label: 'Collections (₵)',
            data: values,
            backgroundColor: 'rgba(37,99,235,0.8)',
            borderRadius: 4,
        }]
    },
    options: {
        responsive: true, maintainAspectRatio: true,
        plugins: { legend: { display: false } },
        scales: {
            y: { ticks: { callback: v => '₵' + v.toLocaleString(), font: { size: 11 } } },
            x: { ticks: { font: { size: 10 } } }
        }
    }
});

// Income Breakdown Donut
new Chart(document.getElementById('incomeChart'), {
    type: 'doughnut',
    data: {
        labels: ['Principal', 'Interest', 'Penalties', 'Fees'],
        datasets: [{
            data: [
                {{ $metrics['collections_this_month'] - $metrics['total_interest_earned_month'] - $metrics['total_penalty_earned_month'] }},
                {{ $metrics['total_interest_earned_month'] }},
                {{ $metrics['total_penalty_earned_month'] }},
                0
            ],
            backgroundColor: ['#2563eb','#16a34a','#dc2626','#d97706'],
            borderWidth: 2,
        }]
    },
    options: {
        responsive: true, maintainAspectRatio: true,
        plugins: {
            legend: { position: 'bottom', labels: { font: { size: 11 } } }
        },
        cutout: '60%'
    }
});
</script>
@endpush
