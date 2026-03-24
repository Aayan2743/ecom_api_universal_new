<?php

namespace App\Http\Controllers;

use App\Exports\ProductBulkExport;
use App\Models\ProductVariantCombination;
use App\Services\WebpService;
use Illuminate\Http\Request;


use App\Http\Controllers\Controller;

use Maatwebsite\Excel\Facades\Excel;
use App\Imports\ProductBulkImport;
use App\Models\ProductImage;
use App\Models\ProductVariantImage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class ProductBulkImportController extends Controller
{
    public function bulkUpload(Request $request)
{

     $validator = Validator::make($request->all(), [
             'file' => 'required|mimes:xlsx,csv'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors'  => $validator->errors()->first(),
            ], 422);
        }

    $import = new ProductBulkImport();

    Excel::import($import, $request->file('file'));

    if(count($import->errors) > 0){

        return response()->json([
            'success'=>false,
            'errors'=>$import->errors
        ],422);
    }

    return response()->json([
        'success'=>true,
        'message'=>'Bulk upload completed'
    ]);
    }

    public function downloadBulkTemplate()
    {
        return Excel::download(new ProductBulkExport(), 'product_bulk_template.xlsx');
    }



    public function productVariants_working(Request $request)
{
    $search  = $request->search;
    $perPage = $request->perPage ?? 10;

    $variants = ProductVariantCombination::with([
        'product',
        'values',
        'images'
    ])
    ->whereHas('product')
    ->when($search, function ($q) use ($search) {

        $q->where('sku', 'like', "%$search%")
          ->orWhereHas('product', function ($p) use ($search) {
              $p->where('name', 'like', "%$search%");
          });

    })
    ->paginate($perPage);

    $data = $variants->getCollection()->map(function ($variant) {

        return [
            'id' => $variant->id,
            'product_name' => $variant->product->name,
            'sku' => $variant->sku,

            'variation_values' => $variant->values
                ->pluck('value')
                ->values(),

            'images' => $variant->images
                ->map(function ($img) {

                    return [
                        'id' => $img->id,
                        'url' => asset('storage/'.$img->image_path)
                    ];

                })
                ->values(),
        ];
    });

    return response()->json([
        'success' => true,
        'data' => $data,
        'pagination' => [
            'currentPage' => $variants->currentPage(),
            'totalPages' => $variants->lastPage(),
            'perPage' => $variants->perPage(),
            'total' => $variants->total(),
        ]
    ]);
}

public function productVariants(Request $request)
{
    $search  = $request->search;
    $perPage = $request->perPage ?? 10;

    $variants = ProductVariantCombination::with([
        'product.images',   // 🔥 product images
        'product',
        'values',
        'images',
        'barcodes'
    ])
    ->whereHas('product')
    ->when($search, function ($q) use ($search) {
        $q->where('sku', 'like', "%$search%")
          ->orWhereHas('product', function ($p) use ($search) {
              $p->where('name', 'like', "%$search%");
          });
    })
    ->paginate($perPage);

    $data = $variants->getCollection()->map(function ($variant) {

        return [
            'id' => $variant->id,
            'qty' => $variant->quantity,

            'product_id' => $variant->product->id,

            'product_name' => $variant->product->name,

            'sku' => $variant->sku,

            'variation_values' => $variant->values
                ->pluck('value')
                ->values(),

            /* PRODUCT IMAGES */

            'product_images' => $variant->product->images
                ->map(function ($img) {
                    return [
                        'id' => $img->id,
                        'url' => asset('storage/'.$img->image_path)
                    ];
                })->values(),

            /* VARIANT IMAGES */

            'variant_images' => $variant->images
                ->map(function ($img) {
                    return [
                        'id' => $img->id,
                        'url' => asset('storage/'.$img->image_path)
                    ];
                })->values(),

              'barcodes' => $variant->barcodes
    ->take($variant->quantity)
    ->map(function ($b) {
        return [
            'id' => $b->id,
            'barcode' => $b->barcode,
            'print_count' => $b->print_count ?? 0,
        ];
    })
    ->values(),

        ];
    });

    return response()->json([
        'success' => true,
        'data' => $data,
        'pagination' => [
            'currentPage' => $variants->currentPage(),
            'totalPages' => $variants->lastPage(),
            'perPage' => $variants->perPage(),
            'total' => $variants->total(),
        ]
    ]);
}



