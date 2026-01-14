<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Agreement extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'agreement_id',
        'payer_reference',
        'status',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
