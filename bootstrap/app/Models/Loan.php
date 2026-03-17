<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

class Loan extends Model
{
    use HasFactory, SoftDeletes, LogsActivity;

    protected $fillable = [
        'loan_number', 'branch_id', 'borrower_id', 'loan_product_id',
        'loan_officer_id', 'created_by', 'loan_purpose',
        'requested_amount', 'approved_amount', 'disbursed_amount',
        'term_months', 'repayment_frequency',
        'interest_type', 'interest_rate',
        'processing_fee_amount', 'insurance_fee_amount',
        'admin_fee_amount', 'other_fees_amount',
        'total_interest', 'total_repayable', 'installment_amount',
        'outstanding_principal', 'outstanding_interest',
        'outstanding_fees', 'outstanding_penalty', 'total_outstanding',
        'total_paid', 'total_interest_paid', 'total_penalty_paid',
        'application_date', 'first_repayment_date', 'disbursement_date',
        'maturity_date', 'actual_completion_date',
        'status', 'days_past_due', 'is_overdue', 'overdue_since',
        'disbursement_method', 'disbursement_bank', 'disbursement_account',
        'disbursement_reference', 'disbursed_by', 'accountant_verified_by',
        'recommended_by', 'recommended_at', 'recommendation_note',
        'approved_by', 'approved_at', 'approval_note',
        'second_approver_id', 'second_approved_at',
        'rejected_by', 'rejected_at', 'rejection_reason',
        'debt_to_income_ratio', 'affordability_score',
        'credit_assessment_notes', 'existing_debt_monthly',
        'write_off_amount', 'written_off_by', 'written_off_at', 'write_off_reason',
        'waiver_amount', 'waiver_reason',
        'group_loan_id', 'internal_notes',
    ];

