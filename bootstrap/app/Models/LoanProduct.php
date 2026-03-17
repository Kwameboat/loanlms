<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

class LoanProduct extends Model
{
    use HasFactory, SoftDeletes, LogsActivity;

    protected $fillable = [
        'name', 'code', 'description', 'product_type',
        'min_amount', 'max_amount', 'min_term', 'max_term',
        'interest_type', 'interest_rate', 'interest_period',
        'processing_fee', 'processing_fee_amount',
        'insurance_fee', 'insurance_fee_amount',
        'admin_fee', 'admin_fee_amount',
        'grace_period_days', 'repayment_frequency',
        'allow_partial_payment', 'allow_early_repayment', 'early_repayment_fee',
        'penalty_enabled', 'penalty_type', 'penalty_rate',
        'penalty_fixed_amount', 'penalty_grace_days',
        'min_age', 'max_age', 'min_monthly_income',
        'eligible_employment_types', 'requires_guarantor', 'eligibility_notes',
        'requires_approval_chain', 'approval_levels', 'is_active', 'is_group_loan',
        'required_documents', 'terms_and_conditions', 'created_by',
    ];

    protected $casts = [
        'min_amount'                => 'decimal:2',
        'max_amount'                => 'decimal:2',
        'interest_rate'             => 'decimal:4',
        'processing_fee'            => 'decimal:4',
        'processing_fee_amount'     => 'decimal:2',
        'insurance_fee'             => 'decimal:4',
        'insurance_fee_amount'      => 'decimal:2',
        'admin_fee'                 => 'decimal:4',
        'admin_fee_amount'          => 'decimal:2',
        'penalty_rate'              => 'decimal:4',
        'penalty_fixed_amount'      => 'decimal:2',
        'early_repayment_fee'       => 'decimal:4',
        'min_monthly_income'        => 'decimal:2',
        'eligible_employment_types' => 'array',
        'required_documents'        => 'array',
        'is_active'                 => 'boolean',
        'is_group_loan'             => 'boolean',
        'penalty_enabled'           => 'boolean',
        'requires_guarantor'        => 'boolean',
        'requires_approval_chain'   => 'boolean',
        'allow_partial_payment'     => 'boolean',
        'allow_early_repayment'     => 'boolean',
        'approval_levels'           => 'integer',
        'min_term'                  => 'integer',
        'max_term'                  => 'integer',
        'min_age'                   => 'integer',
        'max_age'                   => 'integer',
        'grace_period_days'         => 'integer',
        'penalty_grace_days'        => 'integer',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logAll()->logOnlyDirty()->dontSubmitEmptyLogs();
    }

    public function branches()
    {
        return $this->belongsToMany(Branch::class, 'branch_loan_products')
                    ->withPivot('is_active')
                    ->withTimestamps();
    }

    public function loans()
    {
        return $this->hasMany(Loan::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Calculate processing fee amount for a given principal.
     */
    public function calculateProcessingFee(float $principal): float
    {
        if ($this->processing_fee_amount) {
            return (float) $this->processing_fee_amount;
        }
        return round($principal * ($this->processing_fee / 100), 2);
    }

    /**
     * Calculate insurance fee amount for a given principal.
     */
    public function calculateInsuranceFee(float $principal): float
    {
        if ($this->insurance_fee_amount) {
            return (float) $this->insurance_fee_amount;
        }
        return round($principal * ($this->insurance_fee / 100), 2);
    }

    /**
     * Calculate admin fee amount for a given principal.
     */
    public function calculateAdminFee(float $principal): float
    {
        if ($this->admin_fee_amount) {
            return (float) $this->admin_fee_amount;
        }
        return round($principal * ($this->admin_fee / 100), 2);
    }

    public function getTypeLabelAttribute(): string
    {
        $types = [
            'salary_loan'  => 'Salary Loan',
            'personal_loan'=> 'Personal Loan',
            'business_loan'=> 'Business Loan',
            'emergency_loan'=> 'Emergency Loan',
            'group_loan'   => 'Group Loan',
            'microloan'    => 'Micro Loan',
            'other'        => 'Other',
        ];
        return $types[$this->product_type] ?? ucfirst($this->product_type);
    }
}
