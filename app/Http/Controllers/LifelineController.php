<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Lifeline;
use App\Models\UserLifeline;
use App\Models\LifelineUsage;
use App\Models\User;
use App\Models\Transaction;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

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

             // If reward has not yet been claimed, process referral reward
            // Disable this feature for now
             // if($user->is_reward_given === 0){
            //     $this->referalRewardLifeline($user);
            // }

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
            Transaction::create([
                'user_id' => $user->id,
                'type' => 'lifeline_purchase',
                'amount' => -$totalCost,
                'description' => "Purchased {$request->quantity} {$lifeline->name} lifeline(s)",
                'reference_id' => $lifeline->id,
                'reference_type' => Lifeline::class
            ]);

            DB::commit();
            return $this->successResponse([
                'lifeline' => $lifeline->only(['id', 'name', 'description', 'icon']),
                'quantity' => $request->quantity,
                'remaining_funds' => $user->funds
            ], "{$request->quantity} {$lifeline->name} lifeline(s) purchased successfully", 200);

        } catch (\Exception $e){
            DB::rollBack();
            return $this->errorResponse([], $e->getMessage(), 500);
        }
        
    }

    private function referalRewardLifeline($user){
        if (!$user->refer_by) {
            return;
        }

        UserLifeline::create([
            'user_id' => $user->refer_by, //this is the user to whom the reward is given
            'lifeline_id' => 3, //revive lifeine given as reward
            'quantity' => 1
        ]);

        // Record the transaction
        Transaction::create([
            'user_id' => $user->id,
            'type' => 'referral_reward',
            'amount' => 0, // free rewards
            'description' => "Rewarded 1 Revive lifeline",
            'reference_id' => 3, //revive lifeline given
            'reference_type' => Lifeline::class
        ]);

        // now the reward has been claimed
        $user->update(['is_reward_given' => 1]);
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
