<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class BorrowerDocument extends Model
{
    use HasFactory;
    protected $fillable = [
        'borrower_id','uploaded_by','document_type','document_name',
        'file_path','file_type','file_size','status','verified_by','verified_at','notes',
    ];
    protected $casts = ['verified_at' => 'datetime'];
    public function borrower()    { return $this->belongsTo(Borrower::class); }
    public function uploadedBy()  { return $this->belongsTo(User::class, 'uploaded_by'); }
    public function getFileUrlAttribute(): string { return asset('storage/'.$this->file_path); }
    public function getTypeLabelAttribute(): string {
        return str_replace('_',' ',ucwords($this->document_type,'_'));
    }
}

class Guarantor extends Model
{
    use HasFactory;
    protected $fillable = [
        'borrower_id','name','phone','email','ghana_card_number','relationship',
        'address','occupation','employer','monthly_income','photo','id_document','status','notes',
    ];
    protected $casts = ['monthly_income' => 'decimal:2'];
    public function borrower() { return $this->belongsTo(Borrower::class); }
    public function getPhotoUrlAttribute(): string {
        return $this->photo ? asset('storage/'.$this->photo) : asset('images/default-avatar.png');
    }
}

class BorrowerNote extends Model
{
    use HasFactory;
    protected $fillable = ['borrower_id','created_by','note','type','is_private'];
    protected $casts    = ['is_private' => 'boolean'];
    public function borrower()   { return $this->belongsTo(Borrower::class); }
    public function createdBy()  { return $this->belongsTo(User::class,'created_by'); }
    public function getTypeBadgeColorAttribute(): string {
        return ['general'=>'secondary','warning'=>'warning','positive'=>'success',
                'collection'=>'info','legal'=>'danger'][$this->type] ?? 'secondary';
    }
}

class LoanDocument extends Model
{
    use HasFactory;
    protected $fillable = [
        'loan_id','uploaded_by','document_type','document_name','file_path','file_type','file_size',
    ];
    public function loan()       { return $this->belongsTo(Loan::class); }
    public function uploadedBy() { return $this->belongsTo(User::class,'uploaded_by'); }
    public function getFileUrlAttribute(): string { return asset('storage/'.$this->file_path); }
}

class PaymentLink extends Model
{
    use HasFactory;
    protected $fillable = [
        'loan_id','borrower_id','repayment_schedule_id','reference',
        'paystack_access_code','authorization_url','amount','email',
        'purpose','status','expires_at',
    ];
    protected $casts = [
        'amount'     => 'decimal:2',
        'expires_at' => 'datetime',
    ];
    public function loan()     { return $this->belongsTo(Loan::class); }
    public function borrower() { return $this->belongsTo(Borrower::class); }
    public function schedule() { return $this->belongsTo(RepaymentSchedule::class,'repayment_schedule_id'); }
    public function isExpired(): bool {
        return $this->expires_at && $this->expires_at->isPast();
    }
}

class LedgerEntry extends Model
{
    use HasFactory;
    protected $fillable = [
        'branch_id','loan_id','repayment_id','created_by',
        'entry_type','debit_credit','amount','description','entry_date','reference',
    ];
    protected $casts = ['entry_date' => 'date', 'amount' => 'decimal:2'];
    public function branch()    { return $this->belongsTo(Branch::class); }
    public function loan()      { return $this->belongsTo(Loan::class); }
    public function repayment() { return $this->belongsTo(Repayment::class); }
    public function createdBy() { return $this->belongsTo(User::class,'created_by'); }
}
