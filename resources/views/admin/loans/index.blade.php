{{-- resources/views/admin/loans/index.blade.php --}}
@extends('layouts.app')
@section('title', 'Loans')
@section('page-title', 'Loan Management')

@section('content')
@php $sym = config('bigcash.company.currency_symbol','₵'); @endphp

<!-- Filters -->
<div class="table-card mb-3" style="padding:1rem">
    <form method="GET" class="row g-2 align-items-end">
        <div class="col-md-3">
            <input type="text" name="search" class="form-control form-control-sm" placeholder="Search borrower, loan #..." value="{{ request('search') }}">
        </div>
        <div class="col-md-2">
            <select name="status" class="form-select form-select-sm">
                <option value="">All Statuses</option>
                @foreach(config('bigcash.loan.statuses') as $key => $label)
                <option value="{{ $key }}" @selected(request('status') === $key)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-2">
            <select name="product_id" class="form-select form-select-sm">
                <option value="">All Products</option>
                @foreach($products as $p)
                <option value="{{ $p->id }}" @selected(request('product_id') == $p->id)>{{ $p->name }}</option>
                @endforeach
            </select>
        </div>
        @if(auth()->user()->isSuperAdmin() || auth()->user()->hasRole('admin'))
        <div class="col-md-2">
            <select name="branch_id" class="form-select form-select-sm">
                <option value="">All Branches</option>
                @foreach($branches as $b)
                <option value="{{ $b->id }}" @selected(request('branch_id') == $b->id)>{{ $b->name }}</option>
                @endforeach
            </select>
        </div>
        @endif
        <div class="col-auto">
            <button class="btn btn-primary btn-sm"><i class="bi bi-search me-1"></i>Filter</button>
            <a href="{{ route('admin.loans.index') }}" class="btn btn-outline-secondary btn-sm">Clear</a>
        </div>
        @can('loans.create')
        <div class="col-auto ms-auto">
            <a href="{{ route('admin.loans.create') }}" class="btn btn-success btn-sm">
                <i class="bi bi-plus-lg me-1"></i>New Loan
            </a>
        </div>
        @endcan
    </form>
</div>

<!-- Loans Table -->
<div class="table-card">
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead>
                <tr>
                    <th>Loan #</th>
                    <th>Borrower</th>
                    <th>Product</th>
                    <th>Amount</th>
                    <th>Outstanding</th>
                    <th>Next Due</th>
                    <th>Status</th>
                    <th>Officer</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                @forelse($loans as $loan)
                <tr>
                    <td>
                        <a href="{{ route('admin.loans.show', $loan) }}" class="text-decoration-none fw-semibold text-primary" style="font-size:0.83rem">
                            {{ $loan->loan_number }}
                        </a>
                        <div style="font-size:0.7rem;color:#94a3b8">{{ gh_date($loan->application_date) }}</div>
                    </td>
                    <td>
                        <a href="{{ route('admin.borrowers.show', $loan->borrower_id) }}" class="text-decoration-none" style="font-size:0.83rem;font-weight:500">
                            {{ $loan->borrower->display_name }}
                        </a>
                        <div style="font-size:0.7rem;color:#94a3b8">{{ $loan->borrower->primary_phone }}</div>
                    </td>
                    <td style="font-size:0.82rem">{{ $loan->loanProduct->name }}</td>
                    <td style="font-size:0.85rem;font-weight:600">{{ money($loan->approved_amount ?? $loan->requested_amount) }}</td>
                    <td style="font-size:0.85rem;color:{{ $loan->is_overdue ? '#dc2626' : '#1e293b' }};font-weight:{{ $loan->is_overdue ? '700' : '400' }}">
                        {{ money($loan->total_outstanding) }}
                        @if($loan->is_overdue)
                            <div style="font-size:0.68rem;color:#dc2626">{{ $loan->days_past_due }}d overdue</div>
                        @endif
                    </td>
                    <td style="font-size:0.8rem;color:#d97706">{{ $loan->next_due_date ?? '—' }}</td>
                    <td>{!! $loan->status_badge !!}</td>
                    <td style="font-size:0.8rem">{{ $loan->loanOfficer->name ?? '—' }}</td>
                    <td>
                        <a href="{{ route('admin.loans.show', $loan) }}" class="btn btn-sm btn-outline-primary" style="font-size:0.72rem">
                            <i class="bi bi-eye"></i>
                        </a>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="9" class="text-center py-4 text-muted">
                        <i class="bi bi-inbox" style="font-size:2rem;display:block;opacity:0.3;margin-bottom:0.5rem"></i>
                        No loans found matching your criteria.
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($loans->hasPages())
    <div style="padding:1rem">{{ $loans->links() }}</div>
    @endif
</div>
@endsection
