<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Lifeline;
use App\Models\UserLifeline;
use App\Models\LifelineUsage;
use App\Models\User;
use App\Models\FundTransaction;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;

use App\Traits\JsonResponseTrait;


class LifelineController extends Controller
{ use JsonResponseTrait;

    public function lifelines()
    {
        $lifelines = User::with('lifelines')->where('id', Auth::user()->id)->get();
        return $this->successResponse($lifelines, "Available Lifelines", 200);
    }

    public function fetchLifeline()
    {
        $lifelines = Lifeline::all();
        return $this->successResponse($lifelines, "Lifeline Details", 200);
    }
    
    public function userLifelines()
    {
        $userLifelines = auth()->user()->lifelines()
            ->with('lifeline')
            ->get()
            ->map(function($userLifeline) {
                return [
                    'id' => $userLifeline->lifeline->id,
                    'name' => $userLifeline->lifeline->name,
                    'description' => $userLifeline->lifeline->description,
                    'icon' => $userLifeline->lifeline->icon,
                    'quantity' => $userLifeline->quantity,
                    'last_used_at' => $userLifeline->last_used_at
                ];
            });
            
        return response()->json([
            'status' => 'success',
            'data' => $userLifelines
        ]);
    }

    public function updateLifeline(Request $request){
        $validator = Validator::make($request->all(), [
            'lifeline_id' => 'required|exists:lifelines,id',
            'cost' => 'required|integer'
        ]);

        if ($validator->fails()) {
            return $this->errorResponse([], $validator->errors(), 422);
        }

        $data = $validator->validated();

        $lifeline = Lifeline::findOrFail($data['lifeline_id']);

        $lifeline->update([
            'cost' => $data['cost']
        ]);

        return $this->successResponse([], 'Lifeline updated successfully!', 200);
    }

    public function lifelineTransactions(Request $request)
    {
        $user = Auth::user();
        $page = $request->input('page', 1);
        $limit = Config::get('himpri.constant.dashboardPaginationLimit'); 
        $offset = ($page - 1) * $limit; 
        $transactionsQuery = FundTransaction::where('user_id', $user->id)
                                ->where('action', 'lifeline_purchase')
                                ->orderBy('id', 'DESC');
        $totalCount = $transactionsQuery->count();
        $transactions = $transactionsQuery->limit($limit)->offset($offset)->get();
        return $this->successResponse([
            'totalCount' => $totalCount,
            'lifeline_transactions' => $transactions
        ], 'Lifeline Transactions has been fetched', 200);
    }

    public function lifelineUsageHistory(Request $request)
    {
        $user = Auth::user();
        $page = $request->input('page', 1);
        $limit = Config::get('himpri.constant.dashboardPaginationLimit'); 
        $offset = ($page - 1) * $limit; 
        $usageHistoryQuery = DB::table('lifeline_usages')
            ->leftJoin('user_responses', 'user_responses.id', '=', 'lifeline_usages.user_response_id')
            ->leftJoin('quizzes', 'quizzes.id', '=', 'user_responses.quiz_id')
            ->leftJoin('lifelines', 'lifelines.id', '=', 'lifeline_usages.lifeline_id')
            ->select('lifelines.name as lifeline_name', 'quizzes.title as applied_quiz_name', 'lifeline_usages.used_at')
            ->where('lifeline_usages.user_id', $user->id)
            ->orderByDesc('lifeline_usages.used_at');
        $totalCount = $usageHistoryQuery->count();
        $usageHistory = $usageHistoryQuery->limit($limit)->offset($offset)->get();

        return $this->successResponse([
            'totalCount' => $totalCount,
            'lifeline_histories' => $usageHistory
        ], 'Lifeline Usage HIstory has been fetched', 200);

    }
    
    public function purchaseLifeline(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'lifeline_id' => 'required|exists:lifelines,id',
            'quantity' => 'required|integer|min:1'
        ]);

        // If validation fails, return the error response
        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }
        $data = $validator->validated();
        
        $lifeline = Lifeline::findOrFail($request->lifeline_id);
        $user = Auth::user();
        
        // Calculate total cost
        $totalCost = $lifeline->cost * $request->quantity;
        
        // Check if user has enough funds
        if ($user->funds < $totalCost) {
            return response()->json([
                'status' => 'error',
                'message' => 'Insufficient funds to purchase lifeline'
            ], 403);
        }

        DB::beginTransaction();
        try{

            // Lock user row to prevent race conditions
            $user = User::where('id', $user->id)->lockForUpdate()->first();

            // Process the purchase
            $user->decrement('funds', $totalCost);
            
            // Add lifeline to user's inventory
            UserLifeline::updateOrCreate(
                [
                    'user_id' => $user->id,
                    'lifeline_id' => $lifeline->id
                ],
                [
                    'quantity' => DB::raw('quantity + ' . $request->quantity)
                ]
            );
            
            // Record the transaction
            FundTransaction::create([
                'user_id' => $user->id,
                'action' => 'lifeline_purchase',
                'amount' => -$totalCost,
                'description' => "Purchased {$request->quantity} {$lifeline->name} lifeline(s)",
                'reference_id' => $lifeline->id,
                'reference_type' => Lifeline::class,
                'approved_status' => 'approved'
            ]);

            DB::commit();
            return $this->successResponse([
                'lifeline' => $lifeline->only(['id', 'name', 'description', 'icon']),
                'quantity' => $request->quantity,
                'remaining_funds' => $user->funds
            ], "{$request->quantity} {$lifeline->name} lifeline(s) purchased successfully", 200);

        } catch (\Exception $e){
            DB::rollBack();
            return $this->exceptionHandler($e, $e->getMessage(), 500);
        }
        
    }

    public function storeLifelineUsage(Request $request)
    {
        // Validate the request data
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|exists:users,id',
            'lifeline_id' => 'required|exists:lifelines,id',
            'user_response_id' => 'required|exists:user_responses,id',
            'question_id' => 'required|integer',
            'result_data' => 'nullable|json',
        ]);

        // If validation fails, return the error response
        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Create a new LifelineUsage record
        $lifelineUsage = LifelineUsage::create([
            'user_id' => $request->user_id,
            'lifeline_id' => $request->lifeline_id,
            'user_response_id' => $request->user_response_id,
            'question_id' => $request->question_id,
            'used_at' => now(), // Automatically set the current timestamp
            'result_data' => $request->result_data,
        ]);

        // Return a success response with the created record
        return response()->json([
            'message' => 'Lifeline usage recorded successfully!',
            'data' => $lifelineUsage,
        ], 201);
    }
}
