@extends('layouts.app')
@section('title', 'Loan ' . $loan->loan_number)
@section('page-title', $loan->loan_number)

@section('content')
@php $sym = config('bigcash.company.currency_symbol','₵'); @endphp

<!-- Breadcrumb & Actions -->
<div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
    <nav style="font-size:0.82rem">
        <a href="{{ route('admin.loans.index') }}" class="text-decoration-none text-muted">Loans</a>
        <span class="mx-2 text-muted">/</span>
        <span class="fw-semibold">{{ $loan->loan_number }}</span>
    </nav>
    <div class="d-flex gap-2 flex-wrap">
        @if($loan->status === 'draft') @can('loans.create')
            <form method="POST" action="{{ route('admin.loans.submit', $loan) }}" class="d-inline">@csrf
                <button class="btn btn-info btn-sm"><i class="bi bi-send me-1"></i>Submit for Review</button>
            </form>
        @endcan @endif

        @if(in_array($loan->status, ['submitted','under_review'])) @can('loans.recommend')
            <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#recommendModal">
                <i class="bi bi-check-circle me-1"></i>Recommend
            </button>
        @endcan @endif

        @if(in_array($loan->status, ['submitted','under_review','recommended'])) @can('loans.approve')
            <button class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#approveModal">
                <i class="bi bi-check2-all me-1"></i>Approve
            </button>
            <button class="btn btn-danger btn-sm" data-bs-toggle="modal" data-bs-target="#rejectModal">
                <i class="bi bi-x-circle me-1"></i>Reject
            </button>
        @endcan @endif

        @if($loan->status === 'approved') @can('loans.disburse')
            <button class="btn btn-warning btn-sm text-dark" data-bs-toggle="modal" data-bs-target="#disburseModal">
                <i class="bi bi-cash me-1"></i>Disburse
            </button>
        @endcan @endif

        @if($loan->isActive()) @can('repayments.create')
            <a href="{{ route('admin.repayments.create', ['loan_id' => $loan->id]) }}" class="btn btn-outline-success btn-sm">
                <i class="bi bi-plus-circle me-1"></i>Record Payment
            </a>
        @endcan @endif

        @can('ai.credit_analysis')
        <button class="btn btn-outline-purple btn-sm" id="btn-ai-assess" style="background:linear-gradient(135deg,#ede9fe,#ddd6fe);color:#5b21b6;border:1px solid #c4b5fd">
            <i class="bi bi-stars me-1"></i>AI Assessment
        </button>
        @endcan

        <a href="{{ route('admin.loans.schedule_pdf', $loan) }}" class="btn btn-outline-secondary btn-sm" target="_blank">
            <i class="bi bi-download me-1"></i>Schedule PDF
        </a>
    </div>
</div>

<!-- Status Banner -->
<div class="alert @if($loan->is_overdue) alert-danger @elseif($loan->status === 'completed') alert-success @elseif($loan->status === 'approved') alert-warning @else alert-info @endif d-flex align-items-center gap-2 mb-3">
    <i class="bi bi-info-circle-fill"></i>
    <div>
        <strong>Status: {!! $loan->status_badge !!}</strong>
        @if($loan->is_overdue) — <strong>{{ $loan->days_past_due }} days past due</strong>. Penalties accruing. @endif
        @if($loan->rejected_at) — {{ $loan->rejection_reason }} @endif
    </div>
</div>

<!-- AI Assessment Result Panel -->
<div id="ai-assessment-panel" class="mb-3" style="display:none">
    <div class="table-card" style="border:2px solid #c4b5fd">
        <div style="padding:1rem;background:linear-gradient(135deg,#ede9fe,#f3f4f6);border-bottom:1px solid #e2e8f0">
            <h6 class="mb-0" style="color:#5b21b6"><i class="bi bi-stars me-2"></i>BigCashAI Credit Assessment</h6>
        </div>
        <div style="padding:1rem" id="ai-assessment-content">
            <div class="text-center py-3"><div class="spinner-border spinner-border-sm text-primary"></div> Analyzing...</div>
        </div>
    </div>
</div>

