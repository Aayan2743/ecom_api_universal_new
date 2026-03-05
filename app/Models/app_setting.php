<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class app_setting extends Model
{
    protected $guarded = [

    ];

    protected $appends = ['app_logo_url', 'app_favicon_url'];

    public static function one()
    {
        return self::firstOrCreate(['id' => 1]);
    }

    /* ================= ACCESSORS ================= */

    public function getAppLogoUrlAttribute()
    {
        if (! $this->app_logo) {
            return null;
        }

        return asset('storage/' . $this->app_logo);
    }

    public function getAppFaviconUrlAttribute()
    {
        if (! $this->app_favicon) {
            return null;
        }

        return asset('storage/' . $this->app_favicon);
    }
}
