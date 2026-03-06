<?php
namespace App\Imports;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductSeoMeta;
use App\Models\ProductTaxAffinity;
use App\Models\ProductVariantCombination;
use App\Models\ProductVariantCombinationValue;
use App\Models\ProductVariationValue;
use App\Models\ProductVideo;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Concerns\ToCollection;

class ProductBulkImport implements ToCollection
{
    /**
     * @param Collection $collection
     */

    public $errors = [];
    public function collection(Collection $rows)
    {
        unset($rows[0]); // skip header

        // preload variation values (performance optimization)
        $variationValues = ProductVariationValue::pluck('id', 'value');

        foreach ($rows as $index => $row) {

            DB::beginTransaction();

            try {

                /* ---------------------------
                    | BASIC VALIDATION
                    ---------------------------- */

                if (empty($row[0])) {
                    throw new \Exception("Product name missing");
                }

                if (empty($row[1])) {
                    throw new \Exception("Category name missing");
                }

                if (empty($row[3])) {
                    throw new \Exception("SKU missing");
                }

                /* ---------------------------
                    | GET CATEGORY
                    ---------------------------- */

                $category = Category::where('name', trim($row[1]))->first();

                if (! $category) {
                    throw new \Exception("Category not found: " . $row[1]);
                }

                /* ---------------------------
                    | CREATE OR FIND PRODUCT
                    ---------------------------- */

                $product = Product::firstOrCreate(
                    [
                        'name'        => $row[0],
                        'category_id' => $category->id,
                    ],
                    [
                        'slug'        => Str::slug($row[0]) . '-' . rand(100, 999),
                        'description' => $row[2] ?? null,
                        'status'      => 'Published',
                    ]
                );

                // $product = Product::create([
                //         'name' => trim($row[0]),
                //         'category_id' => $category->id,
                //         'slug' => Str::slug($row[0]) . '-' . uniqid(),
                //         'description' => $row[2] ?? null,
                //         'status' => 'published'
                //     ]);

                /* ---------------------------
                    | SEO + TAX (FIRST TIME)
                    ---------------------------- */

                if ($product->wasRecentlyCreated) {

                    ProductSeoMeta::create([
                        'product_id'       => $product->id,
                        'meta_title'       => $row[17] ?? null,
                        'meta_description' => $row[18] ?? null,
                        'meta_tags'        => $row[19] ?? null,
                    ]);

                    ProductTaxAffinity::create([
                        'product_id'       => $product->id,
                        'gst_enabled'      => 1,
                        'gst_type'         => 'inclusive',
                        'gst_percent'      => $row[20] ?? 0,
                        'affinity_enabled' => 0,
                        'affinity_percent' => 0,
                    ]);
                }

                /* ---------------------------
                    | CREATE VARIANT
                    ---------------------------- */

                $variant = ProductVariantCombination::create([
                    'product_id'     => $product->id,
                    'sku'            => $row[3],
                    'purchase_price' => $row[4] ?? 0,
                    'extra_price'    => $row[5] ?? 0,
                    'discount'       => $row[6] ?? 0,
                    'quantity'       => $row[7] ?? 0,
                    'low_quantity'   => $row[8] ?? 0,
                ]);

                /* ---------------------------
                    | VARIATIONS
                    ---------------------------- */
                // $variationColumns = [9,10,11,12,13,14,15,16];

                // $filledVariations = [];

                // foreach ($variationColumns as $col) {

                //     if (!empty($row[$col])) {
                //         $filledVariations[$col] = trim($row[$col]);
                //     }
                // }

                // if(count($filledVariations) > 0){

                //     foreach ($filledVariations as $value) {

                //         $variationValue = ProductVariationValue::where('value', $value)->first();

                //         if (!$variationValue) {
                //             throw new \Exception("Variation value not found: " . $value);
                //         }

                //         ProductVariantCombinationValue::create([
                //             'variant_combination_id' => $variant->id,
                //             'variation_value_id'     => $variationValue->id
                //         ]);
                //     }
                // }

                // if (! empty($row[9])) {

                //     $value = trim($row[9]);

                //     $variationValue = ProductVariationValue::where('value', $value)->first();

                //     if (! $variationValue) {
                //         throw new \Exception("Variation value not found: " . $value);
                //     }

                //     ProductVariantCombinationValue::create([
                //         'variant_combination_id' => $variant->id,
                //         'variation_value_id'     => $variationValue->id,
                //     ]);
                // }

                if (! empty($row[9])) {

                    $value = trim($row[9]);

                    $variationValueId = $variationValues[$value] ?? null;

                    if (! $variationValueId) {
                        throw new \Exception("Variation value not found: " . $value);
                    }

                    ProductVariantCombinationValue::create([
                        'variant_combination_id' => $variant->id,
                        'variation_value_id'     => $variationValueId,
                    ]);
                }

                /* ---------------------------
                    | VIDEO
                    ---------------------------- */

                if (! empty($row[21]) && $product->wasRecentlyCreated) {

                    ProductVideo::create([
                        'product_id' => $product->id,
                        'video_url'  => $row[21],
                    ]);
                }

                DB::commit();

            } catch (\Exception $e) {

                DB::rollBack();

                $this->errors[] = [
                    'row'     => $index + 1,
                    'message' => $e->getMessage(),
                ];
            }
        }
    }
}
