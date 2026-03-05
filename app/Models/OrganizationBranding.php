<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrganizationBranding extends Model
{
    protected $guarded = [

    ];

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }

}
