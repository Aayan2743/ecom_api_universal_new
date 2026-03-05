<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ContactSetting extends Model
{
    protected $fillable = [
        'title',
        'description',
        'address',
        'phone_1',
        'phone_2',
        'working_days',
        'working_hours',
        'weekend_note',
        'google_map_url',
    ];
}
