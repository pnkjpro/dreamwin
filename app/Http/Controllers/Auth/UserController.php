<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\FundTransaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use App\Http\Controllers\OtpController;

use App\Traits\JsonResponseTrait;



class UserController extends Controller
{
    use JsonResponseTrait;
    public $OtpController;

    public function __construct()
    {
        $this->OtpController = new OtpController();
    }
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
            $referById = User::where('refer_code',$request->refer_code)->first()->id;
        }

        $avatars = Config::get('himpri.constant.avatars');
        $randomAvatar = '/avatars/' . Arr::random($avatars);

        $isOtpSent = $this->OtpController->sendOtp(new Request([
            'email' => $request->email,
            'label' => 'verify_email'
        ]));

        if($isOtpSent->original['success']){
            $user = User::create([
                'name' => $request->name,
                'avatar' => $randomAvatar,
                'email' => $request->email,
                'mobile' => $request->mobile,
                'password' => Hash::make($request->password),
                'refer_code' => $this->generateReferralCode(),
                'refer_by' => isset($referById) ? $referById : null,
            ]);
        } else {
            return $this->errorResponse([], $isOtpSent->original['message'], 500);
        }

        return $this->successResponse($user, "user created successfully!", 201);
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
        
        $userDetails = $this->userDetails($request);
        
        if (empty($userDetails->original['data']['user']) || !Hash::check($request->password, $userDetails->original['data']['user']->password)) {
            return $this->errorResponse([], "Invalid Credential", 422);
        }
        return $this->successResponse([
            'user' => $userDetails->original['data']['user'],
            'token' => $userDetails->original['data']['token']
        ], "User details is found", 200);
    }

    public function listReferredUsers(Request $request){
        $user = Auth::user();

        $referAmountEarned = FundTransaction::where('user_id', $user->id)
                                        ->where('action', 'referred_reward')
                                        ->sum('amount');
        $baseQuery = User::where('refer_by', $user->id);

        // Clone base query to avoid mutation
        $totalCount = (clone $baseQuery)->count();
        $claimedRewards = (clone $baseQuery)->where('is_reward_given', 1)->count();
        $pendingRewards = (clone $baseQuery)->where('is_reward_given', 0)->count();
        $referredUsers = (clone $baseQuery)->orderByDesc('id')->get();

        return $this->successResponse([
            'referred_users' => $referredUsers,
            'claimed_rewards' => $claimedRewards,
            'pending_rewards' => $pendingRewards,
            'refer_earned' => $referAmountEarned,
            'total_referred' => $totalCount
        ], "Referred Users Found Successfully", 200);
    }

    public function userDetails(Request $request)
    {
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

        $user->user_responses->makeHidden('user_id');
        $token = $user->createToken('auth_token')->plainTextToken;

        return $this->successResponse([
            'user' => $user,
            'token' => $token
        ], "User details is found", 200);
    }

    public function userList(Request $request)
    {
        $page = $request->input('page', 1);
        $limit = Config::get('himpri.constant.adminPaginationLimit'); 
        $offset = ($page - 1) * $limit; 
        
        // Start building the query with relationships
        $usersQuery = User::with([
            'referredBy:id,refer_code',
            'referredUsers:id,name,email,refer_code,refer_by'
        ])->orderByDesc('id');
        
        // Apply search filters
        if ($request->filled('name')) {
            $usersQuery->where('name', 'LIKE', '%' . $request->input('name') . '%');
        }
        
        if ($request->filled('email')) {
            $usersQuery->where('email', 'LIKE', '%' . $request->input('email') . '%');
        }
        
        if ($request->filled('mobile')) {
            $usersQuery->where('mobile', 'LIKE', '%' . $request->input('mobile') . '%');
        }
        
        if ($request->filled('upi_id')) {
            $usersQuery->where('upi_id', 'LIKE', '%' . $request->input('upi_id') . '%');
        }
        
        // Apply verification status filter
        if ($request->filled('verified_status')) {
            $verifiedStatus = $request->input('verified_status');
            if ($verifiedStatus === 'verified') {
                $usersQuery->whereNotNull('email_verified_at');
            } elseif ($verifiedStatus === 'not_verified') {
                $usersQuery->whereNull('email_verified_at');
            }
        }
        
        // Apply date range filters
        if ($request->filled('start_date')) {
            $usersQuery->whereDate('created_at', '>=', $request->input('start_date'));
        }
        
        if ($request->filled('end_date')) {
            $usersQuery->whereDate('created_at', '<=', $request->input('end_date'));
        }
        
        // Get total count after applying filters
        $totalCount = $usersQuery->count();
        
        // Get paginated results
        $users = $usersQuery->limit($limit)->offset($offset)->get();
        
        // Map the users data
        $users = $users->map(function($user) {
            return [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'email_verified_at' => $user->email_verified_at,
                'mobile' => $user->mobile,
                'upi_id' => $user->upi_id,
                'funds' => $user->funds,
                'refer_code' => $user->refer_code,
                'referred_by_code' => $user->referredBy?->refer_code,
                'created_at' => $user->created_at,
                'referred_users' => $user->referredUsers->map(function($refUser) {
                    return [
                        'id' => $refUser->id,
                        'name' => $refUser->name,
                        'email' => $refUser->email,
                        'refer_code' => $refUser->refer_code,
                    ];
                }),
            ];
        });
        
        // Calculate pagination info
        $hasMore = ($offset + $limit) < $totalCount;
        $currentPage = $page;
        $totalPages = ceil($totalCount / $limit);
        
        return $this->successResponse([
            'totalCount' => $totalCount,
            'users' => $users,
            'pagination' => [
                'current_page' => $currentPage,
                'total_pages' => $totalPages,
                'has_more' => $hasMore,
                'per_page' => $limit,
                'total' => $totalCount
            ]
        ], "Users have been fetched", 200);
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

    public function updatePassword(Request $request){
        $validator = Validator::make($request->all(),[
            'password' => 'required|min:6|confirmed',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse([], $validator->errors()->first(), 422);
        }

        $user = Auth::user();
        $user->update([
            'password' => Hash::make($request->password)
        ]);

        return $this->successResponse([], "Password has been reset", 200);
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
