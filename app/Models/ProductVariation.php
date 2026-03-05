<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductVariation extends Model
{
    protected $table = 'product_variations';

    protected $fillable = [
        'name',
        'type',
    ];

    public function values()
    {
        return $this->hasMany(ProductVariationValue::class, 'variation_id');
    }
}
