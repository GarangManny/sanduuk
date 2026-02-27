<?php

namespace App\Policies;

use App\Models\Chama;
use App\Models\User;
use App\Models\ChamaMember;
use Illuminate\Auth\Access\HandlesAuthorization;

class ChamaPolicy
{
    use HandlesAuthorization;

    /**
     * Determine if the user can view the chama.
     */
    public function view(User $user, Chama $chama)
    {
        return ChamaMember::where('chama_id', $chama->id)
            ->where('user_id', $user->id)
            ->exists();
    }

    /**
     * Determine if the user can contribute to the chama.
     */
    public function contribute(User $user, Chama $chama)
    {
        return ChamaMember::where('chama_id', $chama->id)
            ->where('user_id', $user->id)
            ->exists();
    }

    /**
     * Determine if the user can take a loan from the chama.
     */
    public function loan(User $user, Chama $chama)
    {
        return ChamaMember::where('chama_id', $chama->id)
            ->where('user_id', $user->id)
            ->exists();
    }

    /**
     * Determine if the user can manage (admin) the chama.
     */
    public function manage(User $user, Chama $chama)
    {
        return $chama->admin_id === $user->id;
    }
}
