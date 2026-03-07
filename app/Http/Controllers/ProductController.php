<?php
namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;

class ProductController extends Controller
{

    public function index(Request $request)
    {
        $search  = $request->search;
        $perPage = $request->perPage ?? 10;

        // $products = Product::with([
        //     'category:id,name,parent_id',
        //     'category.parent:id,name',
        //     'brand:id,name',
        //     'images:id,product_id,image_path,is_primary',
        // ])
        //     ->withMin('variantCombinations as min_price', 'extra_price')
        //     ->withMin('variantCombinations as min_discount', 'discount')
        //     ->orderBy('id', 'desc')
        //     ->paginate($perPage);

        $products = Product::query()
            ->whereNull('deleted_at') // explicit (optional but clean)
            ->with([
                'category:id,name,parent_id',
                'category.parent:id,name',
                'brand:id,name',
                'images:id,product_id,image_path,is_primary',
                'sections:id,name,slug',
            ])
            ->withMin('variantCombinations as min_price', 'extra_price')
            ->withMin('variantCombinations as min_discount', 'discount')
            ->orderBy('id', 'desc')
            ->paginate($perPage);

        return response()->json([
            'data'       => $products->getCollection()->map(fn($p) => [
                'id'            => $p->id,
                'name'          => $p->name,
                'slug'          => $p->slug,
                'category_id'   => $p->category_id,
                'category_name' => $p->category?->name,
                'category_main' => $p->category?->parent?->name,
                'brand_id'      => $p->brand_id,
                'brand_name'    => $p->brand?->name,

                // 🔥 VARIATION PRICES
                'price'         => $p->min_price,
                'discount'      => $p->min_discount,
                'final_price'   => max(
                    0,
                    ($p->min_price ?? 0) - ($p->min_discount ?? 0)
                ),
                'image_url'     => optional(
                    $p->images->firstWhere('is_primary', true) ?? $p->images->first()
                )->image_path
                    ? asset('storage/' . optional(
                    $p->images->firstWhere('is_primary', true) ?? $p->images->first()
                )->image_path)
                    : null,

                'status'        => $p->status,
                // 🔥 ADD THIS ONLY
                'sections'      => $p->sections->map(fn($s) => [
                    'id'   => $s->id,
                    'name' => $s->name,
                    'slug' => $s->slug,
                ])->values(),
            ]),
            'pagination' => [
                'currentPage' => $products->currentPage(),
                'totalPages'  => $products->lastPage(),
            ],
        ]);
    }

