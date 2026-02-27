<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\PackageController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\SettingsController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
*/

// 1️⃣ Landing Page (For customers)
Route::get('/', function () {
    return Inertia::render('Welcome');
});

// 2️⃣ Protected Admin Routes
Route::middleware(['auth', 'verified'])->group(function () {
    
    // --- DASHBOARD ---
    Route::get('/dashboard', [DashboardController::class, 'index'])
        ->name('dashboard');

    // --- WIFI PACKAGES ---
    Route::get('/packages', [PackageController::class, 'index'])
        ->name('packages.index');

    Route::post('/packages', [PackageController::class, 'store'])
        ->name('packages.store');

    Route::delete('/packages/{package}', [PackageController::class, 'destroy'])
        ->name('packages.destroy');

    // --- SUBSCRIBERS (USERS) ---
    Route::get('/users', [UserController::class, 'index'])
        ->name('users');

    // ✅ NEW: User Detailed Profile Page
    Route::get('/users/{user}', [UserController::class, 'show'])
        ->name('users.show');

    // ✅ NEW: Suspend / Activate Toggle
    Route::post('/users/{user}/toggle', [UserController::class, 'toggleStatus'])
        ->name('users.toggle');

    // --- PAYMENTS (HISTORY) ---
    Route::get('/payments', [PaymentController::class, 'index'])
        ->name('payments');

    // --- SYSTEM SETTINGS ---
    Route::get('/settings', [SettingsController::class, 'index'])
        ->name('settings');
    

});

require __DIR__.'/auth.php';
