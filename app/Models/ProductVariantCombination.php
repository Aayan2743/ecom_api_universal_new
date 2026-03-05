<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductVariantCombination extends Model
{
    protected $guarded = [

    ];

    protected $appends = ['amount'];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    // public function values()
    // {
    //     return $this->belongsToMany(
    //         ProductVariationValue::class,
    //         'product_variant_combination_values',
    //         'variant_combination_id',
    //         'variation_value_id'
    //     );
    // }

    public function variationValue()
    {
        return $this->belongsTo(ProductVariationValue::class, 'variation_value_id');
    }

    // public function images()
    // {
    //     return $this->hasMany(ProductVariantImage::class, 'variant_combination_id');
    // }

    public function values()
    {
        return $this->belongsToMany(
            ProductVariationValue::class,
            'product_variant_combination_values',
            'variant_combination_id',
            'variation_value_id'
        );
    }

    public function images()
    {
        return $this->hasMany(
            ProductVariantImage::class,
            'variant_combination_id'
        );
    }

    // ✅ amount = selling_price (single quantity)
    public function getAmountAttribute()
    {
        $price    = (float) ($this->extra_price ?? 0);
        $discount = (float) ($this->discount ?? 0);

        return max(0, $price - $discount);
    }
}
