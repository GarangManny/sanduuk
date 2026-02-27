<?php

namespace App\Policies;

use App\Models\Transaction;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class TransactionPolicy
{
    use HandlesAuthorization;

    /**
     * Determine if the user can approve the transaction.
     */
    public function approve(User $user, Transaction $transaction)
    {
        // Only the admin of the associated chama can approve
        return $transaction->chama && $transaction->chama->admin_id === $user->id;
    }

    /**
     * Determine if the user can reject the transaction.
     */
    public function reject(User $user, Transaction $transaction)
    {
        // Only the admin of the associated chama can reject
        return $transaction->chama && $transaction->chama->admin_id === $user->id;
    }
}
