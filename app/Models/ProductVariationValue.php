<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductVariationValue extends Model
{
    protected $table = 'product_variation_values';

    protected $guarded = [

    ];

    /**
     * Relation to product_variations
     */
    public function variation()
    {
        return $this->belongsTo(ProductVariation::class, 'variation_id');
    }
}
