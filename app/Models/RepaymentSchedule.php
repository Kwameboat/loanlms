<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class RepaymentSchedule extends Model
{
    use HasFactory;

    protected $fillable = [
        'loan_id', 'installment_number', 'due_date',
        'opening_balance', 'principal_due', 'interest_due', 'fees_due',
        'penalty_due', 'total_due', 'closing_balance',
        'principal_paid', 'interest_paid', 'fees_paid', 'penalty_paid', 'total_paid',
        'status', 'paid_date', 'paid_at', 'days_past_due', 'is_overdue',
    ];

    protected $casts = [
        'due_date'        => 'date',
        'paid_date'       => 'date',
        'paid_at'         => 'datetime',
        'is_overdue'      => 'boolean',
        'opening_balance' => 'decimal:2',
        'principal_due'   => 'decimal:2',
        'interest_due'    => 'decimal:2',
        'fees_due'        => 'decimal:2',
        'penalty_due'     => 'decimal:2',
        'total_due'       => 'decimal:2',
        'closing_balance' => 'decimal:2',
        'principal_paid'  => 'decimal:2',
        'interest_paid'   => 'decimal:2',
        'fees_paid'       => 'decimal:2',
        'penalty_paid'    => 'decimal:2',
        'total_paid'      => 'decimal:2',
    ];

    public function loan()
    {
        return $this->belongsTo(Loan::class);
    }

    public function repayments()
    {
        return $this->hasMany(Repayment::class);
    }

    public function getBalanceDueAttribute(): float
    {
        return max(0, (float)$this->total_due - (float)$this->total_paid);
    }

    public function scopeOverdue($query)
    {
        return $query->where('status', 'overdue');
    }

    public function scopePending($query)
    {
        return $query->whereIn('status', ['pending', 'partial']);
    }
}
