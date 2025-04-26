<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use App\Models\OtpVerification;

use App\Http\Controllers\Auth\UserController;

use App\Mail\SendOtpMail;
use Illuminate\Support\Facades\Mail;
use App\Traits\JsonResponseTrait;


class OtpController extends Controller
{
    use JsonResponseTrait;
    public function sendOtp(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
        ]);

        $otp = rand(100000, 999999);
        $expiryTimestamp = now()->addMinutes(10)->timestamp; // <- this is a UNIX timestamp (seconds)

        $data = [
            'email' => $request->email,
            'otp' => $otp,
            'valid_on' => $expiryTimestamp,
        ];

        $isEmailExists = User::where('email', $request->email)->exists();
        if ($isEmailExists) {
            $data['is_registered'] = 1;
        }

        $otpModal = OtpVerification::create($data);

        Mail::to($request->email)->send(new SendOtpMail($otp));
        try {
            Mail::to($request->email)->send(new SendOtpMail($otp));
        } catch (\Exception $e) {
            $otpModal->delete();
            return $this->errorResponse([], 'Failed to send OTP email. Please try again later.', 500);
        }

        return $this->successResponse([], 'OTP sent successfully!', 200);
    }


    public function verifyOtp(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'otp' => 'required|numeric|digits:6',
        ]);

        $otpModal = OtpVerification::where([
            ['email', '=', $request->email],
            ['otp', '=', $request->otp],
            ['is_verified', '=', 0],
        ])->first();

        if (!$otpModal) {
            return $this->errorResponse('Invalid OTP or Email.', 400);
        }

        // Check if OTP expired
        if (time() > $otpModal->valid_on) { // using PHP time()
            return $this->errorResponse('OTP has expired.', 400);
        }

        $otpModal->update([
            'is_verified' => 1,
            'is_registered' => 1
            ]);
        
        return $this->successResponse([], 'OTP verified successfully!', 200);
    }

    
}
