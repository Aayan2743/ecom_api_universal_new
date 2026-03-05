<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Brand extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'image',
        'status',
        'created_at',
        'updated_at',
    ];

    /* Casts */
    protected $casts = [
        'status' => 'string',
    ];

    /* Relationships */

    // Brand → Products
    public function products()
    {
        return $this->hasMany(Product::class);
    }
}
