<?php
namespace App\Http\Controllers;

use App\Models\Brand;
use App\Services\WebpService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Validator;

class BrandController extends Controller
{
    public function index(Request $request)
    {
        $search  = $request->search;
        $perPage = $request->perPage ?? 10;

        $brands = Brand::when($search, fn($q) =>
            $q->where('name', 'like', "%{$search}%")
        )
            ->orderBy('id', 'desc')
            ->paginate($perPage);

        return response()->json([
            'data'       => $brands->getCollection()->map(fn($b) => [
                'id'     => $b->id,
                'name'   => $b->name,
                'slug'   => $b->slug,
                'status' => $b->status,
                'image'  => $b->image
                    ? asset('storage/brands/' . $b->image)
                    : null,
            ]),
            'pagination' => [
                'totalPages'  => $brands->lastPage(),
                'currentPage' => $brands->currentPage(),
            ],
        ]);
    }

    public function index_no_pagination(Request $request)
    {
        $brands = Brand::all();
        return response()->json(['brands' => $brands]);

    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name'   => 'required|string|max:255',
            'image'  => 'nullable|image|max:2048',
            'status' => 'required|in:active,inactive',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors'  => $validator->errors()->first(),
            ], 422);
        }

        $imageName = null;

        if ($request->hasFile('image')) {
            $file     = $request->file('image');
            $baseName = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);

            $imageName = time() . '_' . Str::slug($baseName) . '.webp';

            WebpService::convert(
                $file->getPathname(),
                storage_path('app/public/brands/' . $imageName),
                90,

            );
        }

        Brand::create([
            'name'   => $request->name,
            'slug'   => Str::slug($request->name),
            'image'  => $imageName,
            'status' => $request->status,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Brand created successfully',
        ]);
    }

    public function update(Request $request, $id)
    {
        $brand = Brand::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'name'   => 'required|string|max:255',
            'image'  => 'nullable|image|max:2048',
            'status' => 'required|in:active,inactive',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors'  => $validator->errors(),
            ], 422);
        }

        $imageName = $brand->image;

        if ($request->hasFile('image')) {

            if ($brand->image) {
                Storage::disk('public')->delete('brands/' . $brand->image);
            }

            $file     = $request->file('image');
            $baseName = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);

            $imageName = time() . '_' . Str::slug($baseName) . '.webp';

            WebpService::convert(
                $file->getPathname(),
                storage_path('app/public/brands/' . $imageName),
                60,

            );
        }

        $brand->update([
            'name'   => $request->name,
            'slug'   => Str::slug($request->name),
            'image'  => $imageName,
            'status' => $request->status,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Brand updated successfully',
        ]);
    }

    public function destroy($id)
    {
        $brand = Brand::findOrFail($id);

        if ($brand->image) {
            Storage::disk('public')->delete('brands/' . $brand->image);
        }

        $brand->delete();

        return response()->json([
            'success' => true,
            'message' => 'Brand deleted successfully',
        ]);
    }

}
