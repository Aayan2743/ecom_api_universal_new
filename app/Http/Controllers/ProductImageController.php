<?php
namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductVideo;
use App\Services\WebpService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class ProductImageController extends Controller
{
    // public function store(Request $request, Product $product)
    // {
    //     $validator = Validator::make($request->all(), [
    //         'images'       => 'nullable|array',
    //         'images.*'     => 'image|mimes:jpg,jpeg,png,webp|max:5120',
    //         'main_index'   => 'required|integer|min:0',
    //         'video_urls'   => 'nullable|array',
    //         'video_urls.*' => 'required|url',
    //     ]);

    //     if ($validator->fails()) {
    //         return response()->json([
    //             'success' => false,
    //             'errors'  => $validator->errors()->first(),
    //         ], 422);
    //     }

    //     /* ================= SAVE IMAGES (WEBP) ================= */

    //     if ($request->hasFile('images')) {
    //         foreach ($request->file('images') as $index => $file) {

    //             // 1️⃣ Store temp original
    //             $tempPath = $file->store('temp', 'public');
    //             $srcPath  = storage_path('app/public/' . $tempPath);

    //             // 2️⃣ Generate WEBP destination
    //             $fileName         = Str::uuid() . '.webp';
    //             $webpRelativePath = 'products/images/' . $fileName;
    //             $destPath         = storage_path('app/public/' . $webpRelativePath);

    //             // 3️⃣ Convert to WEBP
    //             WebpService::convert(
    //                 $srcPath,
    //                 $destPath,
    //                 60// quality
    //             );

    //             // 4️⃣ Delete temp original
    //             Storage::disk('public')->delete($tempPath);

    //             // 5️⃣ Save DB
    //             ProductImage::create([
    //                 'product_id' => $product->id,
    //                 'image_path' => $webpRelativePath,
    //                 'is_primary' => ((int) $request->main_index === $index),
    //             ]);
    //         }
    //     }

    //     /* ================= SAVE VIDEOS ================= */

    //     if (! empty($request->video_urls)) {
    //         foreach ($request->video_urls as $url) {
    //             ProductVideo::create([
    //                 'product_id' => $product->id,
    //                 'video_url'  => $url,
    //             ]);
    //         }
    //     }

    //     return response()->json([
    //         'success' => true,
    //         'message' => 'Product gallery saved successfully',
    //     ], 201);
    // }

    public function update(Request $request, Product $product)
    {

        $validator = Validator::make($request->all(), [
            'images'       => 'nullable|array',
            'images.*'     => 'image|mimes:jpg,jpeg,png,webp|max:5120',
            'main_index'   => 'nullable|integer|min:0',
            'video_urls'   => 'nullable|array',
            'video_urls.*' => 'required|url',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors'  => $validator->errors()->first(),
            ], 422);
        }

        /* ================= UPDATE IMAGES ================= */

        ProductImage::where('product_id', $product->id)
            ->update(['is_primary' => false]);

        if ($request->hasFile('images')) {

            // 🔥 Delete old images (DB + storage)
            $oldImages = ProductImage::where('product_id', $product->id)->get();

            foreach ($oldImages as $old) {
                Storage::disk('public')->delete($old->image_path);
                $old->delete();
            }

            // 🔥 Add new images (convert → WEBP)
            foreach ($request->file('images') as $index => $file) {

                // 1️⃣ Temp store
                $tempPath = $file->store('temp', 'public');
                $srcPath  = storage_path('app/public/' . $tempPath);

                // 2️⃣ WEBP destination
                $fileName         = Str::uuid() . '.webp';
                $webpRelativePath = 'products/images/' . $fileName;
                $destPath         = storage_path('app/public/' . $webpRelativePath);

                // 3️⃣ Convert
                WebpService::convert($srcPath, $destPath, 70);

                // 4️⃣ Delete temp
                Storage::disk('public')->delete($tempPath);

                // 5️⃣ Save DB
                ProductImage::create([
                    'product_id' => $product->id,
                    'image_path' => $webpRelativePath,
                    // 'is_primary' =>((int) $request->main_index === $index),
                    'is_primary' => ((int) $request->main_index === $index),
                ]);
            }
        }

        /* ================= UPDATE VIDEOS ================= */

        if ($request->has('video_urls')) {

            // 🔥 Delete old videos
            ProductVideo::where('product_id', $product->id)->delete();

            // 🔥 Add new ones
            if (! empty($request->video_urls)) {
                foreach ($request->video_urls as $url) {
                    ProductVideo::create([
                        'product_id' => $product->id,
                        'video_url'  => $url,
                    ]);
                }
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Product gallery updated successfully',
        ]);
    }

    public function addImages(Request $request, Product $product)
    {
        $validator = Validator::make($request->all(), [
            'images'   => 'required|array',
            'images.*' => 'image|mimes:jpg,jpeg,png,webp|max:5120',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors'  => $validator->errors()->first(),
            ], 422);
        }

        foreach ($request->file('images') as $file) {

            // temp store
            $tempPath = $file->store('temp', 'public');
            $srcPath  = storage_path('app/public/' . $tempPath);

            // webp destination
            $fileName = Str::uuid() . '.webp';
            $relative = 'products/images/' . $fileName;
            $destPath = storage_path('app/public/' . $relative);

            // convert
            WebpService::convert($srcPath, $destPath, 70);

            // cleanup
            Storage::disk('public')->delete($tempPath);

            // save
            ProductImage::create([
                'product_id' => $product->id,
                'image_path' => $relative,
                'is_primary' => false,
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Images added successfully',
        ]);
    }

    public function deleteImage(ProductImage $image)
    {
        Storage::disk('public')->delete($image->image_path);
        $image->delete();

        return response()->json([
            'success' => true,
            'message' => 'Image deleted successfully',
        ]);
    }

    public function setMainImage(Request $request, Product $product)
    {
        $validator = Validator::make($request->all(), [
            'image_id' => 'required|exists:product_images,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors'  => $validator->errors()->first(),
            ], 422);
        }

        // reset old main
        ProductImage::where('product_id', $product->id)
            ->update(['is_primary' => false]);

        // set new main
        ProductImage::where('id', $request->image_id)
            ->update(['is_primary' => true]);

        return response()->json([
            'success' => true,
            'message' => 'Main image updated',
        ]);
    }

    public function updateVideos(Request $request, Product $product)
    {
        $validator = Validator::make($request->all(), [
            'video_urls'   => 'nullable|array',
            'video_urls.*' => 'required|url',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors'  => $validator->errors()->first(),
            ], 422);
        }

        ProductVideo::where('product_id', $product->id)->delete();

        foreach ($request->video_urls ?? [] as $url) {
            ProductVideo::create([
                'product_id' => $product->id,
                'video_url'  => $url,
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Videos updated successfully',
        ]);
    }
}
