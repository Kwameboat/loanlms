<?php namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Penalty extends Model {
    use HasFactory;
    protected $fillable = [
        'loan_id','repayment_schedule_id','amount','days_overdue',
        'accrual_date','status','paid_amount','waived_amount',
        'waived_by','waiver_reason',
    ];
    protected $casts = [
        'accrual_date' => 'date',
        'amount'       => 'decimal:2',
        'paid_amount'  => 'decimal:2',
        'waived_amount'=> 'decimal:2',
    ];
    public function loan()     { return $this->belongsTo(Loan::class); }
    public function schedule() { return $this->belongsTo(RepaymentSchedule::class,'repayment_schedule_id'); }
    public function waivedBy() { return $this->belongsTo(User::class,'waived_by'); }
    public function getOutstandingAttribute(): float {
        return max(0, (float)$this->amount - (float)$this->paid_amount - (float)$this->waived_amount);
    }
}
