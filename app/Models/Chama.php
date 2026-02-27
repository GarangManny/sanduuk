<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Chama extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'admin_id',
        'total_balance',
        'invite_code',
        'contribution_amount',
        'currency',
        'contribution_period',
    ];

    public function admin()
    {
        return $this->belongsTo(User::class, 'admin_id');
    }

    public function members()
    {
        return $this->hasMany(ChamaMember::class);
    }

    public function transactions()
    {
        return $this->hasMany(Transaction::class);
    }
}
