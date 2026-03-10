<?php
namespace App\Http\Controllers;

use App\Models\PendingSale;
use App\Models\Product;
use App\Models\ProductBarcode;
use App\Models\ProductVariantCombination;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\User;
use App\Services\Messenger360Service;
use App\Services\Shiprocket\ShiprocketOrderService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class posController extends Controller
{

    protected $messenger;

    public function __construct(Messenger360Service $messenger)
    {
        $this->messenger = $messenger;
    }
    public function store(Request $request)
    {

        $validator = Validator::make($request->all(), [
            'customer_id'              => 'nullable|exists:users,id',
            'payment_method'           => 'required|string',
            'paid_amount'              => 'required|numeric|min:0',

            'customer_name'            => 'nullable|string|max:255',
            'customer_phone'           => 'nullable|string|max:20',

            // ✅ Address JSON validation
            'address_snapshot'         => 'nullable|array',
            'address_snapshot.address' => 'nullable|string',
            'address_snapshot.city'    => 'nullable|string',
            'address_snapshot.state'   => 'nullable|string',
            'address_snapshot.pincode' => 'nullable|string',

            'items'                    => 'required|array|min:1',

            'items.*.product_id'       => 'required|integer|exists:products,id',
            'items.*.variant_id'       => 'required|integer|exists:product_variant_combinations,id',
            'items.*.qty'              => 'required|integer|min:1',

            'items.*.barcode_id'       => 'nullable|exists:product_barcodes,id',

        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors'  => $validator->errors()->first(),
            ], 422);
        }

        DB::beginTransaction();

        try {

            $subtotal      = 0;
            $discountTotal = 0;
            $taxTotal      = 0;

            $itemsData = [];

            foreach ($request->items as $item) {

                $variant = ProductVariantCombination::with('product', 'images')
                    ->lockForUpdate()
                    ->findOrFail($item['variant_id']);

                // 🚫 Prevent overselling
                if ($variant->quantity < $item['qty']) {
                    return response()->json([
                        'success' => false,
                        'message' => "Insufficient stock for {$variant->sku}",
                    ], 422);
                }

                $price    = $variant->extra_price;
                $discount = $variant->discount ?? 0;
                $tax      = 0; // Add GST logic if needed

                $lineTotal = ($price - $discount) * $item['qty'];

                $subtotal      += $price * $item['qty'];
                $discountTotal += $discount * $item['qty'];

                $itemsData[]  = [
                    'variant'  => $variant,
                    'price'    => $price,
                    'discount' => $discount,
                    'tax'      => $tax,
                    'qty'      => $item['qty'],
                    'total'    => $lineTotal,
                ];
            }

            $grandTotal   = $subtotal - $discountTotal + $taxTotal;
            $changeAmount = $request->paid_amount - $grandTotal;

            if ($changeAmount < 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Paid amount is insufficient',
                ], 422);
            }

            // ✅ Build Address Snapshot
            // $addressSnapshot = [
            //     'name'    => $request->customer_name,
            //     'phone'   => $request->customer_phone,
            //     'address' => $request->address_snapshot['address'],
            //     'city'    => $request->address_snapshot['city'],
            //     'state'   => $request->address_snapshot['state'],
            //     'country' => $request->address_snapshot['country'] ?? 'India',
            //     'pincode' => $request->address_snapshot['pincode'],
            // ];

            $address = $request->address_snapshot ?? [];

            $addressSnapshot  = [
                'name'    => $request->customer_name,
                'phone'   => $request->customer_phone,
                'address' => $address['address'] ?? null,
                'city'    => $address['city'] ?? null,
                'state'   => $address['state'] ?? null,
                'country' => $address['country'] ?? 'India',
                'pincode' => $address['pincode'] ?? null,
            ];

            // 🧾 Create Sale
            $sale = Sale::create([
                'invoice_number'            => 'INV-' . now()->format('YmdHis') . '-' . rand(100, 999),
                'customer_id'               => $request->customer_id,
                'subtotal'                  => $subtotal,
                'discount_total'            => $discountTotal,
                'tax_total'                 => $taxTotal,
                'grand_total'               => $grandTotal,

                'payment_method'            => $request->payment_method,
                'paid_amount'               => $request->paid_amount,
                'change_amount'             => $changeAmount,

                'customer_name'             => $request->customer_name,
                'customer_phone'            => $request->customer_phone,
                // ✅ SAVE JSON COLUMN HERE
                'shipping_address_snapshot' => $addressSnapshot,
                'status'                    => 'created',
            ]);

            // 🧾 Save Sale Items + Deduct Stock
            // foreach ($itemsData as $data) {

            //     $variant = $data['variant'];

            //     SaleItem::create([
            //         'sale_id'                => $sale->id,

            //         'product_id'             => $variant->product->id,
            //         'variant_combination_id' => $variant->id,

            //         'product_name'           => $variant->product->name,
            //         'variant_name'           => $variant->sku,
            //         'sku'                    => $variant->sku,

            //         'product_image'          => optional($variant->images->first())->image_path
            //             ? asset('storage/' . $variant->images->first()->image_path)
            //             : null,

            //         'price'                  => $data['price'],
            //         'discount'               => $data['discount'],
            //         'tax'                    => $data['tax'],

            //         'quantity'               => $data['qty'],
            //         'total'                  => $data['total'],
            //     ]);

            //     // 🔥 Deduct Stock
            //     $variant->decrement('quantity', $data['qty']);
            // }



            foreach ($itemsData as $index => $data) {

    $variant = $data['variant'];

    SaleItem::create([
        'sale_id'                => $sale->id,
        'product_id'             => $variant->product->id,
        'variant_combination_id' => $variant->id,
        'product_name'           => $variant->product->name,
        'variant_name'           => $variant->sku,
        'sku'                    => $variant->sku,

        'product_image'          => optional($variant->images->first())->image_path
            ? asset('storage/' . $variant->images->first()->image_path)
            : null,

        'price'                  => $data['price'],
        'discount'               => $data['discount'],
        'tax'                    => $data['tax'],
        'quantity'               => $data['qty'],
        'total'                  => $data['total'],
    ]);

    // Deduct Stock
    $variant->decrement('quantity', $data['qty']);

    $barcodeId = $request->items[$index]['barcode_id'] ?? null;

    /*
    |--------------------------------------------------------------------------
    | CASE 1 : Barcode Scanned
    |--------------------------------------------------------------------------
    */

    if ($barcodeId) {

        ProductBarcode::where('id', $barcodeId)
            ->where('is_used', false)
            ->update([
                'is_used' => true,
                // 'used_at' => now()
            ]);
    }

    /*
    |--------------------------------------------------------------------------
    | CASE 2 : Manual Add (No Barcode)
    |--------------------------------------------------------------------------
    */

    else {

        ProductBarcode::where('variant_id', $variant->id)
            ->where('is_used', false)
            ->limit($data['qty'])
            ->update([
                'is_used' => true,
                // 'used_at' => now()
            ]);
    }
}

            DB::commit();

            // return response()->json([
            //     'success' => true,
            //     'message' => 'Order created successfully',
            //     'data'    => [
            //         'sale_id'        => $sale->id,
            //         'invoice_number' => $sale->invoice_number,
            //         'grand_total'    => $sale->grand_total,
            //     ],
            // ]);


            try {

    if ($request->customer_phone) {

        // $phone = '91' . ltrim($request->customer_phone, '0');
        $phone = $request->customer_phone;

        $message  = "🧾 *Order Invoice*\n";
        $message .= "Invoice: {$sale->invoice_number}\n\n";

        $message .= "📦 *Items*\n";

        foreach ($sale->items as $item) {

            $message .= "• {$item->product_name}\n";
            $message .= "Qty: {$item->quantity}\n";
            $message .= "Price: ₹{$item->price}\n";
            $message .= "Total: ₹{$item->total}\n\n";

        }

        $message .= "Subtotal: ₹{$sale->subtotal}\n";
        $message .= "Tax: ₹{$sale->tax_total}\n";
        $message .= "*Grand Total: ₹{$sale->grand_total}*\n\n";

        $message .= "🙏 *Thank you for visiting Sri Devi Herbals*\n";
        $message .= "🌿 Welcome again!";

        $this->messenger->send($phone, $message);
    }

} catch (\Exception $e) {

    Log::error("WhatsApp send failed: ".$e->getMessage());

}

            return response()->json([
                'success' => true,
                'message' => 'Order created successfully',
                'data'    => [
                    'sale_id'        => $sale->id,
                    'invoice_number' => $sale->invoice_number,
                    'grand_total'    => $sale->grand_total,
                    'subtotal'       => $sale->subtotal,
                    'tax_total'      => $sale->tax_total,
                    'items'          => $sale->items()->get()->map(function ($item) {

                        return [
                            'product_name' => $item->product_name,
                            'qty'          => $item->quantity,
                            'total'        => $item->total,
                        ];

                    }),
                ],
            ]);

        } catch (\Exception $e) {

            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Something went wrong',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    public function sendOrderOtp_w(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'customer_id'              => 'nullable|exists:users,id',
            'customer_name'            => 'required|string',
            'customer_phone'           => 'required|string',
            'items'                    => 'required|array|min:1',
            'items.*.variant_id'       => 'required|integer|exists:product_variant_combinations,id',
            'items.*.qty'              => 'required|integer|min:1',
            'address_snapshot'         => 'nullable|array',
            'address_snapshot.address' => 'nullable|string',
            'address_snapshot.city'    => 'nullable|string',
            'address_snapshot.state'   => 'nullable|string',
            'address_snapshot.pincode' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors'  => $validator->errors()->first(),
            ], 422);
        }

        $itemsFormatted = [];
        $subtotal       = 0;

        foreach ($request->items as $item) {

            $variant = ProductVariantCombination::with('product')
                ->findOrFail($item['variant_id']);

            $price     = $variant->extra_price;
            $qty       = $item['qty'];
            $lineTotal = $price * $qty;

            $subtotal += $lineTotal;

            $itemsFormatted[] = [
                'product_name' => $variant->product->name,
                'variant_name' => $variant->sku,
                'price'        => $price,
                'qty'          => $qty,
                'total'        => $lineTotal,
            ];
        }

        $grandTotal = $subtotal;

        $snapshot  = [
            'customer_name'    => $request->customer_name,
            'customer_phone'   => $request->customer_phone,
            'items'            => $itemsFormatted,
            'subtotal'         => $subtotal,
            'discount'         => 0,
            'grand_total'      => $grandTotal,
            'address_snapshot' => $request->address_snapshot,
        ];

        $otp = rand(100000, 999999);

        $pending = PendingSale::create([
            'customer_id'    => $request->customer_id,
            'order_snapshot' => $snapshot,
            'otp'            => $otp,
            'expires_at'     => Carbon::now()->addMinutes(5),
        ]);

        // Format WhatsApp message
        $message = $this->formatOrderMessage($snapshot, $otp);

        // Send WhatsApp
        // $this->messenger->send(
        //     $request->customer_phone,
        //     $message
        // );

        $response = $this->messenger->send(
            $request->customer_phone,
            $message
        );

        return response()->json([
            'success'    => true,
            'message'    => 'OTP sent successfully',
            'pending_id' => $pending->id,
            'response'   => $response,
        ]);
    }

    public function sendOrderOtp(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'customer_id'              => 'nullable|exists:users,id',
            'customer_name'            => 'required|string',
            'customer_phone'           => 'required|string',

            'items'                    => 'required|array|min:1',
            'items.*.variant_id'       => 'required|integer|exists:product_variant_combinations,id',
            'items.*.qty'              => 'required|integer|min:1',

            'address_snapshot'         => 'nullable|array',
            'address_snapshot.address' => 'nullable|string',
            'address_snapshot.city'    => 'nullable|string',
            'address_snapshot.state'   => 'nullable|string',
            'address_snapshot.pincode' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors'  => $validator->errors()->first(),
            ], 422);
        }

        $itemsFormatted = [];
        $subtotal       = 0;

        foreach ($request->items as $item) {

            $variant = ProductVariantCombination::with('product')
                ->findOrFail($item['variant_id']);

            $price     = $variant->extra_price;
            $qty       = $item['qty'];
            $lineTotal = $price * $qty;

            $subtotal += $lineTotal;

            $itemsFormatted[] = [
                'product_name' => $variant->product->name,
                'variant_name' => $variant->sku,
                'price'        => $price,
                'qty'          => $qty,
                'total'        => $lineTotal,
            ];
        }

        $grandTotal = $subtotal;

        /*
    |--------------------------------------------------------------------------
    | Order Snapshot
    |--------------------------------------------------------------------------
    */

        $snapshot = [
            'customer_name'  => $request->customer_name,
            'customer_phone' => $request->customer_phone,
            'items'          => $itemsFormatted,
            'subtotal'       => $subtotal,
            'discount'       => 0,
            'grand_total'    => $grandTotal,
        ];

        // Add address only if provided
        if ($request->filled('address_snapshot')) {
            $snapshot['address_snapshot'] = $request->address_snapshot;
        }

        /*
    |--------------------------------------------------------------------------
    | Generate OTP
    |--------------------------------------------------------------------------
    */

        $otp = rand(100000, 999999);

        $pending = PendingSale::create([
            'customer_id'    => $request->customer_id,
            'order_snapshot' => $snapshot,
            'otp'            => $otp,
            'expires_at'     => Carbon::now()->addMinutes(5),
        ]);

        /*
    |--------------------------------------------------------------------------
    | Format WhatsApp Message
    |--------------------------------------------------------------------------
    */

        $message = $this->formatOrderMessage($snapshot, $otp);

        /*
    |--------------------------------------------------------------------------
    | Send WhatsApp
    |--------------------------------------------------------------------------
    */

        $response = $this->messenger->send(
            $request->customer_phone,
            $message
        );

        return response()->json([
            'success'    => true,
            'message'    => 'OTP sent successfully',
            'pending_id' => $pending->id,
                'otp'        => $pending->otp,
            'response'   => $response,
        ]);
    }

    private function formatOrderMessage_w($snapshot, $otp)
    {
        $message  = "🧾 *Order Confirmation*\n\n";
        $message .= "👤 Name: {$snapshot['customer_name']}\n";
        $message .= "📱 Phone: {$snapshot['customer_phone']}\n\n";

        $message .= "🛍 *Items:*\n";

        foreach ($snapshot['items'] as $item) {
            $message .= "- {$item['product_name']} ({$item['variant_name']})\n";
            $message .= "  ₹{$item['price']} x {$item['qty']} = ₹{$item['total']}\n";
        }

        $message .= "\n💰 Subtotal: ₹{$snapshot['subtotal']}";
        $message .= "\n🎯 Discount: ₹{$snapshot['discount']}";
        $message .= "\n🧮 Total: ₹{$snapshot['grand_total']}\n\n";

        $address = $snapshot['address_snapshot'];

        $message .= "📍 *Delivery Address:*\n";
        $message .= "{$address['address']}, {$address['city']}, {$address['state']} - {$address['pincode']}\n\n";

        $message .= "🔐 Your OTP is: *{$otp}*\n";
        $message .= "⏳ Valid for 5 minutes.";

        return $message;
    }

    private function formatOrderMessage($snapshot, $otp)
    {
        $message = "🛒 *Order Summary*\n\n";

        $message .= "👤 Customer: " . $snapshot['customer_name'] . "\n";
        $message .= "📞 Phone: " . $snapshot['customer_phone'] . "\n\n";

        $message .= "*Items*\n";

        foreach ($snapshot['items'] as $item) {

            $message .= "• " . $item['product_name'];
            $message .= " (" . $item['variant_name'] . ")";
            $message .= " x " . $item['qty'];
            $message .= " = ₹" . $item['total'] . "\n";
        }

        $message .= "\nSubtotal: ₹" . $snapshot['subtotal'];
        $message .= "\nGrand Total: ₹" . $snapshot['grand_total'] . "\n";

        /*
    |--------------------------------------------------------------------------
    | Optional Address
    |--------------------------------------------------------------------------
    */

        $addr = $snapshot['address_snapshot'];

        if (! empty($addr)) {

            $message .= "\n📍 *Delivery Address*\n";

            if (! empty($addr['address'])) {
                $message .= $addr['address'] . "\n";
            }

            if (! empty($addr['city'])) {
                $message .= $addr['city'] . "\n";
            }

            if (! empty($addr['state'])) {
                $message .= $addr['state'] . "\n";
            }

            if (! empty($addr['pincode'])) {
                $message .= $addr['pincode'] . "\n";
            }
        }

        $message .= "\n🔐 *OTP*: " . $otp;
        $message .= "\nValid for 5 minutes.";

        return $message;
    }

    public function verifyOrderOtp(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'otp'        => 'required|digits:6',
            'pending_id' => 'required|exists:pending_sales,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
            ], 422);
        }

        // Allow only last 5 minutes OTP
        $pending = PendingSale::where('id', $request->pending_id)
            ->where('otp', $request->otp)
            ->where('created_at', '>=', now()->subMinutes(5))
            ->first();

        if (! $pending) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid or expired OTP',
            ], 422);
        }

        if ($pending->verified_at) {
            return response()->json([
                'success' => false,
                'message' => 'OTP already used',
            ], 422);
        }

        $pending->update([
            'verified_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'OTP verified successfully',
        ]);
    }

    public function manualOrders_old()
    {
        $orders = Sale::with([
            'customer:id,name,phone', // 👈 only these fields
        ])
            ->orderBy('id', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data'    => $orders,
        ]);
    }

    public function manualOrders(Request $request)
    {
        $query = Sale::with('customer:id,name,phone');

        if ($request->search) {
            $query->where(function ($q) use ($request) {
                $q->where('invoice_number', 'like', "%{$request->search}%")
                    ->orWhere('customer_name', 'like', "%{$request->search}%")
                    ->orWhere('customer_phone', 'like', "%{$request->search}%");
            });
        }

        $orders = $query->latest()->paginate(10);

        return response()->json([
            'success' => true,
            'data'    => $orders,
        ]);
    }

    public function manualOrderDetails($id)
    {
        $order = Sale::with('items')->findOrFail($id);

        return response()->json([
            'success' => true,
            'data'    => $order,
        ]);
    }

    public function sendToCourier($id, ShiprocketOrderService $shiprocket, Request $request)
    {
        $order = Sale::with('items')->findOrFail($id);

        if (! $order->shipping_address_snapshot) {
            return response()->json([
                'success' => false,
                'message' => 'Shipping address missing',
            ], 422);
        }

        // return response()->json([
        //     'success' => true,
        //     'message' => $order,
        // ], 200);

        $shipping = $order->shipping_address_snapshot;

        $items = [];

        foreach ($order->items as $item) {
            $items[] = [
                "name"          => $item->product_name,
                "sku"           => $item->sku ?? 'SKU-' . $item->id,
                "units"         => $item->quantity,
                "selling_price" => $item->price,
            ];
        }

        $payload = [
            "order_id"              => $order->invoice_number,
            "order_date"            => now()->format("Y-m-d H:i"),
            "pickup_location"       => "Home",
            "billing_customer_name" => $shipping['name'],
            "billing_last_name"     => "",
            "billing_address"       => $shipping['address'],
            "billing_city"          => $shipping['city'],
            "billing_pincode"       => $shipping['pincode'],
            "billing_state"         => $shipping['state'],
            "billing_country"       => "India",
            "billing_email"         => "test@example.com",
            "billing_phone"         => $shipping['phone'],
            "shipping_is_billing"   => true,
            "order_items"           => $items,
            "payment_method"        => "Prepaid",
            "sub_total"             => $order->grand_total,
            "length"                => $request->length,
            "breadth"               => $request->breadth,
            "height"                => $request->height,
            "weight"                => $request->weight,
        ];

        try {

            $response = $shiprocket->create($payload);

            // Save Shiprocket order ID
            $order->update([
                'status' => 'shipped',
                //  'shiprocket_order_id' => $response['order_id'] ?? null,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Order sent to Shiprocket successfully',
                'data'    => $response,
            ]);

        } catch (\Exception $e) {

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }

    }

    public function userDetails(Request $request)
    {
        $customers = User::withCount('sales') // total orders
            ->where('role', 'user')
            ->withSum('sales', 'grand_total') // total amount
            ->paginate(10);

        return response()->json([
            'data' => $customers->items(),
            'meta' => [
                'current_page' => $customers->currentPage(),
                'last_page'    => $customers->lastPage(),
                'per_page'     => $customers->perPage(),
            ],
        ]);
    }

    public function customerOrders($id)
    {
        $orders = Sale::where('customer_id', $id)
            ->latest()
            ->get();

        return response()->json([
            'success' => true,
            'data'    => $orders,
        ]);
    }

}
