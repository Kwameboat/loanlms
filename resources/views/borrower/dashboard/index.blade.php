@extends('layouts.borrower')
@section('title', 'My Dashboard')

@section('content')
@php $sym = config('bigcash.company.currency_symbol','₵'); @endphp

<!-- Welcome -->
<div style="margin-bottom:1rem">
    <div style="font-size:1rem;font-weight:700;color:#1e293b">Hello, {{ $borrower->first_name }} 👋</div>
    <div style="font-size:0.78rem;color:#64748b">{{ now()->format('l, d F Y') }}</div>
</div>

<!-- Summary Hero Card -->
<div class="loan-card mb-3">
    <div class="row g-2">
        <div class="col-6">
            <div class="label">Total Outstanding</div>
            <div class="amount">{{ $sym }}{{ number_format($summary['total_outstanding'], 2) }}</div>
        </div>
        <div class="col-6 text-end">
            <div class="label">Active Loans</div>
            <div style="font-size:2rem;font-weight:800">{{ $summary['active_loans'] }}</div>
        </div>
    </div>
    <hr style="border-color:rgba(255,255,255,0.2);margin:0.75rem 0">
    <div class="row g-0 text-center">
        <div class="col-4">
            <div style="font-size:0.68rem;opacity:0.7">Next Due</div>
            <div style="font-weight:700;font-size:0.9rem">{{ $sym }}{{ number_format($summary['next_due_amount'], 2) }}</div>
        </div>
        <div class="col-4" style="border-left:1px solid rgba(255,255,255,0.2);border-right:1px solid rgba(255,255,255,0.2)">
            <div style="font-size:0.68rem;opacity:0.7">Overdue</div>
            <div style="font-weight:700;font-size:0.9rem;color:{{ $summary['overdue_loans'] > 0 ? '#fca5a5' : '#86efac' }}">
                {{ $summary['overdue_loans'] }}
            </div>
        </div>
        <div class="col-4">
            <div style="font-size:0.68rem;opacity:0.7">ID</div>
            <div style="font-weight:700;font-size:0.9rem">{{ $borrower->borrower_number }}</div>
        </div>
    </div>
</div>

<!-- Overdue Alert -->
@if($summary['overdue_loans'] > 0)
<div class="alert alert-danger d-flex align-items-center gap-2 mb-3" style="border-radius:12px;font-size:0.85rem">
    <i class="bi bi-exclamation-triangle-fill fs-5"></i>
    <div>
        <strong>{{ $summary['overdue_loans'] }} overdue loan(s)!</strong>
        Penalties are accruing daily. Please make payment immediately.
    </div>
</div>
@endif

<!-- Upcoming Installments -->
@if($nextDueInstallments->count())
<div class="portal-card mb-3">
    <div style="padding:0.9rem 1rem;border-bottom:1px solid #f1f5f9;font-weight:600;font-size:0.88rem">
        <i class="bi bi-calendar3 me-2 text-primary"></i>Upcoming Payments
    </div>
    @foreach($nextDueInstallments->take(3) as $installment)
    <div style="padding:0.75rem 1rem;@if(!$loop->last) border-bottom:1px solid #f8fafc; @endif">
        <div class="d-flex justify-content-between align-items-start">
            <div>
                <div style="font-size:0.83rem;font-weight:600;color:#1e293b">{{ $installment->loan->loan_number }}</div>
                <div style="font-size:0.72rem;color:#64748b">{{ $installment->loan->loanProduct->name ?? '' }}</div>
                <div style="font-size:0.75rem;color:{{ $installment->due_date->isPast() ? '#dc2626' : '#d97706' }};margin-top:2px">
                    <i class="bi bi-calendar-event me-1"></i>
                    Due: {{ $installment->due_date->format('d M Y') }}
                    @if($installment->due_date->isPast())
                        <span class="badge bg-danger ms-1" style="font-size:0.65rem">OVERDUE</span>
                    @elseif($installment->due_date->isToday())
                        <span class="badge bg-warning text-dark ms-1" style="font-size:0.65rem">TODAY</span>
                    @elseif($installment->due_date->diffInDays(now()) <= 3)
                        <span class="badge bg-warning text-dark ms-1" style="font-size:0.65rem">SOON</span>
                    @endif
                </div>
            </div>
            <div class="text-end">
                <div style="font-size:1rem;font-weight:700;color:#1e293b">{{ $sym }}{{ number_format($installment->balance_due, 2) }}</div>
                @if($installment->loan->borrower->email)
                <a href="{{ route('borrower.loans.show', $installment->loan) }}"
                   class="btn btn-primary btn-sm mt-1" style="font-size:0.72rem;border-radius:8px;padding:0.25rem 0.7rem">
                    Pay Now
                </a>
                @endif
            </div>
        </div>
    </div>
    @endforeach
</div>
@endif

<!-- Active Loans -->
<div style="font-weight:600;font-size:0.88rem;color:#1e293b;margin-bottom:0.6rem">
    <i class="bi bi-cash-stack me-1 text-primary"></i>My Active Loans
