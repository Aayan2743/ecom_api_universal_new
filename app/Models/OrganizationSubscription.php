<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrganizationSubscription extends Model
{
     protected $guarded= [
        
    ];

      protected $casts = [
        'starts_at' => 'datetime',
        'ends_at'   => 'datetime',
    ];

    public function organization()
{
    return $this->belongsTo(Organization::class);
}

public function cards()
{
    return $this->hasMany(DigitalCard::class, 'subscription_id');
}


}