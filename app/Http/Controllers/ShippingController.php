<?php
namespace App\Http\Controllers;

use App\Helpers\EnvHelper;
use App\Models\Order;
use App\Models\Sale;
use App\Services\Shipmozo\ShipmozoClient;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Validator;
use App\Services\Messenger360Service;
use Illuminate\Support\Facades\Log;

class ShippingController extends Controller
{

    protected $whatsapp;

    public function __construct(Messenger360Service $whatsapp)
    {
        $this->whatsapp = $whatsapp;
    }



    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [

            /* SHIPROCKET */
            'shiprocket.enabled'  => 'required|boolean',
            'shiprocket.base_url' => 'nullable|url',
            'shiprocket.email'    => 'nullable|email',
            'shiprocket.password' => 'nullable|string',

            /* SHIPMOZO */
            'shipmozo.enabled'    => 'required|boolean',
            'shipmozo.base_url'   => 'nullable|url',
            'shipmozo.api_key'    => 'nullable|string',
            'shipmozo.secret'     => 'nullable|string',

            /* XPRESSBEES */
            'xpressbees.enabled'  => 'required|boolean',
            'xpressbees.base_url' => 'nullable|url',
            'xpressbees.api_key'  => 'nullable|string',

            /* DTDC */
            'dtdc.enabled'        => 'required|boolean',
            'dtdc.base_url'       => 'nullable|url',
            'dtdc.api_key'        => 'nullable|string',

            /* DELHIVERY */
            'delhivery.enabled'   => 'required|boolean',
            'delhivery.base_url'  => 'nullable|url',
            'delhivery.api_key'   => 'nullable|string',

            /* EKART */
            'ekart.enabled'       => 'required|boolean',
            'ekart.base_url'      => 'nullable|url',
            'ekart.api_key'       => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors'  => $validator->errors()->first(),
            ], 422);
        }

        $data = $request->all();

        /* ================= SHIPROCKET ================= */
        EnvHelper::setEnvValue('SHIPROCKET_ENABLED', $request->boolean('shiprocket.enabled'));
        EnvHelper::setEnvValue('SHIPROCKET_BASE_URL', $data['shiprocket']['base_url'] ?? '');
        EnvHelper::setEnvValue('SHIPROCKET_EMAIL', $data['shiprocket']['email'] ?? '');
        EnvHelper::setEnvValue('SHIPROCKET_PASSWORD', $data['shiprocket']['password'] ?? '');

        /* ================= SHIPMOZO ================= */
        EnvHelper::setEnvValue('SHIPMOZO_ENABLED', $request->boolean('shipmozo.enabled'));
        EnvHelper::setEnvValue('SHIPMOZO_BASE_URL', $data['shipmozo']['base_url'] ?? '');
        EnvHelper::setEnvValue('SHIPMOZO_API_KEY', $data['shipmozo']['api_key'] ?? '');
        EnvHelper::setEnvValue('SHIPMOZO_SECRET', $data['shipmozo']['secret'] ?? '');

        /* ================= XPRESSBEES ================= */
        EnvHelper::setEnvValue('XPRESSBEES_ENABLED', $request->boolean('xpressbees.enabled'));
        EnvHelper::setEnvValue('XPRESSBEES_BASE_URL', $data['xpressbees']['base_url'] ?? '');
        EnvHelper::setEnvValue('XPRESSBEES_API_KEY', $data['xpressbees']['api_key'] ?? '');

        /* ================= DTDC ================= */
        EnvHelper::setEnvValue('DTDC_ENABLED', $request->boolean('dtdc.enabled'));
        EnvHelper::setEnvValue('DTDC_BASE_URL', $data['dtdc']['base_url'] ?? '');
        EnvHelper::setEnvValue('DTDC_API_KEY', $data['dtdc']['api_key'] ?? '');

        /* ================= DELHIVERY ================= */
        EnvHelper::setEnvValue('DELHIVERY_ENABLED', $request->boolean('delhivery.enabled'));
        EnvHelper::setEnvValue('DELHIVERY_BASE_URL', $data['delhivery']['base_url'] ?? '');
        EnvHelper::setEnvValue('DELHIVERY_API_KEY', $data['delhivery']['api_key'] ?? '');

        /* ================= EKART ================= */
        EnvHelper::setEnvValue('EKART_ENABLED', $request->boolean('ekart.enabled'));
        EnvHelper::setEnvValue('EKART_BASE_URL', $data['ekart']['base_url'] ?? '');
        EnvHelper::setEnvValue('EKART_API_KEY', $data['ekart']['api_key'] ?? '');

        Artisan::call('config:clear');

        return response()->json([
            'success' => true,
            'message' => 'Shipping settings updated successfully',
        ]);
    }

    public function index()
    {
        return response()->json([
            'success' => true,
            'data'    => [
                'shiprocket' => [
                    'enabled'  => config('services.shipping.shiprocket.enabled', false),
                    'base_url' => config('services.shipping.shiprocket.base_url', ''),
                    'email'    => config('services.shipping.shiprocket.email', ''),
                    'password' => config('services.shipping.shiprocket.password', ''),
                ],

                'shipmozo'   => [
                    'enabled'  => config('services.shipping.shipmozo.enabled', false),
                    'base_url' => config('services.shipping.shipmozo.base_url', ''),
                    'api_key'  => config('services.shipping.shipmozo.api_key', ''),
                    'secret'   => config('services.shipping.shipmozo.secret', ''),
                ],

                'xpressbees' => [
                    'enabled'  => config('services.shipping.xpressbees.enabled', false),
                    'base_url' => config('services.shipping.xpressbees.base_url', ''),
                    'api_key'  => config('services.shipping.xpressbees.api_key', ''),
                ],

                'dtdc'       => [
                    'enabled'  => config('services.shipping.dtdc.enabled', false),
                    'base_url' => config('services.shipping.dtdc.base_url', ''),
                    'api_key'  => config('services.shipping.dtdc.api_key', ''),
                ],

                'delhivery'  => [
                    'enabled'  => config('services.shipping.delhivery.enabled', false),
                    'base_url' => config('services.shipping.delhivery.base_url', ''),
                    'api_key'  => config('services.shipping.delhivery.api_key', ''),
                ],

                'ekart'      => [
                    'enabled'  => config('services.shipping.ekart.enabled', false),
                    'base_url' => config('services.shipping.ekart.base_url', ''),
                    'api_key'  => config('services.shipping.ekart.api_key', ''),
                ],
            ],
        ]);
    }

    /* ================= ENABLED COURIERS ================= */
    public function enabledCouriers()
    {
        $shipping = config('services.shipping');

        $enabled = [];

        foreach ($shipping as $key => $value) {
            if (! empty($value['enabled'])) {
                $enabled[] = [
                    'code' => $key,
                    'name' => ucfirst($key),
                ];
            }
        }

        return response()->json([
            'success' => true,
            'data'    => $enabled,
        ]);
    }

    /* ================= SEND COURIER ================= */


    public function sendCourier(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'courier' => 'required|string',
            'length'  => 'required|numeric',
            'breadth' => 'required|numeric',
            'height'  => 'required|numeric',
            'weight'  => 'required|numeric', // dead weight from form
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors'  => $validator->errors()->first(),
            ], 422);
        }

        $order = Sale::findOrFail($id);

        switch ($request->courier) {

            case 'shiprocket':
                // Shiprocket logic here
                break;

            case 'shipmozo':

                $shipmozo = new ShipmozoClient();

                $address = is_array($order->shipping_address_snapshot)
                    ? $order->shipping_address_snapshot
                    : json_decode($order->shipping_address_snapshot, true);

                if (! $address) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Shipping address missing',
                    ]);
                }

                /* ================= WEIGHT CALCULATIONS ================= */

                $deadWeight = (float) ($request->weight*1000);

                $length  = (float) $request->length;
                $breadth = (float) $request->breadth;
                $height  = (float) $request->height;

                // volumetric weight
                $volumetricWeight = round(($length * $breadth * $height) / 5000, 2);

                /* ================= PRODUCT DETAILS ================= */

                $productDetails = [];

                foreach ($order->items as $item) {
                    $productDetails[] = [
                        "name"             => $item->product_name,
                        "sku_number"       => $item->sku ?? (string) $item->product_id,
                        "quantity"         => (int) $item->quantity,
                        "discount"         => (string) $item->discount,
                        "hsn"              => "",
                        "unit_price"       => (float) $item->price,
                        "product_category" => "Other",
                    ];
                }

                /* ================= PAYLOAD ================= */

                $payload = [

                    "order_id"                   => (string) $order->invoice_number,
                    "order_date"                 => now()->format('Y-m-d'),
                    "order_type"                 => "ESSENTIALS",

                    "consignee_name"             => $address['name'],
                    "consignee_phone"            => (string) $address['phone'],
                    "consignee_alternate_phone"  => "",
                    "consignee_email"            => "",
                    "consignee_address_line_one" => $address['address'],
                    "consignee_address_line_two" => "",
                    "consignee_pin_code"         => (string) $address['pincode'],
                    "consignee_city"             => $address['city'],
                    "consignee_state"            => $address['state'],

                    "product_detail"             => $productDetails,

                    "payment_type"               => $order->payment_method === 'cod'
                        ? "COD"
                        : "PREPAID",

                    "cod_amount"                 => $order->payment_method === 'cod'
                        ? (float) $order->grand_total
                        : "",

                    "shipping_charges"           => "",

                    // weights
                    "weight"                     => $deadWeight,
                    // "volumetric_weight"          => $volumetricWeight,

                    "length"                     => $length,
                    "width"                      => $breadth,
                    "height"                     => $height,

                    "warehouse_id"               => "72958",
                    "gst_ewaybill_number"        => "",
                    "gstin_number"               => "",
                ];

                $response = $shipmozo->createOrder($payload);

                \Log::info('Shipmozo Response Full', $response);

                if ($response['result'] !== "1") {
                    return response()->json([
                        'success' => false,
                        'message' => $response['message'] ?? 'Shipmozo failed',
                    ]);
                }

                /* ================= SAVE INTO SALES TABLE ================= */

                $order->tracking_number   = $response['data']['order_id'];
                $order->shipping_partner  = 'shipmozo';
                $order->status            = 'shipped';
                $order->dead_weight       = ($deadWeight/1000);
                $order->volumetric_weight = $volumetricWeight;

                $order->save();

                break;

            case 'delhivery':
                // Delhivery logic
                break;

            default:
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid courier selected',
                ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Courier order created successfully',
        ]);
    }

        /* ================= GET RATE CARDS SHIPMOZO COURIER ================= */



