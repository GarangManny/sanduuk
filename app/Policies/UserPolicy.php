<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class UserPolicy
{
    use HandlesAuthorization;

    /**
     * Determine if the user can view the list of users.
     */
    public function viewAny(User $user)
    {
        return $user->role === 'admin';
    }

    /**
     * Determine if the user can manage another user (suspend/toggle).
     */
    public function manage(User $user, User $target)
    {
        return $user->role === 'admin';
    }
}