<div class="row g-3">
    <!-- Left Column -->
    <div class="col-lg-8">

        <!-- Loan Overview -->
        <div class="table-card mb-3">
            <div class="table-card-header"><span class="fw-semibold">Loan Details</span></div>
            <div style="padding:1rem">
                <div class="row g-3">
                    <div class="col-6 col-md-3">
                        <div style="font-size:0.72rem;color:#64748b;text-transform:uppercase">Requested</div>
                        <div style="font-weight:700">{{ money($loan->requested_amount) }}</div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div style="font-size:0.72rem;color:#64748b;text-transform:uppercase">Approved</div>
                        <div style="font-weight:700;color:#16a34a">{{ money($loan->approved_amount ?? 0) }}</div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div style="font-size:0.72rem;color:#64748b;text-transform:uppercase">Disbursed</div>
                        <div style="font-weight:700;color:#2563eb">{{ money($loan->disbursed_amount ?? 0) }}</div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div style="font-size:0.72rem;color:#64748b;text-transform:uppercase">Outstanding</div>
                        <div style="font-weight:700;color:#dc2626">{{ money($loan->total_outstanding) }}</div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div style="font-size:0.72rem;color:#64748b;text-transform:uppercase">Interest Type</div>
                        <div style="font-weight:600">{{ ucfirst($loan->interest_type) }}</div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div style="font-size:0.72rem;color:#64748b;text-transform:uppercase">Interest Rate</div>
                        <div style="font-weight:600">{{ $loan->interest_rate }}% p.a.</div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div style="font-size:0.72rem;color:#64748b;text-transform:uppercase">Term</div>
                        <div style="font-weight:600">{{ $loan->term_months }} months</div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div style="font-size:0.72rem;color:#64748b;text-transform:uppercase">Frequency</div>
                        <div style="font-weight:600">{{ ucfirst($loan->repayment_frequency) }}</div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div style="font-size:0.72rem;color:#64748b;text-transform:uppercase">Installment</div>
                        <div style="font-weight:700">{{ money($loan->installment_amount) }}</div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div style="font-size:0.72rem;color:#64748b;text-transform:uppercase">Total Repayable</div>
                        <div style="font-weight:600">{{ money($loan->total_repayable) }}</div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div style="font-size:0.72rem;color:#64748b;text-transform:uppercase">Disbursed On</div>
                        <div style="font-weight:600">{{ gh_date($loan->disbursement_date) }}</div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div style="font-size:0.72rem;color:#64748b;text-transform:uppercase">Maturity</div>
                        <div style="font-weight:600">{{ gh_date($loan->maturity_date) }}</div>
                    </div>
                </div>
                @if($loan->loan_purpose)
                <div class="mt-3 pt-3" style="border-top:1px solid #f1f5f9">
                    <div style="font-size:0.72rem;color:#64748b;text-transform:uppercase;margin-bottom:0.3rem">Loan Purpose</div>
                    <div style="font-size:0.88rem">{{ $loan->loan_purpose }}</div>
                </div>
                @endif
            </div>
        </div>

        <!-- Repayment Schedule (first 6 rows) -->
        @if($loan->schedule->count())
        <div class="table-card mb-3">
            <div class="table-card-header">
                <span class="fw-semibold">Repayment Schedule</span>
                <a href="{{ route('admin.loans.schedule', $loan) }}" class="btn btn-sm btn-outline-primary" style="font-size:0.75rem">View Full</a>
            </div>
            <div class="table-responsive">
                <table class="table table-sm mb-0">
                    <thead>
                        <tr><th>#</th><th>Due Date</th><th>Principal</th><th>Interest</th><th>Fees</th><th>Total</th><th>Paid</th><th>Status</th></tr>
                    </thead>
                    <tbody>
                        @foreach($loan->schedule->take(8) as $s)
                        <tr style="{{ $s->is_overdue ? 'background:#fff1f2' : ($s->status === 'paid' ? 'background:#f0fdf4' : '') }}">
                            <td>{{ $s->installment_number }}</td>
                            <td style="font-size:0.8rem">{{ gh_date($s->due_date) }}</td>
                            <td>{{ money($s->principal_due) }}</td>
                            <td>{{ money($s->interest_due) }}</td>
                            <td>{{ money($s->fees_due) }}</td>
                            <td style="font-weight:600">{{ money($s->total_due) }}</td>
                            <td style="color:#16a34a">{{ money($s->total_paid) }}</td>
                            <td>
                                <span class="badge bg-{{ $s->status === 'paid' ? 'success' : ($s->status === 'overdue' ? 'danger' : ($s->status === 'partial' ? 'warning' : 'secondary')) }}" style="font-size:0.68rem">
                                    {{ ucfirst($s->status) }}
                                </span>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        @endif

        <!-- Recent Repayments -->
        @if($loan->repayments->count())
        <div class="table-card mb-3">
            <div class="table-card-header">
                <span class="fw-semibold">Payment History</span>
                @can('repayments.create')
                <a href="{{ route('admin.repayments.create', ['loan_id' => $loan->id]) }}" class="btn btn-sm btn-success" style="font-size:0.75rem">
                    + Record Payment
                </a>
                @endcan
            </div>
            <div class="table-responsive">
                <table class="table table-sm mb-0">
                    <thead>
                        <tr><th>Receipt</th><th>Date</th><th>Amount</th><th>Method</th><th>Collector</th><th>Status</th><th></th></tr>
                    </thead>
                    <tbody>
                        @foreach($loan->repayments->take(10) as $r)
                        <tr>
                            <td style="font-size:0.8rem;color:#2563eb">
                                <a href="{{ route('admin.repayments.receipt', $r) }}" target="_blank">{{ $r->receipt_number }}</a>
                            </td>
                            <td style="font-size:0.8rem">{{ gh_date($r->payment_date) }}</td>
                            <td style="font-weight:600">{{ money($r->amount) }}</td>
                            <td style="font-size:0.78rem">{{ strtoupper(str_replace('_',' ',$r->payment_method)) }}</td>
                            <td style="font-size:0.78rem">{{ $r->collectedBy->name ?? '—' }}</td>
                            <td>
                                <span class="badge bg-{{ $r->status === 'confirmed' ? 'success' : ($r->status === 'reversed' ? 'danger' : 'warning') }}" style="font-size:0.68rem">
                                    {{ ucfirst($r->status) }}
                                </span>
                            </td>
                            <td>
                                @can('repayments.reverse')
                                @if($r->status === 'confirmed')
                                <button class="btn btn-link btn-sm p-0 text-danger" data-bs-toggle="modal" data-bs-target="#reverseModal{{ $r->id }}" title="Reverse">
                                    <i class="bi bi-arrow-counterclockwise"></i>
                                </button>
                                <!-- Reverse Modal -->
                                <div class="modal fade" id="reverseModal{{ $r->id }}" tabindex="-1">
                                    <div class="modal-dialog modal-sm">
                                        <div class="modal-content">
                                            <div class="modal-header"><h6 class="modal-title">Reverse Payment</h6><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                                            <form method="POST" action="{{ route('admin.repayments.reverse', $r) }}">@csrf
                                                <div class="modal-body">
                                                    <p style="font-size:0.85rem">Reverse <strong>{{ money($r->amount) }}</strong> ({{ $r->receipt_number }})?</p>
                                                    <textarea name="reason" class="form-control form-control-sm" rows="2" placeholder="Reason for reversal..." required></textarea>
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="submit" class="btn btn-danger btn-sm">Reverse</button>
                                                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                                @endif
                                @endcan
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        @endif

    </div>

    <!-- Right Column -->
    <div class="col-lg-4">

        <!-- Borrower Info -->
        <div class="table-card mb-3">
            <div class="table-card-header"><span class="fw-semibold">Borrower</span></div>
            <div style="padding:1rem">
                <div class="d-flex align-items-center gap-2 mb-3">
                    <div class="rounded-circle d-flex align-items-center justify-content-center bg-primary text-white fw-bold"
                         style="width:42px;height:42px;font-size:0.9rem;flex-shrink:0">
                        {{ $loan->borrower->initials ?? initials($loan->borrower->display_name) }}
                    </div>
                    <div>
                        <div style="font-weight:700;font-size:0.9rem">{{ $loan->borrower->full_name }}</div>
                        <div style="font-size:0.75rem;color:#64748b">{{ $loan->borrower->borrower_number }}</div>
                    </div>
                </div>
                <div class="d-flex flex-column gap-1" style="font-size:0.82rem">
                    <div><i class="bi bi-phone me-2 text-muted"></i>{{ $loan->borrower->primary_phone }}</div>
                    @if($loan->borrower->email)
                    <div><i class="bi bi-envelope me-2 text-muted"></i>{{ $loan->borrower->email }}</div>
                    @endif
                    <div><i class="bi bi-briefcase me-2 text-muted"></i>{{ $loan->borrower->occupation ?? 'N/A' }}</div>
                    <div><i class="bi bi-currency-exchange me-2 text-muted"></i>{{ money($loan->borrower->monthly_income ?? 0) }}/mo</div>
                </div>
                @if($loan->debt_to_income_ratio)
                <div class="mt-2 p-2 rounded" style="background:#f8fafc;font-size:0.8rem">
                    <span class="text-muted">DTI Ratio:</span>
                    <span class="fw-bold ms-1 {{ $loan->debt_to_income_ratio > 40 ? 'text-danger' : 'text-success' }}">
                        {{ number_format($loan->debt_to_income_ratio, 1) }}%
                    </span>
                </div>
                @endif
                <a href="{{ route('admin.borrowers.show', $loan->borrower) }}" class="btn btn-sm btn-outline-primary w-100 mt-2" style="font-size:0.78rem">
                    View Full Profile
                </a>
            </div>
        </div>

        <!-- Approval Chain -->
        <div class="table-card mb-3">
            <div class="table-card-header"><span class="fw-semibold">Approval Chain</span></div>
            <div style="padding:1rem;font-size:0.82rem">
                <div class="d-flex flex-column gap-2">
                    <div class="d-flex justify-content-between">
                        <span class="text-muted">Created by</span>
                        <span class="fw-semibold">{{ $loan->createdBy->name }}</span>
                    </div>
                    <div class="d-flex justify-content-between">
                        <span class="text-muted">Loan Officer</span>
                        <span class="fw-semibold">{{ $loan->loanOfficer->name }}</span>
                    </div>
                    @if($loan->recommendedBy)
                    <div class="d-flex justify-content-between">
                        <span class="text-muted">Recommended by</span>
                        <span class="fw-semibold text-primary">{{ $loan->recommendedBy->name }}</span>
                    </div>
                    @endif
                    @if($loan->approvedBy)
                    <div class="d-flex justify-content-between">
                        <span class="text-muted">Approved by</span>
                        <span class="fw-semibold text-success">{{ $loan->approvedBy->name }}</span>
                    </div>
                    @endif
                    @if($loan->disbursedByUser)
                    <div class="d-flex justify-content-between">
                        <span class="text-muted">Disbursed by</span>
                        <span class="fw-semibold">{{ $loan->disbursedByUser->name }}</span>
                    </div>
                    @endif
                    @if($loan->rejectedBy)
                    <div class="d-flex justify-content-between">
                        <span class="text-muted">Rejected by</span>
                        <span class="fw-semibold text-danger">{{ $loan->rejectedBy->name }}</span>
                    </div>
                    @endif
                </div>
                @if($loan->credit_assessment_notes)
                <div class="mt-2 p-2 rounded" style="background:#f0f9ff;border:1px solid #bae6fd;font-size:0.78rem">
                    <strong>Credit Notes:</strong> {{ $loan->credit_assessment_notes }}
                </div>
                @endif
            </div>
        </div>

        <!-- Status History -->
        <div class="table-card mb-3">
            <div class="table-card-header"><span class="fw-semibold">Status History</span></div>
            <div style="max-height:260px;overflow-y:auto">
                @foreach($loan->statusHistory as $h)
                <div style="padding:0.6rem 1rem;@if(!$loop->last) border-bottom:1px solid #f8fafc; @endif">
                    <div class="d-flex justify-content-between align-items-start">
                        <span class="badge bg-{{ config('bigcash.loan.status_colors.'.$h->to_status,'secondary') }}" style="font-size:0.68rem">
                            {{ $h->to_label }}
                        </span>
                        <span style="font-size:0.68rem;color:#94a3b8">{{ $h->created_at->format('d/m/Y H:i') }}</span>
                    </div>
                    <div style="font-size:0.75rem;color:#64748b;margin-top:3px">{{ $h->changedBy->name }}</div>
                    @if($h->note)
                    <div style="font-size:0.75rem;color:#475569;margin-top:2px;font-style:italic">{{ $h->note }}</div>
                    @endif
                </div>
                @endforeach
            </div>
        </div>

        <!-- Write-off / Waiver Actions -->
        @if($loan->isActive())
        @can('loans.write_off')
        <div class="table-card mb-3">
            <div class="table-card-header"><span class="fw-semibold text-danger">Danger Zone</span></div>
            <div style="padding:1rem;display:flex;flex-direction:column;gap:0.5rem">
                <button class="btn btn-outline-warning btn-sm" data-bs-toggle="modal" data-bs-target="#rescheduleModal">
                    <i class="bi bi-arrow-repeat me-1"></i>Reschedule Loan
                </button>
                <button class="btn btn-outline-danger btn-sm" data-bs-toggle="modal" data-bs-target="#writeOffModal">
                    <i class="bi bi-x-octagon me-1"></i>Write Off Loan
                </button>
            </div>
        </div>
        @endcan
        @endif

    </div>
