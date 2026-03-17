<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class NotificationLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id', 'borrower_id', 'loan_id', 'channel',
        'template_type', 'recipient', 'message', 'status', 'error_message',
    ];

    public function user()     { return $this->belongsTo(User::class); }
    public function borrower() { return $this->belongsTo(Borrower::class); }
    public function loan()     { return $this->belongsTo(Loan::class); }
}
