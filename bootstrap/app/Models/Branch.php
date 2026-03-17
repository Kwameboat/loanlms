<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

class Branch extends Model
{
    use HasFactory, SoftDeletes, LogsActivity;

    protected $fillable = [
        'name', 'code', 'address', 'region', 'phone', 'email',
        'manager_name', 'is_head_office', 'is_active', 'notes',
    ];

    protected $casts = [
        'is_head_office' => 'boolean',
        'is_active'      => 'boolean',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logAll()->dontSubmitEmptyLogs();
    }

    public function users()
    {
        return $this->hasMany(User::class);
    }

    public function borrowers()
    {
        return $this->hasMany(Borrower::class);
    }

    public function loans()
    {
        return $this->hasMany(Loan::class);
    }

    public function loanProducts()
    {
        return $this->belongsToMany(LoanProduct::class, 'branch_loan_products')
                    ->withPivot('is_active')
                    ->withTimestamps();
    }

    public function repayments()
    {
        return $this->hasMany(Repayment::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function getActiveLoanCountAttribute(): int
    {
        return $this->loans()->whereIn('status', ['active', 'overdue', 'disbursed'])->count();
    }

    public function getPortfolioAtRiskAttribute(): float
    {
        $overdue = $this->loans()->where('is_overdue', true)->sum('outstanding_principal');
        $total   = $this->loans()->whereIn('status', ['active', 'overdue'])->sum('outstanding_principal');
        if ($total == 0) return 0;
        return round(($overdue / $total) * 100, 2);
    }
}