</div>

<!-- ── Modals ─────────────────────────────────────────────────────────────── -->

<!-- Recommend Modal -->
<div class="modal fade" id="recommendModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header"><h5 class="modal-title">Recommend Loan</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <form method="POST" action="{{ route('admin.loans.recommend', $loan) }}">@csrf
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Recommendation Note <span class="text-danger">*</span></label>
                        <textarea name="note" class="form-control" rows="3" placeholder="Credit assessment notes, recommendation rationale..." required></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn btn-primary">Recommend for Approval</button>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Approve Modal -->
<div class="modal fade" id="approveModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header"><h5 class="modal-title text-success">Approve Loan</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <form method="POST" action="{{ route('admin.loans.approve', $loan) }}">@csrf
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Approved Amount <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text">{{ $sym }}</span>
                            <input type="number" name="approved_amount" class="form-control" step="0.01"
                                   value="{{ $loan->requested_amount }}" max="{{ $loan->requested_amount }}" required>
                        </div>
                        <small class="text-muted">Requested: {{ money($loan->requested_amount) }}</small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Approval Note</label>
                        <textarea name="note" class="form-control" rows="2" placeholder="Optional approval conditions..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn btn-success">Approve Loan</button>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Reject Modal -->
<div class="modal fade" id="rejectModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header"><h5 class="modal-title text-danger">Reject Loan</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <form method="POST" action="{{ route('admin.loans.reject', $loan) }}">@csrf
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Rejection Reason <span class="text-danger">*</span></label>
                        <textarea name="reason" class="form-control" rows="3" placeholder="Provide a clear reason for rejection..." required></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn btn-danger">Reject Loan</button>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Disburse Modal -->
