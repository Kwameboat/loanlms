<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

class Borrower extends Model
{
    use HasFactory, SoftDeletes, LogsActivity;

    protected $fillable = [
        'branch_id', 'created_by', 'borrower_number',
        'first_name', 'last_name', 'other_names', 'gender', 'date_of_birth',
        'ghana_card_number', 'voter_id', 'passport_number', 'nationality',
        'marital_status', 'number_of_dependants',
        'primary_phone', 'secondary_phone', 'email', 'whatsapp_number',
        'residential_address', 'digital_address', 'nearest_landmark',
        'region', 'district', 'town_city', 'latitude', 'longitude',
        'employment_status', 'occupation', 'employer_name', 'employer_address',
        'employer_phone', 'monthly_income',
        'business_name', 'business_registration_number', 'business_address',
        'business_type', 'monthly_business_revenue',
        'next_of_kin_name', 'next_of_kin_relationship', 'next_of_kin_phone',
        'next_of_kin_address',
        'bank_name', 'bank_branch', 'account_number', 'account_name',
        'mobile_money_number', 'mobile_money_provider',
        'photo', 'status', 'blacklist_reason', 'credit_score', 'internal_notes',
    ];

    protected $casts = [
        'date_of_birth'           => 'date',
        'monthly_income'          => 'decimal:2',
        'monthly_business_revenue'=> 'decimal:2',
        'latitude'                => 'decimal:7',
        'longitude'               => 'decimal:7',
        'number_of_dependants'    => 'integer',
        'credit_score'            => 'decimal:2',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['status', 'branch_id', 'ghana_card_number', 'blacklist_reason'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    // ─── Relationships ────────────────────────────────────────────────────────

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function documents()
    {
        return $this->hasMany(BorrowerDocument::class);
    }

    public function guarantors()
    {
        return $this->hasMany(Guarantor::class);
    }

    public function loans()
    {
        return $this->hasMany(Loan::class);
    }

    public function activeLoans()
    {
        return $this->hasMany(Loan::class)->whereIn('status', ['active', 'overdue', 'disbursed']);
    }

    public function completedLoans()
    {
        return $this->hasMany(Loan::class)->whereIn('status', ['completed', 'written_off']);
    }

    public function repayments()
    {
        return $this->hasMany(Repayment::class);
    }

    public function notes()
    {
        return $this->hasMany(BorrowerNote::class)->orderBy('created_at', 'desc');
    }

    public function userAccount()
    {
        return $this->belongsTo(User::class, 'email', 'email');
    }

    // ─── Accessors ────────────────────────────────────────────────────────────

    public function getFullNameAttribute(): string
    {
        return trim("{$this->first_name} {$this->other_names} {$this->last_name}");
    }

    public function getDisplayNameAttribute(): string
    {
        return "{$this->first_name} {$this->last_name}";
    }

    public function getAgeAttribute(): int
    {
        return $this->date_of_birth ? $this->date_of_birth->age : 0;
    }

    public function getPhotoUrlAttribute(): string
    {
        if ($this->photo) {
            return asset('storage/' . $this->photo);
        }
        return asset('images/default-borrower.png');
    }

    public function getTotalActiveOutstandingAttribute(): float
    {
        return (float) $this->activeLoans()->sum('total_outstanding');
    }

    public function getTotalLoansTakenAttribute(): int
    {
        return $this->loans()->whereNotIn('status', ['draft', 'rejected'])->count();
    }

    public function getRepaymentRateAttribute(): float
    {
        $total = $this->loans()->sum('total_repayable');
        $paid  = $this->loans()->sum('total_paid');
        if ($total == 0) return 100;
        return round(($paid / $total) * 100, 2);
    }

    // ─── Scopes ───────────────────────────────────────────────────────────────

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeSearch($query, $term)
    {
        return $query->where(function ($q) use ($term) {
            $q->where('first_name', 'like', "%{$term}%")
              ->orWhere('last_name', 'like', "%{$term}%")
              ->orWhere('primary_phone', 'like', "%{$term}%")
              ->orWhere('ghana_card_number', 'like', "%{$term}%")
              ->orWhere('borrower_number', 'like', "%{$term}%")
              ->orWhere('email', 'like', "%{$term}%");
        });
    }

    public function scopeForBranch($query, $branchId)
    {
        if ($branchId) {
            return $query->where('branch_id', $branchId);
        }
        return $query;
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    public static function generateBorrowerNumber(): string
    {
        $year = date('Y');
        $last = static::whereYear('created_at', $year)->max('id') ?? 0;
        return 'BRW-' . $year . '-' . str_pad($last + 1, 5, '0', STR_PAD_LEFT);
    }
}
