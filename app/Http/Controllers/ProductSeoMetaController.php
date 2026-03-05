<?php
namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ProductSeoMeta;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ProductSeoMetaController extends Controller
{
    public function store(Request $request, Product $product)
    {
        $validator = Validator::make($request->all(), [
            'meta_title'       => 'nullable|string|max:60',
            'meta_description' => 'nullable|string|max:160',
            'meta_tags'        => 'nullable|string', // comma separated
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors'  => $validator->errors()->first(),
            ], 422);
        }

        // ✅ UPSERT (create or update)
        $seo = ProductSeoMeta::updateOrCreate(
            ['product_id' => $product->id],
            [
                'meta_title'       => $request->meta_title,
                'meta_description' => $request->meta_description,
                'meta_tags'        => $request->meta_tags,
            ]
        );

        return response()->json([
            'success' => true,
            'message' => 'SEO meta saved successfully',
            'data'    => $seo,
        ], 200);
    }

    public function update(Request $request, $productId)
    {
        /* ================= VALIDATION ================= */
        $validator = Validator::make($request->all(), [
            'meta_title'       => 'nullable|string|max:60',
            'meta_description' => 'nullable|string|max:160',
            'meta_tags'        => 'nullable|string', // comma-separated
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status'  => false,
                'message' => 'Validation error',
                'errors'  => $validator->errors()->first(),
            ], 422);
        }

        /* ================= UPDATE OR CREATE ================= */
        $meta = ProductSeoMeta::updateOrCreate(
            ['product_id' => $productId],
            [
                'meta_title'       => $request->meta_title,
                'meta_description' => $request->meta_description,
                'meta_tags'        => $request->meta_tags,
            ]
        );

        return response()->json([
            'status'  => true,
            'message' => 'Product meta updated successfully',
            'data'    => $meta,
        ]);
    }
}
