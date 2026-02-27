<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use App\Services\AuditService;

class TransactionController extends Controller
{
    /**
     * Send money to another user.
     */
    public function send(Request $request)
    {
        $request->validate([
            'phone' => 'required|string|exists:users,phone',
            'amount' => 'required|numeric|min:1',
        ]);

        $sender = Auth::user()->load('chama');
        $recipient = User::where('phone', $request->phone)->first()->load('chama');

        if ($sender->id === $recipient->id) {
            throw ValidationException::withMessages([
                'phone' => ['You cannot send money to yourself.'],
            ]);
        }

        if ($sender->balance < $request->amount) {
            throw ValidationException::withMessages([
                'amount' => ['Insufficient balance.'],
            ]);
        }

        $senderCurrency = $sender->chama->currency ?? 'KES';
        $recipientCurrency = $recipient->chama->currency ?? 'KES';
        $rate = \App\Services\CurrencyService::getRate($senderCurrency, $recipientCurrency);
        $convertedAmount = $request->amount * $rate;

        return DB::transaction(function () use ($sender, $recipient, $request, $senderCurrency, $recipientCurrency, $rate, $convertedAmount) {
            // Deduct from sender
            $sender->decrement('balance', $request->amount);

            // Add to recipient (converted amount)
            $recipient->increment('balance', $convertedAmount);

            // Record transaction for sender (Debit)
            Transaction::create([
                'user_id' => $sender->id,
                'type' => 'transfer',
                'from_account' => 'personal',
                'to_account' => 'personal',
                'amount' => -$request->amount, // Saved as negative for sender
                'currency' => $senderCurrency,
                'exchange_rate' => $rate,
                'converted_amount' => $convertedAmount,
                'status' => 'completed',
                'recorded_by' => $sender->id,
            ]);

            // Record transaction for recipient (Credit)
            Transaction::create([
                'user_id' => $recipient->id,
                'type' => 'transfer',
                'from_account' => 'personal',
                'to_account' => 'personal',
                'amount' => $convertedAmount,
                'currency' => $recipientCurrency,
                'exchange_rate' => 1 / $rate,
                'converted_amount' => $request->amount,
                'status' => 'completed',
                'recorded_by' => $sender->id,
            ]);

            AuditService::log($sender->id, 'transfer.sent', $sender, null, ['amount' => $request->amount, 'recipient_id' => $recipient->id]);
            AuditService::log($recipient->id, 'transfer.received', $recipient, null, ['amount' => $convertedAmount, 'sender_id' => $sender->id]);

            return response()->json([
                'message' => 'Money sent successfully to ' . $recipient->name,
                'balance' => $sender->fresh()->balance,
                'converted_amount' => $convertedAmount,
                'recipient_currency' => $recipientCurrency,
            ]);
        });
    }

    /**
     * Withdraw money.
     */
    public function withdraw(Request $request)
    {
        $request->validate([
            'amount' => 'required|numeric|min:1',
        ]);

        $user = Auth::user()->load('chama');

        if ($user->balance < $request->amount) {
            throw ValidationException::withMessages([
                'amount' => ['Insufficient balance.'],
            ]);
        }

        return DB::transaction(function () use ($user, $request) {
            $user->decrement('balance', $request->amount);

            Transaction::create([
                'user_id' => $user->id,
                'type' => 'withdraw',
                'from_account' => 'personal',
                'to_account' => 'external', // Withdrawal to external account
                'amount' => -$request->amount, // Negative for withdrawal
                'currency' => $user->chama->currency ?? 'KES',
                'status' => 'completed',
                'recorded_by' => $user->id,
            ]);

            AuditService::log($user->id, 'withdrawal.processed', $user, null, ['amount' => $request->amount]);

            return response()->json([
                'message' => 'Withdrawal successful.',
                'balance' => $user->fresh()->balance,
            ]);
        });
    }

    /**
     * Search for a user by phone number.
     */
    public function searchUser(Request $request)
    {
        $request->validate([
            'phone' => 'required|string',
        ]);

        $user = User::where('phone', $request->phone)->with('chama')->first();

        if (!$user) {
            return response()->json(['message' => 'User not found'], 404);
        }

        if ($user->id === Auth::id()) {
            return response()->json(['message' => 'You cannot send to yourself'], 400);
        }

        return response()->json([
            'name' => $user->name,
            'phone' => $user->phone,
            'currency' => $user->chama->currency ?? 'KES',
        ]);
    }
}
