<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SocialMediaSetting extends Model
{
    protected $table = 'social_media_settings';

    protected $fillable = [
        'linkedin',
        'dribbble',
        'instagram',
        'twitter',
        'youtube',
    ];
}
