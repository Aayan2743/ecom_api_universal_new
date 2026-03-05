<?php
namespace App\Http\Controllers;

use App\Models\Payment;
use App\Services\PhonePeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class PhonePayController extends Controller
{
    protected PhonePeService $phonePe;

    public function __construct(PhonePeService $phonePe)
    {
        $this->phonePe = $phonePe;
    }

    /**
     * Create Payment
     */
    // public function create(Request $request)
    // {

    //     $validator = Validator::make($request->all(), [
    //         'amount' => 'required|numeric|min:1',
    //     ]);

    //     if ($validator->fails()) {
    //         return response()->json([
    //             'success' => false,
    //             'errors'  => $validator->errors()->first(),
    //         ], 422);
    //     }

    //     $merchantOrderId = 'ORDER_' . Str::uuid();

    //     // Payment::create([
    //     //     'merchant_order_id' => $merchantOrderId,
    //     //     'amount'            => $request->amount,
    //     //     'status'            => 'PENDING',
    //     // ]);

    //     $response = $this->phonePe->createPayment(
    //         amount: $request->amount,
    //         merchantOrderId: $merchantOrderId,
    //         redirectUrl: config('payment.phonepe.base_url') . '/payment-success?order_id=' . $merchantOrderId
    //     );

    //     if (! isset($response['redirectUrl'])) {
    //         return response()->json([
    //             'success'  => false,
    //             'response' => $response,
    //         ], 400);
    //     }

    //     return response()->json([
    //         'success'      => true,
    //         'order_id'     => $merchantOrderId,
    //         'checkout_url' => $response['redirectUrl'],
    //     ]);
    // }

    public function create(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'amount' => 'required|numeric|min:1',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors'  => $validator->errors()->first(),
            ], 422);
        }

        $merchantOrderId = 'ORDER_' . Str::uuid();

        // 1️⃣ Save Order First (Pending)
        // $order = Order::create([
        //     'user_id'           => auth()->id() ?? 1,
        //     'payment_method'    => 'phonepe',
        //     'subtotal'          => $request->amount,
        //     'total_amount'      => $request->amount,
        //     'status'            => 'pending',
        //     'razorpay_order_id' => $merchantOrderId,
        // ]);

        // 2️⃣ Call PhonePe
        $response = $this->phonePe->createPayment(
            amount: $request->amount,
            merchantOrderId: $merchantOrderId,
            redirectUrl: config('payment.phonepe.base_url') .
            '/payment-success?order_id=' . $merchantOrderId
        );

        if (! isset($response['redirectUrl'])) {
            return response()->json([
                'success'  => false,
                'response' => $response,
            ], 400);
        }

        return response()->json([
            'success'      => true,
            'order_id'     => $merchantOrderId,
            'checkout_url' => $response['redirectUrl'],
        ]);
    }

    /**
     * Check Status
     */

    public function status(Request $request)
    {
        $merchantOrderId = $request->input('merchantOrderId') ?? $request->query('order_id');

        if (! $merchantOrderId) {
            return response()->json(['message' => 'Order ID missing'], 400);
        }

        $response = $this->phonePe->checkOrderStatus($merchantOrderId);
        $state    = $response['state'] ?? 'FAILED';

        if ($state === 'COMPLETED') {
            // Update Database: Order::where('id', ...)->update(['status' => 'paid']);
            return "<h1>✅ Payment Successful!</h1><p>Order ID: $merchantOrderId</p>";
        }

        if ($state === 'PENDING') {
            return "<h1>⏳ Payment is Pending</h1>
                <p>We are waiting for confirmation from your bank. Please do not refresh.</p>
                <script>setTimeout(function(){ window.location.reload(); }, 5000);</script>";
        }

        return "<h1>❌ Payment Failed</h1><p>Status: $state</p>";
    }

}