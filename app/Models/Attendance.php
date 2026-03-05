<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Attendance extends Model
{
    protected $fillable = [
        'user_id',
        'date',
        'status',
        'in_time',
        'out_time',
        'ot_amount',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
