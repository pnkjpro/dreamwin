<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;


class UserController extends Controller
{
    /**
     * Register a new user.
     */
    public function register(Request $request)
    {
        $validator = Validator::make($request->all(),[
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'mobile' => 'nullable|numeric|unique:users,mobile',
            'password' => 'required|min:6|confirmed',
        ]);

        // If validation fails, return the error response
        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'mobile' => $request->mobile,
            'password' => Hash::make($request->password),
        ]);

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'user' => $user,
            'token' => $token
        ], 201);
    }

    /**
     * Login User using Email or Mobile.
     */
    public function login(Request $request)
    {
        $request->validate([
            'login' => 'required', // Can be email or mobile
            'password' => 'required'
        ]);

        $user = User::with('lifelines')->where('email', $request->login)
                    ->orWhere('mobile', $request->login)
                    ->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'login' => ['Invalid credentials.'],
            ]);
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'user' => $user,
            'token' => $token
        ]);
    }

    public function fetchUser(Request $request){
        return response()->json([
            'user' => Auth::user()->load('lifelines')->makeHidden('password')
        ]);
    }

    /**
     * Verify Mobile Number (Simulating OTP Verification)
     */
    public function verifyMobile(Request $request)
    {
        $request->validate([
            'mobile' => 'required|string|exists:users,mobile',
        ]);

        $user = User::where('mobile', $request->mobile)->first();
        
        if (!$user) {
            return response()->json(['message' => 'User not found'], 404);
        }

        // Simulating OTP verification (In real-world, send OTP and verify)
        $user->update(['mobile_verified_at' => now()]);

        return response()->json(['message' => 'Mobile number verified successfully']);
    }

    /**
     * Logout User (Invalidate Token)
     */
    public function logout(Request $request)
    {
        $request->user()->tokens()->delete();

        return response()->json(['message' => 'Logged out successfully']);
    }
}
