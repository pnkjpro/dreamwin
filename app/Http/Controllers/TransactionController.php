<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

use App\Models\FundTransaction;
use App\Models\User;

use App\Traits\JsonResponseTrait;


class TransactionController extends Controller
{
    use JsonResponseTrait;
    public function make_transaction(Request $request){
        $validator = Validator::make($request->all(),[
            'amount' => 'required|numeric',
            'action' => 'required|in:deposit,withdraw',
            'transaction_id' => 'required_if:action,deposit|string|max:22'
        ]);
        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }
        $data = $validator->validated();
        $user = Auth::user();
        try {
            $transaction = FundTransaction::create([
                'user_id' => $user->id,
                'action' => $data['action'],
                'amount' => $data['amount'],
                'transaction_id' => $data['transaction_id']
            ]); 
    
            $message = "";
            if($data['action'] == 'deposit'){
                $message = "Payment of ₹{$data['amount']} has been registered. Funds will be credited within 3 hours.";
            }else if($data['action'] == 'withdraw'){
                $message = "Withdrawal request for ₹{$data['amount']} has been submitted";
            }
    
            return $this->successResponse($transaction, $message, 201);
        } catch(Exception $e){
            return $this->errorResponse([], "Something Went Wrong, Please Try Again", 500);
        }
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
            return $this->errorResponse([], "Something Went Wrong!", 500);
        }


        return $this->successResponse($transaction, "Transaction has been {$data['change_approval']}", 201);
    }

    public function listAllTransactions(Request $request){
        // $transactions = FundTransaction::orderBy('created_at', 'DESC')->get();
        $page = $request->input('page', 1);
        $limit = Config::get('himpri.constant.adminPaginationLimit'); 
        $offset = ($page - 1) * $limit; 
        $transactionsQuery = DB::table('fund_transactions as ft')
                            ->leftjoin('users as u', function($join){
                                    $join->on('u.id', '=', 'ft.user_id');
                            })
                            ->select('ft.id','ft.user_id','u.name', 'u.upi_id', 'ft.action', 'ft.amount', 'ft.transaction_id', 'ft.description', 'ft.approved_status')
                            ->orderBy('ft.id','DESC');
        $totalCount = $transactionsQuery->count();
        $transactions = $transactionsQuery->limit($limit)->offset($offset)->get();
        return $this->successResponse([
            'totalCount' => $totalCount,
            'transactions' => $transactions], "Latest transactions has been fetched", 200);
    }

    public function listTransactions(Request $request){
        $user = Auth::user();
        $page = $request->input('page', 1);
        $limit = Config::get('himpri.constant.dashboardPaginationLimit'); 
        $offset = ($page - 1) * $limit; 
        $transactionsQuery = FundTransaction::where('user_id', $user->id)->orderBy('id', 'DESC');
        $totalCount = $transactionsQuery->count();
        $transactions = $transactionsQuery->limit($limit)->offset($offset)->get();

        return $this->successResponse([
            'totalCount' => $totalCount,
            'transactions' => $transactions
        ], "Transactions has been fetched", 200);
    }
}
