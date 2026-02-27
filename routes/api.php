<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\PackageController;
use App\Http\Controllers\PaymentController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
| Stateless JSON routes for Expo Mobile App
|--------------------------------------------------------------------------
*/


/*
|--------------------------------------------------------------------------
| PUBLIC ROUTES (No Login Required)
|--------------------------------------------------------------------------
*/

// Register
Route::post('/register', [AuthController::class, 'register']);

// Login
Route::post('/login', [AuthController::class, 'login']);

// View WiFi Packages (public)
Route::get('/wifi-packages', [PackageController::class, 'apiIndex']);

// M-Pesa Callback (Safaricom must reach this without auth)
Route::post('/mpesa/callback', [App\Http\Controllers\MpesaController::class, 'callback']);


/*
|--------------------------------------------------------------------------
| PROTECTED ROUTES (Requires Sanctum Token)
|--------------------------------------------------------------------------
*/

Route::middleware('auth:sanctum')->group(function () {

    // 1️⃣ Get Logged-in User Profile
    Route::get('/user', [AuthController::class, 'user']);

    Route::get('/chamas', [App\Http\Controllers\Api\ChamaController::class, 'index']);
    Route::post('/chamas', [App\Http\Controllers\Api\ChamaController::class, 'store']);
    Route::get('/chamas/{id}', [App\Http\Controllers\Api\ChamaController::class, 'show']);
    Route::post('/chamas/join', [App\Http\Controllers\Api\ChamaController::class, 'join']);
    Route::post('/chamas/{id}/contribute', [App\Http\Controllers\Api\ChamaController::class, 'contribute']);
    Route::post('/chamas/{id}/loan', [App\Http\Controllers\Api\ChamaController::class, 'loan']);
    Route::post('/transactions/{id}/approve', [App\Http\Controllers\Api\ChamaController::class, 'approveContribution']);
    Route::post('/transactions/{id}/reject', [App\Http\Controllers\Api\ChamaController::class, 'rejectContribution']);
    Route::get('/notifications', [App\Http\Controllers\Api\ChamaController::class, 'adminNotifications']);

    // 💳 Transactions
    Route::post('/send', [App\Http\Controllers\Api\TransactionController::class, 'send']);
    Route::post('/withdraw', [App\Http\Controllers\Api\TransactionController::class, 'withdraw']);
    Route::get('/search-user', [App\Http\Controllers\Api\TransactionController::class, 'searchUser']);
    Route::get('/exchange-rate', function (Request $request) {
        $from = $request->query('from');
        $to = $request->query('to');
        if (!$from || !$to)
            return response()->json(['error' => 'Missing parameters'], 400);
        return response()->json(['rate' => \App\Services\CurrencyService::getRate($from, $to)]);
    });

    // 2️⃣ Initiate STK Push (Only logged-in users can buy)
    Route::post('/mpesa/stkpush', [App\Http\Controllers\MpesaController::class, 'stkPush']);

    // 3️⃣ Logout
    Route::post('/logout', [AuthController::class, 'logout']);

});
