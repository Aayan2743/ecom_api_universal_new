<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SaleItem extends Model
{
    protected $fillable = [
        'sale_id',

        'product_id',
        'variant_combination_id',

        'product_name',
        'variant_name',
        'sku',
        'product_image',

        'price',
        'discount',
        'tax',
        'quantity',
        'total',
    ];

    protected $casts = [
        'price'    => 'decimal:2',
        'discount' => 'decimal:2',
        'tax'      => 'decimal:2',
        'total'    => 'decimal:2',
    ];

    /* ================= RELATIONSHIPS ================= */

    public function sale()
    {
        return $this->belongsTo(Sale::class);
    }
}
