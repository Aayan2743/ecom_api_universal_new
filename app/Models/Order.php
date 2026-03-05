<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    protected $fillable = [
        'user_id',
        'address_id',
        'payment_method',
        'razorpay_order_id',
        'razorpay_payment_id',
        'subtotal',
        'discount',
        'coupon_code',
        'total_amount',
        'status',
        'tracking_id',
        'shiprocket_order_id',
        'awb_code',
        'courier_name',
        'shipment_status',
    ];

    public function items()
    {
        return $this->hasMany(OrderItem::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
    public function address()
    {
        return $this->belongsTo(Address::class, 'address_id');
    }
}
