<?php
namespace App\Http\Controllers;

use App\Helpers\EnvHelper;
use App\Models\PaymentGateway;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Validator;

class PaymentGatewayController extends Controller
{
    public function show()
    {
        $data = PaymentGateway::first();

        return response()->json([
            'success' => true,
            'data'    => $data,
        ]);
    }

    /**
     * CREATE or UPDATE (single row)
     */
    public function storedd(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'razorpay_key'        => 'nullable|string',
            'razorpay_secret'     => 'nullable|string',
            'razorpay_enabled'    => 'nullable|boolean',

            'cashfree_app_id'     => 'nullable|string',
            'cashfree_secret'     => 'nullable|string',
            'cashfree_enabled'    => 'nullable|boolean',

            'phonepe_merchant_id' => 'nullable|string',
            'phonepe_secret'      => 'nullable|string',
            'phonepe_enabled'     => 'nullable|boolean',

            'cod_enabled'         => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors'  => $validator->errors()->first(),
            ], 422);
        }

        $now = Carbon::now();

        $gateway = PaymentGateway::updateOrCreate(
            ['id' => 1], // 👈 enforce SINGLE ROW
            array_merge(
                $validator->validated(),
                ['created_at' => $now, 'updated_at' => $now]
            )
        );

        /* ================= RAZORPAY ================= */

        EnvHelper::setEnvValue('RAZORPAY_KEY', $request->razorpay_key);

        if ($request->filled('razorpay_secret')) {
            EnvHelper::setEnvValue('RAZORPAY_SECRET', $request->razorpay_secret);
        }

        EnvHelper::setEnvValue(
            'RAZORPAY_ENABLED',
            $request->razorpay_enabled ? 'true' : 'false'
        );

        /* ================= CASHFREE ================= */

        EnvHelper::setEnvValue('CASHFREE_APP_ID', $request->cashfree_app_id);

        if ($request->filled('cashfree_secret')) {
            EnvHelper::setEnvValue('CASHFREE_SECRET', $request->cashfree_secret);
        }

        EnvHelper::setEnvValue(
            'CASHFREE_ENABLED',
            $request->cashfree_enabled ? 'true' : 'false'
        );

        /* ================= PHONEPE ================= */

        EnvHelper::setEnvValue('PHONEPE_MERCHANT_ID', $request->phonepe_merchant_id);
        EnvHelper::setEnvValue('PHONEPE_CLIENT_ID', $request->phonepe_client_id);

        if ($request->filled('phonepe_client_secret')) {
            EnvHelper::setEnvValue(
                'PHONEPE_CLIENT_SECRET',
                $request->phonepe_client_secret
            );
        }

        EnvHelper::setEnvValue(
            'PHONEPE_CLIENT_VERSION',
            $request->phonepe_client_version
        );

        EnvHelper::setEnvValue(
            'PHONEPE_BASE_URL',
            $request->phonepe_base_url
        );

        EnvHelper::setEnvValue(
            'PHONEPE_ENABLED',
            $request->phonepe_enabled ? 'true' : 'false'
        );

        /* ================= PAYU ================= */

        EnvHelper::setEnvValue('PAYU_KEY', $request->payu_key);

        if ($request->filled('payu_salt')) {
            EnvHelper::setEnvValue('PAYU_SALT', $request->payu_salt);
        }

        EnvHelper::setEnvValue(
            'PAYU_ENABLED',
            $request->payu_enabled ? 'true' : 'false'
        );

        /* ================= COD ================= */

        EnvHelper::setEnvValue(
            'COD_ENABLED',
            $request->cod_enabled ? 'true' : 'false'
        );

        /* ================= CLEAR CACHE ================= */

        Artisan::call('config:clear');
        Artisan::call('cache:clear');
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [

            // Razorpay
            'razorpay_key'           => 'nullable|string',
            'razorpay_secret'        => 'nullable|string',
            'razorpay_enabled'       => 'nullable|boolean',

            // Cashfree
            'cashfree_app_id'        => 'nullable|string',
            'cashfree_secret'        => 'nullable|string',
            'cashfree_enabled'       => 'nullable|boolean',

            // PhonePe
            'phonepe_merchant_id'    => 'nullable|string',
            'phonepe_client_id'      => 'nullable|string',
            'phonepe_client_secret'  => 'nullable|string',
            'phonepe_client_version' => 'nullable|string',
            'phonepe_base_url'       => 'nullable|string',
            'phonepe_enabled'        => 'nullable|boolean',

            // PayU
            'payu_key'               => 'nullable|string',
            'payu_salt'              => 'nullable|string',
            'payu_enabled'           => 'nullable|boolean',

            // COD
            'cod_enabled'            => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors'  => $validator->errors()->first(),
            ], 422);
        }

        // $data = $validator->validated();

        // PaymentGateway::updateOrCreate(
        //     ['id' => 1],
        //     $data
        // );

        /* ================= SAVE TO ENV ================= */

        $data = $request->all();

        foreach ($data as $key => $value) {

            $envKey = strtoupper($key);

            if (str_contains($envKey, 'ENABLED')) {
                EnvHelper::setEnvValue(
                    $envKey,
                    filter_var($value, FILTER_VALIDATE_BOOLEAN)
                );
            } else {
                EnvHelper::setEnvValue($envKey, $value ?? '');
            }
        }

        // foreach ($data as $key => $value) {
        //     $envKey = strtoupper($key);

        //     if (str_contains($envKey, 'ENABLED')) {
        //         EnvHelper::setEnvValue($envKey, $value ? 'true' : 'false');
        //     } else {
        //         EnvHelper::setEnvValue($envKey, $value);
        //     }
        // }

        Artisan::call('config:clear');
        Artisan::call('cache:clear');

        return response()->json([
            'success' => true,
            'message' => 'Payment gateway updated successfully',
        ]);
    }

    /**
     * DELETE settings (optional)
     */
    public function destroy()
    {
        PaymentGateway::where('id', 1)->delete();

        return response()->json([
            'success' => true,
            'message' => 'Payment gateway settings deleted',
        ]);
    }

    public function index1()
    {
        $data = [];

        // Razorpay
        if (config('payment.razorpay.enabled')) {
            $data['razorpay'] = [
                'key' => config('payment.razorpay.key'),
            ];
        }

        // Cashfree
        if (config('payment.cashfree.enabled')) {
            $data['cashfree'] = [
                'app_id' => config('payment.cashfree.app_id'),
            ];
        }

        // PhonePe
        if (config('payment.phonepe.enabled')) {
            $data['phonepe'] = [
                'merchant_id' => config('payment.phonepe.merchant_id'),
            ];
        }

        // COD
        if (config('payment.cod.enabled')) {
            $data['cod'] = true;
        }

        return response()->json([
            'success' => true,
            'data'    => $data,
        ]);
    }

    public function index()
    {
        return response()->json([
            'success' => true,
            'data'    => [

                /* ================= RAZORPAY ================= */

                'razorpay_key'           => config('payment.razorpay.key'),
                'razorpay_secret'        => config('payment.razorpay.secret'),
                'razorpay_enabled'       => config('payment.razorpay.enabled'),

                /* ================= CASHFREE ================= */

                'cashfree_app_id'        => config('payment.cashfree.app_id'),
                'cashfree_secret'        => config('payment.cashfree.secret'),
                'cashfree_enabled'       => config('payment.cashfree.enabled'),

                /* ================= PHONEPE ================= */

                'phonepe_merchant_id'    => config('payment.phonepe.merchant_id'),
                'phonepe_client_id'      => config('payment.phonepe.client_id'),
                'phonepe_client_secret'  => config('payment.phonepe.client_secret'),
                'phonepe_client_version' => config('payment.phonepe.client_version'),
                'phonepe_base_url'       => config('payment.phonepe.base_url'),
                'phonepe_enabled'        => config('payment.phonepe.enabled'),

                /* ================= PAYU ================= */

                'payu_key'               => config('payment.payu.key'),
                'payu_salt'              => config('payment.payu.salt'),
                'payu_enabled'           => config('payment.payu.enabled'),

                /* ================= COD ================= */

                'cod_enabled'            => config('payment.cod.enabled'),
            ],
        ]);
    }
}
