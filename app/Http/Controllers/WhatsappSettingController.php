<?php
namespace App\Http\Controllers;

use App\Helpers\EnvHelper;
use App\Models\WhatsappSetting;
use Illuminate\Http\Request;
use Validator;

class WhatsappSettingController extends Controller
{
    public function show()
    {
        $data = WhatsappSetting::first();

        return response()->json([
            'success' => true,
            'data'    => $data,
        ]);
    }

    /**
     * SAVE (create or update)
     */
    public function store_live(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'api_key' => 'required|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors'  => $validator->errors()->first(),
            ], 422);
        }

        $setting = WhatsappSetting::updateOrCreate(
            ['id' => 1], // 👈 single-row enforced
            ['api_key' => $request->api_key]
        );

        return response()->json([
            'success' => true,
            'message' => 'WhatsApp settings saved successfully',
            'data'    => $setting,
        ]);
    }

    // Local Store env
    public function store(Request $request)
    {

        $validator = Validator::make($request->all(), [
            'enabled'  => 'required|boolean',
            'api_key'  => 'required|string',
            'base_url' => 'required|url',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors'  => $validator->errors()->first(),
            ], 422);
        }

        EnvHelper::setEnvValue('WHATSAPP_ENABLED', $request->enabled);
        EnvHelper::setEnvValue('WHATSAPP_API_KEY', $request->api_key);
        EnvHelper::setEnvValue('WHATSAPP_BASE_URL', $request->base_url);

        // Artisan::call('config:clear');
        // Artisan::call('cache:clear');

        return response()->json([
            'success' => true,
            'message' => 'WhatsApp settings updated successfully',
        ]);
    }

    public function index()
    {
        return response()->json([
            'success' => true,
            'data'    => [
                'enabled'  => config('services.whatsapp.enabled'),
                'api_key'  => config('services.whatsapp.api_key'),
                'base_url' => config('services.whatsapp.base_url'),
            ],
        ]);
    }
}
