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


class LifelineController extends Controller
{
    public function index()
    {
        $lifelines = Lifeline::where('is_active', true)
            ->get();
            
        return response()->json([
            'status' => 'success',
            'data' => $lifelines
        ]);
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
        
        $lifeline = Lifeline::findOrFail($request->lifeline_id);
        // $user = auth()->user();
        $user = User::findOrFail(1);
        
        // Calculate total cost
        $totalCost = $lifeline->cost * $request->quantity;
        
        // Check if user has enough funds
        if ($user->funds < $totalCost) {
            return response()->json([
                'status' => 'error',
                'message' => 'Insufficient funds to purchase lifeline'
            ], 403);
        }
        
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
        
        return response()->json([
            'status' => 'success',
            'message' => "{$request->quantity} {$lifeline->name} lifeline(s) purchased successfully",
            'data' => [
                'lifeline' => $lifeline->only(['id', 'name', 'description', 'icon']),
                'quantity' => $request->quantity,
                'remaining_points' => $user->points
            ]
        ]);
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
