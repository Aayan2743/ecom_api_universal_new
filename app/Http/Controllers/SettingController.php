<?php
namespace App\Http\Controllers;

use App\Models\app_setting;
use App\Models\SocialMediaSetting;
use App\Services\WebpService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class SettingController extends Controller
{
    public function show()
    {
        return response()->json([
            'success' => true,
            'data'    => app_setting::one(),
        ]);
    }

    // CREATE / UPDATE
    public function update(Request $request)
    {
        $setting = app_setting::one();

        $validator = Validator::make($request->all(), [
            'app_name'    => 'nullable|string|max:255',
            'app_logo'    => 'nullable|image',
            'app_favicon' => 'nullable|image',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors'  => $validator->errors()->first(),
            ], 422);
        }

        $setting->app_name = $request->app_name;

        if ($request->hasFile('app_logo')) {

            // 🧹 delete old logo
            if ($setting->app_logo && Storage::disk('public')->exists($setting->app_logo)) {
                Storage::disk('public')->delete($setting->app_logo);
            }

            // new filename
            $name = Str::uuid() . '.webp';
            $path = 'settings/' . $name;

            // convert & save
            WebpService::convert(
                $request->file('app_logo')->getRealPath(),
                storage_path('app/public/' . $path),
                60
            );

            // save path
            $setting->app_logo = $path;
        }

        if ($request->hasFile('app_favicon')) {

            // delete old
            if ($setting->app_favicon && Storage::disk('public')->exists($setting->app_favicon)) {
                Storage::disk('public')->delete($setting->app_favicon);
            }

            $name = Str::uuid() . '.webp';
            $path = 'settings/' . $name;

            WebpService::convert(
                $request->file('app_favicon')->getRealPath(),
                storage_path('app/public/' . $path),
                60,
                32,
                32
            );

            $setting->app_favicon = $path;
        }

        $setting->save();

        return response()->json([
            'success' => true,
            'message' => 'Settings updated',
            'data'    => $setting,
        ]);
    }

    public function show_social_media()
    {
        $settings = SocialMediaSetting::first();

        return response()->json([
            'success' => true,
            'data'    => $settings,
        ]);
    }

    /**
     * Create or Update Social Media Settings (single row)
     */
    public function store_social_media(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'linkedin'  => 'nullable|url',
            'dribbble'  => 'nullable|url',
            'instagram' => 'nullable|url',
            'twitter'   => 'nullable|url',
            'youtube'   => 'nullable|url',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors'  => $validator->errors()->first(),
            ], 422);
        }

        $settings = SocialMediaSetting::updateOrCreate(
            ['id' => 1], // 👈 enforce single row
            $validator->validated()
        );

        return response()->json([
            'success' => true,
            'message' => 'Social media settings saved successfully',
            'data'    => $settings,
        ]);
    }
}
