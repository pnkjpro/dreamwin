<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Http\JsonResponse;
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
            return $this->errorResponse([], $validator->errors()->first(), 422);
        }
        $data = $validator->validated();
        $user = Auth::user();
        if($data['action'] == 'withdraw' && $data['amount'] > $user->funds){
            return $this->errorResponse([], "You have insufficient balance!", 422);
        }
        $isPendingWithdrawalExists = FundTransaction::where('user_id', $user->id)
                                                        ->where('action', 'withdraw')
                                                        ->where('approved_status', 'pending')
                                                        ->exists();
        if($isPendingWithdrawalExists){
            return $this->errorResponse([], "You already have a pending withdrawal request!");
        }
        try {
            $transaction = FundTransaction::create([
                'user_id' => $user->id,
                'action' => $data['action'],
                'amount' => $data['action'] == 'deposit' ? $data['amount'] : -$data['amount'],
                'transaction_id' => $data['transaction_id'] ?? ''
            ]); 
    
            $message = "";
            if($data['action'] == 'deposit'){
                $message = "Payment of ₹{$data['amount']} has been registered. Funds will be credited within 3 hours.";
            }else if($data['action'] == 'withdraw'){
                $message = "Withdrawal request for ₹{$data['amount']} has been submitted";
            }
    
            return $this->successResponse($transaction, $message, 201);
        } catch(Exception $e){
            return $this->exceptionHandler($e, $e->getMessage(), 500);
        }
    }

    public function fundApproval(Request $request){
        $validator = Validator::make($request->all(), [
            'uid' => 'required|exists:users,id',
            'approval_id' => 'required|exists:fund_transactions,id',
            'change_approval' => 'required|in:pending,approved,rejected'
        ]);

        
        if($validator->fails()){
            return $this->errorResponse([], $validator->errors()->first(), 422);
        }
        
        $data = $validator->validated();
        
        $user = User::findOrFail($data['uid']);
        $transaction = FundTransaction::findOrFail($data['approval_id']);
        $amount = abs($transaction->amount);
        DB::beginTransaction();
        try {
            $transaction->update(['approved_status' => $data['change_approval']]);

            if ($data['change_approval'] == 'approved') {
                if ($transaction->action == 'deposit') {
                    $user->increment('funds', $amount);
                } elseif ($transaction->action == 'withdraw') {
                    if ($user->funds >= $amount) {
                        $user->decrement('funds', $amount);
                    } else {
                        DB::rollBack();
                        return $this->errorResponse([], 'Insufficient funds for withdrawal', 422);
                    }
                }
            }

            DB::commit();
            return $this->successResponse($transaction, "Transaction has been {$data['change_approval']}", 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->exceptionHandler($e, $e->getMessage(), 500);
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
                            ->select('ft.id','ft.user_id','u.name', 'u.upi_id', 'ft.action', 'ft.amount', 'ft.razorpay_order_id', 'ft.description', 'ft.approved_status')
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

    // ============= Admin Enhanced Fund Transaction ========================
    public function getAllTransactions(Request $request): JsonResponse
    {
        try {
            // Validate request parameters
            $validator = Validator::make($request->all(), [
                'page' => 'integer|min:1',
                'per_page' => 'integer|min:1|max:100',
                'search' => 'string|max:255',
                'action' => 'string|in:deposit,withdraw,referred_reward,quiz_entry,quiz_reward,lifeline_purchase',
                'approved_status' => 'string|in:pending,approved,rejected',
                'date_from' => 'date',
                'date_to' => 'date|after_or_equal:date_from',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation error',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Get pagination parameters
            $page = $request->get('page', 1);
            $perPage = $request->get('per_page', 30);
            $offset = ($page - 1) * $perPage;

            // Build the query with joins
            $query = DB::table('fund_transactions as t')
                ->leftJoin('users as u', 't.user_id', '=', 'u.id')
                ->select([
                    't.id',
                    't.user_id',
                    't.action',
                    't.amount',
                    't.razorpay_order_id',
                    't.transaction_id',
                    't.description',
                    't.reference_id',
                    't.reference_type',
                    't.approved_status',
                    't.created_at',
                    't.updated_at',
                    'u.name',
                    'u.email',
                    'u.mobile',
                    'u.upi_id'
                ]);

            // Apply search filter
            if ($request->filled('search')) {
                $searchTerm = '%' . $request->get('search') . '%';
                $query->where(function ($q) use ($searchTerm) {
                    $q->where('u.name', 'LIKE', $searchTerm)
                      ->orWhere('u.email', 'LIKE', $searchTerm)
                      ->orWhere('u.mobile', 'LIKE', $searchTerm)
                      ->orWhere('u.upi_id', 'LIKE', $searchTerm)
                      ->orWhere('t.razorpay_order_id', 'LIKE', $searchTerm)
                      ->orWhere('t.transaction_id', 'LIKE', $searchTerm)
                      ->orWhere('t.reference_id', 'LIKE', $searchTerm);
                });
            }

            // Apply action filter
            if ($request->filled('action')) {
                $query->where('t.action', $request->get('action'));
            }

            // Apply status filter
            if ($request->filled('approved_status')) {
                $query->where('t.approved_status', $request->get('approved_status'));
            }

            // Apply date range filters
            if ($request->filled('date_from')) {
                $dateFrom = Carbon::parse($request->get('date_from'))->startOfDay();
                $query->where('t.created_at', '>=', $dateFrom);
            }

            if ($request->filled('date_to')) {
                $dateTo = Carbon::parse($request->get('date_to'))->endOfDay();
                $query->where('t.created_at', '<=', $dateTo);
            }

            // Get total count before pagination
            $totalCount = $query->count();

            // Apply pagination and ordering
            $transactions = $query
                ->orderBy('t.created_at', 'desc')
                ->orderBy('t.id', 'desc')
                ->offset($offset)
                ->limit($perPage)
                ->get();

            // Transform the data
            $transformedTransactions = $transactions->map(function ($transaction) {
                return [
                    'id' => $transaction->id,
                    'user_id' => $transaction->user_id,
                    'action' => $transaction->action,
                    'amount' => (float) $transaction->amount,
                    'razorpay_order_id' => $transaction->razorpay_order_id,
                    'transaction_id' => $transaction->transaction_id,
                    'description' => $transaction->description,
                    'reference_id' => $transaction->reference_id,
                    'reference_type' => $transaction->reference_type,
                    'approved_status' => $transaction->approved_status,
                    'created_at' => $transaction->created_at,
                    'updated_at' => $transaction->updated_at,
                    'name' => $transaction->name,
                    'email' => $transaction->email,
                    'mobile' => $transaction->mobile,
                    'upi_id' => $transaction->upi_id,
                ];
            });

            // Calculate pagination info
            $totalPages = ceil($totalCount / $perPage);
            $hasMorePages = $page < $totalPages;

            return response()->json([
                'success' => true,
                'message' => 'Transactions fetched successfully',
                'data' => [
                    'transactions' => $transformedTransactions,
                    'totalCount' => $totalCount,
                    'currentPage' => $page,
                    'perPage' => $perPage,
                    'totalPages' => $totalPages,
                    'hasMorePages' => $hasMorePages
                ]
            ]);

        } catch (\Exception $e) {
            // Log the error
            \Log::error('Error fetching transactions: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'request' => $request->all()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'An error occurred while fetching transactions',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Get transaction statistics
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function getTransactionStats(Request $request): JsonResponse
    {
        try {
            // Validate date filters
            $validator = Validator::make($request->all(), [
                'date_from' => 'date',
                'date_to' => 'date|after_or_equal:date_from',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation error',
                    'errors' => $validator->errors()
                ], 422);
            }

            $query = DB::table('fund_transactions');

            // Apply date filters if provided
            if ($request->filled('date_from')) {
                $dateFrom = Carbon::parse($request->get('date_from'))->startOfDay();
                $query->where('created_at', '>=', $dateFrom);
            }

            if ($request->filled('date_to')) {
                $dateTo = Carbon::parse($request->get('date_to'))->endOfDay();
                $query->where('created_at', '<=', $dateTo);
            }

            // Get statistics
            $stats = [
                'total_transactions' => $query->count(),
                'pending_transactions' => (clone $query)->where('approved_status', 'pending')->count(),
                'approved_transactions' => (clone $query)->where('approved_status', 'approved')->count(),
                'rejected_transactions' => (clone $query)->where('approved_status', 'rejected')->count(),
                'total_deposit_amount' => (clone $query)->where('action', 'deposit')->where('approved_status', 'approved')->sum('amount') ?? 0,
                'total_withdrawal_amount' => (clone $query)->where('action', 'withdraw')->where('approved_status', 'approved')->sum('amount') ?? 0,
                'pending_withdrawal_amount' => (clone $query)->where('action', 'withdraw')->where('approved_status', 'pending')->sum('amount') ?? 0,
            ];

            // Get action-wise breakdown
            $actionBreakdown = $query->select('action', 'approved_status')
                ->selectRaw('COUNT(*) as count, SUM(amount) as total_amount')
                ->groupBy('action', 'approved_status')
                ->get()
                ->groupBy('action');

            return response()->json([
                'success' => true,
                'message' => 'Transaction statistics fetched successfully',
                'data' => [
                    'statistics' => $stats,
                    'action_breakdown' => $actionBreakdown
                ]
            ]);

        } catch (\Exception $e) {
            \Log::error('Error fetching transaction stats: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'An error occurred while fetching statistics'
            ], 500);
        }
    }
}
