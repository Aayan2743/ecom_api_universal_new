<?php
namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\OrderItem;
use App\Services\PhonePeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class PhonePayController extends Controller
{
    protected PhonePeService $phonePe;

    public function __construct(PhonePeService $phonePe)
    {
        $this->phonePe = $phonePe;
    }

    public function creates(Request $request)
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

        // Generate a clean ID
        $merchantOrderId = 'ORD' . time() . strtoupper(Str::random(4));

        // IMPORTANT: This route name must exist in your web.php
        $redirectUrl = route('phonepe.status', ['order_id' => $merchantOrderId]);

        $response = $this->phonePe->createPayment(
            (int) $request->amount,
            $merchantOrderId,
            $redirectUrl
        );

        // If PhonePe returns the redirectUrl, send it to frontend
        if (isset($response['redirectUrl'])) {
            return response()->json([
                'success'      => true,
                'order_id'     => $merchantOrderId,
                'checkout_url' => $response['redirectUrl'],
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'PhonePe API Error',
            'details' => $response,
        ], 400);
    }

    public function create(Request $request)
    {

        $validator = Validator::make($request->all(), [
            // 'user_id'    => 'required|exists:users,id',
            'address_id' => 'required|exists:addresses,id',
            'items'      => 'required|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors'  => $validator->errors()->first(),
            ], 422);
        }

        DB::beginTransaction();

        try {

            // 1️⃣ Calculate total
            $subtotal = 0;

            foreach ($request->items as $item) {
                $subtotal += $item['price'] * $item['quantity'];
            }

            $total = $subtotal;

            $userId = auth()->user()->id;

            // 2️⃣ Create order (pending)
            $order = Order::create([
                'user_id'        => $userId,
                'address_id'     => $request->address_id,
                'payment_method' => 'phonepe',
                'subtotal'       => $subtotal,
                'discount'       => 0,
                'total_amount'   => $total,
                'status'         => 'pending',
            ]);

            // 3️⃣ Save order items
            foreach ($request->items as $item) {
                OrderItem::create([
                    'order_id'   => $order->id,
                    'product_id' => $item['product_id'],
                    'quantity'   => $item['quantity'],
                    'price'      => $item['price'],
                    'total'      => $item['price'] * $item['quantity'],
                ]);
            }

            // 4️⃣ Generate merchant order id
            $merchantOrderId = 'ORD' . time() . strtoupper(Str::random(4));

            $order->update([
                'razorpay_order_id' => $merchantOrderId, // rename later to phonepe_order_id
            ]);

            // 5️⃣ Call PhonePe
            $redirectUrl = route('phonepe.status', [
                'order_id' => $merchantOrderId,
            ]);

            $response = $this->phonePe->createPayment(
                (int) $total,
                $merchantOrderId,
                $redirectUrl
            );

            DB::commit();

            return response()->json([
                'success'      => true,
                'checkout_url' => $response['redirectUrl'],
            ]);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ]);
        }
    }

    public function status(Request $request)
    {
        $merchantOrderId = $request->query('order_id');

        if (! $merchantOrderId) {
            return "<h1>Order ID missing</h1>";
        }

        $response = $this->phonePe->checkOrderStatus($merchantOrderId);

        $state = $response['state'] ?? 'FAILED';

        // Find order
        $order = Order::where('razorpay_order_id', $merchantOrderId)->first();

        if (! $order) {
            return "<h1>Order not found</h1>";
        }

        if ($state === 'COMPLETED') {

            $order->update([
                'status'              => 'paid',
                'razorpay_payment_id' => $response['transactionId'] ?? null,
            ]);

            return "<h1>✅ Payment Successful</h1>";
        }

        if ($state === 'PENDING') {
            return "<h1>⏳ Payment Pending</h1>
            <script>setTimeout(function(){ window.location.reload(); }, 5000);</script>";
        }

        $order->update([
            'status' => 'failed',
        ]);

        return "<h1>❌ Payment Failed</h1>";
    }

}
