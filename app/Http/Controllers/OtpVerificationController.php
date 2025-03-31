<?php

namespace App\Http\Controllers;

use App\Models\OtpVerification;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class OtpVerificationController extends Controller
{
    /**
     * Send OTP to user's email or mobile
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function sendOtp(Request $request)
    {
        $request->validate([
            'type' => 'required|in:email,mobile',
            'value' => 'required|string',
        ]);

        $type = $request->type;
        $value = $request->value;
        $user = Auth::user();

        // Generate a 6-digit OTP
        $otp = random_int(100000, 999999);
        
        // Set expiration time (15 minutes from now)
        $validUntil = Carbon::now()->addMinutes(15)->timestamp;

        // Store the OTP in the database
        $otpVerification = OtpVerification::updateOrCreate(
            [
                'user_id' => $user->id,
                "$type" => $value,
                'is_verified' => 0,
            ],
            [
                'otp' => $otp,
                'valid_on' => $validUntil,
                'validated_at' => null,
            ]
        );

        // Send the OTP based on type
        if ($type === 'email') {
            // Send email with OTP
            // Mail::to($value)->send(new OtpMail($otp));
            
            // Placeholder for email sending logic
            // You would typically use Laravel's Mail facade here
            
            return response()->json([
                'message' => 'OTP sent to your email',
                'expires_at' => $validUntil,
            ]);
        } else {
            // Send SMS with OTP
            // Implement your SMS sending logic here
            // Example: SmsService::send($value, "Your OTP is: {$otp}");
            
            return response()->json([
                'message' => 'OTP sent to your mobile',
                'expires_at' => $validUntil,
            ]);
        }
    }

    /**
     * Verify OTP entered by user
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function verifyOtp(Request $request)
    {
        $request->validate([
            'type' => 'required|in:email,mobile',
            'value' => 'required|string',
            'otp' => 'required|integer|digits:6',
        ]);

        $type = $request->type;
        $value = $request->value;
        $otp = $request->otp;
        $user = Auth::user();
        $now = Carbon::now()->timestamp;

        // Find the OTP verification record
        $otpVerification = OtpVerification::where([
            'user_id' => $user->id,
            "$type" => $value,
            'otp' => $otp,
            'is_verified' => 0,
        ])->first();

        if (!$otpVerification) {
            return response()->json([
                'message' => 'Invalid OTP',
            ], 422);
        }

        // Check if OTP is expired
        if ($now > $otpVerification->valid_on) {
            return response()->json([
                'message' => 'OTP has expired',
            ], 422);
        }

        // Mark OTP as verified
        $otpVerification->update([
            'is_verified' => 1,
            'validated_at' => $now,
        ]);

        // Update user's verification status if needed
        // For example, if verifying email:
        if ($type === 'email') {
            $user->update([
                'email_verified_at' => Carbon::now(),
            ]);
        }

        return response()->json([
            'message' => ucfirst($type) . ' verified successfully',
        ]);
    }
}