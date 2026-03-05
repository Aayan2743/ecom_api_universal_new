<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PendingSale extends Model
{
    protected $table = 'pending_sales';

    protected $fillable = [
        'customer_id',
        'order_snapshot',
        'otp',
        'is_verified',
    ];

    protected $casts = [
        'order_snapshot' => 'array', // ✅ auto JSON cast
        'is_verified'    => 'boolean',
    ];

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    public function customer()
    {
        return $this->belongsTo(User::class, 'customer_id');
    }
}
