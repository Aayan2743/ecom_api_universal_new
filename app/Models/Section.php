<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Section extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'status',
    ];

    public function productss()
    {
        return $this->belongsToMany(Product::class, 'product_section');
    }

    public function products()
    {
        return $this->belongsToMany(Product::class, 'product_section')
            ->withTimestamps();
    }
}
