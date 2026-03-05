<?php
namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ProductTaxAffinity;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ProductTaxAffinityController extends Controller
{
    public function store(Request $request, Product $product)
    {
        $validator = Validator::make($request->all(), [
            'gst_enabled'      => 'required|boolean',
            'gst_type'         => 'nullable|in:inclusive,exclusive',
            'gst_percent'      => 'nullable|numeric|min:0|max:100',

            'affinity_enabled' => 'required|boolean',
            'affinity_percent' => 'nullable|numeric|min:0|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors'  => $validator->errors()->first(),
            ], 422);
        }

        $tax = ProductTaxAffinity::updateOrCreate(
            ['product_id' => $product->id],
            [
                'gst_enabled'      => $request->gst_enabled ? 1 : 0,
                'gst_type'         => $request->gst_enabled
                    ? ($request->gst_type ?? 'exclusive')
                    : 'exclusive',

                'gst_percent'      => $request->gst_enabled
                    ? ($request->gst_percent ?? 0)
                    : 0,

                'affinity_enabled' => $request->affinity_enabled ? 1 : 0,
                'affinity_percent' => $request->affinity_enabled
                    ? ($request->affinity_percent ?? 0)
                    : 0,
            ]
        );

        return response()->json([
            'success' => true,
            'message' => 'Tax & affinity saved successfully',
            'data'    => $tax,
        ]);
    }

    public function update(Request $request, $productId)
    {
        /* ================= VALIDATION ================= */
        $validator = Validator::make($request->all(), [
            'gst_enabled'      => 'required|boolean',
            'gst_type'         => 'required_if:gst_enabled,true|in:inclusive,exclusive',
            'gst_percent'      => 'nullable|numeric|min:0|max:100',

            'affinity_enabled' => 'required|boolean',
            'affinity_percent' => 'nullable|numeric|min:0|max:100',

            'status'           => 'required|in:Published,draft',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status'  => false,
                'message' => 'Validation error',
                'errors'  => $validator->errors(),
            ], 422);
        }

        /* ================= TAX + AFFINITY ================= */
        $tax = ProductTaxAffinity::updateOrCreate(
            ['product_id' => $productId],
            [
                'gst_enabled'      => $request->gst_enabled,
                'gst_type'         => $request->gst_enabled ? $request->gst_type : 'exclusive',
                'gst_percent'      => $request->gst_enabled ? $request->gst_percent : 0,

                'affinity_enabled' => $request->affinity_enabled,
                'affinity_percent' => $request->affinity_enabled ? $request->affinity_percent : 0,
            ]
        );

        /* ================= PRODUCT STATUS ================= */
        Product::where('id', $productId)->update([
            'status' => $request->status,
        ]);

        return response()->json([
            'status'  => true,
            'message' => 'Tax & affinity settings updated successfully',
            'data'    => $tax,
        ]);
    }
}
