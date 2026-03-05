<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CardPricing extends Model
{
      protected $fillable = [
        'card_amount',
        'min_card',
    ];
}