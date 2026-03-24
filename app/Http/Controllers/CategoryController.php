<?php
namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Product;
use App\Services\WebpService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class CategoryController extends Controller
{
    public function index(Request $request)
    {
        $search  = $request->search;
        $perPage = $request->perPage ?? 5;

        $query = Category::query()
            ->with('parent')
            ->when($search, function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%");
            })
            ->orderBy('id', 'desc');

        $categories = $query->paginate($perPage);

        return response()->json([
            'data'       => $categories->getCollection()->map(function ($cat) {
                return [
                    'id'             => $cat->id,
                    'name'           => $cat->name,
                    'parent_id'      => $cat->parent_id,
                    'parent_name'    => $cat->parent?->name,
                     'is_active_pos'=>$cat->is_active_pos,
                     'is_active_ecom'=>$cat->is_active_ecom,

                    'full_image_url' => $cat->image
                        ? asset('storage/categories/' . $cat->image)
                        : null,
                ];
            }),
            'pagination' => [
                'totalPages'  => $categories->lastPage(),
                'currentPage' => $categories->currentPage(),
            ],
        ]);
    }

    public function index_all_w(Request $request)
    {
        $search = $request->search;

        $categories = Category::query()
            ->with('parent')
            ->when($search, function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%");
            })
            ->orderBy('name', 'asc') // nicer for dropdown
            ->get();

        return response()->json([
            'data' => $categories->map(function ($cat) {
                return [
                    'id'          => $cat->id,
                    'name'        => $cat->name,
                    'parent_id'   => $cat->parent_id,
                    'parent_name' => $cat->parent?->name,
                ];
            }),
        ]);

    }

    public function index_all(Request $request)
    {
        $search = $request->search;

        $categories = Category::query()
            ->with('parent')
               ->where('is_active_pos', 1) // ✅ ONLY POS
            ->when($search, function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%");
            })
            ->orderBy('sort_order', 'asc')
            ->get();

        return response()->json([
            'data' => $categories->map(function ($cat) {
                return [
                    'id'          => $cat->id,
                    'name'        => $cat->name,
                    'parent_id'   => $cat->parent_id,
                    'parent_name' => $cat->parent?->name,
                    'image'       => $cat->image
                        ? asset('storage/categories/' . $cat->image)
                        : null,
                ];
            }),
        ]);
    }


      public function index_all_sort(Request $request)
    {
        $search = $request->search;

        $categories = Category::query()
            ->with('parent')
            //    ->where('is_active_pos', 1) // ✅ ONLY POS
            ->when($search, function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%");
            })
            ->orderBy('sort_order', 'asc')
            ->get();

        return response()->json([
            'data' => $categories->map(function ($cat) {
                return [
                    'id'          => $cat->id,
                    'name'        => $cat->name,
                    'parent_id'   => $cat->parent_id,
                    'parent_name' => $cat->parent?->name,
                    'is_active_pos' => $cat->is_active_pos,
                    'is_active_ecom' => $cat->is_active_ecom,
                    'image'       => $cat->image
                        ? asset('storage/categories/' . $cat->image)
                        : null,
                ];
            }),
        ]);
    }





    /* ================= CREATE CATEGORY ================= */
    public function stores(Request $request)
    {

        $validator = Validator::make($request->all(), [
            'name'      => 'required|string|max:255',
            'parent_id' => 'nullable|exists:categories,id',
            'image'     => 'nullable|image|max:5048',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
            ], 422);
        }

        $imageName = null;

        if ($request->hasFile('image')) {

            $file     = $request->file('image');
            $baseName = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);

            // ✅ save only webp
            $imageName = time() . '_' . Str::slug($baseName) . '.webp';

            $src  = $file->getPathname();
            $dest = storage_path('app/public/categories/' . $imageName);

            // 🔥 convert + resize
            WebpService::convert(
                $src,
                $dest,
                60, // quality

            );
        }

        Category::create([
            'name'      => $request->name,
            'slug'      => Str::slug($request->name),
            'parent_id' => $request->parent_id,
            'image'     => $imageName,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Category created successfully',
        ]);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name'      => 'required|string|max:255',
            'parent_id' => 'nullable|exists:categories,id',
            'image'     => 'nullable|image|max:5048',

            // ✅ Check slug uniqueness
            'name'      => [
                'required',
                'string',
                'max:255',
                function ($attribute, $value, $fail) {
                    $slug = Str::slug($value);

                    if (Category::where('slug', $slug)->exists()) {
                        $fail('Category already exists.');
                    }
                },
            ],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
            ], 422);
        }

        $imageName = null;

        if ($request->hasFile('image')) {

            $file     = $request->file('image');
            $baseName = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);

            $imageName = time() . '_' . Str::slug($baseName) . '.webp';

            $src  = $file->getPathname();
            $dest = storage_path('app/public/categories/' . $imageName);

            WebpService::convert($src, $dest, 60);
        }

        Category::create([
            'name'      => $request->name,
            'slug'      => Str::slug($request->name),
            'parent_id' => $request->parent_id,
            'image'     => $imageName,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Category created successfully',
        ]);
    }

    /* ================= UPDATE CATEGORY ================= */
    public function updates(Request $request, $id)
    {
        $category = Category::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'name'      => 'required|string|max:255',
            'parent_id' => 'nullable|exists:categories,id|not_in:' . $id,
            'image'     => 'nullable|image|max:5048',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
            ], 422);
        }

        $imageName = $category->image;

        if ($request->hasFile('image')) {

            // 🗑️ delete old image
            if ($category->image) {
                Storage::disk('public')->delete('categories/' . $category->image);
            }

            $file     = $request->file('image');
            $baseName = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);

            // ✅ always save as webp
            $imageName = time() . '_' . Str::slug($baseName) . '.webp';

            $src  = $file->getPathname();
            $dest = storage_path('app/public/categories/' . $imageName);

            // 🔥 convert + resize
            WebpService::convert(
                $src,
                $dest,
                60, // quality

            );
        }

        $category->update([
            'name'      => $request->name,
            'slug'      => Str::slug($request->name),
            'parent_id' => $request->parent_id,
            'image'     => $imageName,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Category updated successfully',
        ]);
    }

    public function update(Request $request, $id)
    {

        $category = Category::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'name'      => 'required|string|max:255',
            'parent_id' => 'nullable|exists:categories,id|not_in:' . $id,
            'image'     => 'nullable|image|max:5048',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
            ], 422);
        }

        // ✅ Check duplicate slug (ignore current category)
        $slug = Str::slug($request->name);

        $exists = Category::where('slug', $slug)
            ->where('id', '!=', $id)
            ->exists();

        if ($exists) {
            return response()->json([
                'success' => false,
                'message' => 'Category already exists.',
            ], 422);
        }

        $imageName = $category->image;

        if ($request->hasFile('image')) {

            // delete old image
            if ($category->image) {
                Storage::disk('public')->delete('categories/' . $category->image);
            }

            $file     = $request->file('image');
            $baseName = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);

            $imageName = time() . '_' . Str::slug($baseName) . '.webp';

            $src  = $file->getPathname();
            $dest = storage_path('app/public/categories/' . $imageName);

            WebpService::convert($src, $dest, 60);
        }

        $category->update([
            'name'      => $request->name,
            'slug'      => $slug,
            'parent_id' => $request->parent_id,
            'image'     => $imageName,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Category updated successfully',
        ]);
    }


    public function updateOrder(Request $request)
{
    foreach ($request->order as $item) {
        Category::where('id', $item['id'])
            ->update(['sort_order' => $item['position']]);
    }

    return response()->json(['success' => true]);
}

    /* ================= DELETE CATEGORY ================= */
    /* ================= DELETE CATEGORY ================= */
    public function destroy($id)
    {
        $category = Category::findOrFail($id);

        // Prevent deleting parent with children
        if ($category->children()->count() > 0) {
            return response()->json([
                'message' => 'Delete subcategories first',
            ], 422);
        }

        // ❌ DO NOT delete image file in soft delete
        // Only mark record as deleted

        $category->forceDelete();

        return response()->json([
            'success' => true,
            'message' => 'Category deleted successfully',
        ]);
    }

    /* ================= SUB  CATEGORY ADDING ================= */
    public function addSubCategorys(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'parent_id'       => 'required|exists:categories,id',
            'subcategories'   => 'required|array|min:1',
            'subcategories.*' => 'required|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
            ], 422);
        }

        $insertData = [];

        foreach ($request->subcategories as $name) {
            $insertData[] = [
                'name'       => $name,
                'slug'       => Str::slug($name),
                'parent_id'  => $request->parent_id,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        Category::insert($insertData);

        return response()->json([
            'success' => true,
            'message' => 'Sub categories added successfully',
        ]);
    }


public function toggle(Request $request)
{
    try {
        // ✅ validate input




          $validator = Validator::make($request->all(), [
             'id' => 'required|exists:categories,id',
            'type' => 'required|in:pos,ecom'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
            ], 422);
        }

        $category = Category::findOrFail($request->id);

        // ✅ toggle logic
        if ($request->type === 'pos') {
            $newStatus = !$category->is_active_pos;

            $category->is_active_pos = $newStatus;

            \App\Models\Product::where('category_id', $category->id)
                ->update(['is_active_pos' => $newStatus]);
        }

        if ($request->type === 'ecom') {
            $newStatus = !$category->is_active_ecom;

            $category->is_active_ecom = $newStatus;

            \App\Models\Product::where('category_id', $category->id)
                ->update(['is_active_ecom' => $newStatus]);
        }

        $category->save();

        return response()->json([
            'success' => true,
            'message' => 'Category updated successfully',
            'data' => $category
        ]);

    } catch (\Exception $e) {

        return response()->json([
            'success' => false,
            'error' => 'SERVER_ERROR',
            'message' => $e->getMessage()
        ], 500);
    }
}

}