</div>
@forelse($activeLoans as $loan)
<div class="portal-card mb-2">
    <div style="padding:0.9rem 1rem">
        <div class="d-flex justify-content-between align-items-start mb-2">
            <div>
                <div style="font-size:0.85rem;font-weight:700">{{ $loan->loan_number }}</div>
                <div style="font-size:0.72rem;color:#64748b">{{ $loan->loanProduct->name }}</div>
            </div>
            {!! $loan->status_badge !!}
        </div>
        <div class="row g-2" style="font-size:0.8rem">
            <div class="col-4">
                <div style="color:#64748b;font-size:0.68rem">Disbursed</div>
                <div style="font-weight:600">{{ $sym }}{{ number_format($loan->disbursed_amount, 0) }}</div>
            </div>
            <div class="col-4">
                <div style="color:#64748b;font-size:0.68rem">Outstanding</div>
                <div style="font-weight:600;color:#dc2626">{{ $sym }}{{ number_format($loan->total_outstanding, 0) }}</div>
            </div>
            <div class="col-4">
                <div style="color:#64748b;font-size:0.68rem">Next Due</div>
                <div style="font-weight:600;color:#d97706">{{ $loan->next_due_date ?? '—' }}</div>
            </div>
        </div>
        <!-- Progress bar -->
        @php
            $progress = $loan->total_repayable > 0
                ? min(100, round(($loan->total_paid / $loan->total_repayable) * 100))
                : 0;
        @endphp
        <div style="margin-top:0.75rem">
            <div class="d-flex justify-content-between" style="font-size:0.7rem;color:#64748b;margin-bottom:3px">
                <span>Repaid {{ $progress }}%</span>
                <span>{{ $loan->paid_installments }}/{{ $loan->total_installments }} installments</span>
            </div>
            <div style="height:6px;background:#e2e8f0;border-radius:3px;overflow:hidden">
                <div style="height:100%;width:{{ $progress }}%;background:{{ $progress < 50 ? '#d97706' : '#16a34a' }};border-radius:3px;transition:width 0.3s"></div>
            </div>
        </div>
        <div class="d-flex gap-2 mt-2">
            <a href="{{ route('borrower.loans.show', $loan) }}" class="btn btn-outline-primary btn-sm flex-fill" style="font-size:0.75rem;border-radius:8px">
                <i class="bi bi-eye me-1"></i>View Details
            </a>
            <a href="{{ route('borrower.loans.schedule', $loan) }}" class="btn btn-outline-secondary btn-sm flex-fill" style="font-size:0.75rem;border-radius:8px">
                <i class="bi bi-table me-1"></i>Schedule
            </a>
        </div>
    </div>
</div>
@empty
<div class="portal-card" style="padding:2rem;text-align:center;color:#94a3b8">
    <i class="bi bi-cash-stack" style="font-size:2.5rem;opacity:0.4;display:block;margin-bottom:0.5rem"></i>
    No active loans. <a href="{{ route('borrower.loans.apply') }}" class="text-primary">Apply for a loan</a>
</div>
@endforelse

<!-- Quick Actions -->
<div class="d-flex gap-2 mt-3">
    <a href="{{ route('borrower.loans.apply') }}" class="btn btn-primary flex-fill" style="border-radius:12px;font-size:0.85rem;padding:0.7rem">
        <i class="bi bi-plus-circle me-1"></i>Apply for Loan
    </a>
    <a href="{{ route('borrower.payments.index') }}" class="btn btn-outline-secondary flex-fill" style="border-radius:12px;font-size:0.85rem;padding:0.7rem">
        <i class="bi bi-receipt me-1"></i>Payment History
    </a>
</div>

<!-- Recent Payments -->
@if($recentPayments->count())
<div style="font-weight:600;font-size:0.88rem;color:#1e293b;margin:1.2rem 0 0.6rem">
    <i class="bi bi-clock-history me-1 text-success"></i>Recent Payments
</div>
<div class="portal-card">
    @foreach($recentPayments as $payment)
    <div style="padding:0.7rem 1rem;@if(!$loop->last) border-bottom:1px solid #f8fafc; @endif display:flex;justify-content:space-between;align-items:center">
        <div>
            <div style="font-size:0.82rem;font-weight:600;color:#1e293b">{{ $sym }}{{ number_format($payment->amount, 2) }}</div>
            <div style="font-size:0.7rem;color:#64748b">{{ $payment->receipt_number }} · {{ $payment->payment_date->format('d M Y') }}</div>
        </div>
        <div class="d-flex align-items-center gap-2">
            <span class="badge bg-success bg-opacity-10 text-success" style="font-size:0.68rem">{{ strtoupper(str_replace('_',' ',$payment->payment_method)) }}</span>
            <a href="{{ route('borrower.payments.receipt', $payment) }}" class="btn btn-link p-0" style="font-size:0.78rem">
                <i class="bi bi-download"></i>
            </a>
        </div>
    </div>
    @endforeach
</div>
@endif

@endsection
