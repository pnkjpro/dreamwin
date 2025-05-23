<?php

namespace App\Http\Controllers;

use Razorpay\Api\Api;
use Illuminate\Support\Str;
use App\Models\FundTransaction;
use Illuminate\Http\Request;
use App\Models\User;
use Razorpay\Api\Errors\SignatureVerificationError;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Config;
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
        $this->rzp_webhook_secret = config('services.razorpay.webhook');
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

            // Step 1: Verify signature
            $this->api->utility->verifyPaymentSignature($attributes);

            // Step 2: Fetch payment details
            $payment = $this->api->payment->fetch($request->razorpay_payment_id);

            // Step 3: Check payment status
            if ($payment->status !== 'captured') {
                return $this->errorResponse([], "Payment not completed. Status: {$payment->status}", 400);
            }

            $fundTransaction = FundTransaction::where('razorpay_order_id', $request->razorpay_order_id)->first();
            
            if ($fundTransaction && ($fundTransaction->approved_status === 'pending' || $fundTransaction->approved_status === 'rejected')) {
                $fundTransaction->approved_status = 'approved';
                $fundTransaction->save();

                $user->increment('funds', $fundTransaction->amount);
            }

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

    public function handleWebhook(Request $request)
    {
        $payload = $request->getContent();
        $signature = $request->header('X-Razorpay-Signature');
        
        if (!$signature) {
            Log::channel('razorpay')->warning('Razorpay webhook signature missing');
            return response()->json(['status' => 'error', 'message' => 'Signature missing'], 400);
        }
        
        try {
            $this->api->utility->verifyWebhookSignature($payload, $signature, $this->rzp_webhook_secret);
            $data = json_decode($payload, true);
            
            // Process different event types
            switch ($data['event']) {    
                case 'payment.captured':
                    return $this->handlePaymentCaptured($data);
                    
                case 'payment.failed':
                    return $this->handlePaymentFailed($data);

                default:
                    Log::channel('razorpay')->info('Unhandled Razorpay webhook event', ['event' => $data['event']]);
                    return response()->json(['status' => 'received', 'message' => 'Webhook received but not processed']);
            }
            
        } catch (SignatureVerificationError $e) {
            Log::error('Razorpay webhook signature verification failed', ['error' => $e->getMessage()]);
            return response()->json(['status' => 'error', 'message' => 'Invalid signature'], 400);
            
        } catch (\Exception $e) {
            Log::error('Error processing Razorpay webhook', ['error' => $e->getMessage()]);
            return $this->exceptionHandler($e, 'Error processing Razorpay webhook' . $e->getMessage(), 500);
        }
    }
    
    protected function handlePaymentCaptured($data)
    {
        $payment = $data['payload']['payment']['entity'];  
        try {
            $fundTransaction = FundTransaction::where('razorpay_order_id', $payment['order_id'])->first();
            $user = User::findOrFail($fundTransaction->user_id);
            if($fundTransaction->approved_status === 'pending' || $fundTransaction->approved_status === 'rejected'){
                $fundTransaction->approved_status = 'approved';
                $fundTransaction->save();
                $user->increment('funds', $fundTransaction->amount);
            }
            
            // Here you can trigger other business logic like:
            // - Send confirmation email
            // - Update order status
            // - Trigger fulfillment process
            
            $receipt = [
                'payment_id' => $payment['id'],
                'name' => $user->name,
                'email' => $user->email,
                'mobile' => $user->mobile,
                'order_id' => $payment['order_id'],
                'amount' => $payment['amount'] / 100,
            ];
            Log::channel('razorpay')->info('Payment captured', $receipt);
            $officialEmail = Config::get('himpri.constant.email.official');
            \Mail::to([$officialEmail, $user->email])->send(new \App\Mail\PaymentReceiptMail($receipt));
            return response()->json(['status' => 'success']);
        } catch (\Exception $e) {
            Log::channel('razorpay')->error('Failed to process payment.captured webhook', ['error' => $e->getMessage()]);
            return $this->exceptionHandler($e, 'Failed to process payment.captured webhook' . $e->getMessage(), 500);
        }
    }
    
    protected function handlePaymentFailed($data)
    {
        $payment = $data['payload']['payment']['entity'];
        
        try {
            $fundTransaction = FundTransaction::with('user')->where('razorpay_order_id', $payment['order_id'])->first();
            if($fundTransaction->approved_status === 'pending'){
                $fundTransaction->approved_status = 'rejected';
                $fundTransaction->save();
            } 
            Log::channel('razorpay')->info('Payment failed', [
                'payment_id' => $payment['id'],
                'order_id' => $payment['order_id'],
                'name' => $fundTransaction->user->name,
                'email' => $fundTransaction->user->email,
                'mobile' => $fundTransaction->user->mobile,
                'amount' => $fundTransaction->amount,
                'error_code' => $payment['error_code'] ?? null,
                'error_description' => $payment['error_description'] ?? null,
            ]);
            return response()->json(['status' => 'success']);
        } catch (\Exception $e) {
            Log::channel('razorpay')->error('Failed to process payment.failed webhook', ['error' => $e->getMessage()]);
            return $this->exceptionHandler($e, 'Failed to process payment.failed webhook' . $e->getMessage(), 500);
        }
    }
}
