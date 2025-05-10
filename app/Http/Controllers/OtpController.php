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
            'label' => 'required|in:reset_password,verify_email'
        ]);

        $isEmailExists = User::where('email', $request->email)->exists();
        if($request->label === 'reset_password'){
            if(!$isEmailExists){
                return $this->errorResponse([], "User not found for this email", 400);
            }
        }

        $otp = rand(100000, 999999);
        $expiryTimestamp = now()->addMinutes(10)->timestamp; // <- this is a UNIX timestamp (seconds)

        $data = [
            'email' => $request->email,
            'otp' => $otp,
            'valid_on' => $expiryTimestamp,
        ];

        if ($isEmailExists) {
            $data['is_registered'] = 1;
        }

        $otpModal = OtpVerification::create($data);

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
            'label' => 'required|in:verify_email,reset_password'
        ]);

        $otpModal = OtpVerification::where([
            ['email', '=', $request->email],
            ['otp', '=', $request->otp],
            ['is_verified', '=', 0],
        ])->first();

        if (!$otpModal) {
            return $this->errorResponse([], 'Invalid OTP or Email.', 400);
        }

        // Check if OTP expired
        if (time() > $otpModal->valid_on) { // using PHP time()
            return $this->errorResponse([], 'OTP has expired.', 400);
        }

        if($request->label === 'verify_email'){
            $userModal = User::where('email', $request->email)->update([
                'email_verified_at' => now()
            ]);
        }

        if($request->label === 'reset_password'){
            //
        }

        $otpModal->update([
            'is_verified' => 1,
            'is_registered' => 1
            ]);

        $userController = new UserController();
        $userDetails = $userController->userDetails(new Request([
            'login' => $request->email
        ]));
        
        return $this->successResponse($userDetails->original['data'], 'OTP verified successfully!', 200);
    }

    
}
