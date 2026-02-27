<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Package;
use Illuminate\Auth\Access\HandlesAuthorization;

class PackagePolicy
{
    use HandlesAuthorization;

    /**
     * Determine if any user can view packages.
     */
    public function viewAny(User $user)
    {
        return true; // Everyone can see packages
    }

    /**
     * Determine if the user can manage packages.
     */
    public function manage(User $user)
    {
        return $user->role === 'admin';
    }
}
