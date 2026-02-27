<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    // ✅ 1. REGISTER (Sign Up)
    public function register(Request $request)
    {
        // Validate
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'phone' => 'required|string|unique:users,phone',
            'password' => 'required|string|min:4|confirmed',
        ]);

        // Create User
        $user = User::create([
            'name' => $validated['name'],
            'phone' => $validated['phone'],
            'password' => Hash::make($validated['password']),
            'role' => 'user',
            'status' => 'active',
        ]);

        $user->load(['subscription.package', 'chama', 'transactions']);

        // Generate Token
        $token = $user->createToken('mobile-app')->plainTextToken;

        return response()->json([
            'message' => 'Account created successfully!',
            'user' => $user,
            'token' => $token
        ], 201);
    }

    // ✅ 2. LOGIN (Sign In)
    public function login(Request $request)
    {
        $request->validate([
            'phone' => 'required',
            'password' => 'required',
        ]);

        $user = User::where('phone', $request->phone)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'phone' => ['Invalid credentials provided.'],
            ]);
        }

        $user->load(['subscription.package', 'chama', 'transactions']);

        $token = $user->createToken('mobile-app')->plainTextToken;

        return response()->json([
            'message' => 'Welcome back!',
            'user' => $user,
            'token' => $token
        ]);
    }

    // ✅ 3. GET USER PROFILE
    public function user(Request $request)
    {
        return $request->user()->load(['subscription.package', 'chama', 'transactions']);
    }

    // ✅ 4. LOGOUT
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();
        return response()->json(['message' => 'Logged out successfully']);
    }
}