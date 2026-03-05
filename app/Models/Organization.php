<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Organization extends Model
{
    //

     protected $guarded= [
        
    ];


    public function subscriptions()
{
    return $this->hasMany(OrganizationSubscription::class);
}

public function users()
{
    return $this->hasMany(User::class);
}

public function cards()
{
    return $this->hasMany(DigitalCard::class);
}
}