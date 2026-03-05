<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DigitalCard extends Model
{
    protected $guarded= [
        
    ];

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }

}