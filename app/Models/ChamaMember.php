<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ChamaMember extends Model
{
    use HasFactory;

    protected $fillable = [
        'chama_id',
        'user_id',
        'role',
        'total_contribution'
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
