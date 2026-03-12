<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentLink extends Model
{
    protected $fillable = [
        'razorpay_link_id',
        'payment_link',
        'amount',
        'customer_name',
        'customer_phone',
        'status',
        'paid_at',
        'variant_id',
        'address_id',
    ];
}
