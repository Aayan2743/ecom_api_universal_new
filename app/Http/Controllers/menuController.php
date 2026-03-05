<?php
namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class menuController extends Controller
{
/**
 * @OA\Get(
 *   path="/api/menu",
 *   summary="Menu",
 *   @OA\Response(response=200, description="OK")
 * )
 */
    public function menu()
    {
        $categories = Category::whereNull('parent_id')
            ->where('is_active', 1)
            ->with(['children' => function ($q) {
                $q->where('is_active', 1)->orderBy('name');
            }])
            ->get();

        $menu = $categories->map(function ($cat) {
            return [
                'key'   => Str::slug($cat->name),
                'label' => $cat->name,
                'items' => $cat->children->pluck('name')->values(),
            ];
        })->values();

        return response()->json($menu);
    }

    public function products_working(Request $request)
    {
        $query = Product::where('status', 'active')
            ->with('category:id,name,slug,parent_id');

        if ($request->filled('category')) {
            $category = Category::where('slug', $request->category)->first();

            if ($category) {
                if ($category->parent_id) {
                    // sub category
                    $query->where('category_id', $category->id);
                } else {
                    // main category
                    $subIds = Category::where('parent_id', $category->id)
                        ->pluck('id');

                    $query->whereIn('category_id', $subIds);
                }
            }
        }

        return response()->json([
            'success' => true,
            'data'    => $query->paginate(12),
        ]);
    }

    public function products2(Request $request)
    {
        $query = Product::where('status', 'active')
            ->with([
                'category:id,name,slug,parent_id',
                'images:id,product_id,image_path,is_primary',
                'videos:id,product_id,video_url',
                'variantCombinations.values.variation:id,name',
            ]);

        /* ================= CATEGORY FILTER ================= */
        if ($request->filled('category')) {
            $category = Category::where('slug', $request->category)->first();

            if ($category) {
                if ($category->parent_id) {
                    $query->where('category_id', $category->id);
                } else {
                    $subIds = Category::where('parent_id', $category->id)->pluck('id');
                    $query->whereIn('category_id', $subIds);
                }
            }
        }

        /* ================= SLUG FILTER ================= */
        if ($request->filled('slug')) {
            $query->where('slug', $request->slug);
        }

        /* ================= COLOR + PRICE (SAME VARIANT) ================= */
        if (
            $request->filled('colors') ||
            $request->filled('min_price') ||
            $request->filled('max_price')
        ) {
            $colorIds = $request->filled('colors')
                ? explode(',', $request->colors)
                : [];

            $min = $request->min_price ?? 0;
            $max = $request->max_price ?? PHP_INT_MAX;

            $query->whereHas('variantCombinations', function ($q) use ($colorIds, $min, $max) {
                $q->whereBetween('extra_price', [$min, $max]);

                if (! empty($colorIds)) {
                    $q->whereHas('values', function ($qq) use ($colorIds) {
                        $qq->whereIn(
                            'product_variant_combination_values.variation_value_id',
                            $colorIds
                        );
                    });
                }
            });
        }

        $products = $query->paginate(12);

        // (transform code stays EXACTLY same as before)

        return response()->json([
            'success' => true,
            'data'    => $products,
        ]);
    }

    public function products(Request $request)
    {
        $query = Product::where('status', 'Published')
            ->with([
                'category:id,name,slug,parent_id',
                'images:id,product_id,image_path,is_primary',
                'videos:id,product_id,video_url',
                'variantCombinations.values.variation:id,name',
                'sections:id,name,slug',
            ]);

        /* ================= CATEGORY FILTER ================= */
        if ($request->filled('category')) {
            $category = Category::where('slug', $request->category)->first();

            if ($category) {
                if ($category->parent_id) {
                    $query->where('category_id', $category->id);
                } else {
                    $subIds = Category::where('parent_id', $category->id)->pluck('id');
                    $query->whereIn('category_id', $subIds);
                }
            }
        }

        /* ================= SLUG FILTER ================= */
        if ($request->filled('slug')) {
            $query->where('slug', $request->slug);
        }

        /* ================= COLOR + PRICE FILTER ================= */
        if (
            $request->filled('colors') ||
            $request->filled('min_price') ||
            $request->filled('max_price')
        ) {
            $colorIds = $request->filled('colors')
                ? explode(',', $request->colors)
                : [];

            $min = $request->min_price ?? 0;
            $max = $request->max_price ?? PHP_INT_MAX;

            $query->whereHas('variantCombinations', function ($q) use ($colorIds, $min, $max) {
                $q->whereBetween('extra_price', [$min, $max]);

                if (! empty($colorIds)) {
                    $q->whereHas('values', function ($qq) use ($colorIds) {
                        $qq->whereIn(
                            'product_variant_combination_values.variation_value_id',
                            $colorIds
                        );
                    });
                }
            });
        }

        $products = $query->paginate(12);

        // ❌ NO TRANSFORM
        // ❌ NO RESPONSE CHANGE

        return response()->json([
            'success' => true,
            'data'    => $products,
        ]);
    }

    public function products_main_w(Request $request)
    {
        $query = Product::where('status', 'Published')
            ->with([
                'category:id,name,slug,parent_id',
                'images:id,product_id,image_path,is_primary',
                'videos:id,product_id,video_url',
                'variantCombinations.values.variation:id,name',
                'sections:id,name,slug',
            ]);

        /* ================= CATEGORY FILTER ================= */
        if ($request->filled('category')) {
            $category = Category::where('slug', $request->category)->first();

            if ($category) {
                $query->where('category_id', $category->id);
            }
        }

        /* ================= PRODUCT SLUG FILTER ================= */
        if ($request->filled('slug')) {
            $query->where('slug', $request->slug);
        }

        /* ================= COLOR + PRICE FILTER ================= */
        if (
            $request->filled('colors') ||
            $request->filled('min_price') ||
            $request->filled('max_price')
        ) {
            $colorIds = $request->filled('colors')
                ? explode(',', $request->colors)
                : [];

            $min = $request->min_price ?? 0;
            $max = $request->max_price ?? PHP_INT_MAX;

            $query->whereHas('variantCombinations', function ($q) use ($colorIds, $min, $max) {
                $q->whereBetween('extra_price', [$min, $max]);

                if (! empty($colorIds)) {
                    $q->whereHas('values', function ($qq) use ($colorIds) {
                        $qq->whereIn(
                            'product_variant_combination_values.variation_value_id',
                            $colorIds
                        );
                    });
                }
            });
        }

        /* ================= SORT ================= */
        if ($request->filled('sort')) {
            if ($request->sort === 'price_low_high') {
                $query->orderByRaw(
                    '(select min(extra_price) from product_variant_combinations where product_id = products.id) asc'
                );
            }

            if ($request->sort === 'price_high_low') {
                $query->orderByRaw(
                    '(select min(extra_price) from product_variant_combinations where product_id = products.id) desc'
                );
            }
        }

        /* ================= PAGINATION ================= */
        $products = $query->paginate(12);

        /* ================= ADD FULL IMAGE URL ================= */
        $products->getCollection()->transform(function ($product) {
            if ($product->images) {
                $product->images->transform(function ($image) {
                    $image->image_url = asset('storage/' . $image->image_path);
                    return $image;
                });
            }

            return $product;
        });

        return response()->json([
            'success' => true,
            'data'    => $products,
        ]);
    }

    public function products_main(Request $request)
    {
        // normalize params (important)
        $request->merge([
            'min_price' => $request->min_price ?? $request->price_min,
            'max_price' => $request->max_price ?? $request->price_max,
            'sort'      => $request->sort ?? $request->sortBy,
        ]);

        $query = Product::where('status', 'Published')
            ->with([
                'category:id,name,slug,parent_id',
                'images:id,product_id,image_path,is_primary',
                'videos:id,product_id,video_url',
                'variantCombinations.values.variation:id,name',
            ])
            ->select('products.*')
        // 👇 expose min variant price as a selectable column
            ->selectSub(
                DB::table('product_variant_combinations')
                    ->selectRaw('MIN(extra_price)')
                    ->whereColumn(
                        'product_variant_combinations.product_id',
                        'products.id'
                    ),
                'min_variant_price'
            );

        /* ================= CATEGORY ================= */
        if ($request->filled('category')) {
            $category = Category::where('slug', $request->category)->first();
            if ($category) {
                $query->where('category_id', $category->id);
            }
        }

        /* ================= SLUG ================= */
        if ($request->filled('slug')) {
            $query->where('slug', $request->slug);
        }

        /* ================= PRICE FILTER ================= */
        if ($request->filled('min_price') || $request->filled('max_price')) {
            $min = $request->min_price ?? 0;
            $max = $request->max_price ?? PHP_INT_MAX;

            $query->whereHas('variantCombinations', function ($q) use ($min, $max) {
                $q->whereBetween('extra_price', [$min, $max]);
            });
        }

        /* ================= SORT ================= */
        if ($request->sort === 'price_low_high') {
            $query->orderBy('min_variant_price', 'asc');
        }

        if ($request->sort === 'price_high_low') {
            $query->orderBy('min_variant_price', 'desc');
        }

        /* ================= PAGINATION ================= */
        $products = $query->paginate(12);

        /* ================= IMAGE URL ================= */
        $products->getCollection()->transform(function ($product) {
            $product->images?->transform(function ($img) {
                $img->image_url = asset('storage/' . $img->image_path);
                return $img;
            });
            return $product;
        });

        return response()->json([
            'success' => true,
            'data'    => $products,
        ]);
    }

}
