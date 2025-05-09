<?php

namespace App\Http\Controllers;

use Razorpay\Api\Api;
use Illuminate\Support\Str;
use App\Models\FundTransaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use App\Traits\JsonResponseTrait;

class RazorpayController extends Controller
{
    use JsonResponseTrait;

    protected $rzr_key;
    protected $rzr_secret;
    protected $api;

    public function __construct(){
        $this->rzr_key = config('services.razorpay.key');
        $this->rzr_secret = config('services.razorpay.secret');
        $this->api = new Api($this->rzr_key, $this->rzr_secret);
    }

    public function createOrder(Request $request)
    {
        $amount = $request->amount;

        if ($amount < 1) {
            return $this->errorResponse([], "Minimum payment amount is ₹1", 400);
        }

        $user = Auth::user();
        $order = $this->api->order->create([
            'receipt'         => Str::random(20),
            'amount'          => (int)$amount * 100, // in paise
            'currency'        => 'INR',
            'payment_capture' => 1
        ]);

        FundTransaction::create([
            'user_id' => $user->id,
            'action' => 'deposit',
            'razorpay_order_id' => $order['id'],
            'amount' => $amount,
            'description' => "Razorpay Payment of Rs $amount",
            'approved_status' => 'pending'
        ]);

        return $this->successResponse([
            'order_id' => $order['id'],
            'key'      => $this->rzr_key,
            'amount'   => $order['amount'],
            'currency' => $order['currency']
        ], "Payment is successfully initiated", 200);
    }

    public function verifyPayment(Request $request)
    {
        $request->validate([
            'razorpay_payment_id' => 'required|string',
            'razorpay_order_id' => 'required|string',
            'razorpay_signature' => 'required|string',
        ]);
        $user = Auth::user();
        try {
            $attributes = [
                'razorpay_payment_id' => $request->razorpay_payment_id,
                'razorpay_order_id' => $request->razorpay_order_id,
                'razorpay_signature' => $request->razorpay_signature,
            ];

            $fundTransaction = FundTransaction::where('razorpay_order_id', $request->razorpay_order_id)->first();

            $this->api->utility->verifyPaymentSignature($attributes);
            
            $fundTransaction->approved_status = 'approved';
            $fundTransaction->save();
            $user->increment('funds', $fundTransaction->amount);

            \Log::channel('razorpay')->info("Payment Successful:", $fundTransaction->toArray());
            return $this->successResponse([], "Payment verified successfully", 200);

        } catch (\Exception $e) {
            \Log::channel('razorpay')->error("Razorpay Exception:", [
                'line' => $e->getLine(),
                'errorMessage' => $e->getMessage(),
                'file' => $e->getFile()
            ]);

            return $this->exceptionHandler($e, $e->getMessage(), 500);
        }
    }
}
