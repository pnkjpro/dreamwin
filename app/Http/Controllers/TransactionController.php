<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

use App\Models\FundTransaction;

use App\Traits\JsonResponseTrait;


class TransactionController extends Controller
{
    use JsonResponseTrait;
    public function make_transaction(Request $request){
        $validator = Validator::make($request->all(),[
            'amount' => 'required|numeric',
            'action' => 'required|in:deposit,withdraw'
        ]);
        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }
        $data = $validator->validated();
        $user = Auth::user();

        $transaction = FundTransaction::create([
            'user_id' => $user->id,
            'action' => $data['action'],
            'amount' => $data['amount']
        ]); 

        return $this->successResponse($transaction, "transaction has been made", 201);
    }

    public function fundApproval(Request $request){
        $validator = Validator::make($request->all(), [
            'uid' => 'required|exists:users,id',
            'approval_id' => 'required|exists:fund_transactions,id',
            'change_approval' => 'required|in:pending,approved,rejected'
        ]);

        
        if($validator->fails()){
            return response()->json(['error' => $validator->errors()], 422);
        }
        
        $data = $validator->validated();
        
        $user = User::findOrFail($data['uid']);
        $transaction = FundTransaction::findOrFail($data['approval_id']);

        DB::beginTransaction();
        try {
            $transaction->update(['approved_status' => $data['change_approval']]);

            if ($data['change_approval'] == 'approved') {
                if ($transaction->action == 'deposit') {
                    $user->increment('funds', $transaction->amount);
                } elseif ($transaction->action == 'withdraw') {
                    if ($user->funds >= $transaction->amount) {
                        $user->decrement('funds', $transaction->amount);
                    } else {
                        DB::rollBack();
                        return response()->json(['error' => 'Insufficient funds for withdrawal.'], 400);
                    }
                }
            }

            DB::commit();
            return $this->successResponse($transaction, "Transaction has been {$data['change_approval']}", 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'Something went wrong.'], 500);
        }


        return $this->successResponse($transaction, "Transaction has been {$data['change_approval']}", 201);
    }
}
