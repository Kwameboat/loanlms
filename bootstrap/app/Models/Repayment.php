<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

class Repayment extends Model
{
    use HasFactory, SoftDeletes, LogsActivity;

    protected $fillable = [
        'receipt_number', 'loan_id', 'borrower_id', 'branch_id',
        'collected_by', 'verified_by',
        'amount', 'principal_paid', 'interest_paid', 'fees_paid', 'penalty_paid',
        'payment_method', 'payment_reference',
        'mobile_money_number', 'mobile_money_provider',
        'bank_name', 'cheque_number',
        'paystack_reference', 'paystack_transaction_id', 'paystack_channel',
        'paystack_fees', 'paystack_raw_response', 'paystack_status',
        'payment_date', 'payment_time', 'status',
        'reversed_by', 'reversed_at', 'reversal_reason',
        'notes', 'receipt_path', 'repayment_schedule_id', 'bulk_upload_batch',
    ];

    protected $casts = [
        'payment_date'         => 'date',
        'reversed_at'          => 'datetime',
        'amount'               => 'decimal:2',
        'principal_paid'       => 'decimal:2',
        'interest_paid'        => 'decimal:2',
        'fees_paid'            => 'decimal:2',
        'penalty_paid'         => 'decimal:2',
        'paystack_fees'        => 'decimal:2',
        'paystack_raw_response'=> 'array',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['amount', 'status', 'reversed_at', 'reversal_reason'])
            ->logOnlyDirty();
    }

    public function loan()           { return $this->belongsTo(Loan::class); }
    public function borrower()       { return $this->belongsTo(Borrower::class); }
    public function branch()         { return $this->belongsTo(Branch::class); }
    public function collectedBy()    { return $this->belongsTo(User::class, 'collected_by'); }
    public function verifiedBy()     { return $this->belongsTo(User::class, 'verified_by'); }
    public function reversedBy()     { return $this->belongsTo(User::class, 'reversed_by'); }
    public function schedule()       { return $this->belongsTo(RepaymentSchedule::class, 'repayment_schedule_id'); }

    public function getReceiptUrlAttribute(): string
    {
        if ($this->receipt_path) {
            return asset('storage/' . $this->receipt_path);
        }
        return route('admin.repayments.receipt', $this->id);
    }

    public static function generateReceiptNumber(): string
    {
        $year  = date('Y');
        $month = date('m');
        $count = static::whereYear('created_at', $year)->whereMonth('created_at', $month)->count() + 1;
        return 'RCT-' . $year . $month . '-' . str_pad($count, 5, '0', STR_PAD_LEFT);
    }

    public function scopeConfirmed($query)
    {
        return $query->where('status', 'confirmed');
    }

    public function scopeForBranch($query, $branchId)
    {
        return $branchId ? $query->where('branch_id', $branchId) : $query;
    }
}