public function rateCard(Request $request, ShipmozoClient $shipmozo)
{

    $order = Sale::findOrFail($request->order_id);
                // dd($order->shipping_address_snapshot);
    // decode shipping address
    // $address = json_decode($order->shipping_address_snapshot, true);
    $address = $order->shipping_address_snapshot;



    // dd($order->dead_weight, $order->volumetric_weight);


    $deadWeight = $order->dead_weight ?? 0.5;
    $volWeight  = $order->volumetric_weight ?? 0.5;

    // chargeable weight
    $weight = max($deadWeight, $volWeight) * 1000;
    //  dd($weight);


    $payload = [
        "order_id" => "",

        "pickup_pincode" => env('SHIPMOZO_PICKUP_PINCODE', '524004'),
        "delivery_pincode" => $address['pincode'] ?? "",

        "payment_type" => "PREPAID",
        "shipment_type" => "FORWARD",

        "order_amount" => $order->grand_total,

        "type_of_package" => "SPS",
        "rov_type" => "ROV_OWNER",

        "cod_amount" => "",

        "weight" => $weight,

        "dimensions" => [
            [
                "no_of_box" => 1,
                "length" => 10,
                "width" => 10,
                "height" => 10
            ]
        ]
    ];

    //  dd($payload);

    $rates = $shipmozo->rateCalculator($payload);

    if (($rates['result'] ?? '0') === '0') {
    return response()->json([
        'success' => false,
        'message' => $rates['message'] ?? 'Failed to fetch courier rates',
        'data' => []
    ]);
}


    return response()->json([
        'success' => true,
        'data' => $rates
    ]);
}


