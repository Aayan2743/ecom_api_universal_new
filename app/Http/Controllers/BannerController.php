<?php
namespace App\Http\Controllers;

use App\Models\Banner;
use App\Services\WebpService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class BannerController extends Controller
{
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'title'       => 'required|string|max:255',
            'subtitle'    => 'required|string|max:255',
            'description' => 'required|string',
            'button_text' => 'required|string|max:100',
            'button_link' => 'required|url|max:255',
            'start_date'  => 'required|date',
            'end_date'    => 'required|date|after:start_date',
            'image'       => 'required|image|mimes:jpg,jpeg,png,webp|max:2048',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors'  => $validator->errors()->first(),
            ], 422);
        }

        $fileName = Str::uuid() . '.webp';

        WebpService::convert(
            $request->file('image')->getPathname(),
            storage_path('app/public/banners/' . $fileName),
            60,
            800,
            800
        );

        $banner = Banner::create([
            'title'       => $request->title,
            'subtitle'    => $request->subtitle,
            'description' => $request->description,
            'button_text' => $request->button_text,
            'button_link' => $request->button_link,
            'start_date'  => $request->start_date,
            'end_date'    => $request->end_date,
            'image'       => 'banners/' . $fileName,
            'status'      => 1,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Scheduled Banner Created',
            'data'    => $banner,
        ]);
    }

    public function update(Request $request, $id)
    {
        $banner = Banner::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'title'       => 'required|string|max:255',
            'subtitle'    => 'required|string|max:255',
            'description' => 'required|string',
            'button_text' => 'required|string|max:100',
            'button_link' => 'required|url|max:255',
            'image'       => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
            'start_date'  => 'required|date',
            'end_date'    => 'required|date|after:start_date',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors'  => $validator->errors()->first(),
            ], 422);
        }

        $imagePath = $banner->image;

        if ($request->hasFile('image')) {

            // delete old image
            if ($banner->image && file_exists(storage_path('app/public/' . $banner->image))) {
                unlink(storage_path('app/public/' . $banner->image));
            }

            $fileName = Str::uuid() . '.webp';

            WebpService::convert(
                $request->file('image')->getPathname(),
                storage_path('app/public/banners/' . $fileName),
                60,
                800,
                800
            );

            $imagePath = 'banners/' . $fileName;
        }

        $banner->update([
            'title'       => $request->title,
            'subtitle'    => $request->subtitle,
            'description' => $request->description,
            'button_text' => $request->button_text,
            'button_link' => $request->button_link,
            'start_date'  => $request->start_date,
            'end_date'    => $request->end_date,
            'image'       => $imagePath,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Banner Updated Successfully',
            'data'    => $banner->fresh(),
        ]);
    }

    public function activeBanner()
    {
        $now = Carbon::now();

        $banner = Banner::where('status', 1)
            ->where(function ($query) use ($now) {
                $query->whereNull('start_date')
                    ->orWhere('start_date', '<=', $now);
            })
            ->where(function ($query) use ($now) {
                $query->whereNull('end_date')
                    ->orWhere('end_date', '>=', $now);
            })
            ->latest()
            ->first();

        if (! $banner) {
            return response()->json([
                'success' => false,
                'message' => 'No active scheduled banner',
            ]);
        }

        return response()->json([
            'success' => true,
            'data'    => [
                'title'       => $banner->title,
                'subtitle'    => $banner->subtitle,
                'description' => $banner->description,
                'button_text' => $banner->button_text,
                'button_link' => $banner->button_link,
                'image'       => asset('storage/' . $banner->image),
                'start_date'  => $banner->start_date,
                'end_date'    => $banner->end_date,
            ],
        ]);
    }

    public function changeStatus($id)
    {
        $banner = Banner::findOrFail($id);

        $banner->status = $banner->status ? 0 : 1;
        $banner->save();

        return response()->json([
            'success' => true,
            'message' => 'Banner status updated',
            'status'  => $banner->status,
        ]);
    }

    public function index(Request $request)
    {
        $search  = $request->get('search');
        $perPage = $request->get('per_page', 10);

        $query = Banner::query();

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%$search%")
                    ->orWhere('subtitle', 'like', "%$search%");
            });
        }

        $banners = $query->orderByDesc('id')->paginate($perPage);

        return response()->json([
            'success' => true,
            'data'    => $banners,
        ]);
    }

    public function show($id)
    {
        $banner = Banner::find($id);

        if (! $banner) {
            return response()->json([
                'success' => false,
                'message' => 'Banner not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data'    => $banner,
        ]);
    }

    public function destroy($id)
    {
        $banner = Banner::find($id);

        if (! $banner) {
            return response()->json([
                'success' => false,
                'message' => 'Banner not found',
            ], 404);
        }

        // Delete image file
        if ($banner->image && file_exists(storage_path('app/public/' . $banner->image))) {
            unlink(storage_path('app/public/' . $banner->image));
        }

        $banner->delete();

        return response()->json([
            'success' => true,
            'message' => 'Banner deleted successfully',
        ]);
    }

}
