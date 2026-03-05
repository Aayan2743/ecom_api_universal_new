<?php
namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\OrderItem;
use App\Services\Messenger360Service;
use App\Services\Shiprocket\ShiprocketOrderService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class OrderController extends Controller
{

    public function sendWhatsappTest()
    {
        try {
            $whatsapp = new Messenger360Service();

            $res = $whatsapp->send(
                '8919273834',
                'Hello World!',
                // 'https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=Example',
                // '01-12-2025 09:29' // optional, GMT
            );

            return response()->json([
                'success'  => true,
                'response' => $res,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function store_w(Request $request)
    {
        $validator = Validator::make($request->all(), [
            // 'user_id'                     => 'required|exists:users,id',
            'address_id'                  => 'required|exists:addresses,id',

            'payment.method'              => 'required|string',
            'payment.razorpay_order_id'   => 'nullable|string',
            'payment.razorpay_payment_id' => 'nullable|string',
            'payment.amount'              => 'required|numeric|min:1',

            'price_details.subtotal'      => 'required|numeric|min:1',
            'price_details.discount'      => 'nullable|numeric|min:0',
            'price_details.coupon_code'   => 'nullable|string',
            'price_details.total_amount'  => 'required|numeric|min:1',

            'items'                       => 'required|array|min:1',
            'items.*.product_id'          => 'required|exists:products,id',
            'items.*.quantity'            => 'required|integer|min:1',
            'items.*.price'               => 'required|numeric|min:1',
            'items.*.total'               => 'required|numeric|min:1',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors'  => $validator->errors()->first(),
            ], 422);
        }

        $user_id = auth()->user()->id;

        // ✅ Create Order
        $order = Order::create([
            'user_id'             => $user_id,
            'address_id'          => $request->address_id,
            'payment_method'      => $request->payment['method'],
            'razorpay_order_id'   => $request->payment['razorpay_order_id'] ?? null,
            'razorpay_payment_id' => $request->payment['razorpay_payment_id'] ?? null,
            'subtotal'            => $request->price_details['subtotal'],
            'discount'            => $request->price_details['discount'] ?? 0,
            'coupon_code'         => $request->price_details['coupon_code'],
            'total_amount'        => $request->price_details['total_amount'],
            'status'              => 'paid',
        ]);

        // ✅ Save Order Items
        foreach ($request->items as $item) {
            OrderItem::create([
                'order_id'   => $order->id,
                'product_id' => $item['product_id'],
                'quantity'   => $item['quantity'],
                'price'      => $item['price'],
                'total'      => $item['total'],
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Order created successfully',
            'data'    => $order->load('items'),
        ]);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'address_id'                  => 'required|exists:addresses,id',

            'payment.method'              => 'required|string',
            'payment.razorpay_order_id'   => 'nullable|string',
            'payment.razorpay_payment_id' => 'nullable|string',
            'payment.amount'              => 'required|numeric|min:1',

            'price_details.subtotal'      => 'required|numeric|min:1',
            'price_details.discount'      => 'nullable|numeric|min:0',
            'price_details.coupon_code'   => 'nullable|string',
            'price_details.total_amount'  => 'required|numeric|min:1',

            'items'                       => 'required|array|min:1',
            'items.*.product_id'          => 'required|exists:products,id',
            'items.*.quantity'            => 'required|integer|min:1',
            'items.*.price'               => 'required|numeric|min:1',
            'items.*.total'               => 'required|numeric|min:1',

            // 'send_to_shiprocket'          => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors'  => $validator->errors()->first(),
            ], 422);
        }

        $user = auth()->user();

        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Please login again.',
            ], 401);
        }

        // ✅ Create Order
        $order = Order::create(attributes: [
            'user_id'             => $user->id,
            'address_id'          => $request->address_id,
            'payment_method'      => $request->payment['method'],
            'razorpay_order_id'   => $request->payment['razorpay_order_id'] ?? null,
            'razorpay_payment_id' => $request->payment['razorpay_payment_id'] ?? null,
            'subtotal'            => $request->price_details['subtotal'],
            'discount'            => $request->price_details['discount'] ?? 0,
            'coupon_code'         => $request->price_details['coupon_code'],
            'total_amount'        => $request->price_details['total_amount'],
            'status'              => 'paid',
        ]);

        // ✅ Save Order Items
        foreach ($request->items as $item) {
            OrderItem::create([
                'order_id'   => $order->id,
                'product_id' => $item['product_id'],
                'quantity'   => $item['quantity'],
                'price'      => $item['price'],
                'total'      => $item['total'],
            ]);
        }

        /**
         * ==================================================
         * 🚚 OPTIONAL: SEND TO SHIPROCKET
         * ==================================================
         */
        // if ($request->boolean('send_to_shiprocket')) {

        // Load relations
        $order->load([
            'items.product',
            'user',
            'address',
        ]);

        if (! $order->address) {
            return response()->json([
                'success' => false,
                'message' => 'Billing / Shipping address is required for shipping',
            ], 422);
        }

        // return response()->json([
        //     'ssss' => $order,
        // ]);

        try {
            $shiprocket = new ShiprocketOrderService();

            $payload = [
                "order_id"              => "ORD-" . $order->id,
                "order_date"            => now()->format('Y-m-d'),

                "pickup_location"       => "Home",

                // ===== BILLING DETAILS =====
                "billing_customer_name" => $order->address->name,
                "billing_last_name"     => "",
                "billing_address"       => $order->address->address,
                "billing_city"          => $order->address->city,
                "billing_state"         => $order->address->state,
                "billing_country"       => $order->address->country ?? "India",
                "billing_pincode"       => $order->address->pincode,
                "billing_phone"         => $order->address->phone,
                "billing_email"         => $order->user->email,

                // ===== SHIPPING SAME AS BILLING =====
                "shipping_is_billing"   => true,

                // ===== ITEMS =====
                "order_items"           => collect($order->items)->map(function ($item) {
                    return [
                        "name"          => $item->product->name,
                        "sku"           => $item->product->slug ?? "SKU-" . $item->product_id,
                        "units"         => $item->quantity,
                        "selling_price" => (float) $item->price,
                    ];
                })->toArray(),

                // ===== PAYMENT =====
                "payment_method"        => $order->payment_method === "cod"
                    ? "COD"
                    : "Prepaid",

                // ===== PRICE =====
                "sub_total"             => (float) $order->total_amount,

                // ===== PACKAGE DETAILS (MANDATORY) =====
                "length"                => 10,
                "breadth"               => 10,
                "height"                => 5,
                "weight"                => 0.5,
            ];

            $payload_test = [
                "order_id"              => "ORD-31",
                "order_date"            => "2026-02-10",

                "pickup_location"       => "Home",

                "billing_customer_name" => "Asif",
                "billing_last_name"     => "",
                "billing_address"       => "Nellroe",
                "billing_city"          => "Nellore",
                "billing_state"         => "Andhra Pradesh",
                "billing_country"       => "India",
                "billing_pincode"       => "524004",
                "billing_phone"         => "9440161007",
                "billing_email"         => "ssss@gmail.com",

                "shipping_is_billing"   => true,

                "order_items"           => [
                    [
                        "name"          => "Soft Silk Saree2",
                        "sku"           => "soft-silk-saree2",
                        "units"         => 1,
                        "selling_price" => 6960,
                    ],
                ],

                "payment_method"        => "Prepaid",
                "sub_total"             => 6960,

                "length"                => 10,
                "breadth"               => 10,
                "height"                => 5,
                "weight"                => 0.5,
            ];

            $response = $shiprocket->create($payload);

            // Save Shiprocket info
            $order->update([
                'shiprocket_order_id' => $response['order_id'] ?? null,
                'awb_code'            => $response['awb_code'] ?? null,
                'courier_name'        => $response['courier_name'] ?? null,
                'shipment_status'     => 'created',
            ]);

        } catch (\Exception $e) {
            // ❗ Do not fail order creation
            \Log::error('SHIPROCKET ERROR', ['error' => $e->getMessage()]);
            return response()->json([
                'success'          => true,
                'message'          => 'Order created, but shipment failed',
                'shiprocket_error' => $e->getMessage(),
                'data'             => $order->load('items'),
            ]);
        }

        try {
            $messenger = new Messenger360Service();

            // ================= CUSTOMER =================
            $messenger->send(
                $order->address->phone,
                $this->customerOrderMessage($order)
            );

            // // ================= ADMIN(S) =================
            $adminNumbers = config('services.admin_whatsapp_numbers');

            foreach ($adminNumbers as $adminPhone) {
                $messenger->send(
                    $adminPhone,
                    $this->adminOrderMessage($order)
                );
            }

        } catch (\Exception $e) {
            \Log::error('WHATSAPP ERROR', [
                'order_id' => $order->id,
                'error'    => $e->getMessage(),
            ]);
        }

        // }

        return response()->json([
            'success' => true,
            'message' => 'Order created successfully',
            'data'    => $order->load('items'),
        ]);
    }

    private function customerOrderMessage($order): string
    {
        $a = $order->address;

        return
        "🛒 *Order Confirmed!*\n\n" .
        "🆔 Order ID: ORD-{$order->id}\n" .
        "💰 Amount: ₹{$order->total_amount}\n" .
        "💳 Payment: {$order->payment_method}\n\n" .
        "📍 Address:\n" .
        "{$a->name}\n{$a->address}\n{$a->city}, {$a->state} - {$a->pincode}\n\n" .
        "🚚 Status: " . strtoupper($order->shipment_status ?? 'PENDING') . "\n" .
            ($order->awb_code ? "📦 AWB: {$order->awb_code}\n" : "") .
            "\n🙏 Thank you for shopping with us!";
    }

    private function adminOrderMessage($order): string
    {
        return
        "📦 *New Order Received!*\n\n" .
        "🆔 Order: ORD-{$order->id}\n" .
        "👤 Customer: {$order->user->name}\n" .
        "📞 Phone: {$order->address->phone}\n" .
        "💰 Amount: ₹{$order->total_amount}\n" .
        "💳 Payment: {$order->payment_method}\n\n" .
        "🚚 Shipment: " . strtoupper($order->shipment_status ?? 'PENDING');
    }

