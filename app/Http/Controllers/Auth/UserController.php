<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

use App\Traits\JsonResponseTrait;



class UserController extends Controller
{
    use JsonResponseTrait;
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
            'refer_code' => 'nullable|max:8|exists:users,refer_code'
        ]);

        // If validation fails, return the error response
        if ($validator->fails()) {
            return $this->errorResponse([], $validator->errors(), 422);
        }

        $referBy = null;
        if(isset($request->refer_code)){
            $referBy = User::where('refer_code',$request->refer_code)->first();
            $referBy->increment('funds', 10); // increase the amount by 10 when referred!
            $referById = User::where('refer_code',$request->refer_code)->first()->id;
        }

        $avatars = Config::get('himpri.constant.avatars');
        $randomAvatar = '/avatars/' . Arr::random($avatars);



        $user = User::create([
            'name' => $request->name,
            'avatar' => $randomAvatar,
            'email' => $request->email,
            'mobile' => $request->mobile,
            'password' => Hash::make($request->password),
            'refer_code' => $this->generateReferralCode(),
            'refer_by' => isset($referById) ? $referById : null,
        ]);

        $token = $user->createToken('auth_token')->plainTextToken;

        return $this->successResponse([
            'user' => $user,
            'token' => $token
        ], "user created successfully!", 201);
    }

    public function generateReferralCode()
    {
        $code = strtoupper(Str::random(8));
        while (User::where('refer_by', $code)->exists()) {
            $code = strtoupper(Str::random(8));
        }  
        return $code;
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

        $user = User::with([
            'lifelines',
            'user_responses' => function($query) {
                $query->select(['user_id', 'node_id', 'quiz_variant_id', 'score', 'status']);
            }
            ])->where('email', $request->login)
                    ->orWhere('mobile', $request->login)
                    ->first();
        if(empty($user)){
            return $this->errorResponse([], "User not found", 403);
        }

        // Hide user_id from responses before returning
        $user->user_responses->makeHidden('user_id');

        if (!$user || !Hash::check($request->password, $user->password)) {
            return $this->errorResponse([], "Invalid Credential", 422);
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        return $this->successResponse([
            'user' => $user,
            'token' => $token
        ], "user logged in successfully!", 200);


    }

    public function userList(Request $request){
        $page = $request->input('page', 1);
        $limit = Config::get('himpri.constant.adminPaginationLimit'); 
        $offset = ($page - 1) * $limit; 
        $users = User::orderByDesc('id')->limit($limit)->offset($offset)->get();
        return $this->successResponse($users, "Users has been fetched", 200);
    }

    public function fetchUser(Request $request){
        $user = Auth::user()->load([
            'lifelines', 
            'user_responses' => function($query) {
                $query->select(['user_id', 'node_id', 'quiz_variant_id', 'score', 'status']);
            }
        ]);

        // Hide user_id from responses before returning
        $user->user_responses->makeHidden('user_id');
        
        return response()->json([
            'user' => $user
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

    public function updatePaymentUpi(Request $request){
        $validator = Validator::make($request->all(), [
            'upi_id' => 'required|string|max:225'
        ]);

        // If validation fails, return the error response
        if ($validator->fails()) {
            return $this->errorResponse([], $validator->errors, 422);
        }

        $user = Auth::user();
        $user->update(['upi_id' => $request->upi_id]);

        return $this->successResponse([], "UPI ID has been updated successfully", 200);
    }

    /**
     * Logout User (Invalidate Token)
     */
    public function logout(Request $request)
    {
        // $request->user()->tokens()->delete();
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Logged out successfully']);
    }
}
