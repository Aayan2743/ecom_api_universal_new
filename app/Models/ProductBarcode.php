<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductBarcode extends Model
{
     protected $fillable = [
        'variant_id',
        'barcode',
        'is_used'
    ];

    public function variant()
    {
        return $this->belongsTo(ProductVariantCombination::class,'variant_id');
    }
}