    protected $casts = [
        'application_date'        => 'date',
        'first_repayment_date'    => 'date',
        'disbursement_date'       => 'date',
        'maturity_date'           => 'date',
        'actual_completion_date'  => 'date',
        'overdue_since'           => 'date',
        'recommended_at'          => 'datetime',
        'approved_at'             => 'datetime',
        'second_approved_at'      => 'datetime',
        'rejected_at'             => 'datetime',
        'written_off_at'          => 'datetime',
        'is_overdue'              => 'boolean',
        'requested_amount'        => 'decimal:2',
        'approved_amount'         => 'decimal:2',
        'disbursed_amount'        => 'decimal:2',
        'total_interest'          => 'decimal:2',
        'total_repayable'         => 'decimal:2',
        'installment_amount'      => 'decimal:2',
        'outstanding_principal'   => 'decimal:2',
        'outstanding_interest'    => 'decimal:2',
        'outstanding_fees'        => 'decimal:2',
        'outstanding_penalty'     => 'decimal:2',
        'total_outstanding'       => 'decimal:2',
        'total_paid'              => 'decimal:2',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['status', 'approved_amount', 'disbursed_amount', 'total_outstanding'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    // ─── Relationships ────────────────────────────────────────────────────────

    public function branch()          { return $this->belongsTo(Branch::class); }
    public function borrower()        { return $this->belongsTo(Borrower::class); }
    public function loanProduct()     { return $this->belongsTo(LoanProduct::class); }
    public function loanOfficer()     { return $this->belongsTo(User::class, 'loan_officer_id'); }
    public function createdBy()       { return $this->belongsTo(User::class, 'created_by'); }
    public function approvedBy()      { return $this->belongsTo(User::class, 'approved_by'); }
    public function disbursedByUser() { return $this->belongsTo(User::class, 'disbursed_by'); }
    public function recommendedBy()   { return $this->belongsTo(User::class, 'recommended_by'); }
    public function rejectedBy()      { return $this->belongsTo(User::class, 'rejected_by'); }

    public function schedule()
    {
        return $this->hasMany(RepaymentSchedule::class)->orderBy('installment_number');
    }

    public function repayments()
    {
        return $this->hasMany(Repayment::class)->orderBy('payment_date', 'desc');
    }

    public function statusHistory()
    {
        return $this->hasMany(LoanStatusHistory::class)->orderBy('created_at', 'desc');
    }

    public function documents()
    {
        return $this->hasMany(LoanDocument::class);
    }

    public function penalties()
    {
        return $this->hasMany(Penalty::class);
    }

    public function paymentLinks()
    {
        return $this->hasMany(PaymentLink::class);
    }

    public function ledgerEntries()
    {
        return $this->hasMany(LedgerEntry::class);
    }

    // ─── Accessors ────────────────────────────────────────────────────────────

    public function getStatusBadgeAttribute(): string
    {
        $colors = config('bigcash.loan.status_colors');
        $color  = $colors[$this->status] ?? 'secondary';
        $label  = config('bigcash.loan.statuses')[$this->status] ?? ucfirst($this->status);
        return "<span class=\"badge bg-{$color}\">{$label}</span>";
    }

    public function getStatusLabelAttribute(): string
    {
        return config('bigcash.loan.statuses')[$this->status] ?? ucfirst($this->status);
    }

    public function getSettlementAmountAttribute(): float
    {
        return (float) ($this->outstanding_principal
            + $this->outstanding_interest
            + $this->outstanding_fees
            + $this->outstanding_penalty);
    }

    public function getNextDueDateAttribute(): ?string
    {
        $next = $this->schedule()
            ->whereIn('status', ['pending', 'partial', 'overdue'])
            ->orderBy('due_date')
            ->first();
        return $next ? $next->due_date->format(config('bigcash.company.date_format', 'd/m/Y')) : null;
    }

    public function getNextDueAmountAttribute(): float
    {
        $next = $this->schedule()
            ->whereIn('status', ['pending', 'partial', 'overdue'])
            ->orderBy('due_date')
            ->first();
        return $next ? (float)($next->total_due - $next->total_paid) : 0;
    }

    public function getPaidInstallmentsAttribute(): int
    {
        return $this->schedule()->where('status', 'paid')->count();
    }

    public function getTotalInstallmentsAttribute(): int
    {
        return $this->schedule()->count();
    }

    // ─── Scopes ───────────────────────────────────────────────────────────────

    public function scopeActive($query)
    {
        return $query->whereIn('status', ['active', 'overdue', 'disbursed']);
    }

    public function scopeOverdue($query)
    {
        return $query->where('is_overdue', true);
    }

    public function scopeForBranch($query, $branchId)
    {
        if ($branchId) {
            return $query->where('branch_id', $branchId);
        }
        return $query;
    }

    public function scopeForOfficer($query, $officerId)
    {
        return $query->where('loan_officer_id', $officerId);
    }

    public function scopeDueToday($query)
    {
        return $query->whereHas('schedule', fn($q) =>
            $q->where('due_date', today())->whereIn('status', ['pending', 'partial'])
        );
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    public static function generateLoanNumber(): string
    {
        $year = date('Y');
        $count = static::whereYear('created_at', $year)->count() + 1;
        return 'LN-' . $year . '-' . str_pad($count, 5, '0', STR_PAD_LEFT);
    }

    public function canTransitionTo(string $status): bool
    {
        $transitions = [
            'draft'             => ['submitted', 'rejected'],
            'submitted'         => ['under_review', 'pending_documents', 'rejected'],
            'under_review'      => ['pending_documents', 'recommended', 'rejected'],
            'pending_documents' => ['under_review', 'rejected'],
            'recommended'       => ['approved', 'rejected'],
            'approved'          => ['disbursed', 'rejected'],
            'disbursed'         => ['active'],
            'active'            => ['overdue', 'completed', 'rescheduled', 'defaulted'],
            'overdue'           => ['active', 'completed', 'defaulted', 'written_off', 'rescheduled'],
            'rescheduled'       => ['active', 'overdue', 'completed', 'defaulted'],
            'defaulted'         => ['written_off', 'active'],
        ];
        return in_array($status, $transitions[$this->status] ?? []);
    }

    public function isActive(): bool
    {
        return in_array($this->status, ['active', 'overdue', 'disbursed']);
    }

    public function needsSecondApproval(): bool
    {
        return $this->loanProduct->approval_levels >= 2
            && is_null($this->second_approved_at);
    }
}
