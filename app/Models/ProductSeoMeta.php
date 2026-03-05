<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductSeoMeta extends Model
{
    protected $table = 'product_seo_meta';

    protected $guarded = [

    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