<div class="modal fade" id="disburseModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header"><h5 class="modal-title">Disburse Loan — {{ money($loan->approved_amount) }}</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <form method="POST" action="{{ route('admin.loans.disburse', $loan) }}">@csrf
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Disbursement Amount <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text">{{ $sym }}</span>
                                <input type="number" name="disbursed_amount" class="form-control" step="0.01" value="{{ $loan->approved_amount }}" max="{{ $loan->approved_amount }}" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Disbursement Method <span class="text-danger">*</span></label>
                            <select name="disbursement_method" class="form-select" required>
                                <option value="mobile_money">Mobile Money</option>
                                <option value="bank_transfer">Bank Transfer</option>
                                <option value="cash">Cash</option>
                                <option value="cheque">Cheque</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Disbursement Date <span class="text-danger">*</span></label>
                            <input type="date" name="disbursement_date" class="form-control datepicker" value="{{ today()->toDateString() }}" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">First Repayment Date <span class="text-danger">*</span></label>
                            <input type="date" name="first_repayment_date" class="form-control datepicker" value="{{ today()->addMonth()->toDateString() }}" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Account / Number</label>
                            <input type="text" name="disbursement_account" class="form-control" placeholder="Account or mobile number">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Reference / Transaction ID</label>
                            <input type="text" name="disbursement_reference" class="form-control" placeholder="Payment reference">
                        </div>
                    </div>
                    <div class="alert alert-warning mt-3 mb-0" style="font-size:0.83rem">
                        <i class="bi bi-exclamation-triangle me-2"></i>
                        Disbursement will generate the full repayment schedule and activate the loan. This cannot be undone.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn btn-warning text-dark fw-bold">Confirm Disbursement</button>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Write-Off Modal -->
