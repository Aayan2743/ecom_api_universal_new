<?php
namespace App\Http\Controllers;

use App\Models\LandingBanner;
use App\Services\WebpService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class LandingBannerController extends Controller
{
    public function index()
    {
        $banners = LandingBanner::where('status', 'Active')
            ->orderBy('sort_order')
            ->get();

        return response()->json([
            'success' => true,
            'data'    => $banners,
        ]);
    }

    public function adminList()
    {
        $banners = LandingBanner::
            orderBy('sort_order')
            ->get();

        return response()->json([
            'success' => true,
            'data'    => $banners,
        ]);
    }

    /* ================= STORE ================= */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'title'       => 'required|string|max:255',
            'subtitle'    => 'required|string|max:255',
            'small_text'  => 'required|string|max:255',
            'image'       => 'required|image|mimes:jpg,jpeg,png,webp|max:4096',
            'button_text' => 'required|string|max:255',
            'button_link' => 'required|string|max:500',
            'status'      => 'required|in:Active,Inactive',
            'sort_order'  => 'nullable|integer',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
            ], 422);
        }

        $data = $request->except('image');

        /* ================= IMAGE PROCESS ================= */
        if ($request->hasFile('image')) {

            $file = $request->file('image');

            // Temporary path
            $tempPath = $file->getPathname();

            // Destination folder
            $folder = storage_path('app/public/banners');

            // Unique name
            $fileName = Str::uuid() . '.webp';

            $destination = $folder . '/' . $fileName;

            WebpService::convert(
                $tempPath,
                $destination,
                70,   // quality
                1920, // width (adjust if needed)
                700   // height (adjust if needed)
            );

            $data['image'] = 'banners/' . $fileName;
        }

        $banner = LandingBanner::create($data);

        return response()->json([
            'success' => true,
            'message' => 'Banner created successfully',
            'data'    => $banner,
        ]);
    }

    /* ================= UPDATE ================= */
    public function update(Request $request, $id)
    {
        $banner = LandingBanner::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'title'       => 'nullable|string|max:255',
            'subtitle'    => 'nullable|string|max:255',
            'small_text'  => 'nullable|string|max:255',
            'image'       => 'nullable|image|mimes:jpg,jpeg,png,webp|max:4096',
            'button_text' => 'nullable|string|max:255',
            'button_link' => 'nullable|string|max:500',
            'status'      => 'required|in:Active,Inactive',
            'sort_order'  => 'nullable|integer',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
            ], 422);
        }

        $data = $request->except('image');

        if ($request->hasFile('image')) {

            // delete old
            if ($banner->image && Storage::disk('public')->exists($banner->image)) {
                Storage::disk('public')->delete($banner->image);
            }

            $file     = $request->file('image');
            $tempPath = $file->getPathname();

            $folder      = storage_path('app/public/banners');
            $fileName    = Str::uuid() . '.webp';
            $destination = $folder . '/' . $fileName;

            WebpService::convert(
                $tempPath,
                $destination,
                70,
                1920,
                700
            );

            $data['image'] = 'banners/' . $fileName;
        }

        $banner->update($data);

        return response()->json([
            'success' => true,
            'message' => 'Banner updated successfully',
            'data'    => $banner,
        ]);
    }

    /* ================= DELETE ================= */
    public function destroy($id)
    {
        $banner = LandingBanner::findOrFail($id);

        if ($banner->image && Storage::disk('public')->exists($banner->image)) {
            Storage::disk('public')->delete($banner->image);
        }

        $banner->delete();

        return response()->json([
            'success' => true,
            'message' => 'Banner deleted successfully',
        ]);
    }
}
