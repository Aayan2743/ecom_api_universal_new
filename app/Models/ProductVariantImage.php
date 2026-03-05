<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductVariantImage extends Model
{
    protected $fillable = [
        'variant_combination_id',
        'image_path',
        'created_at',
        'updated_at',
    ];

    public function variant()
    {
        return $this->belongsTo(
            ProductVariantCombination::class,
            'variant_combination_id'
        );
    }
}