<div class="modal fade" id="writeOffModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header border-danger"><h5 class="modal-title text-danger">Write Off Loan</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <form method="POST" action="{{ route('admin.loans.write_off', $loan) }}">@csrf
                <div class="modal-body">
                    <div class="alert alert-danger" style="font-size:0.83rem">
                        Writing off will mark <strong>{{ money($loan->total_outstanding) }}</strong> as bad debt. This action is logged.
                    </div>
                    <textarea name="reason" class="form-control" rows="3" placeholder="Reason for write-off..." required></textarea>
                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn btn-danger">Confirm Write-Off</button>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                </div>
            </form>
        </div>
    </div>
</div>

@endsection

@push('scripts')
<script>
document.getElementById('btn-ai-assess')?.addEventListener('click', async function() {
    const panel = document.getElementById('ai-assessment-panel');
    const content = document.getElementById('ai-assessment-content');
    panel.style.display = 'block';
    panel.scrollIntoView({ behavior: 'smooth', block: 'start' });

    try {
        const resp = await fetch('{{ route("admin.ai.assess", $loan) }}', {
            headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content }
        });
        const data = await resp.json();

        if (data.error) {
            content.innerHTML = `<div class="alert alert-warning">${data.message}</div>`;
            return;
        }

        const riskColor = { low: '#16a34a', medium: '#d97706', high: '#dc2626', very_high: '#7f1d1d' }[data.risk_level] || '#64748b';
        const recColor  = { approve: '#16a34a', conditional_approve: '#d97706', reject: '#dc2626' }[data.recommendation] || '#64748b';

        content.innerHTML = `
            <div class="row g-3">
                <div class="col-md-4 text-center">
                    <div style="font-size:3rem;font-weight:900;color:${riskColor}">${data.risk_score}</div>
                    <div style="font-size:0.75rem;color:#64748b">Risk Score (100 = Lowest Risk)</div>
                    <span class="badge mt-1" style="background:${riskColor};font-size:0.8rem">${(data.risk_level||'').toUpperCase().replace('_',' ')}</span>
                </div>
                <div class="col-md-8">
                    <div class="mb-2"><strong>Recommendation:</strong>
                        <span class="badge ms-1" style="background:${recColor}">${(data.recommendation||'').toUpperCase().replace('_',' ')}</span>
                    </div>
                    <p style="font-size:0.85rem;color:#475569">${data.summary || ''}</p>
                </div>
                ${data.key_strengths?.length ? `<div class="col-md-6">
                    <strong style="font-size:0.82rem;color:#16a34a">✓ Strengths</strong>
                    <ul style="font-size:0.82rem;padding-left:1.2rem;margin:0.3rem 0 0">${data.key_strengths.map(s=>`<li>${s}</li>`).join('')}</ul>
                </div>` : ''}
                ${data.key_concerns?.length ? `<div class="col-md-6">
                    <strong style="font-size:0.82rem;color:#dc2626">⚠ Concerns</strong>
                    <ul style="font-size:0.82rem;padding-left:1.2rem;margin:0.3rem 0 0">${data.key_concerns.map(c=>`<li>${c}</li>`).join('')}</ul>
                </div>` : ''}
                ${data.conditions?.length ? `<div class="col-12">
                    <strong style="font-size:0.82rem;color:#d97706">Conditions</strong>
                    <ul style="font-size:0.82rem;padding-left:1.2rem;margin:0.3rem 0 0">${data.conditions.map(c=>`<li>${c}</li>`).join('')}</ul>
                </div>` : ''}
            </div>
        `;
    } catch(e) {
        content.innerHTML = `<div class="alert alert-danger">AI assessment failed. Please try again.</div>`;
    }
});
</script>
@endpush
