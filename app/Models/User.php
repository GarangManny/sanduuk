<?php

namespace App\Models;

use Laravel\Sanctum\HasApiTokens; // Sanctum API Tokens
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name',
        // 'email',
        'password',
        'phone',
        'role',          // admin, user
        'status',        // active, suspended
        //  'wifi_password', // new field
        // 'mikrotik_ip',
        'username',
        'balance',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
    ];

    /**
     * A user has many payments
     */
    public function payments()
    {
        return $this->hasMany(Payment::class);
    }

    /**
     * Latest subscription (current)
     */
    public function subscription()
    {
        return $this->hasOne(Subscription::class)->latestOfMany();
    }

    /**
     * Full subscription history
     */
    public function subscriptions()
    {
        return $this->hasMany(Subscription::class)->latest();
    }

    /**
     * Helper: Check if user is admin
     */
    public function isAdmin()
    {
        return $this->role === 'admin';
    }

    public function transactions()
    {
        return $this->hasMany(Transaction::class)->latest();
    }

    public function chamaMembership()
    {
        return $this->hasOne(\App\Models\ChamaMember::class);
    }

    public function chama()
    {
        return $this->hasOneThrough(
            Chama::class,
            ChamaMember::class,
            'user_id',     // Foreign key on chama_members
            'id',          // Foreign key on chamas
            'id',          // Local key on users
            'chama_id'     // Local key on chama_members
        );
    }

}