public function assignCourier(Request $request, ShipmozoClient $shipmozo)
{



    $validator = Validator::make($request->all(), [
        'order_id'   => 'required',
        'courier_id' => 'required'
    ]);

    if ($validator->fails()) {
        return response()->json([
            'success' => false,
            'errors'  => $validator->errors()->first(),
        ], 422);
    }

    $order = Sale::where('tracking_number', $request->order_id)->first();

    if(!$order){
        return response()->json([
            'success' => false,
            'message' => 'Order not found'
        ]);
    }

    try {


        $payload = [
            "order_id"   => $request->order_id,
            "courier_id" => $request->courier_id
        ];

        $response = $shipmozo->assignCourier($payload);

         if(($response['result'] ?? '0') == "1"){

            $data = $response['data'];

            // $order->shipping_partner = $data['courier'] ?? null;
            $order->awb_no           = $data['awb_number'] ?? null;
            $order->courier_number   = $data['courier'] ?? null;
            $order->status           = "ASSIGNED COURIER";

            $order->save();



            if(($response['result'] ?? '0') == "1"){

    $data = $response['data'];

    $order->shipping_partner = $data['courier'] ?? null;
    $order->awb_no = $data['awb_number'] ?? null;
    $order->courier_number = $data['order_id'] ?? null;
    $order->status = "shipped";

    $order->save();

    // Send WhatsApp Alert
    $phone = $order->customer_phone;

  $message = "📦 Your order has been shipped!\n\n"
    ."Courier: ".$order->shipping_partner."\n"
    ."AWB Number: ".$order->awb_no."\n\n"
    ."Thank you for shopping with us.";

    app(\App\Services\Messenger360Service::class)->send($phone, $message);

    return response()->json([
        "success" => true,
        "message" => "Courier assigned successfully",
        "data" => $data
    ]);
}

            return response()->json([
                "success" => true,
                "message" => "Courier assigned successfully",
                "data" => $data
            ]);
        }

        return response()->json([
            "success" => false,
            "message" => $response['message'] ?? "Failed to assign courier"
        ]);









    } catch (\Exception $e){

        return response()->json([
            "success" => false,
            "message" => $e->getMessage()
        ]);
    }
}



