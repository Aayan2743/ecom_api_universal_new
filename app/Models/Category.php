<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Category extends Model
{

    use SoftDeletes;
    protected $guarded = [

    ];

    /* Parent category */
    public function parent()
    {
        return $this->belongsTo(Category::class, 'parent_id');
    }

    /* Child categories (subcategories) */
    public function children()
    {
        return $this->hasMany(Category::class, 'parent_id')
            ->orderBy('sort_order');
    }

    public function childrenRecursive()
    {
        return $this->children()->with('childrenRecursive');
    }

    protected $dates = ['deleted_at'];
}