public function bulkProductImages(Request $request)
{
    foreach ($request->images as $productId => $files) {

        foreach ($files as $file) {

            $filename = Str::uuid().".webp";

            $destination = storage_path(
                "app/public/products/images/".$filename
            );

            WebpService::convert(
                $file->getPathname(),
                $destination,
                60
            );

            ProductImage::create([
                'product_id'=>$productId,
                'image_path'=>"products/images/".$filename
            ]);
        }
    }

    return response()->json([
        'success'=>true
    ]);
}


public function deleteProductImage($id)
{
    $image = ProductImage::find($id);

    if(!$image){
        return response()->json([
            'success'=>false,
            'message'=>"Image not found"
        ]);
    }

    Storage::disk('public')->delete($image->image_path);

    $image->delete();

    return response()->json([
        'success'=>true
    ]);
}



public function bulkVariantImages(Request $request)
{
    try {

        if (!$request->has('images')) {
            return response()->json([
                'success' => false,
                'message' => 'No images received'
            ], 422);
        }

        $saved = [];

        DB::beginTransaction();

        foreach ($request->images as $variantId => $files) {

            // 🔹 Check variant + product relation
            $variant = ProductVariantCombination::with('product')->find($variantId);

            if (!$variant || !$variant->product) {
                // Skip if product is null
                continue;
            }

            foreach ($files as $file) {

                $filename = Str::uuid() . ".webp";

                $destination = storage_path(
                    "app/public/products/variant-images/" . $filename
                );

                WebpService::convert(
                    $file->getPathname(),
                    $destination,
                    60
                );

                ProductVariantImage::create([
                    'variant_combination_id' => $variantId,
                    'image_path' => "products/variant-images/" . $filename
                ]);

                $saved[] = $filename;
            }
        }

        DB::commit();

        return response()->json([
            'success' => true,
            'uploaded' => count($saved)
        ]);

    } catch (\Throwable $e) {

        DB::rollBack();

        return response()->json([
            'success' => false,
            'error' => 'SERVER_ERROR',
            'message' => $e->getMessage()
        ], 500);
    }
}


public function bulkProductVariantImages(Request $request)
{
    try {

        if (!$request->has('variant_images') && !$request->has('product_images')) {

            return response()->json([
                'success' => false,
                'message' => 'No images received'
            ], 422);

        }

        $uploaded = 0;

        DB::beginTransaction();

        /* ================= VARIANT IMAGES ================= */

        if ($request->variant_images) {

            foreach ($request->variant_images as $variantId => $files) {

                foreach ($files as $file) {

                    $filename = Str::uuid() . ".webp";

                    $destination = storage_path(
                        "app/public/products/variant-images/" . $filename
                    );

                    WebpService::convert(
                        $file->getPathname(),
                        $destination,
                        60
                    );

                    ProductVariantImage::create([
                        'variant_combination_id' => $variantId,
                        'image_path' => "products/variant-images/" . $filename
                    ]);

                    $uploaded++;

                }
            }
        }

        /* ================= PRODUCT IMAGES ================= */

        if ($request->product_images) {

            foreach ($request->product_images as $productId => $files) {

                foreach ($files as $file) {

                    $filename = Str::uuid() . ".webp";

                    $destination = storage_path(
                        "app/public/products/images/" . $filename
                    );

                    WebpService::convert(
                        $file->getPathname(),
                        $destination,
                        60
                    );

                    ProductImage::create([
                        'product_id' => $productId,
                        'image_path' => "products/images/" . $filename
                    ]);

                    $uploaded++;

                }
            }
        }

        DB::commit();

        return response()->json([
            'success' => true,
            'uploaded' => $uploaded
        ]);

    } catch (\Throwable $e) {

        DB::rollBack();

        return response()->json([
            'success' => false,
            'error' => 'SERVER_ERROR',
            'message' => $e->getMessage()
        ], 500);

    }
}



public function deleteVariantImage($id)
{
    try {

        $image = ProductVariantImage::find($id);

        if (!$image) {
            return response()->json([
                'success' => false,
                'message' => 'Image not found'
            ], 404);
        }

        if ($image->image_path &&
            Storage::disk('public')->exists($image->image_path)) {

            Storage::disk('public')->delete($image->image_path);
        }

        $image->delete();

        return response()->json([
            'success' => true,
            'message' => 'Image deleted successfully'
        ]);

    } catch (\Throwable $e) {

        return response()->json([
            'success' => false,
            'error' => 'SERVER_ERROR',
            'message' => $e->getMessage()
        ], 500);
    }
}

}