public function resetCourier($id)
{
    $order = Sale::find($id);

    if (!$order) {
        return response()->json([
            'success' => false,
            'message' => 'Order not found'
        ]);
    }

    $order->tracking_number = null;
    $order->shipping_partner = null;
    $order->awb_no = null;
    $order->courier_number = null;
    $order->status = 'created';

    $order->save();

    return response()->json([
        'success' => true,
        'message' => 'Courier removed successfully'
    ]);
}


public function cancelCourier(Request $request, ShipmozoClient $shipmozo, $id)
{
    try {

        $order = Sale::findOrFail($id);

        // Validate
        if (!$order->tracking_number) {
            return response()->json([
                'success' => false,
                'message' => 'Tracking number missing'
            ]);
        }

        // Call Shipmozo API
        $response = $shipmozo->cancelOrder([
            "order_id"   => (string) $order->invoice_number,
            "awb_number" => $order->awb_no,
        ]);

        // Success check
        if (($response['result'] ?? "0") == "1") {

            // $order->status = 'cancelled';
            // $order->save();



            $order->tracking_number = null;
            $order->shipping_partner = null;
            $order->awb_no = null;
            $order->courier_number = null;
            $order->status = 'created';

            $order->save();

            return response()->json([
                'success' => true,
                'message' => 'Order cancelled successfully'
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => $response['message'] ?? 'Cancel failed'
        ]);

    } catch (\Exception $e) {

        return response()->json([
            'success' => false,
            'message' => $e->getMessage()
        ]);

    }
}



public function handle(Request $request)
{
    Log::info('Webhook:', $request->all());

    $awb     = $request->awb_number;
    $status  = $request->current_status;

    $sale = Sale::where('awb_no', $awb)->first();

    if ($sale) {

        $mappedStatus = $this->mapStatus($status);

        // 🚨 Prevent duplicate update + message
        if ($sale->status === $mappedStatus) {
            return response()->json(['success' => true]);
        }

        $sale->update([
            'status' => $mappedStatus,
        ]);

        // 🔥 SEND WHATSAPP
        $this->sendWhatsAppStatus($sale, $status);
    }

    return response()->json(['success' => true]);
}

private function mapStatus($status)
{
    return match ($status) {
        'Delivered' => 'completed',
        'In Transit' => 'in_transit',
        'Out For Delivery' => 'out_for_delivery',
        'Pickup Completed' => 'picked',
        'Cancelled' => 'cancelled',
        'Undelivered' => 'undelivered',
        'RTO Delivered' => 'rto_delivered',
        default => 'pending',
    };
}





private function sendWhatsAppStatus($sale, $status)
{
    $customerPhone = $sale->customer_phone;
    $adminPhone    = '8919273834'; // ✅ hardcoded admin number

    $message = match ($status) {

        'Pickup Completed' =>
            "📦 Order #{$sale->invoice_number} shipped",

        'In Transit' =>
            "🚚 Order #{$sale->invoice_number} is in transit",

        'Out For Delivery' =>
            "🚚 Order #{$sale->invoice_number} out for delivery",

        'Delivered' =>
            "✅ Order #{$sale->invoice_number} delivered",

        default => null,
    };

    if (!$message) return;

    // ✅ CUSTOMER
    if ($customerPhone) {
        $this->whatsapp->send($customerPhone, $message);
    }

    // ✅ ADMIN (TEST NUMBER)
    $adminMessage = "📢 TEST ADMIN ALERT\n\n"
        . "Order: #{$sale->invoice_number}\n"
        . "Status: {$status}\n"
        . "Customer: {$sale->customer_name}\n"
        . "Phone: {$sale->customer_phone}";

    $this->whatsapp->send($adminPhone, $adminMessage);
}
}
