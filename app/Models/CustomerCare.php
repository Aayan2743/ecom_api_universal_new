<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CustomerCare extends Model
{
    protected $fillable = [
        'title',
        'time',
        'working_days',
        'whatsapp_number',
        'email',
    ];
}
