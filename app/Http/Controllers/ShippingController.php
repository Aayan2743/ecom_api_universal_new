<?php
namespace App\Http\Controllers;

use App\Helpers\EnvHelper;
use App\Models\Sale;
use App\Services\Shipmozo\ShipmozoClient;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Validator;

class ShippingController extends Controller
{
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

        // dd($request->all());
        $validator = Validator::make($request->all(), [

            'courier' => 'required|string',
            'length'  => 'required|numeric',
            'breadth' => 'required|numeric',
            'height'  => 'required|numeric',
            'weight'  => 'required|numeric',
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
                // call Shiprocket service
                break;

            case 'shipmozo':
                // call Shipmozo service
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
                    "weight"                     => (float) $request->weight,
                    "length"                     => (float) $request->length,
                    "width"                      => (float) $request->breadth,
                    "height"                     => (float) $request->height,
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

                $order->tracking_number  = $response['data']['order_id']; // Shipmozo tracking id
                $order->shipping_partner = 'shipmozo';
                $order->status           = 'shipped';

                $order->save();

                break;

            case 'delhivery':
                // call Delhivery service
                break;

            default:
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid courier selected',
                ]);
        }

        $order->status = 'shipped';
        $order->save();

        return response()->json([
            'success' => true,
            'message' => 'Courier order created successfully',
        ]);
    }
}
