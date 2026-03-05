<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Sale extends Model
{
    protected $fillable = [
        'invoice_number',
        'customer_id',
        'subtotal',
        'discount_total',
        'tax_total',
        'grand_total',
        'payment_method',
        'paid_amount',
        'change_amount',
        'customer_name',
        'customer_phone',
        'status',
        'shipping_address_snapshot',
        'tracking_number',
        'shipping_partner',
    ];

    protected $casts = [
        'subtotal'                  => 'decimal:2',
        'discount_total'            => 'decimal:2',
        'tax_total'                 => 'decimal:2',
        'grand_total'               => 'decimal:2',
        'paid_amount'               => 'decimal:2',
        'change_amount'             => 'decimal:2',
        'shipping_address_snapshot' => 'array',
    ];

    /* ================= RELATIONSHIPS ================= */

    public function items()
    {
        return $this->hasMany(SaleItem::class, 'sale_id');
    }

    public function customer()
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

}
