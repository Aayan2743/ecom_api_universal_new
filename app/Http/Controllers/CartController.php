<?php
namespace App\Http\Controllers;

use App\Models\PaymentLink;
use App\Models\UserCart;
use App\Services\Messenger360Service;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Razorpay\Api\Api;
use Razorpay\Api\Errors\SignatureVerificationError;

class CartController extends Controller
{
    public function sync(Request $request)
    {
        $user = $request->user();

        $cart = $request->cart ?? [];

        if (! is_array($cart)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid cart data',
            ], 422);
        }

        // 🔥 Clear existing cart
        UserCart::where('user_id', $user->id)->delete();

        // 🔥 Insert fresh cart
        foreach ($cart as $item) {
            UserCart::create([
                'user_id'    => $user->id,
                'product_id' => $item['id'],
                'variant_id' => $item['variationId'] ?? null,
                'quantity'   => $item['quantity'],
                'price'      => $item['price'],
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Cart synced successfully',
        ]);
    }

    /* ================= GET CART ================= */
    public function get(Request $request)
    {
        $cart = UserCart::where('user_id', $request->user()->id)
            ->with([
                'product:id,name,slug',
                'product.images',
                'variant',
            ])
            ->get();

        return response()->json([
            'success' => true,
            'data'    => $cart,
        ]);
    }

    /* ================= CLEAR CART ================= */
    public function clear(Request $request)
    {
        UserCart::where('user_id', $request->user()->id)->delete();

        return response()->json([
            'success' => true,
            'message' => 'Cart cleared',
        ]);
    }

    // for rozarpay

    public function createOrder(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'amount' => 'required|numeric|min:1',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
            ], 422);
        }

        $api = new Api(
            env('RAZORPAY_KEY'),
            env('RAZORPAY_SECRET')
        );

        $order = $api->order->create([
            'receipt'  => uniqid(),
            'amount'   => $request->amount * 100, // INR → paise
            'currency' => 'INR',
        ]);

        return response()->json([
            'success' => true,
            'order'   => $order->toArray(),
            'key'     => env('RAZORPAY_KEY'),
        ]);
    }

    /* ================= VERIFY PAYMENT ================= */
    public function verifyPayment(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'razorpay_order_id'   => 'required|string',
            'razorpay_payment_id' => 'required|string',
            'razorpay_signature'  => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
            ], 422);
        }

        $api = new Api(
            env('RAZORPAY_KEY_ID'),
            env('RAZORPAY_KEY_SECRET')
        );

        try {
            $api->utility->verifyPaymentSignature([
                'razorpay_order_id'   => $request->razorpay_order_id,
                'razorpay_payment_id' => $request->razorpay_payment_id,
                'razorpay_signature'  => $request->razorpay_signature,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Payment verified',
            ]);

        } catch (SignatureVerificationError $e) {
            return response()->json([
                'success' => false,
                'message' => 'Payment verification failed',
            ], 400);
        }
    }

    /* ================= SAVE ORDER AFTER PAYMENT ================= */
    public function saveOrder(Request $request)
    {

        $validator = Validator::make($request->all(), [
            'address_id'     => 'required|integer',
            'items'          => 'required|array|min:1',
            'payment_method' => 'required|string',
            'payment_id'     => 'required|string',
            'subtotal'       => 'required|numeric',
            'total_amount'   => 'required|numeric',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors'  => $validator->errors()->first(),
            ], 422);
        }

        // 🔥 Here you save order + order_items (example)
        // Order::create(...)
        // OrderItem::insert(...)

        return response()->json([
            'success' => true,
            'message' => 'Order saved successfully',
        ]);
    }

    /* ================= PAYMENT LINK ================= */

    public function createPaymentLink(Request $request, Messenger360Service $whatsapp)
    {
        try {

            $api = new Api(
                env('RAZORPAY_KEY'),
                env('RAZORPAY_SECRET')
            );

            $validator = Validator::make($request->all(), [
                'amount' => 'required|numeric|min:1',
                'name'   => 'required|string',
                'phone'  => 'required|digits:10',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => $validator->errors()->first(),
                ], 422);
            }

            $paymentLink = $api->paymentLink->create([
                'amount'          => (int) round($request->amount * 100),
                'currency'        => 'INR',
                'description'     => 'POS Order Payment',
                'customer'        => [
                    'name'    => $request->name,
                    'contact' => '91' . $request->phone,
                ],

                'notes'           => [
                    'sale_id' => $request->sale_id, // 🔥 IMPORTANT
                ],

                'notify'          => [
                    'sms' => true,
                ],
                'reminder_enable' => true,
            ]);

            $payment = PaymentLink::create([
                'razorpay_link_id' => $paymentLink['id'],
                'payment_link'     => $paymentLink['short_url'],
                'amount'           => $request->amount,
                'customer_name'    => $request->name,
                'customer_phone'   => $request->phone,
                'status'           => 'pending',
            ]);

            // 🔥 SEND WHATSAPP MESSAGE
            $phone = '91' . $request->phone;

            $message =
                "Hello {$request->name},\n\n" .
                "Please complete your payment using the link below:\n" .
                $paymentLink['short_url'] . "\n\n" .
                "Thank you.";

            $whatsapp->send($phone, $message);

            return response()->json([
                'success'      => true,
                'payment_link' => $paymentLink['short_url'],
                'link_id'      => $paymentLink['id'],
            ]);

        } catch (\Exception $e) {

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function razorpayWebhook(Request $request)
    {
        \Log::info('Razorpay Webhook', $request->all());

        try {

            if ($request->event == 'payment.captured') {

                $payment = $request->payload['payment']['entity'];

                $linkId = $request->payload['payment_link']['entity']['id'] ?? null;

                \Log::info('Payment Captured', [
                    'payment_id' => $payment['id'],
                    'link_id'    => $linkId,
                ]);

                if ($linkId) {

                    $updated = PaymentLink::where('razorpay_link_id', $linkId)
                        ->update([
                            'status'  => 'paid',
                            'paid_at' => now(),
                        ]);

                    \Log::info('Payment Data base', [
                        'updated_rows' => $updated,
                    ]);
                }

            }

            return response()->json(['success' => true]);

        } catch (\Exception $e) {

            \Log::error('Webhook Error', [
                'message' => $e->getMessage(),
            ]);

            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

        public function checkPaymentLink(Request $request)
    {
        try {

            $validator = Validator::make($request->all(), [
                'link_id' => 'required',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => $validator->errors()->first(),
                ]);
            }

            $api = new Api(
                env('RAZORPAY_KEY'),
                env('RAZORPAY_SECRET')
            );

            $link = $api->paymentLink->fetch($request->link_id);

            if ($link['status'] == 'paid') {

                PaymentLink::where('razorpay_link_id', $request->link_id)
                    ->update([
                        'status'  => 'paid',
                        'paid_at' => now(),
                    ]);

                return response()->json([
                    'success' => true,
                    'message' => 'Payment received',
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => 'Payment not completed',
            ]);

        } catch (\Exception $e) {

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ]);
        }
    }
}