    public function fetchById($id)
    {
        $product = Product::withTrashed()->with([

            'category:id,name,parent_id',
            'category.parent:id,name',
            'brand:id,name',
            'images:id,product_id,image_path,is_primary',
            'videos:id,product_id,video_url',

            'variantCombinations:id,product_id,sku,purchase_price,extra_price,discount,quantity,low_quantity',

            // 🔥 REQUIRED FOR EDIT FLOW

            'variantCombinations.values:id,value,variation_id',
            'variantCombinations.images:id,variant_combination_id,image_path',

            'seo',
            'taxAffinity',

        ])
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'data'    => [
                'id'                  => $product->id,
                'name'                => $product->name,

                'category_id'         => $product->category_id,
                'category'            => $product->category
                    ? [
                    'id'        => $product->category->id,
                    'name'      => $product->category->name,
                    'parent_id' => $product->category->parent_id,
                    'main_name' => $product->category?->parent?->name,
                ]
                    : null,

                'brand_id'            => $product->brand_id,
                'brand'               => $product->brand
                    ? [
                    'id'   => $product->brand->id,
                    'name' => $product->brand->name,
                ]
                    : null,

                'description'         => $product->description,
                'specifications'      => $product->specifications ?? [],
                'extra_details'       => $product->extra_details ?? [],
                'base_price'          => $product->base_price,
                'discount'            => $product->discount,
                'status'              => $product->status,
                'video'               => $product->videos->map(fn($v) => [
                    'id'        => $v->id,
                    'video_url' => $v->video_url,
                ]),

                /* Gallery */
                'gallery'             => $product->images->map(fn($img) => [
                    'id'         => $img->id,
                    'url'        => asset('storage/' . $img->image_path),
                    'is_primary' => $img->is_primary,
                ]),

                /* Variations */
                // 'variantCombinations' => $product->variantCombinations->map(fn($v) => [
                //     'id'             => $v->id,
                //     'sku'            => $v->sku,
                //     'purchase_price' => $v->purchase_price,
                //     'extra_price'    => $v->extra_price,
                //     'discount'       => $v->discount,
                //     'quantity'       => $v->quantity,
                //     'low_quantity'   => $v->low_quantity,
                // ]),

                'variantCombinations' => $product->variantCombinations->map(function ($combo) {
                    return [
                        'id'                 => $combo->id,
                        'sku'                => $combo->sku,
                        'purchase_price'     => $combo->purchase_price,
                        'extra_price'        => $combo->extra_price,
                        'discount'           => $combo->discount,
                        'quantity'           => $combo->quantity,
                        'low_quantity'       => $combo->low_quantity,

                        // 🔥 THIS FIXES EDIT PREFILL
                        'combination_values' => $combo->values->map(fn($v) => [
                            'value' => [
                                'id'           => $v->id,
                                'value'        => $v->value,
                                'variation_id' => $v->variation_id,
                            ],
                        ]),

                        'images'             => $combo->images->map(fn($img) => [
                            'id'        => $img->id,
                            'image_url' => asset('storage/' . $img->image_path),
                        ]),
                    ];
                }),

                'meta'                => $product->seo ?? (object) [],
                'product_tax'         => $product->taxAffinity,
            ],
        ]);

    }

    /* ================= STORE ================= */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name'             => 'required|string|max:255',
            'category_id'      => 'required|exists:categories,id',
            'brand_id'         => 'nullable|exists:brands,id',

            // 'purchase_price'   => 'nullable|numeric|min:0',
            // 'base_price'       => 'nullable|numeric|min:0',
            // 'discount'         => 'nullable|numeric|min:0',
            'status'           => 'nullable|in:draft,active,inactive',

            'specifications'   => 'nullable|array',
            'specifications.*' => 'nullable|string',

            'extra_details'    => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors'  => $validator->errors()->first(),
            ], 422);
        }

        $product = Product::create([
            'name'           => $request->name,
            'slug'           => Str::slug($request->name),
            'category_id'    => $request->category_id,
            'brand_id'       => $request->brand_id,

            // 'purchase_price' => $request->purchase_price,
            // 'base_price'     => $request->base_price,
            // 'discount'       => $request->discount,
            'status'         => $request->status ?? 'draft',

            'specifications' => $request->specifications ?? [],
            'extra_details'  => $request->extra_details ?? [],
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Product created successfully',
            'product' => [
                'id' => $product->id,
            ],
        ]);
    }

    /* ================= UPDATE ================= */
    public function update(Request $request, $id)
    {
        $product = Product::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'name'             => 'required|string|max:255',
            'category_id'      => 'required|exists:categories,id',
            'brand_id'         => 'nullable|exists:brands,id',

            // 'purchase_price'   => 'nullable|numeric|min:0',
            // 'base_price'       => 'nullable|numeric|min:0',
            // 'discount'         => 'nullable|numeric|min:0',
            'status'           => 'nullable|in:draft,active,inactive',

            'specifications'   => 'nullable|array',
            'specifications.*' => 'nullable|string',

            'extra_details'    => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors'  => $validator->errors()->first(),
            ], 422);
        }

        $product->update([
            'name'           => $request->name,
            'slug'           => Str::slug($request->name),
            'category_id'    => $request->category_id,
            'brand_id'       => $request->brand_id,

            // 'purchase_price' => $request->purchase_price,
            // 'base_price'     => $request->base_price,
            // 'discount'       => $request->discount,
            'status'         => $request->status ?? $product->status,

            'specifications' => $request->specifications ?? [],
            'extra_details'  => $request->extra_details ?? [],
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Product updated successfully',
            'product' => [
                'id' => $product->id,
            ],
        ]);
    }

    /* ================= DELETE ================= */

    public function destroy($id)
    {
        Product::findOrFail($id)->delete();
    }
    /* ================= RESTORE ================= */
    public function restore($id)
    {
        $product = Product::withTrashed()->findOrFail($id);
        $product->restore();

        return response()->json([
            'success' => true,
            'message' => 'Product restored successfully',
        ]);
    }

    public function publish(Request $request, $id)
    {
        $product = Product::findOrFail($id);

        // ⛔ Prevent double publish
        if ($product->status === 'Published') {
            return response()->json([
                'success' => false,
                'message' => 'Product is already published',
            ], 409);
        }

        if (! $product->variantCombinations()->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Add product variants before publishing',
            ], 422);
        }

        // ✅ Publish
        $product->update([
            'status' => 'Published',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Product published successfully',
            'data'    => [
                'id'     => $product->id,
                'status' => $product->status,
            ],
        ]);
    }

    public function upload(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'file' => 'required|file|mimes:xlsx,xls,csv',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Invalid file',
                'errors'  => $validator->errors()->all(),
            ], 422);
        }

        $rows = Excel::toArray([], $request->file('file'))[0];

        $header = array_map('strtolower', $rows[0]);
        unset($rows[0]);

        $inserted = 0;
        $errors   = [];

        foreach ($rows as $index => $row) {
            $data = array_combine($header, $row);

            $rowValidator = Validator::make($data, [
                'name'        => 'required|string|max:255',
                'category_id' => 'required|integer',
                'brand_id'    => 'nullable|integer',

            ]);

            if ($rowValidator->fails()) {
                $errors[] = [
                    'row'    => $index + 2,
                    'errors' => $rowValidator->errors(),
                ];
                continue;
            }

            Product::create([
                'name'        => $data['name'],
                'slug'        => Str::slug($data['name']), // ✅ FIX
                'category_id' => $data['category_id'],
                'brand_id'    => $data['brand_id'] ?? null,
                'description' => $data['description'] ?? '',

            ]);

            $inserted++;
        }

        return response()->json([
            'success'  => true,
            'inserted' => $inserted,
            'errors'   => $errors,
        ]);
    }

    public function posProducts(Request $request)
    {
        $category = $request->category;

        $products = Product::with([
            'images',
            'variantCombinations.images',
        ])
            ->when($category !== 'all', function ($q) use ($category) {
                $q->where('category_id', $category);
            })
            ->where('status', 'Published')
            ->get();

        return response()->json([
            'data' => $products->map(function ($p) {
                return [
                    'id'        => $p->id,
                    'name'      => $p->name,
                    'image_url' => optional($p->images->first())->image_path
                        ? asset('storage/' . $p->images->first()->image_path)
                        : null,

                    'variants'  => $p->variantCombinations->map(function ($v) {
                        return [
                            'id'     => $v->id,
                            'name'   => $v->sku,
                            'price'  => $v->extra_price,
                            'stock'  => $v->quantity,

                            // 🔥 VARIANT IMAGES
                            'images' => $v->images->map(fn($img) => [
                                'id'        => $img->id,
                                'image_url' => asset('storage/' . $img->image_path),
                            ]),
                        ];
                    }),
                ];
            }),
        ]);
    }

    public function collectionssss(Request $request)
    {
        $query = Product::query();

        // ================= CATEGORY FILTER =================
        if ($request->category) {

            // find category by slug
            $category = Category::where('slug', $request->category)->first();

            if ($category) {

                // 🔥 If MAIN CATEGORY
                if (is_null($category->parent_id)) {

                    // get all subcategories
                    $subCategoryIds = Category::where('parent_id', $category->id)
                        ->pluck('id')
                        ->toArray();

                    // include main category also (optional)
                    $subCategoryIds[] = $category->id;

                    $query->whereIn('category_id', $subCategoryIds);

                } else {
                    // 🔥 If SUBCATEGORY
                    $query->where('category_id', $category->id);
                }
            }
        }

        // ================= SEARCH (OPTIONAL) =================
        if ($request->search) {
            $query->where('name', 'like', '%' . $request->search . '%');
        }

                                              // ================= ONLY ACTIVE PRODUCTS =================
        $query->where('status', 'published'); // or active

        // ================= PAGINATION =================
        $products = $query->paginate(12);

        return response()->json([
            'success'    => true,
            'data'       => $products->items(),
            'pagination' => [
                'current_page' => $products->currentPage(),
                'total_pages'  => $products->lastPage(),
                'total'        => $products->total(),
            ],
        ]);
    }

    public function posProductsSearch_old(Request $request)
    {
        $search = $request->search;

        $products = Product::where('name', 'like', "%$search%")
            ->select('id', 'name')
            ->limit(20)
            ->get();

        return response()->json([
            'data' => $products,
        ]);
    }

    public function posProductsSearch(Request $request)
    {
        $search = $request->search;

        $products = Product::with([
            'variants',
            'variants.images',
            'variants.values',
        ])
            ->select('id', 'name')
            ->where('name', 'like', "%{$search}%")
            ->limit(20)
            ->get()
            ->map(function ($product) {

                return [
                    'id'        => $product->id,
                    'name'      => $product->name,

                    // first variant image as product image
                    'image_url' => optional($product->variants->first()->images->first())->image_path
                        ? asset('storage/products/variant-images/' . $product->variants->first()->images->first()->image_path)
                        : null,

                    'variants'  => $product->variants->map(function ($variant) {

                        return [
                            'id'     => $variant->id,

                            // variant name from variation values
                            'name'   => $variant->values->pluck('value')->implode(' / '),

                            'price'  => $variant->amount,

                            'stock'  => $variant->quantity ?? 0,

                            'images' => $variant->images->map(function ($img) {
                                return [
                                    'id'        => $img->id,
                                    'image_url' => asset('storage/products/variant-images/' . $img->image_path),
                                ];
                            }),
                        ];
                    }),
                ];
            });

        return response()->json([
            'data' => $products,
        ]);
    }

    public function collection(Request $request)
    {
        $query = Product::query()
            ->with([
                'category',
                'images',
                'videos',
                'variantCombinations.values.variation',
            ])
            ->where('status', 'Published');

        // ================= CATEGORY FILTER =================
        if ($request->category) {

            $category = Category::where('slug', $request->category)->first();

            if ($category) {

                $categoryIds = [$category->id];

                // If main category, include subcategories
                if (is_null($category->parent_id)) {
                    $subIds = Category::where('parent_id', $category->id)
                        ->pluck('id')
                        ->toArray();

                    $categoryIds = array_merge($categoryIds, $subIds);
                }

                $query->whereIn('category_id', $categoryIds);
            }
        }

        $products = $query->latest()->paginate(12);

        return response()->json([
            'success'    => true,
            'data'       => $products->map(function ($product) {

                return [
                    'id'                   => $product->id,
                    'name'                 => $product->name,
                    'slug'                 => $product->slug,
                    'category_id'          => $product->category_id,
                    'brand_id'             => $product->brand_id,
                    'description'          => $product->description,
                    'purchase_price'       => $product->purchase_price,
                    'base_price'           => $product->base_price,
                    'discount'             => $product->discount,
                    'status'               => $product->status,
                    'created_at'           => $product->created_at,
                    'updated_at'           => $product->updated_at,
                    'specifications'       => $product->specifications ?? [],
                    'extra_details'        => $product->extra_details ?? [],
                    'deleted_at'           => $product->deleted_at,

                    'category'             => $product->category,

                    'images'               => $product->images,

                    'videos'               => $product->videos,

                    'variant_combinations' => $product->variantCombinations->map(function ($variant) {

                        return [
                            'id'             => $variant->id,
                            'product_id'     => $variant->product_id,
                            'sku'            => $variant->sku,
                            'purchase_price' => $variant->purchase_price,
                            'extra_price'    => $variant->extra_price,
                            'discount'       => $variant->discount,
                            'quantity'       => $variant->quantity,
                            'low_quantity'   => $variant->low_quantity,
                            'created_at'     => $variant->created_at,
                            'updated_at'     => $variant->updated_at,

                            // 🔥 calculated final amount
                            'amount'         => $variant->extra_price -
                            (($variant->extra_price * $variant->discount) / 100),

                            'values'         => $variant->values->map(function ($value) {
                                return [
                                    'id'           => $value->id,
                                    'variation_id' => $value->variation_id,
                                    'value'        => $value->value,
                                    'color_code'   => $value->color_code,
                                    'created_at'   => $value->created_at,
                                    'updated_at'   => $value->updated_at,
                                    'variation'    => $value->variation,
                                ];
                            }),
                        ];
                    }),
                ];
            }),

            'pagination' => [
                'current_page' => $products->currentPage(),
                'total_pages'  => $products->lastPage(),
                'total'        => $products->total(),
            ],
        ]);
    }

}
