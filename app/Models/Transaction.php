<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;


use Illuminate\Database\Eloquent\Factories\HasFactory;


class Transaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'chama_id',
        'type',
        'amount',
        'exchange_rate',
        'converted_amount',
        'currency',
        'from_account',
        'to_account',
        'status',
        'recorded_by',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function chama()
    {
        return $this->belongsTo(Chama::class);
    }
}

