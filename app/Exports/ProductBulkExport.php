<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class ProductBulkExport implements WithMultipleSheets
{
    /**
    * @return \Illuminate\Support\Collection
    */


    public function sheets(): array
    {
        return [
            // new ProductsSheetExport(),
            new VariationsSheetExport(),
        ];
    }
    public function collection()
    {
        //
    }
}
