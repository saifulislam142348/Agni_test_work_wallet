<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Wallet extends Model
{

 use HasFactory;
     protected $fillable = [
        'user_id',
        'token',
        'masked',
        'balance',
    ];

    protected $casts = [
        'balance' => 'decimal:2',
    ];

    /* =====================
        Relationships
    ====================== */

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function transactions()
    {
        return $this->hasMany(WalletTransaction::class);
    }

    /* =====================
        Token Encryption
    ====================== */

    public function setTokenAttribute($value)
    {
        $this->attributes['token'] = encrypt($value);
    }

    public function getTokenAttribute($value)
    {
        return decrypt($value);
    }
}
