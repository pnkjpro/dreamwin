<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use App\Models\UserLifeline;
use App\Models\UserResponse;
use App\Models\Lifeline;
use App\Models\LifelineUsage;
use App\Models\Quiz;
use App\Models\QuizVariant;
use App\Models\FundTransaction;
use Illuminate\Support\Facades\DB;


class QueryController extends Controller
{
    public $skipUsers;

    public function __construct(){
        $this->skipUsers = [1,54];
    }

    public function fundsNetDifference(){
        $users = User::where('email', 'not like', '%@himpri.com')
                        ->whereNotIn('id',$this->skipUsers)->get();
        $result = [];
        foreach($users as $user){
            $funds = (int) FundTransaction::where('user_id', $user->id)
                                            ->where('approved_status', 'approved')
                                            ->sum('amount');
            if($user->funds == $funds){
                // $result['verified_funds'][] = $user ->id;
            } else {
                $adjusted_amt = $funds - $user->funds;
                // User::find($user->id)->increment('funds', $adjusted_amt);
                if($user->funds > $funds){
                    $result['mismatched_funds']['user']['over'][$user->id]['net_diff'] = $funds - $user->funds; 
                } else if($funds > $user->funds){
                    $result['mismatched_funds']['user']['under'][$user->id]['net_diff'] = $funds - $user->funds; 
                }
            }
        }
        // $result['metainfo']['counts']['verified_funds'] = count($result['verified_funds']);
        $result['metainfo']['counts']['mismatched_funds']['over'] = isset($result['mismatched_funds']['user']['over']) ? count($result['mismatched_funds']['user']['over']) : 0;
        $result['metainfo']['counts']['mismatched_funds']['under'] = isset($result['mismatched_funds']['user']['under']) ? count($result['mismatched_funds']['user']['under']) : 0;  
        
        return response()->json($result);
    }

    public function netProfit(Request $request){
        $result = DB::table('fund_transactions as ft')
                            ->leftJoin('users as u', 'ft.user_id', '=', 'u.id')
                            ->where('u.email', 'not like', '%@himpri.com')
                            ->whereIn('ft.action', ['deposit', 'withdraw'])
                            ->whereNotIn('ft.user_id', $this->skipUsers)
                            ->where('ft.approved_status', 'approved')
                            ->sum('ft.amount');
        return response()->json($result);
    }

    public function netWithdrawal(Request $request){
        $netWithdrawal = DB::table('fund_transactions as ft')
                            ->leftJoin('users as u', 'ft.user_id', '=', 'u.id')
                            ->where('ft.action', 'withdraw')
                            ->where('ft.approved_status', 'approved')
                            ->whereNotIn('ft.user_id', $this->skipUsers)
                            ->where('u.email', 'not like', '%@himpri.com')
                            ->sum('ft.amount');
        return response()->json($netWithdrawal);
    }
}
