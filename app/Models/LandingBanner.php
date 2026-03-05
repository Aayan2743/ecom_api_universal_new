<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LandingBanner extends Model
{

    public $table       = 'landingbanners';
    protected $fillable = [
        'title',
        'subtitle',
        'small_text',
        'image',
        'button_text',
        'button_link',
        'status',
        'sort_order',
    ];

    protected $appends = ['image_url'];

    public function getImageUrlAttribute()
    {
        return $this->image
            ? asset('storage/' . $this->image)
            : null;
    }
}
