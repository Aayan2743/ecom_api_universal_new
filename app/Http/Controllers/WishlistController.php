<?php
namespace App\Http\Controllers;

use App\Models\Wishlist;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class WishlistController extends Controller
{
    public function index_w(Request $request)
    {

        $wishlist = Wishlist::with('product.images', 'product.variants')
            ->where('user_id', auth()->id())
            ->latest()
            ->get()
            ->map(function ($item) {
                $product = $item->product;

                return [
                    'id'    => $product->id,
                    'name'  => $product->name,
                    'slug'  => $product->slug,

                    // ✅ GET ACTUAL SELLING PRICE
                    'price' => optional($product->variants->first())->selling_price ?? 0,

                    'image' => optional(
                        $product->images->firstWhere('is_primary', true) ?? $product->images->first()
                    )->image_url,
                ];
            });

        return response()->json([
            'success' => true,
            'data'    => $wishlist,
        ]);
    }

    public function index(Request $request)
    {
        $wishlist = Wishlist::with('product.images', 'product.variants')
            ->where('user_id', auth()->id())
            ->latest()
            ->get()
            ->map(function ($item) {

                $product = $item->product;
                $variant = $product->variants->first();

                // ✅ FIXED: use extra_price
                $price = optional($variant)->extra_price ?? 0;

                // ✅ uses accessor from model
                $amount = optional($variant)->amount ?? $price;

                return [
                    'id'     => $product->id,
                    'name'   => $product->name,
                    'slug'   => $product->slug,

                    // existing (now correct)
                    'price'  => $price,

                    // only new field
                    'amount' => $amount,

                    'image'  => optional(
                        $product->images->firstWhere('is_primary', true) ?? $product->images->first()
                    )->image_url,
                ];
            });

        return response()->json([
            'success' => true,
            'data'    => $wishlist,
        ]);
    }

    /* ================= ADD / REMOVE (TOGGLE) ================= */
    public function toggle(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'product_id' => 'required|exists:products,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
            ], 422);
        }

        $wishlist = Wishlist::where('user_id', auth()->id())
            ->where('product_id', $request->product_id)
            ->first();

        if ($wishlist) {
            $wishlist->delete();

            return response()->json([
                'success' => true,
                'action'  => 'removed',
            ]);
        }

        Wishlist::create([
            'user_id'    => auth()->id(),
            'product_id' => $request->product_id,
        ]);

        return response()->json([
            'success' => true,
            'action'  => 'added',
        ]);
    }
}
