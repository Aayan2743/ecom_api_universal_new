<?php

namespace App\Exports;

use App\Models\Category;
use App\Models\ProductVariation;
use App\Models\ProductVariationValue;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;

class VariationsSheetExport implements FromArray, WithHeadings
{
   public function array(): array
    {
        $data = [];

        /* Categories */

        foreach (Category::pluck('name') as $category) {

            $data[] = [
                'variation_name' => 'CATEGORY',
                'variation_value' => $category
            ];
        }

        /* Variations */

        $variations = ProductVariation::with('values')->get();

        foreach ($variations as $variation) {

            foreach ($variation->values as $value) {

                $data[] = [
                    'variation_name' => $variation->name,
                    'variation_value' => $value->value
                ];
            }
        }

        return $data;
    }

    public function headings(): array
    {
        return [
            'variation_name',
            'variation_value'
        ];
    }
}
