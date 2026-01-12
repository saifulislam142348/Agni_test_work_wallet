<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentAttempt extends Model
{
     use HasFactory;

    protected $fillable = [
        'user_id',
        'request_id',
        'status',
    ];

    /* =====================
        Relationships
    ====================== */

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
