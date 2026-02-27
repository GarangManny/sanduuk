<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Chama;
use App\Models\ChamaMember;
use App\Models\Transaction;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Services\AuditService;



class ChamaController extends Controller
{
    //
    public function index(Request $request)
    {
        $memberships = ChamaMember::where('user_id', $request->user()->id)->get();

        if ($memberships->isEmpty()) {
            return response()->json([], 200);
        }

        $chamaIds = $memberships->pluck('chama_id');
        $chamas = Chama::whereIn('id', $chamaIds)->get();

        return response()->json($chamas);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'contribution_amount' => 'required|numeric|min:1',
            'currency' => 'required|string|max:10',
            'contribution_period' => 'required|string'
        ]);

        $chama = Chama::create([
            'name' => $request->name,
            'admin_id' => auth()->id(),
            'total_balance' => 0,
            'invite_code' => strtoupper(Str::random(6)),
            'contribution_amount' => $request->contribution_amount,
            'currency' => $request->currency,
            'contribution_period' => $request->contribution_period,
        ]);

        // Automatically add creator as member
        ChamaMember::create([
            'chama_id' => $chama->id,
            'user_id' => auth()->id(),
            'role' => 'admin'
        ]);

        return response()->json([
            'message' => 'Chama created successfully',
            'chama' => $chama
        ]);
    }
    public function show($id)
    {
        $chama = Chama::with([
            'members.user',
            'transactions' => function ($q) {
                $q->with('user')->latest();
            }
        ])->findOrFail($id);

        $this->authorize('view', $chama);

        // --- Contribution lock: compute current period window ---
        $userId = auth()->id();
        $periodStart = match ($chama->contribution_period) {
            'weekly' => now()->startOfWeek(),
            'monthly' => now()->startOfMonth(),
            default => now()->startOfMonth(),
        };

        $contribution = $chama->transactions()
            ->where('user_id', $userId)
            ->where('type', 'contribution')
            ->where('updated_at', '>=', $periodStart)
            ->whereIn('status', ['pending', 'approved'])
            ->latest('updated_at')
            ->first();

        return response()->json(array_merge($chama->toArray(), [
            'user_contribution_status' => $contribution?->status ?? null,
            // null   → no contribution this period → button UNLOCKED
            // pending → awaiting admin approval   → button LOCKED
            // approved → done for this period     → button LOCKED
        ]));
    }

    public function contribute(Request $request, $id)
    {
        $user = $request->user();
        $chama = Chama::findOrFail($id);

        $this->authorize('contribute', $chama);

        $member = ChamaMember::where('chama_id', $id)
            ->where('user_id', $user->id)
            ->firstOrFail();

        // Removed balance decrement since it's physical cash
        // $user->decrement('balance', $request->amount);
        //$chama->increment('total_balance', $request->amount);
        //$member->increment('total_contribution', $request->amount);

        Transaction::create([
            'user_id' => $user->id,
            'chama_id' => $id,
            'type' => 'contribution',
            'recorded_by' => $user->id,
            'from_account' => 'cash',
            'to_account' => 'chama',
            'amount' => -$chama->contribution_amount, // Derived from DB, not request
            'status' => 'pending',
        ]);

        return response()->json(['message' => 'Contribution successful']);
    }

    public function join(Request $request)
    {
        // 1. Validate that 'id' was actually sent in the POST body
        $request->validate([
            'id' => 'required|exists:chamas,id'
        ]);

        // 2. Check if already a member (assuming a user belongs to many chamas through ChamaMember)
        $alreadyMember = ChamaMember::where('user_id', $request->user()->id)
            ->where('chama_id', $request->id)
            ->exists();

        if ($alreadyMember) {
            return response()->json(['message' => 'You are already a member of this chama.'], 400);
        }

        $chama = Chama::find($request->id);

        // 3. Create membership
        ChamaMember::create([
            'chama_id' => $chama->id,
            'user_id' => $request->user()->id,
            'total_contribution' => 0,
        ]);

        return response()->json([
            'message' => 'Joined successfully',
            'chama' => $chama // Include the chama object so the frontend can find res.data.chama.id
        ]);
    }
    public function approveContribution($id)
    {
        $transaction = Transaction::findOrFail($id);
        $chama = $transaction->chama;

        $this->authorize('manage', $chama);

        if ($transaction->status !== 'pending') {
            return response()->json(['message' => 'Already processed'], 400);
        }

        return DB::transaction(function () use ($transaction, $chama) {
            $transaction->update([
                'status' => 'approved'
            ]);

            $chama->increment('total_balance', abs($transaction->amount));

            // Update member's contribution history
            ChamaMember::where('chama_id', $chama->id)
                ->where('user_id', $transaction->user_id)
                ->increment('total_contribution', abs($transaction->amount));

            AuditService::log(auth()->id(), 'contribution.approved', $transaction, null, ['amount' => abs($transaction->amount)]);

            return response()->json(['message' => 'Contribution approved']);
        });
    }

    public function rejectContribution($id)
    {
        $transaction = Transaction::findOrFail($id);
        $chama = $transaction->chama;

        $this->authorize('manage', $chama);

        if ($transaction->status !== 'pending') {
            return response()->json(['message' => 'Already processed'], 400);
        }

        $transaction->update([
            'status' => 'rejected'
        ]);

        AuditService::log(auth()->id(), 'contribution.rejected', $transaction);

        return response()->json(['message' => 'Contribution rejected']);
    }

    public function loan(Request $request, $id)
    {
        $request->validate([
            'amount' => 'required|numeric|min:1'
        ]);

        $chama = Chama::findOrFail($id);
        $user = Auth::user();

        return DB::transaction(function () use ($id, $user, $request) {
            // lockForUpdate prevents race conditions during balance checks
            $chama = Chama::lockForUpdate()->findOrFail($id);

            $this->authorize('loan', $chama);

            if ($chama->total_balance < $request->amount) {
                return response()->json(['message' => 'Insufficient Chama balance'], 400);
            }

            // Deduct from Chama
            $chama->decrement('total_balance', $request->amount);

            // Add to User's personal balance
            $user->increment('balance', $request->amount);

            // Record transaction for Chama (Debit)
            Transaction::create([
                'user_id' => $user->id,
                'chama_id' => $chama->id,
                'type' => 'withdrawal', // Moving OUT of Chama
                'from_account' => 'chama',
                'to_account' => 'personal',
                'amount' => -$request->amount,
                'currency' => $chama->currency,
                'status' => 'completed',
                'recorded_by' => $user->id,
            ]);

            // Record transaction for User (Credit)
            Transaction::create([
                'user_id' => $user->id,
                'type' => 'deposit', // Moving INTO personal account
                'from_account' => 'chama',
                'to_account' => 'personal',
                'amount' => $request->amount,
                'currency' => $chama->currency,
                'status' => 'completed',
                'recorded_by' => $user->id,
            ]);

            AuditService::log($user->id, 'loan.processed', $chama, null, [
                'amount' => $request->amount,
                'user_id' => $user->id
            ]);

            return response()->json([
                'message' => 'Loan processed successfully',
                'chama_balance' => $chama->fresh()->total_balance,
                'user_balance' => $user->fresh()->balance,
            ]);
        });
    }

    public function adminNotifications()
    {
        $adminId = auth()->id();

        $chamas = Chama::where('admin_id', $adminId)->pluck('id');

        $pending = Transaction::with(['user', 'chama'])
            ->whereIn('chama_id', $chamas)
            ->where('status', 'pending')
            ->latest()
            ->get();

        return response()->json($pending);
    }
}