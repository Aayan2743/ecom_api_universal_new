<?php
namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ProductVariantCombination;
use App\Models\ProductVariantCombinationValue;
use App\Models\ProductVariantImage;
use App\Services\WebpService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class ProductVariantController extends Controller
{

    public function store(Request $request, Product $product)
    {
        // ✅ FIX: Decode variants JSON when sent via FormData
        if ($request->has('variants') && is_string($request->variants)) {
            $request->merge([
                'variants' => json_decode($request->variants, true),
            ]);
        }

        $validator = Validator::make($request->all(), [
            'variants'                       => 'required|array|min:1',
            'variants.*.variation_value_ids' => 'required|array|min:1',
            'variants.*.sku'                 => 'nullable|string|max:100',
            'variants.*.purchase_price'      => 'nullable|numeric|min:0',
            'variants.*.extra_price'         => 'nullable|numeric|min:0',
            'variants.*.quantity'            => 'nullable|integer|min:0',
            'variants.*.low_quantity'        => 'nullable|integer|min:0',

            // 🔥 images validation
            'variant_images'                 => 'nullable|array',
            'variant_images.*'               => 'nullable|array',
            'variant_images.*.*'             => 'image|mimes:jpg,jpeg,png,webp|max:5120',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors'  => $validator->errors()->first(),
            ], 422);
        }

        DB::beginTransaction();

        try {
            $createdVariants = [];

            foreach ($request->variants as $index => $variant) {

                $combo = ProductVariantCombination::create([
                    'product_id'     => $product->id,
                    'sku'            => $variant['sku'] ?? null,
                    'purchase_price' => $variant['purchase_price'] ?? 0,
                    'extra_price'    => $variant['sell_price'] ?? 0,
                    'discount'       => $variant['discount'] ?? 0,
                    'quantity'       => $variant['quantity'] ?? 0,
                    'low_quantity'   => $variant['low_quantity'] ?? 0,
                ]);

                foreach ($variant['variation_value_ids'] as $valueId) {
                    ProductVariantCombinationValue::create([
                        'variant_combination_id' => $combo->id,
                        'variation_value_id'     => $valueId,
                    ]);
                }

                // ✅ Variant Images
                if ($request->hasFile("variant_images.$index")) {
                    foreach ($request->file("variant_images.$index") as $file) {

                        $temp = $file->store('temp', 'public');
                        $src  = storage_path('app/public/' . $temp);

                        $filename     = Str::uuid() . '.webp';
                        $relativePath = "products/variant-images/$filename";
                        $dest         = storage_path('app/public/' . $relativePath);

                        WebpService::convert($src, $dest, 70);
                        Storage::disk('public')->delete($temp);

                        ProductVariantImage::create([
                            'variant_combination_id' => $combo->id,
                            'image_path'             => $relativePath,
                        ]);
                    }
                }

                $createdVariants[] = $combo;
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'data'    => $createdVariants,
            ], 201);

        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }
    }

    public function syncVariationsfff(Request $request, Product $product)
    {
        $validator = Validator::make($request->all(), [
            'variants'                       => 'required|array',

            'variants.*.id'                  => 'nullable|integer|exists:product_variant_combinations,id',
            'variants.*.variation_value_ids' => 'required|array',

            'variants.*.sku'                 => 'nullable|string|max:255',
            'variants.*.purchase_price'      => 'nullable|numeric|min:0',
            'variants.*.extra_price'         => 'nullable|numeric|min:0',
            'variants.*.sell_price'          => 'nullable|numeric|min:0',
            'variants.*.discount'            => 'nullable|numeric|min:0',
            'variants.*.quantity'            => 'nullable|integer|min:0',
            'variants.*.low_quantity'        => 'nullable|integer|min:0',

            'variants.*.keep_image_ids'      => 'nullable|array',
            'variants.*.keep_image_ids.*'    => 'integer|exists:product_variant_images,id',

            'variants.*.images'              => 'nullable|array',
            'variants.*.images.*'            => 'image|mimes:jpg,jpeg,png,webp|max:5120',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
            ], 422);
        }

        DB::beginTransaction();

        try {
            $savedVariantIds = [];

            foreach ($request->variants as $variant) {

                $combo = ProductVariantCombination::updateOrCreate(
                    ['id' => $variant['id'] ?? null],
                    [
                        'product_id'     => $product->id,
                        'sku'            => $variant['sku'] ?? '',
                        'purchase_price' => $variant['purchase_price'] ?? 0,
                        'extra_price'    => $variant['extra_price'] ?? $variant['sell_price'] ?? 0,
                        'discount'       => $variant['discount'] ?? 0,
                        'quantity'       => $variant['quantity'] ?? 0,
                        'low_quantity'   => $variant['low_quantity'] ?? 0,
                    ]
                );

                $savedVariantIds[] = $combo->id;

                // Sync variation values
                $combo->values()->sync($variant['variation_value_ids']);

                // Remove deleted images
                if (isset($variant['keep_image_ids'])) {
                    $combo->images()
                        ->whereNotIn('id', $variant['keep_image_ids'])
                        ->get()
                        ->each(function ($img) {
                            Storage::disk('public')->delete($img->image_path);
                            $img->delete();
                        });
                }

                // Add new images
                if (! empty($variant['images'])) {
                    foreach ($variant['images'] as $file) {

                        $temp = $file->store('temp', 'public');
                        $src  = storage_path('app/public/' . $temp);

                        $name = Str::uuid() . '.webp';
                        $path = 'products/variant-images/' . $name;
                        $dest = storage_path('app/public/' . $path);

                        WebpService::convert($src, $dest, 70);
                        Storage::disk('public')->delete($temp);

                        $combo->images()->create([
                            'image_path' => $path,
                        ]);
                    }
                }
            }

            // Delete removed variants
            ProductVariantCombination::where('product_id', $product->id)
                ->whereNotIn('id', $savedVariantIds)
                ->get()
                ->each(function ($combo) {
                    foreach ($combo->images as $img) {
                        Storage::disk('public')->delete($img->image_path);
                    }
                    $combo->delete();
                });

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Product variations saved successfully',
            ]);

        } catch (\Throwable $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Failed to save variations',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    public function syncVariations(Request $request, Product $product)
    {
        $validator = Validator::make($request->all(), [
            'variants'                       => 'required|array',

            'variants.*.id'                  => 'nullable|integer|exists:product_variant_combinations,id',
            'variants.*.variation_value_ids' => 'required|array',

            'variants.*.sku'                 => 'nullable|string|max:255',
            'variants.*.purchase_price'      => 'nullable|numeric|min:0',
            'variants.*.extra_price'         => 'nullable|numeric|min:0',
            'variants.*.discount'            => 'nullable|numeric|min:0',
            'variants.*.quantity'            => 'nullable|integer|min:0',
            'variants.*.low_quantity'        => 'nullable|integer|min:0',

            'variants.*.keep_image_ids'      => 'nullable|array',
            'variants.*.keep_image_ids.*'    => 'integer|exists:product_variant_images,id',

            'variants.*.images'              => 'nullable|array',
            'variants.*.images.*'            => 'image|mimes:jpg,jpeg,png,webp|max:5120',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
            ], 422);
        }

        DB::beginTransaction();

        try {
            $savedVariantIds = [];

            foreach ($request->variants as $variant) {

                /* CREATE / UPDATE VARIANT */
                $combo = ProductVariantCombination::updateOrCreate(
                    ['id' => $variant['id'] ?? null],
                    [
                        'product_id'     => $product->id,
                        'sku'            => $variant['sku'] ?? '',
                        'purchase_price' => $variant['purchase_price'] ?? 0,
                        'extra_price'    => $variant['extra_price'] ?? 0,
                        'discount'       => $variant['discount'] ?? 0,
                        'quantity'       => $variant['quantity'] ?? 0,
                        'low_quantity'   => $variant['low_quantity'] ?? 0,
                    ]
                );

                $savedVariantIds[] = $combo->id;

                /* SYNC VARIATION VALUES */
                $combo->values()->sync($variant['variation_value_ids']);

                /* REMOVE DELETED IMAGES */
                if (isset($variant['keep_image_ids'])) {
                    $combo->images()
                        ->whereNotIn('id', $variant['keep_image_ids'])
                        ->get()
                        ->each(function ($img) {
                            Storage::disk('public')->delete($img->image_path);
                            $img->delete();
                        });
                }

                /* ADD NEW IMAGES (WEBP) */
                if (! empty($variant['images'])) {
                    foreach ($variant['images'] as $file) {
                        $temp = $file->store('temp', 'public');
                        $src  = storage_path('app/public/' . $temp);

                        $name = Str::uuid() . '.webp';
                        $path = 'products/variant-images/' . $name;
                        $dest = storage_path('app/public/' . $path);

                        WebpService::convert($src, $dest, 70);
                        Storage::disk('public')->delete($temp);

                        $combo->images()->create([
                            'image_path' => $path,
                        ]);
                    }
                }
            }

            /* DELETE REMOVED VARIANTS */
            ProductVariantCombination::where('product_id', $product->id)
                ->whereNotIn('id', $savedVariantIds)
                ->each(function ($combo) {
                    foreach ($combo->images as $img) {
                        Storage::disk('public')->delete($img->image_path);
                    }
                    $combo->delete();
                });

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Product variations updated successfully',
            ]);

        } catch (\Throwable $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Failed to save variations',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

}
