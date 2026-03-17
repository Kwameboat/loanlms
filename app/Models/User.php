<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;
use Spatie\Activitylog\Traits\CausesActivity;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes, HasRoles, CausesActivity, LogsActivity;

    protected $fillable = [
        'branch_id', 'name', 'email', 'phone', 'employee_id',
        'password', 'avatar', 'is_active', 'two_factor_enabled',
        'two_factor_secret', 'otp_code', 'otp_expires_at',
        'last_login_at', 'last_login_ip', 'must_change_password', 'notes',
    ];

    protected $hidden = [
        'password', 'remember_token', 'two_factor_secret', 'otp_code',
    ];

    protected $casts = [
        'email_verified_at'       => 'datetime',
        'two_factor_confirmed_at' => 'datetime',
        'otp_expires_at'          => 'datetime',
        'last_login_at'           => 'datetime',
        'is_active'               => 'boolean',
        'two_factor_enabled'      => 'boolean',
        'must_change_password'    => 'boolean',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'email', 'is_active', 'branch_id'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    // ─── Relationships ────────────────────────────────────────────────────────

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function loans()
    {
        return $this->hasMany(Loan::class, 'loan_officer_id');
    }

    public function createdLoans()
    {
        return $this->hasMany(Loan::class, 'created_by');
    }

    public function repayments()
    {
        return $this->hasMany(Repayment::class, 'collected_by');
    }

    public function borrowerProfile()
    {
        return $this->hasOne(Borrower::class, 'email', 'email');
    }

    // ─── Accessors & Helpers ──────────────────────────────────────────────────

    public function getAvatarUrlAttribute(): string
    {
        if ($this->avatar) {
            return asset('storage/' . $this->avatar);
        }
        return asset('images/default-avatar.png');
    }

    public function getInitialsAttribute(): string
    {
        $parts = explode(' ', $this->name);
        return strtoupper(
            (isset($parts[0]) ? substr($parts[0], 0, 1) : '') .
            (isset($parts[1]) ? substr($parts[1], 0, 1) : '')
        );
    }

    public function isSuperAdmin(): bool
    {
        return $this->hasRole('super_admin');
    }

    public function isBorrower(): bool
    {
        return $this->hasRole('borrower');
    }

    public function canAccessBranch(int $branchId): bool
    {
        if ($this->isSuperAdmin() || $this->hasRole(['admin'])) {
            return true;
        }
        return $this->branch_id === $branchId;
    }

    public function isOtpValid(string $otp): bool
    {
        return $this->otp_code === $otp
            && $this->otp_expires_at
            && $this->otp_expires_at->isFuture();
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeStaff($query)
    {
        return $query->whereDoesntHave('roles', fn ($q) => $q->where('name', 'borrower'));
    }
}
