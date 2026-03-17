<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class LoanStatusHistory extends Model
{
    use HasFactory;

    protected $fillable = [
        'loan_id', 'changed_by', 'from_status', 'to_status', 'note', 'ip_address',
    ];

    public function loan()      { return $this->belongsTo(Loan::class); }
    public function changedBy() { return $this->belongsTo(User::class, 'changed_by'); }

    public function getFromLabelAttribute(): string
    {
        return config('bigcash.loan.statuses')[$this->from_status] ?? ucfirst($this->from_status ?? 'New');
    }

    public function getToLabelAttribute(): string
    {
        return config('bigcash.loan.statuses')[$this->to_status] ?? ucfirst($this->to_status);
    }
}