/* ================= GET USER ORDERS ================= */
    public function index(Request $request)
    {
        $orders = Order::with([
            'items.product.images', // 👈 include product images
        ])
            ->where('user_id', auth()->id())
            ->latest()
            ->get();

        // Optional: add full image_url
        $orders->each(function ($order) {
            $order->items->each(function ($item) {
                if ($item->product && $item->product->images) {
                    $item->product->images->each(function ($img) {
                        $img->image_url = asset('storage/' . $img->image_path);
                    });
                }
            });
        });

        return response()->json([
            'success' => true,
            'data'    => $orders,
        ]);
    }

/* ================= SHOW ORDER ================= */

    public function show($id)
    {
        $order = Order::with([
            'items.product.images', // 👈 THIS is the key line
        ])->findOrFail($id);

        // (optional) append full image URL
        $order->items->each(function ($item) {
            if ($item->product && $item->product->images) {
                $item->product->images->each(function ($img) {
                    $img->image_url = asset('storage/' . $img->image_path);
                });
            }
        });

        return response()->json([
            'success' => true,
            'data'    => $order,
        ]);
    }

    public function getMyOrderDetails(Request $request, $id)
    {
        $user = $request->user();

        $order = Order::where('id', $id)
            ->where('user_id', $user->id)
            ->with([
                'items.product:id,name',
            ])
            ->first();

        if (! $order) {
            return response()->json([
                'success' => false,
                'message' => 'Order not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data'    => [
                'id'              => $order->id,
                'order_status'    => $order->order_status,
                'subtotal'        => $order->subtotal,
                'discount_amount' => $order->discount ?? 0,
                'total_amount'    => $order->total_amount,
                'createdAt'       => $order->created_at,

                'items'           => $order->items->map(function ($item) {
                    return [
                        'id'       => $item->id,
                        'quantity' => $item->quantity,
                        'price'    => $item->price,

                        'product'  => [
                            'name' => $item->product?->name,
                        ],
                    ];
                }),
            ],
        ]);
    }

/* ================= DELETE ORDER ================= */
    public function destroy($id)
    {
        Order::findOrFail($id)->delete();

        return response()->json([
            'success' => true,
            'message' => 'Order deleted',
        ]);
    }

    public function allorders_w(Request $request)
    {
        $search  = $request->search;
        $perPage = $request->perPage ?? 10;

        $orders = Order::with([
            'items.product.images',
            'user:id,name,phone,email',
        ])
            ->when($search, function ($q) use ($search) {
                $q->where('id', 'like', "%{$search}%")
                    ->orWhereHas('items.product', function ($p) use ($search) {
                        $p->where('name', 'like', "%{$search}%");
                    })
                    ->orWhereHas('user', function ($u) use ($search) {
                        $u->where('name', 'like', "%{$search}%")
                            ->orWhere('phone', 'like', "%{$search}%");
                    });
            })
            ->latest()
            ->paginate($perPage);

        return response()->json([
            'success'    => true,
            'data'       => $orders->items(),
            'pagination' => [
                'total'       => $orders->total(),
                'currentPage' => $orders->currentPage(),
                'totalPages'  => $orders->lastPage(),
                'perPage'     => $orders->perPage(),
            ],
        ]);
    }

    public function allorders(Request $request)
    {
        $search  = $request->search;
        $perPage = $request->perPage ?? 10;

        $orders = Order::with([
            'items.product.images',
            'user:id,name,phone,email',
            'address:id,name,phone,address,city,state,pincode',
        ])
            ->when($search, function ($q) use ($search) {
                $q->where('id', 'like', "%{$search}%")
                    ->orWhereHas('items.product', function ($p) use ($search) {
                        $p->where('name', 'like', "%{$search}%");
                    })
                    ->orWhereHas('user', function ($u) use ($search) {
                        $u->where('name', 'like', "%{$search}%")
                            ->orWhere('phone', 'like', "%{$search}%");
                    })
                    ->orWhereHas('address', function ($a) use ($search) {
                        $a->where('address', 'like', "%{$search}%")
                            ->orWhere('city', 'like', "%{$search}%")
                            ->orWhere('pincode', 'like', "%{$search}%");
                    });
            })
            ->latest()
            ->paginate($perPage);

        return response()->json([
            'success'    => true,
            'data'       => $orders->items(),
            'pagination' => [
                'total'       => $orders->total(),
                'currentPage' => $orders->currentPage(),
                'totalPages'  => $orders->lastPage(),
                'perPage'     => $orders->perPage(),
            ],
        ]);
    }

    public function allorders_show($id)
    {
        $order = Order::with([
            'items.product.images',
            'user:id,name,phone,email',
        ])
            ->find($id);

        if (! $order) {
            return response()->json([
                'success' => false,
                'message' => 'Order not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data'    => $order,
        ]);
    }

    public function updateStatusRequest($request, $id)
    {
        $request->validate([
            'status'      => 'required|string',
            'tracking_id' => 'nullable|string',
        ]);

        $order = Order::findOrFail($id);

        if ($request->status === 'shipping' && ! $request->tracking_id) {
            return response()->json([
                'message' => 'Tracking ID required',
            ], 422);
        }

        $order->status      = $request->status;
        $order->tracking_id = $request->tracking_id;
        $order->save();

        return response()->json([
            'success' => true,
            'message' => 'Order status updated',
        ]);
    }

}