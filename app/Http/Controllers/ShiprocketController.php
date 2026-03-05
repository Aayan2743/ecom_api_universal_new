<?php
namespace App\Http\Controllers;

use App\Models\Order;
use App\Services\Shiprocket\ShiprocketOrderService;

class ShiprocketController extends Controller
{
    public function createShipment($orderId)
    {
        $order = Order::with('items.product')->findOrFail($orderId);

        $payload = [
            "order_id"              => $order->id,
            "order_date"            => now()->toDateString(),

            "pickup_location"       => "Primary",

            "billing_customer_name" => $order->address->name,
            "billing_phone"         => $order->address->phone,
            "billing_address"       => $order->address->address,
            "billing_city"          => $order->address->city,
            "billing_state"         => $order->address->state,
            "billing_pincode"       => $order->address->pincode,
            "billing_country"       => "India",
            "billing_email"         => $order->user->email,

            "shipping_is_billing"   => true,

            "order_items"           => $order->items->map(function ($item) {
                return [
                    "name"          => $item->product->name,
                    "sku"           => $item->product->sku ?? 'SKU-' . $item->id,
                    "units"         => $item->quantity,
                    "selling_price" => $item->price,
                ];
            })->toArray(),

            "payment_method"        => $order->payment_method === 'cod'
                ? "COD"
                : "Prepaid",

            "sub_total"             => $order->total,

            "length"                => 10,
            "breadth"               => 10,
            "height"                => 10,
            "weight"                => 0.5,
        ];

        $shiprocket = new ShiprocketOrderService();
        $response   = $shiprocket->create($payload);

        $order->update([
            'shiprocket_order_id' => $response['order_id'],
            'awb_code'            => $response['awb_code'] ?? null,
            'courier_name'        => $response['courier_name'] ?? null,
            'shipment_status'     => 'created',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Shipment created successfully',
            'data'    => $response,
        ]);
    }
}
