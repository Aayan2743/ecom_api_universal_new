<?php
namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Section;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class SectionController extends Controller
{
    /* ================= CREATE SECTION ================= */
    public function store(Request $request)
    {

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors'  => $validator->errors()->first(),
            ], 422);
        }

         // Get last position
         $lastPosition = Section::max('position');

        $section = Section::create([
            'name'   => $request->name,
            'slug'   => Str::slug($request->name),
            'status' => 1,
            'position' => $lastPosition ? $lastPosition + 1 : 1, // 👈 important
        ]);

        return response()->json([
            'success' => true,
            'data'    => $section,
        ]);
    }

    public function sort(Request $request)
{
    foreach ($request->order as $item) {
        Section::where('id', $item['id'])
            ->update(['position' => $item['position']]);
    }

    return response()->json(['success' => true]);
}


public function destroy($id)
{
    $section = Section::find($id);

    if (! $section) {
        return response()->json([
            'success' => false,
            'message' => 'Section not found',
        ], 404);
    }

    $section->delete();

    return response()->json([
        'success' => true,
        'message' => 'Section deleted successfully',
    ]);
}

    public function update(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'name'   => 'required|string|max:255|unique:sections,name,' . $id,
            'status' => 'nullable|in:0,1',
             'position' => 'nullable|integer|min:1', // 👈 add this

        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors'  => $validator->errors()->first(),
            ], 422);
        }

        $section = Section::find($id);

        if (! $section) {
            return response()->json([
                'success' => false,
                'message' => 'Section not found',
            ], 404);
        }

        $section->update([
            'name'   => $request->name,
            'slug'   => Str::slug($request->name),
            'status' => $request->status ?? $section->status,
            'position' => $request->position ?? $section->position, // 👈 add this

        ]);

        return response()->json([
            'success' => true,
            'data'    => $section,
        ]);
    }

    /* ================= LIST SECTIONS ================= */
    public function index()
    {
        return response()->json([
            'success' => true,
            'data'    => Section::orderBy('position')->get(),
        ]);
    }

    /* ================= ASSIGN PRODUCT TO SECTIONS ================= */

    public function assignSections(Request $request, $productId)
    {
        $validator = Validator::make($request->all(), [
            'sections'   => 'array',
            'sections.*' => 'exists:sections,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors'  => $validator->errors()->first(),
            ], 422);
        }

        $product = Product::findOrFail($productId);

        // ✅ Get validated data correctly
        $sections = $request->sections ?? [];

        $product->sections()->sync($sections);

        return response()->json([
            'success' => true,
            'message' => 'Sections assigned successfully',
        ]);
    }

    /* ================= GET PRODUCTS BY SECTION ================= */

    public function productsBySection($slug)
    {
        $section = Section::where('slug', $slug)
            ->where('status', 1)
            ->first();

        if (! $section) {
            return response()->json([
                'success' => false,
                'message' => 'Section not found',
            ], 404);
        }

        $products = Product::where('status', 'Published')
            ->whereHas('sections', function ($q) use ($slug) {
                $q->where('slug', $slug);
            })
            ->with([
                'category:id,name,slug,parent_id',
                'images:id,product_id,image_path,is_primary',
                'videos:id,product_id,video_url',
                'variantCombinations.values.variation:id,name',
                'sections:id,name,slug',
            ])
            ->latest()
            ->paginate(12);

        return response()->json([
            'success' => true,
            'section' => $section->name,
            'data'    => $products,
        ]);
    }

    public function homeSections()
    {
        $sections = Section::where('status', 1)

            ->with(['products' => function ($query) {

                $query->where('status', 'Published')
                    ->with([
                        'category:id,name,slug,parent_id',
                        'category.parent:id,name',
                        'brand:id,name',
                        'images:id,product_id,image_path,is_primary',
                        'videos:id,product_id,video_url',
                        'variantCombinations.values.variation:id,name',
                        'sections:id,name,slug',
                    ])
                    ->withMin('variantCombinations as min_price', 'extra_price')
                    ->withMin('variantCombinations as min_discount', 'discount')
                    ->latest()
                    ->take(10);
            }])
            ->get();

        $data = $sections->map(function ($section) {

            return [
                'id'       => $section->id,
                'name'     => $section->name,
                'slug'     => $section->slug,

                'products' => $section->products->map(function ($p) {

                    $image = $p->images->firstWhere('is_primary', true) ?? $p->images->first();

                    return [
                        'id'                   => $p->id,
                        'name'                 => $p->name,
                        'slug'                 => $p->slug,
                        'description'          => $p->description,
                        'category_id'          => $p->category_id,
                        'category_name'        => $p->category?->name,
                        'category_main'        => $p->category?->parent?->name,
                        'brand_id'             => $p->brand_id,
                        'brand_name'           => $p->brand?->name,

                        'price'                => $p->min_price,
                        'discount'             => $p->min_discount,
                        'final_price'          => max(
                            0,
                            ($p->min_price ?? 0) - ($p->min_discount ?? 0)
                        ),

                        'status'               => $p->status,

                        'images'               => $p->images->map(function ($img) {
                            return [
                                'id'         => $img->id,
                                'image_url'  => asset('storage/' . $img->image_path),
                                'is_primary' => $img->is_primary,
                            ];
                        }),

                        'videos'               => $p->videos,

                        'variant_combinations' => $p->variantCombinations,

                        'sections'             => $p->sections,
                    ];
                }),
            ];
        });

        return response()->json([
            'success' => true,
            'data'    => $data,
        ]);
    }
}
