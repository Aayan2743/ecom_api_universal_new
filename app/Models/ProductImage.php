<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductImage extends Model
{
    protected $guarded = [

    ];

    protected $appends = ['image_url'];

    protected $hidden = ['image_path'];

    public function getImageUrlAttribute()
    {
        return asset('storage/' . $this->image_path);
    }

    protected $casts = [
        'is_primary' => 'boolean',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

}
