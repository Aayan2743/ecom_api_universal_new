<?php

namespace App\Exports;


use App\Models\ProductVariantCombination;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class ProductsSheetExport implements  WithHeadings
{
    /**
    * @return \Illuminate\Support\Collection
    */


   public function headings(): array
    {
        return [
            'product_name',
            'category_name',
            'description',
            'sku',
            'purchase_price',
            'extra_price',
            'discount',
            'quantity',
            'low_quantity',
            'color',
            'size',
            'volume',
            'weight',
            'material',
            'pattern',
            'pack_size',
            'model',
            'meta_title',
            'meta_description',
            'meta_tags',
            'gst_percent',
            'video_url'
        ];
    }
}
